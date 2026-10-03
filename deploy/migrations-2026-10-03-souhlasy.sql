-- Slevohlídka — souhlasy uživatele (R51): přijetí podmínek užití, souhlas s obchodními sděleními.
-- Dosavadní účty se označí jako ověřené (ověření e-mailu přibylo s R51).
-- Opakovatelný (IF NOT EXISTS). Pustit PŘED nahráním kódu; stará verze kódu s ním běží dál.
--
-- @author Roman Hlaváček
-- @created 2026-10-03

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `terms_accepted_at` timestamp NULL DEFAULT NULL COMMENT 'UTC, přijetí podmínek užití při registraci' AFTER `digest_sent_at`,
    ADD COLUMN IF NOT EXISTS `terms_version` smallint(5) unsigned DEFAULT NULL COMMENT 'letaky.legal.terms_version v době přijetí' AFTER `terms_accepted_at`,
    ADD COLUMN IF NOT EXISTS `marketing_consent_at` timestamp NULL DEFAULT NULL COMMENT 'UTC, souhlas s obchodními sděleními; null = bez souhlasu' AFTER `terms_version`,
    ADD COLUMN IF NOT EXISTS `marketing_consent_version` smallint(5) unsigned DEFAULT NULL COMMENT 'letaky.legal.marketing_consent_version v době souhlasu' AFTER `marketing_consent_at`,
    ADD COLUMN IF NOT EXISTS `marketing_consent_withdrawn_at` timestamp NULL DEFAULT NULL COMMENT 'UTC, poslední odvolání souhlasu' AFTER `marketing_consent_version`;

UPDATE `users` SET `email_verified_at` = `created_at` WHERE `email_verified_at` IS NULL;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_03_200000_add_consents_to_users_table', 4 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_03_200000_add_consents_to_users_table');
