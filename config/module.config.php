<?php declare(strict_types=1);

namespace Translate;

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
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'translation' => View\Helper\Translation::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\TranslationForm::class => Form\TranslationForm::class,
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
                    'translation' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/translation[/:action]',
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'Translate\Controller\Admin',
                                'controller' => Controller\Admin\IndexController::class,
                                'action' => 'browse',
                            ],
                        ],
                    ],
                    'translation-id' => [
                        'type' => \Laminas\Router\Http\Segment::class,
                        'options' => [
                            'route' => '/translation/:id[/:action]',
                            'constraints' => [
                                // The slug may be an id or a slug. A slug should never be fully numeric.
                                'slug' => '[a-z0-9_-]+',
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'Translate\Controller\Admin',
                                'controller' => Controller\Admin\IndexController::class,
                                'action' => 'show',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'column_defaults' => [
        'admin' => [
            'translations' => [
                ['type' => 'string'],
                ['type' => 'lang'],
                ['type' => 'translation'],
                ['type' => 'locale'],
                ['type' => 'automatic'],
                ['type' => 'reviewed'],
            ],
        ],
    ],
    'browse_defaults' => [
        'admin' => [
            'translations' => [
                'sort_by' => 'string',
                'sort_order' => 'asc',
            ],
        ],
    ],
    'sort_defaults' => [
        'admin' => [
            'translations' => [
                'string' => 'String', // @translate
                'lang' => 'Language', // @translate
                'translation' => 'Translation', // @translate
                'locale' => 'Locale', // @translate
                'automatic' => 'Automatic', // @translate
                'reviewed' => 'Reviewed', // @translate
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
            'translation' => [
                'label' => 'Translations', // @translate
                'route' => 'admin/translation',
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
    ],
];
