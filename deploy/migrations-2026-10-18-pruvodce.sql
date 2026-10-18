-- Slevohlídka — průvodce prvním nastavením (R153): users.onboarded_at. Dosavadní účty průvodce
-- nedostanou, vyplní se jim čas registrace — jen při prvním spuštění (před zápisem do migrations),
-- opakované spuštění po nasazení by jinak označilo i nové účty.
-- Opakovatelný (IF NOT EXISTS). Pustit PŘED nahráním kódu; stará verze kódu s ním běží dál.
--
-- @author Roman Hlaváček
-- @created 2026-10-18

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `onboarded_at` timestamp NULL DEFAULT NULL COMMENT 'UTC, dokončení průvodce nebo zavření karty Dokončete nastavení' AFTER `last_seen_at`;

UPDATE `users` SET `onboarded_at` = `created_at`
WHERE `onboarded_at` IS NULL
  AND NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_18_100000_add_onboarded_at_to_users');

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_18_100000_add_onboarded_at_to_users', 18 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_18_100000_add_onboarded_at_to_users');
