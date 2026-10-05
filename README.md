<img src="public/images/brand/logo.png" alt="Slevohlídka" width="240">

# Slevohlídka — rychlý lovec slev

Hlídání akčních nabídek z letáků obchodů **Kaufland, Tesco, Albert, Lidl, Penny, Globus a Billa**.
Zadáš, co kupuješ: konkrétní produkt („Coca-Cola Zero“) nebo kategorii bez ohledu na značku
(„vejce“, „polotučné mléko“). Aplikace ukáže, kde a za kolik je to právě ve slevě, včetně cen
s věrnostní kartou a ceny za jednotku, a dá vědět e-mailem, když přibude nová akce.

| | |
|---|---|
| **Zadání, datový model, rozhodnutí** | [docs/PLAN.md](docs/PLAN.md) |
| **Zdroje dat obchodů** | [docs/ZDROJE_DAT.md](docs/ZDROJE_DAT.md) |
| **Pravidla pro psaní kódu** | [docs/CODING_GUIDELINES.md](docs/CODING_GUIDELINES.md) |
| **Odložené úkoly a nápady** | [docs/TODO.md](docs/TODO.md) |
| **Zveřejnění (checklist)** | [docs/ZVEREJNENI.md](docs/ZVEREJNENI.md) |
| **Nasazení (Websupport)** | [deploy/DEPLOYMENT.md](deploy/DEPLOYMENT.md) |
| **Instrukce pro AI agenty** | [CLAUDE.md](CLAUDE.md) |
| **Správce** | Roman Hlaváček |

> **Stav:** běží na [slevohlidka.rhsoft.cz](https://slevohlidka.rhsoft.cz) (Websupport,
> [deploy/DEPLOYMENT.md](deploy/DEPLOYMENT.md)). Hotové je stahování akcí všech sedmi obchodů
> (u Alberta jen zmínky v letácích bez ceny), hlídání s katalogem 164 produktů, Moje slevy
> s výběrem obchodu „Jsem v obchodě“, nákupní seznam, srovnání s dřívějšími akcemi, e-mailová
> upozornění (hned / denně / týdně) a příprava na zveřejnění (podmínky, zásady, souhlasy, cookie
> lišta). Co platí a proč je v tabulce na začátku [PLAN.md](docs/PLAN.md), log rozhodnutí R1–R65
> v [kap. 8](docs/PLAN.md#8-log-rozhodnutí). Makro zatím nejde (ochrana proti robotům,
> [ZDROJE_DAT.md](docs/ZDROJE_DAT.md)).

## Jak se to používá

1. **Registrace** — nový účet rovnou sleduje všechny obchody a otevře se Hlídám.
2. **Hlídám** — vyber produkt z katalogu (jedním klepnutím), nebo zadej vlastní hledaná slova, případně variantu („zero“) a slova k vyloučení. Produkt jde začít hlídat i přímo z karty akce ve Všech akcích („+ Hlídat Máslo“).
3. **Moje slevy** — akce k hlídaným položkám od nejnižší ceny za kg / l / ks. Souhrnné akce („různé druhy“) bez hledané varianty jsou označené **Možná**. U akce je srovnání s dřívějšími akcemi stejné položky („Nejlevněji za 12 týdnů“, „Před 3 týdny stálo 29,90 Kč“). Výběr **Jsem v obchodě** ukáže jen akce jednoho obchodu jako kompaktní řádky. Pod akcemi jsou **zmínky v letácích bez ceny** (Lidl, Penny, Albert).
4. **Seznam** — nákupní seznam z akcí („+ Do seznamu“ na kartě), po obchodech, s odškrtáváním v obchodě.
5. **Obchody** — které obchody sleduješ, karty a aplikace, které máš, u Tesca a Albertu typ prodejny, u Kauflandu své prodejny (pultové maso a ryby se po prodejnách liší). Změny se ukládají hned.
6. **Všechny akce** — hledání s našeptávačem a filtr obchodu; veřejné i bez přihlášení.
7. **Můj účet** (menu pod avatarem) — profil, upozornění e-mailem (hned / denně / týdně), řazení Mých slev, heslo a přihlášená zařízení, zrušení účtu.
8. **Katalog** (jen admin, `php artisan letaky:admin email`) — tabulka sdílených produktů se slovy a kategorií ze stromu e-shopu Tesco. Akce se k nim přiřadí při každém stažení; v detailu produktu jde přiřazení ručně opravit.

Na telefonu jde Slevohlídka přidat na plochu (manifest).

## Jak to funguje

