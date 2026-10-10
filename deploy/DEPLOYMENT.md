<!--
  Slevohlídka — nasazení na webhosting
  @author Roman Hlaváček
  @created 2026-10-02
-->

# Nasazení na webhosting

Postup pro shared hosting **Websupport** a doménu `slevohlidka.cz` (R20, R38). Do přestěhování
(R93, viz *Přestěhování na slevohlidka.cz*) běžela aplikace na zkušební subdoméně `slevohlidka.rhsoft.cz`.
Vychází z nasazení projektu Počasí.

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
| `coming-soon/` | stránka „Brzy spouštíme“ pro `slevohlidka.cz` do přestěhování aplikace (R79) — viz níž |
| `hosting-check.php` | jednorázová kontrola hostingu (PHP, rozšíření, limity, `pdftotext`, odchozí HTTPS) — nahrát, přečíst, smazat |
| `subdomain-redirect/` | `.htaccess` a „samozničující“ `sw.js` pro starou subdoménu po přestěhování (R93) |

## Předpoklady na hostingu
- **PHP 8.4** s `pdo_mysql`, `mbstring`, `intl`, `dom` (vektorová vrstva letáku Penny, výstup pdftotext), `openssl`
- **`pdftotext` (Poppler) a povolené `proc_open`** — text s polohou z PDF letáků Lidlu a Albertu (R86). Ověřeno
  2026-10-06 na hostingu subdomény (`hosting-check.php`; nový hosting ověřit stejně): `/usr/bin/pdftotext` 22.02, `memory_limit` 512M, `max_execution_time` 600,
  žádné `disable_functions`, `open_basedir` dovoluje `/tmp/` (dočasný soubor s PDF), rozšíření GD i Imagick
- **MariaDB 11.4**
- **Document root** domény nasměrovaný do `public/` (WebAdmin → Web → Služby → Upravit)
- **Odchozí HTTPS** k obchodům (prodejny.kaufland.cz, xapi.tesco.com, api.prod.retail.tesco.com,
  www.lidl.cz, endpoints.leaflets.schwarz, assets.leaflets.schwarz (PDF), www.penny.cz, files.rewe.co.at, www.albert.cz,
  letaky.albert.cz, view.publitas.com (PDF), www.globus.cz, gapi.globus.cz (PDF), www.billa.cz) — Websupport ho povoluje
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

Obsah `deploy/upload/` nahraj přes FTP do kořene aplikace (např. `/slevohlidka.cz/web/`).
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
- `LETAKY_VAPID_PUBLIC_KEY` a `LETAKY_VAPID_PRIVATE_KEY` — klíče pro upozornění v telefonu (R66):
  `docker compose exec app php artisan letaky:push-keys`, vygenerovat **jednou** a neměnit (nové klíče
  zneplatní všechny odběry); prázdné = upozornění vypnutá
- volitelně `GOOGLE_CLIENT_ID` / `GOOGLE_CLIENT_SECRET` a `FACEBOOK_CLIENT_ID` / `FACEBOOK_CLIENT_SECRET` —
  přihlášení přes Google a Facebook (R96, kapitola *Přihlášení přes Google, Seznam a Facebook*); prázdné = tlačítko se neukáže
- volitelně `LETAKY_GA_MEASUREMENT_ID` (Google Analytics po souhlasu s cookies, R52) a `LETAKY_USER_AGENT`
  (User-Agent stahování — **bez `https://`**, jinak Albert vrací 400, R65; konfigurace není v cache,
  změna v `.env` platí hned bez nasazení)

> **Ladění bez SSH:** chyby jsou v `storage/logs/laravel-RRRR-MM-DD.log` —
> stáhni ho přes FTP. `APP_DEBUG=true` na produkci nezapínej ani dočasně.

## 5) Účet a správce katalogu

1. Na `https://slevohlidka.cz/registrace` si založ účet.
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
| Slevohlídka – Kaufland prodejny | `45 4,12 * * *` | `https://slevohlidka.cz/cron/import-stores?chain=kaufland&token=<LETAKY_CRON_TOKEN>` (~1,5 min; seznam prodejen a jejich akcí, R49 — před stažením Kauflandu) |
| Slevohlídka – Kaufland | `0 5,13 * * *` | `https://slevohlidka.cz/cron/import-offers?chain=kaufland&token=<LETAKY_CRON_TOKEN>` |
| Slevohlídka – Tesco | `10 5,13 * * *` | `…/cron/import-offers?chain=tesco&token=…` (~45 s, nejdelší) |
| Slevohlídka – Lidl | `20 5,13 * * *` | `…/cron/import-offers?chain=lidl&token=…` (~30 s) |
| Slevohlídka – Penny | `30 5,13 * * *` | `…/cron/import-offers?chain=penny&token=…` (~25 s) |
| Slevohlídka – Albert | `40 5,13 * * *` | `…/cron/import-offers?chain=albert&token=…` |
| Slevohlídka – Globus | `50 5,13 * * *` | `…/cron/import-offers?chain=globus&token=…` (~25 s) |
| Slevohlídka – Billa | `0 6,14 * * *` | `…/cron/import-offers?chain=billa&token=…` (~50 s; celý katalog, po ranní výměně akcí) |
| Slevohlídka – kategorie | `0 4 1 * *` | `…/cron/import-categories?token=…` — strom kategorií (stačí občas) |
| Slevohlídka – souhrn | `30 6-22 * * *` | `…/cron/send-digests?token=…` — e-mailové souhrny a okamžitá upozornění nových akcí (R42, R58) a upozornění v telefonu (R66); jedno volání = dávka 100 uživatelů na e-mail a 200 na telefon, kterým je čas a od jejichž souhrnu doběhlo stažení (R54) |
| Slevohlídka – úklid | `15 3 * * *` | `…/cron/prune-sessions?token=…` — smaže vypršelé relace (IP, prohlížeč) a propadlé odkazy na obnovu hesla (R53; zásady slibují průběžné mazání) |

Hned po nasazení zavolej URL stažení ručně v prohlížeči (kategorie první), ať se nečeká
na ranní běh. *Posílat výsledky e-mailem* stačí zapnout na první dny, pak hlídá `/health/imports`.

