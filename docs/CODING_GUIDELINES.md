<!--
  Pravidla pro psaní kódu — Agregátor letáků
  @author Roman Hlaváček
  @created 2026-10-02
-->

# Coding Guidelines — Agregátor letáků

Tento dokument je závazný pro všechny úpravy v repozitáři. Vychází z pravidel
projektu Počasí, upravených na scrapery obchodů a uživatelské účty.
Cílem je udržet projekt **čitelný, bezpečný a dlouhodobě udržovatelný**.
V rozporu mezi „elegancí" a „srozumitelností" volíme srozumitelnost.

Funkční zadání a log rozhodnutí je v [PLAN.md](PLAN.md), technické detaily zdrojů
dat v [ZDROJE_DAT.md](ZDROJE_DAT.md).

---

## 1. Obecné principy

- **Vždy best practices daného jazyka a frameworku.** Když něco děláš jinak, musí to mít zdokumentovaný důvod.
- **Žádné magic numbers.** Hodnoty patří do konstant, enumů nebo konfigurace (`config/letaky.php`, `.env`).
- **Než napíšeš novou utilitu, zjisti, jestli už existuje** v projektu, v Laravelu nebo v použitých balíčcích.
- **YAGNI:** žádné feature flagy ani abstrakce pro hypotetické budoucí potřeby.
- **Validace na hranici systému.** Pro vstup od uživatele Form Request, pro data od obchodu normalizace ve zdroji. Uvnitř doménového kódu věř typům.
- **Bezpečnost:** žádný SQL string concat, žádný `eval`, žádný `unserialize` na vstupu zvenčí.

---

## 2. Hlavička každého nového souboru

Každý PHP, JS, Vue, SCSS a Blade soubor začíná hlavičkou s popisem, autorem
a datem vytvoření. Platí to i pro soubory z `make:*`, generátor hlavičku
nepřidá.

**PHP:** hlavička je před `declare(strict_types=1)`, prázdný řádek před `@created` doplní Pint.
```php
<?php

/**
 * Krátký popis souboru.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);
```

**JS:**
```js
/**
 * Krátký popis souboru.
 *
 * @author Roman Hlaváček
 * @created 2026-10-02
 */
```

**Vue:**
```vue
<!--
    Krátký popis komponenty.

    @author Roman Hlaváček
    @created 2026-10-02
-->
```

**SCSS:**
```scss
// _offer-card.scss
// Krátký popis.
//
// @author Roman Hlaváček
// @created 2026-10-02
```

**Blade:** `{{-- … --}}` se stejným obsahem.

---

## 3. PHP & Laravel

### Verze a styl
- **PHP 8.4**
- **`declare(strict_types=1);`** v každém PHP souboru
- **Formátování:** Laravel Pint (preset `laravel`)
- **Statická analýza:** Larastan level 8
- **Naming:** třídy `PascalCase`, modely v jednotném čísle (`Offer`); metody a proměnné `camelCase`; URL `kebab-case`

### Architektura

Tenké kontrolery, logika v doméně:

```
Controller / Artisan command   ← orchestrace, validace (Form Request), odpověď
    ↓
Action / doménová třída        ← jeden use-case = jedna třída (ImportChainOffers, MatchWatchItems)
    ↓
Eloquent model                 ← perzistence
```

| Složka | Obsah |
|---|---|
| `app/Domain/Chains` | výčet obchodů `Chain`, prodejny, věrnostní programy |
| `app/Domain/Sources/<Obchod>` | **zdroj dat jednoho obchodu**: HTTP požadavky a převod odpovědi na `OfferData`; nic jiného |
| `app/Domain/Offers` | normalizace (balení, cena za jednotku, typ akce, platnost), deduplikace, uložení |
| `app/Domain/Offers/Actions` | use-casy (`ImportChainOffers`) |
| `app/Domain/Extraction` | extrakce položek z letáků přes LLM (etapa 6) |
| `app/Domain/Matching` | párování hlídaných položek s nabídkami, kategorie |
| `app/Enums` | výčty (`Chain`, `OfferType`, `LoyaltyProgram`, `MatchStatus`) |
| `app/Http/Controllers` | tenké kontrolery |
| `app/Http/Requests` | Form Requesty |
| `app/Console/Commands` | Artisan příkazy, jen obálka nad Action |

