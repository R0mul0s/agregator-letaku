<!--
  Agregátor letáků — plán projektu
  @author Roman Hlaváček
  @created 2026-10-02
-->

# Agregátor letáků — plán projektu

Webová aplikace, která hlídá akční nabídky z letáků českých obchodů. Uživatel si
zvolí prodejny, zadá, co ho zajímá („Coca-Cola Zero“, „vejce“, „polotučné mléko“),
a aplikace mu ukáže, kde a za kolik je to právě ve slevě.

Obchody letáky publikují hlavně jako PDF nebo flipbooky v JS prohlížečích. Průzkum
2026-10-02 ale ukázal, že u většiny z nich jde akční nabídku získat i strukturovaně.
Zbytek vytěžíme z letáku pomocí LLM ([R6](#8-log-rozhodnutí)).

- **Vývoj:** `http://localhost:54720` (Docker, viz [CLAUDE.md](../CLAUDE.md))
- **Produkce:** zatím neurčeno ([O1](#7-otevřené-otázky))
- **Repozitář:** [github.com/R0mul0s/agregator-letaku](https://github.com/R0mul0s/agregator-letaku), osobní projekt
- **Pravidla pro psaní kódu:** [CODING_GUIDELINES.md](CODING_GUIDELINES.md)
- **Zdroje dat jednotlivých obchodů (endpointy, pole, pasti):** [ZDROJE_DAT.md](ZDROJE_DAT.md)

---

## 1. Rozsah a cíl

### Co systém dělá
- Jednou až dvakrát denně stáhne aktuální akční nabídku z obchodů **Kaufland, Tesco, Albert, Lidl a Penny**, včetně příštího týdne, pokud už je zveřejněný.
- Nabídky převede do jednotného tvaru: obchod, název, balení, cena, původní cena, cena s kartou nebo aplikací, cena za jednotku, platnost od–do a typ akce ([R7](#8-log-rozhodnutí), [R8](#8-log-rozhodnutí)).
- Uživatel si založí účet, vybere **konkrétní prodejny** ([R3](#8-log-rozhodnutí)) a věrnostní programy, které používá.
- Uživatel zadá **hlídané položky**, buď konkrétní produkt, nebo kategorii bez ohledu na značku ([R9](#8-log-rozhodnutí)).
- Zobrazí **seznam aktuálních slev** k hlídaným položkám ve vybraných prodejnách, porovnatelný podle ceny za jednotku.
- Nabídky archivuje, takže zůstává historie cen i po zmizení letáku ([R10](#8-log-rozhodnutí)).

### Co systém nedělá
- Nebere data z agregátorů (kupi.cz, akcniceny.cz), jen přímo od obchodů ([R1](#8-log-rozhodnutí)).
- Nepřebírá letáky ani fotky produktů. Ukládá fakta a odkaz na zdroj ([R5](#8-log-rozhodnutí)).
- Zatím neposílá upozornění, výstupem je webový seznam (nápad je v [TODO.md](TODO.md)).
- Neřeší nákupní košík ani objednávky.

### Typické scénáře (z průzkumu)

| Scénář | Typ hledání | Na co dát pozor |
|---|---|---|
| Coca-Cola Zero | konkrétní produkt | Kaufland a Albert mají jen „Coca-Cola různé druhy“ → stav **možná** ([R9](#8-log-rozhodnutí)); Tesco má „Super cenu“, která není slevou ([R8](#8-log-rozhodnutí)) |
| vejce | kategorie | vyřadit vaječné těstoviny, ruské vejce, „Přidej vejce“; porovnávat cenu za kus (10 / 20 / 30 ks) |
| polotučné mléko | kategorie | vyřadit kefírové a kokosové mléko a mléčnou čokoládu; trvanlivé i čerstvé; časté víkendové akce jen s kartou |

---

## 2. Obchody a zdroje dat

Podrobnosti ke každému obchodu (URL, struktura odpovědí, pole, pasti) jsou
v [ZDROJE_DAT.md](ZDROJE_DAT.md). Přehled:

| Obchod | Hlavní zdroj | Doplněk | Pokrytí letáku strukturovanými daty | Náročnost |
|---|---|---|---|---|
| **Kaufland** | JSON v HTML `prodejny.kaufland.cz/nabidka/prehled.html` (`window.SSR`) | API letáků Schwarz (PDF s textem) | 100 % | nízká |
| **Tesco** | GraphQL e-shopu `xapi.tesco.com` (akce a Clubcard) | GraphQL letáků (seznam produktů v letáku bez cen) | ~100 % | nízká až střední |
| **Lidl** | JSON v HTML kampaňových stránek `lidl.cz/c/…` (`data-grid-data`) | API letáků Schwarz + PDF s textovou vrstvou → LLM | ~1/3 | střední |
| **Penny** | JSON API `penny.cz/api/product-discovery` | vektorová vrstva letáku FlippingBook (SVG) → parser nebo LLM | malá (33 položek týdně) | střední až vysoká |
| **Albert** | jen leták: GraphQL `getLeaflets` + Publitas (obrázky stránek) → vision LLM | PDF s textovou vrstvou jako kontrola | 0 % | střední |

Ověřeno na všech obchodech: **nikde není potřeba headless prohlížeč ani obcházení
ochrany proti botům**. Stačí HTTP klient Laravelu.

### Prodejny a varianty nabídky

| Obchod | Liší se nabídka podle prodejny? | Jak |
|---|---|---|
| Kaufland | mírně (desítky položek ze ~740) | cookie `x-aem-variant=CZxxxx`, seznam 149 prodejen v `.klstorefinder.json` |
| Tesco | podle formátu (hypermarket / supermarket) + výjimky | leták HM / SM; e-shop zvlášť ([R4](#8-log-rozhodnutí)) |
| Albert | podle formátu + lokální varianty | leták HM / SM, `getLeaflets` vrací seznam prodejen letáku |
| Lidl | celostátně, „Rozšířená nabídka“ jen ve vybraných prodejnách | příznak u položky |
| Penny | celostátně | — |

---

## 3. Technologie

Stejný stack jako projekt Počasí ([R2](#8-log-rozhodnutí)).

| Vrstva | Volba | Poznámka |
|---|---|---|
| Jazyk | PHP 8.4 | |
| Framework | Laravel 13 | HTTP klient (Guzzle) pro scrapery |
| Frontend | Inertia + Vue 3 | |
| Styly | SCSS, BEM, design tokeny v CSS proměnných | bez Tailwindu |
| Přihlášení | Laravel Fortify + vlastní Vue stránky | [R12](#8-log-rozhodnutí) |
| Databáze | MariaDB 11.4 | |
| Extrakce letáků | LLM s vision (Claude API) | etapa 6, model a rozpočet viz [O2](#7-otevřené-otázky) |
| Testy | Pest, Larastan level 8, Pint | HTTP odpovědi obchodů jako fixtures ([R11](#8-log-rozhodnutí)) |
| Vývoj | Docker Compose | PHP, Composer ani Node se lokálně neinstalují |

---

## 4. Datový model (návrh)

Návrh se upřesní v etapě 1. Obchody (řetězce) jsou pevný výčet `Chain` v kódu,
jejich nastavení je v `config/letaky.php`.

### `stores`: prodejny
| Sloupec | Význam |
|---|---|
| `chain` | `kaufland` / `tesco` / `albert` / `lidl` / `penny` |
| `external_id` | ID prodejny u obchodu (Kaufland `CZ3300`, Tesco `storeId`…) |
| `name`, `city`, `address`, `latitude`, `longitude` | |
| `format` | `hypermarket` / `supermarket` / null, pokud obchod formáty nerozlišuje |

### `leaflets`: zdroje nabídek (leták, kampaňová stránka, e-shop)
| Sloupec | Význam |
|---|---|
| `chain`, `kind` | obchod; `leaflet` / `web` / `eshop` |
| `external_id`, `title`, `source_url` | identifikace u obchodu, odkaz pro uživatele |
| `format` | pro letáky HM / SM |
| `valid_from`, `valid_to` | platnost, **místní datum** ([R7](#8-log-rozhodnutí)) |
| `fetched_at` | kdy se naposledy stáhl |

### `offers`: akční nabídky
| Sloupec | Význam |
|---|---|
| `chain`, `leaflet_id`, `store_id` | `store_id` null = platí pro všechny prodejny obchodu nebo formátu |
| `external_id` | ID položky u obchodu (Kaufland `klNr`, Lidl `productId`, Tesco `id`…), pro deduplikaci |
| `name`, `brand`, `description` | |
| `variant_note` | „různé druhy“, „vybrané druhy“ → párování „možná“ ([R9](#8-log-rozhodnutí)) |
| `package_text`, `quantity`, `unit` | balení: text a rozparsované množství (g / ml / ks) |
| `price`, `original_price`, `loyalty_price` | **v haléřích** ([R7](#8-log-rozhodnutí)); `loyalty_price` = cena s kartou nebo aplikací |
| `loyalty_program` | `kaufland_card` / `clubcard` / `muj_albert` / `lidl_plus` / `penny_karta` / null |
| `discount_percent`, `unit_price` | sleva podle obchodu; cena za kg / l / ks v haléřích |
| `offer_type` | `discount` / `everyday_price` / `loyalty_only` / `multibuy`… ([R8](#8-log-rozhodnutí)) |
| `online_only` | jen e-shop Tesco ([R4](#8-log-rozhodnutí)) |
| `purchase_limit` | „max. 3 balení na nákup“ |
| `valid_from`, `valid_to` | platnost položky (víkendové akce mají kratší než leták) |
| `category_id` | vlastní kategorie ([O3](#7-otevřené-otázky)) |
| `image_url`, `source_url` | odkazy u obchodu, obrázky se nestahují ([R5](#8-log-rozhodnutí)) |
| `raw` | JSON původní položky, aby se nic neztratilo |

### Uživatelé a hlídání
- `users`: účty (Fortify)
- `store_user`: vybrané prodejny uživatele
- `loyalty_program_user`: věrnostní programy, které uživatel má; podle nich se ukazuje cena s kartou jako dosažitelná
- `watch_items`: hlídané položky. Typ `product` / `category`, hledaný text, kategorie, volitelně značka
- `watch_matches`: shody hlídané položky s nabídkou. Stav `match` / `maybe`, důvod

### Provoz
- `scrape_runs`: každé stažení zdroje (obchod, zdroj, začátek, konec, stav, počet položek, chyba). **Nula položek u zdroje, který je obvykle má, je chyba**, ne „žádné akce“.
- `llm_extractions`: (etapa 6) vytěžené stránky letáků, aby se stránka neposílala do LLM dvakrát.

---

## 5. Toky dat

```
scheduler / cron (1–2× denně)
   └─▶ ImportChainOffers (pro každý obchod)
          ├─ zdroj obchodu (Sources/<Obchod>) ──HTTP──▶ web / API obchodu
          ├─ normalizace (balení, cena za jednotku, typ akce, platnost)
          ├─ deduplikace (stejná položka ve více kategoriích, web × leták)
          └─▶ offers  +  scrape_runs
                 │
                 ▼
          MatchWatchItems ──▶ watch_matches ──▶ seznam slev uživatele
```

Etapa 6 přidá extrakci letáků:

```
leták (obrázky stránek / PDF / SVG) ──▶ ExtractLeafletPage ──Claude API──▶ položky ──▶ normalizace ──▶ offers
```

Jak se úlohy spouštějí (scheduler a fronta, nebo cron volající URL), závisí na
hostingu ([O1](#7-otevřené-otázky)). Proto je každá úloha **Action** volatelná
z artisan příkazu i odjinud.

---

## 6. Etapy

| # | Obsah | Stav |
|---|---|---|
| 0 | Technický průzkum zdrojů dat všech 5 obchodů ([ZDROJE_DAT.md](ZDROJE_DAT.md)), dokumentace | hotovo 2026-10-02 |
| 1 | **Kostra:** Laravel 13, Docker, CI, Pint, Larastan, Pest, SCSS tokeny, layout; přihlášení a registrace (Fortify); model `stores` | |
| 2 | **Kaufland a Tesco:** zdroje, normalizace, `offers`, `leaflets`, `scrape_runs`, artisan příkaz importu; import seznamu prodejen; přehled všech nabídek s fulltextem | |
| 3 | **Hlídání:** výběr prodejen a věrnostních programů, hlídané položky (produkt / kategorie), párování podle pravidel (klíčová slova, vylučovací slova), seznam slev uživatele s cenou za jednotku | |
| 4 | **Lidl a Penny, strukturovaná část:** Lidl `data-grid-data` z kampaňových stránek, Penny product-discovery API | |
| 5 | **Kategorizace:** vlastní strom kategorií ([O3](#7-otevřené-otázky)), zařazení nabídek (pravidla, případně LLM), stav „možná“ pro „různé druhy“ | |
| 6 | **Extrakce letáků přes LLM:** Albert (obrázky stránek), Lidl (PDF), Penny (SVG vrstva); deduplikace proti strukturovaným datům | |
| 7 | Nasazení na produkci ([O1](#7-otevřené-otázky)) | |

---

## 7. Otevřené otázky

| # | Otázka | Stav |
|---|---|---|
| O1 | **Kde poběží produkce?** Shared hosting jako Počasí (Websupport: bez SSH, fronty a scheduleru, cron umí jen volat URL), nebo VPS? Rozhoduje o tom, jak se budou spouštět dlouhé úlohy: stažení PDF letáku 25–40 MB, desítky volání LLM, binárka `pdftotext` | |
| O2 | **LLM pro extrakci letáků:** konkrétní model a měsíční rozpočet. Objem je zhruba 400 stran letáků týdně (Albert ~100, Lidl ~250, Penny ~37), plus případná klasifikace kategorií | |
| O3 | **Kategorie:** vlastní strom, nebo převzít strukturu některého obchodu (Tesco `superDepartment` / `department`)? Jak jemně dělit (mléko → polotučné → trvanlivé / čerstvé)? | |
| O4 | **Seznamy prodejen** Tesco, Lidl a Penny: odkud je brát. Kaufland má `.klstorefinder.json`, Albert vrací prodejny u letáku, ostatní zatím neověřeno | |
| O5 | **„Různé druhy“:** jde konkrétní variantu dohledat? Hotspoty letáku Tesco obsahují jednotlivé varianty (COCA-COLA ZERO 1,5l), Albert má katalog `productSearch`. U Kauflandu a Penny zřejmě ne | |
| O6 | **Zveřejnění aplikace:** před zpřístupněním dalším lidem právně posoudit. Podmínky Tesco výslovně zakazují užití obsahu pro jinou než osobní potřebu, VOP Albert zakazují stahování obsahu e-shopu a aplikace; dále autorský zákon a právo pořizovatele databáze. Viz [R5](#8-log-rozhodnutí) | odloženo do zveřejnění |
| O7 | Obrázky produktů: zobrazovat odkazem na CDN obchodu, nebo vůbec? | |

---

## 8. Log rozhodnutí

| # | Datum | Rozhodnutí | Proč |
|---|---|---|---|
| R1 | 2026-10-02 | **Data přímo od obchodů**, ne z agregátorů (kupi.cz, akcniceny.cz) | Agregátory nemají veřejné API a jejich podmínky stahování zakazují. Průzkum ukázal, že u obchodů jde data získat přímo a bez obcházení ochran. |
| R2 | 2026-10-02 | **Stack jako Počasí:** PHP 8.4, Laravel 13, Inertia + Vue 3, SCSS (BEM a tokeny), MariaDB 11.4, Pest, Larastan, Pint, vývoj v Dockeru. Osobní projekt na GitHubu `R0mul0s` | Ověřený stack z vlastních projektů, stejná pravidla a nástroje. |
| R3 | 2026-10-02 | Uživatel si volí **konkrétní prodejny**. Data se stahují jen pro prodejny, které má aspoň jeden uživatel vybrané (Kaufland). U obchodů s celostátní nabídkou platí nabídka pro všechny prodejny, u Tesco a Albert podle formátu HM / SM | Nabídka Kauflandu se liší po prodejnách, ale stahovat všech 149 by bylo ~110 tisíc řádků týdně skoro bez rozdílu. Albert a Tesco mají odlišné letáky pro hypermarkety a supermarkety. |
| R4 | 2026-10-02 | **Akce e-shopu Tesco se zobrazují** s příznakem `online_only` | E-shop má ~5 100 akcí a leták ~1 200 produktů. Část akcí (Coca-Cola Zero 0,5 l s Clubcard) je jen online, a i ta informace je pro uživatele užitečná. |
| R5 | 2026-10-02 | **Právní stránka se ve fázi osobního použití neřeší.** I tak se ukládají jen fakta (název, cena, platnost) s odkazem na zdroj. Letáky, PDF ani fotky se nepřebírají a stahuje se šetrně (1–2× denně, pauzy mezi požadavky, respektuje robots.txt) | Aplikace je zatím jen pro autora. Kdyby se zveřejnila, je to otázka O6. Ukládat jen fakta je správně i tak a usnadní to pozdější zveřejnění. |
| R6 | 2026-10-02 | **Nejdřív strukturovaná data, extrakce letáků přes LLM až v etapě 6.** Pořadí zdrojů podle obchodů je v kapitole 2 | Kaufland a Tesco pokryjí 100 % letáku čistým JSON. Lidl a Penny pokryjí strukturovaně jen část, Albert nic. Vision LLM nad obrázkem stránky byl v průzkumu spolehlivější než parsování textu PDF (rozházené sloupce, ceny bez desetinné čárky, vlastní fonty). |
| R7 | 2026-10-02 | **Ceny jako celé číslo v haléřích**, platnost jako **místní datum** (`DATE`, Europe/Prague), časy běhů v UTC | Žádné chyby zaokrouhlení float. Penny posílá ceny v haléřích přímo. Akce platí po dnech, ale Albert a Tesco posílají UTC (`22:00Z` = půlnoc), takže převod musí proběhnout na hranici systému. |
| R8 | 2026-10-02 | **Typ akce a cena s kartou jsou samostatné atributy.** „Sleva“ je jen nabídka s původní cenou | Spousta položek v letáku nejsou slevy: Kaufland „AKCE! pouze“, Tesco „Super cena“ (shodná s běžnou), Penny „Jedinečná nabídka“, Lidl „Ušetřete %“ (úspora na ceně za jednotku). Ceny s kartou nebo aplikací mají všech 5 obchodů a uživatel kartu mít nemusí. |
| R9 | 2026-10-02 | **Dva typy hlídání:** konkrétní produkt (text a značka) a kategorie (bez značky). Výsledek párování má **tři stavy: shoda / možná / ne** | Souhrnné položky („Coca-Cola/Fanta/Sprite různé druhy“) Zero zahrnovat mohou, ale nemusí. Zamlčet je by bylo stejně špatně jako tvrdit shodu. Kategorie potřebuje vylučovací pravidla (vaječné těstoviny, mléčná čokoláda). |
| R10 | 2026-10-02 | **Nabídky ani letáky se nemažou**, zůstávají jako historie | Penny a Albert staré letáky z webu mažou. Historie umožní porovnat, jestli je „akce“ opravdu levnější než obvykle. |
| R11 | 2026-10-02 | **Testy nikdy nesahají na síť.** Odpovědi obchodů jsou fixtures v `tests/Fixtures/<obchod>/` uložené ze skutečných odpovědí | Neveřejná API se mění a testy musí být deterministické. Fixture zároveň dokumentuje tvar dat k danému datu. |
| R12 | 2026-10-02 | **Přihlášení přes Laravel Fortify** s vlastními Vue stránkami, ne starter kit | Starter kity Laravelu stojí na Tailwindu, což je v rozporu s pravidly stylování (SCSS a BEM). Fortify dodá backend (registrace, přihlášení, reset hesla, throttle) bez UI. |
