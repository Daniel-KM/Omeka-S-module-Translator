<?php declare(strict_types=1);

namespace Translator\Job;

use Translator\Module;

/**
 * Translate all existing resources in batch.
 *
 * Unlike the event-based translation that processes one resource at a time,
 * this job collects all unique texts across resources, deduplicates them
 * globally, filters existing translations in bulk, then sends them to DeepL in
 * batches to minimize API calls.
 */
class TranslateAll extends AbstractTranslate
{
    /**
     * Number of resources fetched per iteration.
     *
     * @var int
     */
    const RESOURCE_BATCH = 100;

    public function perform(): void
    {
        $this->initServices('translator/translate-all');

        if (!$this->checkDeeplApiKey()) {
            return;
        }

        $pairs = $this->normalizeLanguagePairs();
        if (!$pairs) {
            $this->logger->notice(
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
            $this->logger->notice(
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

        $this->logCollectedTexts($textsToTranslate);

        // Phase 2: for each pair, filter existing translations and translate
        // via DeepL in batches.
        $this->translatePairs($textsToTranslate);
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
                            $langCode = Module::normalizeLangCode($lang);

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
     * iterating hundreds of thousands of resources via Doctrine representations
     * is orders of magnitude slower than a single filtered scan of the value
     * table.
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
            $this->logger->warn(
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

        // Values may use a three letters code, bibliographic or terminologic,
        // whereas DeepL uses mainly two letters codes, so append all variants.
        $supportedLangs = array_keys(Module::$langsSupportedInput);
        $variants = [];
        foreach ($supportedLangs as $lang) {
            foreach (\Iso639p3\Iso639p3::codes($lang) as $variant) {
                $variants[$variant] = $variant;
            }
        }
        $supportedLangs = array_values(array_unique(array_merge($supportedLangs, array_values($variants))));

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
            // Lang codes for values should use "-", but "_" is common, in
            // particular when values are imported from another tool.
            $where[] = '(v.lang IS NULL OR LOWER(SUBSTRING_INDEX(REPLACE(v.lang, \'_\', \'-\'), \'-\', 1)) IN (:langs))';
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
            $langCode = Module::normalizeLangCode($lang);
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
}
