<?php declare(strict_types=1);

namespace Translator\Job;

use Doctrine\DBAL\ParameterType;
use Omeka\Job\AbstractJob;
use Translator\Module;

/**
 * Common process for the jobs that translate a set of texts.
 *
 * The subclasses collect the texts to translate, grouped by pair of languages,
 * then this class filters the texts that are already translated, sends the
 * remaining ones to the translation service and stores the results.
 *
 * The structure of the collected texts is an array of arrays with the keys
 * "source" (may be null for an automatic detection), "target", "texts" and the
 * optional key "options", that is passed to the translation service, for
 * example to manage the html.
 */
abstract class AbstractTranslate extends AbstractJob
{
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

    /**
     * Prepare the services and the reference id used for the logs.
     */
    protected function initServices(string $referenceId): void
    {
        $services = $this->getServiceLocator();
        $this->api = $services->get('Omeka\ApiManager');
        $this->connection = $services->get('Omeka\Connection');
        $this->entityManager = $services->get('Omeka\EntityManager');
        $this->logger = $services->get('Omeka\Logger');
        $this->settings = $services->get('Omeka\Settings');

        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId($referenceId . '/job_' . $this->job->getId());
        $this->logger->addProcessor($referenceIdProcessor);
    }

    /**
     * Check the api key of the translation service and return it.
     */
    protected function checkDeeplApiKey(): ?string
    {
        $deeplApiKey = $this->getServiceLocator()
            ->get('Omeka\Cipher')
            ->decrypt((string) $this->settings->get('translator_deepl_api_key'));
        if (!$deeplApiKey) {
            $this->logger->warn(
                'No DeepL API key configured.' // @translate
            );
            return null;
        }
        return $deeplApiKey;
    }

    /**
     * Log the number of texts and characters collected for all pairs.
     */
    protected function logCollectedTexts(array $textsToTranslate): void
    {
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
    }

