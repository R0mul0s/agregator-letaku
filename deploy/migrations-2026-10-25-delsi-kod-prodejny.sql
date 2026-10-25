-- Slevohlídka — delší kód prodejny (R169): prodejny COOP mají kódem část adresy své stránky na coopclub.cz
-- (až 54 znaků), sloupce měly 20 znaků. Opakovatelný (MODIFY na stejnou délku nic nezmění). Pustit PŘED
-- nahráním kódu; stará verze kódu delší sloupec nepozná.
--
-- @author Roman Hlaváček
-- @created 2026-10-25

ALTER TABLE `stores`
  MODIFY `code` varchar(80) NOT NULL COMMENT 'kód prodejny obchodu (Kaufland CZ4400, COOP část adresy stránky prodejny)';

ALTER TABLE `offer_stores`
  MODIFY `store_code` varchar(80) NOT NULL COMMENT 'stores.code — akce platí jen v těchto prodejnách; bez řádků = všude';

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_25_100000_widen_store_codes', 19 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_25_100000_widen_store_codes');
