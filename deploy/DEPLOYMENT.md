<!--
  Slevohlídka — nasazení na webhosting
  @author Roman Hlaváček
  @created 2026-10-02
-->

# Nasazení na webhosting

Postup pro shared hosting **Websupport** a subdoménu `slevohlidka.rhsoft.cz` (R20, R38).
Vychází z nasazení projektu Počasí na stejném účtu.

> **Na hostingu nejdou spouštět příkazy** — není tam SSH ani composer. Nic
> z `php artisan …` se na produkci nepouští: schéma a data katalogu se zakládají
> SQL skripty v phpMyAdminu, `vendor/` a assety se sestaví lokálně, stahování akcí
> spouští cron WebAdminu voláním URL.

## Obsah `deploy/`
| Soubor | K čemu |
|---|---|
| `build-upload.ps1` | sestaví nahrávací balíček do `deploy/upload/` |
| `.env.production.example` | šablona `.env` pro hosting |
| `migrations-*.sql` | změny schématu pro produkci (verzované) |
| `data-*.sql` | data pro produkci (verzované) — kategorie a produkty katalogu |
| `root-htaccess-fallback` | nouzové řešení, když nejde nasměrovat document root do `public/` |

## Předpoklady na hostingu
- **PHP 8.4** s `pdo_mysql`, `mbstring`, `intl`, `dom` (vektorová vrstva letáku Penny), `openssl`
- **MariaDB 11.4**
- **Document root** subdomény nasměrovaný do `public/` (WebAdmin → Web → Služby → Upravit)
- **Odchozí HTTPS** k obchodům (prodejny.kaufland.cz, xapi.tesco.com, api.prod.retail.tesco.com,
  www.lidl.cz, endpoints.leaflets.schwarz, www.penny.cz, files.rewe.co.at, www.albert.cz,
  letaky.albert.cz) — Websupport ho povoluje
- `mod_rewrite` a `mod_headers` — na HTTPS přesměrovává a bezpečnostní hlavičky nastavuje
  `public/.htaccess` (TLS končí na proxy hostingu, schéma je v `X-Forwarded-Proto`)

---

## 1) Sestav balíček

V rootu repa, s běžícím `docker compose`:

```powershell
powershell -ExecutionPolicy Bypass -File deploy\build-upload.ps1
```

Vznikne **`deploy/upload/`** (~50 MB) — jen to, co aplikace potřebuje za běhu (`app`,
`bootstrap`, `config`, `database`, `lang`, `public` se sestavenými assety,
`resources/views`, `routes`, `vendor` bez dev balíčků). Ze `storage/` jde jen
prázdná kostra adresářů — lokální logy se na hosting nedostanou.

Skript balí jen **commitnutý** stav — s necommitnutými změnami skončí chybou
(pro zkoušku přepínač `-AllowDirty`). Hash commitu zapíše do `public/version.txt`.

> Skript musí mít kódování **UTF-8 s BOM** — Windows PowerShell 5.1 čte soubor bez BOM
> jako ANSI a česká diakritika mu rozbije uvozovky.

## 2) Připrav databázi (jen první nasazení)

Ve WebAdminu založ databázi **MariaDB 11.4** a v phpMyAdminu spusť v tomto pořadí:

1. `deploy/migrations-2026-10-02-init.sql` — založí všechny tabulky a zapíše migrace
   do tabulky `migrations`
2. `deploy/data-2026-10-02-katalog.sql` — strom kategorií e-shopu Tesco (1 728) a 164 produktů
   katalogu; `REPLACE`, opakovatelný

Akce zatím v databázi nejsou — stáhne je cron (krok 6). Přiřazení akcí k produktům
katalogu se dopočítá při každém stažení samo.

## 3) Nahraj soubory

Obsah `deploy/upload/` nahraj přes FTP do kořene aplikace (např. `/rhsoft.cz/sub/slevohlidka/`).
Document root má mířit do `.../public`.

> **Nejde změnit docroot?** Nahraj do kořene aplikace `deploy/root-htaccess-fallback`
> přejmenovaný na `.htaccess` (stejně běží Počasí). Chrání `.env`, `vendor`, `storage`
> atd. i bez `mod_rewrite`.

## 4) Vytvoř `.env`

Zkopíruj `deploy/.env.production.example` na hosting jako **`.env`** (bez BOM)
a vyplň místa `<…>`:

