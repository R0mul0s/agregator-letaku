-- Slevohlídka — přihlášení přes Google a Facebook (R96): tabulka social_accounts s propojenými
-- účty a users.password jako nepovinné (účet založený přes Google heslo mít nemusí).
-- Opakovatelný (IF NOT EXISTS, MODIFY je idempotentní). Pustit PŘED nahráním kódu; stará verze
-- kódu s ním běží dál (účty bez hesla zakládá až nový kód).
--
-- @author Roman Hlaváček
-- @created 2026-10-06

CREATE TABLE IF NOT EXISTS `social_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `provider` varchar(20) NOT NULL COMMENT 'poskytovatel přihlášení (SocialProvider): google, facebook',
  `provider_user_id` varchar(255) NOT NULL COMMENT 'ID uživatele u poskytovatele (Google sub, Facebook id)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `social_accounts_provider_provider_user_id_unique` (`provider`,`provider_user_id`),
  UNIQUE KEY `social_accounts_user_id_provider_unique` (`user_id`,`provider`),
  CONSTRAINT `social_accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `users`
    MODIFY COLUMN `password` varchar(255) DEFAULT NULL COMMENT 'null = účet bez hesla, přihlášení jen přes propojený účet (R96)';

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_06_100000_create_social_accounts_table', 9 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_06_100000_create_social_accounts_table');
