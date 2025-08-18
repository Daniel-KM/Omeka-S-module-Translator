<?php declare(strict_types=1);

namespace Translate;

if (!class_exists('Common\TraitModule', false)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Module\AbstractModule;

class Module extends AbstractModule
{
    use TraitModule;

    public const NAMESPACE = __NAMESPACE__;

    /**
     * Read-only (but php 7.4).
     * @see https://developers.deepl.com/docs/getting-started/supported-languages
     */
    public static $langsSupportedInput = [
        'ar' => 'Arabic',
        'bg' => 'Bulgarian',
        'cs' => 'Czech',
        'da' => 'Danish',
        'de' => 'German',
        'el' => 'Greek',
        'en' => 'English',
        'es' => 'Spanish',
        'et' => 'Estonian',
        'fi' => 'Finnish',
        'fr' => 'French',
        'he' => 'Hebrew',
        'hu' => 'Hungarian',
        'id' => 'Indonesian',
        'it' => 'Italian',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'lt' => 'Lithuanian',
        'lv' => 'Latvian',
        'nb' => 'Norwegian Bokmål',
        'nl' => 'Dutch',
        'pl' => 'Polish',
        'pt' => 'Portuguese',
        'ro' => 'Romanian',
        'ru' => 'Russian',
        'sk' => 'Slovak',
        'sl' => 'Slovenian',
        'sv' => 'Swedish',
        'th' => 'Thai',
        'tr' => 'Turkish',
        'uk' => 'Ukrainian',
        'vi' => 'Vietnamese',
        'zh' => 'Chinese',
    ];

    /**
     * Read-only (but php 7.4).
     * @see https://developers.deepl.com/docs/getting-started/supported-languages
     */
    public static $langsSupportedOutput = [
        'ar' => 'Arabic',
        'bg' => 'Bulgarian',
        'cs' => 'Czech',
        'da' => 'Danish',
        'de' => 'German',
        'el' => 'Greek',
        'en-gb' => 'English (British)',
        'en-us' => 'English (American)',
        'es' => 'Spanish',
        'es-419' => 'Spanish (Latin American)',
        'et' => 'Estonian',
        'fi' => 'Finnish',
        'fr' => 'French',
        'he' => 'Hebrew',
        'hu' => 'Hungarian',
        'id' => 'Indonesian',
        'it' => 'Italian',
        'ja' => 'Japanese',
        'ko' => 'Korean',
        'lt' => 'Lithuanian',
        'lv' => 'Latvian',
        'nb' => 'Norwegian Bokmål',
        'nl' => 'Dutch',
        'pl' => 'Polish',
        'pt-br' => 'Portuguese (Brazilian)',
        'pt-pt' => 'Portuguese',
        'ro' => 'Romanian',
        'ru' => 'Russian',
        'sk' => 'Slovak',
        'sl' => 'Slovenian',
        'sv' => 'Swedish',
        'th' => 'Thai',
        'tr' => 'Turkish',
        'uk' => 'Ukrainian',
        'vi' => 'Vietnamese',
        'zh-hans' => 'Chinese (simplified)',
        'zh-hant' => 'Chinese (traditional)',
    ];

    public function init(ModuleManager $moduleManager): void
    {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $translate = $services->get('ControllerPluginManager')->get('translate');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.71')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.71'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }
    }

    protected function postInstall(): void
    {
        /**
         * @var \Omeka\Api\Manager $api
         * @var \Omeka\Settings\Settings $settings
         * @var \Omeka\Settings\SiteSettings $siteSettings
         */
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');
        $settings = $services->get('Omeka\Settings');
        $siteSettings = $services->get('Omeka\Settings\Site');

        $settings->set('translate_properties_include', [
            'properties_max_500',
            'dcterms:title',
            'dcterms:description',
        ]);
        $settings->set('translate_properties_exclude', [
            'properties_min_500',
            'bibo:content',
            'extracttext:extracted_text',
        ]);

        $mainLocale = $settings->get('locale', 'en-US');
        if (!$mainLocale) {
            return;
        }

        $mainLocale = strtr($mainLocale, '_', '-');
        $pos = strpos($mainLocale, '-');
        $mainLocale = $pos
            ? mb_substr(mb_strtolower($mainLocale), 0, $pos) . '-' . mb_substr(mb_strtolower($mainLocale), $pos + 1)
            : mb_strtolower($mainLocale);
        // Don't set default language by default, because the user may prefer
        // "skip" or "auto" (auto by default).
        // $settings->set('translate_lang_source_default', $mainLocale);

        $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
        $pairs = [];
        foreach ($siteIds as $siteId) {
            $siteSettings->setTargetId($siteId);
            $siteLocale = strtr((string) $siteSettings->get('locale', $mainLocale ?: 'en-US'), '_', '-');
            $pos = strpos($siteLocale, '-');
            $siteLocale = $pos
                ? mb_substr(mb_strtolower($siteLocale), 0, $pos) . '-' . mb_substr(mb_strtolower($siteLocale), $pos + 1)
                : mb_strtolower($siteLocale);
            if ($siteLocale && $siteLocale !== $mainLocale) {
                // Pairs cannot be assocative, because main locale is multiple.
                $pairs[] = "$mainLocale = $siteLocale";
            }
        }
        $settings->set('translate_lang_pairs', $pairs);

        $plugins = $services->get('ControllerPluginManager');
        $url = $plugins->get('url');
        $messenger = $plugins->get('messenger');
        $messenger->addWarning((new PsrMessage(
            'Fill your DeepL api key, then set languages to translate in {link}main settings{link_end}.', // @translate
            ['link' => sprintf('<a href="%s">', $url->fromRoute('admin/default', ['controller' => 'setting'], ['fragment' => 'translate'])), 'link_end' => '</a>']
        ))->setEscapeHtml(false));
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        /** @var \Omeka\Permissions\Acl $acl */
        $acl = $this->getServiceLocator()->get('Omeka\Acl');

        // Translations have no visibility, so they are all public.
        // Only backend user can edit them and admin can batch-delete them.

        $backendRoles = [
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
            \Omeka\Permissions\Acl::ROLE_AUTHOR,
            \Omeka\Permissions\Acl::ROLE_RESEARCHER,
        ];
        $backendRolesExceptResearcher = [
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
            \Omeka\Permissions\Acl::ROLE_AUTHOR,
        ];
        $backendRolesAdmins = [
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
        ];

        $acl
            // Anybody can search and read translations mainly via api endpoint.
            // No translation is private.
            ->allow(
                null,
                [
                    \Translate\Api\Adapter\TranslateAdapter::class,
                ],
                [
                    'read',
                    'search',
                ]
            )
            ->allow(
                null,
                [
                    \Translate\Entity\Text::class,
                    \Translate\Entity\Translate::class,
                ],
                [
                    'read',
                ]
            )

            // All backend roles can search and read translations in admin.
            ->allow(
                $backendRoles,
                [
                    \Translate\Controller\Admin\IndexController::class,
                ],
                [
                    'index',
                    'browse',
                    'search',
                    'show',
                    'show-details',
                ]
            )

            // All roles except researcher can translate and batch translate.
            // Even author can batch process, except batch delete.
            ->allow(
                $backendRolesExceptResearcher,
                [
                    \Translate\Controller\Admin\IndexController::class,
                ],
                [
                    'add',
                    'edit',
                    'delete',
                    'delete-confirm',
                    'batch-edit',
                ]
            )
            ->allow(
                $backendRolesExceptResearcher,
                [
                    \Translate\Api\Adapter\TranslateAdapter::class,
                ],
                [
                    'create',
                    'update',
                    'delete',
                    'batch_update',
                ]
            )
            ->allow(
                $backendRolesExceptResearcher,
                [
                    \Translate\Entity\Text::class,
                    \Translate\Entity\Translate::class,
                ]
            )
            ->allow(
                $backendRolesAdmins,
                [
                    \Translate\Controller\Admin\IndexController::class,
                ],
                [
                    'batch-delete',
                ]
            )
            ->allow(
                $backendRolesAdmins,
                [
                    \Translate\Api\Adapter\TranslateAdapter::class,
                ],
                [
                    'batch_delete',
                ]
            )
        ;
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $adapters = [
            \Omeka\Api\Adapter\ItemAdapter::class,
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            \Omeka\Api\Adapter\MediaAdapter::class,
            \Omeka\Api\Adapter\ValueAnnotationAdapter::class,
            \Annotate\Api\Adapter\AnnotationAdapter::class,
            // This does not exists.
            // \Omeka\Api\Adapter\ResourceAdapter::class,
        ];
        foreach ($adapters as $adapter) {
            $sharedEventManager->attach(
                $adapter,
                'api.create.post',
                [$this, 'handleSavePost']
            );
            $sharedEventManager->attach(
                $adapter,
                'api.update.post',
                [$this, 'handleSavePost']
            );
        }

        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );
    }

    public function handleSavePost(Event $event): void
    {
        /**
         * @var \Omeka\Api\Manager $api
         * @var \Omeka\Api\Request $request
         * @var \Omeka\Settings\Settings $settings
         * @var \Common\Stdlib\EasyMeta $easyMeta
         * @var \Omeka\Entity\Resource $resource
         * @var \Omeka\Api\Adapter\AbstractEntityAdapter $adapter
         */
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        // Quick checks.

        $deeplApiKey = $settings->get('translate_deepl_api_key');
        if (!$deeplApiKey) {
            return;
        }

        $propertiesToInclude = $settings->get('translate_properties_include', []);
        if (!$propertiesToInclude) {
            return;
        }

        // This is an api-post event, so id is ready and checks are done.

        $resource = $event->getParam('response')->getContent();
        $adapter = $event->getTarget();
        $resource = $adapter->getRepresentation($resource);

        $textsToTranslate = $this->filterValuesToTranslate($resource);
        if (!$textsToTranslate) {
            return;
        }

        $results = [];
        foreach ($textsToTranslate as $langAndTexts) {
            // The lang source may be null for automatic detection.
            $texts = $langAndTexts['texts'];
            $langSource = $langAndTexts['source'];
            $langTarget = $langAndTexts['target'];
            $texts = $this->filterExistingTranslations($texts, $langSource, $langTarget);
            if ($texts) {
                $texts = array_values($texts);
                $translations = $this->translateDeepL($texts, $langSource, $langTarget);
                foreach ($translations as $key => $translation) {
                    $results[] = [
                        'o:string' => $texts[$key],
                        'o:lang_source' => $langSource,
                        'o:lang_target' => $langTarget,
                        'o:translation' => $translation->text,
                        'o:automatic' => true,
                    ];
                }
            }
        }

        if (!$results) {
            return;
        }

        // The results contain only new translations, because existing ones were
        // skipped above.
        $api = $services->get('Omeka\ApiManager');
        $api->batchCreate('translates', $results, [], ['continueOnError' => true]);
    }

    /**
     * Get the list of translatable values for each language pair.
     *
     * @return array Associative array with pair as key, and array of arrays as
     * value. Each array contains the source language, the target language and a
     * list of texts. The source language may be null for automatic detection.
     */
    protected function filterValuesToTranslate(AbstractResourceEntityRepresentation $resource): array
    {
        /**
         * @var \Omeka\Settings\Settings $settings
         * @var \Common\Stdlib\EasyMeta $easyMeta
         */
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        $propertiesToInclude = $settings->get('translate_properties_include', []);
        if (!$propertiesToInclude) {
            return [];
        }

        $pairs = $this->normalizeLanguagePairs();
        if (!$pairs) {
            return [];
        }

        $easyMeta = $services->get('Common\EasyMeta');

        $defaultLang = $settings->get('translate_lang_source_default');
        $pairsLangsSource = array_column($pairs, 'source', 'source');

        $isSkipEmptyLang = $defaultLang === 'skip'
            || ($defaultLang && !isset(self::$langsSupportedInput[$defaultLang]));
        if (!$defaultLang || $defaultLang === 'auto' || $defaultLang === 'skip') {
            $defaultLang = null;
        }

        $propertiesToExclude = $settings->get('translate_properties_exclude', []);

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

        if (in_array('properties', $propertiesToInclude)) {
            $propertiesToInclude = $easyMeta->propertyTerms();
        }

        $propertiesToInclude = array_combine($propertiesToInclude, $propertiesToInclude);
        $sizeLimitToInclude = array_intersect_key($propertySizes, $propertiesToInclude);
        $propertiesToInclude = array_diff_key($propertiesToInclude, $propertySizes);

        $propertiesToExclude = array_combine($propertiesToExclude, $propertiesToExclude);
        $sizeLimitToExclude = array_intersect_key($propertiesToExclude, $propertySizes);
        $propertiesToExclude = array_diff_key($propertiesToExclude, $propertySizes);

        $propertiesToInclude = array_diff_key($propertiesToInclude, $propertiesToExclude);

        $t = array_intersect_key($propertySizesMax, $sizeLimitToInclude);
        $sizeLimitToIncludeMax = $t ? max($t) : 0;
        $t = array_intersect_key($propertySizesMin, $sizeLimitToInclude);
        $sizeLimitToIncludeMin = $t ? min($t) : 0;
        $t = array_intersect_key($propertySizesMax, $sizeLimitToExclude);
        $sizeLimitToExcludeMax = $t ? min($t) : 0;
        $t = array_intersect_key($propertySizesMin, $sizeLimitToExclude);
        $sizeLimitToExcludeMin = $t ? max($t) : 0;
        $hasNoSizeLimitToInclude = !$sizeLimitToExcludeMin && !$sizeLimitToIncludeMax;

        $textsToTranslate = [];

        // Simplify list of values and filters properties.
        $allValues = $resource->values();
        $allValues = array_diff_key($allValues, $propertiesToExclude);
        if ($hasNoSizeLimitToInclude) {
            $allValues = array_intersect_key($allValues, $propertiesToInclude);
            if (!$allValues) {
                return [];
            }
        }

        /** @var \Omeka\Api\Representation\ValueRepresentation $value */
        foreach ($allValues as $term => $values) foreach ($values['values'] as $value) {
            // Don't translate linked resource, uri without label, numeric data,
            // or invalid lang.
            // Lang codes for values use "-", not "_".
            $val = (string) $value->value();
            $type = (string) $value->type();
            $length = mb_strlen($val);
            $lang = (string) $value->lang();
            $langCode = strtok($lang, '-');
            // Check exclusion first.
            if (!$val
                || is_numeric($val)
                || $value->valueResource()
                // TODO Manage html and xml via option tag_handling and other options, so process them separately.
                || in_array($type, ['boolean', 'json', 'html', 'xml', 'place'])
                || strpos($type, 'geographic:') === 0
                || strpos($type, 'geometry:') === 0
                || strpos($type, 'numeric:') === 0
                || ($sizeLimitToExcludeMax && $length <= $sizeLimitToExcludeMax)
                || ($sizeLimitToExcludeMin && $length > $sizeLimitToExcludeMin)
                || (!$langCode && $isSkipEmptyLang)
                || ($langCode && !isset(self::$langsSupportedInput[$langCode]))
            ) {
                continue;
            }
            // Check for explicit inclusion.
            if (($propertiesToInclude && isset($propertiesToInclude[$term]))
                || ($sizeLimitToIncludeMax && $length <= $sizeLimitToIncludeMax)
                || ($sizeLimitToIncludeMin && $length > $sizeLimitToIncludeMin)
            ) {
                // Add text for each pair according to lang and default lang.
                // When empty, the original lang is not kept when a default lang
                // is set. Else, it will be complex to manage a change of the
                // default lang.
                // The target languages are already filtered.
                foreach ($pairs as $pair) {
                    $langSource = $pair['source'] ?: $defaultLang;
                    if (isset($pairsLangsSource[$langSource])) {
                        $langTarget = $pair['target'];
                        $key = $langSource . '=' . $langTarget;
                        $textsToTranslate[$key]['source'] = $langSource;
                        $textsToTranslate[$key]['target'] = $langTarget;
                        $textsToTranslate[$key]['texts'][] = $val;
                    }
                }
            }
        }

        return $textsToTranslate;
    }

    /**
     * Normalize language pairs and remove unsupported pairs.
     *
     * @todo Store the result one time each time the setting is updated.
     */
    protected function normalizeLanguagePairs(): array
    {
        /**
         * @var \Omeka\Settings\Settings $settings
         */
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        $pairs = $settings->get('translate_lang_pairs');
        if (!$pairs) {
            return [];
        }

        $result = [];
        foreach ($pairs as $singleOrPair) {
            $r = array_map('trim', array_filter(explode('=', $singleOrPair)));
            if ($r) {
                $langSource = count($r) === 1 ? null : strtr(mb_strtolower($r[0]), '_', '-');
                $langTarget = strtr(mb_strtolower(count($r) === 1 ? $r[0] : $r[1]), '_', '-');
                if ($langTarget
                    && isset(self::$langsSupportedOutput[$langTarget])
                    && (!$langSource || isset(self::$langsSupportedInput[$langSource]))
                ) {
                    $result[] = [
                        'source' => $langSource ?: null,
                        'target' => $langTarget,
                    ];
                }
            }
        }

        return array_values(array_unique($result, SORT_REGULAR));
    }

    protected function filterExistingTranslations(array $texts, ?string $langSource, string $langTarget): array
    {
        /**
         * @var \Omeka\Api\Manager $api
         */
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        // TODO Simplify to avoid the loop.
        // FIXME Search empty language source.
        $filteredTexts = [];
        foreach ($texts as $text) {
            $existingTranslations = $api->search('translates', [
                'string' => $text,
                'lang_source' => $langSource,
                'lang_target' => $langTarget,
            ])->getContent();
            if (empty($existingTranslations)) {
                $filteredTexts[] = $text;
            }
        }

        return $filteredTexts;
    }

    protected function translateDeepL(array $texts, ?string $langSource, string $langTarget, array $options = []): array
    {
        /**
         * @var \Omeka\Settings\Settings $settings
         * @var \Laminas\Log\Logger $logger
         * @var \Laminas\Http\Client $httpClient
         */
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        $logger = $services->get('Omeka\Logger');
        // TODO Use psr logger for DeepL.
        // TODO Use omeka http client for DeepL.
        // $httpClient = $this->services->get('Omeka\HttpClient');

        $deeplApiKey = $settings->get('translate_deepl_api_key');
        if (!$deeplApiKey) {
            return [];
        }

        $deeplClient = new \DeepL\DeepLClient($deeplApiKey, [
            \DeepL\TranslatorOptions::SERVER_URL => null,
            \DeepL\TranslatorOptions::HEADERS => [],
            \DeepL\TranslatorOptions::TIMEOUT => null,
            \DeepL\TranslatorOptions::MAX_RETRIES => null,
            \DeepL\TranslatorOptions::PROXY => null,
            // \DeepL\TranslatorOptions::LOGGER => $logger,
            \DeepL\TranslatorOptions::HTTP_CLIENT => null,
            \DeepL\TranslatorOptions::SEND_PLATFORM_INFO => true,
            \DeepL\TranslatorOptions::APP_INFO => new \DeepL\AppInfo(
                'OmekaS-Translator',
                \Omeka\Module::VERSION . '-' . $services->get('Omeka\ModuleManager')->getModule('Translator')->getIni('version')
            ),
        ]);

        $options += [
            // Options for translation.
            \DeepL\TranslateTextOptions::CONTEXT => null,
            \DeepL\TranslateTextOptions::FORMALITY => 'prefer_more',
            \DeepL\TranslateTextOptions::MODEL_TYPE => 'prefer_quality_optimized',
            // Options for glossary.
            \DeepL\TranslateTextOptions::GLOSSARY => null,
            // Options for format.
            \DeepL\TranslateTextOptions::SPLIT_SENTENCES => null,
            \DeepL\TranslateTextOptions::PRESERVE_FORMATTING => true,
            // Options for tag format (html/xml).
            \DeepL\TranslateTextOptions::TAG_HANDLING => null,
            \DeepL\TranslateTextOptions::OUTLINE_DETECTION => true,
            \DeepL\TranslateTextOptions::SPLITTING_TAGS => null,
            \DeepL\TranslateTextOptions::NON_SPLITTING_TAGS => null,
            \DeepL\TranslateTextOptions::IGNORE_TAGS => null,
        ];

        return $deeplClient->translateText($texts, $langSource, $langTarget, $options);
    }
}
