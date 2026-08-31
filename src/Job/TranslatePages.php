<?php declare(strict_types=1);

namespace Translator\Job;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Translator\Entity\PageTranslation;
use Translator\Module;
use Translator\Stdlib\PageTexts;

/**
 * Translate the pages copied inside the sites of a multilingual site group.
 *
 * A multilingual site group is managed by the module Internationalisation: the
 * sites of a group have the same pages, copied from one site to the others, and
 * the relations between the copies are stored in the table
 * "site_page_relation".
 *
 * So the strings of a copied page are the ones of the original page, and they
 * are translated in place: unlike the values of the resources, they are stored
 * in the page itself, so the site displays them without any specific process.
 *
 * The direction of the translation is the one of the pairs of languages set in
 * the main settings: for a pair "fr = en-gb", the pages of the sites of a group
 * whose locale is French are translated into the pages of the sites of the same
 * group whose locale is British English. So a pair without an explicit source
 * language cannot be used here.
 *
 * A mirror page is never translated: it displays the original page, so it has
 * no string of its own.
 *
 * The blocks of a copy are rebuilt from the blocks of the original page, so
 * they follow its structure, and only the configured keys are translated. To
 * avoid to translate them again and again, and to avoid to overwrite the
 * corrections made by a user, a hash of the original content is stored for each
 * copy: a block is processed only when the original text changed.
 */
class TranslatePages extends AbstractTranslate
{
    /**
     * A mirror page displays another page, so it has nothing to translate.
     *
     * @var string
     */
    const LAYOUT_MIRROR = 'mirrorPage';

    /**
     * Key used to store the hash of the title of a page.
     *
     * @var string
     */
    const KEY_TITLE = 'title';

    /**
     * @var \Translator\Stdlib\PageTexts
     */
    protected $pageTexts;

    /**
     * Locale of each site, indexed by site id.
     *
     * @var array
     */
    protected $langBySite = [];

    /**
     * Ids of the pages to reindex at the end of the job.
     *
     * @var array
     */
    protected $pagesToIndex = [];

    /**
     * @var int
     */
    protected $countPages = 0;

    /**
     * @var int
     */
    protected $countBlocks = 0;

    public function perform(): void
    {
        $this->initServices('translator/translate-pages');

        if (!$this->checkDeeplApiKey()) {
            return;
        }

        $pairs = $this->normalizeLanguagePairs();
        $pairs = array_values(array_filter($pairs, fn ($pair) => $pair['source'] !== null));
        if (!$pairs) {
            $this->logger->err(
                'No pair of languages with an explicit source language is configured, so the direction of the translation of the pages is unknown.' // @translate
            );
            return;
        }

        $this->pageTexts = new PageTexts(
            $this->settings->get('translator_pages_include', []),
            $this->settings->get('translator_pages_exclude', [])
        );
        if (!$this->pageTexts->hasKeys()) {
            $this->logger->err(
                'No keys of page blocks to translate configured.' // @translate
            );
            return;
        }

        if (!$this->isInternationalisationActive()) {
            $this->logger->err(
                'The module Internationalisation is required to translate the pages: it manages the site groups and the relations between the copied pages.' // @translate
            );
            return;
        }

        $siteGroups = $this->siteGroups();
        if (!$siteGroups) {
            $this->logger->err(
                'No multilingual site group is configured in the module Internationalisation.' // @translate
            );
            return;
        }

        $pageIds = $this->normalizePageIds();

        $this->logger->notice(
            'Starting translation of the pages of {count} site groups.', // @translate
            ['count' => count($siteGroups)]
        );

        foreach ($siteGroups as $siteIds) {
            if ($this->shouldStop()) {
                break;
            }
            foreach ($pairs as $pair) {
                $this->translateGroupForPair($siteIds, $pair['source'], $pair['target'], $pageIds);
            }
        }

        $this->indexPages();

        $this->logger->notice(
            'Translation of the pages completed: {pages} pages and {blocks} blocks updated.', // @translate
            ['pages' => $this->countPages, 'blocks' => $this->countBlocks]
        );

        $this->logDatabaseTotals();

        if ($this->quotaExceeded) {
            $this->logger->warn(
                'Job aborted: DeepL quota exceeded. Re-run later when quota resets.' // @translate
            );
        }
    }

