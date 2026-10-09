-- Slevohlídka — „Tohle ne“ a hlášení chyb (R125): tabulka watch_item_offer_exclusions
-- s akcemi skrytými u hlídané položky a tabulka offer_reports s hlášeními chyb v akcích.
-- Opakovatelný (IF NOT EXISTS). Pustit PŘED nahráním kódu; stará verze kódu s ním běží dál.
--
-- @author Roman Hlaváček
-- @created 2026-10-09

CREATE TABLE IF NOT EXISTS `watch_item_offer_exclusions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `watch_item_id` bigint(20) unsigned NOT NULL,
  `offer_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `watch_item_offer_exclusions_watch_item_id_offer_id_unique` (`watch_item_id`,`offer_id`),
  KEY `watch_item_offer_exclusions_offer_id_foreign` (`offer_id`),
  CONSTRAINT `watch_item_offer_exclusions_offer_id_foreign` FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `watch_item_offer_exclusions_watch_item_id_foreign` FOREIGN KEY (`watch_item_id`) REFERENCES `watch_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `offer_reports` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `offer_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `reason` varchar(32) NOT NULL COMMENT 'App\Enums\OfferReportReason',
  `note` text DEFAULT NULL COMMENT 'nepovinný popis od uživatele',
  `resolved_at` timestamp NULL DEFAULT NULL COMMENT 'UTC, kdy admin hlášení vyřešil; null = otevřené',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `offer_reports_offer_id_user_id_unique` (`offer_id`,`user_id`),
  KEY `offer_reports_resolved_at_index` (`resolved_at`),
  KEY `offer_reports_user_id_foreign` (`user_id`),
  CONSTRAINT `offer_reports_offer_id_foreign` FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `offer_reports_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_09_300000_create_watch_item_offer_exclusions_table', 11 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_09_300000_create_watch_item_offer_exclusions_table');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_09_300001_create_offer_reports_table', 11 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_09_300001_create_offer_reports_table');
