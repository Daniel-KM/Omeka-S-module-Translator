CREATE TABLE `translate` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `lang_source` VARCHAR(8) NOT NULL,
    `lang_target` VARCHAR(8) NOT NULL,
    `automatic` TINYINT(1) DEFAULT '0' NOT NULL,
    `reviewed` TINYINT(1) DEFAULT '0' NOT NULL,
    `created` DATETIME NOT NULL,
    `modified` DATETIME DEFAULT NULL,
    `string` LONGTEXT NOT NULL,
    `translation` LONGTEXT NOT NULL,
    INDEX `idx_translate_langtarget_langsource` (`lang_target`, `lang_source`),
    INDEX `idx_translate_string_langtarget_langsource` (`string`(190), `lang_target`, `lang_source`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
