<!--
  Slevohlídka — historie nasazení na produkci
  @author Roman Hlaváček
  @created 2026-10-07
-->

# Historie nasazení

Poznámky k jednotlivým nasazením — co se při nich dělalo navíc proti běžnému postupu
v [DEPLOYMENT.md](DEPLOYMENT.md) (*Aktualizace už nasazené verze*). Nasazené verze a SQL skripty
jsou v tabulkách tamtéž. Přesunuto z DEPLOYMENT.md 7. 10. 2026 (R106).
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
   lidé, pošli jim podmínky e-mailem (souhlas se registrací nedali). Výsledek 4. 10. 2026: 0 účtů.
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

### Aktualizace z `e7f942f` (desáté nasazení — provedeno, `0068710`)

Hledání (R71): živé výsledky od začátku slova podle relevance, našeptávač s produkty, akcemi,
posledními a oblíbenými hledáními, oprava překlepu, „Jen slevy“; v Hlídám počty akcí u produktů,
náhled vlastních slov a „Vrátit“ v toastu. **Tón a kontakt (R72):** přátelské texty webu, právní
texty „my“ (datum účinnosti 2026-10-05), stránka `/kontakt`, v patičce sekce Kontakt. **České
adresy (R73):** `/prihlaseni`, `/registrace`, `/zapomenute-heslo`… (staré přesměrované 301), přepínač
„Ukazovat i akce jen pro e-shop“. **Centrum upozornění (R74):** zvonek v hlavičce, `/upozorneni`,
upozornění v telefonu vede na záznam, odpoledne „Zítra končí…“ s akcemi z nákupního seznamu, akce
nejlevnější za 12 týdnů se štítkem, zprávy od nás na `/zpravy` (admin, v menu pod avatarem); mobilní navigace se spodní lištou do 829 px. **SQL skript
`migrations-2026-10-05-centrum-upozorneni.sql`**, `composer.lock` se nezměnil, žádné soubory nezmizely.

1. **Záloha databáze** (viz *Záloha databáze*) a pak v phpMyAdminu
   `deploy/migrations-2026-10-05-centrum-upozorneni.sql` — **před** nahráním kódu.
2. **Nahraj `deploy/upload/`** bez `vendor/`; `public/build/` nejdřív smaž. Nové soubory jsou
   v `app/Domain/Offers/`, `app/Domain/Notifications/`, `app/Enums/`, `app/Http/Middleware/` a `app/Http/Controllers/`,
   změnily se `config/`, `lang/`, `routes/`, `resources/legal/privacy.md` (localStorage, centrum upozornění).
3. **Ověř:**
   - `version.txt?v=<cokoli>`
   - `/akce`: klepnutí do pole ukáže poslední hledání a „Teď nejvíc v akci“; „pizza“ přepočítá
     výsledky bez tlačítka a našeptá produkt Pizza s počtem akcí a akce s obrázkem; „pyzza“ ukáže
     „„pyzza“ nic nenašlo — výsledky jsou pro „pizza““; „Jen slevy“ zúží výpis
   - `/akce/naseptavac?q=pizza` vrací JSON s `products` a `offers`
   - Hlídám: „rum“ našeptá produkt s „N akcí · od …“ a u vlastních slov „Teď by našlo N akcí“;
     po přidání produktu toast s „Vrátit“, které položku zase odebere
   - na telefonu se hledání ve Všech akcích otevře přes celou obrazovku s tlačítkem Zpět
   - `/kontakt` ukazuje provozovatele, e-mail, telefon, „S čím se ozvat“ a časté otázky; v patičce
     sekce Kontakt s RHsoft.cz; `/podminky` „Účinné od 5. 10. 2026“ a „fyzická osoba zapsaná…“
   - `/neexistuje` ukáže „Tahle stránka nám utekla“
   - `/login` přesměruje na `/prihlaseni`, `/register?hlidat=1` na `/registrace?hlidat=1`;
     přihlášení, odhlášení, změna hesla v Mém účtu a „Zapomenuté heslo“ (odkaz v e-mailu vede na `/nove-heslo/…`) fungují
   - v hlavičce je zvonek, `/upozorneni` ukáže „Zatím je tu ticho“; cron `send-digests` vypíše
     „Centrum upozornění — zapsáno: 0“ (první běh jen začne počítat), po dalším stažení s novými akcemi
     přibude záznam a upozornění v telefonu po klepnutí otevře jeho detail; odpoledne (od 16:00)
     cron vypíše „Končící akce ze seznamu — zapsáno: N“
   - `/zpravy` (admin): zpráva o službě s „i do telefonu“ se objeví v centru všem a s dalším během
     cronu souhrnů přijde do telefonu
