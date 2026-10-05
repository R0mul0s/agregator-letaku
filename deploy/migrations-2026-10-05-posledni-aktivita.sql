-- Slevohlídka — poslední aktivita uživatele (R84) pro přehled uživatelů admina: users.last_seen_at.
-- Dosavadním účtům se doplní z ještě uložených relací (unix čas → řetězec v UTC jako zapisuje Laravel).
-- Opakovatelný (IF NOT EXISTS, doplní jen prázdné). Pustit PŘED nahráním kódu; stará verze kódu s ním běží dál.
--
-- @author Roman Hlaváček
-- @created 2026-10-05

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `last_seen_at` timestamp NULL DEFAULT NULL COMMENT 'UTC, poslední požadavek přihlášeného uživatele (zapisuje se nejvýš jednou za minutu)' AFTER `notified_at`,
    ADD INDEX IF NOT EXISTS `users_last_seen_at_index` (`last_seen_at`);

UPDATE `users` u
JOIN (SELECT `user_id`, MAX(`last_activity`) AS `last_activity` FROM `sessions` WHERE `user_id` IS NOT NULL GROUP BY `user_id`) s ON s.`user_id` = u.`id`
SET u.`last_seen_at` = DATE_ADD('1970-01-01 00:00:00', INTERVAL s.`last_activity` SECOND)
WHERE u.`last_seen_at` IS NULL;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_05_300000_add_last_seen_at_to_users_table', 8 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_05_300000_add_last_seen_at_to_users_table');