Odpověď je prostý text, např. `Tesco — uloženo nabídek: 5139` (200), při chybě
`Tesco — chyba: …` (500); každé stažení je i v tabulce `scrape_runs`. Špatný nebo
chybějící token vrací **404**. Akce, které obchod mezi dvěma staženími stáhl, se označí
a z výpisů zmizí (R16). Když jich chybí víc než 40 % neskončených (rozbitý parser, chybějící
leták), neoznačí se žádná a odpověď je `Lidl — uloženo nabídek: …, ale chybějící akce se neoznačily
jako stažené (…)` (200, stav `partial` v `scrape_runs`, R54) — zdroj je potřeba prověřit.

**Limit požadavku (O8):** cron URL si prodlouží `max_execution_time` na 180 s
(`letaky.cron.time_limit_seconds`); timeout proxy hostingu to ale přebít může. Když Tesco
v `scrape_runs` končí bez dokončení nebo cron hlásí timeout, ověř limit u Websupportu.
Záložní řešení: cron typu **Spuštění PHP 8.4 souboru** běží v CLI bez limitu požadavku
i proxy — potřeboval by malý PHP skript, který stažení spustí (zatím neexistuje).

## 7) Ověř

```bash
curl -I  "http://slevohlidka.cz/prihlaseni"
curl -I  "https://slevohlidka.cz/prihlaseni"
curl -s  "https://slevohlidka.cz/version.txt?v=$(date +%s)"
curl -s  "https://slevohlidka.cz/health/imports"
curl -si "https://slevohlidka.cz/cron/import-offers?chain=kaufland&token=spatny" | head -1
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
   curl -s "https://slevohlidka.cz/robots.txt"     # Allow: /, Disallow soukromých cest, Sitemap: https://…
   curl -s "https://slevohlidka.cz/sitemap.xml"    # adresy s https:// (jinak nefunguje trustProxies)
   curl -s "https://slevohlidka.cz/llms.txt"
   curl -s "https://slevohlidka.cz/" | grep -E 'canonical|og:image'   # https://, ne http://
   ```
