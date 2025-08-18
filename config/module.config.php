<?php declare(strict_types=1);

namespace Translate;

return [
    'api_adapters' => [
        'invokables' => [
            'translates' => Api\Adapter\TranslateAdapter::class,
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
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'factories' => [
            'translating' => Service\ViewHelper\TranslatingFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
            Form\TranslateForm::class => Form\TranslateForm::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            Controller\Admin\IndexController::class => Controller\Admin\IndexController::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'translate' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/translate',
                            'defaults' => [
                                '__NAMESPACE__' => 'Translate\Controller\Admin',
                                'controller' => Controller\Admin\IndexController::class,
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                            'id' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:language[/:action]',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        // The language tag follows the BCP47 specification
                                        // according to the list of languages supported by Omeka.
                                        // The recommandation allows 7 subtags of 1 to 8 characters
                                        // separated with a "-". Each subtag should be listed in
                                        // the recommandation. The case should follow the
                                        // recommandation.
                                        // The separator should be a "-", but laminas uses "_".
                                        /** @see https://en.wikipedia.org/wiki/IETF_language_tag */
                                        // 'locale' => '[a-zA-Z]{1,8}((-|_)[a-zA-Z0-9]{1,8}){0,6}',
                                        // This is a locale for pages, not resources, where another pattern is used.
                                        // See application/asset/js/global.js.
                                        'language' => '[a-zA-Z]{2,3}((-|_)[a-zA-Z0-9]{2,4})?',
                                    ],
                                    'defaults' => [
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ]
                    ],
                ],
            ],
        ],
    ],
    'column_types' => [
        'invokables' => [
            'lang_source' => ColumnType\LangSource::class,
            'lang_target' => ColumnType\LangTarget::class,
            'automatic' => ColumnType\Automatic::class,
            'reviewed' => ColumnType\Reviewed::class,
            'string' => ColumnType\StringSource::class,
            'translation' => ColumnType\Translation::class,
        ],
    ],
    'column_defaults' => [
        'admin' => [
            'translates' => [
                // ['type' => 'string'],
                ['type' => 'lang_source'],
                ['type' => 'lang_target'],
                ['type' => 'automatic'],
                ['type' => 'reviewed'],
                ['type' => 'translation'],
                ['type' => 'created'],
                ['type' => 'modified'],
            ],
        ],
    ],
    'browse_defaults' => [
        'admin' => [
            'translates' => [
                'sort_by' => 'string',
                'sort_order' => 'asc',
            ],
        ],
    ],
    'sort_defaults' => [
        'admin' => [
            'translates' => [
                'lang_source' => 'Language source', // @translate
                'lang_target' => 'Language target', // @translate
                'automatic' => 'Automatic', // @translate
                'reviewed' => 'Reviewed', // @translate
                'string' => 'String', // @translate
                'translation' => 'Translation', // @translate
                'created' => 'Created', // @translate
                'modified' => 'Modified', // @translate
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            'translate' => [
                'label' => 'Translate', // @translate
                'route' => 'admin/translate',
                'resource' => Controller\Admin\IndexController::class,
                'privilege' => 'browse',
                'class' => 'o-icon- fa-language',
            ],
        ],
    ],
    'translate' => [
        'config' => [
            'translate_deepl_api_key' => '',
        ],
        'settings' => [
            'translate_lang_pairs' => [],
            'translate_lang_source_default' => '',
            'translate_properties_include' => [],
            'translate_properties_exclude' => [],
        ],
        'site_settings' => [
            // Hidden site settings, adapted to site locale.
            'translator_lang_pairs' => [],
        ],
    ],
];
