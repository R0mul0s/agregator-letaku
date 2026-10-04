-- Slevohlídka — upozornění v telefonu, web push (R66): tabulka push_subscriptions s odběry
-- prohlížečů a sloupec users.push_sent_at s časem posledního upozornění.
-- Opakovatelný (IF NOT EXISTS). Pustit PŘED nahráním kódu; stará verze kódu s ním běží dál.
--
-- @author Roman Hlaváček
-- @created 2026-10-04

CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `endpoint` varchar(500) NOT NULL COMMENT 'adresa push služby prohlížeče (letaky.push.allowed_hosts)',
  `public_key` varchar(100) NOT NULL COMMENT 'klíč p256dh prohlížeče, base64url',
  `auth_token` varchar(50) NOT NULL COMMENT 'tajemství auth prohlížeče, base64url',
  `device` varchar(100) NOT NULL COMMENT 'čitelný název zařízení z User-Agentu (DeviceName)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `push_subscriptions_endpoint_unique` (`endpoint`),
  KEY `push_subscriptions_user_id_foreign` (`user_id`),
  CONSTRAINT `push_subscriptions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `push_sent_at` timestamp NULL DEFAULT NULL COMMENT 'UTC, poslední zpracované upozornění v telefonu (R66), i když nebylo co poslat' AFTER `digest_sent_at`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_04_200000_create_push_subscriptions_table', 6 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_04_200000_create_push_subscriptions_table');
