-- Slevohlídka — centrum upozornění (R74): tabulka notifications (databázové notifikace
-- Laravelu), sloupec users.notified_at s časem, do kterého má uživatel nové akce v centru,
-- a tabulka announcements se zprávami od nás (etapa 11d).
-- Opakovatelný (IF NOT EXISTS). Pustit PŘED nahráním kódu; stará verze kódu s ním běží dál.
--
-- @author Roman Hlaváček
-- @created 2026-10-05

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` uuid NOT NULL,
  `type` varchar(255) NOT NULL COMMENT 'druh upozornění (databaseType notifikace, např. new_offers)',
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL COMMENT 'JSON: hlídané položky a ID akcí v okamžiku upozornění',
  `read_at` timestamp NULL DEFAULT NULL COMMENT 'UTC, kdy si uživatel upozornění přečetl',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `notified_at` timestamp NULL DEFAULT NULL COMMENT 'UTC, do kdy jsou nové akce zapsané v centru upozornění (R74), i když nebylo co zapsat' AFTER `push_sent_at`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_05_100000_create_notifications_table', 7 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_05_100000_create_notifications_table');

-- Zprávy od nás (R74, etapa 11d): přehled zpráv, které admin poslal do centra upozornění
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `title` varchar(120) NOT NULL,
  `body` text NOT NULL,
  `url` varchar(500) DEFAULT NULL COMMENT 'odkaz ze zprávy: cesta v aplikaci nebo https adresa',
  `category` varchar(20) NOT NULL COMMENT 'service = o službě všem, marketing = jen se souhlasem s obchodními sděleními',
  `push` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'poslat i do telefonu (jen zpráva o službě)',
  `recipients` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'kolik uživatelů zprávu dostalo do centra',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `announcements_user_id_foreign` (`user_id`),
  CONSTRAINT `announcements_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_05_200000_create_announcements_table', 7 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_05_200000_create_announcements_table');
