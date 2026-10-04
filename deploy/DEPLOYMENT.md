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
  letaky.albert.cz, www.globus.cz, www.billa.cz) — Websupport ho povoluje
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
- `LETAKY_VAPID_PUBLIC_KEY` a `LETAKY_VAPID_PRIVATE_KEY` — klíče pro upozornění v telefonu (R66):
  `docker compose exec app php artisan letaky:push-keys`, vygenerovat **jednou** a neměnit (nové klíče
  zneplatní všechny odběry); prázdné = upozornění vypnutá
- volitelně `LETAKY_GA_MEASUREMENT_ID` (Google Analytics po souhlasu s cookies, R52) a `LETAKY_USER_AGENT`
  (User-Agent stahování — **bez `https://`**, jinak Albert vrací 400, R65; konfigurace není v cache,
  změna v `.env` platí hned bez nasazení)

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
| Slevohlídka – Kaufland prodejny | `45 4,12 * * *` | `https://slevohlidka.rhsoft.cz/cron/import-stores?chain=kaufland&token=<LETAKY_CRON_TOKEN>` (~1,5 min; seznam prodejen a jejich akcí, R49 — před stažením Kauflandu) |
| Slevohlídka – Kaufland | `0 5,13 * * *` | `https://slevohlidka.rhsoft.cz/cron/import-offers?chain=kaufland&token=<LETAKY_CRON_TOKEN>` |
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
změně odpovědi obchodu (`SourceResponseChanged`) i neplatném klíči Tesca. Částečné stažení (`partial`, R54)
se za úspěch nepočítá — když trvá 26 hodin, obchod je na `/health/imports` jako výpadek.

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

### Aktualizace z `c5d45d7` (2026-10-02, druhé nasazení — provedeno, `20ef035`)

Účet s avatarem, předvolby Mých slev, e-mailový souhrn, nové Hlídám, stránkování, úvodní
stránka, SEO a limity požadavků (R39–R45), nové obchody Globus (R46) a Billa (R48) a úpravy vzhledu (R47). `composer.lock` se nezměnil.

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
6. **Cron** ve WebAdminu přidej:
   - `30 6 * * *` → `https://slevohlidka.rhsoft.cz/cron/send-digests?token=<LETAKY_CRON_TOKEN>` (souhrny e-mailem),
   - `50 5,13 * * *` → `https://slevohlidka.rhsoft.cz/cron/import-offers?chain=globus&token=<LETAKY_CRON_TOKEN>`,
   - `0 6,14 * * *` → `https://slevohlidka.rhsoft.cz/cron/import-offers?chain=billa&token=<LETAKY_CRON_TOKEN>`,
   - **oba hned jednou zavolej ručně** — `/health/imports` jinak vrací 503, dokud nový obchod nemá
     úspěšné stažení. Billa trvá ~50 s (ověř, že se vejde do limitu požadavku, O8).
7. **Ověř** (kroky 1–8 v *7) Ověř*), navíc:
   - `version.txt?v=…` vrací commit balíčku (vypíše ho `build-upload.ps1` na konci),
   - úvodní stránka bez přihlášení, `/akce` bez přihlášení,
   - v účtu nahrání profilového obrázku (zapisuje do `storage/app/private/avatars`),
   - zapomenuté heslo → e-mail dorazí s českým předmětem,
   - `/cron/send-digests?token=…` vrací `Souhrny — odesláno: N`,
   - `/cron/import-offers?chain=globus&token=…` vrací `Globus — uloženo nabídek: ~650`, `chain=billa`
     `Billa — uloženo nabídek: ~3400`; ve výběru obchodu na `/akce` jsou oba s logem, v Mých obchodech
     karty Můj Globus a BILLA Klub.
8. **Search Console** a odeslání sitemap (*Vyhledávače*).
9. Zapiš verzi do *Nasazené verze* a datum ke skriptu v *Historii SQL skriptů*.

### Aktualizace z `20ef035` (třetí nasazení — provedeno, `ae88b48`)

Kaufland po prodejnách (R49). `composer.lock` se nezměnil.

1. **Záloha databáze**.
2. **SQL:** v phpMyAdminu spusť `deploy/migrations-2026-10-03-prodejny.sql` (tabulky `stores`, `offer_stores`, sloupec `followed_chains.store_codes`). Opakovatelný, stará verze kódu s ním běží dál.
3. **Nahraj `deploy/upload/`** jako minule: bez `vendor/`, ale s `vendor/composer/` a `bootstrap/cache/packages.php`; `public/build/` nejdřív smaž.
4. **Cron** přidej `45 4,12 * * *` → `https://slevohlidka.rhsoft.cz/cron/import-stores?chain=kaufland&token=<LETAKY_CRON_TOKEN>` a **hned ho zavolej ručně** (~1,5 min, odpověď `Kaufland — prodejen: 149, seznamů akcí: 149`), potom ručně i `/cron/import-offers?chain=kaufland&token=…` (~50 s, místo ~2 s — stahuje i stránky prodejen). Ověř, že se oba vejdou do limitu požadavku (O8).
5. **Ověř:** v Mých obchodech u Kauflandu výběr prodejen, na `/akce?chain=kaufland&q=K-Mistři` štítky „Jen …“ / „Jen v N prodejnách“.
6. Zapiš verzi do *Nasazené verze* a datum ke skriptu v *Historii SQL skriptů*.

