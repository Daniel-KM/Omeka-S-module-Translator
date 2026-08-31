<?php declare(strict_types=1);

namespace Translator;

use Common\Stdlib\PsrMessage;

/**
 * @var Module $this
 * @var \Laminas\ServiceManager\ServiceLocatorInterface $services
 * @var string $newVersion
 * @var string $oldVersion
 *
 * @var \Omeka\Api\Manager $api
 * @var \Omeka\View\Helper\Url $url
 * @var \Laminas\Log\Logger $logger
 * @var \Omeka\Settings\Settings $settings
 * @var \Laminas\I18n\View\Helper\Translate $translate
 * @var \Doctrine\DBAL\Connection $connection
 * @var \Laminas\Mvc\I18n\Translator $translator
 * @var \Doctrine\ORM\EntityManager $entityManager
 * @var \Omeka\Settings\SiteSettings $siteSettings
 * @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger
 */
$plugins = $services->get('ControllerPluginManager');
$url = $plugins->get('url');
$api = $plugins->get('api');
$logger = $services->get('Omeka\Logger');
$settings = $services->get('Omeka\Settings');
$translate = $plugins->get('translate');
$translator = $services->get('MvcTranslator');
$connection = $services->get('Omeka\Connection');
$messenger = $plugins->get('messenger');
$siteSettings = $services->get('Omeka\Settings\Site');
$entityManager = $services->get('Omeka\EntityManager');

if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.91')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Common', '3.4.91'
    );
    $messenger->addError($message);
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $translate('Missing requirement. Unable to upgrade.')); // @translate
}

if (PHP_VERSION_ID < 80100) {
    $message = new PsrMessage(
        'This version of module {module} requires a version of php ≥ {version}.', // @translate
        ['module' => 'Translator', 'version' => '8.1']
    );
    $messenger->addError($message);
    throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $translate('Missing requirement. Unable to upgrade.')); // @translate
}

if (version_compare((string) $oldVersion, '3.4.4', '<')) {
    $sizeKeys = [
        'properties_max_500',
        'properties_max_1000',
        'properties_max_5000',
        'properties_min_500',
        'properties_min_1000',
        'properties_min_5000',
    ];
    foreach (['include', 'exclude'] as $direction) {
        $key = 'translator_properties_' . $direction;
        $values = (array) $settings->get($key, []);
        $sizes = array_values(array_intersect($values, $sizeKeys));
        $rest = array_values(array_diff($values, $sizeKeys));
        $settings->set($key, $rest);
        $settings->set($key . '_size', $sizes[0] ?? '');
    }
    $messenger->addSuccess(new PsrMessage(
        'Setting "{key}" was split into "{key}" + "{key}_size".', // @translate
        ['key' => 'translator_properties_include']
    ));
}
