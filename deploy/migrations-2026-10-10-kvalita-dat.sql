-- Slevohlídka — přehled kvality dat (R129): tabulka leaflet_stats s počtem akcí letáku za každé
-- stažení a u letáků z PDF/SVG s počtem nalezených a ověřených cen.
-- Opakovatelný (IF NOT EXISTS). Pustit PŘED nahráním kódu; stará verze kódu s ním běží dál.
--
-- @author Roman Hlaváček
-- @created 2026-10-10

CREATE TABLE IF NOT EXISTS `leaflet_stats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scrape_run_id` bigint(20) unsigned NOT NULL,
  `leaflet_id` bigint(20) unsigned NOT NULL,
  `chain` varchar(20) NOT NULL COMMENT 'App\\Enums\\Chain',
  `offers_count` int(10) unsigned NOT NULL COMMENT 'akce letáku uložené tímto stažením',
  `tile_candidates` int(10) unsigned DEFAULT NULL COMMENT 'ceny, ke kterým parser PDF/SVG hledal dlaždici; null = leták z API',
  `tiles_verified` int(10) unsigned DEFAULT NULL COMMENT 'ceny ověřené jako akce',
  `created_at` timestamp NOT NULL COMMENT 'UTC, konec stažení',
  PRIMARY KEY (`id`),
  KEY `leaflet_stats_scrape_run_id_foreign` (`scrape_run_id`),
  KEY `leaflet_stats_leaflet_id_created_at_index` (`leaflet_id`,`created_at`),
  KEY `leaflet_stats_chain_created_at_index` (`chain`,`created_at`),
  KEY `leaflet_stats_created_at_index` (`created_at`),
  CONSTRAINT `leaflet_stats_leaflet_id_foreign` FOREIGN KEY (`leaflet_id`) REFERENCES `leaflets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `leaflet_stats_scrape_run_id_foreign` FOREIGN KEY (`scrape_run_id`) REFERENCES `scrape_runs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_10_100000_create_leaflet_stats_table', 12 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_10_100000_create_leaflet_stats_table');