- **Doménová logika nepatří do kontroleru, commandu ani modelu.**
- Repository třídy nezavádíme, Eloquent stačí.
- Úlohy importu jsou **Actions**, které jde spustit z artisan příkazu i odjinud (scheduler, cron URL). Způsob spouštění závisí na hostingu ([O1](PLAN.md#7-otevřené-otázky)).

### Zdroje dat obchodů (scrapery)
- **Každý obchod = jedna třída zdroje** v `app/Domain/Sources/<Obchod>`, implementuje společné rozhraní a vrací kolekci `OfferData` (DTO, `readonly`). Zdroj neukládá do DB a neví o uživatelích.
- **HTTP výhradně přes `Http::` facade Laravelu** s timeoutem, retry a User-Agentem z konfigurace. Žádný `file_get_contents` ani curl.
- URL, hlavičky, pauzy mezi požadavky a API klíče jsou v `config/letaky.php` (klíče v `.env`). V kódu zdroje nejsou natvrdo.
- **Mezi požadavky na stejný obchod je pauza** (`config('letaky.request_delay_ms')`). Respektuj robots.txt, viz [ZDROJE_DAT.md](ZDROJE_DAT.md).
- Parsování odpovědi je samostatná metoda nebo třída, která přijímá řetězec nebo pole. Kvůli testům s fixtures nesmí sama stahovat.
- **Neočekávaný tvar odpovědi = výjimka**, ne prázdná kolekce. Nula položek se zapíše do `scrape_runs` jako chyba.
- Původní položka se ukládá do `offers.raw`, aby se data dala přepočítat bez nového stažení.

### Normalizace dat od obchodů
- **Ceny v haléřích jako `int`** ([R7](PLAN.md#8-log-rozhodnutí)). Převod z „29,90“ nebo `29.9` jen přes sdílený parser, nikdy `(int) ($x * 100)` (float).
- **Platnost jako místní datum.** Časy v UTC od obchodu (Tesco, Albert) se převedou na `Europe/Prague` a teprve pak na datum.
- Typ akce (`OfferType`) a cena s kartou (`loyalty_price`, `loyalty_program`) se určují ve zdroji podle pravidel obchodu ([R8](PLAN.md#8-log-rozhodnutí)). „Sleva“ je jen s původní cenou.
- Množství a jednotka balení přes sdílený parser („4x0,33 l plech“, „20 kusů“, „125 g - balení“).

### LLM (etapa 6)
- Volání LLM jen přes rozhraní v `app/Domain/Extraction`, v testech nahrazené fake implementací.
- **Strukturovaný výstup s pevným schématem** a validace výsledku (cena > 0, sleva odpovídá poměru cen, platnost uvnitř platnosti letáku). Nevalidní položka se zahodí a zaloguje, nikdy se neuloží jako pravdivá.
- Výsledek pro stránku letáku se ukládá (`llm_extractions`) a stejná stránka se do LLM neposílá znovu.
- Model a limity jsou v konfiguraci, ne v kódu.

### Konfigurace
- Konstanty projektu jsou v **`config/letaky.php`** s výchozí hodnotou z `.env`.
- Do `.env` patří jen infrastruktura a tajemství (DB, API klíče).
- Kód čte `config('letaky.…')`, nikdy `env()` mimo konfigurační soubory.

### Eloquent
- `$fillable` explicitně, nikdy `$guarded = []`.
- `casts()` explicitně: datumy jako `immutable_date` / `immutable_datetime`, enumy jako enum, `raw` jako `array`.
- `Model::preventLazyLoading()` pro `local` a `testing`.
- Žádné raw queries kromě odůvodněných agregací, a tam vždy parametrizovaně.
- Dotazy na data uživatele vždy přes vazbu (`$user->watchItems()`), nikdy podle ID z requestu bez kontroly vlastníka (Policy).

### Komentáře
- **Každá metoda má PHPDoc** s popisem, co dělá.
- Komentáře píšeme tam, kde **není zjevné proč**. Žádné `// inkrementuj počítadlo`.
- Doménové třídy mají třídní PHPDoc popisující účel.
- **U parsování odpovědi obchodu uveď, odkud pravidlo pochází** (např. „Tesco: Clubcard cena je jen v `description`, viz ZDROJE_DAT.md“).

### Testy
- **Pest.** Popis testu česky jako věta o chování: `it('u Clubcard akce vezme cenu z popisu, ne z afterDiscount')`.
- **Testy nikdy nesahají na síť** ([R11](PLAN.md#8-log-rozhodnutí)). `Http::preventStrayRequests()` v `tests/Pest.php`, odpovědi přes `Http::fake()`.
- **Fixtures jsou zkrácené skutečné odpovědi obchodů** v `tests/Fixtures/<obchod>/`. Nové testy je používají, nevymýšlí vlastní tvar dat. Název souboru nese datum stažení (`kaufland/prehled-2026-10-02.html`).
- **Feature testy** pokrývají use-case end-to-end (artisan nebo HTTP request → stav DB).
- **Unit testy** jsou pro parsery a normalizaci (ceny, balení, typ akce, platnost) a pro párování.
- Testy běží proti MariaDB `agregator_test`, ne SQLite.

### Error handling
- Doménové výjimky jako vlastní třídy (`SourceResponseChanged`), ne holé `\Exception`.
- **Žádný try/catch jen pro „polknutí" chyby.** Selhání jednoho obchodu nesmí zastavit import ostatních, ale musí skončit v `scrape_runs` a v logu.

---

## 4. Databáze

### Migrace
- **Každá změna schématu = nová migrace.** Nikdy se neupravuje migrace, která už běžela na produkci.
- Každá migrace má funkční `down()`.
- Sloupce s cenou mají `comment()` s jednotkou („haléře“).

### Datové typy a pojmenování
- Tabulky jsou `snake_case` v plurálu, sloupce `snake_case`, primární klíč `id`.
- **Ceny jsou `unsignedInteger` v haléřích**, procenta `unsignedTinyInteger`.
- **Platnost (`valid_from`, `valid_to`) je `date`** v místním čase. Časy událostí (`fetched_at`, `created_at`) jsou v UTC.
- Obchod je sloupec `chain` s hodnotou enumu, ne cizí klíč (obchody jsou pevný výčet).

### Časová pásma
- Aplikace i databáze běží v **UTC**, do `Europe/Prague` se převádí až při zobrazení.
- **Do prohlížeče se posílá vždy ISO 8601 s posunem** (datum platnosti jako `YYYY-MM-DD`).
- „Platí dnes“ = místní dnešek, ne UTC dnešek.

---

## 5. Frontend: Inertia + Vue 3

- Stránky jsou v `resources/js/Pages`, sdílené komponenty v `resources/js/Components`, pomocné funkce v `resources/js/lib`.
- **`<script setup>`**, props s typy a výchozí hodnotou.
- **Žádné texty natvrdo.** Vše přes `useTranslations()`, klíče ve skupině `app.ui` v `lang/cs/app.php`.
- Ceny, čísla a datumy formátuje `resources/js/lib/format.js` (`Intl`, haléře → Kč). Nikdy se neskládají ručně.
- Data do stránky připravuje server. Komponenta nepočítá ceny za jednotku ani nefiltruje velké seznamy.
- Žádný jQuery.

---

## 6. Stylování

### Závazná pravidla
- **Žádné inline styly**: ani `style="…"`, ani `:style` s pevnými hodnotami.
- **Žádný `<style>` blok ve Vue komponentách.** Styly patří do `resources/scss/`.
- **Žádné utility třídy (Tailwind).** Používáme BEM třídy (`.offer-card__price--loyalty`).
- **Barvy, mezery, poloměry a písmo jen z tokenů** v `base/_tokens.scss` (CSS custom properties).
- Tmavý režim přepíná tokeny přes mixin `dark`, komponenty o něm nevědí.
- Barvy obchodů (Kaufland červená, Tesco modrá…) jsou tokeny, ne hodnoty v komponentách.

### Struktura

```
resources/scss/
  app.scss              ← entry point, jen @use
  abstracts/            ← negeneruje CSS: _variables.scss (breakpointy), _mixins.scss
  base/                 ← _tokens.scss (design tokeny), _reset.scss
  layout/               ← kostra stránky (_app-header.scss, _page.scss)
  components/           ← jedna komponenta = jeden soubor (_offer-card.scss)
  pages/                ← rozvržení konkrétní stránky (_watchlist.scss)
```

- **Vždy `@use`, nikdy `@import`.**
- Pro styling se nikdy nepoužívají `id` selektory.

---

## 7. Texty a lokalizace

- Všechny texty jsou v `lang/cs/app.php`: PHP (`__('app.…')`) i Vue (skupina `app.ui`).
- Placeholdery `:name`, plurály přes `trans_choice()` (čeština má tři tvary).
- Chybějící klíč se zobrazí jako holý text, a to je **bug**.
- Názvy obchodů, typů akcí a věrnostních programů jsou v `lang`, ne v enumu.

---

## 8. Git workflow

- Repozitář: `https://github.com/R0mul0s/agregator-letaku` (osobní účet, autor `Roman Hlaváček <romanhlavacek91@gmail.com>`, nastavené lokálně v repu).
- Pracuje se v **`main`**, který musí být vždy funkční. Commit až po projití kontrol (sekce 9).
- Předmět commitu je **česká věta „Oblast: co se změnilo"**, bez prefixů Conventional Commits.
- **Tělo commitu popisuje co a proč.** Při zásahu do více oblastí odrážky.
- Dokumentace (PLAN, guidelines, ZDROJE_DAT) se mění **ve stejném commitu** jako kód.

```
Kaufland: zdroj nabídky z window.SSR na prodejny.kaufland.cz
Hlídání: stav „možná“ pro položky s různými druhy
```

---

## 9. Nástroje a kvalita

Kontroly se pouštějí **ručně před každým commitem**. Commit, po kterém některá neprojde, do `main` nepatří.

```bash
docker compose exec app ./vendor/bin/pest
docker compose exec app ./vendor/bin/pint
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec app npm run build          # při změně JS, Vue nebo SCSS
```

CI (GitHub Actions) zatím není, ruční kontroly jsou jediná pojistka ([R14](PLAN.md#8-log-rozhodnutí)).

---

## 10. Bezpečnost

- **Přihlášení přes Fortify** ([R12](PLAN.md#8-log-rozhodnutí), [R13](PLAN.md#8-log-rozhodnutí)): hesla hashovaná, limit pokusů o přihlášení na dvojici e-mail + IP, odeslání odkazu na obnovu hesla omezuje Laravel (jednou za minutu).
- **Uživatel vidí a mění jen svá data.** Hlídané položky a výběr prodejen přes Policy a vazby na uživatele.
- Tajemství (API klíče obchodů a LLM) jsou v `.env`, nikdy v repu. `.env.example` má prázdné hodnoty.
- CSRF všude. Případná cron URL je chráněná tokenem z `.env` a rate limitem.
- **Obsah od obchodu je nedůvěryhodný vstup**: ve Vue jen textová interpolace, nikdy `v-html`. Totéž platí pro text od LLM.
- Neveřejná rozhraní obchodů, která vyžadují přihlášení (`UNAUTHENTICATED`), se neobcházejí.

---

## 11. Co nedělat

- Neukládejte ceny jako `float` ani `decimal` ve Kč. Používejte haléře jako `int` ([R7](PLAN.md#8-log-rozhodnutí)).
- Neoznačujte jako slevu nabídku bez původní ceny („Super cena“, „AKCE! pouze“, Lidl „Ušetřete %“), viz [R8](PLAN.md#8-log-rozhodnutí).
- Nepovažujte prázdnou odpověď obchodu za „žádné akce“. Je to chyba zdroje.
- Nevolejte v testech skutečné obchody ani LLM.
- Nepoužívejte zdroje, které zakazuje robots.txt (Lidl search API), a neobcházejte ochrany ani přihlášení.
- Nestahujte a neukládejte letáky, PDF a fotky produktů natrvalo ([R5](PLAN.md#8-log-rozhodnutí)). Pracovní soubory extrakce se po zpracování mažou.
- Nemažte staré nabídky ani letáky, slouží jako historie ([R10](PLAN.md#8-log-rozhodnutí)).
- Nepoužívejte `dd()`, `dump()` ani `console.log()` v commitech.

---

*Autor: Roman Hlaváček · Vytvořeno: 2026-10-02*
