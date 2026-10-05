-- Slevohlídka — centrum upozornění (R74): tabulka notifications (databázové notifikace
-- Laravelu) a sloupec users.notified_at s časem, do kterého má uživatel nové akce v centru.
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
