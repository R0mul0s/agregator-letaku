-- Slevohlídka — nastavení účtu (R40): profilový obrázek.
-- Opakovatelný (IF NOT EXISTS). Pustit PŘED nahráním kódu.
--
-- @author Roman Hlaváček
-- @created 2026-10-02

ALTER TABLE `users`
    ADD COLUMN IF NOT EXISTS `avatar_path` varchar(100) DEFAULT NULL COMMENT 'soubor na disku local (storage/app/private), null = iniciály' AFTER `email`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_02_235000_add_avatar_to_users_table', 2 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_02_235000_add_avatar_to_users_table');