### Aktualizace z `b2996c0` (šesté nasazení — provedeno, `10072aa`)

Opravy a funkce z revize (R54–R64). `composer.lock` se nezměnil.

1. **Záloha databáze**.
2. **SQL:** v phpMyAdminu spusť `deploy/migrations-2026-10-04-nakupni-seznam.sql` (tabulka `shopping_list_items`, R61). Opakovatelný, stará verze kódu s ním běží dál. Nový stav `partial` (R54) a četnost `instant` (R58) jsou jen hodnoty v textových sloupcích.
3. **Nahraj `deploy/upload/`** jako minule: bez `vendor/`, ale s `vendor/composer/` a `bootstrap/cache/packages.php`; `public/build/` nejdřív smaž.
4. **Cron souhrnu** změň z `30 6 * * *` na `30 6-22 * * *` (dávky po 100 uživatelích, okamžitá upozornění R58).
5. **Ověř:** `/cron/send-digests?token=…` vrací `Souhrny — odesláno: N`; v Účtu se při změně e-mailu objeví pole s heslem; v menu je Seznam a karta akce má „Do seznamu“; `/manifest.webmanifest` vrací JSON.
6. Zapiš verzi do *Nasazené verze* a datum ke skriptu v *Historii SQL skriptů*.

Po nasazení se ukázalo, že Albert od pátého nasazení padá s HTTP 400 (User-Agent s `https://`,
R65). Opraveno bez nasazení řádkem `LETAKY_USER_AGENT="Slevohlidka/1.0 (+slevohlidka.rhsoft.cz)"`
v `.env` na hostingu.

### Aktualizace z `10072aa` (sedmé nasazení — provedeno, `046d8eb`)

User-Agent bez `https://` v kódu (R65) a **aplikace v telefonu** (R66): service worker a offline
režim, spodní lišta, upozornění v telefonu. **Změnil se `composer.lock`** (`minishlink/web-push`
a jeho závislosti), přibyla složka `resources/pwa` a obrázky v `public/images/brand`.

1. **Záloha databáze** a v phpMyAdminu `migrations-2026-10-04-upozorneni-v-telefonu.sql` (tabulka
   `push_subscriptions`, `users.push_sent_at`) — **před** nahráním kódu.
2. Lokálně `docker compose exec app php artisan letaky:push-keys` a oba řádky vlož do `.env` na hostingu.
3. **Nahraj `deploy/upload/` celé včetně `vendor/`** (nové balíčky) a `bootstrap/cache/packages.php`;
   `public/build/` nejdřív smaž.
4. Řádek `LETAKY_USER_AGENT` v `.env` už není potřeba — výchozí hodnota v kódu je stejná; může zůstat.
5. **Ověř:** `version.txt?v=<cokoli>`, `/health/imports` (Albert OK po dalším stažení),
   `/sw.js` vrací JavaScript začínající `self.SW_CONFIG`, `/offline` stránku „Jste offline“;
   v telefonu: Můj účet → Upozornění → *Posílat upozornění na toto zařízení* a *Poslat zkušební
   upozornění* (na iPhonu až z aplikace přidané na plochu); `/cron/send-digests?token=…` vrací
   i řádek `Upozornění v telefonu — odesláno: N`.
6. Zapiš verzi do *Nasazené verze* a datum ke skriptu v *Historii SQL skriptů*.

### Aktualizace z `046d8eb` (osmé nasazení — provedeno, `25a224e`)

Hlavička na telefonu s celým logem a spodní lištou do šířky 799 px (R66), cenovka slevy na kartě
bez obrázku nepřekrývá název; **revize před spuštěním (R67–R69)**: adresy z `APP_URL`, přesměrování
`/public/…` a lomítka, odhlášení zařízení po změně hesla, hodinový limit e-mailů, SEO (titulky,
Albert `noindex`, `security.txt`), GA bez tokenů v adrese, právní texty s datem účinnosti;
**katalog rozšířený na 206 produktů (R70)** — jediný SQL skript jsou data katalogu, schéma se
nemění. `composer.lock` se nezměnil, žádné soubory nezmizely. **Změnil se kořenový
`.htaccess`** (`deploy/root-htaccess-fallback`) — ten balíček nenahrává.

1. **Nahraj `deploy/upload/`** bez `vendor/` (`composer.lock` je stejný); `public/build/` nejdřív smaž.
   Nový je `app/Listeners/`, změnily se `public/.htaccess`, `config/`, `lang/`, `resources/legal/`.
