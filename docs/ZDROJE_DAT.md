<!--
  Zdroje dat jednotlivých obchodů — výsledek technického průzkumu
  @author Roman Hlaváček
  @created 2026-10-02
-->

# Zdroje dat obchodů

Výsledek technického průzkumu z **2. 10. 2026** (leták 30. 9.–6. 10. 2026). Všechno
je ověřené skutečnými požadavky (curl). Co je jen předpoklad, je označené
**(předpoklad)**.

Jde o **neveřejná a nedokumentovaná rozhraní**, která se můžou kdykoli změnit.
Když scraper přestane fungovat, začni tady a porovnej s aktuální odpovědí. Po
změně tento dokument aktualizuj ve stejném commitu jako kód.

Společné pro všechny obchody:
- Headless prohlížeč, captcha ani obcházení WAF nejsou potřeba.
- Stahovat šetrně: 1–2× denně, pauza mezi požadavky, identifikovatelný User-Agent ([R5](PLAN.md#8-log-rozhodnutí)).
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
- Seznam prodejny má jen `klNr` a platnost; detail (název, cena) je jen na stránce nabídky s cookie `x-aem-variant={kód}` (~2,5 MB). Výchozí stránka (bez cookie) je CZ3300 Praha-Vypich. Na detail všech akcí všech prodejen stačilo **24 stránek** navíc (výběr: vždy prodejna s nejvíc chybějícími akcemi).
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
- **Akce bez `price`** (95 položek): „3 za cenu 2“, „MENU BAGETY“, „PECIVO+NAPOJ“, „2 za 799 Kč“. Cena produktu je jen `price.actual`.
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

**Cesta:** JSON z kampaňových stránek webu (~1/3 letáku) a text stránek letáku z API letáků pro zmínky bez ceny (R27); ceny ze zbytku letáku jen přes LLM. Náročnost střední.

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
- PDF (25–34 MB) má použitelnou textovou vrstvu (`pdftotext -layout`), ale sloupce se míchají. Přiřazení ceny a Lidl Plus k produktu proto spolehlivě zvládne až LLM, případně vision nad obrázkem stránky.
- Týdně jsou zhruba 4 potravinové letáky × 50–60 stran.
- **Vyhledávání v prohlížeči letáku** (`…/view/search/page/1`) prohledává jen `keyWords` stránek — žádný seznam produktů s cenami v JS není.
- **Zmínky bez ceny (R27, implementováno):** `LidlOfferSource::leafletPages` vezme ze stránky letáků slugy s předponou `akcni-letak-od-` (bez „spotrebni-zbozi“, „hity-tydne“, „…cen-v-klidu…“), pro každý stáhne detail a uloží stránky do `leaflet_pages`: `keyWords` + `altText` (popis stránky větou), náhled `thumbnail` (400 px), odkaz `/l/cs/letak/{slug}/view/flyer/page/{n}`. Leták je zdroj `kind = leaflet` bez nabídek. Název = `name` + `title` („Akční leták OD ČTVRTKA 8. 10. - 11. 10. 2026“), platnost `offerStartDate` / `offerEndDate` (místní data).
- **Pasti `keyWords`:** slova bez pořadí, s velkými počátečními písmeny, čísla bez čárky („05“ = 0,5 l, „2997“ = 29,97); stránky s receptem vyjmenovávají suroviny („Vejce“, „Máslo“ — 8. 10. str. 18 a 49) a poznají se podle „Postup přípravy“, „Nákupní seznam“ nebo `altText` „Recept na…“. Coca-Cola bez „Zero“ na str. 28 je jen „možná“.

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
- URL letáku: `https://files.rewe.co.at/PennyIntLeaflet/CZ/{DD_MM_YYYY}/`. Odkaz se dá vyčíst ze stránky `https://www.penny.cz/nabidky/letaky` (hledat `PennyIntLeaflet/CZ/`).
- **Nejlepší zdroj je vektorová vrstva stránek:** `…/files/assets/common/page-vectorlayers/0001.svg` až `00NN.svg`. Obsahuje `<svg:text transform="matrix(a b c d e f)">` s `<svg:tspan x="…" y="…" fill="…">`, tedy každý token se souřadnicemi, velikostí a barvou. Název, gramáž, cena, přeškrtnutá cena a % jdou spárovat podle pozice, nebo se tokeny s pozicemi předají LLM (levnější než vision).
- **Vlastní glyfy ve fontu cen** (odvozeno shodou s API 2. 10. 2026, nedokumentováno):
  - velká cena „24Ǻ“: `Ǻ` (U+01FA) = „,90“ — 560 výskytů, jiné haléře se v letáku neobjevily (výjimečně `Ƿ`, `ɏ` — přeskočí se)
  - malá cena „49“ „,“+U+E00A+U+E009 = „49,90“; U+E00A = „9“, U+E009 = „0“
  - červený glyf U+E010 / U+E011 / U+E00F za malou cenou je **přeškrtávací čára** pro 1- / 2- / 3místnou cenu, ne číslice — podle ní se pozná přeškrtnutá (původní) cena
- Index letáku (`…/{DD_MM_YYYY}/`) odkazuje na stránky `href="./2/"` … `./37/` → počet stran. **Některé strany vektorovou vrstvu nemají** (2. 10.: strany 9 a 33, jen obrázek) → 404, přeskočí se.
- PDF (`…/files/assets/common/downloads/{DD_MM_YYYY}.pdf`) má kvůli fontu rozbité ceny, nepoužívat.
- Obrázky stran jsou jen pozadí bez textu.

### Parser letáku (bez LLM, R23)
Implementace: `app/Domain/Sources/Penny/PennyLeafletParser.php`, podrobný postup v jeho popisu.

- Rozvržení dlaždic se liší stránku od stránky (název nad cenou, vedle ní, bílý na barevném pruhu), pevné okno kolem ceny nefunguje.
- **Přiřazení ceny k dlaždici je ověřené cenou za jednotku**, kterou leták u potravin uvádí: „250 g“ + „100 g 5,16 Kč“ sedí jen k 12,90 Kč. Sousední dlaždice se tak nespletou. Blok textu bez ceny za jednotku se přijme jen u balení 1 kg / 1 l / 1 ks přímo nad cenou.
- Výsledek 2. 10. 2026: **~300 ověřených akcí z ~560 cen** (35 stran). Zbytek (hlavně dlaždice bez ceny za jednotku, s PENNY kartou, kombinace) se neuloží — raději chybějící akce než špatná cena. Dlaždice s PENNY kartou nese API.
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

**Cesta:** jen leták. Metadata z GraphQL, **text stránek z Publitas** (`spreads.json`, R36) — zatím zmínky bez ceny; ceny případně z textu nebo vision LLM. Náročnost střední.

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
| Metadata | `https://letaky.albert.cz/{slug}/data.json` |
| Stránky | `https://letaky.albert.cz/{slug}/spreads.json` — pole dvoustran, u každé stránky `number`, **`text`** (text stránky v pořadí čtení) a `images` (`at200` 151×263 … `at2400` 1818×3169, cesty relativní k `https://letaky.albert.cz`) |
| Stránka v prohlížeči | `https://letaky.albert.cz/{slug}/page/{n}` |
| Hotspoty | `https://letaky.albert.cz/{slug}/page/{n}/hotspots_data.json` (jen pár externích odkazů, **žádné produkty**) |

### Pole a pasti
- **Text stránek (R36, implementováno):** `AlbertOfferSource` stáhne hlavní letáky HM a SM (`isDefault`, lokální varianty ne) a text stránek uloží pro zmínky bez ceny. Text obsahuje názvy, balení i ceny, ale ceny jsou rozsekané („-34 %“ „31“ „90“, „3490“ = 34,90, „48,90/“ = původní cena) a pořadí bloků neodpovídá dlaždicím; titulní strana opakuje obsah další strany a některé strany jsou v letáku dvakrát.
- Platnost je v UTC a ve formátu `DD/MM/YYYY HH:MM:SS` (`22:00` = půlnoc místního času).
- **Hypermarket a supermarket mají odlišné letáky** (`40hm_akcni_letak`, `40sm_akcni_letak`). Existují i lokální varianty (`40sm_akcni_letak_frenstat`, `isDefault=false`) a výjimky prodejen.
- **Dvojí cena:** s aplikací Můj Albert (modrá cenovka) a „BEZ APLIKACE xx,xx“.
- Platnost bloků uvnitř letáku: „PLATÍ POUZE PÁ–NE 2.–4. 10.“, „do 27. 10.“.
- Další pole jen v obsahu stránky: „BĚŽNÁ CENA“, „▼ xx Kč“ (nejnižší cena za 30 dní), limity („MAXIMÁLNĚ 25 ks/den“), štítky „NEPORAZITELNÉ“ a „vybrané druhy“.
- Textová vrstva PDF existuje, ale ceny ztrácejí desetinnou čárku (haléře v horním indexu: „9490“ = 94,90) a sloupce jsou rozházené. **Spolehlivý je vision LLM nad obrázkem stránky**, text PDF slouží jako kontrola.
- Slug dalšího týdne je `{týden}{hm|sm}_akcni_letak` a dá se zkoušet dopředu **(předpoklad)**.
- Doména má Akamai Bot Manager, ale požadavky (GraphQL, Publitas, obrázky) prošly i s výchozím UA. Při vyšší frekvenci může blokovat **(předpoklad)**.
- VOP zakazují stahování obsahu e-shopu a aplikace, na letáky nemíří výslovně ([O6](PLAN.md#7-otevřené-otázky)).

---

## Billa

**Cesta:** product-discovery API webu (stejná platforma REWE jako Penny, Nuxt a commercetools) s **celým katalogem**,
bez LLM — implementováno (R48, `app/Domain/Sources/Billa`). Náročnost nízká až střední (bez platnosti akcí).
`robots.txt` jen odkazuje na sitemap (žádné Disallow), bez WAF, cookies ani zvláštních hlaviček.

### Endpointy
| Účel | Požadavek |
|---|---|
| **Celý katalog (hlavní zdroj)** | `GET https://www.billa.cz/api/product-discovery/products?page={n}&pageSize=500` — 12 184 produktů, 25 požadavků po ~1,2 MB, ~2 s každý |
| Jen akce (nepoužívá se) | totéž s `&inPromotion=true` — 3 042 položek, ale **bez akcí jen s BILLA Klubem** |
| Detail (nepoužívá se) | `GET /api/product-discovery/products/{sku}` (`82-100073`; se slugem 404) — platnost akce také nemá |
| Leták (Publitas, jako Albert R36) — zatím ne | `https://view.publitas.com/billa-cz/{slug}/spreads.json` (`pages[].text`) |

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

### Pole a pasti
- Velký leták (36 stran) pro větší prodejny, malý (8 stran) pro menší; API má jednu celostátní cenu.
- Každá varianta je v API samostatné SKU — „různé druhy“ (R9) je jen v letáku. „NAŠE CENA“ v letáku ≈ „Super cena“,
  API u ní ale dává `crossed`.
- `lowestPrice` = nejnižší cena za 30 dní (neukládá se, jen v `raw`).

## Globus

**Cesta:** veřejné REST API webu (Nuxt 3), bez klíče a bez WAF — implementováno (R46,
`app/Domain/Sources/Globus`), bez LLM. Náročnost nízká. `robots.txt` povoluje `/` včetně `/api/`; zakazuje jen
detaily produktů `…/p/` a podstránky akční nabídky jednotlivých hypermarketů (API je nepotřebuje).

### Endpointy (`B = https://www.globus.cz/api/v1/gsoa/actionOffers`)
| Účel | Požadavek |
|---|---|
| **Akce s cenou v prodejně (hlavní zdroj)** | `GET {B}/houses/4005/actionProductsCatalog?page=0&pageSize=200` — 913 položek, 5 požadavků |
| Položky letáku (popis „různé druhy“) | `GET {B}/houses/4005/actionProducts?page=0&pageSize=200` — 1 025 položek, 6 požadavků |
| Letáky a katalogy (PDF, JPG, platnost) — nepoužívá se | `GET {B}/houses/4005/actionOffers?page=0&pageSize=50` |

`page` od 0, `pageSize` nejvýš 200. Katalog: **`totalCount` nesedí** (869 vs. 913) — stránkuje se, dokud
`paginationShowMore` je `true`. Položky letáku `paginationShowMore` nemají — stránkuje se do kratší stránky. Celé
stažení ~11 požadavků, ~23 s. 16 hypermarketů (`gsoaId` v `__NUXT_DATA__`), stahuje se 4005 Čakovice
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
- Leták na příští týden je v `actionOffers` dřív než jeho produkty v API.

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
