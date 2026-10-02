# Agregátor letáků

Webová aplikace, která hlídá akční nabídky z letáků obchodů **Kaufland, Tesco,
Albert, Lidl a Penny**. Uživatel si vybere prodejny a hlídané položky (konkrétní
produkt nebo kategorii) a vidí, kde a za kolik jsou ve slevě. Osobní projekt,
zatím jen pro vlastní použití.

## Dokumentace

Před prací na projektu si přečti:

- **[docs/PLAN.md](docs/PLAN.md)**: zadání, obchody, datový model, toky dat, etapy, otevřené otázky, log rozhodnutí
- **[docs/ZDROJE_DAT.md](docs/ZDROJE_DAT.md)**: jak se stahují data jednotlivých obchodů (endpointy, struktura odpovědí, pasti)
- **[docs/CODING_GUIDELINES.md](docs/CODING_GUIDELINES.md)**: závazná pravidla pro psaní kódu
- **[docs/TODO.md](docs/TODO.md)**: odložené úkoly a nápady

Všechno je závazné. Když se rozhodnutí změní, **aktualizuj příslušný dokument
ve stejném commitu** jako kód. Nové rozhodnutí patří do logu v PLAN.md (další
volné číslo R…). Změna chování obchodu (nový endpoint, jiné pole) patří do ZDROJE_DAT.md.

## Stav

Hotové jsou etapy 1–3: kostra aplikace s účty (Fortify, R12, R13), stahování
nabídek Kauflandu a Tesca (R15–R17), přehled všech akcí na `/akce` a hlídání:
Moje obchody (`/obchody`), Hlídám (`/hlidam`) a Moje slevy (`/`, R18, R19).
Další je etapa 4: Lidl a Penny (viz PLAN.md, kap. 6).

Vývojový uživatel ze seederu: `test@example.com` / `password`
(`docker compose exec app php artisan db:seed`). E-maily (obnova hesla) se lokálně
jen zapisují do `storage/logs/laravel.log`.

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
docker compose exec app php artisan letaky:import-offers kaufland   # jen vybrané
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
MariaDB 11.4. **Není tam SSH ani composer** — nic z `php artisan` se na produkci nespustí.
Nasazení zatím neproběhlo (etapa 7). Už teď z toho plyne:

- **Žádná fronta, scheduler ani démon** — nic nesmí implementovat `ShouldQueue`.
  Stahování bude spouštět cron WebAdminu voláním URL s tokenem; každá úloha je Action
  volatelná z artisan příkazu i z kontroleru.
- **Každá migrace bude potřebovat SQL skript** `deploy/migrations-<datum>-<popis>.sql`
  (od prvního nasazení; první skript pokryje všechny dosavadní migrace).
- **Dlouhé požadavky:** stažení Tesca trvá ~45 s; limit hostingu se ověří při nasazení (O8).
- Nepřidávej závislost, kterou hosting nemá (Redis, fronta, binárky jako `pdftotext`).

## Stack

PHP 8.4 · Laravel 13 · Inertia 3 · Vue 3 · SCSS (BEM + CSS tokeny) · Fortify ·
MariaDB 11.4 · Pest 4 · Larastan · Pint. Extrakce letáků (etapa 6): Claude API.

## Nejčastější zdroje chyb v tomhle projektu