    /**
     * Translate the pages of the sites of a group for one pair of languages.
     */
    protected function translateGroupForPair(array $siteIds, string $langSource, string $langTarget, array $pageIds): void
    {
        $sourceSiteIds = [];
        $targetSiteIds = [];
        foreach ($siteIds as $siteId) {
            $lang = $this->langBySite[$siteId] ?? null;
            if (!$lang) {
                continue;
            }
            if (Module::normalizeLangCode($lang) === $langSource) {
                $sourceSiteIds[] = $siteId;
            }
            if ($lang === $langTarget || strtok($lang, '-') === strtok($langTarget, '-')) {
                $targetSiteIds[] = $siteId;
            }
        }

        // A site is never a source and a target at the same time.
        $sourceSiteIds = array_values(array_diff($sourceSiteIds, $targetSiteIds));
        if (!$sourceSiteIds || !$targetSiteIds) {
            return;
        }

        $sourcePages = $this->pagesOfSites($sourceSiteIds, $pageIds);
        if (!$sourcePages) {
            return;
        }

        $mirrorPages = $this->mirrorPages(array_keys($sourcePages));

        foreach ($sourcePages as $sourcePageId => $sourcePage) {
            if ($this->shouldStop() || $this->quotaExceeded) {
                return;
            }
            if (isset($mirrorPages[$sourcePageId])) {
                continue;
            }
            foreach ($this->relatedPages($sourcePageId, $targetSiteIds) as $targetPageId) {
                $this->translatePage($sourcePageId, $sourcePage['title'], $targetPageId, $langSource, $langTarget);
            }
        }
    }

