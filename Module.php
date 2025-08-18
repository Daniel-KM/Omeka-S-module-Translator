<?php declare(strict_types=1);

namespace Translate;

if (!class_exists('Common\TraitModule', false)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\TraitModule;
use Laminas\Mvc\MvcEvent;
use Omeka\Module\AbstractModule;

class Module extends AbstractModule
{
    use TraitModule;

    public const NAMESPACE = __NAMESPACE__;

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
}
