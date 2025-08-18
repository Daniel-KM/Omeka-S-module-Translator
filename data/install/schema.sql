CREATE TABLE `text` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `lang` VARCHAR(8) NOT NULL,
    `string` LONGTEXT NOT NULL,
    UNIQUE INDEX `uniq_text_string_lang` (`string`, `lang`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

CREATE TABLE `translate` (
    `id` INT AUTO_INCREMENT NOT NULL,
    `text_id` INT NOT NULL,
    `lang` VARCHAR(8) NOT NULL,
    `automatic` TINYINT(1) DEFAULT '0' NOT NULL,
    `reviewed` TINYINT(1) DEFAULT '0' NOT NULL,
    `created` DATETIME NOT NULL,
    `modified` DATETIME DEFAULT NULL,
    `translation` LONGTEXT NOT NULL,
    INDEX `IDX_4A106377698D3548` (`text_id`),
    INDEX `idx_translate_lang` (`lang`),
    PRIMARY KEY(`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;

ALTER TABLE `translate` ADD CONSTRAINT `FK_4A106377698D3548` FOREIGN KEY (`text_id`) REFERENCES `text` (`id`) ON DELETE CASCADE;