    /**
     * Translate the title and the blocks of a copied page.
     */
    protected function translatePage(
        int $sourcePageId,
        string $sourceTitle,
        int $targetPageId,
        string $langSource,
        string $langTarget
    ): void {
        $sourceBlocks = $this->blocksOfPage($sourcePageId);
        $targetBlocks = $this->blocksOfPage($targetPageId);

        // A mirror page has nothing to translate.
        foreach ($targetBlocks as $targetBlock) {
            if ($targetBlock['layout'] === self::LAYOUT_MIRROR) {
                return;
            }
        }

        // Prepare the parts to translate and their hashes.
        $parts = [];
        if ($this->pageTexts->hasPageTitle() && trim($sourceTitle) !== '') {
            $parts[self::KEY_TITLE] = [
                'strings' => [$sourceTitle => $sourceTitle],
                'hash' => sha1($sourceTitle),
            ];
        }
        foreach ($sourceBlocks as $position => $sourceBlock) {
            if (!$this->pageTexts->isTranslatableLayout($sourceBlock['layout'])
                || $sourceBlock['layout'] === self::LAYOUT_MIRROR
            ) {
                continue;
            }
            $strings = $this->pageTexts->extract($sourceBlock['data']);
            if (!$strings) {
                continue;
            }
            $parts['block:' . $position] = [
                'strings' => $strings,
                'hash' => sha1($sourceBlock['layout'] . "\0" . implode("\0", $strings)),
                'position' => $position,
                'layout' => $sourceBlock['layout'],
                'data' => $sourceBlock['data'],
            ];
        }
        if (!$parts) {
            return;
        }

        // Skip the parts whose original content did not change since the last
        // translation, so the corrections of a user are kept. The hashes are
        // read with a direct sql, because the translation below clears the
        // entity manager.
        $hashes = $this->hashesOfPage($targetPageId);
        $partsToDo = [];
        foreach ($parts as $key => $part) {
            if (($hashes[$key] ?? null) !== $part['hash']) {
                $partsToDo[$key] = $part;
            }
        }
        if (!$partsToDo) {
            return;
        }

        // Translate all the strings of the page at once, the html apart.
        $stringsText = [];
        $stringsHtml = [];
        foreach ($partsToDo as $part) {
            foreach ($part['strings'] as $string) {
                if (PageTexts::isHtml($string)) {
                    $stringsHtml[] = $string;
                } else {
                    $stringsText[] = $string;
                }
            }
        }

        $translations = $this->translateStrings($stringsText, $langSource, $langTarget)
            + $this->translateStrings(
                $stringsHtml,
                $langSource,
                $langTarget,
                [\DeepL\TranslateTextOptions::TAG_HANDLING => 'html']
            );
        if (!$translations) {
            return;
        }

        // Apply the translations to the copy.
        $done = [];
        foreach ($partsToDo as $key => $part) {
            if ($key === self::KEY_TITLE) {
                if (isset($translations[$sourceTitle])) {
                    $this->updatePageTitle($targetPageId, $translations[$sourceTitle]);
                    $done[$key] = $part['hash'];
                }
                continue;
            }

            $position = $part['position'];
            $targetBlock = $targetBlocks[$position] ?? null;
            if (!$targetBlock) {
                $this->logger->warn(
                    'Page #{page_id}: there is no block at position {position} to translate from page #{source_page_id}.', // @translate
                    ['page_id' => $targetPageId, 'position' => $position, 'source_page_id' => $sourcePageId]
                );
                continue;
            }
            if ($targetBlock['layout'] !== $part['layout']) {
                $this->logger->warn(
                    'Page #{page_id}: the block at position {position} is a "{layout}", but the original one is a "{layout_2}", so it is skipped.', // @translate
                    [
                        'page_id' => $targetPageId,
                        'position' => $position,
                        'layout' => $targetBlock['layout'],
                        'layout_2' => $part['layout'],
                    ]
                );
                continue;
            }

            // The data of the copy is rebuilt from the original one, so the
            // structure of the blocks follows it, and only the configured keys
            // are translated.
            $data = $this->pageTexts->replace($part['data'], $translations);
            $this->updateBlockData((int) $targetBlock['id'], $data);
            $this->countBlocks++;
            $done[$key] = $part['hash'];
        }

        if (!$done) {
            return;
        }

        $this->countPages++;
        $this->pagesToIndex[$targetPageId] = $targetPageId;
        $this->saveHashes($targetPageId, $sourcePageId, $langTarget, array_replace($hashes, $done));
    }

    /**
     * The site groups and the relations between the copied pages belong to the
     * module Internationalisation, that is not required by this module, so the
     * table it manages is checked, and not the module itself.
     */
    protected function isInternationalisationActive(): bool
    {
        try {
            $this->connection->executeQuery('SELECT 1 FROM site_page_relation LIMIT 1');
        } catch (\Throwable $e) {
            return false;
        }

        return true;
    }

    /**
     * Get the ids of the pages to process, or an empty array for all of them.
     */
    protected function normalizePageIds(): array
    {
        $pageIds = $this->getArg('page_ids') ?: [];
        if (is_string($pageIds)) {
            $pageIds = preg_split('/\s+/', trim($pageIds)) ?: [];
        }
        return array_values(array_unique(array_filter(array_map('intval', (array) $pageIds))));
    }

