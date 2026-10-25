# Slevohlídka (agregátor letáků)

Webová aplikace **Slevohlídka** („Rychlý lovec slev“, R34), která hlídá akční nabídky z letáků obchodů **Kaufland, Tesco,
Albert, Lidl, Penny, Globus a Billa**. Uživatel si vybere obchody a hlídané položky (konkrétní
produkt nebo kategorii) a vidí, kde a za kolik jsou ve slevě. Osobní projekt
Romana Hlaváčka (IČO), připravuje se zveřejnění pro cizí uživatele (R51, [docs/ZVEREJNENI.md](docs/ZVEREJNENI.md)).

## Dokumentace

Před prací na projektu si přečti:

- **[docs/PLAN.md](docs/PLAN.md)**: zadání, obchody, datový model, toky dat, etapy, otevřené otázky; na začátku tabulka *Co platí a co ne*
- **[docs/ROZHODNUTI.md](docs/ROZHODNUTI.md)**: log rozhodnutí R1–R… (historie — co platí dnes, říká tabulka v PLAN.md)
- **[docs/ZDROJE_DAT.md](docs/ZDROJE_DAT.md)**: jak se stahují data jednotlivých obchodů (endpointy, struktura odpovědí, pasti)
- **[docs/CODING_GUIDELINES.md](docs/CODING_GUIDELINES.md)**: závazná pravidla pro psaní kódu
- **[docs/PASTI.md](docs/PASTI.md)**: pasti podle oblastí — co se snadno pokazí a kde je to ošetřené (čti kapitolu oblasti, na které pracuješ)
- **[docs/TODO.md](docs/TODO.md)**: odložené úkoly a nápady
- **[docs/ZVEREJNENI.md](docs/ZVEREJNENI.md)**: checklist zveřejnění (právní, organizační a technické body)

Všechno je závazné. Když se rozhodnutí změní, **aktualizuj příslušný dokument
ve stejném commitu** jako kód. Nové rozhodnutí patří do logu v ROZHODNUTI.md (další
volné číslo R…) a jeho výsledek do tabulky *Co platí a co ne* v PLAN.md. Změna chování
obchodu (nový endpoint, jiné pole) patří do ZDROJE_DAT.md.

## Stav

Hotové etapy a funkce jsou v PLAN.md (tabulka *Co platí a co ne*, kap. 6 Etapy). Mapa stránek:

| Stránka | Adresa | Hlavní rozhodnutí |
|---|---|---|
| Úvodní stránka pro nepřihlášené / Moje slevy | `/` | R44, R90, R137 / R18, R43, R55, R76, R100–R102, R117, R118, R120, R125, R138, R140, R145, R153 |
| Všechny akce (veřejné), obchod a produkt čistou adresou, jejich kanál RSS | `/akce`, `/akce/lidl`, `/akce/pivo`, `/akce/lidl/rss` | R44, R71, R82, R94, R100–R102, R118, R119, R141, R149 |
| Nejlepší slevy týdne (veřejné) s archivem | `/tyden` → `/tyden/2026-41` | R128, R143 |
| Hlídám, katalog (admin), nákupní seznam (i sdílený odkazem) | `/hlidam`, `/katalog`, `/seznam`, `/seznam/s/{token}` | R39, R47, R144, R148, R152 / R24, R28–R31 / R61, R130, R146, R148 |
| Centrum upozornění, zprávy od nás (admin), uživatelé (admin), hlášení (admin), kvalita dat (admin) | `/upozorneni`, `/zpravy`, `/uzivatele`, `/hlaseni`, `/kvalita-dat` | R74, R84, R125, R129 |
| Můj účet, Moje obchody | `/ucet`, `/obchody` | R40, R63, R64, R96, R98, R116 |
| Průvodce prvním nastavením nového účtu | `/vitejte?krok=1` | R153 |
| Přihlášení, registrace, kontakt, právní texty | `/prihlaseni`, `/registrace`, `/kontakt`, `/podminky`, `/ochrana-udaju` | R51, R72, R73, R116, R121, R123 |

Produkce běží na `https://slevohlidka.cz` (nasazeno 2026-10-02, přestěhováno ze `slevohlidka.rhsoft.cz` R93, naposledy `9023bf7` 2026-10-25);
postup aktualizace a nasazené verze jsou v `deploy/DEPLOYMENT.md`, poznámky k dřívějším nasazením
v `deploy/HISTORIE_NASAZENI.md`. Sleduje se 7 obchodů; Makro zatím nejde (ochrana proti robotům).
Etapa 6 (LLM) jen když bude potřeba.

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
docker compose exec app npm run lint    # ESLint (R113), při změně JS nebo Vue
```

Testy běží proti MariaDB `agregator_test`, ne SQLite, a **nikdy nesahají na síť** (R11).

## Produkce

Sdílený hosting **Websupport** (R20): Apache 2.4 + PHP 8.4 FPM, MariaDB 11.4, `https://slevohlidka.cz`
(stará `slevohlidka.rhsoft.cz` přesměrovává, R93). **Není tam SSH ani composer** — nic z `php artisan`
se na produkci nespustí. Postup aktualizace a nasazené verze v **[deploy/DEPLOYMENT.md](deploy/DEPLOYMENT.md)**:

```powershell
powershell -ExecutionPolicy Bypass -File deploy\build-upload.ps1   # jen z commitnutého stavu
```

