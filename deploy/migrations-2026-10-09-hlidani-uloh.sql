-- Slevohlídka — hlídání úloh cronu (R115): tabulka task_heartbeats s posledním úspěšným
-- a neúspěšným během kanálů upozornění, denního úklidu a kategorií pro /health/tasks.
-- Opakovatelný (IF NOT EXISTS). Pustit PŘED nahráním kódu; stará verze kódu s ním běží dál.
--
-- @author Roman Hlaváček
-- @created 2026-10-09

CREATE TABLE IF NOT EXISTS `task_heartbeats` (
  `task` varchar(40) NOT NULL COMMENT 'App\\Enums\\CronTask',
  `succeeded_at` timestamp NULL DEFAULT NULL COMMENT 'UTC, poslední úspěšný běh',
  `failed_at` timestamp NULL DEFAULT NULL COMMENT 'UTC, poslední běh s chybou',
  PRIMARY KEY (`task`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_09_200000_create_task_heartbeats_table', 10 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_09_200000_create_task_heartbeats_table');
