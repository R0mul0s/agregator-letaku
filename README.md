# Agregátor letáků

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
| **Instrukce pro AI agenty** | [CLAUDE.md](CLAUDE.md) |
| **Správce** | Roman Hlaváček |

> **Stav:** hotová je kostra aplikace s účty a přihlášením (etapa 1). Stahování
> nabídek obchodů přibude v etapě 2 ([PLAN.md, sekce 6](docs/PLAN.md#6-etapy)).

## Jak to funguje

1. Jednou až dvakrát denně se stáhne akční nabídka všech obchodů. Kaufland, Tesco a částečně Lidl a Penny mají strukturovaná data, zbytek letáků se vytěží přes LLM.
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

docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app npm install
docker compose exec app npm run build

# volitelně vývojový uživatel test@example.com / password
docker compose exec app php artisan db:seed
```

Pak otevři http://localhost:54720 a zaregistruj se (nebo se přihlas vývojovým uživatelem).
E-maily (odkaz na obnovu hesla) se lokálně jen zapisují do `storage/logs/laravel.log`.

### Služby

| Služba | Adresa |
|---|---|
| Aplikace | http://localhost:54720 |
| MariaDB | localhost:54721 (`agregator` / `agregator`) |
| Vite dev server | http://localhost:54722 (při `npm run dev`) |

Databáze pro testy `agregator_test` vzniká automaticky, ale **jen při prvním
startu nad prázdným volume** (`docker/mariadb/init.sql`). Když chybí, smaž
volume (`docker compose down -v`).

## Běžné příkazy

Kontrola kvality před commitem: viz [CODING_GUIDELINES.md, sekce 9](docs/CODING_GUIDELINES.md#9-nástroje-a-kvalita).

```bash
docker compose exec app npm run dev      # assety: watch s HMR
docker compose exec app npm run build    # assety: produkční build
```

## Struktura repozitáře

```
app/
  Actions/Fortify/       registrace, obnova a změna hesla, úprava profilu (R12)
  Enums/                 Chain (obchody), StoreFormat (hypermarket / supermarket)
  Http/                  tenké kontrolery, sdílená data Inertie
  Models/                User, Store
config/letaky.php        konstanty aplikace
config/fortify.php       zapnuté funkce účtu (R13)
docker/                  PHP, nginx a MariaDB pro vývoj
docs/                    zadání, pravidla, zdroje dat
lang/cs/                 všechny texty (app.php) a překlady Laravelu
resources/js/            Inertia stránky a Vue komponenty
resources/scss/          styly (tokeny, komponenty, stránky)
tests/                   Pest — Feature a Unit
```

S dalšími etapami přibudou `app/Domain/Sources/<Obchod>/` (stažení nabídky obchodu),
`app/Domain/Offers/` (normalizace a uložení), `app/Domain/Matching/` (párování) a
`tests/Fixtures/<obchod>/` (uložené odpovědi obchodů), viz
[CODING_GUIDELINES.md, sekce 3](docs/CODING_GUIDELINES.md#3-php--laravel).

## Řešení potíží

| Příznak | Příčina a řešení |
|---|---|
| Testy padají na připojení k databázi | chybí `agregator_test`, viz *Služby* |
| Na stránce chybí styly | nesestavené assety, `npm run build` |
| `Route [...] not defined` | cache rout, `php artisan optimize:clear` |
