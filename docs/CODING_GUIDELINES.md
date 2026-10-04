<!--
  Pravidla pro psaní kódu — Slevohlídka
  @author Roman Hlaváček
  @created 2026-10-02
-->

# Coding Guidelines — Slevohlídka

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
| `app/Domain/Chains` | sledovatelné obchody a jejich možnosti (`ChainCatalog`), uložení nastavení (`Actions/UpdateShoppingPreferences`) |
| `app/Domain/Sources` | rozhraní `OfferSource`, `SourceRegistry` (zdroje podle `config/letaky.php`), `SourceHttp` |
| `app/Domain/Sources/<Obchod>` | **zdroj dat jednoho obchodu**: HTTP požadavky a převod odpovědi na `OfferData`; nic jiného |
| `app/Domain/Offers/Data` | jednotný tvar ze zdrojů (`OfferData`, `LeafletData`, `SourceBatch`, `PackageSize`) |
| `app/Domain/Offers/Parsing` | sdílené parsery: `PriceParser` (haléře), `PackageParser` (balení), `VariantNote`, `Text` |
| `app/Domain/Offers` | místní kalendář (`LocalCalendar`), cena za jednotku, hledání a příprava pro stránku |
| `app/Domain/Offers/Actions` | use-casy (`ImportChainOffers`) |
| `app/Domain/Extraction` | extrakce položek z letáků přes LLM (etapa 6) |
| `app/Domain/Matching` | párování hlídaných položek s nabídkami, kategorie |
| `app/Enums` | výčty (`Chain`, `OfferType`, `LoyaltyProgram`, `MatchStatus`) |
| `app/Http/Controllers` | tenké kontrolery |
| `app/Http/Requests` | Form Requesty |
| `app/Console/Commands` | Artisan příkazy, jen obálka nad Action |