4. Zapiš verzi do *Nasazené verze*.

### Aktualizace z `0068710` (jedenácté nasazení — provedeno, `4cf9e35`)

**Akce, které ještě nezačaly (R76):** v Mých slevách sbalená sekce „Brzy“ (ve „Jsem v obchodě“ skrytá),
„Vyplatí se počkat“, štítek „Od čt 8. 10.“ s čárkovaným rámečkem, ve Všech akcích štítek „Brzy začnou“,
v nákupním seznamu „platí až od …“ s potvrzením odškrtnutí; upozornění s datem začátku a ráno „Od dneška
platí N akcí, na které čekáte“. **„Jen slevy“ ve Všech akcích zrušené (R77).** Bez SQL skriptu,
`composer.lock` se nezměnil, žádné soubory nezmizely, cron beze změny (nový záznam běží v `send-digests`).

1. **Nahraj `deploy/upload/`** bez `vendor/`; `public/build/` nejdřív smaž. Nové soubory:
   `app/Domain/Matching/WaitAdvice.php`, `app/Domain/Notifications/StartingTodayNotification.php`,
   `app/Domain/Notifications/Actions/RecordStartingOffers.php`, `app/Support/ShortDate.php`;
   změnily se `config/`, `lang/`, `resources/views/mail/`, `resources/legal/privacy.md` (centrum upozornění).
2. **Ověř:**
   - `version.txt?v=<cokoli>`
   - `/akce`: místo „Jen slevy“ je „Brzy začnou“ — ukáže jen akce s modrým štítkem „Od …“ (pokud
     některý obchod už zveřejnil příští leták, jinak prázdný výpis)
   - Moje slevy: pod skupinami sbalená sekce „Brzy“ s počtem a prvním začátkem, ve skupině „+ N brzy“;
     po výběru „Jsem v obchodě“ sekce zmizí
   - nákupní seznam: budoucí akce je za platnými s „platí až od …“, odškrtnutí se zeptá
   - cron `send-digests` vypíše řádek „Dnes začínající akce — zapsáno: N“ (záznamy vznikají od 7:00)
3. Zapiš verzi do *Nasazené verze*.

### Aktualizace z `d226ba4` (sedmnácté nasazení — provedeno, `034fa36`)

**Víc cen z letáků bez LLM:** leták Penny s novými pravidly parseru (R85, ~490 akcí místo ~300), PDF letáků
přes `pdftotext` (R86) — Lidl s akcemi ze zbytku potravinového letáku a **Albert poprvé s akcemi s cenou**
(R87; `mentions_only` zrušené, výpis Albertu se indexuje). Bez SQL skriptu, `composer.lock` se nezměnil,
žádné soubory nezmizely, cron beze změny. Hosting má `pdftotext` (viz *Předpoklady na hostingu*).

1. **Nahraj `deploy/upload/`** bez `vendor/`; `public/build/` nejdřív smaž. Nové soubory:
   `app/Domain/Sources/Pdf/` (celá složka), `app/Domain/Sources/Exceptions/PdfTextFailed.php`,
   `app/Domain/Sources/Lidl/LidlLeafletParser.php`, `app/Domain/Sources/Albert/AlbertLeafletParser.php`,
   `AlbertBox.php`, `AlbertTile.php`; změnily se `config/letaky.php`, zdroje Penny, Lidlu a Albertu,
   `ImportChainOffers`, `Chain`, `SeoMeta`, `LandingController`.
