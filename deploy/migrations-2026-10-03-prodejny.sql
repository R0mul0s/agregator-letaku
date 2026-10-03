-- Slevohlídka — prodejny Kauflandu a v kterých platí akce (R49): tabulky stores a offer_stores,
-- vybrané prodejny u sledovaného obchodu (followed_chains.store_codes).
-- Opakovatelný (IF NOT EXISTS). Pustit PŘED nahráním kódu; stará verze kódu s ním běží dál.
--
-- @author Roman Hlaváček
-- @created 2026-10-03

CREATE TABLE IF NOT EXISTS `stores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chain` varchar(20) NOT NULL COMMENT 'App\\Enums\\Chain',
  `code` varchar(20) NOT NULL COMMENT 'kód prodejny obchodu (Kaufland CZ4400)',
  `name` varchar(150) NOT NULL COMMENT 'název bez názvu obchodu („Trutnov“, „Praha-Vypich“)',
  `city` varchar(100) NOT NULL,
  `offer_keys` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'akce platné v prodejně jako klíč nabídky „id|od|do“ (OfferData::key)' CHECK (json_valid(`offer_keys`)),
  `offer_keys_fetched_at` timestamp NULL DEFAULT NULL COMMENT 'UTC, kdy se seznam akcí stáhl',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stores_chain_code_unique` (`chain`,`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `offer_stores` (
  `offer_id` bigint(20) unsigned NOT NULL,
  `store_code` varchar(20) NOT NULL COMMENT 'stores.code — akce platí jen v těchto prodejnách; bez řádků = všude',
  PRIMARY KEY (`offer_id`,`store_code`),
  KEY `offer_stores_store_code_index` (`store_code`),
  CONSTRAINT `offer_stores_offer_id_foreign` FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `followed_chains`
    ADD COLUMN IF NOT EXISTS `store_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'vybrané prodejny (stores.code); null = všechny' CHECK (json_valid(`store_codes`)) AFTER `include_online_only`;

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_03_100000_create_stores_tables', 3 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_03_100000_create_stores_tables');
