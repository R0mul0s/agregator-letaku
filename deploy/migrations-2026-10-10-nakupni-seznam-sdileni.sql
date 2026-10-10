-- Slevohlídka — nákupní seznam s vlastními položkami a sdílením odkazem (R130):
-- shopping_list_items.offer_id nepovinné, vlastní název a obchod položky; users.shopping_share_token.
-- Opakovatelný (IF NOT EXISTS). Pustit PŘED nahráním kódu; stará verze kódu s ním běží dál
-- (vlastní položky bez akce ale stará verze neumí zobrazit — nahrát kód hned po skriptu).
--
-- @author Roman Hlaváček
-- @created 2026-10-10

ALTER TABLE `shopping_list_items`
  MODIFY `offer_id` bigint(20) unsigned NULL,
  ADD COLUMN IF NOT EXISTS `custom_name` varchar(100) DEFAULT NULL COMMENT 'vlastní položka bez akce; null = položka je akce' AFTER `offer_id`,
  ADD COLUMN IF NOT EXISTS `chain` varchar(20) DEFAULT NULL COMMENT 'App\\Enums\\Chain — kde vlastní položku koupit; null = kdekoli' AFTER `custom_name`;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `shopping_share_token` varchar(64) DEFAULT NULL COMMENT 'veřejný odkaz na nákupní seznam; nový token zneplatní starý odkaz',
  ADD UNIQUE INDEX IF NOT EXISTS `users_shopping_share_token_unique` (`shopping_share_token`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_10_200000_add_custom_items_and_sharing_to_shopping_list', 12 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_10_200000_add_custom_items_and_sharing_to_shopping_list');