    /**
     * Get the site ids of each multilingual group, and prepare their locales.
     *
     * The groups of a single site are skipped: there is nothing to translate.
     */
    protected function siteGroups(): array
    {
        $services = $this->getServiceLocator();
        $siteSettings = $services->get('Omeka\Settings\Site');

        $siteGroups = $this->settings->get('internationalisation_site_groups') ?: [];
        if (!$siteGroups) {
            return [];
        }

        $siteIdsBySlug = [];
        foreach ($this->api->search('sites', [], ['returnScalar' => 'slug'])->getContent() as $siteId => $slug) {
            $siteIdsBySlug[$slug] = (int) $siteId;
        }

        $mainLocale = $this->settings->get('locale')
            ?: $services->get('Config')['translator']['locale']
            ?: 'en_US';

        $result = [];
        $done = [];
        foreach ($siteGroups as $group) {
            $siteIds = [];
            foreach ($group as $slug) {
                if (isset($siteIdsBySlug[$slug])) {
                    $siteIds[] = $siteIdsBySlug[$slug];
                }
            }
            $siteIds = array_values(array_unique($siteIds));
            if (count($siteIds) <= 1) {
                continue;
            }
            // The same group is stored for each site of the group.
            sort($siteIds);
            $key = implode(',', $siteIds);
            if (isset($done[$key])) {
                continue;
            }
            $done[$key] = true;

            foreach ($siteIds as $siteId) {
                if (!isset($this->langBySite[$siteId])) {
                    $locale = $siteSettings->get('locale', null, $siteId) ?: $mainLocale;
                    $this->langBySite[$siteId] = mb_strtolower(strtr((string) $locale, '_', '-'));
                }
            }

            $result[] = $siteIds;
        }

        return $result;
    }

    /**
     * Get the id and the title of the pages of a list of sites.
     */
    protected function pagesOfSites(array $siteIds, array $pageIds): array
    {
        $sql = 'SELECT id, title FROM site_page WHERE site_id IN (:site_ids)';
        $params = ['site_ids' => $siteIds];
        $types = ['site_ids' => Connection::PARAM_INT_ARRAY];
        if ($pageIds) {
            $sql .= ' AND id IN (:page_ids)';
            $params['page_ids'] = $pageIds;
            $types['page_ids'] = Connection::PARAM_INT_ARRAY;
        }
        $sql .= ' ORDER BY id ASC';

        $result = [];
        $rows = $this->connection->executeQuery($sql, $params, $types)->fetchAllAssociative();
        foreach ($rows as $row) {
            $result[(int) $row['id']] = ['title' => (string) $row['title']];
        }
        return $result;
    }

    /**
     * Get the ids of the pages that contain a mirror block.
     */
    protected function mirrorPages(array $pageIds): array
    {
        if (!$pageIds) {
            return [];
        }
        $rows = $this->connection->executeQuery(
            'SELECT DISTINCT page_id FROM site_page_block WHERE layout = :layout AND page_id IN (:page_ids)',
            ['layout' => self::LAYOUT_MIRROR, 'page_ids' => $pageIds],
            ['layout' => ParameterType::STRING, 'page_ids' => Connection::PARAM_INT_ARRAY]
        )->fetchFirstColumn();

        return array_fill_keys(array_map('intval', $rows), true);
    }

    /**
     * Get the ids of the pages related to a page inside a list of sites.
     *
     * The relations are stored as unordered pairs by the module
     * Internationalisation, so the two columns are checked.
     */
    protected function relatedPages(int $pageId, array $siteIds): array
    {
        $sql = <<<'SQL'
            SELECT p.id
            FROM site_page_relation r
            INNER JOIN site_page p
                ON p.id = IF(r.page_id = :page_id, r.related_page_id, r.page_id)
            WHERE (r.page_id = :page_id OR r.related_page_id = :page_id)
                AND p.site_id IN (:site_ids)
            SQL;

        $rows = $this->connection->executeQuery(
            $sql,
            ['page_id' => $pageId, 'site_ids' => $siteIds],
            ['page_id' => ParameterType::INTEGER, 'site_ids' => Connection::PARAM_INT_ARRAY]
        )->fetchFirstColumn();

        return array_map('intval', $rows);
    }

