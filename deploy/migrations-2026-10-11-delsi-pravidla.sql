-- Slevohlídka — delší pravidla produktů katalogu a hlídaných položek (R135): 500 znaků místo 255.
-- Opakovatelný (MODIFY nastaví stejný stav). Pustit PŘED nahráním kódu a PŘED skriptem
-- data-2026-10-11-katalog-rozsireni.sql; stará verze kódu s ním běží dál.
--
-- @author Roman Hlaváček
-- @created 2026-10-11

ALTER TABLE `products`
  MODIFY `keywords` varchar(500) NOT NULL COMMENT 'všechna slova musí být v nabídce, alternativy přes | (R18)',
  MODIFY `variant_keywords` varchar(500) NULL DEFAULT NULL COMMENT 'chybí-li u nabídky „různé druhy“, je shoda „možná“ (R9)',
  MODIFY `exclude_keywords` varchar(500) NULL DEFAULT NULL COMMENT 'kterékoli slovo nabídku vyřadí';

ALTER TABLE `watch_items`
  MODIFY `keywords` varchar(500) NULL DEFAULT NULL COMMENT 'vlastní slova; null u položky z katalogu',
  MODIFY `variant_keywords` varchar(500) NULL DEFAULT NULL COMMENT 'chybí-li u nabídky „různé druhy“, je shoda „možná“ (R9)',
  MODIFY `exclude_keywords` varchar(500) NULL DEFAULT NULL COMMENT 'kterékoli slovo nabídku vyřadí';

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_10_11_100000_widen_keyword_columns', 15 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_11_100000_widen_keyword_columns');