2. **Hned ručně zavolej stažení** a ověř, že se vejdou do limitu požadavku (O8; lokálně ~25–35 s každé):
   - `/cron/import-offers?chain=penny&token=…` → `Penny — uloženo nabídek: ~500`
   - `/cron/import-offers?chain=lidl&token=…` → `Lidl — uloženo nabídek: ~270` (2 PDF po ~28 MB)
   - `/cron/import-offers?chain=albert&token=…` → `Albert — uloženo nabídek: ~1 400` (až 6 PDF: letáky HM
     a SM tohoto a příštího týdne a katalog)

   Chyba „Text PDF letáku: …“ = `pdftotext` na hostingu nejde spustit; stažení obchodu skončí chybou
   a dosavadní akce zůstanou (nic se neoznačí jako stažené).
3. **Ověř:**
   - `version.txt?v=<cokoli>`, `/health/imports` vrací 200
   - `/akce?chain=albert` ukáže akce s cenou (dřív prázdné), hlavička stránky má `index, follow`
   - `/akce?chain=lidl&brzy=1` má akce od čtvrtka i mimo kampaně webu; odkaz akce z letáku vede na stránku letáku
4. Zapiš verzi do *Nasazené verze*.

### Aktualizace z `034fa36` / `60f0211` (osmnácté nasazení — provedeno, `7dbe429`)

**Akce, které ještě nezačaly, i u Globusu a Billy** z PDF letáků příštího týdne (R88, R89); obsahuje i opravu
letáku Penny se složkou `…_tl2` (`60f0211`), pokud ještě není nahraná. Když akce začne a vrátí ji API, převezme
řádek z PDF — upozornění nepřijde dvakrát. Bez SQL skriptu, `composer.lock` se nezměnil, cron beze změny.
Soubory `app/Domain/Sources/Albert/AlbertBox.php` a `AlbertTile.php` se přesunuly do `app/Domain/Sources/Pdf/`
(`PdfBox.php`, `PdfTile.php`) — **staré na hostingu smaž**.

1. **Nahraj `deploy/upload/`** bez `vendor/`; `public/build/` se nezměnil. Nové soubory v `app/Domain/Sources/Globus/`
   (`GlobusLeafletParser.php`, `GlobusLeafletKey.php`), `app/Domain/Sources/Billa/` (`BillaLeafletParser.php`,
   `BillaLeafletList.php`, `BillaCatalogMatcher.php`, `BillaCatalogProduct.php`, `BillaLeafletItem.php`) a
   `app/Domain/Sources/Pdf/` (`PdfBox.php`, `PdfTile.php`); změnily se `config/letaky.php`, `ImportChainOffers`,
   `OfferData` a zdroje Albertu, Globusu, Billy a Penny.
2. **Hned ručně zavolej stažení** (lokálně Globus ~26 s, Billa ~60 s, v úterý se 4 budoucími letáky ~90 s):
   - `/cron/import-offers?chain=globus&token=…` → `Globus — uloženo nabídek: ~800`
   - `/cron/import-offers?chain=billa&token=…` → `Billa — uloženo nabídek: ~3 600`
   - `/cron/import-offers?chain=penny&token=…` → `Penny — uloženo nabídek: ~900` (jen pokud `60f0211` ještě nebyl nahraný)
3. **Ověř:** `version.txt`, `/health/imports` vrací 200, `/akce?brzy=1&chain=globus` a `…&chain=billa` mají akce od středy.
4. Zapiš verzi do *Nasazené verze*.

### Aktualizace z `6d328eb` (dvacáté nasazení — provedeno, `2a4e112`)

**Přihlášení přes Google a Facebook** (R96). Nový balíček `laravel/socialite` (s `league/oauth1-client`,
`firebase/php-jwt`, `phpseclib/phpseclib`) — **nahraj i `vendor/`**. Cron beze změny.

1. **SQL skript před nahráním kódu:** v phpMyAdminu pusť `migrations-2026-10-06-prihlaseni-pres-google.sql`
   (tabulka `social_accounts`, `users.password` nepovinné). Stará verze kódu s ním běží dál.
