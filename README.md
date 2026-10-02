<img src="public/images/brand/logo.png" alt="Slevohlídka" width="240">

# Slevohlídka — rychlý lovec slev

Hlídání akčních nabídek z letáků obchodů **Kaufland, Tesco, Albert, Lidl a Penny**.
Vybereš si prodejny a zadáš, co tě zajímá: konkrétní produkt („Coca-Cola Zero“)
nebo kategorii bez ohledu na značku („vejce“, „polotučné mléko“). Aplikace ukáže,
kde a za kolik je to právě ve slevě, včetně cen s věrnostní kartou a ceny za jednotku.

| | |
|---|---|
| **Zadání, datový model, rozhodnutí** | [docs/PLAN.md](docs/PLAN.md) |
| **Zdroje dat obchodů** | [docs/ZDROJE_DAT.md](docs/ZDROJE_DAT.md) |
| **Pravidla pro psaní kódu** | [docs/CODING_GUIDELINES.md](docs/CODING_GUIDELINES.md) |
| **Odložené úkoly** | [docs/TODO.md](docs/TODO.md) |
| **Nasazení (Websupport)** | [deploy/DEPLOYMENT.md](deploy/DEPLOYMENT.md) |
| **Instrukce pro AI agenty** | [CLAUDE.md](CLAUDE.md) |
| **Správce** | Roman Hlaváček |

