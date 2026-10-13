-- Slevohlídka — návrhy, co hlídat (R148): tabulka watch_suggestions s tím, co si uživatel
-- opakovaně dává do nákupního seznamu.
-- Opakovatelný (IF NOT EXISTS). Pustit PŘED nahráním kódu; stará verze kódu s ním běží dál.
--
-- @author Roman Hlaváček
-- @created 2026-10-13

CREATE TABLE IF NOT EXISTS `watch_suggestions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `term` varchar(100) NOT NULL COMMENT 'název po TextNormalizer — klíč návrhu',
  `name` varchar(100) NOT NULL COMMENT 'název k zobrazení, jak ho uživatel naposledy napsal',
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `hits` smallint(5) unsigned NOT NULL DEFAULT 1 COMMENT 'kolikrát se položka do seznamu dostala v okně window_days',
  `last_hit_at` timestamp NOT NULL COMMENT 'UTC, poslední započítaný zásah',
  `dismissed_at` timestamp NULL DEFAULT NULL COMMENT 'UTC, „Ne, díky“ nebo začátek hlídání; null = otevřený návrh',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `watch_suggestions_user_id_term_unique` (`user_id`,`term`),
  KEY `watch_suggestions_product_id_foreign` (`product_id`),
  KEY `watch_suggestions_last_hit_at_index` (`last_hit_at`),
  CONSTRAINT `watch_suggestions_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `watch_suggestions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_13_100000_create_watch_suggestions_table', 17 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_13_100000_create_watch_suggestions_table');