2. **`.env` na hostingu:** doplň `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `FACEBOOK_CLIENT_ID`,
   `FACEBOOK_CLIENT_SECRET` (kapitola *Přihlášení přes Google, Seznam a Facebook*). Bez nich se tlačítka jen neukážou.
3. **Nahraj `deploy/upload/`** včetně `vendor/` a `public/build/`; nové jsou `public/images/social/`,
   `app/Domain/Account/Social/`, `app/Domain/Account/Actions/` (`SetUpNewAccount`, `ResolveSocialLogin`,
   `RegisterSocialUser`, `LinkSocialAccount`, `UnlinkSocialAccount`), `app/Domain/Account/AuthShowcase.php`,
   `IdentityConfirmation.php`, `app/Enums/SocialProvider.php`, `app/Models/SocialAccount.php`,
   `app/Rules/ConfirmedIdentity.php`, `app/Http/Controllers/Social*Controller.php`; změnily se mj. `config/services.php`,
   `config/letaky.php`, `routes/web.php`, `lang/cs/app.php`, `resources/legal/privacy.md`,
   `resources/views/seo/content.blade.php`, `resources/views/app.blade.php` a `public/theme-init.js`
   (obsah bez JavaScriptu je vidět — ověření značky u Googlu).
4. **Ověř:**
   - `version.txt`;
   - na `/prihlaseni` jsou tlačítka Google a Facebook;
   - přihlášení přes Google vede na *Dokončení registrace* (nový účet) nebo rovnou do aplikace (propojený);
   - v Mém účtu → Zabezpečení jde propojit a odpojit;
   - z aplikace na ploše iPhonu se po přihlášení přes poskytovatele vrátíš přihlášený.
5. Facebook přepni na *Live*, až Meta dovolí (ověření firmy); do té doby se přihlásí jen lidé s rolí v aplikaci.
6. **Google — znovu odeslat ověření značky** (*Google Auth Platform → Verification Center*). Předtím ověř
   `https://slevohlidka.cz/ochrana-udaju` (část *Přihlášení přes Google nebo Facebook* s Limited Use) a že
   úvodní stránka bez JavaScriptu ukazuje „Co Slevohlídka umí“ a odkazy na zásady a podmínky. Doména musí být
   ověřená v Search Console pod účtem, který je vlastníkem projektu v Google Cloud.
7. Zapiš verzi do *Nasazené verze*.

### Aktualizace z `7b56244` (dvacáté druhé nasazení — provedeno, `6d0e140`)

**Přihlášení přes Seznam** (R98). Bez SQL skriptu a bez nového balíčku (`vendor/` se nemění), cron beze změny.

1. **Služba u Seznamu** podle kapitoly *Přihlášení přes Google, Seznam a Facebook* (adresa návratu
   `https://slevohlidka.cz/prihlaseni/seznam/navrat`).
2. **`.env` na hostingu:** doplň `SEZNAM_CLIENT_ID` a `SEZNAM_CLIENT_SECRET`. Bez nich se tlačítko neukáže.
3. **Nahraj `deploy/upload/`** bez `vendor/`, s `public/build/`; nové jsou `app/Domain/Account/Social/SeznamProvider.php`
   a `public/images/social/seznam.svg`; změnily se `app/Enums/SocialProvider.php`, `app/Providers/AppServiceProvider.php`,
   `app/Domain/Account/Social/SocialLogin.php`, `app/Providers/FortifyServiceProvider.php`,
   `app/Http/Controllers/AccountController.php`, `config/services.php`, `lang/cs/app.php` a `resources/legal/privacy.md`.
4. **Ověř:**
   - `version.txt`;
   - na `/prihlaseni` je mezi Googlem a Facebookem „Přihlásit přes Seznam“ s červeným „eskem“ (v tmavém režimu bílým);
   - přihlášení novým účtem Seznamu vede na *Dokončení registrace* a přijde ověřovací e-mail;
   - v Mém účtu → Zabezpečení jde Seznam propojit a odpojit.