- **`APP_KEY`** — `docker compose exec app php artisan key:generate --show`, vlož i s prefixem `base64:`
- `DB_*` — z detailu databáze ve WebAdminu
- `MAIL_*` — schránka založená ve WebAdminu (odkazy na obnovu hesla)
- `TESCO_API_KEY` — veřejný klíč e-shopu Tesco, stejný jako lokálně (`mangoApiKey`, viz ZDROJE_DAT.md)
- `LETAKY_CRON_TOKEN` — náhodný řetězec: `docker compose exec app php -r "echo bin2hex(random_bytes(24));"`

> **Ladění bez SSH:** chyby jsou v `storage/logs/laravel-RRRR-MM-DD.log` —
> stáhni ho přes FTP. `APP_DEBUG=true` na produkci nezapínej ani dočasně.

## 5) Účet a správce katalogu

1. Na `https://slevohlidka.rhsoft.cz/register` si založ účet.
2. Správu katalogu (`/katalog`, R29) mu dej v phpMyAdminu — `letaky:admin` na hostingu nejde:
   ```sql
   UPDATE `users` SET `is_admin` = 1 WHERE `email` = '<tvůj e-mail>';
   ```

## 6) Cron: stahování akcí

WebAdmin → Cron → *Vytvořit CRON úkol*, **každý obchod zvlášť** (všechny najednou trvají
~1,5 minuty a nemusí se vejít do limitu požadavku, O8). Typ **Návštěva na URL adresy (wget)**,
pole *Opakovat* je zápis cronu (`minuta hodina den měsíc den_v_týdnu`). URL vždy s `https://`
(HTTP by se přesměrovalo). Dvakrát denně, s odstupem 10 minut:

| Poznámka | Opakovat | URL |
|---|---|---|
| Slevohlídka – Kaufland | `0 5,13 * * *` | `https://slevohlidka.rhsoft.cz/cron/import-offers?chain=kaufland&token=<LETAKY_CRON_TOKEN>` |
| Slevohlídka – Tesco | `10 5,13 * * *` | `…/cron/import-offers?chain=tesco&token=…` (~45 s, nejdelší) |
| Slevohlídka – Lidl | `20 5,13 * * *` | `…/cron/import-offers?chain=lidl&token=…` (~30 s) |
| Slevohlídka – Penny | `30 5,13 * * *` | `…/cron/import-offers?chain=penny&token=…` (~25 s) |
| Slevohlídka – Albert | `40 5,13 * * *` | `…/cron/import-offers?chain=albert&token=…` |
| Slevohlídka – kategorie | `0 4 1 * *` | `…/cron/import-categories?token=…` — strom kategorií (stačí občas) |
| Slevohlídka – souhrn | `30 6 * * *` | `…/cron/send-digests?token=…` — e-mailové souhrny nových akcí (R42), po ranním stažení |

Hned po nasazení zavolej URL stažení ručně v prohlížeči (kategorie první), ať se nečeká
na ranní běh. *Posílat výsledky e-mailem* stačí zapnout na první dny, pak hlídá `/health/imports`.

Odpověď je prostý text, např. `Tesco — uloženo nabídek: 5139` (200), při chybě
`Tesco — chyba: …` (500); každé stažení je i v tabulce `scrape_runs`. Špatný nebo
chybějící token vrací **404**. Akce, které obchod mezi dvěma staženími stáhl, se označí
a z výpisů zmizí (R16).

**Limit požadavku (O8):** cron URL si prodlouží `max_execution_time` na 180 s
(`letaky.cron.time_limit_seconds`); timeout proxy hostingu to ale přebít může. Když Tesco
v `scrape_runs` končí bez dokončení nebo cron hlásí timeout, ověř limit u Websupportu.
Záložní řešení: cron typu **Spuštění PHP 8.4 souboru** běží v CLI bez limitu požadavku
i proxy — potřeboval by malý PHP skript, který stažení spustí (zatím neexistuje).

## 7) Ověř

```bash
curl -I  "http://slevohlidka.rhsoft.cz/login"
curl -I  "https://slevohlidka.rhsoft.cz/login"
curl -s  "https://slevohlidka.rhsoft.cz/version.txt?v=$(date +%s)"
curl -s  "https://slevohlidka.rhsoft.cz/health/imports"
curl -si "https://slevohlidka.rhsoft.cz/cron/import-offers?chain=kaufland&token=spatny" | head -1
```
1. HTTP vrací **301** na `https://…`.
2. HTTPS odpověď obsahuje `Strict-Transport-Security` a `Content-Security-Policy`
   (obrázky z CDN obchodů povoluje `img-src https:`).
