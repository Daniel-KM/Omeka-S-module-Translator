<?php declare(strict_types=1);

namespace Translator;

return [
    'api_adapters' => [
        'invokables' => [
            'translations' => Api\Adapter\TranslationAdapter::class,
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'translation' => Service\ViewHelper\TranslationFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
        ],
    ],
    // Because translator is used by module Laminas, Internationalisation,
    // merge them below.
    'translator' => [
        // Library Laminas I18n.
        'translation_file_patterns' => [
            [
                'type' => \Laminas\I18n\Translator\Loader\Gettext::class,
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
        // Module Translator.
        'config' => [
            'translator_deepl_api_key' => '',
        ],
        'settings' => [
            'translator_lang_pairs' => [],
            'translator_lang_source_default' => '',
            'translator_properties_include' => [],
            'translator_properties_exclude' => [],
        ],
        'site_settings' => [
            // Hidden site settings, adapted to site locale.
            'translator_lang_pairs' => [],
            'translator_lang_fallbacks' => [],
        ],
    ],
];
