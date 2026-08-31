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

// The module Mapper embeds the same library "simple-iso-639-3" and its
// autoloader may win, so an older version breaks the languages here.
if ($this->isModuleActive('Mapper') && !$this->isModuleVersionAtLeast('Mapper', '3.4.9')) {
    $message = new \Omeka\Stdlib\Message(
        $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
        'Mapper', '3.4.9'
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

if (version_compare((string) $oldVersion, '3.4.5', '<')) {
    // The DeepL api key is now stored encrypted at rest via Omeka\Cipher.
    // encrypt() is idempotent and skips a value that is already encrypted.
    $apiKey = (string) $settings->get('translator_deepl_api_key', '');
    if ($apiKey !== '') {
        $settings->set('translator_deepl_api_key', $services->get('Omeka\Cipher')->encrypt($apiKey));
    }

    // Without these keys, the pages of the site groups are not translated. An
    // existing list is kept: the user may have set it already.
    if (!$settings->get('translator_pages_include', [])) {
        $settings->set('translator_pages_include', \Translator\Stdlib\PageTexts::KEYS_DEFAULT);
        $messenger->addSuccess(new PsrMessage(
            'The keys of the page blocks to translate were set with the default ones. Check them in the main settings.' // @translate
        ));
    }

    // The state of translations of copied site pages is stored in a new table.
    // The table and the constraints are checked instead of catching the errors
    // of a second run: a real failure must not be hidden, else the module would
    // be marked as upgraded with a table that misses its foreign keys.
    $connection->executeStatement(<<<'SQL'
        CREATE TABLE IF NOT EXISTS `translate_page` (
            `id` INT AUTO_INCREMENT NOT NULL,
            `page_id` INT NOT NULL,
            `source_page_id` INT DEFAULT NULL,
            `lang` VARCHAR(8) NOT NULL,
            `hashes` LONGTEXT NOT NULL COMMENT '(DC2Type:json)',
            `created` DATETIME NOT NULL,
            `modified` DATETIME DEFAULT NULL,
            UNIQUE INDEX `idx_translate_page` (`page_id`),
            INDEX `idx_translate_page_source` (`source_page_id`),
            PRIMARY KEY(`id`)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        SQL);

    $existingConstraints = $connection->executeQuery(<<<'SQL'
        SELECT constraint_name
        FROM information_schema.table_constraints
        WHERE table_schema = DATABASE()
            AND table_name = 'translate_page'
            AND constraint_type = 'FOREIGN KEY'
        SQL)->fetchFirstColumn();
    $existingConstraints = array_flip($existingConstraints);

    $foreignKeys = [
        'FK_translate_page_page' => 'ALTER TABLE `translate_page` ADD CONSTRAINT `FK_translate_page_page` FOREIGN KEY (`page_id`) REFERENCES `site_page` (`id`) ON DELETE CASCADE',
        'FK_translate_page_source' => 'ALTER TABLE `translate_page` ADD CONSTRAINT `FK_translate_page_source` FOREIGN KEY (`source_page_id`) REFERENCES `site_page` (`id`) ON DELETE SET NULL',
    ];
    foreach ($foreignKeys as $name => $sql) {
        if (isset($existingConstraints[$name])) {
            continue;
        }
        try {
            $connection->executeStatement($sql);
        } catch (\Exception $e) {
            $message = new PsrMessage(
                'The foreign key "{name}" of the table "translate_page" could not be created: {message}', // @translate
                ['name' => $name, 'message' => $e->getMessage()]
            );
            $logger->err((string) $message);
            $messenger->addError($message);
        }
    }

    $message = new PsrMessage(
        'It is now possible to translate site pages individually.' // @translate
    );
    $messenger->addSuccess($message);
}
