-- Slevohlídka — hledání bez výsledků (R171): tabulka `search_misses` — co lidé hledají ve Všech akcích
-- a v Hlídám a nic to nenašlo, souhrnně bez vazby na uživatele. Opakovatelný (IF NOT EXISTS).
-- Pustit PŘED nahráním kódu; stará verze kódu tabulku nepoužívá.
--
-- @author Roman Hlaváček
-- @created 2026-10-25

CREATE TABLE IF NOT EXISTS `search_misses` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source` varchar(16) NOT NULL COMMENT 'App\Enums\SearchMissSource — Všechny akce, nebo Hlídám',
  `term` varchar(100) NOT NULL COMMENT 'hledaný text po TextNormalizer — klíč',
  `text` varchar(100) NOT NULL COMMENT 'hledaný text k zobrazení, jak ho někdo naposledy napsal',
  `hits` int(10) unsigned NOT NULL DEFAULT 1 COMMENT 'kolikrát se hledalo — jeden člověk nejvýš jednou za den',
  `last_seen_at` timestamp NOT NULL COMMENT 'UTC, poslední započítané hledání',
  `dismissed_at` timestamp NULL DEFAULT NULL COMMENT 'UTC, admin hledání skryl; null = ukázat',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `search_misses_source_term_unique` (`source`,`term`),
  KEY `search_misses_last_seen_at_index` (`last_seen_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_25_300000_create_search_misses_table', 21 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_25_300000_create_search_misses_table');
