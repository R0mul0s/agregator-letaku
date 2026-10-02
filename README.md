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

> **Stav:** hotový je technický průzkum zdrojů dat a dokumentace. Kostra aplikace
> vznikne v etapě 1 ([PLAN.md, sekce 6](docs/PLAN.md#6-etapy)). Návod níže platí od ní.

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
# doplnit do .env: TESCO_API_KEY (veřejný klíč z HTML e-shopu, viz docs/ZDROJE_DAT.md)

docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app npm install
docker compose exec app npm run build
```

Pak otevři http://localhost:54720.

### Služby

| Služba | Adresa |
|---|---|
| Aplikace | http://localhost:54720 |
| MariaDB | localhost:54721 (`agregator` / `agregator`) |
| Vite dev server | http://localhost:54722 (při `npm run dev`) |

## Struktura repozitáře (plán)

```
app/
  Console/Commands/      artisan příkazy (obálky nad akcemi)
  Domain/Chains/         obchody, prodejny, věrnostní programy
  Domain/Sources/<Obchod>/ stažení a převod nabídky jednoho obchodu
  Domain/Offers/         normalizace, deduplikace, uložení nabídek
  Domain/Extraction/     extrakce letáků přes LLM (etapa 6)
  Domain/Matching/       párování hlídaných položek, kategorie
  Enums/                 Chain, OfferType, LoyaltyProgram, MatchStatus
config/letaky.php        nastavení obchodů a konstanty
docs/                    zadání, pravidla, zdroje dat
lang/cs/app.php          všechny texty
resources/js/            Inertia stránky a Vue komponenty
resources/scss/          styly (tokeny, komponenty, stránky)
tests/Fixtures/<obchod>/ uložené skutečné odpovědi obchodů pro testy
```
