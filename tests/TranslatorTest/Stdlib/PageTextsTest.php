<?php declare(strict_types=1);

namespace TranslatorTest\Stdlib;

use CommonTest\AbstractTestCase;
use Translator\Stdlib\PageTexts;

/**
 * The data of a page block is a free json, so only the configured keys are
 * translated, and they are searched recursively, because a text may be stored
 * in an attachment or in a group of blocks.
 */
class PageTextsTest extends AbstractTestCase
{
    protected function pageTexts(array $keys = ['html', 'heading', 'caption'], array $layouts = []): PageTexts
    {
        return new PageTexts($keys, $layouts);
    }

    public function testNoKeyMeansNoTranslation(): void
    {
        $pageTexts = $this->pageTexts([]);
        $this->assertFalse($pageTexts->hasKeys());
        $this->assertSame([], $pageTexts->extract(['heading' => 'A title']));
    }

    public function testExtractKeepsOnlyIncludedKeys(): void
    {
        $data = [
            'heading' => 'A title',
            'html' => '<p>Some text</p>',
            'template' => 'browse-preview-carousel',
            'limit' => '10',
            'query' => 'resource_class_id[]=25',
        ];
        $this->assertSame(
            ['A title' => 'A title', '<p>Some text</p>' => '<p>Some text</p>'],
            $this->pageTexts()->extract($data)
        );
    }

    /**
     * A text may be nested in a list of attachments or in a sub-block, so the
     * arrays are always walked, whatever their own key is.
     */
    public function testExtractIsRecursive(): void
    {
        $data = [
            'attachments' => [
                ['id' => 12, 'caption' => 'First caption'],
                ['id' => 13, 'caption' => 'Second caption'],
            ],
            'blocks' => [
                ['layout' => 'html', 'data' => ['html' => '<p>Nested</p>']],
            ],
        ];
        $this->assertSame(
            [
                'First caption' => 'First caption',
                'Second caption' => 'Second caption',
                '<p>Nested</p>' => '<p>Nested</p>',
            ],
            $this->pageTexts()->extract($data)
        );
    }

    /**
     * For a list, the key is a number, so the key of the parent is used.
     */
    public function testExtractUsesTheParentKeyForLists(): void
    {
        $data = ['heading' => ['First', 'Second']];
        $this->assertSame(
            ['First' => 'First', 'Second' => 'Second'],
            $this->pageTexts()->extract($data)
        );
    }

    /**
     * A string without any letter, like a size or a number, has nothing to
     * translate. A color like "#ff0000" contains letters, so it cannot be
     * detected here and should be excluded with the list of the keys.
     */
    public function testExtractSkipsStringsWithoutLetter(): void
    {
        $data = [
            'heading' => '2024',
            'caption' => '  ',
            'html' => '100 % (1/2)',
        ];
        $this->assertSame([], $this->pageTexts()->extract($data));
    }

    public function testExtractDeduplicatesStrings(): void
    {
        $data = [
            'heading' => 'Same',
            'attachments' => [
                ['caption' => 'Same'],
            ],
        ];
        $this->assertSame(['Same' => 'Same'], $this->pageTexts()->extract($data));
    }

    public function testReplaceKeepsTheStructure(): void
    {
        $data = [
            'heading' => 'A title',
            'template' => 'A title',
            'attachments' => [
                ['id' => 12, 'caption' => 'A caption'],
            ],
        ];
        $result = $this->pageTexts()->replace($data, [
            'A title' => 'Un titre',
            'A caption' => 'Une légende',
        ]);
        $this->assertSame(
            [
                'heading' => 'Un titre',
                // The key "template" is not translatable, even with the same
                // string as an included key.
                'template' => 'A title',
                'attachments' => [
                    ['id' => 12, 'caption' => 'Une légende'],
                ],
            ],
            $result
        );
    }

    public function testReplaceWithoutTranslationChangesNothing(): void
    {
        $data = ['heading' => 'A title'];
        $this->assertSame($data, $this->pageTexts()->replace($data, []));
    }

    public function testLayoutsToExclude(): void
    {
        $pageTexts = $this->pageTexts(['html'], ['browsePreview']);
        $this->assertTrue($pageTexts->isTranslatableLayout('html'));
        $this->assertFalse($pageTexts->isTranslatableLayout('browsePreview'));
        $this->assertFalse($pageTexts->isTranslatableLayout(''));
        $this->assertFalse($pageTexts->isTranslatableLayout(null));
    }

    public function testPageTitleIsAnExplicitKey(): void
    {
        $this->assertFalse($this->pageTexts()->hasPageTitle());
        $this->assertTrue($this->pageTexts(['page_title'])->hasPageTitle());
        $this->assertTrue($this->pageTexts(PageTexts::KEYS_DEFAULT)->hasPageTitle());
    }

    /**
     * The html requires a specific option of the translation service, so it is
     * collected apart.
     */
    public function testIsHtml(): void
    {
        $this->assertTrue(PageTexts::isHtml('<p>Some text</p>'));
        $this->assertTrue(PageTexts::isHtml('A <em>word</em>'));
        $this->assertFalse(PageTexts::isHtml('Some text'));
        $this->assertFalse(PageTexts::isHtml(''));
        // A comparison sign is not a tag.
        $this->assertFalse(PageTexts::isHtml('1 < 2 and 3 > 2'));
    }
}
