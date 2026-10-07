<!--
  Zdroje dat jednotlivých obchodů — výsledek technického průzkumu
  @author Roman Hlaváček
  @created 2026-10-02
-->

# Zdroje dat obchodů

Výsledek technického průzkumu z **2. 10. 2026** (leták 30. 9.–6. 10. 2026), doplněný
při implementaci a provozu: Globus a Billa (R46, R48), Kaufland po prodejnách (R49, 3. 10.),
prodlužování akcí Billy a User-Agent (R54, R65, 4. 10.). Všechno je ověřené skutečnými
požadavky (curl). Co je jen předpoklad, je označené **(předpoklad)**.

Jde o **neveřejná a nedokumentovaná rozhraní**, která se můžou kdykoli změnit.
Když scraper přestane fungovat, začni tady a porovnej s aktuální odpovědí. Po
změně tento dokument aktualizuj ve stejném commitu jako kód.

Společné pro všechny obchody:
- Headless prohlížeč, captcha ani obcházení WAF nejsou potřeba.
- Stahovat šetrně: 1–2× denně, pauza mezi požadavky, identifikovatelný User-Agent ([R5](PLAN.md#8-log-rozhodnutí)) —
  `Slevohlidka/1.0 (+slevohlidka.cz)`, **bez `https://`**: weby s prerenderem pro roboty (Albert)
  pošlou UA s adresou na prerender a API vrátí chybu ([R65](PLAN.md#8-log-rozhodnutí)).
- Částečná odpověď je nebezpečnější než žádná: chybějící akce se označí jako stažené (R16). Import proto
  hlídá nulu i podezřelý propad počtu akcí ([R54](PLAN.md#8-log-rozhodnutí)).
- Do LLM nebo k parsování nikdy neposílat celé PDF, když existuje lepší zdroj (PDF mají 15–42 MB).

---

## Kaufland

**Cesta:** JSON vložený v HTML (server-side rendering), 100 % letáku. Náročnost nízká.

Pozor, **www.kaufland.cz je marketplace** za Cloudflare (403). Kamenné prodejny
mají samostatný web **prodejny.kaufland.cz** (AEM, Fastly), který nic neblokuje.

### Endpointy
| Účel | Požadavek |
|---|---|
| Nabídka týdne | `GET https://prodejny.kaufland.cz/nabidka/prehled.html?kloffer-week=current` (`=next` pro příští týden) |
| Volba prodejny | cookie `x-aem-variant=CZ3300`; bez cookie výchozí CZ3300 |
| Seznam prodejen | `GET https://prodejny.kaufland.cz/.klstorefinder.json` (149 prodejen) |
| Položky platné v prodejně | `GET https://prodejny.kaufland.cz/.kloffers.storeName=CZ3300.json` (jen `dateFrom`, `dateTo`, `klNr`; ~40 kB) — stejné akce jako stránka nabídky s cookie té prodejny |
| Leták (záloha) | `GET https://endpoints.leaflets.schwarz/v4/flyer?flyer_identifier=CZ_cs_KDZ_3300_CZ40-LFT&region_id=3300`; vrací `pdfUrl` (PDF s textovou vrstvou), stránky a `keyWords` (OCR slova bez struktury), `products` je prázdné |

Identifikátory letáků: `CZ_cs_KDZ_{prodejna}_CZ{týden}-LFT` (potraviny), `CZ_cs_Hyper1_{prodejna}_CZ{týden}-CL1` (nepotraviny).

### Extrakce
HTML (~2,5 MB) obsahuje `<script>window.SSR = window.SSR || {}; window.SSR['<uuid>'] = {...}</script>`.
Komponenta s `component: "OfferTemplate"` má celou nabídku (~736 položek ve 26 kategoriích
a kampaních). Regex na `window\.SSR\['[^']+'\] = (\{.*?\});?\s*</script>` a pak `json_decode`.

Struktura: `props.offerData.cycles[].categories[].offers[]`

```json
{"offerId":"20260930.00153062.CZ3300.CZ260930SK","klNr":"00153062",
 "dateFrom":"2026-09-30","dateTo":"2026-10-06",
 "title":"Čerstvá vejce M20","subtitle":"podestýlková","detailDescription":"Čerstvá vejce\nvelikost M",
 "unit":"20 kusů","price":59.9,"formattedPrice":"59,90","formattedOldPrice":"99,90","discount":40,
 "basePrice":"(=1 kus 3,00)","loyaltyDiscount":0,"label":"reducedPrice",
 "listImage":"https://kaufland.media.schwarz/is/image/schwarz/8594061460122_CZ_P"}
```

Nabídka s Kaufland Card:
```json
"customerType":"KDN","bonusbuy":true,"detailTitle":"tvoje cena s\nKaufland XTRA",
"formattedPrice":"29,90","formattedOldPrice":"42,90","discount":30,
"loyaltyFormattedPrice":"24,90","loyaltyDiscount":41,"loyaltyFormattedBasePrice":"(=100 ml 9,96/4,98)"
```

### Po prodejnách (R49)
Implementace: `KauflandStoreSource` (seznam prodejen a jejich akcí), `KauflandOfferSource` (stránky prodejen a prodejny akce).

- Průzkum 3. 10. 2026 (všech 149 prodejen): **646 akcí je všude stejných** i cenou, **70 jen v některých**, každá prodejna jich má 8–22. Polovina je pultové maso „K-Mistři od fochu“ (`klNr` `630…`; Vrchlabí vepřová pečeně a kližka, Trutnov krkovice a čevapčiči), dál ryby z pultu (losos, pstruh — jen prodejny s rybím pultem, `slf` obsahuje `Fish`) a jednotlivé položky. Vzniká **74 různých kombinací** — nejde o regiony.
- Seznam prodejny má jen `klNr` a platnost; detail (název, cena) je jen na stránce nabídky s cookie `x-aem-variant={kód}` (~2,5 MB). Výchozí stránka (bez cookie) je CZ3300 Praha-Vypich. Na detail všech akcí všech prodejen stačilo **24 stránek** navíc (výběr: vždy prodejna s nejvíc chybějícími akcemi), 6. 10. 2026 s oběma týdny **25 stránek** (~30 s).
- Klíč akce ze seznamu (`klNr|dateFrom|dateTo`) je stejný jako klíč nabídky (`OfferData::key()`).
- Seznam prodejen `.klstorefinder.json`: `n` kód, `cn` „Kaufland Trutnov“ (ukládá se bez „Kaufland “), `t` město, `slf` vybavení prodejny (`Meat`, `Fish`…).
- Seznamy a stránky jsou z jiné doby (cron prodejen běží dřív než stažení nabídky) — akce, kterou žádný seznam nezná, platí všude.

### Pole a pasti
Implementace: `app/Domain/Sources/Kaufland/KauflandOfferParser.php`.

- **Název není jednotný.** Někdy je v `title` značka a v `subtitle` produkt („Tatra“ + „Máslo“), jindy je produkt v `title` („Čerstvá vejce M20“ + „podestýlková“). Název = `title` + `subtitle`.
- **22 ze 736 položek nemá `title`.** Pak se použije `detailTitle` („Rostlinná“, značka je až v `detailDescription` „Rama různé druhy“). U nabídek s kartou je `detailTitle` reklamní „Tvoje cena s Kaufland XTRA“, ten se nepoužije. Položka bez obojího se přeskočí.
- **9 položek nemá `formattedPrice`**, jen `loyaltyFormattedPrice`: akce platí jen s kartou a běžná cena je v `loyaltyFormattedOldPrice`.
- **Nabídka se mění i během týdne.** 2. 10. 2026 dopoledne byla vejce M20 v sortimentu a Superkaufu do 6. 10., odpoledne už jen v „Mimořádné nabídce“ do 2. 10. Proto R16 (stažené nabídky).
- Popis (`detailDescription`) nese údaje, které v názvu chybí: „Kunín Trvanlivé mléko tuk 1,5 %“ má „polotučné“ jen v popisu.
- Platnost `dateFrom` / `dateTo` je u kategorie i u položky. Kampaně (Víkend, Start týdne) mají kratší platnost.
- `label` určuje typ akce. Skutečná sleva je `reducedPrice` a `halfPrice`. **`smallPrice` („AKCE! pouze“) a `specialItems` nemají původní cenu**, často jde o trvale nízkou cenu ([R8](PLAN.md#8-log-rozhodnutí)).
- Kaufland Card: `customerType == "KDN"` a pole `loyalty*`.
- `basePrice` je text („(=1 l 21,27)“) a musí se parsovat.
- **Stejná položka je ve více kategoriích** (sortimentní a „Superkauf“), deduplikovat podle `klNr` a platnosti.
- Názvy jsou často souhrnné: „Coca-Cola/Fanta/Sprite různé druhy“.
- URL obrázku obsahuje EAN (`8594061460122_CZ_P`).
- Příští týden se zveřejňuje 3 dny před začátkem platnosti. Týden začíná ve středu, takže v neděli **(předpoklad z textu webu)**.
- **Od zveřejnění má stránka v datech oba týdny** (ověřeno 6. 10. 2026): `weekData.nextWeekDates` vyjmenuje dny příštího týdne a `offerData.cycles` má dva cykly — aktuální (kategorie od 30. 9.) a příští (od 7. 10.); `?kloffer-week=current` i `=next` vrací stejná data (~3,3 MB, ~1 400 akcí), liší se jen vykreslené dlaždice. Parser proto dělí kategorie podle `dateFrom` (od prvního dne `nextWeekDates` = příští týden) na dva zdroje `nabidka-{začátek}` a odkazy příštího týdne vedou na `kloffer-week=next` (na `current` by dlaždice nebyla). Stránka `next` se stahuje zvlášť, jen když ji stránka ohlásí a příští týden v datech nemá.
- **Paměť:** dvojnásobná stránka × 40 stránek prodejen vedla 6. 10. 2026 k pádu stažení na hostingu (lokálně 671 MB, běh zůstal „running“). Ze stránek prodejen se proto drží jen akce, které chyběly (~100 MB), a položky přeskočené parserem (bez názvu) se za chybějící nepočítají — jinak by je hledala každá další prodejna až do `max_store_pages`.
- robots.txt zakazuje jen detaily (`/nabidka/*/detail`), `/nabidka/prehled.html` je povolená.
- **Detail akce nemá vlastní adresu** — otevírá se jen jako okno nad stránkou, v datech je jen `offerId` a `klNr`. Odkaz u akce proto vede na stránku její kategorie (`?kloffer-week=current|next&kloffer-category={name kategorie}`, např. `03_Mléčné_výrobky__tuky__vejce`) s textovým fragmentem `#:~:text={title dlaždice}`: prohlížeč na dlaždici odroluje a zvýrazní ji (nadpis je v HTML jako `k-product-tile__title`). Pomlčka se ve fragmentu musí kódovat (`%2D`).

---

## Tesco

**Cesta:** dvě GraphQL API, e-shop (ceny a akce) a letáky (co v letáku je, bez cen). Náročnost nízká až střední.

HTML stránky (itesco.cz, nakup.itesco.cz) jsou za Akamai Bot Managerem. Bez plné
sady browser hlaviček vrací 403. **API domény Akamai nemají.**

### E-shop: `xapi.tesco.com`
```
POST https://xapi.tesco.com/
x-apikey: <TESCO_API_KEY>
region: CZ
language: cs-CZ
content-type: application/json
```
Klíč je veřejný a je vložený v HTML e-shopu jako `mangoApiKey`. Patří do `.env`
(`TESCO_API_KEY`), ne do kódu. Při chybě 401 ho znovu načíst z HTML (tam už jsou potřeba
browser hlavičky): ve zdrojovém kódu stránky `nakup.itesco.cz` hledat `mangoApiKey`.

Výpis všech akcí (stránkování po 200, ~5 100 produktů, 26 požadavků):
```json
[{"query":"query P($promotion:String,$page:Int,$count:Int){promotions:promotionType(type:$promotion,page:$page,count:$count){info{total} products{id title brandName superDepartmentName departmentName defaultImageUrl price{actual unitPrice unitOfMeasure} promotions{id startDate endDate description unitSellingInfo price{beforeDiscount afterDiscount} attributes}}}}",
  "variables":{"promotion":"all","page":1,"count":200}}]
```
Vyhledávání: `search(query:, page:, count:)` se stejnými poli.

**Strom kategorií** (kategorie katalogu, [R28](PLAN.md#8-log-rozhodnutí); `TescoCategorySource`):
`query { taxonomy { id name children { id name children { id name children { id name } } } } }`.
Čtyři úrovně: oddělení › sekce › regál › police (2. 10. 2026: 15 › 137 › 745 › 1 340, hlubší
úroveň není). `id` je `b;` + base64 zakódované cesty názvů („Mléčné, vejce a margaríny|Mléko, …“),
nejdelší 350 znaků — přejmenování uzlu u Tesca tedy znamená nové ID. Nejnižší úroveň bývá skoro
produkt („Polotučné mléko“, „Máslo“), jinde jen balení („Malá balení“ / „Velká balení“) a regál
s policí mívají stejný název („Máslo › Máslo“). Oddělení „Top výběr“ a „Novinky“ jsou marketingové
výběry, ne kategorie.

```json
{"id":"211037571","title":"Tesco Mléko UHT polotučné 1,5% 1l",
 "price":{"actual":21.9,"unitPrice":21.9,"unitOfMeasure":"l"},
 "promotions":[{"startDate":"2026-10-01T22:00:00Z","endDate":"2026-10-04T22:00:00Z",
   "description":"8,90 Kč Více než o polovinu nižší cena s Clubcard","unitSellingInfo":"8,90 Kč/l",
   "price":{"beforeDiscount":null,"afterDiscount":21.9},"attributes":["CLUBCARD_PRICING"]}]}
```

### Letáky: `leaflets-be`
`POST https://api.prod.retail.tesco.com/marketing/leaflets-be/graphql`, bez autentizace.

- Platné letáky: `leaflets(options:{filter:{country:{eq:cz},validTo:{after:$now}}}){items{id slug type validFrom validTo leafletUrl promoP1Name}}`. Typy `HM`, `SM`, `CAT` (katalog).
- Obsah: `leafletBySlug(country:cz, slug:"tesco-letak-2026-09-30", leafletType:HM){pages{page pagePNG positions{products{promoOfferName addToBasketURL product{productName pngFileURL productPeriods{periodName promoStart promoEnd}}}}}}`
- HM má ~36 stran, 360 hotspotů a 1 210 produktů; SM má 16 stran a 277 produktů.
- Hotspot obsahuje **konkrétní varianty** (`"productName":"COCA-COLA ZERO 1,5l PET"`) a URL produktu v e-shopu (`…/products/2001120538184`). ID je jiné než v xapi (`120538184`), ale URL funguje a slouží k párování s cenou.
- PDF letáku má **poškozenou textovou vrstvu** (chybí číslice), nepoužívat. Obrázky stránek (`pagePNG`) jsou fallback pro vision LLM.
- Neveřejné dotazy (`protectedLeaflets`, `adminPromotionList`) vrací `UNAUTHENTICATED` a **nemají se obcházet**.

### Pole a pasti
Implementace: `app/Domain/Sources/Tesco/TescoParser.php` a `TescoOfferSource.php` ([R17](PLAN.md#8-log-rozhodnutí)).

- **Párování letáku s e-shopem: posledních 8 číslic ID.** Leták `…/products/2001019279706`, e-shop `219279706`. Ověřeno 2. 10. 2026 na celém letáku: HM 880 z 1 210 produktů, SM 237 z 277, v 5 139 produktech e-shopu žádná kolize. Nespárované jsou hlavně „Super ceny“ (bez akce v e-shopu) a zboží, které online není.
- **Zboží na váhu:** `afterDiscount` / `beforeDiscount` jsou ceny **za kg** (nebo za kus u okurky), `price.actual` je cena odhadovaného kusu (mandarinky 3,91 Kč). Balení se pak bere z jednotky v `unitSellingInfo` („27,90 Kč/kg“ = 1 kg).
- **Akce bez `price`** (95 položek): „3 za cenu 2“, „MENU BAGETY“, „PECIVO+NAPOJ“, „2 za 799 Kč“. Cena produktu je jen `price.actual`. Text akce se ukládá, jak přišel; verzálky převádí na větu až zobrazení (`PromotionText`, R101), slova bez diakritiky doplňuje `letaky.offers.promotion_text_words`.
- Typy popisů Clubcard (2. 10. 2026): „N Kč s Clubcard“ (4 045×), „N Kč Ušetřete N% s Clubcard“, „N Kč Ušetřete 1/3 s Clubcard“, „N Kč Poloviční cena s Clubcard“, „Ušetřete 1/3 99,00 Kč/kg s Clubcard“. Cena s kartou je vždy první částka v Kč.
- „Super cena“ může mít `beforeDiscount` vyšší než `afterDiscount`, pak je to normální sleva.
- Konec platnosti bývá půlnoc dalšího dne (`2026-10-04T22:00:00Z` = do 4. 10.) i poslední sekunda dne (`21:59:59Z`); v zimním čase o hodinu posunuté.
- Časy jsou v **UTC**: `2026-09-29T22:00Z` = 30. 9. místního času ([R7](PLAN.md#8-log-rozhodnutí)).
- **Cena s Clubcard je jen v textu `description`** („8,90 Kč … s Clubcard“). U Clubcard akcí je `afterDiscount` **běžná cena**. Parsovat regexem a ověřit testem.
- Běžná akce: `beforeDiscount` → `afterDiscount`, procento jen v textu („-50%, předtím 59,90 Kč“).
- Gramáž je jen v `title`.
- Nabídky v prodejnách a v e-shopu se liší ([R4](PLAN.md#8-log-rozhodnutí)). „Super cena / cena pro všechny“ (Coca-Cola 1,5 l za 32,90) e-shop jako akci neuvádí, protože se rovná běžné ceně.
- Leták na příští týden se zveřejňuje zřejmě v pondělí před středou platnosti **(předpoklad)**.
- Podmínky webu zakazují užití obsahu pro jinou než osobní potřebu ([O6](PLAN.md#7-otevřené-otázky)).

---

## Lidl

**Cesta:** JSON z kampaňových stránek webu (~1/3 letáku), akce z PDF potravinových letáků přes `pdftotext -bbox-layout` ověřené cenou za jednotku (R86) a text stránek letáku z API letáků pro zmínky bez ceny (R27). Náročnost střední.

### Web lidl.cz
- Kampaně najdeš na homepage v sekcích `data-id="…-Current_Sales_Week"` a `…-Next_Sales_Week`. Dlaždice mají `href="/c/ctvrtecni-nabidka/a10103788"` a `subheadline="V prodejnách od 1. 10."`.
- Potravinové kampaně: čtvrteční a víkendová nabídka, ovoce a zelenina, pečivo, ryby, 1+1 zdarma, tematické týdny, hity týdne. Slugy a ID se každý týden mění.
- Stránka `GET https://www.lidl.cz/c/{slug}/a{id}` (Nuxt SSR) má u každé dlaždice atribut `data-grid-data="{…}"`, což je JSON s HTML entitami.

```json
{"productId":10052485,"fullTitle":"Kanadské borůvky","category":"Food",
 "storeStartDate":1790805600,"storeEndDate":1791151199,
 "lidlPlus":[{"price":{"price":24.9,"prefix":"29,90 Kč bez Lidl Plus","oldPrice":44.9,
   "discount":{"discountText":"-44%"},"basePrice":{"text":"125 g - balení, 100 g = 19,92 Kč"}},
   "lidlPlusText":"s Lidl Plus"}],
 "stockAvailability":{"badgeInfoV2":[{"badges":[{"text":"V prodejnách pouze 01.10. - 04.10."}]}]},
 "canonicalUrl":"/p/kanadske-boruvky/p10052485"}
```
- Bez Lidl Plus je cena v `price` (`price`, `oldPrice`, `discount.percentageDiscount`, `basePrice.text`).
- Platnost je v `storeStartDate` / `storeEndDate` (unix).
- „Ceny v klidu“ (`/c/ceny-v-klidu/a10088117`) **nejsou akce**, jsou to trvalé ceny.
- **Search API (`/q/api/search`) zakazuje robots.txt, nepoužívat.**

### Leták
- Seznam letáků: odkazy `/l/cs/letak/{slug}/ar/0` na `https://www.lidl.cz/c/akcni-letak/s10008644`, nebo `relatedFlyers` v JSON letáku.
- Detail: `GET https://endpoints.leaflets.schwarz/v4/flyer?flyer_identifier={slug}&region_id=0&region_code=0`. Vrací `offerStartDate` / `offerEndDate`, `pdfUrl`, `pages[]` (obrázek, `zoom` 2400 px, `keyWords`) a `relatedFlyers`.
- **Potravinové letáky mají `products` prázdné.** Produkty mají jen nepotravinové letáky.
- PDF (25–34 MB) má použitelnou textovou vrstvu. `pdftotext -layout` sloupce míchá, `-bbox-layout` dá každé slovo s polohou — z ní se dlaždice skládají, viz [PDF letáku](#pdf-letáku-r86).
- Týdně jsou zhruba 4 potravinové letáky × 50–60 stran.
- **Vyhledávání v prohlížeči letáku** (`…/view/search/page/1`) prohledává jen `keyWords` stránek — žádný seznam produktů s cenami v JS není.
- **Zmínky bez ceny (R27, implementováno):** `LidlOfferSource::leaflets` vezme ze stránky letáků slugy s předponou `akcni-letak-od-` (bez „spotrebni-zbozi“, „hity-tydne“, „…cen-v-klidu…“), pro každý stáhne detail a uloží stránky do `leaflet_pages`: `keyWords` + `altText` (popis stránky větou), náhled `thumbnail` (400 px), odkaz `/l/cs/letak/{slug}/view/flyer/page/{n}`. Leták je zdroj `kind = leaflet` s akcemi z PDF (R86). Název = `name` + `title` („Akční leták OD ČTVRTKA 8. 10. - 11. 10. 2026“), platnost `offerStartDate` / `offerEndDate` (místní data).
- **Pasti `keyWords`:** slova bez pořadí, s velkými počátečními písmeny, čísla bez čárky („05“ = 0,5 l, „2997“ = 29,97); stránky s receptem vyjmenovávají suroviny („Vejce“, „Máslo“ — 8. 10. str. 18 a 49) a poznají se podle „Postup přípravy“, „Nákupní seznam“ nebo `altText` „Recept na…“. Coca-Cola bez „Zero“ na str. 28 je jen „možná“.

### PDF letáku (R86)
Implementace: `LidlLeafletParser` nad `PdfTextReader` (`pdftotext -bbox-layout`). `LidlOfferSource::leaflets` stáhne
`flyer.pdfUrl` (timeout `letaky.http.pdf_timeout_seconds`), převede ho a akce přidá do dávky letáku se stránkami.

**Rozvržení** (strana 601 × 1002 b.): dlaždice je sloupec zarovnaný vlevo, od shora:
- název ~15 b., značka a produkt na 1–3 řádcích („BARON“ / „Pravá uzená slanina“);
- popis ~12 b. (podmínky akce na více kusů ~9 b.): balení, druh a cena za jednotku — „125 g, 100 g = 20,72 Kč“,
  „500 g, s chráněným zeměpisným označením, 1 kg = 179,80 Kč“, „6 x 0,5 l, 1 l = 29,97 Kč“, „cena za 1 kg, chlazené“;
- štítek ~25–37 b.: „Super cena“ (i na dvou řádcích „Super“ / „cena“), „-28% 34.90“ (sleva a původní cena),
  „-66%“ a vedle malé „209.90**“ (doporučená cena výrobce), „Ušetřete *“ + „33%“, „4+2“ + „zdarma“, „Novinka“;
- Lidl Plus: malé „S Lidl Plus“ (~9 b.) a pod ním „-25%“ nebo „-10 Kč“;
- velká cena ~35–110 b. („25.90“); u Lidl Plus je to cena s aplikací.

Pasti:
- **Velké ceny sousedních dlaždic bývají v jednom řádku** („179.90 119.90 99.90“), štítek a vedle něj název jiné
  dlaždice taky („-40% 49.90 Mandarinky“). Řádky se proto dělí na úseky podle mezery (> 0,6 × výška písma) a velikosti
  písma (poměr > 1,5) a dlaždice se skládá z úseků nad velkou cenou s levým okrajem do 8 b. od ceny a mezerou do 20 b.
- **Běžná cena u Lidl Plus** je buď menší cena (~34–41 b.) nad „S Lidl Plus“ (sleva i bez aplikace, sekt „-33% 179.90**“
  119.90 a s aplikací 109.90), nebo malá cena (~15 b.) vpravo vedle velké nebo pod jejími desetinami („*standardní cena
  bez Lidl Plus“ = akce jen s aplikací). Cena za jednotku v popisu patří k ceně s aplikací, někdy k běžné.
- Akce na více kusů: velká cena je cena kusu při koupi více kusů, běžná cena kusu je malá cena vedle „zdarma“.
- Balení s desetinnou čárkou („0,7 l, alk. 25 % obj.“) — části popisu se dělí jen čárkou, za kterou není číslice.
- Cena za jednotku „/PP“ je za pevný podíl (okurky 330 g = 190 g) — s balením nesedí a dlaždice se vynechá.
- Hlavička strany „Od čtvrtka 8. 10. do 11. 10.“ chybí asi na polovině stran a jinde je rozbitá překrývajícími
  se vrstvami („Od pondělí čtvrtka xx. 8. 10.…“); patička „Nabídka zboží platí od 8. 10. do 11. 10. 2026“ je skoro
  všude. Pondělní leták (`offerEndDate` 11. 10.) má strany do 7. 10. i do 11. 10.
- Stejná dlaždice bývá na více stranách (bryndza str. 6 a 22).
- Strany s nepotravinovým zbožím (oblečení, nářadí) dlaždice nemají — popis nezačíná balením.

**Pravidla** (`LidlLeafletParser`):
- Cena se přijme, jen když ji ověří balení × cena za jednotku s tolerancí jako u Penny (2 haléře nebo 1,5 %, R26).
  Balení o jedné jednotce bez ceny za jednotku (1 kg, 1 l, kus, 100 g, „cena za 1 kg“, „cena za 100 g“) se přijme
  jen přímo nad štítkem. Neověřitelná dlaždice (bez názvu ve sloupci, „98 g / 100 g“, „⌀ 12 cm“, „balení“) zůstane zmínkou.
- „-NN% původní“ = sleva, procento musí sedět na ±1; jinak se dlaždice vynechá. „-50 Kč“ s původní cenou vedle musí
  sedět přesně. „Super cena“, „Ušetřete* NN%“ a cena bez štítku = akční cena, ne sleva (R8).
- Lidl Plus = `loyalty_price` vedle běžné ceny; s běžnou cenou nad štítkem typ podle ní (sleva / akční cena), se
  standardní cenou vedle nebo bez ní `LoyaltyOnly` jako na webu. Štítek s aplikací musí sedět k původní, jinak k běžné ceně.
- „N+M zdarma“ = `Multibuy`, cena je běžná cena kusu (jako `LidlParser`).
- Platnost: hlavička strany (rok z patičky nebo letáku), jinak patička, jinak leták; „jen v sobotu“ v dlaždici ji zkrátí
  (v letácích 5. a 8. 10. 2026 se nevyskytlo).
- Název = řádky názvu („Hladká/ Polohrubá“ bez mezery za lomítkem), popis = celý popis, balení = první část popisu.
  `source_url` = stránka v prohlížeči letáku, obrázek není. ID `letak-` + otisk názvu, balení a velké ceny (R16).
- **Duplicity s webem** (CLAUDE.md bod 7): dlaždice se vynechá, když akce z webu má stejnou cenu (bez aplikace nebo
  s ní), překrývající se platnost a všechna výrazná slova (≥ 4 znaky, ne čísla) kratšího názvu jsou v delším. Jedno
  společné slovo nestačí — „PIKOK Kladenská pečeně“ a „PIKOK PURE Dušená šunka“ za 19,90 by splynuly.
- Chyba stažení nebo převodu PDF (`PdfTextFailed`) ukončí stažení celého Lidlu — jinak by akce z letáku byly „stažené“ (R16).

**Výsledek 6. 10. 2026** (měřeno na celých letácích proti kampaním webu staženým týž den, 104 potravinových akcí):

| Leták | Stran | Velkých cen | Ověřeno | Duplicita webu | Nových akcí |
|---|---|---|---|---|---|
| od čtvrtka 8. 10. | 50 | 247 | 147 | 54 | 93 (11 s Lidl Plus) |
| od pondělí 5. 10. | 51 | 207 | 116 | 33 | 83 |

- Ceny proti webu: 87 dlaždic odpovídá akci z webu (stejný výrobek podle názvu), cena sedí u všech; 5 dvojic podobných
  názvů byly jiné výrobky (listové těsto 400 g / s máslem 230 g, lískové / vlašské ořechy, Kozel 11 / 10…). Leták často
  uvádí původní cenu, kterou web nemá („-50% 49.90“ u hroznů, web jen 24,90).
- Ruční kontrola 20 náhodných přijatých dlaždic (10 z každého letáku) proti textu strany: všech 20 správně.
- Nevzaté velké ceny: nepotravinové strany (oblečení, nářadí — popis bez balení), „Ceny v klidu“, dlaždice s názvem
  mimo sloupec ceny (mandarinky, okurka na str. 1), balení s více variantami („90 g /95 g /97 g“), „/PP“, vejce „30 ks“
  bez ceny za jednotku. Uloží se i drogerie a svíčky z potravinového letáku — PDF kategorii nemá.
- Pondělní leták má 18 ověřených akcí platných do 11. 10.; zmizí-li leták ze seznamu dřív, označí se jako stažené (R16)
  — pojistka R54 to zastaví jen při propadu nad 40 %.

### Pole a pasti
Implementace: `app/Domain/Sources/Lidl/` — kampaně z úvodní stránky, jen kategorie `Food` (R23).

- **Kampaně nejdou podle adresy rozlišit** na potravinové a nepotravinové (móda, dílna). Stahují se všechny odkazy `/c/…/a{id}` z úvodní stránky (2. 10. 2026: ~40 stránek, s pauzou 0,5 s ~30 s) a ukládají se jen potraviny. „Ceny v klidu“ úvodní stránka neodkazuje, vyloučení v konfiguraci je pojistka.
- **Lidl Plus:** cena s aplikací je `lidlPlus[0].price.price`; cenu bez aplikace uvádí jen `prefix` („29,90 Kč bez Lidl Plus“) a jen někdy. Bez ní je běžná cena `oldPrice` a akce platí jen s aplikací.
- `discountText`: „-25%“ sleva, „1+1 zdarma“ / „3+1 zdarma“ akce na více kusů (`price` je cena za kus při koupi více kusů, `oldPrice` za jeden), „Super cena“ a „Ušetřete* xx %“ nejsou slevy.
- `basePrice.text` míchá balení a cenu za jednotku: „210 g, 100 g = 28,52 Kč“, „500 g - balení,1 kg = 49,80 Kč“, „195 g, 100 g = 13,90 Kč/PP“, ale i jen „1 kg = 64,95 Kč“ (balení neuvedeno — nesmí se číst jako 1 kg).
- Položky „Pouze v prodejnách“ (víkendová vína, prosecco) nemají `storeStartDate` — převezmou platnost ostatních akcí stránky.
- Potraviny na příští týden jsou často **jen v letáku**, web je zatím nemá (2. 10.: vejce 30 ks a Coca-Cola Zero na 8. 10. jen v PDF).
- **„Ušetřete* xx %“ je úspora na ceně za jednotku** (větší balení), ne sleva oproti původní ceně ([R8](PLAN.md#8-log-rozhodnutí)).
- „Rozšířená nabídka“ znamená jen ve vybraných prodejnách. Limity „Max. N balení na nákup“ jsou jen v letáku.
- Úterní nabídka po skončení zmizí z webu (404).
- „Freeway Cola Zero“ je privátní značka Lidlu, ne Coca-Cola.
- CDN Myra Security za ~60 požadavků s 1s pauzou neblokovala.

---

## Penny

**Cesta:** JSON API webu (jen výběr ~33 položek) a k tomu vektorová vrstva letáku (SVG). Náročnost střední až vysoká.

### API penny.cz (Nuxt a commercetools)
| Účel | Požadavek |
|---|---|
| Akční produkty | `GET https://www.penny.cz/api/product-discovery/products?page=0&pageSize=100` |
| Kategorie | `GET https://www.penny.cz/api/product-discovery/categories/tree`, kde je např. kategorie týdne „Web od 30.09.2026“ a podkategorie „produkty s PENNY kartou“ |
| Produkty kategorie | `GET https://www.penny.cz/api/product-discovery/categories/{slug}/products` |

Žádné cookies, CSRF ani speciální hlavičky. **Ceny jsou v haléřích.**
```json
{"name":"Vejce čerstvá M z podestýlky Karlova Koruna","amount":"10","volumeLabelShort":"ks","category":"VEJCE",
 "sku":"88-300602",
 "price":{"crossed":4990,"discountPercentage":-50,
   "regular":{"value":2490,"perStandardizedQuantity":249,"tags":["pt-aktion"]},
   "lowestPrice":2990,"baseUnitShort":"ks","validityStart":"2026-09-30","validityEnd":"2026-10-06"}}
```
U cen s kartou je navíc `"loyalty":{"value":2490,"tags":["SO"]}` a `regular` pak znamená cenu bez karty.

### Leták (FlippingBook na files.rewe.co.at)
- URL letáku: `https://files.rewe.co.at/PennyIntLeaflet/CZ/{DD_MM_YYYY}/`. Odkaz se dá vyčíst ze stránky `https://www.penny.cz/nabidky/letaky` (hledat `PennyIntLeaflet/CZ/`). **Složka může mít příponu verze** (`07_10_2026_tl2/`, 6. 10. 2026) — vzor ji musí připustit, jinak se leták příštího týdne tiše přeskočí.
- **Nejlepší zdroj je vektorová vrstva stránek:** `…/files/assets/common/page-vectorlayers/0001.svg` až `00NN.svg`. Obsahuje `<svg:text transform="matrix(a b c d e f)">` s `<svg:tspan x="…" y="…" fill="…">`, tedy každý token se souřadnicemi, velikostí a barvou. Název, gramáž, cena, přeškrtnutá cena a % jdou spárovat podle pozice, nebo se tokeny s pozicemi předají LLM (levnější než vision).
- **Mezery v textu jsou nezlomitelné (U+00A0)** — `rtrim` ani vzor s obyčejnou mezerou je nechytí (`"500 g | "` končí U+00A0, „cena bez pennykarty“ má U+00A0 mezi slovy). Balení a cena za jednotku bývají **dva tokeny na stejném účaří** (`"500 g |"` a `"100 g 3,98 Kč"` o 17 b. vpravo), takže padnou do různých sloupců; jinde je cena za jednotku na dvou řádcích (`"100g"` / `"31,96 Kč"`) nebo bez haléřů (`"1 kg 49 Kč"`).
- **Vlastní glyfy ve fontu cen** (odvozeno shodou s API 2. 10. 2026, nedokumentováno):
  - velká cena „24Ǻ“: `Ǻ` (U+01FA) = „,90“ — 560 výskytů, jiné haléře se v letáku neobjevily (výjimečně `Ƿ`, `ɏ` — přeskočí se)
  - malá cena „49“ „,“+U+E00A+U+E009 = „49,90“; U+E00A = „9“, U+E009 = „0“
  - červený glyf U+E010 / U+E011 / U+E00F za malou cenou je **přeškrtávací čára** pro 1- / 2- / 3místnou cenu, ne číslice — podle ní se pozná přeškrtnutá (původní) cena
- Index letáku (`…/{DD_MM_YYYY}/`) odkazuje na stránky `href="./2/"` … `./37/` → počet stran. **Některé strany vektorovou vrstvu nemají** (2. 10.: strany 9 a 33, jen obrázek) → 404, přeskočí se.
- PDF (`…/files/assets/common/downloads/{DD_MM_YYYY}.pdf`) má kvůli fontu rozbité ceny, nepoužívat.
- Obrázky stran jsou jen pozadí bez textu.

### Parser letáku (bez LLM, R23, R26, R85)
Implementace: `app/Domain/Sources/Penny/PennyLeafletParser.php`, podrobný postup v jeho popisu.

- Rozvržení dlaždic se liší stránku od stránky (název nad cenou, vedle ní, bílý na barevném pruhu), pevné okno kolem ceny nefunguje.
- **Řádek končící „|“ pokračuje** nejbližším tokenem vpravo na stejném účaří (do 60 b.) — spojí se před skládáním bloků.
- **První kolo: přiřazení ověřené cenou za jednotku**, kterou leták u potravin uvádí: „250 g“ + „100 g 5,16 Kč“ sedí jen k 12,90 Kč. Sousední dlaždice se tak nespletou. Varianty „270/280 g“ + „100 g 7,37/7,11 Kč“ musí sedět obě. Blok textu bez ceny za jednotku se v prvním kole přijme jen u balení 1 kg / 1 l / 1 ks přímo nad cenou. Každá cena i blok nejvýš jednou: nejbližší dvojice, pak rozšiřující cesty (dvě sousední stejné ceny 34,90 Kč u Oreo a Skittles).
- **Druhé kolo: rozvržení (R85).** Blok bez ceny za jednotku (sýr 100 g, „cena za 1kg“ vedle ceny, nepotraviny „1ks“) se přijme, když vůči ceně leží se stejným posunem (±3 b.) jako aspoň 3 ověřené dlaždice téže stránky nebo 8 v celém letáku, a cena i blok mají jediného kandidáta. Posuny celého letáku sbírá `PennyOfferSource` prvním průchodem přes všechny stránky (`tileOffsets`, `commonOffsets`) — stránka se zeleninou sama nic neověří. Blok s nesedící cenou za jednotku se nepřijme nikdy.
- **PENNY karta:** dlaždici pozná drobný štítek „PENNY Karta“ u ceny (ne nadpis oddílu „MOJE PENNY KARTA“, 10,2 b.). Velká cena je cena s kartou (`loyalty_price`, `LoyaltyOnly`), běžná je malé číslo u popisku „cena bez pennykarty“; bez něj se dlaždice neuloží.
- **Cena za více kusů** (Jägermeister: „při koupi 1 ks cena 169,90 Kč od 2 ks cena 149,90 Kč“, u ceny „při koupi 2 a více ks“): jako u Billy `Multibuy`, cena kusu a v `promotion_text` „od 2 ks: 149,90 Kč“. Bez čitelné ceny kusu se dlaždice neuloží.
- **Přeškrtnutá cena bez čáry:** titulní strana glyf čáry nemá — malé číslo vpravo pod cenou se uzná, jen když sedí procento ze štítku slevy („40 %“, ±1 procentní bod).
- Výsledek na letáku 30. 9. 2026 (35 stran, 574 velkých cen): **dřív 297, teď 489 akcí** (R85), žádná dřívější nezmizela; kontrola štítku slevy u 337 akcí bez nesouladu. Zbytek (~85: nepotraviny bez balení, velké dlaždice zeleniny mimo mřížku, chyby letáku jako kefír 500 ml „100 ml 19,80 Kč“, „+25 % navíc“) se neuloží — raději chybějící akce než špatná cena.
- Položka letáku, kterou nese i API (stejná cena, stejné balení, společné slovo názvu), se neuloží podruhé. Samotná shoda slov nestačí — „Karlova Koruna“ je u desítek položek.
- Externí ID akce z letáku je otisk názvu, balení a ceny (`letak-…`), leták kód zboží nemá.
- **Text stránek pro zmínky bez ceny (R27):** `PennyLeafletParser::pageText` spojí tokeny shora dolů a zleva doprava; ukládá se do `leaflet_pages` s odkazem `…/{DD_MM_YYYY}/{n}/`, bez náhledu. Zmínka se ukáže jen tam, kde k položce Penny v tom období nemá akci s cenou.

### Pole a pasti
- Adresa produktu na webu: `https://www.penny.cz/products/{slug}` (`/produkty/` vrací 404).
- API: `price.loyalty` bez štítku `pt-aktion` u `regular` = akce jen s PENNY kartou, `regular` je běžná cena.
- API pokrývá jen malou část letáku: polotučné mléko v akci bylo **jen v letáku**.
- Víkendové akce mají platnost jen v textu stránky („platí od pátku 2. 10. do neděle 4. 10.“).
- „Jedinečná nabídka“ bez přeškrtnuté ceny není sleva. `lowestPrice` = nejnižší cena za 30 dní.
- **Starý leták zmizí** (404) a API drží jen aktuální týden, takže archivovat ([R10](PLAN.md#8-log-rozhodnutí)).
- Nový leták se objeví zřejmě v úterý nebo ve středu **(předpoklad)**.

---

## Albert

**Cesta:** jen leták. Metadata z GraphQL, **akce s cenou z PDF letáku** (`pdftotext -bbox-layout`, R86) a **text stránek z Publitas** (`spreads.json`, R36) pro zmínky bez ceny. Náročnost střední.

Albert nemá HTML výpis akcí a e-shop už neprovozuje. Katalog `productSearch`
v GraphQL má `potentialPromotions` vždy prázdné, takže **není zdrojem akcí**.
Použitelný je nanejvýš pro párování produktů (kód, obrázek, kategorie).

### Endpointy
Seznam letáků (ad-hoc dotaz funguje, persisted query není potřeba):
```
POST https://www.albert.cz/api/v1/
Content-Type: application/json

{"query":"{ getLeaflets(locationType:\"SUPERMARKET\", onlyDefault:false){ leaflets { id isDefault title locationType validityStartDate validityEndDate viewUrl downloadUrl stores { localizedName } } } }"}
```
```json
{"id":"3382787","isDefault":true,"locationType":"SUPERMARKET",
 "validityStartDate":"29/09/2026 22:00:00","validityEndDate":"06/10/2026 21:59:59",
 "viewUrl":"https://letaky.albert.cz/40sm_akcni_letak/","downloadUrl":"https://view.publitas.com/90263/3382787/pdfs/…pdf"}
```
`locationType` je `HYPERMARKET` / `SUPERMARKET`.

Publitas (Albert CZ, groupId 90263):
| Účel | URL |
|---|---|
| Metadata | `https://letaky.albert.cz/{slug}/data.json` — `numPages` a **`config.downloadPdfUrl`** (odkaz na PDF) |
| PDF letáku | `https://view.publitas.com/90263/{id}/pdfs/{uuid}.pdf?response-content-disposition=…` — 28 MB (HM, 54 stran), 42 MB (SM, 45 stran); číslo strany PDF = `page/{n}` prohlížeče |
| Stránky | `https://letaky.albert.cz/{slug}/spreads.json` — pole dvoustran, u každé stránky `number`, **`text`** (text stránky v pořadí čtení) a `images` (`at200` 151×263 … `at2400` 1818×3169, cesty relativní k `https://letaky.albert.cz`) |
| Stránka v prohlížeči | `https://letaky.albert.cz/{slug}/page/{n}` |
| Hotspoty | `https://letaky.albert.cz/{slug}/page/{n}/hotspots_data.json` (jen pár externích odkazů, **žádné produkty**) |

### Akce z PDF letáku (bez LLM, R86)
Implementace: `app/Domain/Sources/Albert/AlbertLeafletParser.php`, podrobný postup v jeho popisu. `AlbertOfferSource`
stáhne ke každému hlavnímu letáku `data.json` a PDF (`letaky.http.pdf_timeout_seconds`, pauza `request_delay_ms`),
text s polohou dá `PdfTextReader` (pdftotext) a akce přidá do dávky letáku vedle stránek pro zmínky.

- **Dlaždice:** název písmem ~14,6 b., pod ním popis ~10,5 b. s odrážkami — balení, cena za jednotku
  („100 ml = 33,27 Kč“, „1 ks od 4,99 Kč“, „1 dávka = 1,11 Kč“), „vybrané druhy“, „platí do 13. 10. 2026“
  a **▼ nejnižší cena za 30 dní** („▼ 44,90 Kč“ — není to akční cena, do popisu se neukládá).
- **Akční cena** ~31–50 b.: koruny a haléře jako dvě slova („49“ „90“, haléře menší a nahoře, někdy dva bloky)
  nebo „599,“ „-“. **Přeškrtnutá / běžná cena** („BĚŽNÁ CENA“ je obrázek) je jedno slovo bez dalšího za sebou:
  „69,90“, „169,-“, „1199,-/“. Sleva „- 50 %“ jsou tři slova; Albert procento uřezává (37,6 % → „- 37 %“).
- **Cena s aplikací Můj Albert:** velká cena je ta s aplikací, menší „BEZ APLIKACE 39 90“ pod ní je běžná
  a cena za jednotku je dvojí („1 l = 53,20 Kč bez Aplikace / 46,54 Kč Aplikace“). Bez menší ceny (Mlynářské
  pečivo) je cenou bez aplikace přeškrtnutá cena. Ukládá se `loyalty_price` (`muj_albert`) vedle `price`;
  procento na cenovce patří k ceně s aplikací, proto se `discount_percent` u ní neukládá.
- **Cena leží nad názvem, pod ním i vedle něj** — přiřadí se jen ověřená: cena přepočtená na balení musí dát
  každou cenu za jednotku dlaždice (tolerance jako Penny, R26), u „od“ s největším balením. Ze sedících dvojic
  vyhrávají nejbližší (do 120 b.). Sleva v procentech u ceny musí sedět na přeškrtnutou cenu, jinak se dlaždice vynechá.
- **Řádky pdftotext se nepoužívají** — slučují slova sousedních dlaždic („- 32 % Vepřová“, „debrecínka 21,90/“);
  řádky se skládají ze slov podle výšky písma a polohy.
- **Neověřitelné (zůstanou zmínkou):** balení 1 kg / 1 l / 1 ks bez ceny za jednotku (ovoce, maso, mouka),
  „cena za 100 g“ (uzeniny, lahůdky), zboží bez ceny za jednotku (elektro, květiny), konzervy s cenou za jednotku
  z hmotnosti po odkapání (sardinky, tuňák — nesedí na balení), akce „CENA ZA 1 bal. PŘI KOUPI 2 bal.“.
  „1 BOD NAVÍC při koupi 2 kusů“ a „+1 KREDIT NAVÍC“ jsou body věrnostního programu, cena platí.
- **Platnost:** „platí do …“ v dlaždici, poznámka „*Tato nabídka platí od čtvrtka …“ pro názvy s hvězdičkou,
  oddíl „PLATÍ POUZE PÁ–NE“ s daty velkým písmem (dlaždice pod ním), „Nabídka na této straně platí od … do …“,
  jinak platnost letáku.
- **Externí ID** je otisk názvu, balení a cen (`letak-…`); titulní strana opakuje další strany a strana 54
  je kopie strany 8 — stejná akce se uloží jednou. Akce se stejným klíčem v letáku HM i SM platí ve všech
  prodejnách (bez formátu).
- **Výsledek 6. 10. 2026 (týden 40):** HM 596 akčních cen → 416 ověřených (405 akcí po odstranění duplicit),
  SM 452 → 323 (311). Nevzaté: balení 1 kg / 1 l / 1 ks bez ceny za jednotku 66 + 44, jiné dlaždice bez ceny
  za jednotku 58 + 28, cena bez dlaždice v okolí (titulní strany, soutěže) 24 + 24, „cena za 100 g“ 17 + 19,
  cena za jednotku nesedí 14 + 12, akce na více kusů 1 + 1, dlaždici vzala bližší cena 0 + 1. Ruční kontrola 15 náhodných akcí: vše správně;
  procento slevy u ceny nesedí 0×. Převod celého letáku pdftotext ~7 s.

### Pole a pasti
- **Text stránek (R36, implementováno):** `AlbertOfferSource` stáhne hlavní letáky HM a SM (`isDefault`, lokální varianty ne) a text stránek uloží pro zmínky bez ceny. Text obsahuje názvy, balení i ceny, ale ceny jsou rozsekané („-34 %“ „31“ „90“, „3490“ = 34,90, „48,90/“ = původní cena) a pořadí bloků neodpovídá dlaždicím; titulní strana opakuje obsah další strany a některé strany jsou v letáku dvakrát. Akce s cenou proto z PDF s polohou slov (výše).
- **Selhání PDF ukončí celé stažení chybou** (`PdfTextFailed`, HTTP, `data.json` bez odkazu) — bez akcí jednoho letáku by je import označil jako stažené obchodem (R16). Nula akcí je chyba jako u ostatních obchodů (R54); `mentions_only` už Albert nemá.
- `isDefault` mají i letáky dalšího týdne a katalogy (`41sm_akcni_letak`, `41sm_akcni_katalog_brand`) — stahuje se PDF každého z nich (v úterý až 6 PDF po 25–45 MB).
- Platnost je v UTC a ve formátu `DD/MM/YYYY HH:MM:SS` (`22:00` = půlnoc místního času).
- **Hypermarket a supermarket mají odlišné letáky** (`40hm_akcni_letak`, `40sm_akcni_letak`). Existují i lokální varianty (`40sm_akcni_letak_frenstat`, `isDefault=false`) a výjimky prodejen.
- **Dvojí cena:** s aplikací Můj Albert (modrá cenovka) a „BEZ APLIKACE xx,xx“.
- Platnost bloků uvnitř letáku: „PLATÍ POUZE PÁ–NE 2.–4. 10.“, „do 27. 10.“.
- Další pole jen v obsahu stránky: „BĚŽNÁ CENA“, „▼ xx Kč“ (nejnižší cena za 30 dní), limity („MAXIMÁLNĚ 25 ks/den“), štítky „NEPORAZITELNÉ“ a „vybrané druhy“.
- Textová vrstva PDF bez polohy ztrácí desetinnou čárku (haléře v horním indexu: „9490“ = 94,90) a sloupce jsou rozházené; s polohou (`-bbox-layout`) jsou haléře samostatné slovo menším písmem nahoře a dlaždice jdou poskládat (výše). Vlastní fonty dávají řídicí znak U+0007, `PdfTextReader` ho vynechá.
- Slug dalšího týdne je `{týden}{hm|sm}_akcni_letak` a dá se zkoušet dopředu **(předpoklad)**.
- Doména má Akamai Bot Manager, ale požadavky (GraphQL, Publitas, obrázky) prošly i s výchozím UA. Při vyšší frekvenci může blokovat **(předpoklad)**.
- **User-Agent s adresou `https://…` jde přes prerender** (ověřeno 4. 10. 2026, R65): Albert ho bere jako robota vyhledávače a pošle na prerender, který zahodí `Content-Type` — Apollo GraphQL pak dotaz zablokuje jako CSRF a vrátí 400 se stránkou HTML. UA proto bez schématu: `Slevohlidka/1.0 (+slevohlidka.rhsoft.cz)` projde (200).
- VOP zakazují stahování obsahu e-shopu a aplikace, na letáky nemíří výslovně ([O6](PLAN.md#7-otevřené-otázky)).

---

## Billa

**Cesta:** product-discovery API webu (stejná platforma REWE jako Penny, Nuxt a commercetools) s **celým katalogem**,
bez LLM — implementováno (R48, `app/Domain/Sources/Billa`); API ukazuje jen dnešní stav, **letáky, které ještě
nezačaly, z PDF** (`pdftotext`, R89) spárované s katalogem. Náročnost nízká až střední (bez platnosti akcí).
`robots.txt` jen odkazuje na sitemap (žádné Disallow), bez WAF, cookies ani zvláštních hlaviček.

### Endpointy
| Účel | Požadavek |
|---|---|
| **Celý katalog (hlavní zdroj)** | `GET https://www.billa.cz/api/product-discovery/products?page={n}&pageSize=500` — 12 184 produktů, 25 požadavků po ~1,2 MB, ~2 s každý |
| Jen akce (nepoužívá se) | totéž s `&inPromotion=true` — 3 042 položek, ale **bez akcí jen s BILLA Klubem** |
| Detail (nepoužívá se) | `GET /api/product-discovery/products/{sku}` (`82-100073`; se slugem 404) — platnost akce také nemá |
| Seznam letáků (R89) | `GET https://www.billa.cz/akcni-letaky` — karty `<a class="ws-teaser …" data-teaser-name="Velký leták">` s textem „Platí od středy 7. 10. do úterý 13. 10. 2026“ (i „od středa“) |
| Stránka letáku (R89) | odkaz karty: `/letaky-billa?tab=letaky-billa/velky-letak`, `…/maly-letak`, `…/velky-letak-nasledujici`, `/akcni-letaky/letak-billa-klub`, `/akcni-letaky/katalog-{název}`, `/akcni-letaky/special-{prodejna}` — tlačítko „Stáhnout PDF“ s přímým odkazem |
| PDF letáku (R89) | `https://view.publitas.com/64069/{id}/pdfs/{uuid}.pdf` — velký leták 41 stran, 50–54 MB, ~4 s; Publitas skupina `billa-cz` |
| Text stránek letáku — nepoužívá se | `https://view.publitas.com/billa-cz/{slug}/spreads.json` (`pages[].text`) |

`page` od 0, `pageSize` nejvýš 500 (víc = 400). Odpověď `{facets, count, offset, total, results, isTotalTruncated}`;
stránkuje se do `total`. Celé stažení lokálně ~47 s (pauza `LETAKY_BILLA_REQUEST_DELAY_MS`, výchozí 1 s).
Detail produktu na webu: `https://www.billa.cz/produkt/{slug}` (`/produkty/` i `/products/` vrací 404).

### Převod (`BillaParser`)
- **Akce** = štítek `pt-aktion` nebo `pt-multi` v `price.regular.tags` (`promotion_tags`); `pt-abverkauf` (doprodej) ne.
  Ceny v haléřích (int).
- `standard.value` (= `crossed`) vyšší než `regular.value` = **sleva** s původní cenou, `discountPercentage` je
  **záporné**. Jinak akční cena (1 položka: Savo s `crossed` nižším než akční cena).
- **Akce jen s BILLA Klubem** (~370): `regular.tags` prázdné a `price.loyalty` se štítkem `pt-loyalclub` nižší než
  `regular.value` → `LoyaltyOnly`, `regular` je běžná cena. Filtr `inPromotion` je nevrátí — proto celý katalog.
  U akce se štítkem `pt-aktion` bývá `loyalty` stejná nebo dražší (Bella For Teens) — bere se jen nižší.
- **Akce na množství** (`pt-multi` a `promotionQuantity` ≥ 2): `PER_SET_OF` („cena 1ks při koupi 3ks“) a `FROM`
  („od 2 ks“) → `Multibuy`, cena = běžná cena kusu (`standard.value`), výhodná cena kusu v textu akce
  („cena 1ks při koupi 3ks: 9,93 Kč“) — jako Tesco a Lidl. `pt-multi` s `promotionQuantity` 0,001 u váženého zboží
  je obyčejná akce.
- **Zboží na váhu** (jako Tesco): `weightPieceArticle` → `value` je cena odhadovaného kusu (pomeranč 9,87 Kč),
  bere se `perStandardizedQuantity` (za kg) i u `standard`; `weightArticle` → `value` za kg, `amount` 1000 g.
- Balení `amount` + `volumeLabelShort` („0.7“ „l“ → 0,7 l). `descriptionShort` je vždy stejný jako název, popis se
  neukládá. Značka `brand.name`, kategorie `category`, obrázek `images[0]`.
- Odznak `eshop-only` (~25) = akce jen v e-shopu → `online_only` (Billa má `has_eshop`, uživatel je může skrýt).

### Platnost — API ji nemá
- API ukazuje stav v okamžiku dotazu. Platnost akce je **akční týden Billy, který obsahuje dnešek**: středa–úterý
  (`week_start_iso_day` = 3), stejně jako leták („Platí od středy 30. 9. do úterý 6. 10. 2026“ na `/akcni-letaky`).
  Zdroj „akce z webu“ má ID `web-{středa}`.
- Co Billa ukončí dřív (víkendové a denní akce „SUPER STŘEDA“, „ČTVRTEK–NEDĚLE“), z API zmizí a import to označí
  jako stažené (R16). Akce, která pokračuje do dalšího týdne se stejnou cenou (i s Klubem), prodlouží svůj řádek
  — převezme začátek platnosti uložené akce (`extends_continuing_offers`, R54); se změnou ceny je to nová akce.
  Bez toho by měla každý týden nový řádek a souhrn by ji poslal znovu jako novou. Stahuje se denně (kvůli dřívějším koncům), cron až po
  ranní výměně akcí (6:00) — ve středu brzy ráno by API mohlo ještě ukazovat minulý týden **(předpoklad)**.
- Leták uvádí skutečnou platnost i oddílů („SUPER STŘEDA 7. 10.“, víkend čt–ne, „PONDĚLÍ A ÚTERÝ“), API ne — dopočítaná
  platnost zůstává i u akcí, které už platí (rozbor v R89: platnost z letáku by měnila klíč akcí a rozbila R54).

### PDF budoucích letáků (R89)
Implementace: `BillaLeafletList` (seznam letáků, odkaz na PDF), `BillaLeafletParser` nad `PdfTextReader`
(`pdftotext -bbox-layout`), `BillaCatalogMatcher` (párování s katalogem) a zapojení v `BillaOfferSource`.

**Výběr letáků:** karty stránky `/akcni-letaky` se začátkem po místním dnešku a s odkazem podle `pdf_leaflet_paths`
(velký a malý leták, leták BILLA klub, katalogy) — ne speciály prodejen (`/akcni-letaky/special-…`, otevření jedné
prodejny) a ne katalogy s názvem podle `pdf_excluded_titles` (drogerie, elektro, textil, hračky…). Ke každé kartě stránka
letáku (1 požadavek) a PDF (1 požadavek, `letaky.http.pdf_timeout_seconds`), pauza `request_delay_ms`. Stránka
`/letaky-billa?tab=…` má v datech Nuxtu odkazy na PDF všech záložek (se znaky `/`) — bere se první obyčejný odkaz,
tlačítko aktivní záložky. Stejné PDF se stahuje jednou. 6. 10. 2026 byl budoucí jen velký leták od 7. 10. (malý leták
dalšího týdne stránka ještě neodkazovala, katalogy a leták Klub platily od 30. 9. / 23. 9.).

**Rozvržení** (strana 567 × 794 b.):
- název ~10,9 b. (na titulní a hlavních dlaždicích až ~33 b.), druh nebo značka ~7,8 b. nad i pod ním („Olma“ /
  „Klasik jogurt bílý“, „Božkov“ / „Standard“), popis ~8,7 b. („volná, 1 kg“, „130 g“, „více druhů“, „2 druhy“),
  cena za jednotku ~7,4 b. („100 g = 7,11 Kč“, „100 g = 13 Kč“, „100 g = od 24,91 Kč“); řádky zarovnané vlevo nebo vpravo
  (text bývá vlevo od ceny a zarovnaný k ní);
- sleva „-52%“ (~25 b.) nad velkou cenou „11,90“ (~22 b., jedno slovo) a pod ní vpravo přeškrtnutá cena „24,90/“ (~6 b.);
- **cena s BILLA Klubem:** velká cena je cena s Klubem, pod ní „běžná cena 23,90“ (cena bez Klubu, ~5–6 b., „běžná cena“
  menším písmem než číslo) a cena za jednotku dvojí „100 g = 13 Kč s Klubem/ 18,38 Kč bez Klubu“; logo Klubu je obrázek;
- **akce na množství:** velká cena je cena kusu při koupi více kusů, v popisu „při koupi 1 ks 29,90“ s vlastní cenou
  za jednotku, u ceny štítek „PŘI KOUPI OD 3 KS“, u „1+1“ „KUPTE 2 ZAPLAŤTE 1“; „KUPTE 3 ZAPLAŤTE 2“ bez ceny kusu
  v popisu (velká cena 13,27 Kč) se vynechá;
- „NAŠE CENA“ / „SUPER CENA“ = akční cena bez přeškrtnuté ceny (R8; API ji po začátku vrací jako slevu s `crossed`);
- **oddíly s platností:** „SUPER STŘEDA“ / „7. 10.“, „PŘIPRAVTE SE NA VÍKEND UŽ VE ČTVRTEK“ / „OD 8. 10. DO 11. 10.“,
  „PLATNOST OD 8. 10.“, rámeček „SUPER PÁTEK 9. 10.“, hlavička přes celou stranu „ČTVRTEK–NEDĚLE 8. 10. – 11. 10. 2026“
  (strana 36) a „SUPER PONDĚLÍ A ÚTERÝ 12. 10. – 13. 10. 2026“ (strana 38); strana 37 víkend pokračuje bez hlavičky;
  „Akce platí 12. 8. – 27. 10. 2026.“ je poznámka pod dlaždicemi (dlouhodobá akce, nese ji API);
- strana kupónů aplikace „s kupónem/ 19,85 Kč bez kupónu“ (strana 40) — nejsou to akce obchodu;
- glyfy písma: „ż“ = „ž“ („Petrżel“, „Cibule żlutá“), „ŭ“ = „ů“ („Mŭj skyr“), „ǜ“ = apostrof („Hellmannǜs“).

**Pravidla dlaždic** (`BillaLeafletParser`):
- Cena se přijme, jen když ji ověří balení × cena za jednotku (tolerance R26); u Klubu cena s Klubem „s Klubem“
  a cena pod ní „bez Klubu“; u akce na množství každou cenu za jednotku dá velká cena nebo cena kusu. Ze sedících dvojic
  cena–dlaždice vyhrávají nejbližší. Sleva „-N%“ musí sedět na přeškrtnutou (běžnou) cenu — Billa zaokrouhluje.
- Typ: „běžná cena“ nebo cena za jednotku „s Klubem“ = `LoyaltyOnly` (cena bez Klubu + `loyalty_price`, `BillaKlub` —
  jako akce jen s Klubem v API), přeškrtnutá cena = `Discount`, „při koupi 1 ks“ = `Multibuy` (cena = cena kusu, text
  „od 3 ks: 19,90 Kč“ jako API, R48), jinak `PromoPrice`.
- Platnost: nejbližší oddíl s datem nad dlaždicí ve stejném sloupci (nadpis nad datem rozšíří sloupec, sahá nejvýš
  0,3 výšky strany) nebo hlavička přes celou stranu, jinak platnost letáku. Strana za stranou s hlavičkou s vlastní
  platností, která nemá vlastní velký nadpis, má platnost nejistou — dlaždice bez oddílu nad sebou se vynechají.
- Neověřitelné (vynechají se): zelenina, ovoce a maso „volná, 1 kg“, „1 ks“, „cena za 1 kg“, pult „cena za 100 g“,
  víc velikostí „od 270 g“ (největší balení leták neuvádí), cena za jednotku z hmotnosti po odkapání bez ní v balení.
- Velký leták s méně než `pdf_main_min_items` (40) ověřenými dlaždicemi a chyba stažení nebo převodu PDF
  (`PdfTextFailed`, HTTP) ukončí stažení celé Billy — jinak by akce letáku byly „stažené“ (R16).

**Totožnost s katalogem** (`BillaCatalogMatcher`): PDF nemá kód zboží, ale API vrací celý katalog i s produkty, které
dnes v akci nejsou, a jejich běžnou cenu (`standard.value`, když je vyšší než aktuální, jinak `regular.value`; u zboží
na kusy vážené za kg). Přeškrtnutá cena, „běžná cena“ u Klubu nebo cena kusu u akce na množství = běžná cena produktu.
- Kandidáti: stejná běžná cena a stejné balení (±1 %, u „od“ i větší); vyhrají ti s nejvíc slovy názvu z letáku
  v názvu nebo značce produktu (bez diakritiky, začátek slova, slova ≥ 3 znaky bez „z naší pece“, „z teplého pultu“),
  aspoň 60 %. Procenta v názvu („12%“, „31%“) musí být i v katalogu.
- „NAŠE CENA“ (bez běžné ceny): všechna slova názvu (aspoň dvě), první slovo je značka produktu, běžná cena vyšší
  než akční a všechny kandidáti se stejnou běžnou cenou (Bohemia Sekt za 179,90 i 279,90 = dvě řady → nespárovat).
- Dlaždice „více druhů“ / „N druhy“ dostane všechny kandidáty (každý druh je v API samostatné SKU); bez druhů jen
  jediného (nebo víc SKU se stejným názvem — katalog má zboží dvakrát).
- Akce dostane z katalogu název, značku, balení, kategorii (filtr krmiv R50), obrázek a odkaz; ceny, typ a platnost
  z letáku. **ID:** platí-li akce přesně akční týden st–út, je to SKU — stejný klíč, jaký dá API po začátku, upsert řádek
  jen aktualizuje (`created_at` zůstane, souhrn ani centrum upozornění ho neohlásí podruhé, R74, R76). Jiná platnost
  (oddíl „SUPER STŘEDA“, víkend, katalog na tři týdny) = předběžné ID `letak-{SKU}` a akce z API nese
  `supersedes` = `letak-{SKU}` → `ImportChainOffers::adoptProvisional` (R88) řádek převezme, jakmile se platnosti překryjí.
  Akce, která začne až uprostřed týdne (víkend), ve středu v API ještě není — R16 ji ve středu označí jako staženou
  a ve čtvrtek ji převzetí vrátí (bez nového upozornění).
- **Pokračující akce (R54):** má-li produkt dnes v API akci se stejnou cenou (i s Klubem), dlaždice se nezaloží —
  ve středu dnešní řádek prodlouží API (`continuePrevious`). `continuePrevious` neprodlužuje akce, které ještě nezačaly.
- Nespárovaná dlaždice se **neuloží**: ID, které by API později převzalo, z ní nejde odvodit (název v letáku se
  od katalogu liší) — po začátku by se ohlásila podruhé jako nová.

**Výsledek 6. 10. 2026:**

| Leták | Stran | Velkých cen | Ověřeno | Spárováno | Akcí uloženo |
|---|---|---|---|---|---|
| velký od 7. 10. (budoucí) | 41 | 382 | 255 dvojic → 243 dlaždic | 123 dlaždic → 238 produktů | 189 (8 s předběžným ID) |
| velký od 30. 9. (proti API týž den) | 41 | — | 163 dlaždic | 81 → 170 produktů | — |

- Nevzaté velké ceny letáku od 7. 10.: bez ceny za jednotku 84 (zelenina, maso, pult), cena za jednotku nesedí nebo patří
  jiné dlaždici 39, bez dlaždice v okolí 4; z 255 ověřených dvojic ubyly opakované dlaždice a vyřazené (sleva nesedí, kupóny, „KUPTE 3 ZAPLAŤTE 2“ bez ceny kusu)
  a nejistá platnost. Nespárováno 120 dlaždic: 90 „NAŠE CENA“ (slova názvu nebo značka nesedí, víc řad), 30 s běžnou
  cenou (jiný název v katalogu — „Salko“ ↔ „Tatra Salko Tradiční“, změna běžné ceny — Kinder Maxi King 69,90 v letáku,
  71,90 v API, víc kandidátů).
- **Přesnost proti API** (leták od 30. 9. v jeho poslední den, 170 dvojic dlaždice–produkt): 156 stejná cena (102 s běžnou
  cenou i se stejným typem, 54 „NAŠE CENA“ — API je vrací jako slevu s `crossed`), 2 nesoulady (Actimel 12 × 100 g: leták
  89,90, API 99,90, stejná přeškrtnutá 136,90), 12 produktů dnes bez akce (druhy, na které akce neplatí nebo skončila:
  Kofola Bez cukru, Activia meruňka, Coca-Cola a Red Bull z víkendu).
- Ruční kontrola 15 náhodných uložených akcí proti textu strany (ceny, cena za jednotku, procento, platnost): vše správně;
  „Coca-cola 1,5 l více druhů“ se spárovala i s Fantou Zero (značka Coca-Cola v katalogu).
- Stažení Billy 6. 10. s letákem od 7. 10.: 3 596 akcí (3 407 z API, 189 z PDF), ~60 s (dřív ~47 s): 25 stránek katalogu,
  stránka letáků, stránka letáku a PDF 50 MB (~4 s) a pdftotext (~3 s). V úterý se 4 budoucími letáky odhad ~90 s.

### Pole a pasti
- Velký leták (36 stran) pro větší prodejny, malý (8 stran) pro menší; API má jednu celostátní cenu.
- Každá varianta je v API samostatné SKU — „různé druhy“ (R9) je jen v letáku. „NAŠE CENA“ v letáku ≈ „Super cena“,
  API u ní ale dává `crossed`.
- `lowestPrice` = nejnižší cena za 30 dní (neukládá se, jen v `raw`).

## Globus

**Cesta:** veřejné REST API webu (Nuxt 3), bez klíče a bez WAF — implementováno (R46,
`app/Domain/Sources/Globus`), bez LLM; API má jen akce, které už platí, **budoucí letáky z PDF** (`pdftotext`, R88). Náročnost nízká až střední. `robots.txt` povoluje `/` včetně `/api/`; zakazuje jen
detaily produktů `…/p/` a podstránky akční nabídky jednotlivých hypermarketů (API je nepotřebuje).

### Endpointy (`B = https://www.globus.cz/api/v1/gsoa/actionOffers`)
| Účel | Požadavek |
|---|---|
| **Akce s cenou v prodejně (hlavní zdroj)** | `GET {B}/houses/4005/actionProductsCatalog?page=0&pageSize=200` — 913 položek, 5 požadavků |
| Položky letáku (popis „různé druhy“, název pro ID z letáku) | `GET {B}/houses/4005/actionProducts?page=0&pageSize=200` — 1 025 položek, 6 požadavků |
| Letáky a katalogy (PDF, JPG, platnost) | `GET {B}/houses/4005/actionOffers?page=0&pageSize=50` — ~25 letáků, 1 požadavek (R88) |
| PDF letáku | `storeDocuments[0].pdfAsset` = `https://gapi.globus.cz/OnlineAsset/3/asset?assetID={uuid}` — ~38 MB, ~4 s |
| Detail letáku — nepoužívá se | `GET {B}/houses/4005/actionOffers/{actionOfferId}` (produkty nemá), `…/actionOffers/slug/{slug}` |

`page` od 0, `pageSize` nejvýš 200. Katalog: **`totalCount` nesedí** (869 vs. 913) — stránkuje se, dokud
`paginationShowMore` je `true`. Položky letáku `paginationShowMore` nemají — stránkuje se do kratší stránky. Celé
stažení ~11 požadavků, ~23 s; s PDF budoucího letáku 13 požadavků, ~26 s. 16 hypermarketů (`gsoaId` v `__NUXT_DATA__`), stahuje se 4005 Čakovice
(`letaky.sources.globus.house_id`); mezi prodejnami se liší jen krátké místní akce (Brno × Čakovice: 900 z 912
stejně).

### Převod (`GlobusParser`)
- `productInHouse.actualPrice` / `originalPrice` / `discountPercentage` jsou **float v Kč**. S původní cenou
  (`priceTagId` 11) = sleva, bez ní (`priceTagId` 12) = akční cena (R8).
- `bonusProgramPrice.actualPrice` = cena s aplikací **Můj Globus** (`LoyaltyProgram::MujGlobus`) — jen když je nižší
  než běžná (u Milko Tolštejn je stejná). Akce bez původní ceny s cenou v aplikaci zůstává akční cenou, ne „jen
  s kartou“: `priceTagId` 12 značí akční cenovku, nevíme, že je běžná.
- `priceValidFrom`/`To` mají místní posun (`2026-10-06T23:59:59.000+02:00`) → místní datum.
- **Jen `priceType` VKA0** (`action_price_types`). VKP0 jsou ceny pultu a spotřebičů s platností do `9999-12-31`,
  ZTP0 doprodej — nejsou to akce z letáku.
- **Vyřazené skupiny zboží** (`excluded_ware_groups`, první 3 znaky `warengroup`): oblečení, obuv, bytový textil,
  kabelky (~210 akcí z módního katalogu). Potraviny, drogerie, krmiva a domácí potřeby zůstávají. `productCategories`
  a `placements` k filtrování nejdou — třetina položek je má prázdné.
- Značka `commonBrand.name` (`brand` je null nebo nesmysl), id `vanr`, obrázek `imgThumbnail`, kategorie
  `placements[0].category` (bez diakritiky, často chybí).
- **Balení** `sellUnitSizeText`; zboží na váhu ho nemá (null nebo prázdné) — pak `unitAmount` + `unitId` (`1 kg`,
  cena je za kg). `unitId` `KS` s `unitAmount` 97 u čokolády 97 g je nesmysl, proto jen pro g/kg/ml/l. „40 dávek“,
  „111 praní“ balení nedají (cena za jednotku chybí).
- **Popis z letáku:** `description` katalogu je dlouhý reklamní text (Milka: „…z alpského mléka“) — hlídání „mléko“
  by ho chytalo. Místo něj se bere krátký `description` položky letáku se stejným EAN („různé druhy“,
  „- dámská\n- různé barvy“ → „dámská, různé barvy“), z něj `VariantNote`. Spárovalo se všech 913 položek katalogu.
  Katalog má na „různé druhy“ jen jednu zástupnou variantu (Milka Bubbly kokosová), proto na popisu záleží.
- Akce mají různou platnost (týden, 14 dní, měsíc, katalogy do prosince) — vše je v jednom průběžném zdroji
  `akcni-nabidka` (`LeafletKind::Web`, bez platnosti). Odkaz zdroje: `https://www.globus.cz/globus/hypermarket/akcni-nabidka`.
  Akce vlastní odkaz nemá — adresa detailu `…/p/{slug}-{vanr}` není ověřená a API slug nevrací.

### Pole a pasti
- `actionProducts` má `originalPrice: 0` místo null a nemá balení — cena se bere z katalogu.
- **Lahůdky:** leták cena za 100 g, katalog za kg (Eidam 11,9 × 119) — katalog je jednotný.
- **Krátké místní akce** jsou jen v katalogu (jogurt 4,90 na 1.–3. 10. vs. leták 7,90) — proto denní stahování;
  klíč nabídky `vanr` + platnost.
- `raw` neukládá `description`, `contains`, `allergens`, `nutritionValues`, `storage` a `regulatedName` (dlouhé texty).
- Leták na příští týden je v `actionOffers` dřív než jeho produkty v API — `actionProducts` ani katalog **budoucí
  položky nevrací** (6. 10. 2026: 805 položek, žádná od 7. 10.; parametry `filter`, `actionOfferId`, `date` API ignoruje,
  detail letáku produkty nemá). Budoucí akce proto z PDF (níže).
- Katalog má u akcí letáku často začátek o den dřív než leták (`priceValidFrom` 29. 9. u letáku od 30. 9.).

### PDF budoucích letáků (R88)
Implementace: `GlobusLeafletParser` nad `PdfTextReader` (`pdftotext -bbox-layout`), výběr letáků a zapojení
v `GlobusOfferSource`. Každý leták je vlastní zdroj (`kind` leaflet, ID `actionOfferId`, název `actionOfferName`).

**Výběr letáků** (`actionOffers`): jen ty, které ještě nezačaly (`validFrom` po místním dnešku). `offerType`:
- `mainFlyer` — hlavní leták (`41_26_L1`, 47 stran, `isComplete` true);
- `theme` — část hlavního letáku (`41_26_L1-2` … `-5`, ID `<hlavní>-N`, 2–15 jeho stran, `isComplete` false);
  stáhne se jen bez svého hlavního letáku v seznamu, jinak by šlo o stejné strany;
- `catalogue` — jen s názvem podle `pdf_catalogue_pattern` (`K2_Nápoje`, `K2_Úklid`, `K5_…_PET_…`; ne
  `K2_Elektro`, `K3_Textil` — R46, elektro nemá cenu za jednotku). Rozvržení katalogů neověřené — 6. 10. žádný
  budoucí potravinový katalog nebyl.

**Rozvržení** (strana 666 × 893 b.):
- název ~12,6 b. na 1–4 řádcích, privátní značka „VÁŠ VÝBĚR“ na řádku nad ním (patří do názvu, `actionProducts`
  ho má také: „VÁŠ VÝBĚR Rozinky“);
- popis ~9,6 b. („plnotučné 3,5%“, „různé druhy“, balení „1 l“, „150 g“, „3× 56 g“, „p. p. 156-240 g“);
- cena za jednotku ~7,2 b. o ~3 b. odsazená, **bez „Kč“**: „100 g = 6,63“, „1 l = 57,-“, „1 dávka = 4,80“,
  „100 g od 14,94“ (víc velikostí balení — počítá se s největším);
- cenovka nad názvem: sleva „-17 %“ a přeškrtnutá cena „27“ „90“ (~21 b., haléře ~10 b.), velká cena „22“ (~42 b.)
  a haléře „90“ (~16 b.) jako samostatná slova; celé koruny „239“ „,-“ nebo „213,-“;
- **Můj Globus:** velká cena je cena s kartou písmem se stínem — pdftotext vrátí každé slovo dvakrát (posun ~6 b.
  vpravo, ~2 b. svisle) a pootočené číslice jako samostatná slova („1“ „1“ „4“ „4“ „5“ „5“ = 145). Cena za jednotku
  je dvojí „AC: 100 g = 6,60 KC: 100 g = 5,93“ (AC = akční cena, KC = cena s kartou, vysvětlivka v patičce).
  Běžná akční cena leží pod cenou s kartou menším písmem (~30 b., „-44 %“ a přeškrtnutá „17“ „90“ ~16 b.) nebo stejně
  velká (Persil „429“ nad „479“);
- platnost v hlavičce strany: „Platí od 7. 10. do 2. 11. 2026.“, „platnost 7. 10. – 20. 10. 2026“, „PLATNOST STRANY“ /
  „7. 10. – 20. 10.“, strany „DELŠÍ PLATNOST“ / „od 30. 9. do 26. 10. 2026“ (akce minulého letáku, které už platí a nese
  je API); jinak platnost letáku.

**Pravidla** (`GlobusLeafletParser`):
- Cena se přijme, jen když ji ověří balení × cena za jednotku (tolerance R26); u karty „KC“ cenu s kartou
  (`loyalty_price`, `LoyaltyProgram::MujGlobus`) a „AC“ běžnou akční cenu. Ze sedících dvojic cena–dlaždice vyhrávají nejbližší.
- „-N %“ musí sedět na přeškrtnutou cenu (Globus uřezává: 27,90 → 22,90 = 17,9 % → „-17 %“), u karty obě slevy;
  sleva bez přeškrtnuté ceny dlaždici vyřadí. Bez štítku je to akční cena (R8), ne sleva.
- Neověřitelné (vynechají se): maso, ovoce a zelenina „1 kg“ / „1 ks“, pult a lahůdky za „100 g“, mléko „1 l“ bez ceny
  za jednotku, restaurace, elektro, textil, varianty s více cenami (kaiserka 1 / 3 / 5 ks).
- ID = otisk názvu v letáku a běžné ceny (`GlobusLeafletKey`, bez diakritiky, velikosti písmen a interpunkce).
  Odkaz akce i zdroje je stránka akční nabídky (PDF má 38 MB).
- Uloží se jen akce se začátkem po dnešku; akci, kterou API už vrací (stejný otisk, překrývající se platnost), PDF nezdvojí.
- Hlavní leták s méně než `pdf_main_min_offers` (50) ověřenými akcemi a chyba stažení nebo převodu PDF (`PdfTextFailed`)
  ukončí stažení celého Globusu — jinak by akce z letáku byly „stažené“ (R16).

**Převzetí akcí z API:** až leták začne, API vrátí akci pod vlastním ID (`vanr`) a s platností katalogu. Akce z API nese
v `OfferData::$supersedes` otisk z názvu spárované položky `actionProducts` a ceny; `ImportChainOffers::adoptProvisional`
řádek z PDF s tímto ID a překrývající se platností přejmenuje na ID z API a převezme platnost. Řádek si nechá `created_at`
(souhrn, centrum upozornění ani „Od dneška platí“ ho neohlásí jako novou akci, R74, R76), přiřazení ke katalogu i ID
v uložených upozorněních. Nepřevzatý řádek z PDF stáhne R16.

**Výsledek 6. 10. 2026:**

| Leták | Stran | Velkých cen | Ověřeno | Z toho budoucích | Stejný otisk v API |
|---|---|---|---|---|---|
| od 7. 10. (41_26_L1) | 47 | 381 | 195 (20 s kartou, 106 se slevou) | 143 | — (API budoucí nemá) |
| od 30. 9. (40_26_L1, měřeno v jeho poslední den) | 51 | 420 | 244 (24 s kartou) | — | 210 |

- Nevzaté velké ceny letáku od 7. 10.: bez ceny za jednotku 164, cena za jednotku nesedí 4 (víc velikostí s pevným
  podílem, varianty), bez dlaždice v okolí 4; procento slevy nesedí 0. Ruční kontrola 15 náhodných akcí proti textu
  strany: vše správně.
- Leták od 30. 9. proti API týž den: u 210 akcí se stejným otiskem cena s kartou 23/23 a původní cena 125/126 stejná
  (Lenor: PDF „299 90“ „-16 %“ → 249,90, API 279,90); u žádné dvojice podobného názvu se cena neliší. 13 akcí má
  v `actionProducts` kratší název („VÁŠ VÝBĚR“, „Fine Dog“, „Tento“) — otisk se liší a akce se po začátku ohlásí znovu;
  21 v katalogu prodejny 4005 chybí (hlavně krmiva ze stran 15–18).
- Stažení Globusu 6. 10. s letákem od 7. 10.: 797 akcí (656 z API, 141 z PDF), ~26 s.

## Makro (průzkum 2026-10-02 — zatím bez zdroje)

Požadavek uživatele přidat Makro. Výsledek: **zdroj, který by šel použít v souladu s pravidly projektu, zatím není.**

- `www.makro.cz` je za ochranou proti robotům: běžný HTTP požadavek (i s User-Agentem prohlížeče)
  vrací **403** se stránkou „ARE YOU LOST?“ — **včetně `robots.txt`**. Ochranu neobcházíme
  (stejně jako marketplace Kauflandu), takže ani nejde zjistit, co robots.txt dovoluje.
- Letáky Makra jinak nabízejí jen agregátory (mojeletaky.cz, kompasslev.cz, kaufino.com,
  najdislevu.cz) — ty jako zdroj nepoužíváme ([R1](PLAN.md#8-log-rozhodnutí)).
- Makro je velkoobchod: nákup jen s kartou zákazníka (podnikatelé, ale i karta pro domácnosti)
  a ceny se uvádějí **bez DPH i s DPH** — pro porovnání s ostatními obchody by se brala cena s DPH.
- Možná cesta: požádat Makro o přístup (oficiální feed letáků nebo povolení stahování), nebo
  počkat, jestli leták nezveřejní na platformě bez ochrany (jako Albert na Publitas).

---

## Výsledky testovacích scénářů (leták 30. 9.–6. 10. 2026)

Slouží jako referenční data pro první testy párování.

| | Coca-Cola Zero | Vejce | Polotučné mléko |
|---|---|---|---|
| Kaufland | možná: „Coca-Cola/Fanta/Sprite různé druhy“ 1,5 l 31,90 (`smallPrice`) | M 20 ks 59,90 (z 99,90) | Kunín trvanlivé 1,5 % 1 l 8,90 (`specialItems`) |
| Tesco | 1,5 l „Super cena“ 32,90 (není sleva); e-shop s Clubcard: 0,5 l 24,90, 4×330 ml 69,90 | M 10 ks 29,90 (z 59,90) | UHT 1,5 % 8,90 s Clubcard, 2.–4. 10. |
| Albert | možná: multipacky „vybrané druhy“ | L 10 ks 39,90 (z 64,90) | trvanlivé 1,5 % 8,90 s aplikací / 10,90 bez, 2.–4. 10.; čerstvé Madeta 24,90 (SM), Olma 28,90 (HM) |
| Lidl | ne; příští týden 1,75 l 29,90 (z 34,90), 8.–11. 10., rozšířená nabídka | ne; příští týden M 30 ks 89,90 | ne |
| Penny | ne (jen Pepsi Zero) | M 10 ks 24,90 (z 49,90) | Madeta trvanlivé 1,5 % 9,90, 2.–4. 10.; bez laktózy 17,90 |
