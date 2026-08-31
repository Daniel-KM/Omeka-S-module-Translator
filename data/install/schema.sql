CREATE TABLE `translate_text` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `lang` VARCHAR(8) DEFAULT NULL,
    `string` LONGTEXT NOT NULL,
    INDEX `idx_text_string` (`string`(190), `lang`),
    INDEX `idx_text_lang` (`lang`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE `translation` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `text_id` INT NOT NULL,
    `lang` VARCHAR(8) NOT NULL,
    `automatic` TINYINT(1) DEFAULT '0' NOT NULL,
    `reviewed` TINYINT(1) DEFAULT '0' NOT NULL,
    `created` DATETIME NOT NULL,
    `modified` DATETIME DEFAULT NULL,
    `translation` LONGTEXT NOT NULL,
    INDEX `IDX_B469456F698D3548` (`text_id`),
    INDEX `idx_translation_lang` (`lang`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

ALTER TABLE `translation` ADD CONSTRAINT `FK_B469456F698D3548` FOREIGN KEY (`text_id`) REFERENCES `translate_text` (`id`) ON DELETE CASCADE;

CREATE TABLE `translate_page` (
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
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

ALTER TABLE `translate_page` ADD CONSTRAINT `FK_translate_page_page` FOREIGN KEY (`page_id`) REFERENCES `site_page` (`id`) ON DELETE CASCADE;
ALTER TABLE `translate_page` ADD CONSTRAINT `FK_translate_page_source` FOREIGN KEY (`source_page_id`) REFERENCES `site_page` (`id`) ON DELETE SET NULL;