> **Stav:** hotové jsou účty, stahování akcí Kauflandu, Tesca, Lidlu a Penny, zmínky v letácích
> bez ceny (Lidl, Penny, Albert), hlídání a katalog 164 produktů se stromem kategorií z Tesca
> (etapy 1–5b, [PLAN.md, sekce 6](docs/PLAN.md#6-etapy)). Balíček pro nasazení na Websupport
> (`slevohlidka.rhsoft.cz`) je připravený, nasazení zatím neproběhlo. Další je LLM (jen když bude potřeba).

## Jak se to používá

1. **Obchody** — zaškrtni obchody, karty a aplikace, které máš; u Tesca a Albertu typ prodejny (hypermarket / supermarket).
2. **Hlídám** — vyber produkt z katalogu (rychlý výběr jedním klepnutím), nebo zadej vlastní hledaná slova, případně variantu („zero“) a slova k vyloučení.
3. **Moje slevy** — akce k hlídaným položkám od nejnižší ceny za kg / l / ks. Souhrnné akce („různé druhy“) bez hledané varianty jsou označené **Možná**. Pod akcemi jsou **zmínky v letácích bez ceny** (Lidl, Penny, Albert) s náhledem a odkazem na stránku letáku.
4. **Všechny akce** — hledání s našeptávačem (produkty katalogu a názvy akcí) a filtr obchodu.
5. **Katalog** (jen admin, `php artisan letaky:admin email`) — tabulka sdílených produktů se slovy a kategorií ze stromu e-shopu Tesco (hledání, filtr oddělení, řazení). Akce se k nim přiřadí při každém stažení; v detailu produktu jde přiřazení ručně opravit („sem patří“ / „sem nepatří“).

## Jak to funguje

1. Jednou až dvakrát denně se stáhne akční nabídka všech obchodů. Kaufland, Tesco a částečně Lidl a Penny mají strukturovaná data; z letáků Lidlu, Penny a Albertu se ukládá text stránek pro zmínky bez ceny. LLM zatím ne (R23).
2. Nabídky se převedou do jednotného tvaru: cena v haléřích, původní cena, cena s kartou, cena za jednotku, platnost a typ akce.
3. Hlídané položky uživatele se spárují s nabídkami ve vybraných prodejnách. Výsledek je **shoda**, nebo **možná** u položek typu „různé druhy“.

## Stack

PHP 8.4 · Laravel 13 · Inertia · Vue 3 · SCSS · MariaDB 11.4 · Pest. Podrobně
s důvody v [PLAN.md, sekce 3](docs/PLAN.md#3-technologie).

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

# nabídky od obchodů (všechny obchody ~1,5 minuty)
docker compose exec app php artisan letaky:import-offers
```

Pak otevři http://localhost:54720 a zaregistruj se (nebo se přihlas vývojovým uživatelem).
Odchozí e-maily (obnova hesla, souhrn akcí) lokálně zachytává Mailpit (http://localhost:54723), nic neodejde ven.

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
| `LETAKY_REQUEST_DELAY_MS` | ne (1500) | pauza mezi požadavky na stejný obchod |
| `LETAKY_LIDL_REQUEST_DELAY_MS`, `LETAKY_PENNY_REQUEST_DELAY_MS`, `LETAKY_ALBERT_REQUEST_DELAY_MS` | ne (500) | kratší pauza pro Lidl, Penny a Albert — desítky malých stránek (R25) |
| `LETAKY_USER_AGENT` | ne | User-Agent požadavků na obchody |
| `LETAKY_CRON_TOKEN` | na produkci | token cron URL `/cron/import-offers?chain=…&token=…` (R38); prázdný = cron URL vrací 404 |
| `LETAKY_DISPLAY_TIMEZONE` | ne (`Europe/Prague`) | zóna pro „místní datum“ platnosti akcí |

Adresy zdrojů obchodů a ostatní konstanty jsou v `config/letaky.php`.

## Běžné příkazy

Kontrola kvality před commitem: viz [CODING_GUIDELINES.md, sekce 9](docs/CODING_GUIDELINES.md#9-nástroje-a-kvalita).

```bash
docker compose exec app npm run dev      # assety: watch s HMR
docker compose exec app npm run build    # assety: produkční build

# stažení nabídek (bez argumentu všechny obchody se zdrojem)
docker compose exec app php artisan letaky:import-offers [kaufland] [tesco] [albert] [lidl] [penny]

# strom kategorií katalogu z e-shopu Tesco (stačí občas)
docker compose exec app php artisan letaky:import-categories

# správa katalogu pro účet (--revoke odebere)
docker compose exec app php artisan letaky:admin email@example.com

# e-mailové souhrny nových akcí těm, kterým je čas (R42); lokálně do Mailpitu
docker compose exec app php artisan letaky:send-digests
```

## Struktura repozitáře

```
app/
  Actions/Fortify/         registrace, obnova a změna hesla, úprava profilu (R12)
  Console/Commands/        artisan importy (obálky nad akcemi)
  Domain/Account/          účet: přihlášená zařízení (session v DB), název zařízení z User-Agentu
  Domain/Catalog/          katalog produktů: kategorie (strom Tesca), přiřazení akcí, ruční opravy
  Domain/Chains/           sledované obchody a jejich možnosti, uložení nastavení
  Domain/Matching/         hlídané položky: pravidla, párování, Moje slevy
  Domain/Offers/           jednotný tvar nabídek (Data), parsery cen a balení (Parsing),
                           import (Actions), hledání, cena za jednotku, místní kalendář
  Domain/Sources/<Obchod>/ stažení a převod nabídky jednoho obchodu (Kaufland, Tesco, Albert, Lidl, Penny)
  Domain/Sources/          rozhraní zdrojů, registr, HTTP klient s pauzami
  Enums/                   Chain, StoreFormat, OfferType, LoyaltyProgram, PackageUnit…
  Http/                    tenké kontrolery, Form Requesty, sdílená data Inertie
  Models/                  User, Leaflet, LeafletPage, Offer, ScrapeRun, FollowedChain, WatchItem,
                           Category, Product, OfferProduct, OfferProductExclusion
  Policies/                oprávnění k hlídaným položkám (jen vlastník)
  Support/                 stránkování (Pagination), SEO hlavička (Seo), limity požadavků (RateLimits)
config/letaky.php          zdroje obchodů a konstanty aplikace
database/seeders/data/     produkty katalogu (catalog-products.php)
deploy/                    nasazení na Websupport: build balíčku, .env, SQL skripty (DEPLOYMENT.md)
public/images/             loga obchodů (chains/) a ikony Slevohlídky (brand/)
resources/brand/           zdrojové logo Slevohlídky
config/fortify.php         zapnuté funkce účtu (R13)
docker/                    PHP, nginx a MariaDB pro vývoj
docs/                      zadání, pravidla, zdroje dat
lang/cs/                   všechny texty (app.php) a překlady Laravelu
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
