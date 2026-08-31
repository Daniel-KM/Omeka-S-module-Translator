<?php declare(strict_types=1);

namespace TranslatorTest;

use CommonTest\AbstractTestCase;
use Iso639p3\Iso639p3;
use Translator\Module;

/**
 * The lang of a value is commonly a three letters code, bibliographic or
 * terminologic, and may use "_" and a region, whereas DeepL uses mainly two
 * letters codes. Such values used to be excluded from the translation without
 * any message, so normalizeLangCode() converts them.
 */
class ModuleTest extends AbstractTestCase
{
    public function testLibraryIsAutoloaded(): void
    {
        $this->assertTrue(class_exists(Iso639p3::class));
    }

    public function testSupportedLanguagesAreNotEmpty(): void
    {
        $this->assertNotEmpty(Module::$langsSupportedInput);
        $this->assertNotEmpty(Module::$langsSupportedOutput);
        $this->assertArrayHasKey('fr', Module::$langsSupportedInput);
        $this->assertArrayHasKey('en', Module::$langsSupportedInput);
    }

    public function testNormalizeLangCodeConvertsThreeLettersCodes(): void
    {
        // Terminologic and bibliographic codes.
        $this->assertSame('fr', Module::normalizeLangCode('fra'));
        $this->assertSame('fr', Module::normalizeLangCode('fre'));
        $this->assertSame('en', Module::normalizeLangCode('eng'));
        $this->assertSame('de', Module::normalizeLangCode('deu'));
        $this->assertSame('de', Module::normalizeLangCode('ger'));
        $this->assertSame('nl', Module::normalizeLangCode('dut'));
        $this->assertSame('nl', Module::normalizeLangCode('nld'));
        $this->assertSame('br', Module::normalizeLangCode('bre'));
        $this->assertSame('eu', Module::normalizeLangCode('eus'));
        $this->assertSame('eu', Module::normalizeLangCode('baq'));
        $this->assertSame('zh', Module::normalizeLangCode('chi'));
        $this->assertSame('zh', Module::normalizeLangCode('zho'));
    }

    /**
     * The lang of a value should use "-", but "_" is common, in particular when
     * values are imported from another tool.
     */
    public function testNormalizeLangCodeAcceptsUnderscoreAndRegion(): void
    {
        $this->assertSame('fr', Module::normalizeLangCode('fr_FR'));
        $this->assertSame('fr', Module::normalizeLangCode('fr-FR'));
        $this->assertSame('fr', Module::normalizeLangCode('fr-CA'));
        $this->assertSame('pt', Module::normalizeLangCode('pt_BR'));
        $this->assertSame('zh', Module::normalizeLangCode('zh_Hant_TW'));
        $this->assertSame('fr', Module::normalizeLangCode('FRE'));
        $this->assertSame('fr', Module::normalizeLangCode('Fr'));
    }

    /**
     * DeepL supports a few languages with a three letters code only, that must
     * not be converted.
     */
    public function testNormalizeLangCodeKeepsThreeLettersCodesOfDeepL(): void
    {
        foreach (['ace', 'bho', 'ceb', 'ckb', 'gom', 'kmr', 'lmo', 'mai', 'pag', 'pam', 'prs', 'scn', 'yue'] as $lang) {
            $this->assertSame($lang, Module::normalizeLangCode($lang), $lang);
            $this->assertArrayHasKey($lang, Module::$langsSupportedInput, $lang);
        }
    }

    /**
     * The code is returned as is when it cannot be converted, so the caller can
     * still check it against the supported languages and distinguish it from a
     * value without lang.
     */
    public function testNormalizeLangCodeKeepsUnsupportedCodes(): void
    {
        foreach (['guc', 'apy', 'way', 'lan', 'laz', 'test'] as $lang) {
            $this->assertSame($lang, Module::normalizeLangCode($lang), $lang);
            $this->assertArrayNotHasKey(Module::normalizeLangCode($lang), Module::$langsSupportedInput, $lang);
        }
    }

    public function testNormalizeLangCodeIsEmptyWithoutLang(): void
    {
        $this->assertSame('', Module::normalizeLangCode(''));
        $this->assertSame('', Module::normalizeLangCode(null));
    }

    /**
     * A value tagged with any variant of a supported language must be
     * translatable, and no variant may be claimed by two languages, else a
     * value would be sent with a wrong source language.
     */
    public function testEveryVariantOfASupportedLanguageIsNormalizedBack(): void
    {
        $owner = [];
        $collisions = [];
        foreach (array_keys(Module::$langsSupportedInput) as $lang) {
            $variants = Iso639p3::codes($lang) ?: [$lang];
            foreach ($variants as $variant) {
                if (isset($owner[$variant]) && $owner[$variant] !== $lang) {
                    $collisions[$variant] = [$owner[$variant], $lang];
                }
                $owner[$variant] = $lang;
                $this->assertSame($lang, Module::normalizeLangCode($variant), "$variant => $lang");
                $this->assertArrayHasKey(
                    Module::normalizeLangCode($variant),
                    Module::$langsSupportedInput,
                    $variant
                );
            }
        }
        $this->assertSame([], $collisions, 'A variant is claimed by two languages.');
        // The variants are more numerous than the supported languages.
        $this->assertGreaterThan(count(Module::$langsSupportedInput), count($owner));
    }

    /**
     * The results are cached, since the method is called for each value and
     * code2letters() loops on more than eight thousand codes.
     */
    public function testNormalizeLangCodeIsStableWhenCalledTwice(): void
    {
        foreach (['fre', 'fr_FR', 'guc', '', 'yue'] as $lang) {
            $first = Module::normalizeLangCode($lang);
            $this->assertSame($first, Module::normalizeLangCode($lang), var_export($lang, true));
        }
    }
}