    /**
     * For each pair, filter existing translations and translate the remaining
     * texts via the translation service in batches.
     *
     * @return int The number of translations created.
     */
    protected function translatePairs(array $textsToTranslate): int
    {
        $totalTranslated = 0;
        $perPairTranslated = [];
        $perPairRemaining = [];
        foreach ($textsToTranslate as $data) {
            $langSource = $data['source'];
            $langTarget = $data['target'];
            $options = $data['options'] ?? [];
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
                $langTarget,
                $options
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

        return $totalTranslated;
    }

    /**
     * Log a per-pair summary of translations created and not translated during
     * this job session.
     */
    protected function logSessionSummary(array $translated, array $remaining): void
    {
        if (!$translated && !$remaining) {
            return;
        }

        // One log for all the pairs: a log by pair floods the journal with
        // entries that share the same timestamp and the same reference.
        $list = [];
        foreach ($translated as $pair => $count) {
            $list[] = sprintf('%s: %d/%d', $pair, $count, $count + ($remaining[$pair] ?? 0));
        }

        $this->logger->notice(
            'Session summary, translated by pair of languages: {list}.', // @translate
            ['list' => implode(' | ', $list)]
        );
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
        $list = [];
        foreach ($rows as $row) {
            $list[] = sprintf(
                '%s → %s: %d',
                $row['source_lang'] ?? 'auto',
                $row['target_lang'],
                (int) $row['n']
            );
        }

        $this->logger->notice(
            'Translations stored in the database, all sessions included: {list}.', // @translate
            ['list' => implode(' | ', $list)]
        );
    }

    /**
     * Get the translations of a list of strings, translating the missing ones.
     *
     * Unlike translatePairs(), that only stores the translations, this method
     * returns them, so the caller can use them, for example to fill the blocks
     * of a copied page.
     *
     * @return array Translations indexed by the original strings. A string that
     * has no translation is not included.
     */
    protected function translateStrings(
        array $strings,
        ?string $langSource,
        string $langTarget,
        array $options = []
    ): array {
        $strings = array_values(array_unique(array_filter(
            $strings,
            fn ($string) => is_string($string) && $string !== ''
        )));
        if (!$strings) {
            return [];
        }

        $missing = $this->filterExistingTranslationsSql($strings, $langSource, $langTarget);
        if ($missing && !$this->quotaExceeded) {
            $this->translateAndStore($missing, $langSource, $langTarget, $options);
        }

        return $this->fetchTranslations($strings, $langSource, $langTarget);
    }

    /**
     * Get the stored translations of a list of strings.
     *
     * @return array Translations indexed by the original strings.
     */
    protected function fetchTranslations(
        array $strings,
        ?string $langSource,
        string $langTarget
    ): array {
        if (!$strings) {
            return [];
        }

        $result = [];
        foreach (array_chunk($strings, 500) as $chunk) {
            $qb = $this->connection->createQueryBuilder();
            $qb->select('text.string', 'tr.translation')
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
                ->fetchAllNumeric();
            foreach ($rows as [$string, $translation]) {
                // Keep the first translation when there are many.
                $result[$string] ??= $translation;
            }
        }

        return $result;
    }

    /**
     * Filter texts that already have a translation, using direct SQL.
     *
     * Uses JSON_TABLE so the diff runs entirely in SQL with the same collation
     * on both sides (avoids byte/collation asymmetry on apostrophes, accents,
     * NFC/NFD, ligatures, etc.). Falls back to a PHP array_diff (byte-exact) on
     * older databases — DeepL may then be called for a few false-negatives, but
     * the per-item try/catch in translateAndStore prevents DB duplicates.
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
     * Filter via JSON_TABLE so the WHERE NOT EXISTS uses the column collation
     * on both sides. Returns only strings without translation.
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

        // The column built by JSON_TABLE uses the default collation of the
        // database, that may not be the one of the table, so it is forced.
        $collate = $this->stringCollation();

        $sql = 'SELECT input.s'
            . ' FROM JSON_TABLE(:input_json, \'$[*]\' COLUMNS(s LONGTEXT PATH \'$\')) input'
            . ' WHERE NOT EXISTS ('
            . '   SELECT 1 FROM translate_text t'
            . '   INNER JOIN translation tr ON tr.text_id = t.id'
            . '   WHERE t.string = input.s' . $collate
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
     * Get the clause to force the collation of the column "string" of the table
     * "translate_text", or an empty string when it cannot be determined.
     */
    protected function stringCollation(): string
    {
        static $collate;

        if ($collate !== null) {
            return $collate;
        }

        try {
            $collation = (string) $this->connection->fetchOne(
                'SELECT collation_name FROM information_schema.columns'
                . ' WHERE table_schema = DATABASE()'
                . ' AND table_name = "translate_text"'
                . ' AND column_name = "string"'
            );
        } catch (\Throwable $e) {
            $collation = '';
        }

        // The collation is used in a sql string, so check it strictly.
        return $collate = preg_match('~^[a-z0-9_]+$~', $collation)
            ? ' COLLATE ' . $collation
            : '';
    }

    /**
     * Detect once if the DB server supports JSON_TABLE
     * (MariaDB ≥ 10.6, MySQL ≥ 8.0.4). Logs a warning when the fallback path is
     * used.
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
        $errors = [];
        foreach ($pairs as $singleOrPair) {
            $r = array_values(array_map('trim', array_filter(explode('=', $singleOrPair))));
            if (!$r) {
                continue;
            }
            $langSource = count($r) === 1 ? null : (strtr(mb_strtolower($r[0]), '_', '-') ?: null);
            $langTarget = strtr(mb_strtolower(count($r) === 1 ? $r[0] : $r[1]), '_', '-');
            if (!$langTarget) {
                continue;
            }
            $hasError = false;
            if ($langSource && !isset(Module::$langsSupportedInput[$langSource])) {
                $hasError = true;
                $errors['source'][$langSource] = $langSource;
            }
            if (!isset(Module::$langsSupportedOutput[$langTarget])) {
                if (isset(Module::$langsSupportedOutputShort[$langTarget])) {
                    $langTarget = Module::$langsSupportedOutputShort[$langTarget][0];
                } else {
                    $hasError = true;
                    $errors['target'][$langTarget] = $langTarget;
                }
            }
            if (!$hasError) {
                $result[] = [
                    'source' => $langSource,
                    'target' => $langTarget,
                ];
            }
        }

        if (!empty($errors['source'])) {
            $this->logger->err(
                'The following source languages are not supported currently: {list}.', // @translate
                ['list' => implode(', ', $errors['source'])]
            );
        }
        if (!empty($errors['target'])) {
            $this->logger->err(
                'The following target languages are not supported currently: {list}.', // @translate
                ['list' => implode(', ', $errors['target'])]
            );
        }

        return array_values(array_unique($result, SORT_REGULAR));
    }

    /**
     * Translate texts in batches and store them.
     *
     * @return int The number of translations created.
     */
    protected function translateAndStore(
        array $texts,
        ?string $langSource,
        string $langTarget,
        array $options = []
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
                $langTarget,
                $options
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
     *
     * The options override the default ones, so a caller can for example set
     * the tag handling for the html.
     */
    protected function translateDeepL(
        array $texts,
        ?string $langSource,
        string $langTarget,
        array $options = []
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

        $options += [
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
