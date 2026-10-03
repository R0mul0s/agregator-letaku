<!--
  Slevohlídka (agregátor letáků) — plán projektu
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
- **Produkce:** sdílený hosting Websupport, `https://slevohlidka.rhsoft.cz`, nasazeno 2026-10-02 ([R20, R38](#8-log-rozhodnutí), [DEPLOYMENT.md](../deploy/DEPLOYMENT.md))
- **Repozitář:** [github.com/R0mul0s/agregator-letaku](https://github.com/R0mul0s/agregator-letaku), osobní projekt
- **Pravidla pro psaní kódu:** [CODING_GUIDELINES.md](CODING_GUIDELINES.md)
- **Zdroje dat jednotlivých obchodů (endpointy, pole, pasti):** [ZDROJE_DAT.md](ZDROJE_DAT.md)

### Co platí a co ne (stav k 2. 10. 2026)

Log rozhodnutí (kap. 8) se nepřepisuje — starší rozhodnutí nahrazují novější. Tady je výsledek:

| Platí | Neplatí (čím nahrazeno) |
|---|---|
| Obchody se sledují celé, u Tesca (a Albertu) podle **typu prodejny** HM / SM (R19, R21); **u Kauflandu výběr více prodejen** (R49) | Výběr **konkrétních prodejen** a seznamy prodejen (R3 → R21), výběr prodejen Kauflandu (R19 → R21) |
| Kaufland po prodejnách (R49): seznam 149 prodejen a jejich akcí (cron `import-stores`), k výchozí nabídce stránky prodejen s chybějícími akcemi; akce, která neplatí všude, má prodejny (`offer_stores`); Moje slevy jen akce vybraných prodejen, u akce „Jen Trutnov“ | Jedna výchozí varianta pro všechny prodejny (R15 → R49); stahování jen prodejen vybraných uživateli (R3) |
| Bez LLM: Kaufland, Tesco, Lidl (kampaně na webu), Penny (API + parser SVG letáku), Globus (REST API webu), Billa (API celého katalogu) (R23, R25, R26, R46, R48) | LLM jako hlavní cesta pro letáky (R6 → R23); LLM jen v etapě 6, pokud bude potřeba |
| **Zmínky v letácích bez ceny** — Lidl, Penny a Albert (R27, R36) | Vyhledávací API letáků Lidlu (zakázané v robots.txt) |
| Hlídaná položka = slova + varianta + vyloučení (R18); katalog produktů (R24, R28–R31) — kategorie ze stromu Tesca, produkty spravuje admin, přiřazení nabídek se ukládá s ručními opravami; hlídaná položka = produkt z katalogu, nebo vlastní slova | Dva oddělené typy hlídání produkt / kategorie (R9 → R18; tři stavy shody platí dál); vymýšlení vlastních kategorií (→ R28); šablony hlídaných položek v konfiguraci (→ produkty katalogu, R31) |
| Obrázky produktů odkazem na CDN obchodu (R22); loga obchodů jako soubory aplikace (R32) | Ukládání obrázků |
| Katalog 164 produktů ověřených na skutečných akcích (R33, R37), data v `database/seeders/data/catalog-products.php`; admin ho spravuje v tabulce `/katalog` | Startovní sada 4 produktů ze šablon (→ R33, R37) |
| Název **Slevohlídka**, motto „Rychlý lovec slev“, barvy a motivy z loga (R34, R35): písmo Nunito, cenovky slev, maskot v prázdných stavech, vodoznak loga obchodu v kartách | Pracovní název Agregátor letáků; vzhled v růžové barvě cenovky |
| Hledání ve Všech akcích s našeptávačem; výběr obchodu s logy (R32); čísla stránek a „Načíst další“ s rozsahem v adrese (R43), stejně v tabulce katalogu s hledáním a řazením na serveru | Jen „Předchozí / Další“; katalog filtrovaný a řazený v prohlížeči |
| Hlavička pro vyhledávače a sdílení ze serveru (meta, canonical, OG, schema.org), `robots.txt` / `sitemap.xml` / `llms.txt` z rout, limity požadavků, `trustProxies` (R45) | Statický `public/robots.txt` povolující vše; jen obecný popis v hlavičce; registrace a obnova hesla bez limitu |
| Úvodní stránka pro nepřihlášené na `/` (co Slevohlídka umí, počty, ukázka akcí, výzva k registraci); Všechny akce veřejné (R44) | Celá aplikace jen po přihlášení; `/` nepřihlášeného přesměrovalo na přihlášení |
| Moje slevy: sbalitelné skupiny s přehledem, akcemi upravit / přestat hlídat a „Rozbalit vše“ (R43) | Všechny akce všech položek rozbalené pod sebou |
| Účet v menu pod avatarem (vlastní obrázek nebo iniciály), přihlášená zařízení s odhlášením ostatních, zrušení účtu (R40); řazení a minimální sleva v Mých slevách jako předvolba účtu (R41); e-mailový souhrn nových akcí denně / týdně (R42) | Položka „Účet“ v hlavní navigaci, přepínač vzhledu a odhlášení přímo v hlavičce |
| Hlídám: jedno pole s našeptávačem katalogu a volbou vlastních slov, dlaždice položek s počtem akcí a nejnižší cenou (R39); katalog k procházení jako v e-shopu — dlaždice oddělení s ikonou, po klepnutí pododdělení s produkty (R47) | Seznam katalogu a formulář vlastních slov stále rozbalené vedle seznamu položek (R31 → R39); sbalený seznam všech produktů s čipy oddělení (R39 → R47) |
| Potvrzení po uložení jako toast dole uprostřed obrazovky (kód stavu v `session('status')` → `ui.toast.messages`); nevratné akce potvrzuje vlastní okno (`<dialog>`); Moje obchody jako karty s přepínači; oslovení v 5. pádě („Ahoj, Romane!“) (R47) | Zpráva o uložení v obsahu stránky jen na Účtu a v Mých obchodech, jinde nic; `window.confirm`; Moje obchody se zaškrtávátky a červeným rámečkem u každého obchodu |
| Globus z REST API webu: jeden hypermarket, jen akce VKA0, bez oblečení a obuvi, cena s aplikací Můj Globus (R46) | Globus jen jako budoucí průzkum; Makro bez zdroje (ochrana proti robotům) |
| Billa z API celého katalogu (kvůli akcím jen s BILLA Klubem), platnost = akční týden středa–úterý, který obsahuje dnešek (R48) | Jen filtr `inPromotion` (bez akcí s Klubem) |
| Krmivo pro zvířata se ukáže jen u hlídání o zvířatech (R50) — pozná ho kategorie obchodu nebo slova a značky v textu, platí pro hlídané položky i katalog | Vylučovat krmivo vyjmenovanými slovy u každého produktu zvlášť (Friskies, Cesar… chyběly) |
| Odkaz akce Kauflandu vede na kategorii a textovým fragmentem na dlaždici (detail akce nemá vlastní adresu) | Odkaz na celý přehled nabídky |
| Produkce Websupport, cron URL, SQL skripty migrací, bez fronty (R20); balíček v `deploy/` pro `slevohlidka.rhsoft.cz`, cron po obchodech, `/health/imports` pro UptimeRobot (R38) | GitHub CI (R14 — zatím ne); jedna cron URL pro všechny obchody (O8) |

---

## 1. Rozsah a cíl

### Co systém dělá
- Jednou až dvakrát denně stáhne aktuální akční nabídku z obchodů **Kaufland, Tesco, Albert, Lidl, Penny, Globus a Billa**, včetně příštího týdne, pokud už je zveřejněný.
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
| **Albert** | GraphQL `getLeaflets` + Publitas `spreads.json` — **text stránek letáku** (R36): zatím zmínky bez ceny (R27) | ceny z textu stránek nebo vision LLM | 0 % s cenou, celý leták jako zmínky | střední |
| **Globus** | REST API webu `globus.cz/api/v1/gsoa/actionOffers` — katalog akcí hypermarketu s cenou, platností a cenou Můj Globus, popis z položek letáku (R46) — hotovo, ~650 akcí bez oblečení | — | ~100 % | nízká |
| **Billa** | JSON API `billa.cz/api/product-discovery` s celým katalogem (R48) — hotovo, ~3 400 akcí včetně ~370 jen s BILLA Klubem | leták Publitas (zmínky) | API nemá platnost akcí — akční týden st–út | nízká až střední |

Ověřeno na všech obchodech: **nikde není potřeba headless prohlížeč ani obcházení
ochrany proti botům**. Stačí HTTP klient Laravelu.

### Prodejny a varianty nabídky

| Obchod | Liší se nabídka podle prodejny? | Jak |
|---|---|---|
| Kaufland | mírně: 646 akcí všude, 70 jen v některých (pultové maso, ryby…), 74 různých kombinací ze 149 prodejen (3. 10. 2026) | cookie `x-aem-variant=CZxxxx`, seznam 149 prodejen v `.klstorefinder.json`, akce prodejny `.kloffers.storeName=CZxxxx.json` (R49) |
| Tesco | podle formátu (hypermarket / supermarket) + výjimky | leták HM / SM; e-shop zvlášť ([R4](#8-log-rozhodnutí)) |
| Albert | podle formátu + lokální varianty | leták HM / SM, `getLeaflets` vrací seznam prodejen letáku |
| Lidl | celostátně, „Rozšířená nabídka“ jen ve vybraných prodejnách | příznak u položky |
| Penny | celostátně | — |
| Billa | velký a malý leták podle velikosti prodejny, API jedna celostátní cena | — |
| Globus | jen krátké místní akce (Brno × Čakovice: 900 z 912 stejně) | stahuje se jeden hypermarket (4005 Čakovice) |

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
Tabulka prodejen `stores` byla v etapách 1–3 a zrušila se (R21); znovu je od R49, jen pro obchody, jejichž akce se liší po prodejnách (Kaufland), s vazbou `offer_stores`. Obchody (řetězce) jsou pevný výčet `Chain` v kódu, jejich nastavení je
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
| prodejny (`offer_stores`) | jen u akce, která neplatí ve všech prodejnách (Kaufland, R49); bez řádků = všude |
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
             │  akce jen z e-shopu, vybrané prodejny R49), které obsahují první slovo některé hlídané položky (SQL LIKE)
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
Lidl ~30 s (~40 kampaní s pauzou 0,5 s), Penny ~23 s (API + ~37 stran letáku s pauzou 0,5 s), Globus ~23 s (11 stránek API po 200 s pauzou 1,5 s), Billa ~47 s (25 stránek katalogu po 500 s pauzou 1 s).
Všechny obchody najednou ~1,5 minuty — na hostingu poběží každý obchod samostatně (O8).

Na produkci každý obchod stahuje vlastní cron URL `/cron/import-offers?chain=…&token=…`
(R38, postup v [deploy/DEPLOYMENT.md](../deploy/DEPLOYMENT.md)); `/health/imports` vrací 503,
když některý obchod nemá úspěšné stažení za posledních 26 hodin.

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
| 5b | **Dolaďování podle zkoušení** (R32–R37): loga obchodů a výběr obchodu s logy, našeptávač ve Všech akcích, Hlídám s katalogem klepnutím (produkt jen jednou), odkazy Kauflandu na dlaždici, název Slevohlídka a vzhled podle loga, Albert jako zmínky z textu stránek Publitas, katalog 164 produktů a tabulka katalogu | hotovo 2026-10-02 |
| 5c | **Přívětivost podle zkoušení** (R39–R44): přehledné Hlídám, Obchody v mřížce, menu účtu s avatarem, přihlášená zařízení a zrušení účtu, předvolby Mých slev, e-mailový souhrn, sbalitelné Moje slevy, stránkování Všech akcí a katalogu, úvodní stránka a veřejné Všechny akce | hotovo 2026-10-02 |
| 5d | **Globus** (R46): zdroj z REST API webu (katalog akcí jednoho hypermarketu, popis „různé druhy“ z položek letáku podle EAN), aplikace Můj Globus, bez oblečení a obuvi | hotovo 2026-10-02 |
| 5f | **Billa** (R48): zdroj z product-discovery API s celým katalogem, akce jen s BILLA Klubem, akce na množství, zboží na váhu, platnost jako akční týden st–út | hotovo 2026-10-02 |
| 5g | **Kaufland po prodejnách** (R49): seznam prodejen a jejich akcí, stránky prodejen s akcemi mimo výchozí nabídku, prodejny akce, výběr více prodejen v Mých obchodech, štítek „Jen Trutnov“ | hotovo 2026-10-03 |
| 5e | **Vzhled podle zkoušení** (R47): toasty místo zpráv v obsahu, vlastní potvrzovací okno, Moje obchody s přepínači, katalog v Hlídám jako dlaždice oddělení, jedoucí košík v Mých slevách, oslovení v 5. pádě, posuvník v barvách webu | hotovo 2026-10-02 |
| 6 | **LLM** (R23), jen pokud bude potřeba: Albert (obrázky stránek), zbytek letáku Lidlu, třídění nepřiřazených nabídek | |
| 7 | **Nasazení na Websupport** (R20, R38): cron URL pro stahování, hlídání stažení (`/health/imports`), HTTPS a bezpečnostní hlavičky v `public/.htaccess`, SQL skripty schématu a katalogu, build balíčku, [deploy/DEPLOYMENT.md](../deploy/DEPLOYMENT.md), ověření O8 | nasazeno 2026-10-02 (první verze `c5d45d7`, aktualizace `20ef035` s R39–R48, `ae88b48` s R49 2026-10-03) |

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
| O8 | **Jak dlouho smí na Websupportu běžet PHP požadavek** (`max_execution_time`, timeout proxy)? Stažení trvá Billa ~47 s, Tesco ~45 s, Lidl ~30 s, Penny ~23 s, Globus ~23 s, Kaufland ~2 s — cron URL proto po obchodech. Ověřit při nasazení; když nestačí, kratší pauza nebo stažení po částech | ověřit při nasazení |

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
| R34 | 2026-10-02 | **Aplikace se jmenuje Slevohlídka, motto „Rychlý lovec slev“.** Barvy podle loga (`resources/brand/slevohlidka-logo.png`): červená #f42630 a antracit #1f222a — v tokenech `--color-brand` / `--color-brand-dark` jen na název v hlavičce, akcent tlačítek ztmavený na #d91f29 (bílý text 5 : 1), v tmavém režimu podklad karet antracit z loga a akcent #ff5c63. Ikony (favicon, apple-touch-icon, ikona v hlavičce) jsou maskot z loga bez textu v `public/images/brand`. V tmavém režimu má maskot v hlavičce světlý kruhový podklad (černé tělo by na tmavé hlavičce zaniklo). Repozitář, konfigurace `letaky.*` a jména tříd se nemění | Rozhodnutí uživatele. Čistá červená z loga má s bílým textem kontrast jen 4,06 : 1, na tlačítka by nesplnila WCAG AA. Přejmenování kódu by nic nepřineslo a rozbilo historii. |
| R35 | 2026-10-02 | **Vizuální styl „rychlý lovec slev“** podle loga: písmo Nunito (zaoblené jako nápis v logu; `@fontsource-variable/nunito`, bez CDN), nadpisy stránek s „rychlostními čárkami“ z loga, červená linka pod hlavičkou, sleva v kartě jako nakloněná červená cenovka přes obrázek, cena velká v barvě slev, fotky na bílém poli i v tmavém režimu, karty a tlačítka se při najetí nadzvednou (vypnuto při `prefers-reduced-motion`). Moje slevy mají úvodní pruh s maskotem, pozdravem a souhrnem (hlídané položky, počet akcí, nejvyšší sleva); prázdné stavy mají maskota (`EmptyState`); přihlášení a registrace mají vedle formuláře červený panel s maskotem, mottem a přednostmi (`AuthShowcase`); karty akcí a zmínek mají v pozadí velké šedé logo obchodu jako vodoznak uříznutý rohem (`ChainWatermark`) | Požadavek uživatele („je to takové suché“). Motivy jsou převzaté z loga (cenovka s %, čárky pohybu, maskot), ne vymyšlené navíc. Bílý text na červené: cenovka má velký tučný text (≥ 18,66 px, stačí 3 : 1), panel s běžným textem má tmavší přechod #d91f29 → #a5121b (kontrast ≥ 5 : 1). |
| R36 | 2026-10-02 | **Albert: zmínky v letácích z textu stránek Publitas.** `spreads.json` prohlížeče letáku má u každé stránky pole `text` (text stránky v pořadí čtení; z něj prohlížeč skládá i alt obrázku). Ukládá se do `leaflet_pages` jako u Lidlu a Penny, s náhledem stránky (`at200`) a odkazem `…/page/{n}`. Jen hlavní letáky hypermarketů a supermarketů (`isDefault`), lokální varianty („…_frenstat“) ne. Technický název z GraphQL („Albert - 40HM_akcni_letak“) se neukládá; zmínka ukáže „Akční leták“ a štítek typu prodejny (hypermarket / supermarket) — ten mají zmínky všech obchodů s odlišnými letáky. Zdroj bez nabídek je v pořádku, pokud má stránky — chybou je až prázdno ve všem | Nález uživatele (alt obrázků stránek). Albert tím přestává být „Připravujeme“ a funguje bez LLM aspoň na úrovni „je to v letáku“. Ceny jsou v textu rozsekané („31“ „90“, „3490“ = 34,90) a bez polohy na stránce je k produktu spolehlivě přiřadit nejde — další krok (TODO, případně vision LLM v etapě 6). |
| R37 | 2026-10-02 | **Katalog rozšířený na 164 produktů** podle toho, co se v akcích skutečně objevuje: nejčastější druhová slova v názvech 6 245 akcí napříč obchody (jogurt, pivo, víno, káva, šampon, krmivo, čistič…). Každé pravidlo ověřené na ostrých akcích, vyloučená slova ze skutečných chybných shod. Kategorie = oddělení Tesca, kam patří většina akcí produktu (u zavádějících ručně). Data v `database/seeders/data/catalog-products.php`, ne v kódu seederu. 2. 10. 2026 pokrývá katalog 3 239 aktuálních akcí (~52 %). Ve výběru kategorie se regál a police se stejným názvem ukazují jen jednou | Požadavek uživatele („předvyplnit katalog z nabídek“). Generovat produkty automaticky ze slov by dalo šum (značky, příchutě), proto návrh ze statistiky a ruční ověření. Slova se hledají jako začátek slova — u krátkých slov („rum“, „med“, „sůl“) je vyloučení nutné („Rump steak“, „meduňka“, „sultánky“). |
| R38 | 2026-10-02 | **Nasazovací balíček jako u Počasí** pro `slevohlidka.rhsoft.cz` (postup v `deploy/DEPLOYMENT.md`). (1) **Cron URL po obchodech:** `/cron/import-offers?chain=…&token=…` a `/cron/import-categories?token=…`, token v `LETAKY_CRON_TOKEN` (porovnání `hash_equals`; špatný, chybějící nebo nenastavený token = 404), odpověď prostý text 200 / 500, `set_time_limit` 180 s. (2) **`/health/imports`** (veřejná, bez tokenu): 503, když některý obchod se zdrojem nemá úspěšné stažení za 26 h — pro UptimeRobot. (3) **`public/.htaccess`:** přesměrování na HTTPS podle `X-Forwarded-Proto`, CSP (`img-src https:` kvůli CDN obchodů, R22), HSTS jen na HTTPS, `immutable` cache hashovaných assetů. (4) **SQL skripty:** `migrations-2026-10-02-init.sql` (schéma všech 10 migrací a záznamy v `migrations`), `data-2026-10-02-katalog.sql` (kategorie a produkty katalogu, `REPLACE`, opakovatelný). Účet admina se nastaví `UPDATE users SET is_admin = 1` v phpMyAdminu. (5) `build-upload.ps1` balí jen commitnutý stav, verzi zapíše do `public/version.txt` | Požadavek uživatele („balíček jako v Počasí“). Na hostingu nejde artisan (R20), proto katalog jako SQL místo seederu. Stažení všech obchodů najednou (~1,5 min) by se nemuselo vejít do limitu požadavku (O8) — po obchodech je nejdelší Tesco ~45 s. Bez hlídání by výpadek cronu nebo změna API obchodu zůstaly bez povšimnutí, aplikace by jen ukazovala stále méně akcí. |
| R39 | 2026-10-02 | **Hlídám: jedno pole „Co chcete hlídat?“ a přehled položek** místo seznamu katalogu a formuláře vlastních slov vedle sebe (upřesňuje R31). Pole našeptává produkty katalogu (filtr v prohlížeči, nejvýš 8 návrhů, už hlídané nejdou zvolit); poslední volba „Hlídat … vlastními slovy“ otevře formulář předvyplněný napsaným textem. Hlídané položky jsou dlaždice s tím, co je teď v akci — počet akcí, nejnižší cena, kterou uživatel zaplatí (`MyOffers::userPrice`), a zmínky v letácích; odkaz vede na skupinu v Mých slevách (`#polozka-{id}`). Katalog k procházení je sbalený, s filtrem podle oddělení. Obchody jsou v mřížce, všech pět v řádku | Podnět uživatele („Hlídám působí chaoticky a nepřehledně“). Dva stále rozbalené způsoby přidání a seznam 164 produktů zabíraly víc místa než to, co uživatel hlídá; položky ukazovaly jen pravidla, ne jestli je k nim akce. Přehled akcí počítá stejný `MyOffers` jako Moje slevy, čísla se proto shodují. |
| R40 | 2026-10-02 | **Účet v menu pod avatarem vpravo nahoře** místo položky „Účet“ v navigaci: jméno a e-mail, Můj účet, přepínač vzhledu a odhlášení. **Profilový obrázek**, bez něj iniciály (první písmena dvou slov jména) na červeném přechodu loga. Prohlížeč obrázek ořízne na čtverec a zmenší na 256 px (WebP, jinak PNG), server ověří typ, velikost a rozměry (`letaky.account.avatar`). Soubor je na disku `local` (`storage/app/private/avatars`), posílá ho `AvatarController` jen vlastníkovi, adresa obsahuje název souboru (cache napořád). V účtu dál **přihlášená zařízení** (session v DB, jen platné podle `session.lifetime`, název z User-Agentu) s odhlášením ostatních — smaže jejich session a změní token „Zapamatovat si mě“ — a **zrušení účtu**; obojí po zadání hesla | Požadavek uživatele (menu jako na jeho snímku, vlastní avatar nebo iniciály; smazání účtu a odhlášení jinde vybral z návrhů). Hosting nemá `storage:link` ani jistotu GD/Imagick (R20) — proto úprava obrázku v prohlížeči a posílání přes kontroler. Zrušení účtu je podmínka pro případné zveřejnění (O6, GDPR). |
| R41 | 2026-10-02 | **Předvolby Mých slev v účtu:** řazení akcí uvnitř hlídané položky (`users.offers_sort`: od nejnižší ceny za jednotku — výchozí, od nejvyšší slevy, od konce platnosti; „možná“ vždy na konci, při shodě rozhoduje cena za jednotku) a **minimální sleva** (`users.min_discount_percent`: 10 / 20 / 30 / 50 % z `letaky.account.min_discount_options`, null = všechny akce). Sleva = od obchodu, jinak dopočtená z původní ceny jen u typu „sleva“ (`Offer::effectiveDiscountPercent`, stejně jako `discountPercent()` v JS); akce bez známé slevy hranici nesplní. Platí i pro přehled v Hlídám. Moje slevy u souhrnu ukazují aktivní řazení a hranici s odkazem na změnu | Uživatel vybral z návrhů. Akční cena bez původní ceny (Penny „Jedinečná nabídka“, Tesco „Super cena“, R8) slevu v procentech nemá — počítat ji jako 0 % je poctivější než ji s hranicí ukazovat. |
| R42 | 2026-10-02 | **E-mailový souhrn nových akcí** podle volby v účtu (`users.digest_frequency`: neposílat — výchozí, denně, jednou týdně). Cron URL `/cron/send-digests` (a `letaky:send-digests`) jednou denně po ranním stažení; uživatel dostane souhrn, když od posledního uplynul interval (`letaky.digest.interval_hours`: 20 h / 164 h — rezerva na posun cronu) **a** přibyly akce. Akce = Moje slevy (`MyOffers`: sledované obchody, karty, minimální sleva), „nová“ = `offers.created_at` po `users.digest_sent_at` (upsert importu `created_at` nepřepisuje). První souhrn po zapnutí ukáže všechny aktuální akce; nejvýš 5 na položku, zbytek odkazem. E-maily mají vlastní téma ve stylu webu (`resources/views/vendor/mail`: logo, barvy, karty akcí s cenou a cenovkou slevy); cena se formátuje bez Intl (vývojový kontejner má ICU jen s angličtinou). Lokálně je zachytává Mailpit (http://localhost:54723). Tabulka `watch_matches` zatím není potřeba | Uživatel vybral z návrhů. Souhrn bez nových akcí by byl spam; ukládat, co už uživatel viděl, by vyžadovalo tabulku shod (TODO) — čas posledního souhrnu a `created_at` stačí. Okamžité upozornění a Telegram zůstávají v TODO. |
| R43 | 2026-10-02 | **Moje slevy: skupiny hlídaných položek ve výchozím stavu sbalené.** Hlavička skupiny ukazuje počet akcí, nejnižší cenu, kterou uživatel zaplatí (`MyOffers::lowestPrice`, sdílené s Hlídám), a nejvyšší slevu; má akce „Upravit“ (vlastní slova → Hlídám s otevřenou úpravou, `?upravit=id`) a „Přestat hlídat“ (návrat zpět na Moje slevy). „Rozbalit vše / Sbalit vše“; rozbalené skupiny si pamatuje prohlížeč (localStorage), odkaz `#polozka-{id}` skupinu rozbalí. Karty se vykreslí až po rozbalení. **Všechny akce: čísla stránek i „Načíst další“** — načtený rozsah je v adrese (`?od=1&strana=3`, `OffersRequest::FROM_PAGE` / `PAGE`), nejvýš `letaky.offers.max_loaded_pages` stránek najednou (delší rozsah se zkrátí zepředu); odkazy staví server (`PaginationLinks`, `PageWindow`, parametry `HasPageWindow`). Stejně stránkuje **tabulka katalogu** — hledání (název, slova, cesta kategorie bez diakritiky), oddělení a řazení dělá server (`CatalogIndexRequest`: `q`, `oddeleni`, `razeni`, `smer`); podle kategorie řadí PHP nad ID vyfiltrovaných produktů, protože cesta kategorie v databázi není | Podnět uživatele (rozbalovací skupiny s nastavením přímo v Mých slevách; stránkování jako na jeho vzoru e-shopu, s rozsahem v adrese). Sbalený přehled se vejde na obrazovku i s desítkami položek. Rozsah v adrese přežije obnovení stránky i návrat zpět; strop chrání před adresou, která by vynutila tisíce karet. |
| R44 | 2026-10-02 | **Úvodní stránka pro nepřihlášené a veřejné Všechny akce.** Na `/` nepřihlášený vidí úvodní stránku (`LandingController`, `Landing.vue`): co Slevohlídka umí a co přinese registrace, živé počty (aktuální akce, obchody, produkty katalogu), tři kroky, šest akcí s nejvyšší slevou z různých obchodů (`letaky.landing`) a výzvu k registraci; přihlášený má na stejné adrese Moje slevy. `/akce` a našeptávač jsou veřejné, nepřihlášený má v navigaci jen Všechny akce, v hlavičce Přihlásit / Registrace (přepínač vzhledu až od středního displeje) a nad výpisem výzvu k registraci. Ostatní stránky dál vyžadují přihlášení | Požadavek uživatele (landing stránka s grafikou, Všechny akce bez přihlášení). Ukázka skutečných akcí přesvědčí víc než popis. Při zveřejnění patří k právnímu posouzení (O6) — veřejný výpis akcí je víc než osobní použití. |
| R45 | 2026-10-02 | **SEO, roboti, bezpečnost a limity požadavků.** (1) **Hlavička HTML ze serveru** (`App\Support\Seo\SeoMeta` v `app.blade.php`): titulek, popis, `robots`, canonical, Open Graph a X/Twitter karta s obrázkem 1200 × 630 (`public/images/brand/og-image.png`, zdroj `resources/brand/og-image.html`). Indexuje se jen úvodní stránka a Všechny akce (i podle obchodu, se stránkou v canonical, bez rozsahu `od`); hledání `noindex, follow`, přihlášení a registrace `noindex, follow`, vše za přihlášením `noindex, nofollow`. **schema.org** jen na indexovaných stránkách: Organization a WebSite se SearchAction (`/akce?q=`); akce záměrně ne jako Product/Offer — nejsme prodejce. (2) **`/robots.txt`, `/sitemap.xml`, `/llms.txt` z rout** (`CrawlerFilesController`): doména z `APP_URL`, mimo produkci `Disallow: /`; sitemap a llms.txt jen obchody s aktuálními akcemi (Albert má jen zmínky); přihlášení a registrace v robots.txt zakázané nejsou, aby robot viděl jejich `noindex`. (3) **`trustProxies(at: '*')`** jako Počasí: TLS končí na proxy Websupportu — bez toho mají všichni IP proxy a absolutní adresy `http://`. (4) **Limity požadavků** (`App\Support\RateLimits`, `letaky.rate_limits`): veřejné stránky 120/min a našeptávač 60/min podle IP, cron 20/min (hádání tokenu), všechny POST/PUT/DELETE 60/min podle uživatele nebo IP, registrace, obnova hesla a formuláře s heslem 5/min podle IP; přihlášení má vlastní limit Fortify. (5) **Hlavičky:** Permissions-Policy, Cross-Origin-Opener-Policy, bez X-Powered-By (`.htaccess` i dev nginx) | Požadavek uživatele (audit schema.org, SEO, OG, hlavička, bezpečnost, rate limiting, llms.txt, robots.txt, sitemap.xml). SPA bez SSR (hosting nemá Node) — co má vidět robot bez JavaScriptu nebo náhled odkazu, musí být v šabloně. Fortify registraci ani obnovu hesla neomezuje. |
| R46 | 2026-10-02 | **Globus jako šestý obchod, z REST API webu bez LLM.** Zdroj `GlobusOfferSource` stahuje katalog akcí jednoho hypermarketu (`actionProductsCatalog`, 4005 Čakovice — prodejny se liší jen pár krátkými místními akcemi) a k němu položky letáku (`actionProducts`) kvůli krátkému popisu („různé druhy“), spárované podle EAN; reklamní popis katalogu se nepoužívá. Ukládá se jen typ ceny VKA0 (ne pult VKP0 ani doprodej ZTP0) a bez oblečení, obuvi, bytového textilu a kabelek (`excluded_ware_groups`). Cena s aplikací **Můj Globus** je `loyalty_price` (nová karta `LoyaltyProgram::MujGlobus`), jen když je nižší než běžná; akce bez původní ceny je akční cena, ne „jen s kartou“. Všechny akce jsou v jednom průběžném zdroji `akcni-nabidka` — mají různou platnost (týden až měsíc). Popis v [ZDROJE_DAT.md](ZDROJE_DAT.md#globus) | Čisté veřejné API s cenou i platností (průzkum 2026-10-02); módní katalog (~210 akcí) by zahltil Všechny akce, aplikace je o nákupu potravin a drogerie (jako Lidl jen potraviny, R25). Billa až potom — API nemá platnost akcí |
| R47 | 2026-10-02 | **Jednotná zpětná vazba a úpravy vzhledu podle zkoušení.** (1) **Toast:** každé uložení vrátí kód stavu v `session('status')` (konstanty `STATUS_*` v kontrolerech, kódy Fortify); `resources/js/lib/toast.js` ho po každé odpovědi serveru (`router.on('success')` a první stránka) přeloží přes `ui.toast.messages.<kód>` a ukáže dole uprostřed (na telefonu nad tlačítkem Nahoru), sám zmizí za 5 s, najetí myší čekání zastaví. Stav bez překladu (věta od Fortify, zrušení účtu) se ukáže tak, jak je. Zprávy v obsahu stránek zrušené. (2) **Potvrzovací okno** (`ConfirmDialog.vue`, `confirmDialog()` vrací Promise) místo `window.confirm` — nativní `<dialog>`, fokus začíná na „Zpět“. (3) **Moje obchody:** karty stejné výšky ve třech sloupcích, přepínač sledování, nastavení jako řádky popisek / ovládání, nesledovaný obchod zešedne, lišta s Uložit se při neuložených změnách přilepí dole. (4) **Katalog v Hlídám** (`CatalogBrowseTree`, `WatchBrowse.vue`): dlaždice oddělení stromu Tesca s ikonou (`letaky.catalog.department_icons`), počtem produktů a počtem hlídaných; po klepnutí pododdělení (2. úroveň stromu) jako nadpisy s produkty; produkty bez kategorie v „Ostatní“. (5) Jedoucí košík z úvodní stránky i v úvodním pruhu Mých slev. (6) **Oslovení v 5. pádě** (`App\Support\CzechVocative`, pravidla podle koncovky + výjimky) v Mých slevách i v e-mailovém souhrnu | Uživatel: hlášky po uložení nejednotné (někde nic, někde pruh v obsahu), `alert`/`confirm` prohlížeče nevypadá jako web, Moje obchody „divoké“, katalog jako hromada čipů nepřehledný. Katalog má jen 2 smysluplné úrovně (12 oddělení, v pododdělení 1–12 produktů), proto dlaždice a nadpisy, ne hluboký strom. 5. pád pravidly bez slovníku — pokrývá běžná jména, neznámé tvary (víc slov, číslice) nechá v 1. pádě |
| R48 | 2026-10-02 | **Billa jako sedmý obchod, z product-discovery API bez LLM.** `BillaOfferSource` prochází **celý katalog** (~12 tisíc produktů, 25 stránek po 500, ~47 s), protože filtr `inPromotion` nevrací akce jen s BILLA Klubem (~370). Akce = štítek `pt-aktion` / `pt-multi` (ne doprodej `pt-abverkauf`); cena s Klubem (`LoyaltyProgram::BillaKlub`) jen nižší než běžná; `pt-multi` od 2 ks = akce na množství s běžnou cenou a výhodnou cenou kusu v textu (jako Tesco, Lidl); vážené zboží za kg; `eshop-only` = jen e-shop (`has_eshop`). **API nemá platnost akcí:** akce platí v akčním týdnu středa–úterý, který obsahuje dnešek (jako leták); dřívější konec řeší označení stažených (R16) při denním stahování. Cron v 6:00 a 14:00, po ranní výměně akcí. Formátování ceny v textu (`App\Support\PriceFormatter`) sdílí s e-mailovým souhrnem. Popis v [ZDROJE_DAT.md](ZDROJE_DAT.md#billa) | Veřejné API s cenami bez ochrany (průzkum 2026-10-02). Platnost z letáku (Publitas, marketingový text „Platí od středy…“) by byla křehčí než kalendář; týden st–út Billa drží. Celý katalog je 25 požadavků místo 7 — za ~370 akcí s Klubem to stojí, limit hostingu (O8) to unese |
| R49 | 2026-10-03 | **Kaufland po prodejnách. Nahrazuje R15, částečně R21.** (1) Cron `/cron/import-stores?chain=kaufland` (`ImportStores`, ~1,5 min) stáhne seznam prodejen (`.klstorefinder.json`) a u každé seznam jejích akcí (`.kloffers.storeName=…json`, jen `klNr` a platnost) do tabulky `stores` (`offer_keys`). (2) Import nabídek stáhne výchozí nabídku a k ní stránky prodejen (cookie `x-aem-variant`), dokud nemají detail všechny akce ze seznamů — vždy prodejnu s nejvíc chybějícími akcemi, nejvýš 40 stránek (3. 10. stačilo 24, celé stažení ~50 s). (3) Akce, která není ve všech prodejnách, dostane prodejny (`offer_stores`); akce ve všech nebo v žádném seznamu (seznam z jiné doby) platí všude. Seznamy starší 36 h se neberou. (4) Uživatel si u Kauflandu vybere **víc prodejen** (`followed_chains.store_codes`, nejvýš 10); Moje slevy a souhrn ukážou jen akce platné aspoň v jedné z nich; u akce, která neplatí ve všech vybraných, štítek „Jen Trutnov“, na Všech akcích bez výběru „Jen v 46 prodejnách“, s výběrem a mimo ně „Není ve vašich prodejnách“; štítek s ikonou „i“ otevře okno se seznamem prodejen (vybrané nahoře s fajfkou, hledání). Bez výběru se ukáže vše | Testeři hlásili rozdíly (Vrchlabí × Trutnov). Průzkum 3. 10. 2026: 646 akcí je ve všech 149 prodejnách stejných (i cenou), 70 jen v některých (polovina pultové maso „K-Mistři od fochu“, ryby), 74 různých kombinací — nejde o pár regionů. Uživatel chce všechny prodejny v cronu, výběr víc prodejen (nakupuje v Jaroměři i v Hradci) a u akce vidět, kde platí. Vazba jen u akcí s omezením místo všech akcí × 149 prodejen (R3: ~110 tisíc řádků týdně); pro detail stačí ~24 stránek místo 149 |
| R50 | 2026-10-03 | **Krmivo pro zvířata jen u hlídání o zvířatech.** `PetFood::isPetOffer`: akce je krmivo, když má kategorii obchodu pro zvířata (Tesco „Pro kočky/psy/hlodavce/ptáky“, Globus „Krmivo pro…“, Billa „Konzervy a kapsičky“, „Suché krmivo“, „Pamlsky a jiné“), nebo v textu začátek slova „pro psy“, „pro kočky“, „krmivo“, „granule“, „stelivo“ nebo značku (Friskies, Cesar, Whiskas, Pedigree…). `PetFood::isPetRule`: hlídání je o zvířatech, když některé hledané slovo začíná „kočk“, „pes/psy“, „krmiv“, značkou… Jinak `WatchItemMatcher` krmivo vynechá — v Mých slevách, souhrnu i v přiřazení k produktům katalogu. Seznamy v `letaky.pet_food` | Hlídané „Hovězí maso“ ukazovalo na prvních místech Friskies a Cesar „s hovězím“ (levné za kilo). Vylučovací slova u produktu (R27) krmivo nepokryla — značek je mnoho. Kategorie obchodu nestačí sama (Penny je nemá, Kaufland má krmivo s drogerií), slova sama také ne (Akinu, „Váš výběr kapsa pes“). Na akcích 3. 10. 2026: 523 z 10 371 krmivo, slova „podestýlk“ (vejce) a „dog“ (Bull Dog sprej) vyřazena kvůli falešným shodám |
