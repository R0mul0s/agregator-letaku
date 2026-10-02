<!--
  Agregátor letáků — plán projektu
  @author Roman Hlaváček
  @created 2026-10-02
-->

# Slevohlídka — plán projektu

Pracovní název byl Agregátor letáků; od 2. 10. 2026 se aplikace jmenuje **Slevohlídka**,
motto „Rychlý lovec slev“ (R34). Repozitář a technické názvy (`agregator-letaku`,
konfigurace `letaky.*`) zůstávají.

Webová aplikace, která hlídá akční nabídky z letáků českých obchodů. Uživatel si
zvolí obchody (u Tesca typ prodejny), zadá, co ho zajímá („Coca-Cola Zero“, „vejce“,
„polotučné mléko“), a aplikace mu ukáže, kde a za kolik je to právě ve slevě.

Obchody letáky publikují hlavně jako PDF nebo flipbooky v JS prohlížečích. Průzkum
2026-10-02 ale ukázal, že u většiny z nich jde akční nabídku získat i strukturovaně.
Zbytek letáku zatím pokrývají zmínky bez ceny ([R27](#8-log-rozhodnutí)), LLM jen pokud
bude potřeba ([R23](#8-log-rozhodnutí)).

- **Vývoj:** `http://localhost:54720` (Docker, viz [CLAUDE.md](../CLAUDE.md))
- **Produkce:** sdílený hosting Websupport, zatím nenasazeno ([R20](#8-log-rozhodnutí))
- **Repozitář:** [github.com/R0mul0s/agregator-letaku](https://github.com/R0mul0s/agregator-letaku), osobní projekt
- **Pravidla pro psaní kódu:** [CODING_GUIDELINES.md](CODING_GUIDELINES.md)
- **Zdroje dat jednotlivých obchodů (endpointy, pole, pasti):** [ZDROJE_DAT.md](ZDROJE_DAT.md)

### Co platí a co ne (stav k 2. 10. 2026)

Log rozhodnutí (kap. 8) se nepřepisuje — starší rozhodnutí nahrazují novější. Tady je výsledek:

| Platí | Neplatí (čím nahrazeno) |
|---|---|
| Obchody se sledují celé, u Tesca (a Albertu) podle **typu prodejny** HM / SM (R19, R21) | Výběr **konkrétních prodejen** a seznamy prodejen (R3 → R21), výběr prodejen Kauflandu (R19 → R21) |
| Kaufland: jedna výchozí varianta nabídky pro všechny prodejny (R15) | Stahování nabídky Kauflandu po prodejnách (R3 → R15, R21) |
| Bez LLM: Kaufland, Tesco, Lidl (kampaně na webu), Penny (API + parser SVG letáku) (R23, R25, R26) | LLM jako hlavní cesta pro letáky (R6 → R23); LLM jen v etapě 6, pokud bude potřeba |
| **Zmínky v letácích bez ceny** — Lidl a Penny (R27) | Vyhledávací API letáků Lidlu (zakázané v robots.txt) |
| Hlídaná položka = slova + varianta + vyloučení (R18); katalog produktů (R24, R28–R31) — kategorie ze stromu Tesca, produkty spravuje admin, přiřazení nabídek se ukládá s ručními opravami; hlídaná položka = produkt z katalogu, nebo vlastní slova | Dva oddělené typy hlídání produkt / kategorie (R9 → R18; tři stavy shody platí dál); vymýšlení vlastních kategorií (→ R28); šablony hlídaných položek v konfiguraci (→ produkty katalogu, R31) |
| Obrázky produktů odkazem na CDN obchodu (R22); loga obchodů jako soubory aplikace (R32) | Ukládání obrázků |
| Produkce Websupport, cron URL, SQL skripty migrací, bez fronty (R20) | GitHub CI (R14 — zatím ne) |

---

## 1. Rozsah a cíl

### Co systém dělá
- Jednou až dvakrát denně stáhne aktuální akční nabídku z obchodů **Kaufland, Tesco, Albert, Lidl a Penny**, včetně příštího týdne, pokud už je zveřejněný.
- Nabídky převede do jednotného tvaru: obchod, název, balení, cena, původní cena, cena s kartou nebo aplikací, cena za jednotku, platnost od–do a typ akce ([R7](#8-log-rozhodnutí), [R8](#8-log-rozhodnutí)).
- Uživatel si založí účet, vybere **obchody** (u Tesca typ prodejny, akce jen z e-shopu) a věrnostní programy, které používá ([R19](#8-log-rozhodnutí), [R21](#8-log-rozhodnutí)).
- Uživatel zadá **hlídané položky**, buď konkrétní produkt, nebo kategorii bez ohledu na značku ([R18](#8-log-rozhodnutí)).
- Zobrazí **seznam aktuálních slev** k hlídaným položkám ve sledovaných obchodech, porovnatelný podle ceny za jednotku.
- Ukáže i **zmínky v letácích bez ceny** — položka je na stránce letáku, ale cenu z něj neumíme přečíst ([R27](#8-log-rozhodnutí)).
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
| **Lidl** | JSON v HTML kampaňových stránek `lidl.cz/c/…` (`data-grid-data`) — hotovo, ~140 potravin týdně | API letáků Schwarz + PDF s textovou vrstvou → LLM | ~1/3 | střední |
| **Penny** | JSON API `penny.cz/api/product-discovery` + parser vektorové vrstvy letáku (R26) — hotovo, ~320 akcí týdně | LLM pro neověřené dlaždice letáku | API 33 položek, s letákem ~55 % cen letáku | střední až vysoká |
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

## 4. Datový model

Tabulky `leaflets`, `offers` a `scrape_runs` existují od etapy 2, tabulky hlídání od etapy 3.
Tabulka prodejen `stores` byla v etapách 1–3 a zrušila se (R21). Obchody (řetězce) jsou pevný výčet `Chain` v kódu, jejich nastavení je
v `config/letaky.php`.

### `leaflets`: zdroje nabídek (leták, kampaňová stránka, e-shop)
| Sloupec | Význam |
|---|---|
| `chain`, `kind` | obchod; `leaflet` / `web` / `eshop` |
| `external_id`, `title`, `source_url` | identifikace u obchodu, odkaz pro uživatele |
| `format` | pro letáky HM / SM |
| `valid_from`, `valid_to` | platnost, **místní datum** ([R7](#8-log-rozhodnutí)); null u průběžných akcí e-shopu |
| `fetched_at` | kdy se naposledy stáhl |

Klíč je `chain` + `kind` + `external_id` (Kaufland `nabidka-2026-09-30`, Tesco `708`, e-shop Tesco `eshop`).

### `offers`: akční nabídky
| Sloupec | Význam |
|---|---|
| `chain`, `leaflet_id` | obchod a zdroj; nabídka e-shopu Tesco, která je v letáku, patří k letáku ([R17](#8-log-rozhodnutí)) |
| `store_format` | `hypermarket` / `supermarket`; null = všechny prodejny obchodu |
| `scrape_run_id`, `withdrawn_at` | stažení, ve kterém se nabídka naposledy objevila; kdy ji obchod stáhl před koncem platnosti ([R16](#8-log-rozhodnutí)) |
| `external_id` | ID položky u obchodu (Kaufland `klNr`, Tesco `id` produktu…) |
| `name`, `brand`, `description` | |
| `variant_note` | „různé druhy“, „vybrané druhy“ → párování „možná“ ([R9](#8-log-rozhodnutí)) |
| `package_text`, `quantity`, `unit` | balení: text obchodu a rozparsované množství (g / ml / ks); nejednoznačné („250 ml/500 ml“) bez množství |
| `price`, `original_price`, `loyalty_price` | **v haléřích** ([R7](#8-log-rozhodnutí)); `price` = bez karty (null, když ji obchod neuvádí), `loyalty_price` = s kartou nebo aplikací |
| `loyalty_program` | `kaufland_card` / `clubcard` / `muj_albert` / `lidl_plus` / `penny_karta` / null |
| `discount_percent` | sleva podle obchodu, jen u typu `discount` |
| `offer_type` | `discount` / `promo_price` / `loyalty_only` / `multibuy` ([R8](#8-log-rozhodnutí)) |
| `promotion_text` | popis akce od obchodu („3 za cenu 2“, „Více než o polovinu nižší cena s Clubcard“) |
| `online_only` | jen e-shop Tesco ([R4](#8-log-rozhodnutí)) |
| `valid_from`, `valid_to` | platnost položky (víkendové akce mají kratší než leták) |
| `source_category` | kategorie u obchodu |
| `image_url`, `source_url` | odkazy u obchodu, obrázky se nestahují ([R5](#8-log-rozhodnutí)) |
| `raw` | JSON původní položky, aby se nic neztratilo |

Klíč je `chain` + `external_id` + `valid_from` + `valid_to`. Cena za jednotku se nepočítá
do sloupce, ale při zobrazení z ceny a množství (`UnitPrice`). Přibudou `category_id`
(etapa 5) a `purchase_limit` (až ho bude některý zdroj dodávat: Lidl, Penny, Albert).

### `leaflet_pages`: text stránek letáku (R27)
| Sloupec | Význam |
|---|---|
| `leaflet_id`, `number` | leták (`kind = leaflet`) a číslo stránky od 1; klíč |
| `text` | slova stránky — Lidl `keyWords` + `altText` z API letáků, Penny text vektorové vrstvy |
| `image_url`, `page_url` | náhled stránky na CDN obchodu (Lidl), odkaz na stránku v prohlížeči letáku |

Stránka se při dalším stažení přepíše. Slouží jen pro zmínky bez ceny v Mých slevách.

### Katalog produktů (etapa 5, R28–R30)
- `categories`: strom kategorií převzatý z e-shopu Tesco — `parent_id`, `name`, `source_id` (ID uzlu u Tesca), `depth` (0 oddělení … 3 police), `position`; nemaže se
- `products`: `name` (jedinečný), `category_id` (nepovinně), `keywords`, `variant_keywords`, `exclude_keywords` — pravidla jako hlídaná položka (R18)
- `offer_product`: přiřazení nabídky k produktu — `status` (shoda / možná), `is_manual`; automatická přiřazení neskončených nabídek se přepočítají po importu obchodu a po uložení produktu, ruční zůstávají
- `offer_product_exclusions`: ruční „sem nepatří“, přepočet je přeskočí
- `users.is_admin`: smí spravovat katalog (`/katalog`); nastavuje příkaz `letaky:admin {email}`

### Uživatelé a hlídání (etapa 3)
- `users`: účty (Fortify); `loyalty_programs` = JSON seznam karet a aplikací, které uživatel má ([R19](#8-log-rozhodnutí))
- `followed_chains`: sledované obchody — `chain`, `store_format` (null = všechny typy prodejen), `include_online_only` ([R19](#8-log-rozhodnutí))
- `watch_items`: hlídané položky — `name`, `product_id` (produkt katalogu, R31) nebo vlastní `keywords`, `variant_keywords`, `exclude_keywords` ([R18](#8-log-rozhodnutí))

Shody hlídaných položek s nabídkami se neukládají, počítají se při zobrazení ([R19](#8-log-rozhodnutí)).
Tabulka `watch_matches` přibude s upozorněními (TODO).

### Provoz
- `scrape_runs`: každé stažení obchodu (začátek, konec, stav, počet uložených a stažených nabídek, chyba). **Nula položek je chyba**, ne „žádné akce“.
- `llm_extractions`: (etapa 6) vytěžené stránky letáků, aby se stránka neposílala do LLM dvakrát.

---

## 5. Toky dat

```
php artisan letaky:import-offers [obchod…]      (zatím ručně, na produkci cron URL — R20)
   └─▶ ImportChainOffers (pro každý obchod, selhání jednoho nezastaví ostatní)
          ├─ zdroj obchodu (Sources/<Obchod>, SourceHttp s pauzami) ──HTTP──▶ web / API obchodu
          │     └─ převod na OfferData: cena v haléřích, balení, typ akce, místní platnost
          ├─ upsert leaflets + offers (deduplikace podle klíče), v jedné transakci
          ├─ neskončené nabídky obchodu, které chyběly → withdrawn_at (R16)
          ├─ AssignProducts::forChain: nabídky obchodu → produkty katalogu (offer_product, R30)
          └─▶ scrape_runs (úspěch / chyba)

php artisan letaky:import-categories            (občas; strom kategorií e-shopu Tesco, R28)
/katalog (admin): uložení produktu → AssignProducts::forProduct; „sem patří / nepatří“ → CorrectAssignment
```

Moje slevy (etapa 3) se počítají při zobrazení stránky:

```
GET / ──▶ MyOffers::forUser
             ├─ položka z katalogu (R31): nabídky z offer_product jejího produktu (sledované obchody)
             ├─ kandidáti: neskončené a nestažené nabídky sledovaných obchodů (typ prodejny,
             │  akce jen z e-shopu), které obsahují první slovo některé hlídané položky (SQL LIKE)
             ├─ WatchItemMatcher: všechna slova, vyloučení, varianta → shoda / možná (R18, R9)
             ├─ akce jen s kartou, kterou uživatel nemá, vynechá (R19)
             ├─ řazení: shody, pak akce s cenou od nejnižší ceny za jednotku (s kartou, pokud ji má),
             │  akce na více kusů, nakonec „možná“
             └─ zmínky bez ceny (R27): stránky neskončených letáků sledovaných obchodů (SQL LIKE),
                bez stránek s receptem, celá slova bez vyloučení, chybí varianta → „možná“;
                leták, kde má obchod k položce akci s cenou ve stejném období, se přeskočí
```

Doba stažení (2. 10. 2026): Kaufland ~2 s (1 požadavek, příští týden +1),
Tesco ~45 s (seznam letáků, 2 letáky, 26 stránek akcí po 200 s pauzou 1,5 s),
Lidl ~30 s (~40 kampaní s pauzou 0,5 s), Penny ~23 s (API + ~37 stran letáku s pauzou 0,5 s).
Všechny obchody najednou ~1,5 minuty — na hostingu poběží každý obchod samostatně (O8).

Etapa 6 přidá extrakci letáků:

```
leták (obrázky stránek / PDF / SVG) ──▶ ExtractLeafletPage ──Claude API──▶ položky ──▶ normalizace ──▶ offers
```

Produkce na Websupportu nemá scheduler ani frontu ([R20](#8-log-rozhodnutí)): úlohy bude
spouštět cron WebAdminu voláním URL s tokenem. Proto je každá úloha **Action** volatelná
z artisan příkazu i z kontroleru.

---

## 6. Etapy

| # | Obsah | Stav |
|---|---|---|
| 0 | Technický průzkum zdrojů dat všech 5 obchodů ([ZDROJE_DAT.md](ZDROJE_DAT.md)), dokumentace | hotovo 2026-10-02 |
| 1 | **Kostra:** Laravel 13, Docker, Pint, Larastan, Pest, SCSS tokeny, layout; přihlášení a registrace (Fortify) | hotovo 2026-10-02 |
| 2 | **Kaufland a Tesco:** zdroje, normalizace, `offers`, `leaflets`, `scrape_runs`, artisan příkaz importu; přehled všech nabídek s hledáním (`/akce`); stažené nabídky (R16) | hotovo 2026-10-02 |
| 3 | **Hlídání:** výběr obchodů s upřesněním a věrnostních karet (`/obchody`), hlídané položky se slovy, variantou a vyloučením a šablonami (`/hlidam`), Moje slevy seřazené podle ceny za jednotku (`/`) | hotovo 2026-10-02 |
| 4 | **Lidl a Penny bez LLM** (R23, R25, R26): Lidl `data-grid-data` z kampaní (potraviny), Penny product-discovery API a parser vektorové vrstvy letáku ověřený cenou za jednotku | hotovo 2026-10-02 |
| 4b | **Zmínky v letácích bez ceny** (R27): text stránek letáků Lidl (API letáků Schwarz) a Penny (vektorová vrstva), sekce „V letáku, ale bez ceny“ v Mých slevách | hotovo 2026-10-02 |
| 5 | **Katalog produktů** ([O3](#7-otevřené-otázky), R24, R28–R31, návrh v kap. 7): 5a strom kategorií z e-shopu Tesco (`categories`, `letaky:import-categories`); 5b produkty se slovy a správa katalogu pro admina (`/katalog`, `letaky:admin`), přiřazení nabídek při importu s ručními opravami (`offer_product`); 5c hlídaná položka z katalogu nebo s vlastními slovy, šablony nahradí produkty | hotovo 2026-10-02 |
| 6 | **LLM** (R23), jen pokud bude potřeba: Albert (obrázky stránek), zbytek letáku Lidlu, třídění nepřiřazených nabídek | |
| 7 | **Nasazení na Websupport** (R20): cron URL pro stahování, SQL skripty migrací, build a nahrání přes FTP, DEPLOYMENT.md, ověření O8 | |

---

## 7. Otevřené otázky

| # | Otázka | Stav |
|---|---|---|
| O1 | **Kde poběží produkce?** Shared hosting jako Počasí (Websupport: bez SSH, fronty a scheduleru, cron umí jen volat URL), nebo VPS? | rozhodnuto (R20): Websupport |
| O2 | **LLM pro extrakci letáků:** je potřeba a jde na shared hostingu? | rozhodnuto (R23): zatím bez LLM; jde to (jen volání API), rozhodne se u Albertu |
| O3 | **Kategorie:** jak párovat „polotučné mléko“, když obchod píše jen „tuk 1,5 %“, a jak nehlídat stejná pravidla u každého uživatele zvlášť? Návrh: sdílený katalog produktů se štítky a pravidly, nabídky se k produktům přiřadí automaticky při importu — viz *Návrh katalogu produktů* níž | rozhodnuto (R24): katalog produktů, etapa 5 |
| O4 | **Seznamy prodejen** Tesco, Lidl a Penny: odkud je brát | rozhodnuto (R21): nejsou potřeba, výběr prodejen i jejich seznam zrušené |
| O5 | **„Různé druhy“:** jde konkrétní variantu dohledat? Hotspoty letáku Tesco obsahují jednotlivé varianty (COCA-COLA ZERO 1,5l), Albert má katalog `productSearch`. U Kauflandu a Penny zřejmě ne | zatím stačí stav „Možná“ (R18); zpřesnění v [TODO.md](TODO.md) |
| O6 | **Zveřejnění aplikace:** před zpřístupněním dalším lidem právně posoudit. Podmínky Tesco výslovně zakazují užití obsahu pro jinou než osobní potřebu, VOP Albert zakazují stahování obsahu e-shopu a aplikace; dále autorský zákon a právo pořizovatele databáze. Viz [R5](#8-log-rozhodnutí) | odloženo do zveřejnění |
| O7 | Obrázky produktů: zobrazovat odkazem na CDN obchodu, nebo vůbec? | rozhodnuto (R22): odkazem |
| O8 | **Jak dlouho smí na Websupportu běžet PHP požadavek** (`max_execution_time`, timeout proxy)? Stažení trvá Tesco ~45 s, Lidl ~30 s, Penny ~23 s, Kaufland ~2 s — cron URL proto po obchodech. Ověřit při nasazení; když nestačí, kratší pauza nebo stažení po částech | ověřit při nasazení |

### Katalog produktů (k O3, R24)

Dnes si každý uživatel píše pravidla sám (R18). Návrh je mít **sdílený katalog**, ke kterému
se nabídky přiřadí automaticky při importu:

- **Produkt** = věc, kterou člověk hledá, bez ohledu na obchod: „Vejce“, „Polotučné mléko“,
  „Máslo“, „Coca-Cola Zero“. Volitelně ve stromu kategorií (Mléčné výrobky → Mléko → Polotučné).
- **Štítky a pravidla produktu** = pod čím je dohledatelný: hledaná slova a alternativy
  („polotučné | 1,5 %“), vyloučená slova, případně značka a varianta. To jsou dnešní šablony,
  jen sdílené a udržované na jednom místě.
- **Přiřazení** nabídka → produkt se spočítá při každém importu (tabulka `offer_product`) a jde
  ručně opravit („tahle nabídka sem nepatří“). Ruční značení každé nabídky nejde — je jich
  ~6 000 týdně.
- **Hlídaná položka** pak vybírá produkt z katalogu (a volitelně zúží značkou nebo variantou);
  vlastní slova zůstanou jako možnost pro věci, které v katalogu nejsou.
- Nepřiřazené nabídky jde později třídit pomocí LLM (návrh nového produktu nebo zařazení).

Upřesnění 2026-10-02 (R28–R31): katalog spravuje admin na stránce `/katalog`, kategorie
jsou převzaté ze stromu e-shopu Tesco, přiřazení se ukládá při importu s ručními opravami
a hlídaná položka je buď produkt z katalogu, nebo vlastní slova.

Konkrétní výrobek napříč obchody (stejné EAN) se párovat nedá — Kaufland EAN má jen v URL
obrázku, Tesco vůbec. Produkt je proto úroveň „co hledám“, ne čárový kód.

---

## 8. Log rozhodnutí

| # | Datum | Rozhodnutí | Proč |
|---|---|---|---|
| R1 | 2026-10-02 | **Data přímo od obchodů**, ne z agregátorů (kupi.cz, akcniceny.cz) | Agregátory nemají veřejné API a jejich podmínky stahování zakazují. Průzkum ukázal, že u obchodů jde data získat přímo a bez obcházení ochran. |
| R2 | 2026-10-02 | **Stack jako Počasí:** PHP 8.4, Laravel 13, Inertia + Vue 3, SCSS (BEM a tokeny), MariaDB 11.4, Pest, Larastan, Pint, vývoj v Dockeru. Osobní projekt na GitHubu `R0mul0s` | Ověřený stack z vlastních projektů, stejná pravidla a nástroje. |
| R3 | 2026-10-02 | **Neplatí — nahrazeno R15 a R21.** Uživatel si volí **konkrétní prodejny**. Data se stahují jen pro prodejny, které má aspoň jeden uživatel vybrané (Kaufland). U obchodů s celostátní nabídkou platí nabídka pro všechny prodejny, u Tesco a Albert podle formátu HM / SM | Nabídka Kauflandu se liší po prodejnách, ale stahovat všech 149 by bylo ~110 tisíc řádků týdně skoro bez rozdílu. Albert a Tesco mají odlišné letáky pro hypermarkety a supermarkety. |
| R4 | 2026-10-02 | **Akce e-shopu Tesco se zobrazují** s příznakem `online_only` | E-shop má ~5 100 akcí a leták ~1 200 produktů. Část akcí (Coca-Cola Zero 0,5 l s Clubcard) je jen online, a i ta informace je pro uživatele užitečná. |
| R5 | 2026-10-02 | **Právní stránka se ve fázi osobního použití neřeší.** I tak se ukládají jen fakta (název, cena, platnost) s odkazem na zdroj. Letáky, PDF ani fotky se nepřebírají a stahuje se šetrně (1–2× denně, pauzy mezi požadavky, respektuje robots.txt) | Aplikace je zatím jen pro autora. Kdyby se zveřejnila, je to otázka O6. Ukládat jen fakta je správně i tak a usnadní to pozdější zveřejnění. |
| R6 | 2026-10-02 | **Upřesněno R23 (LLM zatím ne).** **Nejdřív strukturovaná data, extrakce letáků přes LLM až v etapě 6.** Pořadí zdrojů podle obchodů je v kapitole 2 | Kaufland a Tesco pokryjí 100 % letáku čistým JSON. Lidl a Penny pokryjí strukturovaně jen část, Albert nic. Vision LLM nad obrázkem stránky byl v průzkumu spolehlivější než parsování textu PDF (rozházené sloupce, ceny bez desetinné čárky, vlastní fonty). |
| R7 | 2026-10-02 | **Ceny jako celé číslo v haléřích**, platnost jako **místní datum** (`DATE`, Europe/Prague), časy běhů v UTC | Žádné chyby zaokrouhlení float. Penny posílá ceny v haléřích přímo. Akce platí po dnech, ale Albert a Tesco posílají UTC (`22:00Z` = půlnoc), takže převod musí proběhnout na hranici systému. |
| R8 | 2026-10-02 | **Typ akce a cena s kartou jsou samostatné atributy.** „Sleva“ je jen nabídka s původní cenou | Spousta položek v letáku nejsou slevy: Kaufland „AKCE! pouze“, Tesco „Super cena“ (shodná s běžnou), Penny „Jedinečná nabídka“, Lidl „Ušetřete %“ (úspora na ceně za jednotku). Ceny s kartou nebo aplikací mají všech 5 obchodů a uživatel kartu mít nemusí. |
| R9 | 2026-10-02 | **Typy hlídání nahrazeny R18; tři stavy shody platí.** **Dva typy hlídání:** konkrétní produkt (text a značka) a kategorie (bez značky). Výsledek párování má **tři stavy: shoda / možná / ne** | Souhrnné položky („Coca-Cola/Fanta/Sprite různé druhy“) Zero zahrnovat mohou, ale nemusí. Zamlčet je by bylo stejně špatně jako tvrdit shodu. Kategorie potřebuje vylučovací pravidla (vaječné těstoviny, mléčná čokoláda). |
| R10 | 2026-10-02 | **Nabídky ani letáky se nemažou**, zůstávají jako historie | Penny a Albert staré letáky z webu mažou. Historie umožní porovnat, jestli je „akce“ opravdu levnější než obvykle. |
| R11 | 2026-10-02 | **Testy nikdy nesahají na síť.** Odpovědi obchodů jsou fixtures v `tests/Fixtures/<obchod>/` uložené ze skutečných odpovědí | Neveřejná API se mění a testy musí být deterministické. Fixture zároveň dokumentuje tvar dat k danému datu. |
| R12 | 2026-10-02 | **Přihlášení přes Laravel Fortify** s vlastními Vue stránkami, ne starter kit | Starter kity Laravelu stojí na Tailwindu, což je v rozporu s pravidly stylování (SCSS a BEM). Fortify dodá backend (registrace, přihlášení, reset hesla, throttle) bez UI. |
| R13 | 2026-10-02 | **Fortify jen s registrací, přihlášením, obnovou hesla, úpravou profilu a změnou hesla.** Dvoufázové ověření, passkeys a ověření e-mailu jsou vypnuté. Přihlášení má limit pokusů na dvojici e-mail + IP (`letaky.auth.login_attempts_per_minute`). Session v databázi, fronta zatím `sync` (O1). Adresy formulářů posílá server v props, routy Fortify nejsou ve Vue natvrdo | Aplikace je zatím jen pro autora (R5), další vrstvy zabezpečení by přidaly stránky a tabulky bez užitku. Jdou zapnout v `config/fortify.php`, až se aplikace zveřejní. Session v databázi (na rozdíl od cookie v Počasí) jde u uživatelských účtů zrušit smazáním řádku. |
| R14 | 2026-10-02 | **CI v GitHub Actions zatím není**, kontroly kvality se pouštějí jen ručně před commitem | V rané fázi projektu zbytečné, rozhodnutí autora. Workflow jde převzít z Počasí (`.github/workflows/ci.yml`), až bude potřeba. |
| R15 | 2026-10-02 | **Kaufland zatím jen výchozí varianta nabídky** (bez cookie prodejny, odpovídá CZ3300), platná pro všechny prodejny. Upřesňuje R3 | Rozdíl mezi prodejnami je v desítkách položek ze ~740 (CZ3300 × CZ4600: 736 × 732). Stahovat každou vybranou prodejnu zvlášť by násobilo požadavky i řádky skoro bez užitku. Varianty po prodejnách jsou v [TODO.md](TODO.md). |
| R16 | 2026-10-02 | **Nabídka, kterou obchod stáhne nebo změní před koncem platnosti, se označí `withdrawn_at`** a z výpisů zmizí; nemaže se (R10). Pozná se tak, že v novém úplném stažení obchodu chybí (`scrape_run_id` není poslední stažení). Když se znovu objeví, označení zmizí | Kaufland během 2. 10. 2026 zkrátil akci na vejce z 6. 10. na 2. 10. Ranní záznam by bez toho dál tvrdil, že akce platí do 6. 10. Funguje jen proto, že každý zdroj stahuje celou nabídku obchodu najednou. |
| R17 | 2026-10-02 | **Tesco: ceny z akcí e-shopu, leták jen určuje, kde akce platí.** Produkt e-shopu se páruje s produktem letáku podle **posledních 8 číslic ID** (leták `2001019279706` = e-shop `219279706`) a překryvu platnosti. V letáku HM i SM = všechny prodejny, v jednom = jeho formát, v žádném = jen online (R4). Katalog (CAT) se nesleduje. Položky letáku bez akce v e-shopu („Super cena“ za běžnou cenu) chybí | Ceny jsou jen v e-shopu, PDF letáku má poškozenou textovou vrstvu. Pravidlo 8 číslic ověřené na celém letáku 2. 10. 2026: HM 880 z 1 210 produktů, SM 237 z 277, žádná kolize v 5 139 produktech e-shopu; nespárované jsou hlavně „Super ceny“ a zboží bez akce online. „Super ceny“ by šly doplnit vision LLM (etapa 6). |
| R18 | 2026-10-02 | **Hlídaná položka = název + hledaná slova + varianta + vyloučení**, místo dvou typů produkt / kategorie. Slova se hledají v názvu, značce a popisu nabídky jako **začátek slova**, bez diakritiky a velikosti písmen; všechna musí být v nabídce, alternativy přes „\|“ („mléko polotučné\|1,5“). Chybí-li varianta („zero“) u nabídky „různé druhy“, je shoda **možná** (R9). Kterékoli vyloučené slovo nabídku vyřadí. Šablony (vejce, polotučné mléko, máslo, Coca-Cola Zero) v `config/letaky.php` předvyplní formulář | Rozhodnutí uživatele. Jeden zápis pokryje produkt („coca cola“ + „zero“) i kategorii („vejce“ bez značky) a funguje hned, bez kategorizace (etapa 5). Začátek slova kvůli českým koncovkám („vejce“ najde „vejcem“) — proto ale „máslo“ najde i „máslová dýně“; šablony mají vyloučení ze skutečných nabídek 2. 10. 2026 („máslov“, „ruské“, „lipánek“, „maggi“). Obchody „polotučné“ často nepíšou, proto alternativa „1,5“. |
| R19 | 2026-10-02 | **Výběr prodejen Kauflandu zrušen R21, zbytek platí.** **Sledují se obchody s upřesněním:** u Tesca typ prodejny (nabídka bez typu platí všude) a akce jen z e-shopu, u Kauflandu výběr prodejen; k tomu karty a aplikace, které uživatel má. **Akce jen s kartou, kterou uživatel nemá, se v Mých slevách neukáže**; s kartou se řadí podle ceny s kartou. Shody se počítají při zobrazení, neukládají se | Rozhodnutí uživatele („obchody + upřesnění“) — seznam prodejen zatím má jen Kaufland. Akce jen s kartou bez karty není akce. Nabídek je tisíce a hlídaných položek jednotky: SQL předvybere kandidáty podle prvního slova, pravidla se vyhodnotí v PHP za desítky milisekund; tabulka shod by se musela přepočítávat po každém importu i úpravě položky. Bude potřeba až pro upozornění. |
| R20 | 2026-10-02 | **Produkce na sdíleném hostingu Websupport** (O1), stejně jako Počasí: Apache + PHP 8.4, MariaDB 11.4, bez SSH, composeru, fronty a scheduleru; cron ve WebAdminu umí jen zavolat URL. Z toho: (1) stahování spouští **cron URL s tokenem** (`/cron/…?token=`), Actions jsou na to připravené; (2) **každá migrace potřebuje SQL skript** v `deploy/` — před prvním nasazením jeden úvodní skript za všechny dosavadní migrace; (3) fronta zůstává `sync`, nic `ShouldQueue`; (4) nasazení balíčkem přes FTP jako Počasí; (5) e-maily (obnova hesla) přes SMTP Websupportu | Rozhodnutí uživatele. Hosting už provozuje Počasí — známé omezení i postup nasazení. Dlouhé úlohy (Tesco ~45 s, případné LLM) se musí vejít do jednoho požadavku nebo se rozdělit (O8). |
| R21 | 2026-10-02 | **Seznamy prodejen nejsou potřeba** (O4). Nabídka se rozlišuje jen tam, kde obchod má odlišné letáky — podle **typu prodejny** (Tesco a Albert HM / SM, R19). Výběr prodejen Kauflandu i stahování seznamu prodejen (tabulky `stores`, `store_user`, příkaz `letaky:import-stores`) se zrušily — nic neovlivňovaly (R15) | Rozhodnutí uživatele: jde jen o to, jestli mají prodejny rozdílné letáky. Kód zůstává v historii gitu (`5b2a82d`). |
| R22 | 2026-10-02 | **Obrázky produktů se zobrazují odkazem na CDN obchodu** (O7), nestahují se ani neukládají (R5). `referrerpolicy="no-referrer"`, líné načítání | Rozhodnutí uživatele. Fotka rozliší varianty, které názvy pletou (Coca-Cola Zero × Zero Zero). CDN Tesca (Akamai) blokuje jen „HeadlessChrome“ — běžný prohlížeč obrázky dostane; snímky obrazovky v testech potřebují běžný User-Agent. Produkční CSP musí povolit `img-src` pro CDN obchodů. |
| R23 | 2026-10-02 | **LLM zatím ne** (O2). Etapa 4 (Lidl, Penny) jen ze strukturovaných dat a bez LLM: Lidl JSON z webu, Penny API a **parser vektorové vrstvy letáku Penny** (text s pozicemi, deterministicky). LLM se rozhodne u Albertu, který jinou cestu nemá. Na sdíleném hostingu jde — je to jen HTTPS volání API, žádný model neběží u nás; stránky letáku by se zpracovávaly po dávkách v cron URL a výsledek ukládal, aby se stránka neposílala dvakrát | Bez LLM: Kaufland a Tesco úplně, Lidl ~1/3 letáku, Penny API malý výběr + SVG parser většinu letáku, Albert nic. `pdftotext` na hostingu není; PDF Lidlu by šlo jen čistě PHP knihovnou se stejným problémem s pořadím sloupců jako v průzkumu. |
| R24 | 2026-10-02 | **Katalog produktů** (O3): sdílené produkty se štítky a pravidly, nabídky se k nim přiřazují automaticky při importu a jde to ručně opravit; hlídaná položka vybírá produkt z katalogu, vlastní slova zůstávají jako možnost. Návrh v kap. 7, etapa 5 | Rozhodnutí uživatele („produkty otagované tím, pod čím budou dohledatelné“). Ruční značení každé nabídky nejde (~6 000 týdně); pravidla psaná každým uživatelem zvlášť se opakují. |
| R25 | 2026-10-02 | **Lidl: všechny kampaně z úvodní stránky, ukládají se jen potraviny** (`category: Food`). Pauza mezi požadavky na Lidl a Penny 0,5 s (`request_delay_ms` u zdroje), jinde 1,5 s | Kampaně (~40) nejdou podle adresy rozlišit na potravinové a nepotravinové. Aplikace hlídá potraviny; nepotravinové akce jsou v TODO. Kratší pauza drží stažení pod půl minuty kvůli limitu hostingu (O8) — stránky jsou malé a CDN ani při ní neblokovala. |
| R26 | 2026-10-02 | **Leták Penny bez LLM: dlaždice se přijme, jen když ji ověří cena za jednotku** — cena přepočtená na balení musí dát uvedenou cenu za jednotku (balení 1 kg / 1 l / 1 ks bez ní jen přímo nad cenou). Neověřitelné dlaždice a dlaždice s PENNY kartou se neuloží; položka, kterou nese API (stejná cena a balení), se nezdvojí | Rozvržení stránek se liší a pevné okno kolem ceny přiřazovalo názvy sousedních dlaždic. Kontrola ceny za jednotku dá ~300 akcí z ~560 cen bez chybného přiřazení, mj. polotučné mléko, které je jen v letáku. Chybějící akce je lepší než akce se špatnou cenou. Zbytek může doplnit LLM nad tokeny stránky (etapa 6). |
| R27 | 2026-10-02 | **Zmínky v letácích bez ceny.** Ukládá se text stránek letáků (`leaflet_pages`): Lidl z API letáků Schwarz (`keyWords` + `altText` stránky, jen potravinové letáky „akcni-letak-od-…“), Penny z vektorové vrstvy. Moje slevy k položce ukážou i stránky letáku, které ji zmiňují: hledají se **celá slova** (ne začátky), **bez vyloučených slov**, chybí-li varianta, je zmínka „možná“. Stránky s receptem („postup přípravy“, „nákupní seznam“, „recept na“ — `letaky.mentions.excluded_page_phrases`) se přeskočí. Leták, ve kterém má obchod k položce akci s cenou ve stejném období, zmínku nemá | Rozhodnutí uživatele: „když nebudeme mít cenu, budeme alespoň vědět, že je tam nějaká Milka“. Lidl má na webu jen ~1/3 letáku a vyhledávání v letáku prohledává jen `keyWords` (slova stránky bez pořadí, bez vazby na ceny) — cenu z nich přiřadit nejde. Stránka je směs desítek produktů: vyloučené slovo by vyřadilo celou stránku kvůli jinému produktu a začátek slova by „máslo“ našel v „Dýně máslová“. Recepty vyjmenovávají vejce a máslo, které v akci nejsou (Lidl 8. 10. 2026, str. 18 a 49). |
| R28 | 2026-10-02 | **Kategorie = strom e-shopu Tesco** (dotaz `taxonomy` na `xapi.tesco.com`, 4 úrovně: oddělení › sekce › regál › police, 2. 10. 2026: 15 › 137 › 745 › 1 340). Ukládá se jako vlastní snímek v tabulce `categories` (`parent_id`, `name`, `source_id` = ID uzlu u Tesca, `depth`, `position`), stahuje ho `letaky:import-categories`; opakované stažení aktualizuje podle `source_id`, nic nemaže. Vynechají se marketingová a nepotravinová oddělení z konfigurace (`letaky.categories.excluded_roots`: „Top výběr“, „Novinky“, „Domov a zábava“) | Rozhodnutí uživatele („nemůžeme to z něčeho rovnou přebrat?“). Strom Tesca je česky, dělaný pro potraviny a drogerii a nejnižší úroveň je skoro produkt („Polotučné mléko“, „Máslo“, „Vejce“). Google Product Taxonomy (cs-CZ) má jen 364 potravinových kategorií, strojový překlad a mléko pod „Nápoji“. Vlastní snímek = katalog nezávisí na tom, jestli Tesco strom změní. |
| R29 | 2026-10-02 | **Produkty katalogu spravuje admin** na stránce `/katalog` (`users.is_admin`, zatím jen autor). Produkt (`products`) = název, police stromu kategorií (nepovinně) a pravidla jako hlídaná položka (slova, varianta, vyloučení — R18, `WatchRule`). Výchozí produkty vzniknou z dnešních šablon (vejce, polotučné mléko, máslo, Coca-Cola Zero) | Rozhodnutí uživatele (admin stránka v aplikaci). Pravidla jsou stejná jako u hlídané položky, takže párování a jeho testy zůstávají jedny. |
| R30 | 2026-10-02 | **Přiřazení nabídek k produktům se ukládá** (`offer_product`: nabídka, produkt, stav shoda / možná, ručně ano/ne) a přepočítá se po importu obchodu a po uložení produktu. **Ruční opravy přežijí přepočet:** „sem patří“ = ruční řádek, „sem nepatří“ = záznam v `offer_product_exclusions`, který automatické přiřazení přeskočí | Rozhodnutí uživatele. Ruční opravy potřebují uložené přiřazení; zároveň je to základ pro upozornění (TODO). Přepočet po importu je levný — pravidla jednotek až desítek produktů nad tisíci nabídek. |
| R31 | 2026-10-02 | **Hlídaná položka = produkt z katalogu, nebo vlastní slova** (`watch_items.product_id`, slova jsou pak nepovinná). Produkt hlídá uživatel nejvýš jednou (unikátní `user_id` + `product_id`); v Hlídám se produkt přidá klepnutím v seznamu katalogu, vlastní slova mají samostatný formulář. U produktu se nabídky berou z `offer_product`, zmínky v letácích podle pravidel produktu. Šablony v konfiguraci nahradí produkty katalogu | Rozhodnutí uživatele. Vlastní slova zůstávají pro věci, které v katalogu nejsou. |
| R32 | 2026-10-02 | **Loga obchodů jsou statické soubory aplikace** (`public/images/chains/{chain}.svg`, zdroj a datum v hlavičce souboru: Wikimedia Commons, Albert z albert.cz) a ukazují se všude, kde se obchod zmiňuje: karty akcí a zmínek, Moje obchody, výběr obchodu ve Všech akcích (vlastní listbox `ChainSelect`, nativní `<select>` obrázky neumí). Názvy a loga sdílí `HandleInertiaRequests` jako `chainInfo` | Požadavek uživatele. Na rozdíl od fotek produktů (R22) jde o pět neměnných souborů — není důvod je načítat z cizích serverů. Loga mají v tmavém režimu bílý podklad, protože počítají se světlým pozadím. Při zveřejnění aplikace patří k právnímu posouzení (O6) — jde o ochranné známky. |
| R33 | 2026-10-02 | **Startovní sada katalogu: 54 produktů běžného nákupu** (mléčné výrobky a vejce, maso a uzeniny, pečivo, trvanlivé potraviny, ovoce a zelenina, nápoje, drogerie) s kategorií ze stromu Tesca, v `CatalogSeeder`. Pravidla odladěná na ostrých akcích všech obchodů 2. 10. 2026 (6 245 nabídek): vyloučená slova odstraňují skutečné chybné shody, ovoce se hledá v množném čísle („banány“, „jablka“), jak obchody jmenují čerstvé zboží. „Celé kuře“ vynecháno — „kuře“ jako začátek slova najde i „kuřecí“ | Požadavek uživatele („takhle bude vyhledávat jen 4 produkty“). Nejvíc šumu je v e-shopu Tesca (5 139 položek): příchutě („s příchutí slaniny“), dětské příkrmy, krmiva, hotová jídla. Přesnost doladí ruční opravy v katalogu (R30); pravidla, která chytají jen část, jsou lepší než široká, která zahltí Moje slevy. |
| R34 | 2026-10-02 | **Aplikace se jmenuje Slevohlídka, motto „Rychlý lovec slev“.** Barvy podle loga (`resources/brand/slevohlidka-logo.png`): červená #f42630 a antracit #1f222a — v tokenech `--color-brand` / `--color-brand-dark` jen na název v hlavičce, akcent tlačítek ztmavený na #d91f29 (bílý text 5 : 1), v tmavém režimu podklad karet antracit z loga a akcent #ff5c63. Ikony (favicon, apple-touch-icon, ikona v hlavičce) jsou maskot z loga bez textu v `public/images/brand`. Repozitář, konfigurace `letaky.*` a jména tříd se nemění | Rozhodnutí uživatele. Čistá červená z loga má s bílým textem kontrast jen 4,06 : 1, na tlačítka by nesplnila WCAG AA. Přejmenování kódu by nic nepřineslo a rozbilo historii. |
