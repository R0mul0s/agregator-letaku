# Slevohlídka (agregátor letáků)

Webová aplikace **Slevohlídka** („Rychlý lovec slev“, R34), která hlídá akční nabídky z letáků obchodů **Kaufland, Tesco,
Albert, Lidl, Penny, Globus a Billa**. Uživatel si vybere obchody a hlídané položky (konkrétní
produkt nebo kategorii) a vidí, kde a za kolik jsou ve slevě. Osobní projekt
Romana Hlaváčka (IČO), připravuje se zveřejnění pro cizí uživatele (R51, [docs/ZVEREJNENI.md](docs/ZVEREJNENI.md)).

## Dokumentace

Před prací na projektu si přečti:

- **[docs/PLAN.md](docs/PLAN.md)**: zadání, obchody, datový model, toky dat, etapy, otevřené otázky, log rozhodnutí
- **[docs/ZDROJE_DAT.md](docs/ZDROJE_DAT.md)**: jak se stahují data jednotlivých obchodů (endpointy, struktura odpovědí, pasti)
- **[docs/CODING_GUIDELINES.md](docs/CODING_GUIDELINES.md)**: závazná pravidla pro psaní kódu
- **[docs/TODO.md](docs/TODO.md)**: odložené úkoly a nápady
- **[docs/ZVEREJNENI.md](docs/ZVEREJNENI.md)**: checklist zveřejnění (právní, organizační a technické body)

Všechno je závazné. Když se rozhodnutí změní, **aktualizuj příslušný dokument
ve stejném commitu** jako kód. Nové rozhodnutí patří do logu v PLAN.md (další
volné číslo R…). Změna chování obchodu (nový endpoint, jiné pole) patří do ZDROJE_DAT.md.

## Stav

Hotové jsou etapy 1–5g, zveřejnění (8), opravy a funkce z kritické revize (9) a aplikace v telefonu (10) (PLAN.md, kap. 6):
- **Stahování:** Kaufland (i po 149 prodejnách, R49), Tesco, Lidl, Penny (R15–R17, R25, R26), Globus (R46),
  Billa z celého katalogu (R48); zmínky v letácích bez ceny — Lidl, Penny, Albert (R27, R36; Albert jen zmínky).
  Pojistky importu: nula akcí je chyba, podezřelý propad akce nestáhne (stav `partial`), zámek proti
  souběžnému stažení, prodlužování pokračujících akcí Billy (R54, R57); User-Agent bez `https://` (R65)
- **Hlídání a Moje slevy (`/`):** Hlídám (`/hlidam`, produkt z katalogu nebo vlastní slova, R39, R47),
  katalog 206 produktů se stromem Tesca a tabulkou pro admina (`/katalog`, R24, R28–R31, R37, R70), sbalitelné
  skupiny (R43), „Jsem v obchodě“ s kompaktními řádky (R55, R62), „Je to opravdu sleva?“ (R59)
- **Všechny akce (`/akce`):** veřejné, našeptávač, výběr obchodu s logy, stránkování (R43, R44), „Hlídat“
  přímo z karty (R60); **nákupní seznam** (`/seznam`, R61)
- **Účet:** Fortify (R12), menu pod avatarem (R40), Můj účet jako sekce s ukládáním hned (R63), Moje obchody
  (`/obchody`) s ukládáním hned (R64), nový účet sleduje všechny obchody a jde do Hlídám (R55), registrace
  a přihlášení se skutečnými akcemi a heslem jen jednou (R56), české adresy `/prihlaseni`, `/registrace`… (R73)
- **E-maily:** upozornění na nové akce hned / denně / týdně, po dávkách (R42, R54, R58)
- **Aplikace v telefonu (R66):** manifest se zkratkami, úvodní obrazovky iPhonu, spodní lišta záložek, výzva
  k přidání na plochu; service worker s offline režimem (Moje slevy, seznam, Hlídám), odškrtávání bez signálu,
  nezhasínání displeje a poslání seznamu; upozornění v telefonu (web push) z cronu souhrnů
- **Vzhled a přívětivost:** název Slevohlídka a vzhled podle loga (R34, R35), loga obchodů (R32), toasty
  a vlastní potvrzovací okno (R47), oslovení v 5. pádě, plovoucí hlavička, manifest pro plochu telefonu (R55)