5. Zapiš verzi do *Nasazené verze*.

### Aktualizace z `6d0e140` (dvacáté třetí nasazení — provedeno, `16b4832`)

**Přístupnost, SEO a zobrazení** (R99). Bez SQL skriptu a bez `vendor/`, cron i `.env` beze změny.

1. **Nahraj `deploy/upload/`** bez `vendor/`, s `public/build/`, `public/.htaccess` (nový otisk CSP a přesměrování `/index.php`)
   a `public/version.txt`; nové jsou `app/Support/InlineScript.php` a `app/Support/Seo/StructuredData.php`.
2. **Ověř:** `version.txt`; otisk vloženého skriptu v HTML sedí s `sha256-…` v hlavičce CSP (jinak se vzhled
   nepřepne a stránka problikne); `/index.php` vrací 301 na `/`; `/akce/pivo` a `/kontakt` v Google Rich Results Test.
3. Zapiš verzi do *Nasazené verze*.

### Aktualizace z `16b4832` (dvacáté čtvrté nasazení — provedeno, `9324560`)

**Řazení a filtry výpisů (R100–R102), Microsoft Clarity (R103), historie v náhledu vlastních slov (R104)**
a drobnosti vzhledu. Bez SQL skriptu a bez `vendor/` (`composer.lock` beze změny), cron i `.env` beze změny,
žádný soubor nezmizel.

1. **Nahraj `deploy/upload/`** bez `vendor/`, s `bootstrap/cache/packages.php`, `public/build/`,
   `public/.htaccess` (CSP pro Clarity) a `public/version.txt`.
2. **V projektu Clarity** nastav *Settings → Masking → Strict* — slibují to zásady (R103).
3. **Ověř:** `version.txt`; lišta cookies se ukáže znovu (verze souhlasu 2); po souhlasu s analytickými cookies
   jde v síti požadavek na `www.clarity.ms` a v konzoli není chyba CSP.
4. Zapiš verzi do *Nasazené verze*.

### Aktualizace z `9324560` (dvacáté páté nasazení — provedeno, `2f60395`)

**IndexNow** (R105). Bez SQL skriptu, bez `vendor/` a bez `public/build/`, cron i `.env` beze změny.

1. **Nahraj** `app/Domain/Offers/Actions/ImportChainOffers.php`, nové `app/Domain/Offers/ChangedOfferPages.php`
   a `app/Support/Seo/IndexNow.php`, `app/Http/Controllers/CrawlerFilesController.php`, `config/letaky.php`,
   `routes/web.php` a `public/version.txt`.
2. **Ověř:** `https://slevohlidka.cz/<klíč>.txt` vrací samotný klíč, jiný název 404; po nejbližším cronu
   stažení jsou v Bing Webmaster Tools → *IndexNow* ohlášené adresy (jinak hledej `IndexNow:` v logu).
3. Zapiš verzi do *Nasazené verze*.

### Aktualizace z `2f60395` (dvacáté šesté nasazení — provedeno, `b75955a`)

**Kritická revize (R106):** hranice nových akcí při souběhu se stažením, časový rozpočet cronu
souhrnů, seznam prodejen až po otevření okna, „Načíst další“ jen s novou stránkou, sdílené
kontroly parserů letáků. **Ovoce, zelenina a maso na kg z letáků Albertu a Penny (R107):**
dlaždice 1 kg / 1 ks se sedící slevou, zmínky s vyloučenými slovy v okolí slova a bez prošlých
akcí. Bez SQL skriptu, bez `vendor/` (`composer.lock` beze změny), cron i `.env` beze změny
(`letaky.mentions.exclude_window_words` má výchozí hodnotu v `config/letaky.php`), žádný soubor
nezmizel.

1. **Nahraj `deploy/upload/`** bez `vendor/`, s `bootstrap/cache/packages.php`, `public/build/`
   a `public/version.txt`.
