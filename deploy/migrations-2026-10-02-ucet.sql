-- Slevohlídka — nastavení účtu: profilový obrázek (R40), předvolby Mých slev (R41).
-- Opakovatelný (IF NOT EXISTS). Pustit PŘED nahráním kódu.
--
-- @author Roman Hlaváček
-- @created 2026-10-02

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `avatar_path` varchar(100) DEFAULT NULL COMMENT 'soubor na disku local (storage/app/private), null = iniciály' AFTER `email`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_02_235000_add_avatar_to_users_table', 2 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_02_235000_add_avatar_to_users_table');

-- Předvolby Mých slev (R41): řazení a minimální sleva
ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `offers_sort` varchar(20) NOT NULL DEFAULT 'unit_price' COMMENT 'App\\Enums\\OffersSort — řazení v Mých slevách' AFTER `is_admin`,
    ADD COLUMN IF NOT EXISTS `min_discount_percent` tinyint(3) unsigned DEFAULT NULL COMMENT 'Moje slevy jen se slevou aspoň tolik %, null = všechny akce' AFTER `offers_sort`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_02_235100_add_offers_preferences_to_users_table', 2 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_02_235100_add_offers_preferences_to_users_table');