3. `version.txt` vrací nasazený commit (bez unikátního `?v=` může proxy vrátit starou verzi).
4. `/health/imports` po prvních stáženích vrací **200** a u každého obchodu `OK`.
5. Cron URL se špatným tokenem vrací **404**.
6. Asset z `/build/assets/` má `Cache-Control: … immutable`.
7. Soubory pro roboty (R45) — `APP_ENV=production`, jinak `robots.txt` zakáže celý web:
   ```bash
   curl -s "https://slevohlidka.rhsoft.cz/robots.txt"     # Allow: /, Disallow soukromých cest, Sitemap: https://…
   curl -s "https://slevohlidka.rhsoft.cz/sitemap.xml"    # adresy s https:// (jinak nefunguje trustProxies)
   curl -s "https://slevohlidka.rhsoft.cz/llms.txt"
   curl -s "https://slevohlidka.rhsoft.cz/" | grep -E 'canonical|og:image'   # https://, ne http://
   ```
8. Náhled odkazu: sdílet adresu v chatu, nebo ověřit v [opengraph.xyz](https://www.opengraph.xyz).

## Vyhledávače (jednou po prvním nasazení)

1. [Google Search Console](https://search.google.com/search-console): přidat vlastnost
   `https://slevohlidka.rhsoft.cz/` (ověření DNS záznamem TXT ve WebAdminu), odeslat
   `https://slevohlidka.rhsoft.cz/sitemap.xml`.
2. [Bing Webmaster Tools](https://www.bing.com/webmasters): import ze Search Console.
3. Kontrola strukturovaných dat: [Rich Results Test](https://search.google.com/test/rich-results)
   na úvodní stránku (Organization, WebSite).

## Monitoring: hlídání stahování

URL **`https://slevohlidka.rhsoft.cz/health/imports`** (veřejná, bez tokenu) vrací **200**,
když má každý obchod úspěšné stažení za posledních 26 hodin (`letaky.health.max_import_age_hours`),
jinak **503**. Na každém řádku jeden obchod, např. `Tesco — VÝPADEK: poslední úspěšné stažení 30. 9. 12:00`.

V [UptimeRobot](https://uptimerobot.com) (zdarma, stejně jako Počasí): *Add New Monitor* → HTTP(s),
URL `/health/imports`, interval 1 hodina, upozornění e-mailem. Ozve se při výpadku cronu,
změně odpovědi obchodu (`SourceResponseChanged`) i neplatném klíči Tesca.

---

## Aktualizace už nasazené verze

1. **Záloha databáze**, pokud jdou na produkci SQL skripty — viz *Záloha databáze*
2. Spusť nové skripty `deploy/migrations-*.sql` z tabulky níž (v pořadí, **před** nahráním kódu)
3. `build-upload.ps1` a nahraj obsah `deploy/upload/` přes FTP (`.env` na hostingu nepřepisuj —
   v balíčku není). Když se nezměnil `composer.lock`, `vendor/` nahrávat nemusíš —
   **`bootstrap/cache/packages.php` ale ano**: Laravel ho na hostingu přegeneruje jen tehdy,
   když chybí
4. Soubory, které z repa zmizely, FTP samo nesmaže — smaž je ručně
   (`git diff --name-status --diff-filter=D <nasazený commit> HEAD`)
5. Nové proměnné v `deploy/.env.production.example` doplň do `.env` na hostingu
6. Ověř `version.txt?v=<cokoli>` a zapiš verzi do tabulky *Nasazené verze*

### Aktualizace z `c5d45d7` (2026-10-02, druhé nasazení)

Účet s avatarem, předvolby Mých slev, e-mailový souhrn, nové Hlídám, stránkování, úvodní
stránka, SEO a limity požadavků (R39–R45). `composer.lock` se nezměnil.

1. **Záloha databáze** (phpMyAdmin → Exportovat, viz *Záloha databáze*).
2. **SQL:** v phpMyAdminu spusť `deploy/migrations-2026-10-02-ucet.sql` — přidá sloupce do
   `users` (avatar, předvolby, souhrn). Opakovatelný. Stará verze kódu s ním běží dál.
3. **`.env` na hostingu** — nové proměnné nejsou, zkontroluj ale:
   - `APP_ENV=production` — jinak `robots.txt` zakáže indexaci celého webu,
   - `APP_DEBUG=false`,
   - `APP_URL=https://slevohlidka.rhsoft.cz`,
   - `MAIL_*` vyplněné — posílá se obnova hesla a souhrn akcí.
4. **Smaž na hostingu `public/robots.txt`** — `robots.txt` teď generuje aplikace a statický
   soubor by ho přebil.
5. **Nahraj `deploy/upload/` přes FTP** bez složky `vendor/` (nezměnila se), ale
   **s `bootstrap/cache/packages.php`** a **s podsložkou `vendor/composer/`** (pár set kB):
   optimalizovaný autoloader v ní má seznam tříd aplikace a nové třídy by jinak dohledával
   podle jmenného prostoru (funguje, ale pomaleji). Složku `public/build/` na hostingu nejdřív
   smaž — jinak tam zůstanou staré assety (neškodí, jen zabírají místo).
6. **Cron** ve WebAdminu přidej: `30 6 * * *` →
   `https://slevohlidka.rhsoft.cz/cron/send-digests?token=<LETAKY_CRON_TOKEN>` (souhrny e-mailem).
7. **Ověř** (kroky 1–8 v *7) Ověř*), navíc:
   - `version.txt?v=…` vrací commit balíčku (vypíše ho `build-upload.ps1` na konci),
   - úvodní stránka bez přihlášení, `/akce` bez přihlášení,
   - v účtu nahrání profilového obrázku (zapisuje do `storage/app/private/avatars`),
   - zapomenuté heslo → e-mail dorazí s českým předmětem,
   - `/cron/send-digests?token=…` vrací `Souhrny — odesláno: N`.
8. **Search Console** a odeslání sitemap (*Vyhledávače*).
9. Zapiš verzi do *Nasazené verze* a datum ke skriptu v *Historii SQL skriptů*.

**Každá nová migrace potřebuje SQL skript** `deploy/migrations-<datum>-<popis>.sql`
(opakovatelný: `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`) včetně zápisu do
tabulky `migrations` — ve stejném commitu jako migrace. Nové produkty katalogu jdou na
produkci přidat ve správě katalogu, nebo skriptem `deploy/data-<datum>-katalog.sql`
(`mariadb-dump --no-create-info --replace … products`).

## Záloha databáze

Akce jdou kdykoli stáhnout znovu, nenahraditelné jsou **účty, nastavení a hlídané položky**
a ruční opravy katalogu. Před každým SQL skriptem a jinak aspoň jednou měsíčně:

1. phpMyAdmin → databáze → *Exportovat* → metoda *Vlastní*, formát SQL,
   komprese **gzip**, zaškrtnout *Přidat příkaz DROP TABLE*
2. Tabulky stačí `users`, `followed_chains`, `watch_items`, `products`, `categories`,
   `offer_product_exclusions`, `migrations` — akce a jejich přiřazení stáhne a dopočítá cron
3. Soubor ulož mimo hosting jako `slevohlidka-RRRR-MM-DD.sql.gz`

## Historie SQL skriptů

| Skript | Obsah | Nasazeno |
|---|---|---|
| `migrations-2026-10-02-init.sql` | výchozí schéma — všechny tabulky k 2026-10-02 a záznamy v `migrations` | 2026-10-02 |
| `data-2026-10-02-katalog.sql` | strom kategorií e-shopu Tesco (1 728) a 164 produktů katalogu (R28, R37), `REPLACE` — opakovatelný; až po init | 2026-10-02 |
| `migrations-2026-10-02-ucet.sql` | nastavení účtu: `users.avatar_path` (R40), `users.offers_sort` a `min_discount_percent` (R41), `users.digest_frequency` a `digest_sent_at` (R42); opakovatelný, pustit **před** nahráním kódu | |

## Nasazené verze

Co běží na produkci — pro `git log <commit>..HEAD` při dalším nasazení
(nové SQL skripty, změněné složky, nové proměnné v `.env`).

| Datum | Commit | Co |
|---|---|---|
| 2026-10-02 | `c5d45d7` + `config/inertia.php` z `a17fbae` | první nasazení: balíček, schéma a katalog, crony stahování |
