<?php declare(strict_types=1);

namespace TranslatorTest\Job;

use CommonTest\AbstractTestCase;
use Doctrine\DBAL\ParameterType;
use Omeka\Entity\Job;
use Translator\Job\TranslatePages;
use Translator\Stdlib\PageTexts;

/**
 * The pages copied inside a multilingual site group are translated in place.
 *
 * All the translations are stored before the job runs, so the translation
 * service is never called: the job only has to apply them to the copies.
 */
class TranslatePagesTest extends AbstractTestCase
{
    const TITLE_FR = 'Titre de la page à traduire';
    const TITLE_EN = 'The title of the page';
    const HTML_FR = '<p>Un texte à traduire.</p>';
    const HTML_EN = '<p>A text to translate.</p>';
    const HEADING_FR = 'Une sélection';
    const HEADING_EN = 'A selection';

    /**
     * @var array
     */
    protected $siteIds = [];

    /**
     * @var array
     */
    protected $translationIds = [];

    /**
     * @var array
     */
    protected $previousSettings = [];

    /**
     * @var int
     */
    protected $sourcePageId;

    /**
     * @var int
     */
    protected $targetPageId;

    public function setUp(): void
    {
        parent::setUp();

        $auth = $this->getService('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();

        $this->createRelationTable();

        $api = $this->getService('Omeka\ApiManager');
        $siteSettings = $this->getService('Omeka\Settings\Site');
        $settings = $this->getService('Omeka\Settings');

        $suffix = 'translator-pages-' . substr(md5((string) mt_rand()), 0, 8);
        $slugFr = $suffix . '-fr';
        $slugEn = $suffix . '-en';

        $siteFr = $api->create('sites', [
            'o:slug' => $slugFr,
            'o:title' => 'Site original',
            'o:theme' => 'default',
        ])->getContent();
        $this->siteIds[] = $siteFr->id();
        $siteSettings->set('locale', 'fr', $siteFr->id());

        $siteEn = $api->create('sites', [
            'o:slug' => $slugEn,
            'o:title' => 'Copied site',
            'o:theme' => 'default',
        ])->getContent();
        $this->siteIds[] = $siteEn->id();
        $siteSettings->set('locale', 'en-gb', $siteEn->id());

        // The copy has the same blocks than the original, as the job
        // DuplicateSite of the module Internationalisation builds them.
        $blocks = [
            ['o:layout' => 'pageTitle', 'o:data' => []],
            ['o:layout' => 'html', 'o:data' => ['html' => self::HTML_FR]],
            ['o:layout' => 'browsePreview', 'o:data' => [
                'heading' => self::HEADING_FR,
                'query' => 'resource_class_id[]=25',
                'limit' => '3',
            ]],
        ];

        $this->sourcePageId = $api->create('site_pages', [
            'o:site' => ['o:id' => $siteFr->id()],
            'o:slug' => 'page',
            'o:title' => self::TITLE_FR,
            'o:block' => $blocks,
        ])->getContent()->id();

        $this->targetPageId = $api->create('site_pages', [
            'o:site' => ['o:id' => $siteEn->id()],
            'o:slug' => 'page',
            'o:title' => self::TITLE_FR,
            'o:block' => $blocks,
        ])->getContent()->id();

        $this->relatePages($this->sourcePageId, $this->targetPageId);

        foreach ([
            [self::TITLE_FR, self::TITLE_EN],
            [self::HTML_FR, self::HTML_EN],
            [self::HEADING_FR, self::HEADING_EN],
        ] as [$string, $translation]) {
            $this->translationIds[] = $api->create('translations', [
                'o:string' => $string,
                'o:lang_source' => 'fr',
                'o:lang_target' => 'en-gb',
                'o:translation' => $translation,
                'o:automatic' => true,
            ])->getContent()->id();
        }

        foreach ([
            'translator_deepl_api_key' => $this->getService('Omeka\Cipher')->encrypt('fake-key-for-tests'),
            'translator_lang_pairs' => ['fr = en-gb'],
            'translator_pages_include' => PageTexts::KEYS_DEFAULT,
            'translator_pages_exclude' => [],
            'internationalisation_site_groups' => [
                $slugFr => [$slugFr, $slugEn],
                $slugEn => [$slugFr, $slugEn],
            ],
        ] as $name => $value) {
            $this->previousSettings[$name] = $settings->get($name);
            $settings->set($name, $value);
        }
    }

    public function tearDown(): void
    {
        $api = $this->getService('Omeka\ApiManager');
        foreach ($this->translationIds as $id) {
            try {
                $api->delete('translations', $id);
            } catch (\Throwable $e) {
            }
        }
        foreach ($this->siteIds as $id) {
            try {
                $api->delete('sites', $id);
            } catch (\Throwable $e) {
            }
        }
        $this->translationIds = [];
        $this->siteIds = [];

        $settings = $this->getService('Omeka\Settings');
        foreach ($this->previousSettings as $name => $value) {
            $value === null ? $settings->delete($name) : $settings->set($name, $value);
        }
        $this->previousSettings = [];

        parent::tearDown();
    }

    protected function connection(): \Doctrine\DBAL\Connection
    {
        return $this->getService('Omeka\Connection');
    }

    /**
     * The relations between the copied pages belong to the module
     * Internationalisation, that is not a dependency of this module, so the
     * table is created here to keep the test independent from it.
     *
     * @see \Internationalisation\Entity\SitePageRelation
     */
    protected function createRelationTable(): void
    {
        $this->connection()->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `site_page_relation` (
                `id` INT AUTO_INCREMENT NOT NULL,
                `page_id` INT NOT NULL,
                `related_page_id` INT NOT NULL,
                UNIQUE INDEX `idx_site_page_relation` (`page_id`, `related_page_id`),
                PRIMARY KEY(`id`)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
    }

    protected function relatePages(int $pageId, int $relatedPageId): void
    {
        [$a, $b] = $pageId < $relatedPageId ? [$pageId, $relatedPageId] : [$relatedPageId, $pageId];
        $this->connection()->executeStatement(
            'INSERT INTO site_page_relation (page_id, related_page_id) VALUES (:a, :b)',
            ['a' => $a, 'b' => $b],
            ['a' => ParameterType::INTEGER, 'b' => ParameterType::INTEGER]
        );
    }

    /**
     * Run the job like the dispatcher does, but synchronously.
     */
    protected function runJob(array $args = []): void
    {
        $entityManager = $this->getService('Omeka\EntityManager');

        // The setters of the entity Job of the core return nothing.
        $jobEntity = new Job();
        $jobEntity->setClass(TranslatePages::class);
        $jobEntity->setArgs($args);
        $jobEntity->setStatus(Job::STATUS_IN_PROGRESS);
        $jobEntity->setStarted(new \DateTime('now'));
        $entityManager->persist($jobEntity);
        $entityManager->flush();

        (new TranslatePages($jobEntity, $this->getServiceManager()))->perform();

        $entityManager->clear();
    }

    /**
     * The pages are updated with a direct sql, so they are read the same way.
     */
    protected function blocksOfPage(int $pageId): array
    {
        $rows = $this->connection()->executeQuery(
            'SELECT layout, data FROM site_page_block WHERE page_id = :id ORDER BY position ASC',
            ['id' => $pageId],
            ['id' => ParameterType::INTEGER]
        )->fetchAllAssociative();

        return array_map(fn ($row) => [
            'layout' => $row['layout'],
            'data' => json_decode((string) $row['data'], true),
        ], $rows);
    }

    /**
     * The positions of the blocks start at 1 in the core, so they are read and
     * not guessed.
     */
    protected function positionOfLayout(int $pageId, string $layout): int
    {
        return (int) $this->connection()->fetchOne(
            'SELECT position FROM site_page_block WHERE page_id = :id AND layout = :layout ORDER BY position ASC',
            ['id' => $pageId, 'layout' => $layout],
            ['id' => ParameterType::INTEGER, 'layout' => ParameterType::STRING]
        );
    }

    protected function setBlockData(int $pageId, string $layout, array $data): void
    {
        $this->connection()->executeStatement(
            'UPDATE site_page_block SET data = :data WHERE page_id = :id AND position = :position',
            [
                'data' => json_encode($data),
                'id' => $pageId,
                'position' => $this->positionOfLayout($pageId, $layout),
            ],
            [
                'data' => ParameterType::STRING,
                'id' => ParameterType::INTEGER,
                'position' => ParameterType::INTEGER,
            ]
        );
    }

    protected function titleOfPage(int $pageId): string
    {
        return (string) $this->connection()->fetchOne(
            'SELECT title FROM site_page WHERE id = :id',
            ['id' => $pageId],
            ['id' => ParameterType::INTEGER]
        );
    }

    protected function hashesOfPage(int $pageId): array
    {
        $hashes = $this->connection()->fetchOne(
            'SELECT hashes FROM translate_page WHERE page_id = :id',
            ['id' => $pageId],
            ['id' => ParameterType::INTEGER]
        );

        return is_string($hashes) ? (json_decode($hashes, true) ?: []) : [];
    }

    public function testCopiedPageIsTranslated(): void
    {
        $this->runJob();

        $this->assertSame(self::TITLE_EN, $this->titleOfPage($this->targetPageId));

        $blocks = $this->blocksOfPage($this->targetPageId);
        $this->assertSame(self::HTML_EN, $blocks[1]['data']['html']);
        $this->assertSame(self::HEADING_EN, $blocks[2]['data']['heading']);
        // The keys that are not translatable are kept as is.
        $this->assertSame('resource_class_id[]=25', $blocks[2]['data']['query']);
        $this->assertSame('3', $blocks[2]['data']['limit']);
    }

    public function testOriginalPageIsNotModified(): void
    {
        $this->runJob();

        $this->assertSame(self::TITLE_FR, $this->titleOfPage($this->sourcePageId));
        $blocks = $this->blocksOfPage($this->sourcePageId);
        $this->assertSame(self::HTML_FR, $blocks[1]['data']['html']);
        $this->assertSame(self::HEADING_FR, $blocks[2]['data']['heading']);
    }

    public function testStateIsStored(): void
    {
        $this->assertSame([], $this->hashesOfPage($this->targetPageId));

        $this->runJob();

        $hashes = $this->hashesOfPage($this->targetPageId);
        $this->assertArrayHasKey('title', $hashes);
        $this->assertArrayHasKey(
            'block:' . $this->positionOfLayout($this->sourcePageId, 'html'),
            $hashes
        );
        $this->assertArrayHasKey(
            'block:' . $this->positionOfLayout($this->sourcePageId, 'browsePreview'),
            $hashes
        );
        // A block without any translatable string is not stored.
        $this->assertArrayNotHasKey(
            'block:' . $this->positionOfLayout($this->sourcePageId, 'pageTitle'),
            $hashes
        );

        $sourcePageId = $this->connection()->fetchOne(
            'SELECT source_page_id FROM translate_page WHERE page_id = :id',
            ['id' => $this->targetPageId],
            ['id' => ParameterType::INTEGER]
        );
        $this->assertSame($this->sourcePageId, (int) $sourcePageId);
    }

    /**
     * The original text did not change, so the copy is not translated again and
     * the correction made by a user is kept.
     */
    public function testCorrectionIsKeptWhenTheOriginalDidNotChange(): void
    {
        $this->runJob();

        $blocks = $this->blocksOfPage($this->targetPageId);
        $this->assertSame(self::HTML_EN, $blocks[1]['data']['html']);

        // A user corrects the translation of the copy.
        $correction = '<p>A better text to translate.</p>';
        $this->setBlockData($this->targetPageId, 'html', ['html' => $correction]);

        $this->runJob();

        $blocks = $this->blocksOfPage($this->targetPageId);
        $this->assertSame($correction, $blocks[1]['data']['html']);
    }

    /**
     * When the original text changes, the copy is translated again.
     */
    public function testBlockIsTranslatedAgainWhenTheOriginalChanged(): void
    {
        $this->runJob();

        $newFr = '<p>Un autre texte à traduire.</p>';
        $newEn = '<p>Another text to translate.</p>';
        $this->translationIds[] = $this->getService('Omeka\ApiManager')->create('translations', [
            'o:string' => $newFr,
            'o:lang_source' => 'fr',
            'o:lang_target' => 'en-gb',
            'o:translation' => $newEn,
            'o:automatic' => true,
        ])->getContent()->id();

        $this->setBlockData($this->sourcePageId, 'html', ['html' => $newFr]);

        $this->runJob();

        $blocks = $this->blocksOfPage($this->targetPageId);
        $this->assertSame($newEn, $blocks[1]['data']['html']);
    }

    /**
     * A mirror page displays the original page, so it has nothing to translate.
     */
    public function testMirrorPageIsSkipped(): void
    {
        // Replace the blocks of the copy by a single mirror block.
        $this->connection()->executeStatement(
            'DELETE FROM site_page_block WHERE page_id = :id',
            ['id' => $this->targetPageId],
            ['id' => ParameterType::INTEGER]
        );
        $this->connection()->executeStatement(
            'INSERT INTO site_page_block (page_id, layout, data, position) VALUES (:id, :layout, :data, 0)',
            [
                'id' => $this->targetPageId,
                'layout' => 'mirrorPage',
                'data' => json_encode(['page' => $this->sourcePageId]),
            ],
            ['id' => ParameterType::INTEGER, 'layout' => ParameterType::STRING, 'data' => ParameterType::STRING]
        );

        $this->runJob();

        $blocks = $this->blocksOfPage($this->targetPageId);
        $this->assertCount(1, $blocks);
        $this->assertSame('mirrorPage', $blocks[0]['layout']);
        $this->assertSame(self::TITLE_FR, $this->titleOfPage($this->targetPageId));
        $this->assertSame([], $this->hashesOfPage($this->targetPageId));
    }

    /**
     * Only the pairs with an explicit source language give the direction of the
     * translation of the pages.
     */
    public function testPairWithoutSourceIsSkipped(): void
    {
        $this->getService('Omeka\Settings')->set('translator_lang_pairs', ['en-gb']);

        $this->runJob();

        $this->assertSame(self::TITLE_FR, $this->titleOfPage($this->targetPageId));
        $this->assertSame([], $this->hashesOfPage($this->targetPageId));
    }

    /**
     * A page that is not related to another one has no copy to translate.
     */
    public function testPageWithoutRelationIsSkipped(): void
    {
        $this->connection()->executeStatement(
            'DELETE FROM site_page_relation WHERE page_id = :id OR related_page_id = :id',
            ['id' => $this->sourcePageId],
            ['id' => ParameterType::INTEGER]
        );

        $this->runJob();

        $this->assertSame(self::TITLE_FR, $this->titleOfPage($this->targetPageId));
        $this->assertSame([], $this->hashesOfPage($this->targetPageId));
    }

    /**
     * A single page can be processed, for example after it is saved.
     */
    public function testJobAcceptsPageIds(): void
    {
        $this->runJob(['page_ids' => (string) $this->sourcePageId]);
        $this->assertSame(self::TITLE_EN, $this->titleOfPage($this->targetPageId));
    }

    /**
     * The page is translated in place, into the locale of its own site, without
     * any relation nor site group.
     *
     * The source language is not set, so it is detected by the service: the
     * translations are stored without language here.
     */
    public function testModeSelfTranslatesThePageInPlace(): void
    {
        $api = $this->getService('Omeka\ApiManager');
        foreach ([
            [self::TITLE_FR, self::TITLE_EN],
            [self::HTML_FR, self::HTML_EN],
        ] as [$string, $translation]) {
            $this->translationIds[] = $api->create('translations', [
                'o:string' => $string,
                'o:lang_source' => null,
                'o:lang_target' => 'en-gb',
                'o:translation' => $translation,
                'o:automatic' => true,
            ])->getContent()->id();
        }

        // The page of the English site keeps the French content of the source.
        $targetTitleBefore = $this->titleOfPage($this->targetPageId);
        $this->assertSame(self::TITLE_FR, $targetTitleBefore);

        $this->runJob(['page_ids' => (string) $this->targetPageId, 'mode' => 'self']);

        $this->assertSame(self::TITLE_EN, $this->titleOfPage($this->targetPageId));
        $blocks = $this->blocksOfPage($this->targetPageId);
        $this->assertSame(self::HTML_EN, $blocks[1]['data']['html']);

        // The original page of the French site is not touched.
        $this->assertSame(self::TITLE_FR, $this->titleOfPage($this->sourcePageId));
    }

    /**
     * The hashes are computed on the result, so a second run does not translate
     * the translation.
     */
    public function testModeSelfIsIdempotent(): void
    {
        $api = $this->getService('Omeka\ApiManager');
        foreach ([
            [self::TITLE_FR, self::TITLE_EN],
            [self::HTML_FR, self::HTML_EN],
            // A translation of the translation, that must never be used.
            [self::TITLE_EN, 'Translated twice'],
            [self::HTML_EN, '<p>Translated twice.</p>'],
        ] as [$string, $translation]) {
            $this->translationIds[] = $api->create('translations', [
                'o:string' => $string,
                'o:lang_source' => null,
                'o:lang_target' => 'en-gb',
                'o:translation' => $translation,
                'o:automatic' => true,
            ])->getContent()->id();
        }

        $args = ['page_ids' => (string) $this->targetPageId, 'mode' => 'self'];
        $this->runJob($args);
        $this->runJob($args);

        $this->assertSame(self::TITLE_EN, $this->titleOfPage($this->targetPageId));
        $blocks = $this->blocksOfPage($this->targetPageId);
        $this->assertSame(self::HTML_EN, $blocks[1]['data']['html']);
    }

    /**
     * A page already written in the language of its site is not rewritten.
     *
     * The service may return the text unchanged, in particular when the source
     * language is detected and happens to be the target one. The page keeps its
     * content and the hashes are stored, so nothing is asked again.
     */
    public function testModeSelfKeepsAPageAlreadyInTheTargetLanguage(): void
    {
        $api = $this->getService('Omeka\ApiManager');
        // The service returns the same strings: nothing to change.
        foreach ([self::TITLE_FR, self::HTML_FR] as $string) {
            $this->translationIds[] = $api->create('translations', [
                'o:string' => $string,
                'o:lang_source' => null,
                'o:lang_target' => 'en-gb',
                'o:translation' => $string,
                'o:automatic' => true,
            ])->getContent()->id();
        }

        $this->runJob(['page_ids' => (string) $this->targetPageId, 'mode' => 'self']);

        // The page is untouched.
        $this->assertSame(self::TITLE_FR, $this->titleOfPage($this->targetPageId));
        $blocks = $this->blocksOfPage($this->targetPageId);
        $this->assertSame(self::HTML_FR, $blocks[1]['data']['html']);

        // The hashes are stored anyway, so a second run has nothing to do.
        $this->assertNotSame([], $this->hashesOfPage($this->targetPageId));
    }

    /**
     * The sidebar may limit the translation to some sites.
     */
    public function testJobAcceptsSiteIds(): void
    {
        $this->runJob([
            'page_ids' => (string) $this->sourcePageId,
            'site_ids' => [$this->siteIds[1]],
        ]);

        $this->assertSame(self::TITLE_EN, $this->titleOfPage($this->targetPageId));
    }

    /**
     * A site that is not selected keeps its page untouched.
     */
    public function testJobIgnoresTheSitesOutOfTheFilter(): void
    {
        // Only the site of the source page is selected, so the copy of the
        // other site is not updated.
        $this->runJob([
            'page_ids' => (string) $this->sourcePageId,
            'site_ids' => [$this->siteIds[0]],
        ]);

        $this->assertSame(self::TITLE_FR, $this->titleOfPage($this->targetPageId));
        $blocks = $this->blocksOfPage($this->targetPageId);
        $this->assertSame(self::HTML_FR, $blocks[1]['data']['html']);
    }

    /**
     * Get the messages logged during a job.
     */
    protected function runJobAndGetLogs(array $args): array
    {
        $writer = new \Laminas\Log\Writer\Mock();
        $logger = $this->getService('Omeka\Logger');
        $logger->addWriter($writer);

        $this->runJob($args);

        // The placeholders are substituted by a processor of the logger, that
        // may run after the writer, so the context is merged here.
        return array_map(function (array $event): string {
            $message = (string) $event['message'];
            $context = $event['extra'] ?? [];
            if (is_array($context) && $context) {
                $replace = [];
                foreach ($context as $key => $value) {
                    if (is_scalar($value) || $value === null) {
                        $replace['{' . $key . '}'] = (string) $value;
                    }
                }
                $message = strtr($message, $replace);
            }
            return $message;
        }, $writer->events);
    }

    /**
     * The result of the pages is logged once, and not one entry by page.
     */
    public function testResultOfThePagesIsLoggedOnce(): void
    {
        $messages = $this->runJobAndGetLogs(['page_ids' => (string) $this->sourcePageId]);

        $summaries = array_values(array_filter(
            $messages,
            fn ($message) => strpos((string) $message, 'Pages: ') === 0
        ));

        $this->assertCount(1, $summaries);
        $this->assertStringContainsString('1 translated', $summaries[0]);
        $this->assertStringContainsString('blocks updated', $summaries[0]);
    }

    /**
     * A page translated twice is reported as unchanged the second time.
     */
    public function testResultOfAnUnchangedPage(): void
    {
        $this->runJob(['page_ids' => (string) $this->sourcePageId]);

        $messages = $this->runJobAndGetLogs(['page_ids' => (string) $this->sourcePageId]);
        $summaries = array_values(array_filter(
            $messages,
            fn ($message) => strpos((string) $message, 'Pages: ') === 0
        ));

        $this->assertCount(1, $summaries);
        $this->assertStringContainsString('unchanged', $summaries[0]);
    }

    public function testJobIgnoresPagesOutOfTheRequestedIds(): void
    {
        $this->runJob(['page_ids' => (string) ($this->sourcePageId + 100000)]);
        $this->assertSame(self::TITLE_FR, $this->titleOfPage($this->targetPageId));
    }
}
