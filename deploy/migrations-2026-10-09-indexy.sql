-- Slevohlídka — indexy pro dotazy, které s historií akcí rostou (R113): offers.valid_from
-- (dnes začínající akce), offers.created_at (filtr Nové, hranice nových akcí), offers.withdrawn_at
-- (akce stažené obchodem) a scrape_runs (status, finished_at) (poslední stažení v patičce).
-- Opakovatelný (IF NOT EXISTS). Pustit kdykoli, stará verze kódu s ním běží dál.
--
-- @author Roman Hlaváček
-- @created 2026-10-09

ALTER TABLE `offers`
    ADD INDEX IF NOT EXISTS `offers_valid_from_index` (`valid_from`),
    ADD INDEX IF NOT EXISTS `offers_created_at_index` (`created_at`),
    ADD INDEX IF NOT EXISTS `offers_withdrawn_at_index` (`withdrawn_at`);

ALTER TABLE `scrape_runs`
    ADD INDEX IF NOT EXISTS `scrape_runs_status_finished_at_index` (`status`, `finished_at`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_09_100000_add_listing_indexes', 10 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_09_100000_add_listing_indexes');
