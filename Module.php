<?php declare(strict_types=1);

namespace Translator;

if (!class_exists('Common\TraitModule', false)) {
    require_once file_exists(dirname(__DIR__) . '/Common/src/TraitModule.php')
        ? dirname(__DIR__) . '/Common/src/TraitModule.php'
        : dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ValueRepresentation;
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
        // Core languages.
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
        // Extended languages (translation only).
        'af' => 'Afrikaans',
        'an' => 'Aragonese',
        'as' => 'Assamese',
        'ay' => 'Aymara',
        'az' => 'Azerbaijani',
        'ba' => 'Bashkir',
        'be' => 'Belarusian',
        'bn' => 'Bengali',
        'br' => 'Breton',
        'bs' => 'Bosnian',
        'ca' => 'Catalan',
        'cy' => 'Welsh',
        'eo' => 'Esperanto',
        'eu' => 'Basque',
        'fa' => 'Persian',
        'ga' => 'Irish',
        'gl' => 'Galician',
        'gn' => 'Guarani',
        'gu' => 'Gujarati',
        'ha' => 'Hausa',
        'hi' => 'Hindi',
        'hr' => 'Croatian',
        'ht' => 'Haitian Creole',
        'hy' => 'Armenian',
        'ig' => 'Igbo',
        'is' => 'Icelandic',
        'jv' => 'Javanese',
        'ka' => 'Georgian',
        'kk' => 'Kazakh',
        'ky' => 'Kyrgyz',
        'la' => 'Latin',
        'lb' => 'Luxembourgish',
        'ln' => 'Lingala',
        'mg' => 'Malagasy',
        'mi' => 'Maori',
        'mk' => 'Macedonian',
        'ml' => 'Malayalam',
        'mn' => 'Mongolian',
        'mr' => 'Marathi',
        'ms' => 'Malay',
        'mt' => 'Maltese',
        'my' => 'Burmese',
        'ne' => 'Nepali',
        'oc' => 'Occitan',
        'om' => 'Oromo',
        'pa' => 'Punjabi',
        'ps' => 'Pashto',
        'qu' => 'Quechua',
        'sa' => 'Sanskrit',
        'sq' => 'Albanian',
        'sr' => 'Serbian',
        'st' => 'Sesotho',
        'su' => 'Sundanese',
        'sw' => 'Swahili',
        'ta' => 'Tamil',
        'te' => 'Telugu',
        'tg' => 'Tajik',
        'tk' => 'Turkmen',
        'tl' => 'Tagalog',
        'tn' => 'Tswana',
        'ts' => 'Tsonga',
        'tt' => 'Tatar',
        'ur' => 'Urdu',
        'uz' => 'Uzbek',
        'wo' => 'Wolof',
        'xh' => 'Xhosa',
        'yi' => 'Yiddish',
        'zu' => 'Zulu',
        // Extended languages with 3-letter codes.
        'ace' => 'Acehnese',
        'bho' => 'Bhojpuri',
        'ceb' => 'Cebuano',
        'ckb' => 'Kurdish (Sorani)',
        'gom' => 'Konkani',
        'kmr' => 'Kurdish (Kurmanji)',
        'lmo' => 'Lombard',
        'mai' => 'Maithili',
        'pag' => 'Pangasinan',
        'pam' => 'Kapampangan',
        'prs' => 'Dari',
        'scn' => 'Sicilian',
        'yue' => 'Cantonese',
    ];

    /**
     * Read-only (but php 7.4).
     * @see https://developers.deepl.com/docs/getting-started/supported-languages
     */
    public static $langsSupportedOutput = [
        // Core languages.
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
        'pt-pt' => 'Portuguese (European)',
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
        // Extended languages (translation only).
        'af' => 'Afrikaans',
        'an' => 'Aragonese',
        'as' => 'Assamese',
        'ay' => 'Aymara',
        'az' => 'Azerbaijani',
        'ba' => 'Bashkir',
        'be' => 'Belarusian',
        'bn' => 'Bengali',
        'br' => 'Breton',
        'bs' => 'Bosnian',
        'ca' => 'Catalan',
        'cy' => 'Welsh',
        'eo' => 'Esperanto',
        'eu' => 'Basque',
        'fa' => 'Persian',
        'ga' => 'Irish',
        'gl' => 'Galician',
        'gn' => 'Guarani',
        'gu' => 'Gujarati',
        'ha' => 'Hausa',
        'hi' => 'Hindi',
        'hr' => 'Croatian',
        'ht' => 'Haitian Creole',
        'hy' => 'Armenian',
        'ig' => 'Igbo',
        'is' => 'Icelandic',
        'jv' => 'Javanese',
        'ka' => 'Georgian',
        'kk' => 'Kazakh',
        'ky' => 'Kyrgyz',
        'la' => 'Latin',
        'lb' => 'Luxembourgish',
        'ln' => 'Lingala',
        'mg' => 'Malagasy',
        'mi' => 'Maori',
        'mk' => 'Macedonian',
        'ml' => 'Malayalam',
        'mn' => 'Mongolian',
        'mr' => 'Marathi',
        'ms' => 'Malay',
        'mt' => 'Maltese',
        'my' => 'Burmese',
        'ne' => 'Nepali',
        'oc' => 'Occitan',
        'om' => 'Oromo',
        'pa' => 'Punjabi',
        'ps' => 'Pashto',
        'qu' => 'Quechua',
        'sa' => 'Sanskrit',
        'sq' => 'Albanian',
        'sr' => 'Serbian',
        'st' => 'Sesotho',
        'su' => 'Sundanese',
        'sw' => 'Swahili',
        'ta' => 'Tamil',
        'te' => 'Telugu',
        'tg' => 'Tajik',
        'tk' => 'Turkmen',
        'tl' => 'Tagalog',
        'tn' => 'Tswana',
        'ts' => 'Tsonga',
        'tt' => 'Tatar',
        'ur' => 'Urdu',
        'uz' => 'Uzbek',
        'wo' => 'Wolof',
        'xh' => 'Xhosa',
        'yi' => 'Yiddish',
        'zu' => 'Zulu',
        // Extended languages with 3-letter codes.
        'ace' => 'Acehnese',
        'bho' => 'Bhojpuri',
        'ceb' => 'Cebuano',
        'ckb' => 'Kurdish (Sorani)',
        'gom' => 'Konkani',
        'kmr' => 'Kurdish (Kurmanji)',
        'lmo' => 'Lombard',
        'mai' => 'Maithili',
        'pag' => 'Pangasinan',
        'pam' => 'Kapampangan',
        'prs' => 'Dari',
        'scn' => 'Sicilian',
        'yue' => 'Cantonese',
    ];

    /**
     * Read-only (but php 7.4).
     * When the target lang is short, try long lang code too.
     */
    public static $langsSupportedOutputShort = [
        'en' => [
            'en-gb',
            'en-us',
        ],
        'es' => [
            'es-419',
        ],
        'pt' => [
            'pt-br',
            'pt-pt',
        ],
        // TODO "zh-hant" should be first when the main language is zh-tw.
        'zh' => [
            'zh-hans',
            'zh-hant',
            // For laminas.
            'zh-cn',
            'zh-tw',
        ],
    ];

    public function init(ModuleManager $moduleManager): void
    {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $translator = $services->get('MvcTranslator');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.86')) {
            $message = new \Omeka\Stdlib\Message(
                $translator->translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.86'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }

        if (PHP_VERSION_ID < 80100) {
            $message = new \Omeka\Stdlib\Message(
                $translator->translate('This version of module %1$s requires a version of php ≥ %2$s.'), // @translate
                'Translator', '8.1'
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

        $settings->set('translator_properties_include', [
            'dcterms:title',
            'dcterms:description',
        ]);
        $settings->set('translator_properties_include_size', 'properties_max_500');
        $settings->set('translator_properties_exclude', [
            'bibo:content',
            'extracttext:extracted_text',
        ]);
        $settings->set('translator_properties_exclude_size', 'properties_min_500');

        // Without these keys, the pages of the site groups are not translated.
        $settings->set('translator_pages_include', \Translator\Stdlib\PageTexts::KEYS_DEFAULT);

        $mainLocale = $settings->get('locale') ?: $services->get('Config')['translator']['locale'] ?: 'en_US';
        if (!$mainLocale) {
            return;
        }

        $mainLocale = mb_strtolower(strtr($mainLocale, '_', '-'));
        // Don't set default language by default, because the user may prefer
        // "skip" or "auto" (auto by default).
        // $settings->set('translator_lang_source_default', $mainLocale);

        $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
        $pairs = [];
        foreach ($siteIds as $siteId) {
            $siteSettings->setTargetId($siteId);
            $siteLocale = $siteSettings->get('locale');
            $siteLocale = $siteLocale ? mb_strtolower(strtr($siteLocale, '_', '-')) : null;
            if ($siteLocale && $siteLocale !== $mainLocale) {
                // Pairs cannot be assocative, because main locale is multiple.
                $pairs[] = "$mainLocale = $siteLocale";
            }
        }
        $settings->set('translator_lang_pairs', $pairs);

        $plugins = $services->get('ControllerPluginManager');
        $url = $plugins->get('url');
        $messenger = $plugins->get('messenger');
        $messenger->addWarning((new PsrMessage(
            'Fill your DeepL api key, then set languages to translate in {link}main settings{link_end}.', // @translate
            ['link' => sprintf('<a href="%s">', htmlspecialchars($url->fromRoute('admin/default', ['controller' => 'setting'], ['fragment' => 'translator']))), 'link_end' => '</a>']
        ))->setEscapeHtml(false));
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        /** @var \Omeka\Permissions\Acl $acl */
        $acl = $this->getServiceLocator()->get('Omeka\Acl');

        // Translations have no visibility, so they are all public.
        // Only backend user can edit them and admin can batch-delete them.

        /*
        $backendRoles = [
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
            \Omeka\Permissions\Acl::ROLE_AUTHOR,
            \Omeka\Permissions\Acl::ROLE_RESEARCHER,
        ];
        */
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
                    \Translator\Api\Adapter\TranslationAdapter::class,
                ],
                [
                    'read',
                    'search',
                ]
            )
            ->allow(
                null,
                [
                    \Translator\Entity\Text::class,
                    \Translator\Entity\Translation::class,
                ],
                [
                    'read',
                ]
            )

            ->allow(
                $backendRolesExceptResearcher,
                [
                    \Translator\Api\Adapter\TranslationAdapter::class,
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
                    \Translator\Entity\Text::class,
                    \Translator\Entity\Translation::class,
                ]
            )
            ->allow(
                $backendRolesAdmins,
                [
                    \Translator\Api\Adapter\TranslationAdapter::class,
                ],
                [
                    'batch_delete',
                ]
            )
        ;
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // As long as there is not a main listener for resources (adapter,
        // controller, representation…), use a loop.

        $representations = [
            // \Omeka\Api\Representation\AbstractResourceEntityRepresentation::class,
            \Omeka\Api\Representation\ItemRepresentation::class,
            \Omeka\Api\Representation\ItemSetRepresentation::class,
            \Omeka\Api\Representation\MediaRepresentation::class,
            \Omeka\Api\Representation\ValueAnnotationRepresentation::class,
            \Annotate\Api\Representation\AnnotationRepresentation::class,
        ];
        foreach ($representations as $representation) {
            // Handle translated title.
            $sharedEventManager->attach(
                $representation,
                'rep.resource.title',
                [$this, 'handleResourceTitle']
            );
            // Handle filter of values according to settings.
            $sharedEventManager->attach(
                $representation,
                'rep.resource.display_values',
                [$this, 'handleResourceDisplayValues']
            );
        }

        // Store translations after creation or update of resources.
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

        // Translate the copies of a page of a site group after it is saved. The
        // creation is managed too, so a page added to the original site is
        // translated without waiting for a second save. The job never creates a
        // page and updates the copies with a direct sql, so a copy created by
        // the module Internationalisation cannot trigger a loop.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SitePageAdapter::class,
            'api.create.post',
            [$this, 'handleSavePagePost']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SitePageAdapter::class,
            'api.update.post',
            [$this, 'handleSavePagePost']
        );

        // Update the quick settings when needed.
        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'handleSiteSettings']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SiteAdapter::class,
            'api.create.post',
            [$this, 'handleSaveSitePost']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SiteAdapter::class,
            'api.update.post',
            [$this, 'handleSaveSitePost']
        );
        // It is useless to manage on-delete, because site settings are
        // automatically removed.
    }

    /**
     * Manage translation of the title.
     */
    public function handleResourceTitle(Event $event): void
    {
        /**
         * When we want a translated title, we don’t care of the existing title.
         * Just get the title via value(), that takes care of the language.
         * Similar logic can be found in \Omeka\Api\Representation\AbstractResourceEntityRepresentation::displayDescription()
         *
         * @var \Omeka\Mvc\Status $status
         * @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource
         */
        $services = $this->getServiceLocator();
        $status = $services->get('Omeka\Status');

        // The translation is done only for site.

        if (!$status->isSiteRequest()) {
            return;
        }

        $resource = $event->getTarget();
        $template = $resource->resourceTemplate();
        if ($template && $property = $template->titleProperty()) {
            $value = $resource->value($property->term()) ?? $resource->value('dcterms:title');
        } else {
            $value = $resource->value('dcterms:title');
        }

        if (!$value) {
            return;
        }

        $translation = $this->translateValue($value);

        if ($translation) {
            $event->setParam('title', $translation);
        }
    }

    /**
     * Manage translation of the resource values without change in views.
     *
     * A fake entity with the translation is used internally for each ValueRepresentation,
     * so it will return the right translated value.
     */
    public function handleResourceDisplayValues(Event $event): void
    {
        /**
         * @var \Omeka\Mvc\Status $status
         * @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource
         */
        $services = $this->getServiceLocator();
        $status = $services->get('Omeka\Status');

        // The translation is done only for site.

        if (!$status->isSiteRequest()) {
            return;
        }

        $values = $event->getParam('values');

        // TODO Check term early, even if there is an early check inside isTranslatableValue().

        /** @var \Omeka\Api\Representation\ValueRepresentation $value */
        foreach ($values as /* $term => */ &$valueData) foreach ($valueData['values'] as $value) {
            $translation = $this->translateValue($value);
            if ($translation) {
                $reflection = new \ReflectionClass($value);
                $reflectionProperty = $reflection->getProperty('value');
                $reflectionProperty->setAccessible(true);
                $valueEntity = $reflectionProperty->getValue($value);
                $valueEntity->setValue($translation);
                // No need to update the reflection property.
            }
        }
        unset($valueData);

        $event->setParam('values', $values);
    }

    public function translateValue(ValueRepresentation $value): ?string
    {
        static $localeSite;
        static $langTargets;
        static $defaultLangSource;
        static $initialized = false;

        /**
         * @var \Omeka\Api\Manager $api
         * @var \Doctrine\DBAL\Connection $connection
         * @var \Doctrine\ORM\EntityManager $entityManager
         *
         * @var \Omeka\Mvc\Status $status
         * @var \Omeka\Settings\Settings $settings
         * @var \Omeka\Settings\SiteSettings $siteSettings
         * @var \Common\View\Helper\DefaultSite $defaultSite
         * @var \Omeka\Mvc\Controller\Plugin\CurrentSite $currentSite
         * @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource
         */

        // TODO Manage more fallbacks, not just the short/long code. See module Internationalisation.
        // TODO Is it really useful to check pairs or just check lang source/target and do a query request if needed?

        // Here, only the type of value and the languages source and targets are
        // checked. Of course, there is no need to do more check when the value
        // has the right language.

        $services = $this->getServiceLocator();

        if (!$initialized) {
            $initialized = true;
            $localeSite = $this->getLocaleCurrentSite();

            $siteSettings = $services->get('Omeka\Settings\Site');
            try {
                $langTargets = $siteSettings->get('translator_lang_fallbacks', []);
            } catch (\Throwable $e) {
                $siteId = $this->getCurrentOrDefaultSiteId();
                $langTargets = $siteId
                    ? $siteSettings->get('translator_lang_fallbacks', [], $siteId)
                    : null;
            }

            $settings = $services->get('Omeka\Settings');
            $defaultLangSource = $settings->get('translator_lang_source_default') ?: null;
        }

        if (!$langTargets) {
            return null;
        }

        $isTranslatabelValue = $this->isTranslatableValue($value, $localeSite);
        if (!$isTranslatabelValue) {
            return null;
        }

        $langSource = $value->lang();
        if (!$langSource) {
            if ($defaultLangSource === 'skip') {
                return null;
            }
            $langSource = $defaultLangSource;
        }

        // DeepL input uses short codes without regionalization.
        $langSource = $langSource
            ? mb_strtolower((string) strtok(strtr($langSource, '_', '-'), '-'))
            : null;

        // TODO Update view helper Translation.
        // TODO Use orm or dbal to get translations? For multiple values: most of the time, there is only one record (show) or a list of titles (browse).
        // Use a direct sql to manage target language and fallbacks, until the
        // view helper will manage them.
        // The external translating service is not queried in real time.

        $services = $this->getServiceLocator();
        $connection = $services->get('Omeka\Connection');
        $qb = $connection->createQueryBuilder();
        $expr = $qb->expr();
        $qb
            ->select('translation.translation')
            ->from('translation', 'translation')
            ->innerJoin('translation', 'translate_text', 'text', 'text.id = translation.text_id')
            ->where($expr->eq('text.string', ':string'))
            ->setMaxResults(1)
        ;
        $bind = ['string' => $value->value()];
        $types = ['string' => ParameterType::STRING];
        if ($langSource) {
            $qb->andWhere($expr->eq('text.lang', ':lang_source'));
            $bind['lang_source'] = $langSource;
            $types['lang_source'] = ParameterType::STRING;
        } else {
            $qb->andWhere($expr->isNull('text.lang'));
        }

        if (count($langTargets) === 1) {
            $qb->andWhere($expr->eq('translation.lang', ':lang_target'));
            $bind['lang_target'] = reset($langTargets);
            $types['lang_target'] = ParameterType::STRING;
        } else {
            $qb
                ->andWhere($expr->in('translation.lang', ':lang_targets'))
                ->orderBy('FIELD(translation.lang, :lang_targets)', 'ASC');
            $bind['lang_targets'] = $langTargets;
            $types['lang_targets'] = Connection::PARAM_STR_ARRAY;
        }

        // Warning: the result may be false.
        $result = $qb->setParameters($bind, $types)->execute()->fetchOne();

        return is_string($result) ? $result : null;
    }

    /**
     * List locales according to the request for a site.
     *
     * @fixme Remove the exception that occurs with background job and api during update: job seems to set status as site.
     *
     * Adapted:
     * @see \Internationalisation\Module::getLocales()
     */
    public function handleSavePost(Event $event): void
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        // Quick checks: avoid further processing when the module is not
        // configured to translate.
        if (!$settings->get('translator_deepl_api_key')) {
            return;
        }
        if (!$settings->get('translator_properties_include', [])) {
            return;
        }

        // This is an api-post event, so id is ready and checks are done.
        $resource = $event->getParam('response')->getContent();
        $adapter = $event->getTarget();
        $id = $resource->getId();
        if (!$id) {
            return;
        }
        $resourceName = method_exists($adapter, 'getResourceName')
            ? $adapter->getResourceName()
            : null;
        if (!$resourceName) {
            return;
        }

        // Defer translation to a background job. Multiple saves in the same
        // request (batch create, import) are collapsed into a single job
        // dispatched at shutdown.
        /** @var \Common\Stdlib\DeferredJobDispatch $deferred */
        $deferred = $services->get('Common\DeferredJobDispatch');
        $deferred->defer(
            \Translator\Job\TranslateResources::class,
            'translator_resources',
            ['refs' => $resourceName . ':' . $id]
        );
    }

    /**
     * Translate the copies of a page of a site group after it is created or
     * updated.
     *
     * The job checks if the page is an original one inside a multilingual site
     * group: a copy is never translated back into the original. So a page that
     * is created as a copy in a target site only dispatches a job that has
     * nothing to do.
     */
    public function handleSavePagePost(Event $event): void
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        // Quick checks: avoid further processing when the module is not
        // configured to translate the pages.
        if (!$settings->get('translator_deepl_api_key')
            || !$settings->get('translator_pages_include', [])
            || !$settings->get('internationalisation_site_groups', [])
        ) {
            return;
        }

        $page = $event->getParam('response')->getContent();
        $id = $page->getId();
        if (!$id) {
            return;
        }

        // Defer translation to a background job. Multiple saves in the same
        // request are collapsed into a single job dispatched at shutdown.
        /** @var \Common\Stdlib\DeferredJobDispatch $deferred */
        $deferred = $services->get('Common\DeferredJobDispatch');
        $deferred->defer(
            \Translator\Job\TranslatePages::class,
            'translator_pages',
            ['page_ids' => $id]
        );
    }

    /**
     * Normalize the lang of a value into a lang code usable as DeepL input.
     *
     * The lang of a value may use "_" instead of "-", may contain a region, and
     * may be a two or three letters code, bibliographic or terminologic,
     * whereas DeepL uses mainly two letters codes without region.
     *
     * The code is returned as is when it cannot be converted, so the caller can
     * still check it against self::$langsSupportedInput and distinguish it from
     * a value without lang.
     *
     * @return string An empty string when the value has no lang.
     */
    public static function normalizeLangCode(?string $lang): string
    {
        // The method is called for each value, but the langs are few, and
        // code2letters() loops on the 8120 codes, so cache the results.
        static $cache = [];

        $lang = (string) $lang;
        if (isset($cache[$lang])) {
            return $cache[$lang];
        }

        // Lang codes for values should use "-", but "_" is common, in
        // particular when values are imported from another tool.
        $langCode = mb_strtolower((string) strtok(strtr($lang, '_', '-'), '-'));
        if ($langCode !== '' && !isset(self::$langsSupportedInput[$langCode])) {
            $langCode = \Iso639p3\Iso639p3::code2letters($langCode) ?: $langCode;
        }

        return $cache[$lang] = $langCode;
    }

    /**
     * Get the list of translatable values for each language pair.
     *
     * @return array Associative array with pair as key, and array of arrays as
     * value. Each array contains the source language, the target language and a
     * list of texts. The source language may be null for automatic detection.
     */
    public function filterValuesToTranslate(AbstractResourceEntityRepresentation $resource): array
    {
        /**
         * @var \Omeka\Settings\Settings $settings
         * @var \Common\Stdlib\EasyMeta $easyMeta
         */
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        $propertiesToInclude = $settings->get('translator_properties_include', []);
        $sizeInclude = $settings->get('translator_properties_include_size', '');
        if ($sizeInclude) {
            $propertiesToInclude[] = $sizeInclude;
        }
        if (!$propertiesToInclude) {
            return [];
        }

        $pairs = $this->normalizeLanguagePairs();
        if (!$pairs) {
            return [];
        }

        $easyMeta = $services->get('Common\EasyMeta');

        $defaultLangSource = $settings->get('translator_lang_source_default');

        $isSkipEmptyLang = $defaultLangSource === 'skip'
            || ($defaultLangSource && !isset(self::$langsSupportedInput[$defaultLangSource]));
        if (!$defaultLangSource || $defaultLangSource === 'auto' || $defaultLangSource === 'skip') {
            $defaultLangSource = null;
        }

        $propertiesToExclude = $settings->get('translator_properties_exclude', []);
        $sizeExclude = $settings->get('translator_properties_exclude_size', '');
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

        if (in_array('properties', $propertiesToInclude)) {
            $propertiesToInclude = $easyMeta->propertyTerms();
        }

        $propertiesToInclude = array_combine($propertiesToInclude, $propertiesToInclude);
        $sizeLimitToInclude = array_intersect_key($propertySizes, $propertiesToInclude);
        $propertiesToInclude = array_diff_key($propertiesToInclude, $propertySizes);

        $propertiesToExclude = array_combine($propertiesToExclude, $propertiesToExclude);
        $sizeLimitToExclude = array_intersect_key($propertySizes, $propertiesToExclude);
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

        // Simplify list of values and filters properties.
        $allValues = $resource->values();
        $allValues = array_diff_key($allValues, $propertiesToExclude);
        if ($hasNoSizeLimitToInclude) {
            $allValues = array_intersect_key($allValues, $propertiesToInclude);
            if (!$allValues) {
                return [];
            }
        }

        $textsToTranslate = [];

        // Build a list of explicit source languages from pairs. Null
        // source means automatic detection, so any lang is accepted.
        $pairsLangsSource = [];
        $hasAutoSourcePair = false;
        foreach ($pairs as $pair) {
            if ($pair['source'] === null) {
                $hasAutoSourcePair = true;
            } else {
                $pairsLangsSource[$pair['source']] = $pair['source'];
            }
        }

        /** @var \Omeka\Api\Representation\ValueRepresentation $value */
        foreach ($allValues as $term => $values) foreach ($values['values'] as $value) {
            // Don't translate linked resource, uri without label, numeric data,
            // or invalid lang.
            $val = (string) $value->value();
            $type = (string) $value->type();
            $length = mb_strlen($val);
            $lang = (string) $value->lang();
            $langCode = self::normalizeLangCode($lang);
            // Check exclusion first.
            if (!$val
                || is_numeric($val)
                || $value->valueResource()
                // TODO Manage html and xml via option tag_handling and other options, so process them separately.
                || in_array($type, ['boolean', 'json', 'html', 'xml', 'place'])
                || in_array($type, ['geography', 'geometry'])
                || strpos($type, 'geographic:') === 0
                || strpos($type, 'geometry:') === 0
                || strpos($type, 'numeric:') === 0
                || preg_match('~^\s*(POINT|LINESTRING|POLYGON|MULTIPOINT|MULTILINESTRING|MULTIPOLYGON|GEOMETRYCOLLECTION)\s*[ZM]*\s*\(~i', $val)
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
                    $langSource = $pair['source'] ?: $defaultLangSource;
                    // Accept the pair when source is explicit and matches,
                    // or when the pair has no source (auto-detection).
                    if ($pair['source'] === null
                        || isset($pairsLangsSource[$langSource])
                    ) {
                        $langTarget = $pair['target'];
                        $key = ($langSource ?? '') . '=' . $langTarget;
                        $textsToTranslate[$key]['source'] = $langSource;
                        $textsToTranslate[$key]['target'] = $langTarget;
                        $textsToTranslate[$key]['texts'][] = $val;
                    }
                }
            }
        }

        // Deduplicate texts once after the full loop.
        foreach ($textsToTranslate as &$data) {
            $data['texts'] = array_values(array_unique($data['texts']));
        }
        unset($data);

        return $textsToTranslate;
    }

    /**
     * Normalize language pairs and remove unsupported pairs.
     *
     * @todo Check with the list of languages fetched from endpoint.
     */
    protected function normalizeLanguagePairs(): array
    {
        /**
         * @var \Omeka\Settings\Settings $settings
         * @var \Laminas\Log\Logger $logger
         */
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        $pairs = $settings->get('translator_lang_pairs');
        if (!$pairs) {
            return [];
        }

        $logger = $services->get('Omeka\Logger');

        $result = [];
        $errors = [];
        foreach ($pairs as $singleOrPair) {
            $r = array_values(array_map('trim', array_filter(explode('=', $singleOrPair))));
            if ($r) {
                $langSource = count($r) === 1 ? null : (strtr(mb_strtolower($r[0]), '_', '-') ?: null);
                $langTarget = strtr(mb_strtolower(count($r) === 1 ? $r[0] : $r[1]), '_', '-');
                if ($langTarget) {
                    $hasError = false;
                    if ($langSource && !isset(self::$langsSupportedInput[$langSource])) {
                        $hasError = true;
                        $errors['source'][$langSource] = $langSource;
                    }
                    if (!isset(self::$langsSupportedOutput[$langTarget])) {
                        // Auto-map short codes to default regional
                        // variant (en → en-gb, pt → pt-pt, etc.).
                        if (isset(self::$langsSupportedOutputShort[$langTarget])) {
                            $langTarget = self::$langsSupportedOutputShort[$langTarget][0];
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
            }
        }

        if (!empty($errors['source'])) {
            $logger->err(
                'The following source languages are not supported currently: {list}.', // @translate
                ['list' => implode(', ', $errors['source'])]
            );
        }
        if (!empty($errors['target'])) {
            $logger->err(
                'The following target languages are not supported currently: {list}.', // @translate
                ['list' => implode(', ', $errors['target'])]
            );
        }

        return array_values(array_unique($result, SORT_REGULAR));
    }

    public function filterExistingTranslations(array $strings, ?string $langSource, string $langTarget): array
    {
        /**
         * @var \Omeka\Api\Manager $api
         * @todo Use direct doctrine query? There is no need for api events.
         */
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        /** @var \Translator\Entity\Translation $existingTranslations */
        $existingTranslations = $api->search('translations', [
            'string' => $strings,
            // An empty lang source should be wrapped with single quotes to
            // be searchable.
            'lang_source' => $langSource ?: "''",
            'lang_target' => $langTarget,
        ], ['responseContent' => 'resource'])->getContent();

        // Extract the strings of existing translations.
        $existingStrings = array_map(
            fn (\Translator\Entity\Translation $translation) => $translation->getText()->getString(),
            $existingTranslations
        );

        // Filter strings that have a translation.
        return array_diff($strings, $existingStrings);
    }

    public function translateDeepL(array $texts, ?string $langSource, string $langTarget, array $options = []): array
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

        $deeplApiKey = $settings->get('translator_deepl_api_key');
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

        try {
            return $deeplClient->translateText($texts, $langSource, $langTarget, $options);
        } catch (\DeepL\DeepLException $e) {
            $logger->err(
                'DeepL translation failed: {error}', // @translate
                ['error' => $e->getMessage()]
            );
            return [];
        }
    }

    public function handleMainSettings(Event $event): void
    {
        $this->handleAnySettings($event, 'settings');
        $this->prepareLangsBySite();
        $this->dispatchTranslateResourcesIfRequested();
        $this->dispatchTranslatePagesIfRequested();
    }

    /**
     * Dispatch the TranslateAll job if requested via settings form.
     */
    protected function dispatchTranslateResourcesIfRequested(): void
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        $translateAll = (bool) $settings->get('translator_translate_resources');
        // Always reset the flag.
        $settings->delete('translator_translate_resources');

        if (!$translateAll) {
            return;
        }

        $job = $services->get(\Omeka\Job\Dispatcher::class)->dispatch(
            \Translator\Job\TranslateAll::class,
            ['resource_types' => ['items', 'item_sets', 'media']]
        );

        $this->messageJobDispatched(
            $job,
            'Translating all resources in background (job {link_job}#{job_id}{link_end}, {link_log}logs{link_end}).' // @translate
        );
    }

    /**
     * Dispatch the TranslatePages job if requested via settings form.
     */
    protected function dispatchTranslatePagesIfRequested(): void
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        $translatePages = (bool) $settings->get('translator_translate_pages');
        // Always reset the flag.
        $settings->delete('translator_translate_pages');

        if (!$translatePages) {
            return;
        }

        $job = $services->get(\Omeka\Job\Dispatcher::class)->dispatch(
            \Translator\Job\TranslatePages::class
        );

        $this->messageJobDispatched(
            $job,
            'Translating the copied pages of the site groups in background (job {link_job}#{job_id}{link_end}, {link_log}logs{link_end}).' // @translate
        );
    }

    /**
     * Display a message with the links to a dispatched job and to its logs.
     */
    protected function messageJobDispatched(\Omeka\Entity\Job $job, string $message): void
    {
        $plugins = $this->getServiceLocator()->get('ControllerPluginManager');
        $messenger = $plugins->get('messenger');
        $url = $plugins->get('url');
        $message = new PsrMessage(
            $message,
            [
                'link_job' => sprintf('<a href="%s">', htmlspecialchars($url->fromRoute('admin/id', ['controller' => 'job', 'id' => $job->getId()]))),
                'job_id' => $job->getId(),
                'link_end' => '</a>',
                'link_log' => class_exists('Log\Module', false)
                    ? sprintf('<a href="%1$s">', htmlspecialchars($url->fromRoute('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]])))
                    : sprintf('<a href="%1$s" target="_blank" rel="noopener noreferrer">', htmlspecialchars($url->fromRoute('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()]))),
            ]
        );
        $message->setEscapeHtml(false);
        $messenger->addSuccess($message);
    }

    public function handleSiteSettings(Event $event): void
    {
        $this->handleAnySettings($event, 'site_settings');
        $this->prepareLangsBySite();
    }

    public function handleSaveSitePost(Event $event): void
    {
        $this->prepareLangsBySite();
    }

    /**
     * Store full pairs by site for quick process and to manage fallbacks.
     *
     * The pairs are stored by site, then, as a list, a list by source and a
     * list by target in order to manage different use cases quickly.
     */
    protected function prepareLangsBySite(): void
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

        $pairs = $this->normalizeLanguagePairs();

        $mainLocale = mb_strtolower(strtr(
            $settings->get('locale')
            ?: $services->get('Config')['translator']['locale']
            ?: 'en_US',
            ['_' => '-']
        ));

        $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
        foreach ($siteIds as $siteId) {
            $siteLocale = $siteSettings->get('locale', null, $siteId);
            $siteLocale = $siteLocale ? mb_strtolower(strtr($siteLocale, '_', '-')) : $mainLocale;
            $siteLocaleShort = strtok($siteLocale, '-');

            // Prepare pairs of languages.
            $sitePairs = [];
            foreach ($pairs as $pair) {
                if ($pair['target'] === $siteLocale || strtok($pair['target'], '-') === $siteLocaleShort) {
                    $sitePairs['pairs'][] = $pair;
                    $sitePairs['source'][$pair['source']] = $pair['source'];
                    $sitePairs['target'][$pair['target']] = $pair['target'];
                }
            }
            $siteSettings->set('translator_lang_pairs', array_map('array_values', $sitePairs), $siteId);

            // Prepare automatic fallbacks.
            $fallbacks = array_values(array_unique(
                [-2 => $siteLocale, -1 => $siteLocaleShort]
                + (self::$langsSupportedOutputShort[$siteLocaleShort] ?? [])
            ));
            $siteSettings->set('translator_lang_fallbacks', $fallbacks, $siteId);
        }
    }

    /**
     * When there is a background job, the current site is not set, so it may
     * throw an exception when the site is needed, for example for indexation.
     * So when helper siteSetting() throw an error, the site id of this method
     * can be used to force it. It should not be used early, because in some
     * cases the default site is returned instead of the current site.
     *
     * @todo Is the exception for current site fixed?
     */
    protected function getCurrentOrDefaultSiteId(): ?int
    {
        static $siteId = false;

        if ($siteId === false) {
            $services = $this->getServiceLocator();
            $site = $services->get('ControllerPluginManager')->get('currentSite')()
                ?: $services->get('ViewHelperManager')->get('defaultSite')();
            $siteId = $site ? $site->id() : null;
        }

        return $siteId;
    }

    /**
     * Get the locale of the site.
     *
     * @fixme Remove the exception that occurs with background job and api during update: job seems to set status as site.
     *
     * Adapted:
     * @see \Internationalisation\Module::getLocales()
     * @see \Translator\Module::getLocaleCurrentSite()

     */
     protected function getLocaleCurrentSite(): string
    {
        static $locale;

        if (is_string($locale)) {
            return $locale;
        }

        $services = $this->getServiceLocator();
        $siteSettings = $services->get('Omeka\Settings\Site');

        try {
            $locale = $siteSettings->get('locale');
        } catch (\Throwable $e) {
            $siteId = $this->getCurrentOrDefaultSiteId();
            if ($siteId) {
                $locale = $siteSettings->get('locale', null, $siteId);
            }
        }

        if (!$locale) {
            $settings = $services->get('Omeka\Settings');
            $locale = $settings->get('locale')
                ?: $services->get('Config')['translator']['locale']
                ?: 'en_US';
        }

        $locale = mb_strtolower(strtr($locale, '_', '-'));

        return $locale;
    }

    /**
     * Check if value is translatable according to config, except size.
     *
     * Size is not checked, because it may have been changed between creation
     * of translations. The same for target language, checked against possible
     * targets, not currently used languages.
     *
     * A value that is translatable does not mean that the value was translated.
     *
     * @return bool
     *
     * @todo Return list of languages that may have been used for translation?
     */
    protected function isTranslatableValue(ValueRepresentation $value, string $langTarget): bool
    {
        static $defaultLangSource;
        static $isSkipEmptyLang;
        static $propertiesToExclude;
        static $propertiesToInclude;

        if ($isSkipEmptyLang === null) {
            /**
             * @var \Omeka\Settings\Settings $settings
             * @var \Common\Stdlib\EasyMeta $easyMeta
             */
            $services = $this->getServiceLocator();
            $settings = $services->get('Omeka\Settings');
            $easyMeta = $services->get('Common\EasyMeta');

            $defaultLangSource = $settings->get('translator_lang_source_default');

            $isSkipEmptyLang = $defaultLangSource === 'skip'
                || ($defaultLangSource && ! isset(self::$langsSupportedInput[$defaultLangSource]));
            if (!$defaultLangSource || $defaultLangSource === 'auto' || $defaultLangSource === 'skip') {
                $defaultLangSource = null;
            }

            $propertiesToExclude = $settings->get('translator_properties_exclude', []);
            $sizeExclude = $settings->get('translator_properties_exclude_size', '');
            if ($sizeExclude) {
                $propertiesToExclude[] = $sizeExclude;
            }
            $propertiesToInclude = $settings->get('translator_properties_include', []);
            $sizeInclude = $settings->get('translator_properties_include_size', '');
            if ($sizeInclude) {
                $propertiesToInclude[] = $sizeInclude;
            }

            // Here, the list of specific keys are just used to be removed.
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

            // Here, the size is not managed, unlike during save.
            // In particular, the size may have been changed.
            if (in_array('properties', $propertiesToInclude)) {
                $propertiesToInclude = $easyMeta->propertyTerms();
            }
            $propertiesToInclude = array_combine($propertiesToInclude, $propertiesToInclude);
            $propertiesToInclude = array_diff_key($propertiesToInclude, $propertySizes);

            $propertiesToExclude = array_combine($propertiesToExclude, $propertiesToExclude);
            $propertiesToExclude = array_diff_key($propertiesToExclude, $propertySizes);
            $propertiesToInclude = array_diff_key($propertiesToInclude, $propertiesToExclude);
        }

        // Here, there is always a list of properties to include.
        if (!$propertiesToInclude) {
            return false;
        }

        $term = $value->property()->term();
        // Check for explicit inclusion of the property early.
        // The list of inclusion is filtered of excluded terms above.
        if (!isset($propertiesToInclude[$term])) {
            return false;
        }

        // Check if lang target is a translatable one, included short lang code.
        $langTargetShort = strtok($langTarget, '-');

        // Don't translate linked resource, uri without label, numeric data, or
        // invalid lang.
        $val = (string) $value->value();
        $type = (string) $value->type();
        $lang = (string) $value->lang();
        $langCode = self::normalizeLangCode($lang);
        if (!$val
            || is_numeric($val)
            || $value->valueResource()
            // TODO Manage html and xml via option tag_handling and other options, so process them separately.
            || in_array($type, ['boolean', 'json', 'html', 'xml', 'place'])
            || strpos($type, 'geographic:') === 0
            || strpos($type, 'geometry:') === 0
            || strpos($type, 'numeric:') === 0
            || $langCode === $langTarget
            || $langCode === $langTargetShort
            || (!$langCode && $isSkipEmptyLang)
            || ($langCode && !isset(self::$langsSupportedInput[$langCode]))
            || !(isset(self::$langsSupportedOutput[$langTarget])
                || isset(self::$langsSupportedOutput[$langTargetShort])
                || isset(self::$langsSupportedOutputShort[$langTargetShort])
            )
        ) {
            return false;
        }

        return true;
    }
}