2. **Ověř:** `version.txt`; ve Všech akcích „Načíst další“ připojí akce bez skoku stránky
   (v síti jen jedna stránka akcí); u akce Kauflandu „Jen …“ otevře seznam prodejen; ruční
   `/cron/send-digests?token=…` doběhne s počty u všech pěti kroků. Po ručním
   `/cron/import-offers?chain=albert&token=…` a `chain=penny` jsou ve Všech akcích pod
   `/akce/banany` akce Albertu a Penny z letáku (pokud je leták nese).
3. Zapiš verzi do *Nasazené verze* a tuhle sekci přesuň do `HISTORIE_NASAZENI.md`.

### Aktualizace z `b75955a` (dvacáté sedmé nasazení — provedeno, `7e90785`)

Fotky Billy a Penny v menší variantě CDN (R108), odkaz akce Kauflandu na detail akce (R109),
popis shodný s balením se na kartě neopakuje, „Do letáku“ u odkazu na stránku letáku (R110),
akce ve schema.org bez vnořeného `Product` (R111). Bez SQL skriptu, bez `vendor/`, cron i `.env`
beze změny. Nahrané ručně po souborech včetně `public/build/`, `config/letaky.php` a `lang/cs/app.php`;
`public/version.txt` zůstal `b75955a`.

- **Ověřeno:** Test rozšířených výsledků Googlu na `/akce` bez produktových úryvků (dřív 50 neplatných),
  jen navigační struktura, místní firmy a organizace.

### Aktualizace z `7e90785` (dvacáté osmé nasazení — provedeno, `1b23d0c`)

„+1 brzy“ v sekci Zatím bez akce otevře akce položky v sekci Brzy, v aplikaci z plochy stažení
stránky dolů načte data znovu (R112). **Audit technického dluhu (R113) a úklid (R114):** data
z letáků přes Nový rok, zámek cronu upozornění, zrušení účtu i s centrem upozornění, jeden výpočet
slevy, rozdělený import a sdílené části parserů PDF, frontend po komponentách. **Hlídání úloh cronu
`/health/tasks` (R115).** Mění se backend (`app/`, `config/letaky.php`, `lang/`, `routes/`,
`resources/views/`) i frontend; **dva SQL skripty**; `vendor/` beze změny (`composer.lock` stejný),
`.env` a cron beze změny; **jeden soubor zmizel**.

1. **Záloha databáze** (*Záloha databáze* níže).
2. **SQL** v phpMyAdminu, oba opakovatelné a stará verze kódu s nimi běží:
   `migrations-2026-10-09-indexy.sql` (indexy pro rostoucí historii, R113) a
   `migrations-2026-10-09-hlidani-uloh.sql` (tabulka `task_heartbeats`, R115).
3. **Nahraj `deploy/upload/`** bez `vendor/`, ale **s `vendor/composer/`** (optimalizovaný autoloader
   zná nové třídy) a s `bootstrap/cache/packages.php`, `public/build/` (starý obsah nejdřív smaž)
   a `public/version.txt`.
4. **Smaž na hostingu** `app/Domain/Sources/ImportFreshness.php` (nahradil ho `ScrapeRun::lastFinishedAt`).
5. **Zavolej ručně** `/cron/prune-sessions?token=…`, `/cron/import-categories?token=…`
   a `/cron/send-digests?token=…` — jinak `/health/tasks` hlásí „nikdy“ do jejich cronu
   (kategorie až 1. 11.). Úklid poprvé vyprázdní `raw` akcí skončených před 60 dny (R113).
6. **Ověř:**
   - `version.txt`; `/health/imports` i `/health/tasks` vrací 200 (prodejny Kauflandu OK
     po ranním nebo poledním `import-stores`),
   - našeptávač hledání, okna Filtry a Seřadit na telefonu, potvrzovací dialog (např. odebrání
     hlídané položky), sekce Mého účtu s navigací, Všechny akce s „Načíst další“,
   - v Mých slevách „+1 brzy“ v sekci Zatím bez akce rozbalí sekci Brzy; v aplikaci z plochy
     tah dolů na začátku stránky vysune kruh se šipkou a stránku načte znovu.