2. **Kořenový `.htaccess`:** nahraď na hostingu obsahem `deploy/root-htaccess-fallback` (přesměrování
   `/public/…`, výjimka pro `/.well-known/`).
3. **Google Analytics** (Správce → Datové streamy → web → Rozšířené měření → Zobrazení stránek →
   Rozšířená nastavení): **vypnout „Změny stránek na základě událostí historie prohlížeče“** — zobrazení
   stránek posílá aplikace sama s adresou bez tokenů (R69); se zapnutým by se měřilo dvakrát a s tokeny.
4. **Ověř:**
   - `version.txt?v=<cokoli>`
   - `/public/akce` → 301 na `/akce`, `/akce/` → 301 na `/akce` (ne `/public/akce`)
   - `curl -H "X-Forwarded-Prefix: /zly" https://slevohlidka.rhsoft.cz/akce` má canonical bez `/zly`
   - `/.well-known/security.txt` vrací text s `Contact:`
   - `/akce?chain=billa` má titulek „Aktuální akce Billy · Slevohlídka“ i po načtení stránky
     (záložka prohlížeče); `/akce?chain=albert` má `noindex, follow`
   - `/ochrana-udaju` ukazuje datum účinnosti; v GA Realtime se po „Přijmout“ objeví zobrazení stránky
   - na telefonu hlavička s logem a spodní lišta; na `/akce` karta bez obrázku se slevou
5. **Účty bez přijetí podmínek** (založené mezi prvním nasazením a R51, 2.–3. 10.) — v phpMyAdminu
   `SELECT id, email, created_at FROM users WHERE terms_accepted_at IS NULL;` Jsou-li mezi nimi cizí
   lidé, pošli jim podmínky e-mailem (souhlas se registrací nedali).
6. **Katalog (R70):** po záloze (*Záloha databáze*) pusť v phpMyAdminu
   `deploy/data-2026-10-04-katalog-rozsireni.sql` — kdykoli, kód na něm nezávisí. Ověř
   `SELECT COUNT(*) FROM products;` (206, víc jen s produkty přidanými v `/katalog`); akce se
   k novým produktům přiřadí při dalším stažení každého obchodu.
7. Zapiš verzi do *Nasazené verze* a datum ke skriptu v *Historii SQL skriptů*.

Po nasazení se ukázalo, že `/public/akce` dál vrací 200: u adres pod `public/` Apache bere
pravidla jen z `public/.htaccess`, přesměrování v kořenovém `.htaccess` se tam neuplatní.
Canonical už vede na `/akce` (adresy z `APP_URL`), přesměrování opravuje deváté nasazení.

### Aktualizace z `25a224e` (deváté nasazení — provedeno, `e7f942f`)

Jen `public/.htaccess`: přesměrování `/public/…` na adresu bez něj přesunuté z kořenového
`.htaccess` (R67). Bez SQL skriptu a bez změny kódu.

1. **Nahraj** `public/.htaccess` z repozitáře (v `deploy/upload/` je ještě starý ze `25a224e`).
   Kořenový `.htaccess` je možné nahradit novým `deploy/root-htaccess-fallback` (jen bez
   nefunkčních pravidel), není to nutné.
2. **Ověř:** `/public/akce` → 301 na `/akce`, `/public/akce?chain=lidl` → 301 na `/akce?chain=lidl`,
   `/public` → 301 na `/` (přes `/public/`, lomítko přidá Apache); `/akce` a `/.well-known/security.txt` dál 200.
3. Zapiš verzi do *Nasazené verze*.

**Každá nová migrace potřebuje SQL skript** `deploy/migrations-<datum>-<popis>.sql`
(opakovatelný: `CREATE TABLE IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`) včetně zápisu do
tabulky `migrations` — ve stejném commitu jako migrace. Nové produkty katalogu jdou na
produkci přidat ve správě katalogu, nebo skriptem `deploy/data-<datum>-katalog-<popis>.sql`
s `INSERT … ON DUPLICATE KEY UPDATE` podle jedinečného názvu a kategorií přes `source_id`
(jako `data-2026-10-04-katalog-rozsireni.sql`) — `REPLACE` podle `id` by přepsal produkty,
které admin na produkci přidal sám.

## Záloha databáze

Akce jdou kdykoli stáhnout znovu, nenahraditelné jsou **účty, nastavení a hlídané položky**
a ruční opravy katalogu. Před každým SQL skriptem a jinak aspoň jednou měsíčně:

1. phpMyAdmin → databáze → *Exportovat* → metoda *Vlastní*, formát SQL,
   komprese **gzip**, zaškrtnout *Přidat příkaz DROP TABLE*
2. Tabulky stačí `users`, `followed_chains`, `watch_items`, `products`, `categories`,
   `offer_product_exclusions`, `migrations` — akce a jejich přiřazení stáhne a dopočítá cron
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