- **Zveřejnění:** podmínky a zásady (`/podminky`, `/ochrana-udaju`), souhlasy, ověření e-mailu, odhlášení
  z e-mailů jedním klepnutím, české chybové stránky (R51), cookie lišta a GA4 po souhlasu (R52), ochrana
  registrace a účtů (R53, R54), SEO a limity požadavků (R45); revize před spuštěním — adresy z `APP_URL`,
  odhlášení zařízení po změně hesla, limit e-mailů za hodinu, `security.txt`, GA bez tokenů v adrese (R67–R69)
- **Hledání (R71):** živé výsledky od začátku slova podle relevance, našeptávač s produkty, akcemi, posledními
  a oblíbenými hledáními, oprava překlepu, „Jen slevy“; v Hlídám počty akcí u produktů a náhled vlastních slov
- **Tón a kontakt (R72):** web mluví přátelsky „my“ s jemným humorem, právní texty jako firma (genderově
  neutrálně), stránka `/kontakt` s rozcestníkem a častými otázkami, v patičce sekce Kontakt (RHsoft.cz)

Produkce běží na `https://slevohlidka.rhsoft.cz` (nasazeno 2026-10-02, naposledy `e7f942f` 2026-10-04);
postup aktualizace a nasazené verze jsou v `deploy/DEPLOYMENT.md`. Sleduje se 7 obchodů; Makro
zatím nejde (ochrana proti robotům). Etapa 6 (LLM) jen když bude potřeba.
Co z dřívějších rozhodnutí platí a co ne, je v tabulce na začátku PLAN.md.