8. Náhled odkazu: sdílet adresu v chatu, nebo ověřit v [opengraph.xyz](https://www.opengraph.xyz).

## Vyhledávače (jednou po prvním nasazení)

1. [Google Search Console](https://search.google.com/search-console): přidat vlastnost
   `https://slevohlidka.cz/` (ověření DNS záznamem TXT ve WebAdminu), odeslat
   `https://slevohlidka.cz/sitemap.xml`.
2. [Bing Webmaster Tools](https://www.bing.com/webmasters): import ze Search Console (založeno 2026-10-07).
   Ohlášení stránek přes IndexNow (R105) jsou v *IndexNow* — posílá je stažení obchodu samo, klíč
   z `letaky.indexnow.key` je na `https://slevohlidka.cz/<klíč>.txt` (jiný `LETAKY_INDEXNOW_KEY`, prázdný vypne).
3. [Seznam Webmaster](https://webmaster.seznam.cz): ověření značkou `seznam-wmt` v hlavičce — kód je
   v `letaky.site_verification.seznam` (jiný jde nastavit `LETAKY_SEZNAM_WMT` v `.env`); pak odeslat sitemap.
4. Kontrola strukturovaných dat: [Rich Results Test](https://search.google.com/test/rich-results)
   na úvodní stránku (Organization, WebSite).

## Doména `slevohlidka.cz`: stránka „Brzy spouštíme“ (R79)

Do přestěhování aplikace ze zkušební subdomény běží na `slevohlidka.cz` statická stránka
z `deploy/coming-soon/` (HTML, CSS, JS, bez PHP a databáze).

1. Ve WebAdminu: doména `slevohlidka.cz` (i `www`) s certifikátem Let's Encrypt.
2. Nahrát **celý obsah** `deploy/coming-soon/` do kořene domény — včetně skrytého `.htaccess`
   (HTTPS, `www` → bez `www`, bezpečnostní hlavičky, cache).
3. Ověřit: `https://slevohlidka.cz/` (i v tmavém režimu a na telefonu), `http://` a `www.`
   přesměrují, neexistující adresa ukáže stránku se stavem 404.
4. Změna textu: upravit `index.html`; změna stylu nebo skriptu: zvýšit `?v=` v `index.html`
   (proxy hostingu cachuje statické soubory). Odpočet do spuštění: datum do `LAUNCH_AT`
   v `js/main.js` (`null` = bez odpočtu).

Po přestěhování aplikace na doménu se obsah složky na hostingu smaže a nahradí aplikací;
složku `deploy/coming-soon/` pak jde z repozitáře odstranit.

**Kontakt a User-Agent (R80):** kontaktní e-mail `info@slevohlidka.cz` je v kódu
(`letaky.operator.email`) a projeví se nasazením. Výchozí User-Agent je `Slevohlidka/1.0 (+slevohlidka.cz)`
— řádek `LETAKY_USER_AGENT` se starou adresou v `.env` na hostingu (oprava R65) ho přebije,
při nasazení ho smaž nebo přepiš na novou hodnotu.

## Přestěhování na `slevohlidka.cz` (R93)

Aplikace se stěhuje ze zkušební subdomény `slevohlidka.rhsoft.cz` (hosting účtu rhsoft.cz) na
vlastní hosting domény `slevohlidka.cz`, kde zatím běží stránka „Brzy“. **V databázi se nic
nemění** — adresa webu v ní není, odkazy se skládají z `APP_URL` (ověřeno 2026-10-06 na výpisu
vývojové databáze). Mění se jen `.env`, cron a soubory na obou hostinzích.

**Co uživatelé po přesunu poznají:** musí se znovu přihlásit (cookie relace patří ke staré
adrese), upozornění v telefonu zapnout znovu v Můj účet a aplikaci z plochy přidat znovu (stará
se otevře a přesměruje, „samozničující“ service worker na staré adrese smaže její uložená data).
Volby v prohlížeči (vzhled, poslední hledání) se nepřenesou.

### Příprava (kdykoli předem)

1. **Ověř nový hosting:** nahraj `deploy/hosting-check.php` pod náhodným jménem do kořene
   `slevohlidka.cz`, otevři ho v prohlížeči a **hned smaž**. Žádný řádek `CHYBA` — hlavně PHP 8.4,
   `proc_open` a `pdftotext` (bez nich spadne stažení Lidlu, Albertu, Globusu a Billy z PDF).
   Jiný výsledek než na starém hostingu zapiš do *Předpoklady na hostingu*.
2. **WebAdmin nového hostingu:** PHP **8.4**, databáze **MariaDB 11.4** (zapiš si host, název,
   uživatele a heslo), schránka `info@slevohlidka.cz` (heslo do `.env`). Cron zatím nezakládej.
3. **Sestav balíček** z commitnutého stavu (`deploy\build-upload.ps1`, krok 1).
4. **Připrav `.env`:** stáhni přes FTP `.env` ze starého hostingu a změň v něm jen:
   - `APP_URL=https://slevohlidka.cz`
   - `DB_*` — údaje nové databáze
   - `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` — schránka `info@slevohlidka.cz`
     (pro doménu jsou v DNS SPF, DKIM a DMARC; odesílatel z jiné domény by padal do spamu)
   - řádek `LETAKY_USER_AGENT` se starou adresou smaž (výchozí je `+slevohlidka.cz`, R80)

   **Beze změny nech** `APP_KEY` (šifruje relace a tokeny), `LETAKY_VAPID_*` (klíče upozornění
   v telefonu), `TESCO_API_KEY`, `LETAKY_CRON_TOKEN` a `LETAKY_GA_MEASUREMENT_ID`.

### Den přesunu

5. **Na starém hostingu vypni všechny crony** Slevohlídky (WebAdmin → Cron). Jinak by během
   přesunu stahovaly a posílaly e-maily do staré databáze a po přesunu by běžely dvakrát —
   dvojí stažení a dvojité souhrny.
6. **Export celé databáze** ze starého hostingu: phpMyAdmin → *Exportovat* → *Vlastní*, **všechny
   tabulky** (včetně `migrations`; ne jen výběr z *Záloha databáze*), SQL, gzip, *Přidat příkaz
   DROP TABLE*. Přes FTP stáhni i profilové obrázky `storage/app/private/avatars/`.
7. **Nový hosting:**
   1. smaž obsah stránky „Brzy“ (i skrytý `.htaccess`) a nahraj `deploy/upload/` (krok 3) a `.env`
   2. document root domény nasměruj do `public/` (WebAdmin → Web → Služby → Upravit);
      `www.slevohlidka.cz` přesměruje na adresu bez `www` `public/.htaccess` (R93)
   3. avatary nahraj do `storage/app/private/avatars/`
   4. v phpMyAdminu importuj export z kroku 6 a pak vyprázdni přihlášení a cache staré adresy:
      ```sql
      TRUNCATE TABLE `sessions`;
      TRUNCATE TABLE `cache`;
      TRUNCATE TABLE `cache_locks`;
      ```
8. **Ověř** podle kroku 7 *Ověř* (adresy `https://slevohlidka.cz/…`), navíc:
   - `curl -I https://www.slevohlidka.cz/akce` → **301** na `https://slevohlidka.cz/akce`
   - přihlášení, Moje slevy, profilový obrázek v Můj účet
   - obnova hesla nebo zkušební souhrn → e-mail dorazí od `info@slevohlidka.cz`; jednou ho pošli
     na [mail-tester.com](https://www.mail-tester.com) (SPF, DKIM a DMARC v pořádku)
9. **Cron na novém hostingu** podle tabulky v kroku 6 (adresy `https://slevohlidka.cz/cron/…`)
   a hned jednou ručně kategorie, prodejny Kauflandu a stažení všech obchodů. `/health/imports`
   musí vrátit **200**.
10. **Stará subdoména:** smaž přes FTP celou aplikaci včetně `.env` (hesla k databázi a e-mailu)
    a do document rootu subdomény nahraj obsah `deploy/subdomain-redirect/` (`.htaccess`
    a `sw.js`). Ověř:
    ```bash
    curl -sI "https://slevohlidka.rhsoft.cz/akce?chain=lidl" | grep -i location   # https://slevohlidka.cz/akce?chain=lidl
    curl -s  "https://slevohlidka.rhsoft.cz/sw.js" | head -3                         # „samozničující“ service worker
    ```
    Starou databázi nech pár týdnů jako zálohu a pak ji smaž (zásady slibují zálohy nejdéle
    6 měsíců). Chybu certifikátu `www.slevohlidka.rhsoft.cz` vyřeší smazání záznamu DNS `www`.

### Po přesunu

11. **Search Console:** přidej vlastnost domény `slevohlidka.cz` (záznam TXT v DNS), odešli
    `https://slevohlidka.cz/sitemap.xml` a ve staré vlastnosti `https://slevohlidka.rhsoft.cz/`
    spusť *Nastavení → Změna adresy* na novou doménu. Bing: import ze Search Console.
12. **UptimeRobot** na `https://slevohlidka.cz/health/imports` (a `/up`); **Google Analytics:**
    adresu datového streamu změň na `https://slevohlidka.cz` (ID měření zůstává).
13. **Testovacím uživatelům** napiš (Zprávy od nás, `/zpravy`): nová adresa, znovu se přihlásit,
    zapnout upozornění v telefonu a přidat aplikaci na plochu.
14. **Repozitář:** adresa produkce v `README.md` a `CLAUDE.md`, řádek v *Nasazené verze*, složku
    `deploy/coming-soon/` smaž, odškrtni bod v `docs/ZVEREJNENI.md`.

## Přihlášení přes Google, Seznam a Facebook (R96, R98)

Tlačítka se ukážou, až budou v `.env` klíče aplikace u poskytovatele. Adresa návratu je pro
přihlášení, propojení účtu i potvrzení totožnosti jedna — **`https://slevohlidka.cz/prihlaseni/google/navrat`**
a **`https://slevohlidka.cz/prihlaseni/facebook/navrat`** (doména z `APP_URL`). Nastaveno 2026-10-06,
Google aplikaci ověřil (značka i název na přihlašovací obrazovce).

**Google** ([console.cloud.google.com](https://console.cloud.google.com) → *Google Auth Platform*), projekt „Slevohlidka“:

1. *Branding*: název Slevohlídka, e-mail podpory, home page `https://slevohlidka.cz/`, zásady `/ochrana-udaju`,
   podmínky `/podminky`, autorizovaná doména `slevohlidka.cz`. Logo jen s vědomím, že spustí ověření značky.
   **Ověření značky** kontroluje stránky bez JavaScriptu — úvodní stránka musí v obsahu ze serveru (R94)
   vysvětlovat účel a odkazovat na zásady, zásady musí popsat údaje od Googlu a prohlásit Limited Use
   (Google API Services User Data Policy). Doména musí být ověřená v Search Console pod účtem vlastníka projektu.
2. *Data Access*: jen `openid`, `…/auth/userinfo.email`, `…/auth/userinfo.profile` (nevyžadují bezpečnostní posouzení).
3. *Audience*: **In production** (v režimu Testing se přihlásí jen zapsaní testeři).
4. *Clients* → Web application „Slevohlídka web“, *Authorized redirect URIs* **jen** produkční adresa návratu.
   **Žádný `http://localhost`** — Project Checkup pak hlásí „Use secure flows“ (loopback redirect v produkčním
   klientovi). Tajemství klienta Google ukáže jen při vytvoření. ID a tajemství do `.env` na hostingu.
5. **Vývoj má vlastní projekt** „Slevohlidka vyvoj“: *Audience* v režimu **Testing** se svým Gmailem jako
   testerem, klient s redirect URI `http://localhost:54720/prihlaseni/google/navrat`; jeho klíče jen do lokálního `.env`.

**Seznam** ([vyvojari.seznam.cz/oauth/admin](https://vyvojari.seznam.cz/oauth/admin), R98), přihlásit se účtem Seznam:

1. Nová služba „Slevohlídka“: ikona čtvercová (zobrazí se 32×32, stačí `public/images/brand/icon-192.png`), odkaz
   na web `https://slevohlidka.cz/`, zásady `/ochrana-udaju`.
2. *Adresy pro přesměrování* (každá na řádek, za doménou cesta): `https://slevohlidka.cz/prihlaseni/seznam/navrat`
   a pro vývoj `http://localhost:54720/prihlaseni/seznam/navrat` (localhost Seznam pouští i přes http).
   Ne `/prihlaseni/seznam` — to je odchod k Seznamu, Seznam vrací na `…/navrat`.
3. *Client ID* a *OAuth secret* do `SEZNAM_CLIENT_ID` a `SEZNAM_CLIENT_SECRET` (stejné lokálně i na produkci).
4. Rozsah je jen `identity` (posílá ho `SeznamProvider`). Tlačítko je podle manuálu Seznamu
   („Přihlásit přes Seznam“, červené „esko“, na tmavém bílé) — barvy ani text neměnit (zakázaná použití v manuálu).

**Facebook** ([developers.facebook.com](https://developers.facebook.com)), aplikace „Slevohlídka“ (App ID `2052716665448427`):

1. *Create app* → případ použití **Authenticate and request data from users with Facebook Login**, bez firmy.
2. *Případy použití* → Facebook Login → *Customize*: oprávnění `email` přidat (`public_profile` je tam);
   *Settings*: Client a Web OAuth login ano, Enforce HTTPS ano, Strict Mode ano, *Valid OAuth Redirect URIs*
   = produkční adresa návratu (potvrdit Enterem), JavaScript SDK ne. Localhost se nezapisuje — v režimu
   vývoje ho Meta povoluje sama.
3. *App settings* → *Basic*: *App domains* jen `slevohlidka.cz` (bez `https://`; doména mimo Site URL platformy
   Website uložení zablokuje), *Privacy Policy URL* `/ochrana-udaju`, *Terms of Service URL* `/podminky`,
   *User data deletion* → *Data deletion instructions URL* `/ochrana-udaju` (bez kotvy), ikona 1024×1024
   **s průhledným pozadím** (bílé Meta odmítne; zmenšené `resources/brand/slevohlidka-logo.png`), kategorie
   Nakupování, platforma *Website* `https://slevohlidka.cz/`. Sekce *Data Protection Officer* zůstává prázdná
   (pověřence nemáme, zásady kap. 1). Červený rámeček „Currently ineligible for submission“ se po uložení
   přepočítá se zpožděním.
4. Dokud aplikace není **Live** (*Zveřejnit*), přihlásí se jen lidé s rolí v *App roles*. Live může chtít
   ověření firmy (výpis z živnostenského rejstříku, ověření domény nebo `info@slevohlidka.cz`).
5. *App ID* a *App secret* do `FACEBOOK_CLIENT_ID` a `FACEBOOK_CLIENT_SECRET` (stejné lokálně i na produkci).

**Ověř:** na `/prihlaseni` jsou tlačítka; přihlášení novým účtem vede na *Dokončení registrace*; v Mém účtu
v sekci Zabezpečení jde propojit a odpojit. **Na iPhonu z plochy** (R66) ověř, že se po přihlášení přes
poskytovatele vrátíš do aplikace přihlášený — přesměrování mimo web se otevře v Safari, které má vlastní cookies.

## Monitoring: hlídání stahování

URL **`https://slevohlidka.cz/health/imports`** (veřejná, bez tokenu) vrací **200**,
když má každý obchod úspěšné stažení za posledních 26 hodin (`letaky.health.max_import_age_hours`),
jinak **503**. Na každém řádku jeden obchod, např. `Tesco — VÝPADEK: poslední úspěšné stažení 30. 9. 12:00`.

V [UptimeRobot](https://uptimerobot.com) (zdarma, stejně jako Počasí): *Add New Monitor* → HTTP(s),
URL `/health/imports`, interval 1 hodina, upozornění e-mailem. Ozve se při výpadku cronu,
změně odpovědi obchodu (`SourceResponseChanged`) i neplatném klíči Tesca. Částečné stažení (`partial`, R54)
se za úspěch nepočítá — když trvá 26 hodin, obchod je na `/health/imports` jako výpadek.

**Ostatní úlohy cronu (R115):** `https://slevohlidka.cz/health/tasks` ve stejném tvaru — druhý monitor
v UptimeRobotu (interval 1 hodina). Výpadek, když poslední úspěšný běh je starší než limit:

| Řádek | Úloha | Limit |
|---|---|---|
| Prodejny Kaufland | `import-stores` (nejnovější stažený seznam akcí prodejny) | 18 h |
| Centrum upozornění — nové / končící / dnes začínající akce, E-mailové souhrny, Upozornění v telefonu | `send-digests`, každý kanál zvlášť | 9 h (přes noc pauza 8 h) |
| Denní úklid | `prune-sessions` | 26 h |
| Kategorie katalogu | `import-categories` | 32 dní |

Selhání po posledním úspěchu se připíše „(poté chyba …)“, výpadek je až po limitu. Běhy jsou v tabulce
`task_heartbeats` (prodejny v `stores.offer_keys_fetched_at`); text chyby je v logu a v odpovědi cron URL.
Po prvním nasazení zavolej úklid a kategorie ručně, jinak hlásí „nikdy“ až do svého cronu.

## Limit odesílání e-mailů

Websupport (ochrana proti spamu, ověřeno 2026-10-03): **300 e-mailů za hodinu z jedné
schránky, 2 000 za hodinu z celé domény**; počítá se každý příjemce. Po překročení jde
60 minut odeslat nic a zprávy z té doby se nedoručí. Limit se netýká schránek u Websupportu.

Souhrny (`/cron/send-digests`) jdou po dávkách (R54): jedno volání zpracuje nejvýš
`letaky.digest.users_per_run` (100) uživatelů, od nejdéle čekajících, a jen ty, od jejichž
posledního souhrnu doběhlo stažení akcí (R58) — bez nových stažení je volání skoro zadarmo.
Volá se každou hodinu 6:30–22:30: okamžitá upozornění (nejvýš jednou za hodinu) přijdou do
hodiny po stažení, denní souhrn ráno po prvním stažení. Hodina nepřesáhne 100 e-mailů. Uživatel,
kterému se souhrn neodeslal (chyba SMTP), ho dostane při dalším volání. Při víc uživatelích
zvětšit dávku nejvýš do limitu 300 e-mailů za hodinu.

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

**Každá nová migrace potřebuje SQL skript** `deploy/migrations-<datum>-<popis>.sql`
(opakovatelný: `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`) včetně zápisu do
tabulky `migrations` — ve stejném commitu jako migrace. Nové produkty katalogu jdou na
produkci přidat ve správě katalogu, nebo skriptem `deploy/data-<datum>-katalog-<popis>.sql`
s `INSERT … ON DUPLICATE KEY UPDATE` podle jedinečného názvu a kategorií přes `source_id`
(jako `data-2026-10-04-katalog-rozsireni.sql`) — `REPLACE` podle `id` by přepsal produkty,
které admin na produkci přidal sám.

Co bylo zvláštní na jednotlivých dřívějších nasazeních (SQL skripty, nové proměnné, ruční kroky),
je v [HISTORIE_NASAZENI.md](HISTORIE_NASAZENI.md). Poznámky k nasazení, které se teprve chystá,
patří sem pod tenhle postup; po nasazení se přesunou tam (R106).

### Aktualizace z `3d36522` (připravuje se)

Přehled kvality dat pro admina `/kvalita-dat` a upozornění na propad akcí nebo ověřených cen
(R129) — **s SQL skriptem**. Mění se parsery letáků (jen počítají, výběr akcí stejný), import,
upozornění adminům a denní úklid; `.env` beze změny, žádný soubor nezmizel; `lang/cs/app.php`
se změnil — verze Inertie se změní a otevřené stránky se načtou znovu.

Navíc nákupní seznam s vlastními položkami, sdílením odkazem a „Smazat skončené akce“ a sdílení
akce z karty (R130) — **s druhým SQL skriptem**; zásady ochrany údajů mají novou část o sdílení seznamu.

0. **Záloha databáze** a v phpMyAdminu `deploy/migrations-2026-10-10-kvalita-dat.sql`
   a `deploy/migrations-2026-10-10-nakupni-seznam-sdileni.sql` (před nahráním kódu, hned za ním kód —
   vlastní položky bez akce stará verze nezobrazí).
1. **Nahraj** z `deploy/upload/`: `public/build/` (celý), `app/` (celou složku — nové třídy
   v `Domain/Offers`, `Domain/Sources/Pdf`, `Http/Controllers`, `Models`), `vendor/composer/` (autoloader),
   `config/letaky.php`, `routes/web.php`, `database/migrations/`, `lang/cs/app.php`, `resources/legal/privacy.md`
   a `public/version.txt`.
2. **Ověř:** `version.txt`; R130: na `/seznam` pole „Co koupit“ našeptává akce a „Almette“ přidá jako
   vlastní položku, „Sdílet odkaz“ — odkaz otevřený v anonymním okně ukáže seznam a odškrtnutí je vidět
   u vlastníka, „Zrušit odeslané odkazy“ → starý odkaz 404; karta akce má ikonu „Poslat akci“; admin má v menu pod avatarem **Kvalita dat** — po dalším stažení
   každého obchodu tabulka letáků s počtem akcí a u Penny, Lidlu, Albertu, Globusu a Billy (leták
   na příští týden) podíl ověřených cen; `/kvalita-dat` pro ne-admina 403; `/cron/prune-sessions`
   vypíše i „smazáno starých statistik letáků“; `/health/imports` a `/health/tasks` 200.
3. Zapiš verzi do *Nasazené verze*, SQL skript do *Historie SQL skriptů* (datum) a tuhle sekci
   přesuň do `HISTORIE_NASAZENI.md`.

---

## Záloha databáze

Akce jdou kdykoli stáhnout znovu, nenahraditelné jsou **účty, nastavení a hlídané položky**
a ruční opravy katalogu. Před každým SQL skriptem a jinak aspoň jednou měsíčně:

1. phpMyAdmin → databáze → *Exportovat* → metoda *Vlastní*, formát SQL,
   komprese **gzip**, zaškrtnout *Přidat příkaz DROP TABLE*
2. Tabulky stačí `users`, `followed_chains`, `watch_items`, `watch_item_offer_exclusions`,
   `offer_reports`, `products`, `categories`, `offer_product_exclusions`, `migrations` — akce
   a jejich přiřazení stáhne a dopočítá cron
3. Soubor ulož mimo hosting jako `slevohlidka-RRRR-MM-DD.sql.gz` — na vlastní disk, ne do cloudového
   úložiště (to by byl další příjemce údajů, zásady kap. 4)
4. **Zálohy starší než 6 měsíců smaž** — zásady (kap. 3) slibují nejdéle 6 měsíců (R69)

## Historie SQL skriptů

| Skript | Obsah | Nasazeno |
|---|---|---|
| `migrations-2026-10-02-init.sql` | výchozí schéma — všechny tabulky k 2026-10-02 a záznamy v `migrations` | 2026-10-02 |
| `data-2026-10-02-katalog.sql` | strom kategorií e-shopu Tesco (1 728) a 164 produktů katalogu (R28, R37), `REPLACE` — opakovatelný; až po init | 2026-10-02 |
| `migrations-2026-10-03-prodejny.sql` | prodejny Kauflandu (R49): tabulky `stores` a `offer_stores`, `followed_chains.store_codes`; opakovatelný, pustit **před** nahráním kódu | 2026-10-03 |
| `migrations-2026-10-02-ucet.sql` | nastavení účtu: `users.avatar_path` (R40), `users.offers_sort` a `min_discount_percent` (R41), `users.digest_frequency` a `digest_sent_at` (R42); opakovatelný, pustit **před** nahráním kódu | 2026-10-02 |
| `migrations-2026-10-03-souhlasy.sql` | souhlasy (R51): `users.terms_accepted_at`, `terms_version`, `marketing_consent_at`, `marketing_consent_version`, `marketing_consent_withdrawn_at`; dosavadní účty označí jako ověřené (`email_verified_at`); opakovatelný, pustit **před** nahráním kódu | 2026-10-03 |
| `migrations-2026-10-04-nakupni-seznam.sql` | nákupní seznam (R61): tabulka `shopping_list_items`; opakovatelný, pustit **před** nahráním kódu | 2026-10-04 |
| `migrations-2026-10-04-upozorneni-v-telefonu.sql` | upozornění v telefonu (R66): tabulka `push_subscriptions`, `users.push_sent_at`; opakovatelný, pustit **před** nahráním kódu | 2026-10-04 |
| `data-2026-10-04-katalog-rozsireni.sql` | rozšíření katalogu (R70): 42 nových produktů a nová pravidla šesti (Minerální voda, Džus, Prací prostředek, Salám, Ovesné vločky, Nealkoholické pivo); podle názvu, opakovatelný, nezávisí na kódu | 2026-10-04 |
| `migrations-2026-10-05-centrum-upozorneni.sql` | centrum upozornění (R74): tabulky `notifications` a `announcements`, `users.notified_at`; opakovatelný, pustit **před** nahráním kódu | 2026-10-05 |
| `migrations-2026-10-05-posledni-aktivita.sql` | poslední aktivita (R84): `users.last_seen_at` s indexem, dosavadním účtům doplní z relací; opakovatelný, pustit **před** nahráním kódu | 2026-10-05 |
| `migrations-2026-10-06-prihlaseni-pres-google.sql` | přihlášení přes Google a Facebook (R96): tabulka `social_accounts`, `users.password` nepovinné; opakovatelný, pustit **před** nahráním kódu | 2026-10-06 |
| `migrations-2026-10-09-indexy.sql` | indexy pro rostoucí historii (R113): `offers.valid_from`, `created_at`, `withdrawn_at`, `scrape_runs (status, finished_at)`; opakovatelný, pustit kdykoli (stará verze kódu s ním běží) | 2026-10-09 |
| `migrations-2026-10-09-hlidani-uloh.sql` | hlídání úloh cronu (R115): tabulka `task_heartbeats`; opakovatelný, pustit **před** nahráním kódu | 2026-10-09 |
| `migrations-2026-10-09-tohle-ne.sql` | „Tohle ne“ a hlášení chyb (R125): tabulky `watch_item_offer_exclusions` a `offer_reports`; opakovatelný, pustit **před** nahráním kódu | 2026-10-10 |
| `migrations-2026-10-10-kvalita-dat.sql` | přehled kvality dat (R129): tabulka `leaflet_stats` se statistikou letáků za každé stažení; opakovatelný, pustit **před** nahráním kódu | — |
| `migrations-2026-10-10-nakupni-seznam-sdileni.sql` | nákupní seznam (R130): `shopping_list_items.offer_id` nepovinné, `custom_name` a `chain` vlastní položky, `users.shopping_share_token`; opakovatelný, pustit **před** nahráním kódu | — |

## Nasazené verze

Co běží na produkci — pro `git log <commit>..HEAD` při dalším nasazení
(nové SQL skripty, změněné složky, nové proměnné v `.env`).

| Datum | Commit | Co |
|---|---|---|
| 2026-10-02 | `c5d45d7` + `config/inertia.php` z `a17fbae` | první nasazení: balíček, schéma a katalog, crony stahování |
| 2026-10-02 | `20ef035` | druhé nasazení: účet a e-mailový souhrn (R39–R45), Globus (R46), vzhled (R47), Billa (R48); SQL `migrations-2026-10-02-ucet.sql`, crony Globus, Billa a souhrn. První stažení: Globus 656, Billa 3 421 nabídek |
| 2026-10-03 | `ae88b48` | třetí nasazení: Kaufland po prodejnách (R49); SQL `migrations-2026-10-03-prodejny.sql`, cron `import-stores` (45 4,12) |
| 2026-10-03 | `aed786f` | čtvrté nasazení: krmivo pro zvířata (R50), zveřejnění (R51) — podmínky a zásady, patička, souhlasy při registraci, ověření e-mailu, odhlášení z e-mailů, české chybové stránky; cookie lišta a Google Analytics 4 po souhlasu (R52, CSP v `public/.htaccess`); SQL `migrations-2026-10-03-souhlasy.sql`. Volitelně `LETAKY_GA_MEASUREMENT_ID` v `.env` |
| 2026-10-03 | `b2996c0` | páté nasazení: ochrana účtů a úklid (R53) — registrace proti botům, kontrola uniklých hesel, limit přihlášení na IP, `favicon.ico`, User-Agent; cron `/cron/prune-sessions` (15 3). Bez SQL skriptu |
| 2026-10-04 | `10072aa` | šesté nasazení: opravy a funkce z revize (R54–R64) — pojistky importu, prodlužování akcí Billy, heslo při změně e-mailu, souhrny po dávkách; nový účet sleduje všechny obchody, „Jsem v obchodě“, manifest; registrace se skutečnými akcemi; zámek stažení; okamžité upozornění (cron souhrnu `30 6-22`); „Je to opravdu sleva?“; „Hlídat“ z karty; nákupní seznam (SQL `migrations-2026-10-04-nakupni-seznam.sql`); kompaktní řádky; Můj účet a Moje obchody s ukládáním hned. Po nasazení Albert opraven `LETAKY_USER_AGENT` v `.env` (R65) |
| 2026-10-04 | `046d8eb` | sedmé nasazení: User-Agent bez `https://` v kódu (R65); aplikace v telefonu (R66) — manifest se zkratkami, úvodní obrazovky iPhonu, spodní lišta záložek, výzva k přidání na plochu, service worker s offline režimem a odškrtáváním bez signálu, upozornění v telefonu (web push); SQL `migrations-2026-10-04-upozorneni-v-telefonu.sql`, klíče `LETAKY_VAPID_*` v `.env`, nový balíček `minishlink/web-push` ve `vendor/` |
| 2026-10-04 | `25a224e` | osmé nasazení: hlavička na telefonu s logem a spodní lišta do 799 px (R66); revize před spuštěním (R67–R69) — adresy z `APP_URL`, odhlášení zařízení po změně hesla, hodinový limit e-mailů, titulky a `noindex` Alberta, `security.txt`, GA bez tokenů v adrese, právní texty s datem účinnosti 2026-10-04; nový kořenový `.htaccess`; katalog 206 produktů (R70, SQL `data-2026-10-04-katalog-rozsireni.sql`). `/public/akce` zatím bez přesměrování (oprava v devátém) |
| 2026-10-04 | `e7f942f` | deváté nasazení: jen `public/.htaccess` — přesměrování `/public/…` na adresu bez něj (R67); `version.txt` zůstává `25a224e` |
| 2026-10-05 | `0068710` | desáté nasazení: hledání s našeptávačem a opravou překlepů (R71), přátelský tón, právní texty jako firma a stránka `/kontakt` (R72), české adresy přihlášení a registrace se 301 ze starých (R73), centrum upozornění se zvonkem — nové a končící akce, nejlevněji za 12 týdnů, zprávy od nás, upozornění v telefonu ze záznamů, mobilní navigace do 829 px (R74), katalog admina v menu pod avatarem (R75); SQL `migrations-2026-10-05-centrum-upozorneni.sql` |
| 2026-10-05 | `4cf9e35` | jedenácté nasazení: akce, které ještě nezačaly (R76) — sekce „Brzy“ v Mých slevách, „Vyplatí se počkat“, štítek „Od …“, filtr „Brzy začnou“, nákupní seznam, upozornění s datem začátku a ráno „Od dneška platí…“; zrušené „Jen slevy“ ve Všech akcích (R77); bez SQL skriptu |
| 2026-10-05 | `400c89b` | dvanácté nasazení: nová verze aplikace v telefonu se po nasazení načte sama nebo lištou „Načíst“, ruční kontrola v Můj účet (R78); kontaktní e-mail `info@slevohlidka.cz` a User-Agent `+slevohlidka.cz` (R80), řádek `LETAKY_USER_AGENT` z `.env` na hostingu smazaný; bez SQL skriptu |
| 2026-10-05 | `d887463` | třinácté nasazení: patička e-mailů jen s mottem, bez údajů provozovatele (R81); nahrané jen `message.blade.php`, `lang/cs/app.php` a `version.txt`, bez SQL skriptu |
| 2026-10-05 | `d22d9ee` | čtrnácté nasazení: Všechny akce s výběrem víc obchodů (přihlášený má předvybrané sledované), štítek „Bez e-shopu“, přepínač karty / řádky i v Mých slevách (R82); neutrální tmavý režim — červená jen tlačítka a cenovky slev (R83); celý balíček, bez SQL skriptu |
| 2026-10-05 | `42479f4` | patnácté nasazení: přehled uživatelů pro admina `/uzivatele` s poslední aktivitou a nastavením (R84), oprava tlačítka na červeném panelu a hrany tlačítek v tmavém režimu (R83, `f43d766`); SQL `migrations-2026-10-05-posledni-aktivita.sql` |
| 2026-10-06 | `d226ba4` | šestnácté nasazení: oprava stažení Kauflandu — stránka má od zveřejnění příštího týdne oba týdny a stažení se stránkami prodejen padalo na paměti (od 5. 10. 13:01 bez nových akcí, „Brzy“ bez Kauflandu); nahrané jen `app/Domain/Sources/Kaufland/`, `config/letaky.php` a `version.txt`, bez SQL skriptu. Ruční stažení po nasazení: 1 465 akcí, 798 od 7. 10. |
| 2026-10-06 | `034fa36` | sedmnácté nasazení: víc cen z letáků bez LLM — leták Penny s novými pravidly (R85), PDF letáků přes `pdftotext` (R86): Lidl s akcemi ze zbytku potravinového letáku a Albert poprvé s akcemi s cenou (R87, `mentions_only` zrušené); bez SQL skriptu. Ruční stažení po nasazení: Penny 519, Lidl 275, Albert 1 393 nabídek |
| 2026-10-06 | `7dbe429` | osmnácté nasazení: akce, které ještě nezačaly, i u Globusu a Billy z PDF letáků příštího týdne (R88, R89; převzetí řádku z PDF akcí z API), oprava letáku Penny se složkou `…_tl2` (`60f0211`); bez SQL skriptu, smazané přesunuté `AlbertBox.php` a `AlbertTile.php`. Ruční stažení: Globus 797, Billa 3 596, Penny 906 nabídek; v „Brzy“ Kaufland 798, Albert 772, Penny 413, Billa 189, Lidl 152, Globus 141 |
| 2026-10-06 | `6d328eb` | devatenácté nasazení: přestěhování na `slevohlidka.cz` (R93, stará subdoména přesměrovává 301), obsah pro roboty a čisté adresy (R94), cache úvodní stránky (R95); doplněno dodatečně podle `version.txt` na produkci |
| 2026-10-06 | `2a4e112` | dvacáté nasazení: přihlášení přes Google a Facebook (R96) — tlačítka, dokončení registrace se souhlasy, propojení v Mém účtu, potvrzení u poskytovatele pro účty bez hesla; zásady s částí o údajích od Googlu a Facebooku (Limited Use), obsah ze serveru viditelný bez JavaScriptu; SQL `migrations-2026-10-06-prihlaseni-pres-google.sql`, klíče `GOOGLE_*` / `FACEBOOK_*` v `.env`, `vendor/` se Socialite. Google aplikaci ověřil, Facebook zatím Unpublished |
| 2026-10-07 | `7b56244` | jednadvacáté nasazení: výkon (R97) — maskot a logo ve WebP, přednačtení kódu stránky, vložený `theme-init.js` s otiskem v CSP, písmo Nunito s českou podmnožinou, obsah pro roboty se zapnutým JavaScriptem `display: none`; doplněno dodatečně podle `version.txt` |
| 2026-10-07 | `6d0e140` | dvacáté druhé nasazení: přihlášení přes Seznam (R98) — tlačítko podle manuálu Seznamu, `SEZNAM_CLIENT_ID` / `SEZNAM_CLIENT_SECRET` v `.env`, bez SQL skriptu a bez `vendor/`; přesměrování na `login.seznam.cz` ověřeno |
| 2026-10-07 | `16b4832` | dvacáté třetí nasazení: audit přístupnosti, SEO a zobrazení (R99) — strukturovaná data v jednom grafu (organizace, drobečková navigace, akce jako `Offer`, `FAQPage` kontaktu), `/index.php` přesměruje 301, texty UI jen při celém načtení (odpověď přechodu na `/akce` 52 kB místo 104 kB), vložený `theme-init.js` bez komentářů s novým otiskem v CSP (ověřeno, že sedí), fokus, kontrasty a čtečky; bez SQL skriptu a bez `vendor/` |
| 2026-10-07 | `9324560` | dvacáté čtvrté nasazení: řazení a nastavení Mých obchodů ve Všech akcích, štítky filtrů, kategorie, Moje slevy podle obchodů a filtry na telefonu v okně (R100–R102); Microsoft Clarity po souhlasu s analytickými cookies, verze souhlasu 2, CSP a zásady (R103); náhled vlastních slov s poslední akcí z historie (R104); čitelná lišta ověření e-mailu, šipka výběrů a seznam selectu v tmavém režimu; bez SQL skriptu a bez `vendor/` |
| 2026-10-07 | `2f60395` | dvacáté páté nasazení: IndexNow (R105) — stažení obchodu ohlásí změněné stránky Bingu, Seznamu a dalším, klíč na `/<klíč>.txt` (ověřeno: 200 s klíčem, jiný název 404); nahraných šest souborů v `app/`, `config/` a `routes/`, bez SQL skriptu, bez `vendor/` a `public/build/` |
| 2026-10-09 | `b75955a` | dvacáté šesté nasazení: kritická revize (R106) — hranice nových akcí při souběhu se stažením, časový rozpočet cronu souhrnů, seznam prodejen po otevření okna, „Načíst další“ jen s novou stránkou, sdílené kontroly parserů letáků; ovoce, zelenina a maso na kg z letáků Albertu a Penny, zmínky bez cizích produktů a prošlých akcí (R107); celý balíček bez `vendor/`, bez SQL skriptu |
| 2026-10-09 | `7e90785` | dvacáté sedmé nasazení: fotky Billy a Penny v menší variantě CDN (R108), odkaz akce Kauflandu na detail akce (R109), popis shodný s balením se neopakuje, „Do letáku“ (R110), schema.org bez `Product` — test rozšířených výsledků bez chyb (R111); nahrané ručně včetně `public/build/`, bez SQL skriptu a `vendor/`; `version.txt` zůstal `b75955a` |
| 2026-10-09 | `1b23d0c` | dvacáté osmé nasazení: „+1 brzy“ otevře sekci Brzy, stažení stránky dolů v aplikaci z plochy (R112), audit technického dluhu (R113) a úklid (R114), hlídání úloh cronu `/health/tasks` (R115); SQL `migrations-2026-10-09-indexy.sql` a `migrations-2026-10-09-hlidani-uloh.sql`, bez `vendor/` (jen `vendor/composer/`), smazaný `ImportFreshness.php` |
| 2026-10-09 | `ff76607` | dvacáté deváté nasazení: přilepené a plovoucí lišty na telefonu (R116, R117, R119, R120), název obchodu klepnutím na logo a výraznější Hlídat / Do seznamu (R118), podmínky a zásady `noindex` a mimo sitemap (R121), `lastmod` v sitemapě podle skutečné změny stránky (R122); `public/build/`, čtyři soubory z `app/` a `vendor/composer/`, bez SQL skriptu |
| 2026-10-10 | `b3ad7d2` | třicáté nasazení: bez posunu rozvržení po načtení, přihlášení a registrace s nadpisem i bez JS (R123), tlačítko Seznamu jednobarevně (R124), „Tohle ne“ a hlášení chyb v akcích (R125), upozornění adminům na hlášení a výpadky v centru upozornění (R126), přečtení záznamu až po otevření a cinkající zvonek (R127); SQL `migrations-2026-10-09-tohle-ne.sql` |
| 2026-10-10 | `3d36522` | třicáté první nasazení: veřejná stránka Nejlepší slevy týdne `/tyden/2026-41` s archivem po týdnech, v sitemapě, `llms.txt` a obsahu bez JS (R128); bez SQL skriptu |
