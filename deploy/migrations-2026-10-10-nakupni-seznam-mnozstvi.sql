-- Slevohlídka — množství položky nákupního seznamu (R133): počet kusů nebo balení, výchozí 1.
-- Opakovatelný (IF NOT EXISTS). Pustit PŘED nahráním kódu; stará verze kódu s ním běží dál.
--
-- @author Roman Hlaváček
-- @created 2026-10-10

ALTER TABLE `shopping_list_items`
  ADD COLUMN IF NOT EXISTS `quantity` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'počet kusů nebo balení' AFTER `chain`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_10_400000_add_quantity_to_shopping_list_items', 14 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_10_400000_add_quantity_to_shopping_list_items');