Vývojový uživatel ze seederu: `test@example.com` / `password` (admin katalogu; seeder
založí i výchozí produkty)
(`docker compose exec app php artisan db:seed`). E-maily (obnova hesla, souhrn akcí) lokálně
zachytává Mailpit (http://localhost:54723).

## Prostředí

Projekt běží výhradně v Dockeru. PHP, Composer ani Node se lokálně neinstalují.

```bash
docker compose up -d
```

| Služba | Adresa |
|---|---|
| Aplikace | http://localhost:54720 |
| MariaDB | localhost:54721 (`agregator` / `agregator`), testy v `agregator_test` |
| Vite dev server | http://localhost:54722 (jen při `npm run dev`) |
| Mailpit | http://localhost:54723 — odchozí e-maily (obnova hesla, souhrn R42) |

Porty nekolidují s Počasím (54710–54712) ani s Píchačkami (54687–54690).

Artisan, composer i npm se pouštějí v kontejneru:
```bash
docker compose exec app php artisan migrate
docker compose exec app composer install
docker compose exec app npm run build   # produkční build assetů
docker compose exec app npm run dev     # watch s HMR
```

Stažení nabídek od obchodů (skutečné požadavky, šetrně s pauzami; Tesco
potřebuje `TESCO_API_KEY` v `.env`, viz ZDROJE_DAT.md):
```bash
docker compose exec app php artisan letaky:import-offers            # všechny obchody se zdrojem
docker compose exec app php artisan letaky:import-offers kaufland   # jen vybrané (kaufland, tesco, albert, lidl, penny, globus, billa)
docker compose exec app php artisan letaky:import-stores kaufland     # prodejny Kauflandu a jejich akce (R49), před import-offers
docker compose exec app php artisan letaky:import-categories        # strom kategorií katalogu (Tesco, R28)
docker compose exec app php artisan letaky:admin email@example.com  # správa katalogu /katalog (R29), --revoke odebere
docker compose exec app php artisan letaky:send-digests             # e-mailové souhrny nových akcí (R42), do Mailpitu
docker compose exec app php artisan letaky:prune-sessions           # úklid vypršelých relací a odkazů na obnovu hesla (R53)
docker compose exec app php artisan letaky:push-keys                # klíče VAPID pro upozornění v telefonu (R66), jednou do .env
```
Výsledek každého stažení je v tabulce `scrape_runs`.

## Kontrola kvality

**Pusť ručně a musí projít všechno**, než se něco commitne:
```bash
docker compose exec app ./vendor/bin/pest
docker compose exec app ./vendor/bin/pint
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app npm run build   # při změně JS, Vue nebo SCSS
```

Testy běží proti MariaDB `agregator_test`, ne SQLite, a **nikdy nesahají na síť** (R11).

## Produkce

Sdílený hosting **Websupport** (R20), stejně jako Počasí: Apache 2.4 + PHP 8.4,
MariaDB 11.4, `https://slevohlidka.rhsoft.cz`. **Není tam SSH ani composer** — nic
z `php artisan` se na produkci nespustí. Nasazeno 2026-10-02 (R38) — postup aktualizace a nasazené verze v **[deploy/DEPLOYMENT.md](deploy/DEPLOYMENT.md)**:

```powershell
powershell -ExecutionPolicy Bypass -File deploy\build-upload.ps1   # jen z commitnutého stavu
```

- **Žádná fronta, scheduler ani démon** — nic nesmí implementovat `ShouldQueue`.
  Stahování spouští cron WebAdminu: `/cron/import-offers?chain=…&token=…` po obchodech
  a `/cron/import-categories?token=…`, prodejny Kauflandu `/cron/import-stores?chain=kaufland&token=…` (R49), souhrny a upozornění v telefonu `/cron/send-digests?token=…` (R66), úklid `/cron/prune-sessions?token=…` (R53) (`CronController`, token `LETAKY_CRON_TOKEN`,
  bez tokenu 404). Každá úloha je Action volatelná z artisan příkazu i z kontroleru.
- **`/health/imports`** vrací 503, když obchod nemá úspěšné stažení za 26 h (UptimeRobot).
- **Každá migrace potřebuje SQL skript** `deploy/migrations-<datum>-<popis>.sql`
  (opakovatelný, včetně zápisu do `migrations`) ve stejném commitu jako migrace a řádek
  v tabulce *Historie SQL skriptů* v DEPLOYMENT.md. Výchozí schéma je
  `migrations-2026-10-02-init.sql`, katalog `data-2026-10-02-katalog.sql`.
- **Bezpečnostní hlavičky a HTTPS** jsou v `public/.htaccess` (CSP: skripty jen vlastní,
  obrázky `https:` kvůli CDN obchodů). Nový externí zdroj ve stránce = úprava CSP.
- **Dlouhé požadavky:** stažení Tesca trvá ~45 s; limit hostingu se ověří při nasazení (O8).
- Nepřidávej závislost, kterou hosting nemá (Redis, fronta, binárky jako `pdftotext`).
- **Service worker** (`/sw.js`) je route, zdroj `resources/pwa/service-worker.js` jde do balíčku (`build-upload.ps1`);
  klíče VAPID (`LETAKY_VAPID_*`) jsou v `.env` na hostingu a nemění se (R66).

## Stack

PHP 8.4 · Laravel 13 · Inertia 3 · Vue 3 · SCSS (BEM + CSS tokeny) · Fortify ·
MariaDB 11.4 · Pest 4 · Larastan · Pint. Extrakce letáků (etapa 6): Claude API.

## Nejčastější zdroje chyb v tomhle projektu

1. **Ceny jsou v haléřích jako `int`** (R7). „29,90“, `29.9` i `2990` (Penny) projdou sdíleným parserem, nikdy `(int) ($x * 100)`.
2. **Platnost je místní datum.** Tesco a Albert posílají UTC (`2026-09-29T22:00Z` = 30. 9.), Lidl unix timestamp. Převod na `Europe/Prague` proběhne ve zdroji, před uložením.
3. **Akce ≠ sleva** (R8). Kaufland `smallPrice`/`specialItems` („AKCE! pouze“), Tesco „Super cena“, Penny „Jedinečná nabídka“, Lidl „Ceny v klidu“ a „Ušetřete %“ (úspora na ceně za jednotku) nejsou slevy oproti původní ceně.
4. **Tesco Clubcard: cena s kartou je jen v textu `description`.** `afterDiscount` je u Clubcard akcí běžná cena.
5. **Cena s kartou nebo aplikací mají všechny obchody**, vždy jako `loyalty_price` vedle běžné ceny, nikdy místo ní.
6. **„Různé druhy“ / „vybrané druhy“** neříká, jestli akce platí i na konkrétní variantu. Párování má stav **možná** (R9), ne shodu.
7. **Duplicity:** Kaufland má stejnou položku ve více kategoriích (dedup podle `klNr` a platnosti), Lidl a Penny mají položku na webu i v letáku, Tesco v letáku i v e-shopu.
8. **Varianty nabídky:** Albert a Tesco mají odlišné letáky pro hypermarket a supermarket, Kaufland se mírně liší po prodejnách (cookie `x-aem-variant`, R49 — viz 35). Nabídka bez prodejny nebo formátu platí pro celý obchod.
9. **Uvnitř letáku se liší platnost.** Víkendové akce (pá–ne) a „Start týdne“ mají kratší platnost než leták. Brát platnost položky, ne letáku.
10. **Neveřejná API se mění bez varování.** User-Agent stahování bez `https://` (Albert by ho poslal přes prerender pro roboty a GraphQL vrátí 400, R65). Neočekávaný tvar odpovědi = `SourceResponseChanged`, nula položek = `SourceReturnedNoOffers`, obojí skončí v `scrape_runs`. Oprava začíná porovnáním s ZDROJE_DAT.md a novou fixture.
11. **Testy nesahají na síť** (R11). Fixtures jsou zkrácené skutečné odpovědi v `tests/Fixtures/<obchod>/` (popis v `tests/Fixtures/README.md`), nevymýšlet vlastní tvar dat.
12. **Nepoužívat zakázané a chráněné zdroje:** Lidl search API (robots.txt), Tesco `protectedLeaflets` (`UNAUTHENTICATED`), www.kaufland.cz (marketplace za Cloudflare; prodejny jsou na `prodejny.kaufland.cz`).
13. **Staré letáky mizí** (Penny, Albert, Lidl). Nic se nemaže, nabídky jsou historie (R10).
14. **Tesco API klíč je v konfiguraci**, ne v kódu. Je veřejný (`mangoApiKey` v HTML e-shopu), ale může se změnit.
15. **Zdroj musí vracet celou nabídku obchodu najednou.** Neskončené nabídky, které v novém stažení chybí, se označí jako stažené obchodem (`withdrawn_at`, R16). Zdroj, který by stáhl jen část (jedna stránka, jeden leták), by zbytek nabídky „stáhl“. Pojistka (R54): chybí-li víc než `letaky.import.max_withdrawn_share` neskončených akcí, neoznačí se nic a stažení má stav `partial` (pro `/health/imports` neúspěch). Nula akcí je chyba i se stránkami letáku — výjimku má jen obchod s `mentions_only` (Albert). Stažení obchodu běží jen jedno najednou (zámek v cache, R57). Výpisy nabídek filtrují `->active()->notExpired()`.
16. **Tesco zboží na váhu:** cena je `afterDiscount` za kg, ne `price.actual` (cena odhadovaného kusu). Leták a e-shop se párují podle posledních 8 číslic ID (R17).
17. **Hromadný zápis nabídek (`upsert`) obchází přetypování modelu** — enumy jako `->value`, JSON přes `json_encode`, data jako `Y-m-d` (`ImportChainOffers::row`).
18. **V testech je helper `responseFixture()`**, ne `fixture()` — tu má Pest vlastní.
19. **Párování hlídaných položek hledá začátek slova** v textu normalizovaném `TextNormalizer` (bez diakritiky, interpunkce = mezera). `WatchItemMatcher` a předvýběr kandidátů v `MyOffers` musí hledat ve stejných sloupcích (`name`, `brand`, `description`). Normalizace mění interpunkci na mezeru („K-Mistři“ → „k mistri“), v databázi ale zůstává — předvýběr LIKE proto hledá nejdelší část slova (`OfferPrefilter::likePattern`), a to u nejdelšího slova pravidla (`WatchRule::prefilterTerm`, R54; hledaná slova validuje `SearchableKeywords`). Více pravidel nad jednou nabídkou = `WatchItemMatcher::prepare` jednou a `matchPrepared`. Nový obrat pro „různé druhy“ patří do `VariantNote`, jinak varianta nedá stav „možná“.
20. **Akce jen s kartou se v Mých slevách ukáže jen uživateli s tou kartou** (R19) — `LoyaltyProgram::chain()` páruje kartu s obchodem; nový obchod s kartou ji tam potřebuje.
21. **Šablony hlídaných položek už nejsou** (R31) — nahradil je katalog; výchozí produkty zakládá `CatalogSeeder`. Hlídaná položka z katalogu má `keywords` null a pravidla bere z produktu (`WatchRule::fromWatchItem` potřebuje načtenou relaci `product`). Při smazání produktu se jeho pravidla zkopírují do položek, které ho hlídají.
22. **Leták Penny: glyfy fontu** — `Ǻ` = „,90“, U+E00A U+E009 = „90“, červené U+E00F/E010/E011 = přeškrtávací čára (ne číslice). Přiřazení ceny k dlaždici musí projít kontrolou ceny za jednotku (R26); pravidla neuvolňovat bez porovnání výsledku na celém letáku.
23. **Pauza mezi požadavky je podle zdroje** (`request_delay_ms` u Lidlu a Penny, jinak `letaky.http`); stránka letáku bez textové vrstvy vrací 404 — `SourceHttp::request(allowNotFound: true)`. V testech musí být nulová i pauza zdroje (`LETAKY_LIDL_…`, `LETAKY_PENNY_REQUEST_DELAY_MS` v `phpunit.xml`), jinak test spí.
24. **Zmínky v letácích bez ceny (R27)** se párují jinak než akce: `WatchItemMatcher::mention` hledá **celá slova** a **bez vyloučených slov** (stránka je směs produktů). Stránky s receptem vyřadí fráze `letaky.mentions.excluded_page_phrases`. Zmínka se nezobrazí, když má obchod k položce ve stejném období akci s cenou. Text stránek je v `leaflet_pages` (Lidl `keyWords` + `altText`, Penny text SVG, Albert `text` ze `spreads.json` Publitas); zdroj je přidává do `SourceBatch::$pages`. Zdroj jen se stránkami (Albert) nabídky mít nemusí — `SourceReturnedNoOffers` padá až při prázdnu ve všem.
25. **Katalog produktů (R28–R30):** přiřazení nabídek k produktům (`offer_product`) přepočítává `AssignProducts` — po importu obchodu (`forChain`, uvnitř transakce importu) a po uložení produktu (`forProduct`). Ruční řádky (`is_manual`) a vyřazení (`offer_product_exclusions`) přepočet nesmí změnit. Pravidla produktu = pravidla hlídané položky (`WatchRule::fromProduct`, `WatchItemMatcher::matchText` nad jednou normalizovaným textem). Předvýběr v SQL jen přes `OfferPrefilter` (stejné sloupce jako `WatchItemMatcher::offerText`). Kategorie se nemažou, `source_id` je zakódovaná cesta názvů u Tesca.
26. **Vzhled (R34, R35):** barvy loga jsou tokeny `--color-brand` / `--color-brand-dark` jen pro název v hlavičce; tlačítka a odkazy mají ztmavený akcent kvůli kontrastu (WCAG AA). Loga obchodů jsou v `public/images/chains` a zobrazují se přes `ChainLogo` / `ChainWatermark` ze sdílených dat `chainInfo` — sdílený prop se nesmí jmenovat stejně jako prop stránky (`chains` na Všech akcích ho přepsal). Prázdný stav = `EmptyState` s maskotem. Ikony a logo jsou vygenerované z `resources/brand/slevohlidka-logo.png`.
27. **Pravidla katalogu jsou začátky slov** — krátká slova chytají i jiná („rum“ → „Rump steak“, „sůl“ → „sultánky“). Nový produkt v `database/seeders/data/catalog-products.php` vždy ověřit na ostrých akcích a doplnit vyloučená slova.
28. **Profilový obrázek (R40)** je na disku `local`, ne v `public` — na hostingu nejde `storage:link`. Posílá ho `AvatarController`, ořez a zmenšení dělá prohlížeč (`resources/js/lib/image.js`), server jen validuje. V testech `pngOfSize()` místo `UploadedFile::fake()->image()` (kontejner nemá GD).
29. **E-maily (R42)** mají vlastní téma `resources/views/vendor/mail/html/themes/slevohlidka.css` (barvy webu natvrdo — e-mailové klienty neumí CSS proměnné, Laravel styly vkládá inline). Ceny v e-mailu neformátovat přes `Number`/Intl — kontejner má ICU jen s angličtinou („CZK 39.90“). Veřejná vlastnost mailable přepíše stejnojmennou proměnnou šablony. Lokálně vše zachytí Mailpit.
30. **SEO a roboti (R45):** aplikace je SPA bez SSR — titulek, popis, canonical, `robots`, OG a schema.org skládá `App\Support\Seo\SeoMeta` v `app.blade.php` na serveru. Nová veřejná stránka = doplnit ji do `SeoMeta` (jinak dostane `noindex, nofollow`), do sitemap v `CrawlerFilesController` a případně do `llms.txt`. `robots.txt` je routa, ne soubor v `public/` (statický by routu přebil) a mimo produkci zakáže vše. Indexovaná stránka bere ve Vue titulek ze sdíleného `seoTitle` (`<Head :title="page.props.seoTitle">`), jinak ho `<Head>` po načtení přepíše a Google vidí ten (R68). OG obrázek se kreslí z `resources/brand/og-image.html` (postup v hlavičce souboru).
31. **Za proxy Websupportu** platí `trustProxies(at: '*')` — IP klienta a https z X-Forwarded-*, **bez `X-Forwarded-Prefix`** (proxy ho propouští od klienta, R67). Absolutní adresy jsou vždy z `APP_URL` (`URL::forceRootUrl`) — na produkci běží kořenový `.htaccess` a adresa požadavku může nést `/public`; canonical přes `url()->current()`, ne `$request->url()`. Limity požadavků jsou pojmenované v `App\Support\RateLimits` (`letaky.rate_limits`); měnící požadavky počítá globálně skupina web, citlivé formuláře (heslo, e-mail) mají přísnější limit — nový takový formulář patří do `SENSITIVE_ROUTES`, a pokud posílá e-mail, i do `MAIL_ROUTES` (hodinový limit, R67).
32. **Globus (R46):** ceny jsou **float v Kč** (`PriceParser::fromFloat`), akce je jen typ ceny `VKA0`, popis se bere z položky letáku podle EAN (popis katalogu je reklamní text, hlídání by chytalo cizí slova). Zboží na váhu nemá `sellUnitSizeText` — balení z `unitAmount` + `unitId`. Oblečení a obuv vyřazuje `excluded_ware_groups`. Akce nemají vlastní odkaz (detaily `…/p/` zakazuje robots.txt pro stahování a adresa není ověřená).
33. **Zpětná vazba (R47):** uložení potvrzuje **toast** — kontroler vrátí `->with('status', self::STATUS_…)` a text je v `lang/cs/app.php` `ui.toast.messages.<kód>` (test v `TranslationsTest`); nepiš zprávy do obsahu stránky. Nevratnou akci potvrzuje `await confirmDialog({ title, message, confirmLabel })` z `resources/js/lib/confirm.js`, nikdy `window.confirm`. Oslovení jménem jde přes `App\Support\CzechVocative` (5. pád). Nastavení (Můj účet, Moje obchody) se ukládá hned po změně bez tlačítka (R63, R64) — tlačítko mají jen formuláře, kde se píše; stejný toast se neopakuje. Vysvětlivka štítku nesmí být jen v `title` (na dotykovém displeji se neukáže) — štítek jako tlačítko s `InfoIcon` a textem pod ním (R55). Nový účet sleduje všechny obchody (`CreateNewUser`, R55) — nový obchod se zdrojem dostanou jen noví uživatelé, stávající si ho zapnou v Mých obchodech.
34. **Billa (R48):** API nemá platnost akcí — platnost je akční týden st–út obsahující dnešek (`week_start_iso_day`), dřívější konec řeší R16. Akce pokračující se stejnou cenou prodlouží svůj řádek (`extends_continuing_offers`, R54) — klíč nabídky obsahuje platnost, bez toho by byla každý týden „nová“. Stahuje se **celý katalog**, ne `inPromotion` (ten nevrací akce jen s BILLA Klubem). U `weightPieceArticle` je `value` cena odhadovaného kusu — bere se `perStandardizedQuantity`. Akce na množství má běžnou cenu kusu a výhodnou v `promotion_text`.
35. **Kaufland po prodejnách (R49):** akce bez řádků v `offer_stores` platí ve **všech** prodejnách — řádky má jen akce s omezením. Seznamy akcí prodejen (`stores.offer_keys`) plní `letaky:import-stores` / `/cron/import-stores` a import nabídek je čte (`StoreOfferLists`, jen mladší 36 h); bez nich stáhne jen výchozí nabídku. Nový dotaz na akce pro uživatele musí brát `followed_chains.store_codes` (`Offer::availableInStores`, v `MyOffers::whereFollowed`) a načíst `->with('stores')`, jinak `OfferPresenter` štítek prodejen vynechá. Kódy prodejen jsou jedinečné napříč obchody (`User::selectedStoreCodes`).
36. **Krmivo pro zvířata (R50):** `WatchItemMatcher` vynechá krmivo (`PetFood::isPetOffer` — kategorie obchodu nebo slova a značky z `letaky.pet_food`) u hlídání, které není o zvířatech (`PetFood::isPetRule`). Nový obchod s kategorií krmiva → doplnit ji do `letaky.pet_food.categories`; nové slovo ověřit na všech akcích (dvojznačná: „podestýlk“, „dog“). `AssignProducts` musí načítat `source_category`.
37. **Zveřejnění (R51):** údaje provozovatele jsou v `letaky.operator` — do textů se doplňují (`{operator}`, `{company_id}`, `{trade_office}`… v `resources/legal/*.md`), nikdy se nepíšou natvrdo. Texty webu i právní texty mluví za provozovatele „my“ a genderově neutrálně (R72, tón v CODING_GUIDELINES kap. 7). Kapitoly jsou nadpisy `##` — z nich vzniká obsah stránky a id pro odkazy (`/ochrana-udaju#5-cookies-a-uloziste-v-prohlizeci`), přejmenování nadpisu změní odkaz. Podstatná změna podmínek = zvýšit `letaky.legal.terms_version`, změna textu souhlasu s obchodními sděleními = `marketing_consent_version`. **Každý e-mail jen na ověřenou adresu** (`whereNotNull('email_verified_at')`) a hromadný s odhlášením jedním klepnutím (`MailingSubscriptions::unsubscribeUrl` + hlavičky jako `DigestMail::headers`); obchodní sdělení jen uživatelům s `hasMarketingConsent()`. Nová cookie, localStorage nebo příjemce údajů = upravit `resources/legal/privacy.md` (tabulky cookies podle kategorií). Chybové stránky jsou Blade (`resources/views/errors/page.blade.php`), nový kód s vlastní šablonou Laravelu potřebuje vlastní soubor `errors/<kód>.blade.php`, jinak vyhraje anglická.
38. **Cookies a Google Analytics (R52):** GA4 se načte **jen na produkci a až po souhlasu** s analytickými cookies (`resources/js/lib/consent.js`, sdílený prop `cookieConsent`, ID v `letaky.cookie_consent`). Nic, co ukládá cookies nebo posílá data třetí straně (pixel, reklamní síť, mapa, video), se nesmí načíst před souhlasem — patří do kategorie v `CookieConsent.vue` a za `consentState`. Nový nástroj nebo kategorie = zvýšit `letaky.cookie_consent.version` (všichni se vyberou znovu), doplnit CSP v `public/.htaccess` a tabulku v `resources/legal/privacy.md`. Inline skript CSP nedovolí. Zobrazení stránek posílá `consent.js` sám po každém přechodu Inertie s adresou bez tokenů — nová stránka s tokenem nebo e-mailem v adrese patří do `letaky.cookie_consent.redacted_paths`; měření změn historie v GA musí zůstat vypnuté (R69). Kořen aplikace má třídu `app-root` (přidá `app.js`) — nestylovat `body > div`, chytá i prvky rozšíření prohlížeče.
39. **Ochrana účtů (R53):** registrace bez captchy — skryté pole a podepsaný čas načtení (`RegistrationGuard`); test registrace musí poslat data z `registrationInput()` (`tests/Pest.php`) (token „vyplněný“ před 30 s), jinak skončí chybou `bot_check`. Hesla kontroluje Have I Been Pwned přes `Password::defaults()` — v testech vypnuto (`LETAKY_PASSWORD_UNCOMPROMISED=false` v `phpunit.xml`), test úniku musí volat `Http::fake`. Přihlášení má dva limity (e-mail + IP a samotná IP). Websupport pustí **300 e-mailů za hodinu ze schránky** — hromadné e-maily po dávkách jako souhrny (`letaky.digest.users_per_run`, cron každou hodinu 6:30–22:30, R54; okamžité upozornění R58 bere jen uživatele, od jejichž souhrnu doběhlo stažení). Změna e-mailu chce současné heslo (R54). Změna hesla odhlásí ostatní zařízení, obnova všechna (R67). Souhlas s obchodními sděleními platí, jen když je `marketing_consent_at` novější než `marketing_consent_withdrawn_at` — odvolání čas udělení nemaže (R69).
40. **Aplikace v telefonu (R66):** service worker `resources/pwa/service-worker.js` **není v buildu Vite** — server ho posílá na `/sw.js` s nastavením (`ServiceWorkerController`: verze, soubory z `public/build/manifest.json`); s dev serverem Vite (HMR) se neregistruje, offline a push se zkouší na `npm run build`. Stránky dostupné offline jsou v `letaky.pwa.offline_paths` — uložené HTML i JSON Inertie obsahují data uživatele, po odhlášení je maže `clearOfflineData` (`lib/pwa.js`, název cache `slevohlidka-pages` musí sedět se service workerem). Emulace offline v DevTools se service workeru netýká — ověřovat se zastaveným nginx. Upozornění v telefonu: adresu odběru posílá prohlížeč, server na ni posílá požadavky — jen domény z `letaky.push.allowed_hosts` (`PushServiceEndpoint`, jinak SSRF); odeslání přes `PushSender` (v testech podvržený, `WebPushSender` testovat s `MockHandler` Guzzle). Na iPhonu push jen v aplikaci z plochy. Hlavní položky navigace jsou pod 800 px ve spodní liště (`tab` ve sdílené navigaci; hlavička a lišta mají vlastní breakpoint `$breakpoint-nav`, mixiny `from-nav` / `below-nav`, ne md) — prvek přilepený ke spodnímu okraji musí přičíst `--tab-bar-offset`. Obrázky aplikace (maskovatelná ikona, silueta upozornění, úvodní obrazovky iPhonu) kreslí `resources/brand/app-images.html` v headless Edge.
41. **Hledání (R71):** výsledky (`OfferSearch`) i našeptávač (`SearchSuggestions`) hledají slovo jako **začátek slova** přes `WordStart` a řadí podle relevance (`OfferSearch::relevance`: název → značka → popis). Raw SQL musí být pro Larastan `literal-string` — skládat řetězcem z konstant, ne `implode`. JSON dotaz volaný při psaní (fetch) patří za middleware `ReadOnlySession` — jinak při souběhu s uložením formuláře přepíše relaci a toast i „Vrátit“ zmizí. Oprava překlepu (`SearchVocabulary`) drží slovník v cache (`search.vocabulary`) — v testech `Cache::flush()`. Pole `type="search"` Escapem vymaže sám prohlížeč — v našeptávači `preventDefault`. Nový localStorage (poslední hledání) se maže jen při odhlášení, ne při startu nepřihlášeného (`lib/pwa.js`).

## Jazyk

| Co | Jazyk |
|---|---|
| Identifikátory v kódu: třídy, metody, sloupce, tabulky | **anglicky** (`Offer`, `loyalty_price`) |
| Komentáře, PHPDoc, dokumentace, commit zprávy | **česky** |
| Uživatelské texty | **česky, vždy přes `lang/cs/app.php`**, ve Vue přes `useTranslations()` |

Commity mají předmět „Oblast: co se změnilo" a tělo s tím, co a proč. Viz
guidelines, sekce 8. Repozitář je osobní (`R0mul0s`), autor commitů
`Roman Hlaváček <romanhlavacek91@gmail.com>`.
