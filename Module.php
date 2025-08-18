<?php declare(strict_types=1);

namespace Translate;

if (!class_exists('Common\TraitModule', false)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\TraitModule;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Omeka\Module\AbstractModule;

class Module extends AbstractModule
{
    use TraitModule;

    public const NAMESPACE = __NAMESPACE__;

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
            ? mb_substr(mb_strtolower($mainLocale), 0, $pos) . '-' . mb_substr(mb_strtoupper($mainLocale), $pos)
            : mb_strtolower($mainLocale);
        $settings->set('translate_lang_source_default', $mainLocale);

        $siteIds = $api->search('sites', [], ['returnScalar' => 'id'])->getContent();
        $pairs = [];
        foreach ($siteIds as $siteId) {
            $siteSettings->setTargetId($siteId);
            $siteLocale = strtr($siteSettings->get('locale'), '_', '-');
            $pos = strpos($siteLocale, '-');
            $siteLocale = $pos
                ? mb_substr(mb_strtolower($siteLocale), 0, $pos) . '-' . mb_substr(mb_strtoupper($siteLocale), $pos)
                : mb_strtolower($siteLocale);
            if ($siteLocale && $siteLocale !== $mainLocale) {
                $pairs[] = [$mainLocale => $siteLocale];
            }
        }
        $settings->set('translate_lang_pairs', $pairs);
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
        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );
    }
}