- **Žádná fronta, scheduler ani démon** — nic nesmí implementovat `ShouldQueue`. Úlohy spouští cron
  WebAdminu přes URL `/cron/…?token=…` (`CronController`, token `LETAKY_CRON_TOKEN`, bez tokenu 404):
  `import-offers?chain=…` po obchodech, `import-stores?chain=kaufland` (R49), `import-categories`,
  `send-digests` (souhrny a upozornění, R66), `prune-sessions` (R53). Každá úloha je Action volatelná
  z artisan příkazu i z kontroleru; nová úloha patří i do `/health/tasks` ([PASTI.md](docs/PASTI.md) bod 53).
- **Každá migrace potřebuje SQL skript** `deploy/migrations-<datum>-<popis>.sql` (opakovatelný,
  včetně zápisu do `migrations`) ve stejném commitu jako migrace a řádek v tabulce *Historie SQL
  skriptů* v DEPLOYMENT.md.
- **Nepřidávej závislost, kterou hosting nemá** (Redis, fronta). Ověřeno 2026-10-06 (R86):
  `memory_limit` 512M, `max_execution_time` 600, `proc_open`, `/usr/bin/pdftotext` 22.02 a `gs`,
  GD i Imagick (kontejner GD nemá). Jiný program nebo rozšíření nejdřív ověř jednorázovým
  diagnostickým skriptem (nahrát přes FTP, přečíst, smazat). Stažení Tesca trvá ~45 s (limit O8).
- **CSP a hlavičky** jsou v `public/.htaccess` — nový externí zdroj ve stránce = úprava CSP;
  `public/theme-init.js` má v CSP otisk (PASTI.md bod 54). Service worker `/sw.js` je routa (bod 55).
- **Chyby aplikace** chodí adminům do centra upozornění a telefonu (R154, PASTI.md bod 56); obchod jde vypnout
  proměnnou `LETAKY_DISABLED_CHAINS` v `.env` bez nasazení (R155, bod 57).

## Stack

PHP 8.4 · Laravel 13 · Inertia 3 · Vue 3 · SCSS (BEM + CSS tokeny) · Fortify · Socialite (Google, Facebook, R96; Seznam vlastním ovladačem, R98) ·
MariaDB 11.4 · Pest 4 · Larastan · Pint. Text s polohou z PDF letáků: `pdftotext` (Poppler, R86). Extrakce letáků přes LLM (etapa 6, jen pokud bude potřeba): Claude API.

## Pasti

Podrobně po oblastech v **[docs/PASTI.md](docs/PASTI.md)** (57 bodů se stálými čísly) — **před prací
na oblasti si přečti její kapitolu**: ceny a platnost, stahování a zdroje, jednotlivé obchody
(Tesco, Penny, Globus, Billa, Kaufland, PDF letáky), hlídání a katalog, výpisy a hledání,
upozornění a e-maily, účty a právo, SEO, vzhled a přístupnost, aplikace v telefonu, nákupní
seznam a průvodce, produkce, testy. Nová past patří tam, ne sem.

Jádro, které platí všude:

1. **Ceny jsou v haléřích jako `int`** (R7) — přes sdílený parser, nikdy `(int) ($x * 100)` (bod 1).
2. **Platnost je místní datum** (`Europe/Prague`), převod ve zdroji před uložením (bod 2).
3. **Akce ≠ sleva** (R8). Procenta jen `Offer::effectiveDiscountPercent()` / `Offer::discountSql()`,
   nikdy surový `discount_percent` (bod 3). Cena s kartou je `loyalty_price` vedle běžné (body 4, 5).
4. **Zdroj vrací celou nabídku obchodu najednou** — chybějící akce se označí jako stažené (R16, pojistka
   R54); hromadný `upsert` obchází přetypování modelu (body 15, 17).
5. **Výpisy akcí filtrují `->active()->notExpired()`**, co platí dnes, navíc vyřadí `upcoming` (R76);
   dotaz bez `active()` potřebuje `fromEnabledChains()` (body 15, 43, 57).
6. **Párování hledá začátek slova** v textu z `TextNormalizer`, předvýběr v SQL jen přes
   `OfferPrefilter`; nový výpis pro uživatele po hlídaných položkách jde přes `MyOffers::forUser` (body 19, 49).
7. **Testy nesahají na síť** (R11), fixtures jsou zkrácené skutečné odpovědi, helper je
   `responseFixture()` (body 11, 18).
8. **Texty UI jen přes `lang/cs/app.php`**, uložení potvrzuje toast, nevratnou akci `confirmDialog`
   (bod 33); tón „my“ a genderově neutrální (bod 37).
9. **Nová veřejná stránka** = registr `PublicPages` (nebo vlastní větve jako týden), jinak `noindex` (bod 30).
10. **Cookies a třetí strany jen po souhlasu** — kategorie v `CookieConsent.vue`, CSP, zásady (bod 38).
11. **E-mail jen na ověřenou adresu**, hromadný s odhlášením jedním klepnutím (bod 37); nová tabulka
    s daty uživatele bez `cascadeOnDelete` se maže v `DeleteAccount` (bod 39).

## Jazyk

| Co | Jazyk |
|---|---|
| Identifikátory v kódu: třídy, metody, sloupce, tabulky | **anglicky** (`Offer`, `loyalty_price`) |
| Komentáře, PHPDoc, dokumentace, commit zprávy | **česky** |
| Uživatelské texty | **česky, vždy přes `lang/cs/app.php`**, ve Vue přes `useTranslations()` |

Commity mají předmět „Oblast: co se změnilo" a tělo s tím, co a proč. Viz
guidelines, sekce 8. Repozitář je osobní (`R0mul0s`), autor commitů
`Roman Hlaváček <romanhlavacek91@gmail.com>`.
