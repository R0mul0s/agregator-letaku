-- Slevohlídka — nákupní seznam (R61): tabulka shopping_list_items s akcemi, které si uživatel
-- dal do seznamu, a časem odškrtnutí.
-- Opakovatelný (IF NOT EXISTS). Pustit PŘED nahráním kódu; stará verze kódu s ním běží dál.
--
-- @author Roman Hlaváček
-- @created 2026-10-04

CREATE TABLE IF NOT EXISTS `shopping_list_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `offer_id` bigint(20) unsigned NOT NULL,
  `checked_at` timestamp NULL DEFAULT NULL COMMENT 'UTC, kdy uživatel položku v obchodě odškrtl; null = ještě koupit',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `shopping_list_items_user_id_offer_id_unique` (`user_id`,`offer_id`),
  KEY `shopping_list_items_offer_id_foreign` (`offer_id`),
  CONSTRAINT `shopping_list_items_offer_id_foreign` FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `shopping_list_items_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_04_100000_create_shopping_list_items_table', 5 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_04_100000_create_shopping_list_items_table');