1. **Ceny jsou v haléřích jako `int`** (R7). „29,90“, `29.9` i `2990` (Penny) projdou sdíleným parserem, nikdy `(int) ($x * 100)`.
2. **Platnost je místní datum.** Tesco a Albert posílají UTC (`2026-09-29T22:00Z` = 30. 9.), Lidl unix timestamp. Převod na `Europe/Prague` proběhne ve zdroji, před uložením.
3. **Akce ≠ sleva** (R8). Kaufland `smallPrice`/`specialItems` („AKCE! pouze“), Tesco „Super cena“, Penny „Jedinečná nabídka“, Lidl „Ceny v klidu“ a „Ušetřete %“ (úspora na ceně za jednotku) nejsou slevy oproti původní ceně.
4. **Tesco Clubcard: cena s kartou je jen v textu `description`.** `afterDiscount` je u Clubcard akcí běžná cena.
5. **Cena s kartou nebo aplikací má všech 5 obchodů**, vždy jako `loyalty_price` vedle běžné ceny, nikdy místo ní.
6. **„Různé druhy“ / „vybrané druhy“** neříká, jestli akce platí i na konkrétní variantu. Párování má stav **možná** (R9), ne shodu.
7. **Duplicity:** Kaufland má stejnou položku ve více kategoriích (dedup podle `klNr` a platnosti), Lidl a Penny mají položku na webu i v letáku, Tesco v letáku i v e-shopu.
8. **Varianty nabídky:** Albert a Tesco mají odlišné letáky pro hypermarket a supermarket, Kaufland se mírně liší po prodejnách (cookie `x-aem-variant`). Nabídka bez prodejny nebo formátu platí pro celý obchod.
9. **Uvnitř letáku se liší platnost.** Víkendové akce (pá–ne) a „Start týdne“ mají kratší platnost než leták. Brát platnost položky, ne letáku.
10. **Neveřejná API se mění bez varování.** Neočekávaný tvar odpovědi = `SourceResponseChanged`, nula položek = `SourceReturnedNoOffers`, obojí skončí v `scrape_runs`. Oprava začíná porovnáním s ZDROJE_DAT.md a novou fixture.
11. **Testy nesahají na síť** (R11). Fixtures jsou zkrácené skutečné odpovědi v `tests/Fixtures/<obchod>/` (popis v `tests/Fixtures/README.md`), nevymýšlet vlastní tvar dat.
12. **Nepoužívat zakázané a chráněné zdroje:** Lidl search API (robots.txt), Tesco `protectedLeaflets` (`UNAUTHENTICATED`), www.kaufland.cz (marketplace za Cloudflare; prodejny jsou na `prodejny.kaufland.cz`).
13. **Staré letáky mizí** (Penny, Albert, Lidl). Nic se nemaže, nabídky jsou historie (R10).
14. **Tesco API klíč je v konfiguraci**, ne v kódu. Je veřejný (`mangoApiKey` v HTML e-shopu), ale může se změnit.
15. **Zdroj musí vracet celou nabídku obchodu najednou.** Neskončené nabídky, které v novém stažení chybí, se označí jako stažené obchodem (`withdrawn_at`, R16). Zdroj, který by stáhl jen část (jedna stránka, jeden leták), by zbytek nabídky „stáhl“. Výpisy nabídek filtrují `->active()->notExpired()`.
16. **Tesco zboží na váhu:** cena je `afterDiscount` za kg, ne `price.actual` (cena odhadovaného kusu). Leták a e-shop se párují podle posledních 8 číslic ID (R17).
17. **Hromadný zápis nabídek (`upsert`) obchází přetypování modelu** — enumy jako `->value`, JSON přes `json_encode`, data jako `Y-m-d` (`ImportChainOffers::row`).
18. **V testech je helper `responseFixture()`**, ne `fixture()` — tu má Pest vlastní.
19. **Párování hlídaných položek hledá začátek slova** v textu normalizovaném `TextNormalizer` (bez diakritiky, interpunkce = mezera). `WatchItemMatcher` a předvýběr kandidátů v `MyOffers` musí hledat ve stejných sloupcích (`name`, `brand`, `description`). Nový obrat pro „různé druhy“ patří do `VariantNote`, jinak varianta nedá stav „možná“.
20. **Akce jen s kartou se v Mých slevách ukáže jen uživateli s tou kartou** (R19) — `LoyaltyProgram::chain()` páruje kartu s obchodem; nový obchod s kartou ji tam potřebuje.
21. **Šablony hlídaných položek** jsou v `config/letaky.php` (`watch.templates`), jejich názvy v `lang` (`ui.watch.templates`) — test hlídá, že žádný nechybí.

## Jazyk

| Co | Jazyk |
|---|---|
| Identifikátory v kódu: třídy, metody, sloupce, tabulky | **anglicky** (`Offer`, `loyalty_price`) |
| Komentáře, PHPDoc, dokumentace, commit zprávy | **česky** |
| Uživatelské texty | **česky, vždy přes `lang/cs/app.php`**, ve Vue přes `useTranslations()` |

Commity mají předmět „Oblast: co se změnilo" a tělo s tím, co a proč. Viz
guidelines, sekce 8. Repozitář je osobní (`R0mul0s`), autor commitů
`Roman Hlaváček <romanhlavacek91@gmail.com>`.
