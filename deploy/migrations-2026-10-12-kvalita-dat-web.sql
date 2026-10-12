-- Slevohlídka — akce letáku převzaté webem v přehledu kvality dat (R136): sloupec
-- leaflet_stats.covered_offers (Lidl zveřejní akce z PDF letáku později i na webu).
-- Opakovatelný (IF NOT EXISTS). Pustit PŘED nahráním kódu; stará verze kódu s ním běží dál.
--
-- @author Roman Hlaváček
-- @created 2026-10-12

ALTER TABLE `leaflet_stats`
  ADD COLUMN IF NOT EXISTS `covered_offers` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'akce letáku, které nese jiný zdroj obchodu (Lidl: web) a z letáku se neuložily' AFTER `offers_count`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_12_100000_add_covered_offers_to_leaflet_stats', 16 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_12_100000_add_covered_offers_to_leaflet_stats');
