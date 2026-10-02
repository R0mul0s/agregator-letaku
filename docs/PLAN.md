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

## 4. Datový model

Tabulky `stores`, `leaflets`, `offers` a `scrape_runs` existují (etapy 1–2), ostatní jsou
návrh. Obchody (řetězce) jsou pevný výčet `Chain` v kódu, jejich nastavení je
v `config/letaky.php`.

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

### Uživatelé a hlídání (etapa 3)
- `users`: účty (Fortify); `loyalty_programs` = JSON seznam karet a aplikací, které uživatel má ([R19](#8-log-rozhodnutí))
- `followed_chains`: sledované obchody — `chain`, `store_format` (null = všechny typy prodejen), `include_online_only` ([R19](#8-log-rozhodnutí))
- `store_user`: vybrané prodejny (zatím jen Kaufland, nabídka se podle nich ještě nerozlišuje — [R15](#8-log-rozhodnutí))
- `watch_items`: hlídané položky — `name`, `keywords`, `variant_keywords`, `exclude_keywords` ([R18](#8-log-rozhodnutí))

Shody hlídaných položek s nabídkami se neukládají, počítají se při zobrazení ([R19](#8-log-rozhodnutí)).
Tabulka `watch_matches` přibude s upozorněními (TODO).

### Provoz
- `scrape_runs`: každé stažení obchodu (začátek, konec, stav, počet uložených a stažených nabídek, chyba). **Nula položek je chyba**, ne „žádné akce“.
- `llm_extractions`: (etapa 6) vytěžené stránky letáků, aby se stránka neposílala do LLM dvakrát.

---

## 5. Toky dat

```
php artisan letaky:import-offers [obchod…]      (zatím ručně, později plánovaně — O1)
   └─▶ ImportChainOffers (pro každý obchod, selhání jednoho nezastaví ostatní)
          ├─ zdroj obchodu (Sources/<Obchod>, SourceHttp s pauzami) ──HTTP──▶ web / API obchodu
          │     └─ převod na OfferData: cena v haléřích, balení, typ akce, místní platnost
          ├─ upsert leaflets + offers (deduplikace podle klíče), v jedné transakci
          ├─ neskončené nabídky obchodu, které chyběly → withdrawn_at (R16)
          └─▶ scrape_runs (úspěch / chyba)
```

Moje slevy (etapa 3) se počítají při zobrazení stránky:

```
GET / ──▶ MyOffers::forUser
             ├─ kandidáti: neskončené a nestažené nabídky sledovaných obchodů (typ prodejny,
             │  akce jen z e-shopu), které obsahují první slovo některé hlídané položky (SQL LIKE)
             ├─ WatchItemMatcher: všechna slova, vyloučení, varianta → shoda / možná (R18, R9)
             ├─ akce jen s kartou, kterou uživatel nemá, vynechá (R19)
             └─ řazení: shody, pak akce s cenou od nejnižší ceny za jednotku (s kartou, pokud ji má),
                akce na více kusů, nakonec „možná“
```

Prodejny: `php artisan letaky:import-stores` (zatím Kaufland), nové přidá, existující
aktualizuje, nic nemaže.

Doba stažení (2. 10. 2026): Kaufland ~2 s (1 požadavek, příští týden +1),
Tesco ~45 s (seznam letáků, 2 letáky, 26 stránek akcí po 200 s pauzou 1,5 s).

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
| 1 | **Kostra:** Laravel 13, Docker, Pint, Larastan, Pest, SCSS tokeny, layout; přihlášení a registrace (Fortify); model `stores` | hotovo 2026-10-02 |
| 2 | **Kaufland a Tesco:** zdroje, normalizace, `offers`, `leaflets`, `scrape_runs`, artisan příkaz importu; import seznamu prodejen (Kaufland); přehled všech nabídek s hledáním (`/akce`); stažené nabídky (R16) | hotovo 2026-10-02 |
| 3 | **Hlídání:** výběr obchodů s upřesněním, prodejen a věrnostních karet (`/obchody`), hlídané položky se slovy, variantou a vyloučením a šablonami (`/hlidam`), Moje slevy seřazené podle ceny za jednotku (`/`) | hotovo 2026-10-02 |
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
| O3 | **Kategorie:** vlastní strom, nebo převzít strukturu některého obchodu (Tesco `superDepartment` / `department`)? Jak jemně dělit (mléko → polotučné → trvanlivé / čerstvé)? Pozor: obchody „polotučné“ často nepíšou, jen „tuk 1,5 %“ (Kaufland Kunín má „polotučné“ jen v popisu), a hledání „vejce“ najde i „MAGGI Přidej vejce“ | |
| O4 | **Seznamy prodejen** Tesco, Lidl a Penny: odkud je brát. Albert vrací prodejny u letáku, ostatní zatím neověřeno | Kaufland hotovo (etapa 2) |
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
| R13 | 2026-10-02 | **Fortify jen s registrací, přihlášením, obnovou hesla, úpravou profilu a změnou hesla.** Dvoufázové ověření, passkeys a ověření e-mailu jsou vypnuté. Přihlášení má limit pokusů na dvojici e-mail + IP (`letaky.auth.login_attempts_per_minute`). Session v databázi, fronta zatím `sync` (O1). Adresy formulářů posílá server v props, routy Fortify nejsou ve Vue natvrdo | Aplikace je zatím jen pro autora (R5), další vrstvy zabezpečení by přidaly stránky a tabulky bez užitku. Jdou zapnout v `config/fortify.php`, až se aplikace zveřejní. Session v databázi (na rozdíl od cookie v Počasí) jde u uživatelských účtů zrušit smazáním řádku. |
| R14 | 2026-10-02 | **CI v GitHub Actions zatím není**, kontroly kvality se pouštějí jen ručně před commitem | V rané fázi projektu zbytečné, rozhodnutí autora. Workflow jde převzít z Počasí (`.github/workflows/ci.yml`), až bude potřeba. |
| R15 | 2026-10-02 | **Kaufland zatím jen výchozí varianta nabídky** (bez cookie prodejny, odpovídá CZ3300), platná pro všechny prodejny. Upřesňuje R3 | Rozdíl mezi prodejnami je v desítkách položek ze ~740 (CZ3300 × CZ4600: 736 × 732). Stahovat každou vybranou prodejnu zvlášť by násobilo požadavky i řádky skoro bez užitku. Varianty po prodejnách jsou v [TODO.md](TODO.md). |
| R16 | 2026-10-02 | **Nabídka, kterou obchod stáhne nebo změní před koncem platnosti, se označí `withdrawn_at`** a z výpisů zmizí; nemaže se (R10). Pozná se tak, že v novém úplném stažení obchodu chybí (`scrape_run_id` není poslední stažení). Když se znovu objeví, označení zmizí | Kaufland během 2. 10. 2026 zkrátil akci na vejce z 6. 10. na 2. 10. Ranní záznam by bez toho dál tvrdil, že akce platí do 6. 10. Funguje jen proto, že každý zdroj stahuje celou nabídku obchodu najednou. |
| R17 | 2026-10-02 | **Tesco: ceny z akcí e-shopu, leták jen určuje, kde akce platí.** Produkt e-shopu se páruje s produktem letáku podle **posledních 8 číslic ID** (leták `2001019279706` = e-shop `219279706`) a překryvu platnosti. V letáku HM i SM = všechny prodejny, v jednom = jeho formát, v žádném = jen online (R4). Katalog (CAT) se nesleduje. Položky letáku bez akce v e-shopu („Super cena“ za běžnou cenu) chybí | Ceny jsou jen v e-shopu, PDF letáku má poškozenou textovou vrstvu. Pravidlo 8 číslic ověřené na celém letáku 2. 10. 2026: HM 880 z 1 210 produktů, SM 237 z 277, žádná kolize v 5 139 produktech e-shopu; nespárované jsou hlavně „Super ceny“ a zboží bez akce online. „Super ceny“ by šly doplnit vision LLM (etapa 6). |
| R18 | 2026-10-02 | **Hlídaná položka = název + hledaná slova + varianta + vyloučení**, místo dvou typů produkt / kategorie. Slova se hledají v názvu, značce a popisu nabídky jako **začátek slova**, bez diakritiky a velikosti písmen; všechna musí být v nabídce, alternativy přes „\|“ („mléko polotučné\|1,5“). Chybí-li varianta („zero“) u nabídky „různé druhy“, je shoda **možná** (R9). Kterékoli vyloučené slovo nabídku vyřadí. Šablony (vejce, polotučné mléko, máslo, Coca-Cola Zero) v `config/letaky.php` předvyplní formulář | Rozhodnutí uživatele. Jeden zápis pokryje produkt („coca cola“ + „zero“) i kategorii („vejce“ bez značky) a funguje hned, bez kategorizace (etapa 5). Začátek slova kvůli českým koncovkám („vejce“ najde „vejcem“) — proto ale „máslo“ najde i „máslová dýně“; šablony mají vyloučení ze skutečných nabídek 2. 10. 2026 („máslov“, „ruské“, „lipánek“, „maggi“). Obchody „polotučné“ často nepíšou, proto alternativa „1,5“. |
| R19 | 2026-10-02 | **Sledují se obchody s upřesněním:** u Tesca typ prodejny (nabídka bez typu platí všude) a akce jen z e-shopu, u Kauflandu výběr prodejen; k tomu karty a aplikace, které uživatel má. **Akce jen s kartou, kterou uživatel nemá, se v Mých slevách neukáže**; s kartou se řadí podle ceny s kartou. Shody se počítají při zobrazení, neukládají se | Rozhodnutí uživatele („obchody + upřesnění“) — seznam prodejen zatím má jen Kaufland. Akce jen s kartou bez karty není akce. Nabídek je tisíce a hlídaných položek jednotky: SQL předvybere kandidáty podle prvního slova, pravidla se vyhodnotí v PHP za desítky milisekund; tabulka shod by se musela přepočítávat po každém importu i úpravě položky. Bude potřeba až pro upozornění. |
