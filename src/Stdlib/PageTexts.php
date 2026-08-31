<?php declare(strict_types=1);

namespace Translator\Stdlib;

/**
 * Extract and replace the translatable strings of the blocks of a site page.
 *
 * Unlike the values of a resource, the data of a block is a free json, so there
 * is no way to know which keys contain a text for a end user and which ones
 * contain a setting, an id, a template name, a query, etc. So the keys to
 * translate are listed in the main settings, for example "html" or "heading".
 * The keys are searched recursively, so the sub-blocks and the attachments are
 * managed too.
 *
 * The strings are not indexed by their path: the module stores the translations
 * without any context, so two identical strings always have the same
 * translation and can be replaced together.
 */
class PageTexts
{
    /**
     * Pseudo-key used to translate the title of the page itself.
     *
     * @var string
     */
    const KEY_PAGE_TITLE = 'page_title';

    /**
     * Keys of the data of the blocks of the core and of the common modules that
     * contain a text displayed to the end user.
     *
     * @var array
     */
    const KEYS_DEFAULT = [
        'html',
        'heading',
        'caption',
        'title',
        'subtitle',
        'text',
        'content',
        'body',
        'label',
        'link-text',
        'linkText',
        'more',
        self::KEY_PAGE_TITLE,
    ];

    /**
     * @var array
     */
    protected $keysToInclude;

    /**
     * @var array
     */
    protected $layoutsToExclude;

    public function __construct(array $keysToInclude, array $layoutsToExclude = [])
    {
        $this->keysToInclude = array_fill_keys(array_filter(array_map('trim', $keysToInclude)), true);
        $this->layoutsToExclude = array_fill_keys(array_filter(array_map('trim', $layoutsToExclude)), true);
    }

    /**
     * Check if at least one key is configured, so the pages are managed.
     */
    public function hasKeys(): bool
    {
        return (bool) $this->keysToInclude;
    }

    /**
     * Check if the title of the pages should be translated.
     */
    public function hasPageTitle(): bool
    {
        return isset($this->keysToInclude[self::KEY_PAGE_TITLE]);
    }

    /**
     * Check if the blocks of a layout should be translated.
     */
    public function isTranslatableLayout(?string $layout): bool
    {
        return $layout !== null
            && $layout !== ''
            && !isset($this->layoutsToExclude[$layout]);
    }

    /**
     * Get the unique translatable strings of the data of a block.
     *
     * @return array List of strings, indexed by themselves.
     */
    public function extract(array $data): array
    {
        $strings = [];
        $this->walk($data, null, $strings);
        return $strings;
    }

    /**
     * Replace the strings of the data of a block by their translations.
     *
     * @param array $translations Translations indexed by the original strings.
     */
    public function replace(array $data, array $translations, ?string $parentKey = null): array
    {
        if (!$translations) {
            return $data;
        }
        foreach ($data as $key => &$value) {
            $currentKey = is_int($key) ? $parentKey : (string) $key;
            if (is_array($value)) {
                $value = $this->replace($value, $translations, $currentKey);
            } elseif (is_string($value)
                && $currentKey !== null
                && isset($this->keysToInclude[$currentKey])
                && isset($translations[$value])
            ) {
                $value = $translations[$value];
            }
        }
        unset($value);
        return $data;
    }

    /**
     * Check if a string contains some html, so it needs a specific handling by
     * the translation service.
     */
    public static function isHtml(string $string): bool
    {
        return $string !== strip_tags($string);
    }

    /**
     * Append the translatable strings of an array to the list.
     *
     * The arrays are always walked, because a translatable key may be inside a
     * list of attachments or a group of blocks. For a list, the key is a
     * number, so the key of the parent is used.
     */
    protected function walk(array $data, ?string $parentKey, array &$strings): void
    {
        foreach ($data as $key => $value) {
            $currentKey = is_int($key) ? $parentKey : (string) $key;
            if (is_array($value)) {
                $this->walk($value, $currentKey, $strings);
            } elseif (is_string($value)
                && $currentKey !== null
                && isset($this->keysToInclude[$currentKey])
                && $this->isTranslatableString($value)
            ) {
                $strings[$value] = $value;
            }
        }
    }

    /**
     * A string that contains no letter, like a number, a size or a color, has
     * nothing to translate.
     */
    protected function isTranslatableString(string $string): bool
    {
        return trim($string) !== ''
            && !is_numeric($string)
            && preg_match('~\pL~u', $string) === 1;
    }
}