- **Doménová logika nepatří do kontroleru, commandu ani modelu.**
- Repository třídy nezavádíme, Eloquent stačí.
- Úlohy importu jsou **Actions**, které jde spustit z artisan příkazu i z cron URL. Produkce na Websupportu nemá frontu ani scheduler ([R20](PLAN.md#8-log-rozhodnutí)): nic nesmí implementovat `ShouldQueue` a každá migrace bude potřebovat SQL skript v `deploy/`.

### Zdroje dat obchodů (scrapery)
- **Každý obchod = jedna třída zdroje** v `app/Domain/Sources/<Obchod>`, implementuje společné rozhraní a vrací kolekci `OfferData` (DTO, `readonly`). Zdroj neukládá do DB a neví o uživatelích.
- **HTTP výhradně přes `Http::` facade Laravelu** s timeoutem, retry a User-Agentem z konfigurace. Žádný `file_get_contents` ani curl. User-Agent je identifikovatelný, ale **bez adresy se schématem** (`+slevohlidka.rhsoft.cz`, ne `https://…`) — Albert jinak požadavek pošle přes prerender pro roboty a vrátí 400 ([R65](PLAN.md#8-log-rozhodnutí)).
- URL, hlavičky, pauzy mezi požadavky a API klíče jsou v `config/letaky.php` (klíče v `.env`). V kódu zdroje nejsou natvrdo.
- **Mezi požadavky na stejný obchod je pauza** (`config('letaky.request_delay_ms')`). Respektuj robots.txt, viz [ZDROJE_DAT.md](ZDROJE_DAT.md).
- Parsování odpovědi je samostatná metoda nebo třída, která přijímá řetězec nebo pole. Kvůli testům s fixtures nesmí sama stahovat.
- **Neočekávaný tvar odpovědi = výjimka**, ne prázdná kolekce. Nula položek se zapíše do `scrape_runs` jako chyba (výjimkou je jen obchod s `mentions_only`). Podezřele velký propad akcí oproti minulému stažení akce nestáhne a stažení skončí jako `partial`; stažení obchodu drží zámek, souběžné neběží ([R54](PLAN.md#8-log-rozhodnutí), [R57](PLAN.md#8-log-rozhodnutí)).
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
- **Fixtures jsou zkrácené skutečné odpovědi obchodů** v `tests/Fixtures/<obchod>/`, popsané v `tests/Fixtures/README.md`. Nové testy je používají přes `responseFixture()` / `jsonResponseFixture()` z `tests/Pest.php`, nevymýšlí vlastní tvar dat. Název souboru nese datum stažení (`kaufland/prehled-2026-10-02.html`).
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
- **Každá migrace má ve stejném commitu SQL skript** `deploy/migrations-<datum>-<popis>.sql` — opakovatelný (`IF NOT EXISTS`), se zápisem do `migrations`, schéma shodné s výsledkem `migrate` (`SHOW CREATE TABLE`). Na produkci není SSH ani composer, skript se pouští v phpMyAdminu ([R20](PLAN.md#8-log-rozhodnutí), [DEPLOYMENT.md](../deploy/DEPLOYMENT.md)).
- Nová hodnota enumu v textovém sloupci (stav stažení, četnost souhrnu) migraci ani skript nepotřebuje.
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
- Data do stránky připravuje server. Komponenta nepočítá ceny za jednotku ani nefiltruje velké seznamy (výjimka: malé seznamy pro admina, např. ~160 produktů katalogu, se filtrují a řadí v prohlížeči).
- Sdílená data Inertie (`HandleInertiaRequests::share`) nesmí mít stejný klíč jako prop stránky — prop stránky ho přepíše.
- **Zpětná vazba:** uložení potvrzuje toast (kód stavu ze serveru, [R47](PLAN.md#8-log-rozhodnutí)), nevratnou akci vlastní potvrzovací okno (`confirmDialog`), nikdy `window.confirm`.
- **Nastavení se ukládá hned po změně** (přepínače, výběry, zaškrtávátka) a posílá celý stav, aby při překryvu požadavků vyhrál poslední; tlačítko Uložit mají jen formuláře, kde se píše ([R63](PLAN.md#8-log-rozhodnutí), [R64](PLAN.md#8-log-rozhodnutí)). Heslo se k nebezpečné akci zadává až po klepnutí.
- **Vysvětlivka nesmí být jen v `title`** — na dotykovém displeji se neukáže. Štítek s vysvětlením je tlačítko s ikonou „i“ (`InfoIcon`) a textem pod ním ([R55](PLAN.md#8-log-rozhodnutí)).
- Tlačítko, které jen přepíná stav (do seznamu, hlídat), ukáže nový stav hned po klepnutí a server ho potvrdí — na pomalém mobilním připojení by jinak druhé klepnutí narazilo na zablokované tlačítko ([R62](PLAN.md#8-log-rozhodnutí)).
- Hlavní scénář je telefon v obchodě: ovládací prvky dost velké pro palec, důležité informace na první obrazovce.
- **JSON dotaz při psaní** (našeptávač, náhled) jde přes `fetch` s pauzou v psaní a zrušením předchozího (`AbortController`); routa má middleware `ReadOnlySession`, jinak souběžné uložení formuláře přijde o zprávu pro toast ([R71](PLAN.md#8-log-rozhodnutí)). Text od obchodu se zvýrazňuje komponentou `HighlightText`, ne přes `v-html`.
- **Aplikace v telefonu** ([R66](PLAN.md#8-log-rozhodnutí)): stránka, která má fungovat bez signálu, patří do `letaky.pwa.offline_paths` a změna, kterou jde udělat offline, musí počkat v prohlížeči a odeslat se po návratu signálu (vzor `lib/offlineChecks.js`); co offline nejde, je bez připojení zakázané. Data uživatele uložená v prohlížeči (cache, localStorage) se po odhlášení mažou. Prvek přilepený ke spodnímu okraji obrazovky přičítá `--tab-bar-offset` (spodní lišta záložek) a obsah u okrajů displeje `env(safe-area-inset-*)`. localStorage jen přes `lib/storage.js` (anonymní okno ho nemá).
- Žádný jQuery.

---

## 6. Stylování

### Závazná pravidla
- **Žádné inline styly**: ani `style="…"`, ani `:style` s pevnými hodnotami.
- **Žádný `<style>` blok ve Vue komponentách.** Styly patří do `resources/scss/`.
- **Žádné utility třídy (Tailwind).** Používáme BEM třídy (`.offer-card__price--loyalty`).
- **Barvy, mezery, poloměry a písmo jen z tokenů** v `base/_tokens.scss` (CSS custom properties).
- Tmavý režim přepíná tokeny přes mixin `dark`, komponenty o něm nevědí.
- Obchody se ukazují **logem** (`ChainLogo`, vodoznak `ChainWatermark`), ne barvou — loga jsou v `public/images/chains`, názvy a adresy sdílí `chainInfo`.
- Barvy loga Slevohlídky (`--color-brand`, `--color-brand-dark`) jen na název v hlavičce; na tlačítka a text akcent (`--color-accent*`) se splněným kontrastem WCAG AA (bílý text na červené: velký tučný text 3 : 1, jinak 4,5 : 1).
- Pohyb (nadzvednutí karet a tlačítek) jen přes `transition` s tokeny; při `prefers-reduced-motion` se vypne v `_reset.scss`.
- Prázdný stav stránky = komponenta `EmptyState` s maskotem, ne holá věta.

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
- **Výjimka: právní texty** (podmínky užití, zásady zpracování osobních údajů) jsou Markdown v `resources/legal` ([R51](PLAN.md#8-log-rozhodnutí)) — dlouhý text se tak dá číst, porovnávat mezi verzemi a dát právníkovi. Údaje provozovatele se doplňují z `letaky.operator`, nepíšou se do textu.
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

- **Přihlášení přes Fortify** ([R12](PLAN.md#8-log-rozhodnutí), [R13](PLAN.md#8-log-rozhodnutí)): hesla hashovaná, limit pokusů o přihlášení na dvojici e-mail + IP a na samotnou IP (R53), hesla kontrolovaná proti únikům (Have I Been Pwned), registrace chráněná skrytým polem a časem vyplnění, odeslání odkazu na obnovu hesla omezuje Laravel (jednou za minutu).
- **Uživatel vidí a mění jen svá data.** Hlídané položky, nákupní seznam a sledované obchody přes Policy a vazby na uživatele.
- **Změna e-mailu chce současné heslo** a formuláře s heslem nebo odesláním e-mailu mají přísnější limit požadavků (`RateLimits::SENSITIVE_ROUTES`, [R54](PLAN.md#8-log-rozhodnutí)).
- Tajemství (API klíče obchodů a LLM) jsou v `.env`, nikdy v repu. `.env.example` má prázdné hodnoty.
- CSRF všude. Cron URL je chráněná tokenem z `.env` a rate limitem; bez tokenu vrací 404.
- **Obsah od obchodu je nedůvěryhodný vstup**: ve Vue jen textová interpolace, nikdy `v-html`. Totéž platí pro text od LLM. Odkazy a obrázky od obchodu se ukládají jen jako adresy `http(s)` (`WebUrl`, [R67](PLAN.md#8-log-rozhodnutí)).
- **Absolutní adresy jen z `APP_URL`** (`URL::forceRootUrl`), nikdy z hlaviček požadavku — odkaz na obnovu hesla by šel podvrhnout ([R67](PLAN.md#8-log-rozhodnutí)). Změna hesla odhlásí ostatní zařízení.
- **Do Google Analytics nesmí odejít token ani e-mail z adresy**: stránka s nimi patří do `letaky.cookie_consent.redacted_paths` ([R69](PLAN.md#8-log-rozhodnutí)).
- `v-html` jen pro vlastní právní texty převedené na serveru se zahozeným HTML (`LegalDocuments`, R51).
- **E-maily jen na ověřenou adresu** (R51). Hromadný e-mail má odhlášení jedním klepnutím bez přihlášení (podepsaná adresa, `List-Unsubscribe`); obchodní sdělení jen se souhlasem (`User::hasMarketingConsent`).
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