7. **UptimeRobot:** druhý monitor na `/health/tasks` (*Monitoring: hlídání stahování*).
8. Zapiš verzi do *Nasazené verze*, u obou SQL skriptů datum v *Historie SQL skriptů* a tuhle sekci
   přesuň do `HISTORIE_NASAZENI.md`.

- **Ověřeno:** `version.txt` = `1b23d0c`; `/health/imports` i `/health/tasks` vrací 200, všechny úlohy OK
  po ručním zavolání cronů (9. 10. 17:05).

### Aktualizace z `1b23d0c` (dvacáté deváté nasazení — provedeno, `ff76607`)

Přilepené lišty na telefonu: sekce Mého účtu a kapitoly podmínek a zásad (R116), hlavička
rozbalené skupiny v Mých slevách (R117), plovoucí ikony hledání a filtrů ve Všech akcích (R119) a v Mých slevách (R120);
název obchodu klepnutím na logo a výraznější Hlídat / Do seznamu na kartě (R118). Podmínky a zásady
bez indexace vyhledávači a mimo sitemap (R121), `lastmod` v sitemapě podle skutečné změny stránky (R122).
Mění se frontend a čtyři soubory backendu
(`app/Support/Seo/PublicPages.php`, `app/Support/Seo/SeoMeta.php`, `app/Http/Controllers/CrawlerFilesController.php`,
nový `app/Domain/Offers/OfferPageChanges.php`; nová třída = i `vendor/composer/` kvůli autoloaderu)
— bez SQL skriptu, z `vendor/` jen `composer/`, `config/`, `lang/` i `.env` beze změny, žádný
soubor nezmizel.

1. **Nahraj** `public/build/` (celý, starý obsah můžeš smazat), čtyři soubory z `app/` výše, `vendor/composer/` a `public/version.txt`
   z `deploy/upload/`.
2. **Ověř na telefonu:** `version.txt`; v Mém účtu a na `/podminky` lišta sekcí pod hlavičkou
   dojíždí k aktivní sekci a nadpis po klepnutí nezajede pod ni; v Mých slevách u rozbalené
   položky přilepená hlavička na jeden řádek, klepnutí ji sbalí; ve Všech akcích po odskrolování
   plovoucí ikony, lupa otevře hledání bez posunu stránky; v Mých slevách plovoucí ikony a přilepená
   hlavička skupiny pod nimi, ikona obchodu otevře okno s obchody; klepnutí na logo obchodu na kartě
   ukáže název. `/podminky` a `/ochrana-udaju` mají `noindex, follow` a v `/sitemap.xml` nejsou;
   v `/sitemap.xml` mají obchody a produkty různá data `lastmod` (ne všechny stejný čas).
   V Google Search Console *Kontrola URL* → `https://slevohlidka.cz/` → *Požádat o indexování* (Google
   má u úvodní stránky ještě titulek staré stránky Brzy).
3. Zapiš verzi do *Nasazené verze* a tuhle sekci přesuň do `HISTORIE_NASAZENI.md`.

- **Ověřeno:** `version.txt` = `ff76607`; `/health/imports` i `/health/tasks` 200; `/podminky` a `/ochrana-udaju`
  `noindex, follow`, kontakt `index, follow`; sitemap 216 adres bez právních stránek, 25 různých `lastmod`.

### Aktualizace z `ff76607` (třicáté nasazení — provedeno, `b3ad7d2`)

Bez posunu rozvržení po načtení — přednačtená písma a loga obchodů s rozměry; přihlášení
a registrace s vlastním titulkem a nadpisem i bez JavaScriptu (R123); tlačítko Seznamu v jednobarevné
variantě jako Google a Facebook (R124, jen `public/build/`). `.env` beze změny, žádný soubor
nezmizel; `lang/cs/app.php` se změnil — verze Inertie se změní a otevřené
stránky se načtou znovu.

Navíc „Tohle ne“ a hlášení chyb v akcích (R125) — **s SQL skriptem** — a upozornění adminům na hlášení a výpadky v centru upozornění (R126, bez SQL skriptu).