    /**
     * Get the blocks of a page, indexed by position.
     */
    protected function blocksOfPage(int $pageId): array
    {
        $rows = $this->connection->executeQuery(
            'SELECT id, position, layout, data FROM site_page_block WHERE page_id = :page_id ORDER BY position ASC',
            ['page_id' => $pageId],
            ['page_id' => ParameterType::INTEGER]
        )->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $data = json_decode((string) $row['data'], true);
            $result[(int) $row['position']] = [
                'id' => (int) $row['id'],
                'layout' => (string) $row['layout'],
                'data' => is_array($data) ? $data : [],
            ];
        }
        return $result;
    }

    /**
     * The pages are updated with a direct sql: the api would trigger the event
     * on save, so the job would be dispatched again in a loop.
     */
    protected function updateBlockData(int $blockId, array $data): void
    {
        $this->connection->executeStatement(
            'UPDATE site_page_block SET data = :data WHERE id = :id',
            // The same flags than the json type of Doctrine are used, so the
            // data is stored like the core does.
            ['data' => json_encode($data), 'id' => $blockId],
            ['data' => ParameterType::STRING, 'id' => ParameterType::INTEGER]
        );
    }

    protected function updatePageTitle(int $pageId, string $title): void
    {
        $this->connection->executeStatement(
            'UPDATE site_page SET title = :title WHERE id = :id',
            ['title' => mb_substr($title, 0, 190), 'id' => $pageId],
            ['title' => ParameterType::STRING, 'id' => ParameterType::INTEGER]
        );
    }

    /**
     * Get the hashes of the parts of a page that were already translated.
     */
    protected function hashesOfPage(int $pageId): array
    {
        $hashes = $this->connection->executeQuery(
            'SELECT hashes FROM translate_page WHERE page_id = :page_id',
            ['page_id' => $pageId],
            ['page_id' => ParameterType::INTEGER]
        )->fetchOne();

        if (!is_string($hashes)) {
            return [];
        }

        $hashes = json_decode($hashes, true);

        return is_array($hashes) ? $hashes : [];
    }

    /**
     * Store the hashes of the parts that were translated.
     *
     * The entity is fetched here and not before the translation, because the
     * entity manager is cleared during it.
     */
    protected function saveHashes(
        int $targetPageId,
        int $sourcePageId,
        string $langTarget,
        array $hashes
    ): void {
        $now = new \DateTime('now');

        $pageTranslation = $this->entityManager
            ->getRepository(PageTranslation::class)
            ->findOneBy(['page' => $targetPageId]);

        if (!$pageTranslation) {
            $pageTranslation = new PageTranslation();
            $pageTranslation
                ->setPage($this->entityManager->getReference(\Omeka\Entity\SitePage::class, $targetPageId))
                ->setCreated($now);
            $this->entityManager->persist($pageTranslation);
        } else {
            $pageTranslation->setModified($now);
        }

        $pageTranslation
            ->setSourcePage($this->entityManager->getReference(\Omeka\Entity\SitePage::class, $sourcePageId))
            ->setLang($langTarget)
            ->setHashes($hashes);

        $this->entityManager->flush();
    }

    /**
     * Reindex the full text of the pages that were updated with a direct sql.
     */
    protected function indexPages(): void
    {
        if (!$this->pagesToIndex) {
            return;
        }

        $services = $this->getServiceLocator();
        $fulltext = $services->get('Omeka\FulltextSearch');
        $pageAdapter = $services->get('Omeka\ApiAdapterManager')->get('site_pages');

        $this->entityManager->clear();

        foreach ($this->pagesToIndex as $pageId) {
            $page = $this->entityManager->find(\Omeka\Entity\SitePage::class, $pageId);
            if (!$page) {
                continue;
            }
            try {
                $fulltext->save($page, $pageAdapter);
            } catch (\Throwable $e) {
                // Some blocks fail without a site, that may not be provided for
                // background tasks.
                $this->logger->warn(
                    'The full text of the page #{page_id} was not saved. Run the indexation of the full text manually. Exception: {exception}', // @translate
                    ['page_id' => $pageId, 'exception' => $e]
                );
            }
        }
    }
}
