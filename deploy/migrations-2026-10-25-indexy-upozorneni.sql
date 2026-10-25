-- Slevohlídka — indexy pro cron upozornění a úklid (R170): users.push_sent_at, digest_sent_at
-- a notified_at (výběr a řazení uživatelů každou hodinu), notifications.created_at (denní úklid
-- centra upozornění). Opakovatelný (IF NOT EXISTS). Pustit kdykoli, stará verze kódu s ním běží dál.
--
-- @author Roman Hlaváček
-- @created 2026-10-25

ALTER TABLE `users`
    ADD INDEX IF NOT EXISTS `users_push_sent_at_index` (`push_sent_at`),
    ADD INDEX IF NOT EXISTS `users_digest_sent_at_index` (`digest_sent_at`),
    ADD INDEX IF NOT EXISTS `users_notified_at_index` (`notified_at`);

ALTER TABLE `notifications`
    ADD INDEX IF NOT EXISTS `notifications_created_at_index` (`created_at`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_25_200000_add_notification_indexes', 20 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_25_200000_add_notification_indexes');
