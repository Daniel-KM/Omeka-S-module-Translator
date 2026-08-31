<?php declare(strict_types=1);

namespace Translator\Job;

use Common\Stdlib\PsrMessage;
use Doctrine\DBAL\ParameterType;
use Omeka\Job\AbstractJob;
use Translator\Module;

/**
 * Translate all existing resources in batch.
 *
 * Unlike the event-based translation that processes one resource at a
 * time, this job collects all unique texts across resources, deduplicates
 * them globally, filters existing translations in bulk, then sends them
 * to DeepL in batches to minimize API calls.
 */
class TranslateAll extends AbstractJob
{
    /**
     * Number of resources fetched per iteration.
     *
     * @var int
     */
    const RESOURCE_BATCH = 100;

    /**
     * Max texts per DeepL API call.
     *
     * @var int
     */
    const DEEPL_BATCH = 50;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \Omeka\Settings\Settings
     */
    protected $settings;

    /**
     * @var bool
     */
    protected $quotaExceeded = false;

    public function perform(): void
    {
        $services = $this->getServiceLocator();
        $this->api = $services->get('Omeka\ApiManager');
        $this->connection = $services->get('Omeka\Connection');
        $this->entityManager = $services->get('Omeka\EntityManager');
        $this->logger = $services->get('Omeka\Logger');
        $this->settings = $services->get('Omeka\Settings');

        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('translator/translate-all/job_' . $this->job->getId());
        $this->logger->addProcessor($referenceIdProcessor);

        $deeplApiKey = $services->get('Omeka\Cipher')->decrypt((string) $this->settings->get('translator_deepl_api_key'));
        if (!$deeplApiKey) {
            $this->logger->err(
                'No DeepL API key configured.' // @translate
            );
            return;
        }

        $pairs = $this->normalizeLanguagePairs();
        if (!$pairs) {
            $this->logger->err(
                'No language pairs configured.' // @translate
            );
            return;
        }

        $propertiesToInclude = $this->settings->get('translator_properties_include', []);
        $sizeInclude = $this->settings->get('translator_properties_include_size', '');
        if ($sizeInclude) {
            $propertiesToInclude[] = $sizeInclude;
        }
        if (!$propertiesToInclude) {
            $this->logger->err(
                'No properties to translate configured.' // @translate
            );
            return;
        }

        $resourceTypes = $this->getArg('resource_types')
            ?: ['items', 'item_sets', 'media', 'digital_objects'];

        $this->logger->notice(
            'Starting batch translation for: {types}.', // @translate
            ['types' => implode(', ', $resourceTypes)]
        );

        // Phase 1: collect all unique texts from resources.
        $textsToTranslate = $this->collectTextsSql(
            $resourceTypes,
            $pairs,
            $propertiesToInclude
        );

        if (!$textsToTranslate) {
            $this->logger->notice(
                'No new texts to translate.' // @translate
            );
            return;
        }

        // Summary before translation.
        $totalTexts = 0;
        $totalChars = 0;
        foreach ($textsToTranslate as $data) {
            $totalTexts += count($data['texts']);
            $totalChars += array_sum(array_map('mb_strlen', $data['texts']));
        }
        $this->logger->notice(
            'Collected {texts} unique texts ({chars} characters) across {pairs} language pair(s).', // @translate
            [
                'texts' => $totalTexts,
                'chars' => number_format($totalChars),
                'pairs' => count($textsToTranslate),
            ]
        );

        // Phase 2: for each pair, filter existing translations and
        // translate via DeepL in batches.
        $totalTranslated = 0;
        $perPairTranslated = [];
        $perPairRemaining = [];
        foreach ($textsToTranslate as $data) {
            $langSource = $data['source'];
            $langTarget = $data['target'];
            $pairLabel = ($langSource ?: 'auto') . ' → ' . $langTarget;
            $perPairTranslated[$pairLabel] ??= 0;
            $perPairRemaining[$pairLabel] ??= 0;

            if ($this->shouldStop()) {
                $this->logger->warn(
                    'Job stopped by user.' // @translate
                );
                $perPairRemaining[$pairLabel] += count($data['texts']);
                break;
            }
            if ($this->quotaExceeded) {
                $perPairRemaining[$pairLabel] += count($data['texts']);
                continue;
            }

            $texts = $data['texts'];

            // Filter existing translations via direct SQL.
            $texts = $this->filterExistingTranslationsSql(
                $texts,
                $langSource,
                $langTarget
            );
            if (!$texts) {
                continue;
            }

            $this->logger->info(
                '{count} texts to translate for {source} → {target}.', // @translate
                [
                    'count' => count($texts),
                    'source' => $langSource ?: 'auto',
                    'target' => $langTarget,
                ]
            );

            $countTexts = count($texts);
            $translated = $this->translateAndStore(
                $texts,
                $langSource,
                $langTarget
            );
            $totalTranslated += $translated;
            $perPairTranslated[$pairLabel] += $translated;
            $perPairRemaining[$pairLabel] += max(0, $countTexts - $translated);
        }

        $this->logger->notice(
            'Batch translation completed: {count} translations created.', // @translate
            ['count' => $totalTranslated]
        );

        $this->logSessionSummary($perPairTranslated, $perPairRemaining);
        $this->logDatabaseTotals();

        if ($this->quotaExceeded) {
            $this->logger->warn(
                'Job aborted: DeepL quota exceeded. Re-run later when quota resets.' // @translate
            );
        }
    }

