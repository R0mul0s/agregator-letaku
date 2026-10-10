-- Slevohlídka — hypermarkety Globusu jako prodejny (R131): akce Globusu se liší po hypermarketech
-- (cenová pásma, místní akce). Jen data, schéma se nemění (tabulky stores a offer_stores jsou od R49).
-- Opakovatelný (ON DUPLICATE KEY UPDATE). Pustit PŘED nahráním kódu; stará verze kódu prodejny
-- Globusu jen nabídne k výběru, akce bez vazby na prodejny platí všude.
--
-- @author Roman Hlaváček
-- @created 2026-10-10

INSERT INTO `stores` (`chain`, `code`, `name`, `city`, `created_at`, `updated_at`) VALUES
  ('globus', '4001', 'Brno', 'Brno', NOW(), NOW()),
  ('globus', '4002', 'Praha-Černý Most', 'Praha', NOW(), NOW()),
  ('globus', '4003', 'Praha-Zličín', 'Praha', NOW(), NOW()),
  ('globus', '4004', 'Pardubice', 'Pardubice', NOW(), NOW()),
  ('globus', '4005', 'Praha-Čakovice', 'Praha', NOW(), NOW()),
  ('globus', '4006', 'Liberec', 'Liberec', NOW(), NOW()),
  ('globus', '4007', 'Ostrava', 'Ostrava', NOW(), NOW()),
  ('globus', '4008', 'Olomouc', 'Olomouc', NOW(), NOW()),
  ('globus', '4009', 'České Budějovice', 'České Budějovice', NOW(), NOW()),
  ('globus', '4010', 'Chomutov', 'Chomutov', NOW(), NOW()),
  ('globus', '4011', 'Chotíkov u Plzně', 'Plzeň', NOW(), NOW()),
  ('globus', '4012', 'Opava', 'Opava', NOW(), NOW()),
  ('globus', '4014', 'Jenišov u Karlových Varů', 'Karlovy Vary', NOW(), NOW()),
  ('globus', '4015', 'Trmice u Ústí nad Labem', 'Ústí nad Labem', NOW(), NOW()),
  ('globus', '4019', 'Havířov', 'Havířov', NOW(), NOW()),
  ('globus', '4026', 'Praha-Štěrboholy', 'Praha', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `city` = VALUES(`city`), `updated_at` = NOW();

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_10_300000_add_globus_stores', 13 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_10_300000_add_globus_stores');