0. **Záloha databáze** a v phpMyAdminu `deploy/migrations-2026-10-09-tohle-ne.sql` (před nahráním kódu).
1. **Nahraj** z `deploy/upload/`: `public/build/` (celý, starý obsah můžeš smazat),
   `app/` (R125 mění a přidává soubory v `Domain/Catalog`, `Domain/Matching`, `Enums`,
   `Http/Controllers`, `Http/Requests`, `Http/Middleware`, `Models`, `Providers` — nejjednodušší celou
   složku), `vendor/composer/` (nové třídy v autoloaderu), `config/letaky.php`, `routes/web.php`,
   `database/migrations/`, `lang/cs/app.php`, `resources/legal/privacy.md`, `resources/views/app.blade.php`,
   `resources/views/seo/content.blade.php` a `public/version.txt`.
2. **Ověř:** `version.txt`; ve zdroji úvodní stránky dva `<link rel="preload" as="font">`;
   `/registrace` má titulek „Registrace · Slevohlídka“ a v obsahu bez JS `<h1>`; loga obchodů
   mají `width` a `height`; „Přihlásit přes Seznam“ tmavé jako ostatní tlačítka. Za pár dní DebugBear
   nebo PageSpeed: CLS pod 0,1. R125: v Mých slevách „Tohle ne“ u akce skryje akci (toast
   s „Vrátit“), u položky ikona oka s počtem; tři tečky na kartě ve Všech akcích → hlášení;
   admin v menu **Hlášení** (`/hlaseni`) hlášení vidí a vyřeší; s upozorněními zapnutými v Můj účet mu nové hlášení (i vlastní) přijde do centra upozornění (zvonek) i do telefonu. R126: po nejbližším cronu upozornění je na `/health/tasks` řádek „Upozornění adminům na výpadek — OK“; při výpadku přijde adminovi záznam „Výpadek: …“.
3. Zapiš verzi do *Nasazené verze*, SQL skript do *Historie SQL skriptů* (datum) a tuhle sekci
   přesuň do `HISTORIE_NASAZENI.md`.

- **Ověřeno:** `version.txt` = `b3ad7d2`; `/health/imports` i `/health/tasks` 200 (10. 10.).

### Aktualizace z `b3ad7d2` (třicáté první nasazení — provedeno, `3d36522`)

Veřejná stránka Nejlepší slevy týdne (R128): `/tyden` přesměruje na aktuální týden `/tyden/2026-41`,
žebříček slev napříč obchody, nejlepší slevy po obchodech a archiv týdnů; v sitemapě, `llms.txt`,
obsahu bez JS a v IndexNow. Mění se `app/` (nové třídy v `Domain/Offers` a `Http/Controllers`,
proto i `vendor/composer/`), `config/letaky.php`, `routes/web.php`, `lang/cs/app.php`,
`resources/views/crawlers/llms.blade.php`, `resources/views/seo/content.blade.php`
a `seo/offers.blade.php`, `public/build/` — bez SQL skriptu, `.env` beze změny.

- **Ověřeno:** `version.txt` = `3d36522`; `/tyden` 302 na `/tyden/2026-41`, stránka 200 (0,35 s),
  `index, follow`, titulek „Nejlepší slevy 41. týdne 2026 · Slevohlídka“, žebříček a sekce všech
  7 obchodů; v sitemapě 41. týden s `lastmod` poslední změny akcí týdne a 40. týden s koncem týdne
  (`2026-10-04T22:00:00+00:00`); `llms.txt` s odkazem; `/health/imports` i `/health/tasks` 200.

### Aktualizace z `3d36522` (třicáté druhé nasazení — provedeno, `1ad9cbd`)

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

- **Ověřeno:** `version.txt` = `1ad9cbd`; `/health/imports` i `/health/tasks` 200; `/kvalita-dat`
  nepřihlášeného přesměruje na přihlášení; neplatný odkaz `/seznam/s/…` 404; `robots.txt` zakazuje
  `/kvalita-dat` (10. 10.).
