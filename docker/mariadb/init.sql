-- Agregátor letáků — inicializace databází pro vývoj
--
-- Vedle hlavní databáze (tu založí image podle MARIADB_DATABASE) je potřeba
-- databáze pro testy. Testy běží na MariaDB, ne na SQLite.
--
-- Skript se spustí jen při prvním startu prázdného volume.
--
-- @author Roman Hlaváček
-- @created 2026-10-02

CREATE DATABASE IF NOT EXISTS agregator_test
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON agregator_test.* TO 'agregator'@'%';
FLUSH PRIVILEGES;