    /**
     * Log a per-pair summary of translations created and not translated
     * during this job session.
     */
    protected function logSessionSummary(array $translated, array $remaining): void
    {
        if (!$translated && !$remaining) {
            return;
        }
        $this->logger->notice(
            '--- Session summary ---' // @translate
        );
        foreach ($translated as $pair => $count) {
            $this->logger->notice(
                '{pair}: {translated} translated, {remaining} not translated.', // @translate
                [
                    'pair' => $pair,
                    'translated' => $count,
                    'remaining' => $remaining[$pair] ?? 0,
                ]
            );
        }
    }

    /**
     * Log total translations stored in DB grouped by source and target
     * language.
     */
    protected function logDatabaseTotals(): void
    {
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT t.lang AS source_lang, tr.lang AS target_lang, COUNT(*) AS n'
                . ' FROM translation tr'
                . ' INNER JOIN translate_text t ON t.id = tr.text_id'
                . ' GROUP BY t.lang, tr.lang'
                . ' ORDER BY t.lang, tr.lang'
            );
        } catch (\Throwable $e) {
            return;
        }
        if (!$rows) {
            return;
        }
        $this->logger->notice(
            '--- Database totals (all sessions) ---' // @translate
        );
        foreach ($rows as $row) {
            $this->logger->notice(
                '{source} → {target}: {count} translations stored.', // @translate
                [
                    'source' => $row['source_lang'] ?? 'auto',
                    'target' => $row['target_lang'],
                    'count' => (int) $row['n'],
                ]
            );
        }
    }

    /**
     * Collect all unique texts from all resources, grouped by lang pair.
     */
    protected function collectTexts(
        array $resourceTypes,
        array $pairs,
        array $propertiesToInclude
    ): array {
        $services = $this->getServiceLocator();
        $easyMeta = $services->get('Common\EasyMeta');

        $defaultLangSource = $this->settings->get('translator_lang_source_default');
        $isSkipEmptyLang = $defaultLangSource === 'skip'
            || ($defaultLangSource
                && !isset(Module::$langsSupportedInput[$defaultLangSource]));
        if (!$defaultLangSource
            || $defaultLangSource === 'auto'
            || $defaultLangSource === 'skip'
        ) {
            $defaultLangSource = null;
        }

        if (in_array('properties', $propertiesToInclude)) {
            $propertiesToInclude = $easyMeta->propertyTerms();
        }
        $propertiesToExclude = $this->settings->get('translator_properties_exclude', []);
        $sizeExclude = $this->settings->get('translator_properties_exclude_size', '');
        if ($sizeExclude) {
            $propertiesToExclude[] = $sizeExclude;
        }

        $propertySizes = [
            'properties_max_500' => 500,
            'properties_max_1000' => 1000,
            'properties_max_5000' => 5000,
            'properties_min_500' => 500,
            'properties_min_1000' => 1000,
            'properties_min_5000' => 5000,
        ];
        $propertiesToInclude = array_combine($propertiesToInclude, $propertiesToInclude);
        $propertiesToInclude = array_diff_key($propertiesToInclude, $propertySizes);
        $propertiesToExclude = array_combine($propertiesToExclude, $propertiesToExclude);
        $propertiesToExclude = array_diff_key($propertiesToExclude, $propertySizes);
        $propertiesToInclude = array_diff_key($propertiesToInclude, $propertiesToExclude);

        $textsToTranslate = [];

        foreach ($resourceTypes as $resourceType) {
            if ($this->shouldStop()) {
                break;
            }

            $totalResources = $this->api->search(
                $resourceType,
                ['limit' => 0]
            )->getTotalResults();

            $this->logger->info(
                'Collecting texts from {count} {type}.', // @translate
                ['count' => $totalResources, 'type' => $resourceType]
            );

            $offset = 0;
            $processed = 0;
            $logStep = $totalResources >= 10000 ? 1000 : 200;
            while (true) {
                if ($this->shouldStop()) {
                    break 2;
                }

                $resources = $this->api->search($resourceType, [
                    'limit' => self::RESOURCE_BATCH,
                    'offset' => $offset,
                ])->getContent();

                if (!$resources) {
                    break;
                }

                foreach ($resources as $resource) {
                    $processed++;
                    $values = $resource->values();
                    $values = array_intersect_key($values, $propertiesToInclude);
                    foreach ($values as $valueInfo) {
                        foreach ($valueInfo['values'] as $value) {
                            $val = (string) $value->value();
                            $type = (string) $value->type();
                            $lang = (string) $value->lang();
                            $langCode = mb_strtolower((string) strtok($lang, '-'));

                            if (!$val
                                || is_numeric($val)
                                || $value->valueResource()
                                || in_array($type, ['boolean', 'json', 'html', 'xml', 'place'])
                                || in_array($type, ['geography', 'geometry'])
                                || strpos($type, 'geographic:') === 0
                                || strpos($type, 'geometry:') === 0
                                || strpos($type, 'numeric:') === 0
                                || preg_match('~^\s*(POINT|LINESTRING|POLYGON|MULTIPOINT|MULTILINESTRING|MULTIPOLYGON|GEOMETRYCOLLECTION)\s*[ZM]*\s*\(~i', $val)
                                || (!$langCode && $isSkipEmptyLang)
                                || ($langCode && !isset(Module::$langsSupportedInput[$langCode]))
                            ) {
                                continue;
                            }

                            foreach ($pairs as $pair) {
                                $langSource = $pair['source'] ?: $defaultLangSource;
                                $langTarget = $pair['target'];
                                $key = ($langSource ?? '') . '=' . $langTarget;
                                $textsToTranslate[$key]['source'] = $langSource;
                                $textsToTranslate[$key]['target'] = $langTarget;
                                $textsToTranslate[$key]['texts'][$val] = $val;
                            }
                        }
                    }
                }

                unset($resources);
                $this->entityManager->clear();
                $offset += self::RESOURCE_BATCH;

                if ($processed % $logStep === 0 || $offset >= $totalResources) {
                    $uniqueTexts = 0;
                    foreach ($textsToTranslate as $data) {
                        $uniqueTexts += count($data['texts'] ?? []);
                    }
                    $memMb = (int) round(memory_get_usage(true) / 1048576);
                    $this->logger->info(
                        '{type}: {done}/{total} processed, {unique} unique texts collected, {mem} MB memory.', // @translate
                        [
                            'type' => $resourceType,
                            'done' => min($processed, $totalResources),
                            'total' => $totalResources,
                            'unique' => $uniqueTexts,
                            'mem' => $memMb,
                        ]
                    );
                }
            }
        }

        // Global deduplication.
        foreach ($textsToTranslate as &$data) {
            $data['texts'] = array_values($data['texts']);
        }
        unset($data);

        return $textsToTranslate;
    }

    /**
     * Collect all unique texts via direct SQL on the value table.
     *
     * Bypasses the API/ORM hydration path used by collectTexts() because
     * iterating hundreds of thousands of resources via Doctrine
     * representations is orders of magnitude slower than a single
     * filtered scan of the value table.
     */
    protected function collectTextsSql(
        array $resourceTypes,
        array $pairs,
        array $propertiesToInclude
    ): array {
        $services = $this->getServiceLocator();
        $easyMeta = $services->get('Common\EasyMeta');

        $defaultLangSource = $this->settings->get('translator_lang_source_default');
        $isSkipEmptyLang = $defaultLangSource === 'skip'
            || ($defaultLangSource
                && !isset(Module::$langsSupportedInput[$defaultLangSource]));
        if (!$defaultLangSource
            || $defaultLangSource === 'auto'
            || $defaultLangSource === 'skip'
        ) {
            $defaultLangSource = null;
        }

        if (in_array('properties', $propertiesToInclude)) {
            $propertiesToInclude = $easyMeta->propertyTerms();
        }
        $propertiesToExclude = $this->settings->get('translator_properties_exclude', []);
        $sizeExclude = $this->settings->get('translator_properties_exclude_size', '');
        if ($sizeExclude) {
            $propertiesToExclude[] = $sizeExclude;
        }

        $propertySizesMax = [
            'properties_max_500' => 500,
            'properties_max_1000' => 1000,
            'properties_max_5000' => 5000,
        ];
        $propertySizesMin = [
            'properties_min_500' => 500,
            'properties_min_1000' => 1000,
            'properties_min_5000' => 5000,
        ];
        $propertySizes = $propertySizesMax + $propertySizesMin;

        $includeMaxKey = current(array_intersect(array_keys($propertySizesMax), $propertiesToInclude));
        $includeMinKey = current(array_intersect(array_keys($propertySizesMin), $propertiesToInclude));
        $excludeMaxKey = current(array_intersect(array_keys($propertySizesMax), $propertiesToExclude));
        $excludeMinKey = current(array_intersect(array_keys($propertySizesMin), $propertiesToExclude));
        $sizeIncludeMax = $includeMaxKey ? $propertySizesMax[$includeMaxKey] : null;
        $sizeIncludeMin = $includeMinKey ? $propertySizesMin[$includeMinKey] : null;
        $sizeExcludeMax = $excludeMaxKey ? $propertySizesMax[$excludeMaxKey] : null;
        $sizeExcludeMin = $excludeMinKey ? $propertySizesMin[$excludeMinKey] : null;

        $termsToInclude = array_values(array_diff($propertiesToInclude, array_keys($propertySizes)));
        $termsToExclude = array_values(array_diff($propertiesToExclude, array_keys($propertySizes)));

        $includeIds = $termsToInclude
            ? array_values(array_filter(array_map(
                fn ($term) => $easyMeta->propertyId($term),
                $termsToInclude
            )))
            : [];
        $excludeIds = $termsToExclude
            ? array_values(array_filter(array_map(
                fn ($term) => $easyMeta->propertyId($term),
                $termsToExclude
            )))
            : [];

        $hasSizeFilter = $sizeIncludeMax !== null || $sizeIncludeMin !== null;
        if (!$includeIds && !$hasSizeFilter) {
            $this->logger->err(
                'No resolvable properties to include.' // @translate
            );
            return [];
        }

        $resourceTypeMap = [
            'items' => 'Omeka\\Entity\\Item',
            'item_sets' => 'Omeka\\Entity\\ItemSet',
            'media' => 'Omeka\\Entity\\Media',
            'digital_objects' => 'DigitalObject\\Entity\\DigitalObject',
        ];
        $resourceClasses = [];
        foreach ($resourceTypes as $rt) {
            if (isset($resourceTypeMap[$rt])) {
                $resourceClasses[] = $resourceTypeMap[$rt];
            }
        }
        if (!$resourceClasses) {
            return [];
        }

        $supportedLangs = array_keys(Module::$langsSupportedInput);

        $where = [
            'v.value IS NOT NULL',
            "v.value <> ''",
            'v.value_resource_id IS NULL',
            "v.type NOT IN ('boolean','json','html','xml','place','geography','geometry')",
            "v.type NOT LIKE 'numeric:%'",
            "v.type NOT LIKE 'geographic:%'",
            "v.type NOT LIKE 'geometry:%'",
            'r.resource_type IN (:res_types)',
        ];
        $params = ['res_types' => $resourceClasses];
        $types = ['res_types' => \Doctrine\DBAL\Connection::PARAM_STR_ARRAY];

        if ($hasSizeFilter && $includeIds) {
            $where[] = '(v.property_id IN (:include_ids) OR ('
                . ($sizeIncludeMax !== null ? 'CHAR_LENGTH(v.value) <= :size_inc_max' : '1=1')
                . ($sizeIncludeMin !== null
                    ? ($sizeIncludeMax !== null ? ' AND ' : '')
                        . 'CHAR_LENGTH(v.value) > :size_inc_min'
                    : '')
                . '))';
            $params['include_ids'] = $includeIds;
            $types['include_ids'] = \Doctrine\DBAL\Connection::PARAM_INT_ARRAY;
            if ($sizeIncludeMax !== null) {
                $params['size_inc_max'] = $sizeIncludeMax;
            }
            if ($sizeIncludeMin !== null) {
                $params['size_inc_min'] = $sizeIncludeMin;
            }
        } elseif ($includeIds) {
            $where[] = 'v.property_id IN (:include_ids)';
            $params['include_ids'] = $includeIds;
            $types['include_ids'] = \Doctrine\DBAL\Connection::PARAM_INT_ARRAY;
        } else {
            if ($sizeIncludeMax !== null) {
                $where[] = 'CHAR_LENGTH(v.value) <= :size_inc_max';
                $params['size_inc_max'] = $sizeIncludeMax;
            }
            if ($sizeIncludeMin !== null) {
                $where[] = 'CHAR_LENGTH(v.value) > :size_inc_min';
                $params['size_inc_min'] = $sizeIncludeMin;
            }
        }

        if ($excludeIds) {
            $where[] = 'v.property_id NOT IN (:exclude_ids)';
            $params['exclude_ids'] = $excludeIds;
            $types['exclude_ids'] = \Doctrine\DBAL\Connection::PARAM_INT_ARRAY;
        }
        if ($sizeExcludeMax !== null) {
            $where[] = 'CHAR_LENGTH(v.value) > :size_exc_max';
            $params['size_exc_max'] = $sizeExcludeMax;
        }
        if ($sizeExcludeMin !== null) {
            $where[] = 'CHAR_LENGTH(v.value) <= :size_exc_min';
            $params['size_exc_min'] = $sizeExcludeMin;
        }

        if ($isSkipEmptyLang) {
            $where[] = 'v.lang IS NOT NULL';
        }
        if ($supportedLangs) {
            $where[] = '(v.lang IS NULL OR LOWER(SUBSTRING_INDEX(v.lang, \'-\', 1)) IN (:langs))';
            $params['langs'] = $supportedLangs;
            $types['langs'] = \Doctrine\DBAL\Connection::PARAM_STR_ARRAY;
        }

        $sql = 'SELECT DISTINCT v.value, v.lang'
            . ' FROM value v'
            . ' INNER JOIN resource r ON r.id = v.resource_id'
            . ' WHERE ' . implode(' AND ', $where);

        $this->logger->info(
            'Collecting texts via SQL on table value (terms: {nbi} include / {nbe} exclude, size filter: {sf}).', // @translate
            [
                'nbi' => count($includeIds),
                'nbe' => count($excludeIds),
                'sf' => $hasSizeFilter ? 'yes' : 'no',
            ]
        );

        $stmt = $this->connection->executeQuery($sql, $params, $types);

        $textsToTranslate = [];
        $rowCount = 0;
        $logStep = 50000;
        while ($row = $stmt->fetchAssociative()) {
            if ($this->shouldStop()) {
                break;
            }
            $rowCount++;
            $val = (string) $row['value'];
            if ($val === '' || is_numeric($val)) {
                continue;
            }
            if (preg_match('~^\s*(POINT|LINESTRING|POLYGON|MULTIPOINT|MULTILINESTRING|MULTIPOLYGON|GEOMETRYCOLLECTION)\s*[ZM]*\s*\(~i', $val)) {
                continue;
            }
            $lang = (string) ($row['lang'] ?? '');
            $langCode = mb_strtolower((string) strtok($lang, '-'));
            if (!$langCode && $isSkipEmptyLang) {
                continue;
            }

            foreach ($pairs as $pair) {
                $langSource = $pair['source'] ?: $defaultLangSource;
                $langTarget = $pair['target'];
                $key = ($langSource ?? '') . '=' . $langTarget;
                $textsToTranslate[$key]['source'] = $langSource;
                $textsToTranslate[$key]['target'] = $langTarget;
                $textsToTranslate[$key]['texts'][$val] = $val;
            }

            if ($rowCount % $logStep === 0) {
                $uniqueTexts = 0;
                foreach ($textsToTranslate as $data) {
                    $uniqueTexts += count($data['texts'] ?? []);
                }
                $memMb = (int) round(memory_get_usage(true) / 1048576);
                $this->logger->info(
                    'SQL collect: {rows} rows scanned, {unique} unique texts, {mem} MB memory.', // @translate
                    [
                        'rows' => $rowCount,
                        'unique' => $uniqueTexts,
                        'mem' => $memMb,
                    ]
                );
            }
        }

        foreach ($textsToTranslate as &$data) {
            $data['texts'] = array_values($data['texts']);
        }
        unset($data);

        return $textsToTranslate;
    }

    /**
     * Filter texts that already have a translation, using direct SQL.
     *
     * Uses JSON_TABLE so the diff runs entirely in SQL with the same
     * collation on both sides (avoids byte/collation asymmetry on
     * apostrophes, accents, NFC/NFD, ligatures, etc.). Falls back to
     * a PHP array_diff (byte-exact) on older databases — DeepL may then
     * be called for a few false-negatives, but the per-item try/catch
     * in translateAndStore prevents DB duplicates.
     */
    protected function filterExistingTranslationsSql(
        array $strings,
        ?string $langSource,
        string $langTarget
    ): array {
        if (!$strings) {
            return [];
        }

        if ($this->supportsJsonTable()) {
            return $this->filterExistingTranslationsJsonTable($strings, $langSource, $langTarget);
        }

        $existing = [];
        foreach (array_chunk($strings, 500) as $chunk) {
            $qb = $this->connection->createQueryBuilder();
            $qb->select('text.string')
                ->from('translate_text', 'text')
                ->innerJoin('text', 'translation', 'tr', 'tr.text_id = text.id')
                ->where($qb->expr()->in('text.string', ':strings'))
                ->andWhere($qb->expr()->eq('tr.lang', ':lang_target'));
            $bind = [
                'strings' => $chunk,
                'lang_target' => $langTarget,
            ];
            $types = [
                'strings' => \Doctrine\DBAL\Connection::PARAM_STR_ARRAY,
                'lang_target' => ParameterType::STRING,
            ];
            if ($langSource) {
                $qb->andWhere($qb->expr()->eq('text.lang', ':lang_source'));
                $bind['lang_source'] = $langSource;
                $types['lang_source'] = ParameterType::STRING;
            } else {
                $qb->andWhere($qb->expr()->isNull('text.lang'));
            }

            $rows = $qb->setParameters($bind, $types)
                ->execute()
                ->fetchFirstColumn();
            $existing = array_merge($existing, $rows);
        }

        return array_values(array_diff($strings, $existing));
    }

    /**
     * Filter via JSON_TABLE so the WHERE NOT EXISTS uses the column
     * collation on both sides. Returns only strings without translation.
     */
    protected function filterExistingTranslationsJsonTable(
        array $strings,
        ?string $langSource,
        string $langTarget
    ): array {
        $remaining = [];
        $sourceClause = $langSource
            ? 't.lang = :lang_source'
            : 't.lang IS NULL';

        $sql = 'SELECT input.s'
            . ' FROM JSON_TABLE(:input_json, \'$[*]\' COLUMNS(s LONGTEXT PATH \'$\')) input'
            . ' WHERE NOT EXISTS ('
            . '   SELECT 1 FROM translate_text t'
            . '   INNER JOIN translation tr ON tr.text_id = t.id'
            . '   WHERE t.string = input.s'
            . '     AND tr.lang = :lang_target'
            . '     AND ' . $sourceClause
            . ' )';

        foreach (array_chunk($strings, 500) as $chunk) {
            $params = [
                'input_json' => json_encode(array_values($chunk), JSON_UNESCAPED_UNICODE),
                'lang_target' => $langTarget,
            ];
            if ($langSource) {
                $params['lang_source'] = $langSource;
            }
            $rows = $this->connection
                ->executeQuery($sql, $params)
                ->fetchFirstColumn();
            foreach ($rows as $r) {
                $remaining[] = (string) $r;
            }
        }
        return $remaining;
    }

    /**
     * Detect once if the DB server supports JSON_TABLE
     * (MariaDB ≥ 10.6, MySQL ≥ 8.0.4). Logs a warning when the
     * fallback path is used.
     */
    protected function supportsJsonTable(): bool
    {
        static $cached;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $version = (string) $this->connection->fetchOne('SELECT VERSION()');
        } catch (\Throwable $e) {
            $cached = false;
            return $cached;
        }
        $isMaria = stripos($version, 'mariadb') !== false;
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $m)) {
            $major = (int) $m[1];
            $minor = (int) $m[2];
            $patch = (int) $m[3];
            if ($isMaria) {
                $cached = ($major > 10) || ($major === 10 && $minor >= 6);
            } else {
                $cached = ($major > 8)
                    || ($major === 8 && ($minor > 0 || $patch >= 4));
            }
        } else {
            $cached = false;
        }
        if (!$cached) {
            $this->logger->warn(
                'Database {version} does not support JSON_TABLE; using byte-exact fallback. Some translated strings may be re-sent to DeepL when collation collapses (apostrophes, accents, NFC/NFD, ligatures). Per-item try/catch prevents DB duplicates but burns quota.', // @translate
                ['version' => $version]
            );
        }
        return $cached;
    }

    /**
     * Normalize and validate configured language pairs.
     */
    protected function normalizeLanguagePairs(): array
    {
        $pairs = $this->settings->get('translator_lang_pairs');
        if (!$pairs) {
            return [];
        }

        $result = [];
        foreach ($pairs as $singleOrPair) {
            $r = array_values(array_map('trim', array_filter(explode('=', $singleOrPair))));
            if ($r) {
                $langSource = count($r) === 1
                    ? null
                    : (strtr(mb_strtolower($r[0]), '_', '-') ?: null);
                $langTarget = strtr(mb_strtolower(count($r) === 1 ? $r[0] : $r[1]), '_', '-');
                if ($langTarget) {
                    $hasError = false;
                    if ($langSource && !isset(Module::$langsSupportedInput[$langSource])) {
                        $hasError = true;
                        $this->logger->err(
                            'Unsupported source language: {lang}.', // @translate
                            ['lang' => $langSource]
                        );
                    }
                    if (!isset(Module::$langsSupportedOutput[$langTarget])) {
                        if (isset(Module::$langsSupportedOutputShort[$langTarget])) {
                            $langTarget = Module::$langsSupportedOutputShort[$langTarget][0];
                        } else {
                            $hasError = true;
                            $this->logger->err(
                                'Unsupported target language: {lang}.', // @translate
                                ['lang' => $langTarget]
                            );
                        }
                    }
                    if (!$hasError) {
                        $result[] = [
                            'source' => $langSource,
                            'target' => $langTarget,
                        ];
                    }
                }
            }
        }

        return array_values(array_unique($result, SORT_REGULAR));
    }

    /**
     * Translate texts in DeepL-sized batches and store results.
     */
    protected function translateAndStore(
        array $texts,
        ?string $langSource,
        string $langTarget
    ): int {
        $totalCreated = 0;
        $totalTexts = count($texts);
        $chunks = array_chunk($texts, self::DEEPL_BATCH);
        $nextLog = 1000;

        foreach ($chunks as $chunk) {
            if ($this->shouldStop() || $this->quotaExceeded) {
                break;
            }

            $translations = $this->translateDeepL(
                $chunk,
                $langSource,
                $langTarget
            );

            if ($this->quotaExceeded) {
                break;
            }
            if (!$translations) {
                continue;
            }

            $results = [];
            foreach ($translations as $key => $translation) {
                $results[] = [
                    'o:string' => $chunk[$key],
                    'o:lang_source' => $langSource,
                    'o:lang_target' => $langTarget,
                    'o:translation' => $translation->text,
                    'o:automatic' => true,
                ];
            }

            if ($results) {
                $created = 0;
                $skipped = 0;
                foreach ($results as $payload) {
                    try {
                        $this->api->create('translations', $payload);
                        $created++;
                    } catch (\Omeka\Api\Exception\ValidationException $e) {
                        // Duplicate at DB collation level (e.g. apostrophe
                        // variants collapsed by utf8mb4_unicode_ci): skip.
                        $skipped++;
                    } catch (\Throwable $e) {
                        $this->logger->err(
                            'Translation create failed: {message}', // @translate
                            ['message' => $e->getMessage()]
                        );
                        $skipped++;
                    }
                }
                $totalCreated += $created;
                if ($skipped > 0) {
                    $this->logger->info(
                        '{source} → {target}: {skipped} duplicates skipped in chunk.', // @translate
                        [
                            'source' => $langSource ?: 'auto',
                            'target' => $langTarget,
                            'skipped' => $skipped,
                        ]
                    );
                }
            }

            if ($totalCreated >= $nextLog) {
                $this->logger->info(
                    '{source} → {target}: {done}/{total} translations created.', // @translate
                    [
                        'source' => $langSource ?: 'auto',
                        'target' => $langTarget,
                        'done' => $totalCreated,
                        'total' => $totalTexts,
                    ]
                );
                $nextLog = $totalCreated + ($totalCreated >= 10000 ? 10000 : 1000);
            }

            $this->entityManager->clear();
        }

        return $totalCreated;
    }

    /**
     * Call DeepL API to translate texts.
     */
    protected function translateDeepL(
        array $texts,
        ?string $langSource,
        string $langTarget
    ): array {
        $services = $this->getServiceLocator();

        $deeplApiKey = $services->get('Omeka\Cipher')->decrypt((string) $this->settings->get('translator_deepl_api_key'));
        if (!$deeplApiKey) {
            return [];
        }

        $deeplClient = new \DeepL\DeepLClient($deeplApiKey, [
            \DeepL\TranslatorOptions::SERVER_URL => null,
            \DeepL\TranslatorOptions::HEADERS => [],
            \DeepL\TranslatorOptions::TIMEOUT => null,
            \DeepL\TranslatorOptions::MAX_RETRIES => null,
            \DeepL\TranslatorOptions::PROXY => null,
            \DeepL\TranslatorOptions::HTTP_CLIENT => null,
            \DeepL\TranslatorOptions::SEND_PLATFORM_INFO => true,
            \DeepL\TranslatorOptions::APP_INFO => new \DeepL\AppInfo(
                'OmekaS-Translator',
                \Omeka\Module::VERSION . '-' . $services->get('Omeka\ModuleManager')->getModule('Translator')->getIni('version')
            ),
        ]);

        $options = [
            \DeepL\TranslateTextOptions::CONTEXT => null,
            \DeepL\TranslateTextOptions::FORMALITY => 'prefer_more',
            \DeepL\TranslateTextOptions::MODEL_TYPE => 'prefer_quality_optimized',
            \DeepL\TranslateTextOptions::GLOSSARY => null,
            \DeepL\TranslateTextOptions::SPLIT_SENTENCES => null,
            \DeepL\TranslateTextOptions::PRESERVE_FORMATTING => true,
            \DeepL\TranslateTextOptions::TAG_HANDLING => null,
            \DeepL\TranslateTextOptions::OUTLINE_DETECTION => true,
            \DeepL\TranslateTextOptions::SPLITTING_TAGS => null,
            \DeepL\TranslateTextOptions::NON_SPLITTING_TAGS => null,
            \DeepL\TranslateTextOptions::IGNORE_TAGS => null,
        ];

        try {
            return $deeplClient->translateText($texts, $langSource, $langTarget, $options);
        } catch (\DeepL\DeepLException $e) {
            $message = $e->getMessage();
            if (
                $e instanceof \DeepL\QuotaExceededException
                || stripos($message, 'quota') !== false
            ) {
                $this->quotaExceeded = true;
                $this->logger->err(
                    'DeepL quota exceeded: {error}. Aborting job.', // @translate
                    ['error' => $message]
                );
                return [];
            }
            $this->logger->err(
                'DeepL translation failed: {error}', // @translate
                ['error' => $message]
            );
            return [];
        }
    }
}
