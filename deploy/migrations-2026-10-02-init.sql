-- Slevohlídka — výchozí schéma databáze pro první nasazení (R20, R38)
--
-- Na hostingu nejde spustit `php artisan migrate` (není SSH), proto se schéma
-- zakládá tímto skriptem v phpMyAdminu. Odpovídá všem migracím k 2026-10-02
-- a zapisuje je do tabulky `migrations`. Data katalogu (kategorie a produkty)
-- jsou v data-2026-10-02-katalog.sql.
--
-- Vygenerováno z vývojové databáze (mariadb-dump --no-data).
--
-- @author Roman Hlaváček
-- @created 2026-10-02


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `source_id` varchar(400) NOT NULL COMMENT 'ID uzlu ve stromu e-shopu Tesco (zakódovaná cesta)',
  `depth` tinyint(3) unsigned NOT NULL COMMENT '0 = oddělení, 1 = sekce, 2 = regál, 3 = police',
  `position` smallint(5) unsigned NOT NULL COMMENT 'pořadí mezi sourozenci podle Tesca',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_source_id_unique` (`source_id`),
  KEY `categories_parent_id_foreign` (`parent_id`),
  CONSTRAINT `categories_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` varchar(255) NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `followed_chains` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `chain` varchar(20) NOT NULL COMMENT 'App\\Enums\\Chain',
  `store_format` varchar(20) DEFAULT NULL COMMENT 'App\\Enums\\StoreFormat; null = všechny typy prodejen',
  `include_online_only` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'zobrazovat i akce jen z e-shopu (R4)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `followed_chains_user_id_chain_unique` (`user_id`,`chain`),
  CONSTRAINT `followed_chains_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `leaflet_pages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `leaflet_id` bigint(20) unsigned NOT NULL,
  `number` smallint(5) unsigned NOT NULL COMMENT 'číslo stránky od 1',
  `text` text NOT NULL COMMENT 'slova stránky (Lidl keyWords, Penny text vektorové vrstvy)',
  `image_url` varchar(500) DEFAULT NULL COMMENT 'náhled stránky na CDN obchodu (R22)',
  `page_url` varchar(500) DEFAULT NULL COMMENT 'stránka v prohlížeči letáku obchodu',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leaflet_pages_leaflet_id_number_unique` (`leaflet_id`,`number`),
  CONSTRAINT `leaflet_pages_leaflet_id_foreign` FOREIGN KEY (`leaflet_id`) REFERENCES `leaflets` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `leaflets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chain` varchar(20) NOT NULL COMMENT 'App\\Enums\\Chain',
  `kind` varchar(20) NOT NULL COMMENT 'App\\Enums\\LeafletKind',
  `external_id` varchar(100) NOT NULL COMMENT 'ID u obchodu (Tesco 708, Kaufland nabidka-2026-09-30…)',
  `title` varchar(255) DEFAULT NULL,
  `format` varchar(20) DEFAULT NULL COMMENT 'App\\Enums\\StoreFormat; null = všechny prodejny',
  `valid_from` date DEFAULT NULL COMMENT 'místní datum (R7); null = průběžné akce e-shopu',
  `valid_to` date DEFAULT NULL COMMENT 'místní datum (R7), včetně',
  `source_url` varchar(500) DEFAULT NULL,
  `fetched_at` timestamp NOT NULL COMMENT 'UTC, poslední stažení',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `leaflets_chain_kind_external_id_unique` (`chain`,`kind`,`external_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `offer_product` (
  `offer_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `status` varchar(10) NOT NULL COMMENT 'App\\Enums\\MatchStatus',
  `is_manual` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'přiřadil admin ručně — přepočet ho nemění (R30)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`offer_id`,`product_id`),
  KEY `offer_product_product_id_index` (`product_id`),
  CONSTRAINT `offer_product_offer_id_foreign` FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `offer_product_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `offer_product_exclusions` (
  `offer_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`offer_id`,`product_id`),
  KEY `offer_product_exclusions_product_id_index` (`product_id`),
  CONSTRAINT `offer_product_exclusions_offer_id_foreign` FOREIGN KEY (`offer_id`) REFERENCES `offers` (`id`) ON DELETE CASCADE,
  CONSTRAINT `offer_product_exclusions_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `offers` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chain` varchar(20) NOT NULL COMMENT 'App\\Enums\\Chain',
  `leaflet_id` bigint(20) unsigned NOT NULL,
  `scrape_run_id` bigint(20) unsigned NOT NULL COMMENT 'stažení, ve kterém se nabídka naposledy objevila (R16)',
  `withdrawn_at` timestamp NULL DEFAULT NULL COMMENT 'UTC; obchod nabídku stáhl nebo změnil před koncem platnosti (R16)',
  `store_format` varchar(20) DEFAULT NULL COMMENT 'App\\Enums\\StoreFormat; null = všechny prodejny',
  `external_id` varchar(64) NOT NULL COMMENT 'ID položky u obchodu (Kaufland klNr, Tesco id produktu)',
  `name` varchar(255) NOT NULL,
  `brand` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `variant_note` varchar(100) DEFAULT NULL COMMENT '„různé druhy“ — párování „možná“ (R9)',
  `package_text` varchar(100) DEFAULT NULL COMMENT 'balení, jak ho uvádí obchod',
  `quantity` decimal(10,3) DEFAULT NULL COMMENT 'množství v balení v jednotce unit',
  `unit` varchar(5) DEFAULT NULL COMMENT 'App\\Enums\\PackageUnit (g, ml, ks)',
  `price` int(10) unsigned DEFAULT NULL COMMENT 'haléře; cena bez karty, null = obchod ji neuvádí',
  `original_price` int(10) unsigned DEFAULT NULL COMMENT 'haléře; původní cena před slevou',
  `loyalty_price` int(10) unsigned DEFAULT NULL COMMENT 'haléře; cena s kartou nebo aplikací',
  `loyalty_program` varchar(20) DEFAULT NULL COMMENT 'App\\Enums\\LoyaltyProgram',
  `discount_percent` tinyint(3) unsigned DEFAULT NULL COMMENT '% slevy podle obchodu',
  `offer_type` varchar(20) NOT NULL COMMENT 'App\\Enums\\OfferType (R8)',
  `promotion_text` varchar(255) DEFAULT NULL COMMENT 'popis akce od obchodu („3 za cenu 2“)',
  `online_only` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'jen e-shop (R4)',
  `valid_from` date NOT NULL COMMENT 'místní datum (R7)',
  `valid_to` date NOT NULL COMMENT 'místní datum (R7), včetně',
  `source_category` varchar(150) DEFAULT NULL COMMENT 'kategorie u obchodu',
  `image_url` varchar(500) DEFAULT NULL,
  `source_url` varchar(500) DEFAULT NULL,
  `raw` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'původní položka od obchodu' CHECK (json_valid(`raw`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `offers_chain_external_id_valid_from_valid_to_unique` (`chain`,`external_id`,`valid_from`,`valid_to`),
  KEY `offers_leaflet_id_foreign` (`leaflet_id`),
  KEY `offers_scrape_run_id_foreign` (`scrape_run_id`),
  KEY `offers_valid_to_index` (`valid_to`),
  CONSTRAINT `offers_leaflet_id_foreign` FOREIGN KEY (`leaflet_id`) REFERENCES `leaflets` (`id`),
  CONSTRAINT `offers_scrape_run_id_foreign` FOREIGN KEY (`scrape_run_id`) REFERENCES `scrape_runs` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `keywords` varchar(255) NOT NULL COMMENT 'všechna slova musí být v nabídce, alternativy přes | (R18)',
  `variant_keywords` varchar(255) DEFAULT NULL COMMENT 'chybí-li u nabídky „různé druhy“, je shoda „možná“ (R9)',
  `exclude_keywords` varchar(255) DEFAULT NULL COMMENT 'kterékoli slovo nabídku vyřadí',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_name_unique` (`name`),
  KEY `products_category_id_foreign` (`category_id`),
  CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `scrape_runs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chain` varchar(20) NOT NULL COMMENT 'App\\Enums\\Chain',
  `status` varchar(20) NOT NULL COMMENT 'App\\Enums\\ScrapeStatus',
  `offers_count` int(10) unsigned NOT NULL DEFAULT 0,
  `withdrawn_count` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'nabídky, které v tomto stažení chyběly (R16)',
  `error` text DEFAULT NULL,
  `started_at` timestamp NOT NULL COMMENT 'UTC',
  `finished_at` timestamp NULL DEFAULT NULL COMMENT 'UTC',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `scrape_runs_chain_started_at_index` (`chain`,`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `loyalty_programs` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'App\\Enums\\LoyaltyProgram[] — karty, které uživatel má' CHECK (json_valid(`loyalty_programs`)),
  `is_admin` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'smí spravovat katalog produktů (R29)',
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `watch_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `product_id` bigint(20) unsigned DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `keywords` varchar(255) DEFAULT NULL COMMENT 'vlastní slova; null u položky z katalogu',
  `variant_keywords` varchar(255) DEFAULT NULL COMMENT 'chybí-li u nabídky „různé druhy“, je shoda „možná“ (R9)',
  `exclude_keywords` varchar(255) DEFAULT NULL COMMENT 'kterékoli slovo nabídku vyřadí',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `watch_items_user_id_product_id_unique` (`user_id`,`product_id`),
  KEY `watch_items_product_id_foreign` (`product_id`),
  CONSTRAINT `watch_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  CONSTRAINT `watch_items_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;


INSERT INTO `migrations` (`migration`, `batch`) VALUES
('0001_01_01_000000_create_users_table', 1),
('0001_01_01_000001_create_cache_table', 1),
('0001_01_01_000002_create_jobs_table', 1),
('2026_10_02_120000_create_offers_tables', 1),
('2026_10_02_140000_create_watching_tables', 1),
('2026_10_02_180000_create_leaflet_pages_table', 1),
('2026_10_02_200000_create_categories_table', 1),
('2026_10_02_210000_create_catalog_tables', 1),
('2026_10_02_220000_add_product_to_watch_items_table', 1),
('2026_10_02_230000_add_unique_product_to_watch_items_table', 1);