1. Dvakrát denně se stáhne akční nabídka každého obchodu (cron URL na hostingu). Kaufland, Tesco, Globus, Billa a částečně Lidl a Penny mají strukturovaná data; z letáků Lidlu, Penny a Albertu se ukládá text stránek pro zmínky bez ceny. LLM zatím ne (R23).
2. Nabídky se převedou do jednotného tvaru: cena v haléřích, původní cena, cena s kartou, cena za jednotku, platnost a typ akce. Stažení hlídají pojistky — nula akcí je chyba, podezřele velký propad akce nestáhne a stažení obchodu běží jen jedno najednou (R54, R57).
3. Hlídané položky uživatele se spárují s nabídkami sledovaných obchodů. Výsledek je **shoda**, nebo **možná** u položek typu „různé druhy“.
4. Po stažení, které přineslo nové akce, odejdou e-mailová upozornění po dávkách (R42, R58).

## Stack

PHP 8.4 · Laravel 13 · Inertia 3 · Vue 3 · SCSS · Fortify · MariaDB 11.4 · Pest · Larastan · Pint.
Podrobně s důvody v [PLAN.md, sekce 3](docs/PLAN.md#3-technologie).

## Požadavky

**Docker Desktop** a Git, nic dalšího. PHP, Composer i Node běží v kontejneru.

## Rychlý start

```bash
git clone https://github.com/R0mul0s/agregator-letaku.git
cd agregator-letaku

cp .env.example .env
# doplnit do .env: TESCO_API_KEY (veřejný klíč z HTML e-shopu, viz docs/ZDROJE_DAT.md)

docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app npm install
docker compose exec app npm run build

# volitelně vývojový uživatel test@example.com / password (admin) a katalog produktů;
# kategorie katalogu se přiřadí, když je strom stažený (letaky:import-categories před seedem)
docker compose exec app php artisan letaky:import-categories
docker compose exec app php artisan db:seed

# prodejny Kauflandu, pak nabídky od obchodů (všechny obchody ~3 minuty)
docker compose exec app php artisan letaky:import-stores kaufland
docker compose exec app php artisan letaky:import-offers
```

Pak otevři http://localhost:54720 a zaregistruj se (nebo se přihlas vývojovým uživatelem).
Odchozí e-maily (ověření adresy, obnova hesla, upozornění na akce) lokálně zachytává Mailpit
(http://localhost:54723), nic neodejde ven.

### Služby

| Služba | Adresa |
|---|---|
| Aplikace | http://localhost:54720 |
| MariaDB | localhost:54721 (`agregator` / `agregator`) |
| Vite dev server | http://localhost:54722 (při `npm run dev`) |
| Mailpit | http://localhost:54723 (odchozí e-maily) |

Databáze pro testy `agregator_test` vzniká automaticky, ale **jen při prvním
startu nad prázdným volume** (`docker/mariadb/init.sql`). Když chybí, smaž
volume (`docker compose down -v`).

## Konfigurace

| Proměnná | Povinná | K čemu |
|---|---|---|
| `TESCO_API_KEY` | pro Tesco | veřejný klíč e-shopu Tesco (`mangoApiKey` v HTML `nakup.itesco.cz`); bez něj stažení Tesca skončí chybou |
| `LETAKY_CRON_TOKEN` | na produkci | token cron URL `/cron/…?token=…` (R38); prázdný = cron URL vrací 404 |
| `LETAKY_GA_MEASUREMENT_ID` | ne | ID Google Analytics 4 — načte se jen na produkci a po souhlasu s cookies (R52) |
| `LETAKY_REQUEST_DELAY_MS` | ne (1500) | pauza mezi požadavky na stejný obchod |
| `LETAKY_LIDL_REQUEST_DELAY_MS`, `LETAKY_PENNY_REQUEST_DELAY_MS`, `LETAKY_ALBERT_REQUEST_DELAY_MS` | ne (500) | kratší pauza pro Lidl, Penny a Albert — desítky malých stránek (R25) |
| `LETAKY_BILLA_REQUEST_DELAY_MS` | ne (1000) | pauza mezi stránkami katalogu Billy (25 stránek, R48) |
| `LETAKY_KAUFLAND_STORES_DELAY_MS`, `LETAKY_KAUFLAND_STORE_PAGE_DELAY_MS` | ne (300, 1000) | pauza mezi seznamy akcí 149 prodejen Kauflandu a před stránkou prodejny (R49) |
| `LETAKY_USER_AGENT` | ne | User-Agent požadavků na obchody; výchozí `Slevohlidka/1.0 (+slevohlidka.cz)` — **bez `https://`**, jinak Albert vrací 400 (R65) |
| `LETAKY_PASSWORD_UNCOMPROMISED` | ne (`true`) | kontrola uniklých hesel přes Have I Been Pwned (R53); v testech vypnutá |
| `LETAKY_DISPLAY_TIMEZONE` | ne (`Europe/Prague`) | zóna pro „místní datum“ platnosti akcí |

Adresy zdrojů obchodů a ostatní konstanty jsou v `config/letaky.php`.

## Běžné příkazy

Kontrola kvality před commitem: viz [CODING_GUIDELINES.md, sekce 9](docs/CODING_GUIDELINES.md#9-nástroje-a-kvalita).

```bash
docker compose exec app npm run dev      # assety: watch s HMR
docker compose exec app npm run build    # assety: produkční build

# stažení nabídek (bez argumentu všechny obchody se zdrojem)
docker compose exec app php artisan letaky:import-offers [kaufland] [tesco] [albert] [lidl] [penny] [globus] [billa]

# prodejny Kauflandu a akce každé z nich (R49) — před stažením Kauflandu
docker compose exec app php artisan letaky:import-stores kaufland

# strom kategorií katalogu z e-shopu Tesco (stačí občas)
docker compose exec app php artisan letaky:import-categories

# správa katalogu pro účet (--revoke odebere)
docker compose exec app php artisan letaky:admin email@example.com

# e-mailová upozornění na nové akce těm, kterým je čas (R42, R58); lokálně do Mailpitu
docker compose exec app php artisan letaky:send-digests

# úklid vypršelých relací a odkazů na obnovu hesla (R53)
docker compose exec app php artisan letaky:prune-sessions
```

## Struktura repozitáře

```
app/
  Actions/Fortify/         registrace, obnova a změna hesla, úprava profilu (R12)
  Console/Commands/        artisan importy, souhrny, úklid (obálky nad akcemi)
  Domain/Account/          účet: přihlášená zařízení, ochrana registrace, odhlášení z e-mailů
  Domain/Catalog/          katalog produktů: kategorie (strom Tesca), přiřazení akcí, ruční opravy, „Hlídat“ z karty
  Domain/Chains/           sledované obchody a jejich možnosti, uložení nastavení
  Domain/Digest/           e-mailová upozornění na nové akce (souhrny po dávkách)
  Domain/Matching/         hlídané položky: pravidla, párování, Moje slevy
  Domain/Offers/           jednotný tvar nabídek (Data), parsery cen a balení (Parsing), import
                           s pojistkami (Actions), hledání, historie cen, ukázka akcí, místní kalendář
  Domain/Sources/<Obchod>/ stažení a převod nabídky jednoho obchodu (Kaufland, Tesco, Albert, Lidl, Penny, Globus, Billa)
  Domain/Sources/          rozhraní zdrojů, registr, HTTP klient s pauzami
  Enums/                   Chain, StoreFormat, OfferType, LoyaltyProgram, DigestFrequency, ScrapeStatus…
  Http/                    tenké kontrolery, Form Requesty, vlastní odpovědi Fortify, sdílená data Inertie
  Mail/                    e-mail s upozorněním na akce
  Models/                  User, Leaflet, LeafletPage, Offer, ScrapeRun, FollowedChain, WatchItem, Store,
                           OfferStore, Category, Product, OfferProduct, OfferProductExclusion, ShoppingListItem
  Policies/                oprávnění k hlídaným položkám a nákupnímu seznamu (jen vlastník)
  Rules/                   vlastní validační pravidla (hledaná slova)
  Support/                 stránkování, SEO hlavička, limity požadavků, právní texty, provozovatel, 5. pád jména
config/letaky.php          zdroje obchodů a konstanty aplikace
config/fortify.php         zapnuté funkce účtu (R13)
database/seeders/data/     produkty katalogu (catalog-products.php)
deploy/                    nasazení na Websupport: build balíčku, .env, SQL skripty (DEPLOYMENT.md)
docker/                    PHP, nginx a MariaDB pro vývoj
docs/                      zadání, pravidla, zdroje dat, zveřejnění, nápady
lang/cs/                   všechny texty (app.php) a překlady Laravelu
public/images/             loga obchodů (chains/) a ikony Slevohlídky (brand/)
resources/brand/           zdrojové logo a OG obrázek Slevohlídky
resources/legal/           podmínky užití a zásady zpracování osobních údajů (Markdown)
resources/js/              Inertia stránky a Vue komponenty
resources/scss/            styly (tokeny, komponenty, stránky)
tests/                     Pest — Feature a Unit
tests/Fixtures/<obchod>/   zkrácené skutečné odpovědi obchodů (popis v README tamtéž)
```

## Řešení potíží

| Příznak | Příčina a řešení |
|---|---|
| Testy padají na připojení k databázi | chybí `agregator_test`, viz *Služby* |
| Na stránce chybí styly | nesestavené assety, `npm run build` |
| `Route [...] not defined` | cache rout, `php artisan optimize:clear` |
| Každý požadavek lokálně trvá ~2,5 s | sdílené složky Dockeru na Windows — není to chyba aplikace (výpočet Mých slev trvá desetiny sekundy) |
| Albert na produkci padá s HTTP 400 | User-Agent s `https://` — Albert ho pošle přes prerender pro roboty (R65), viz *Konfigurace* |
