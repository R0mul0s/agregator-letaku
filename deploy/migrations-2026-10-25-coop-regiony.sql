-- Slevohlídka — regiony COOP jako prodejny (R167): regionální letáky platí jen v prodejnách družstev
-- svého regionu, celostátní všude. Jen data, schéma se nemění (tabulky stores a offer_stores jsou od R49).
-- Opakovatelný (ON DUPLICATE KEY UPDATE). Pustit PŘED nahráním kódu; stará verze kódu obchod coop nezná
-- a řádky jen nechá ležet.
--
-- @author Roman Hlaváček
-- @created 2026-10-25

INSERT INTO `stores` (`chain`, `code`, `name`, `city`, `created_at`, `updated_at`) VALUES
  ('coop', 'coop-sc', 'Střední Čechy', 'Jednota Benešov, Kladno, Rakovník, Nymburk, COOP Hořovice, Příbram, Mnichovo Hradiště', NOW(), NOW()),
  ('coop', 'coop-jc', 'Jižní Čechy', 'Milevsko, Tábor, Vimperk, Volyně, Kaplice, Jindřichův Hradec, Kamenice nad Lipou', NOW(), NOW()),
  ('coop', 'coop-vc', 'Východní Čechy', 'Nová Paka, Pardubice, Dvůr Králové nad Labem', NOW(), NOW()),
  ('coop', 'coop-zc', 'Západní Čechy', 'COOP Plasy, Tachov, Podbořany, Sušice, Plzeň', NOW(), NOW()),
  ('coop', 'coop-ms', 'Morava a Slezsko', 'jen celostátní leták', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `city` = VALUES(`city`), `updated_at` = NOW();

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_25_100000_add_coop_regions', 19 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_25_100000_add_coop_regions');
