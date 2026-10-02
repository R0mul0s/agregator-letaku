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
| Položky platné v prodejně | `GET https://prodejny.kaufland.cz/.kloffers.storeName=CZ3300.json` (jen `dateFrom`, `dateTo`, `klNr`) |
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

### Pole a pasti
- Platnost `dateFrom` / `dateTo` je u kategorie i u položky. Kampaně (Víkend, Start týdne) mají kratší platnost.
- `label` určuje typ akce. Skutečná sleva je `reducedPrice` a `halfPrice`. **`smallPrice` („AKCE! pouze“) a `specialItems` nemají původní cenu**, často jde o trvale nízkou cenu ([R8](PLAN.md#8-log-rozhodnutí)).
- Kaufland Card: `customerType == "KDN"` a pole `loyalty*`.
- `basePrice` je text („(=1 l 21,27)“) a musí se parsovat.
- **Stejná položka je ve více kategoriích** (sortimentní a „Superkauf“), deduplikovat podle `klNr` a platnosti.
- Názvy jsou často souhrnné: „Coca-Cola/Fanta/Sprite různé druhy“.
- URL obrázku obsahuje EAN (`8594061460122_CZ_P`).
- Příští týden se zveřejňuje 3 dny před začátkem platnosti. Týden začíná ve středu, takže v neděli **(předpoklad z textu webu)**.
- robots.txt zakazuje jen detaily (`/nabidka/*/detail`), `/nabidka/prehled.html` je povolená.

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
Klíč je veřejný a je vložený v HTML e-shopu jako `mangoApiKey`. Patří do konfigurace,
ne do kódu. Při chybě 401 ho znovu načíst z HTML (tam už jsou potřeba browser hlavičky).

Výpis všech akcí (stránkování po 200, ~5 100 produktů, 26 požadavků):
```json
[{"query":"query P($promotion:String,$page:Int,$count:Int){promotions:promotionType(type:$promotion,page:$page,count:$count){info{total} products{id title brandName superDepartmentName departmentName defaultImageUrl price{actual unitPrice unitOfMeasure} promotions{id startDate endDate description unitSellingInfo price{beforeDiscount afterDiscount} attributes}}}}",
  "variables":{"promotion":"all","page":1,"count":200}}]
```
Vyhledávání: `search(query:, page:, count:)` se stejnými poli.

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
- Časy jsou v **UTC**: `2026-09-29T22:00Z` = 30. 9. místního času ([R7](PLAN.md#8-log-rozhodnutí)).
- **Cena s Clubcard je jen v textu `description`** („8,90 Kč … s Clubcard“). U Clubcard akcí je `afterDiscount` **běžná cena**. Parsovat regexem a ověřit testem.
- Běžná akce: `beforeDiscount` → `afterDiscount`, procento jen v textu („-50%, předtím 59,90 Kč“).
- Gramáž je jen v `title`.
- Nabídky v prodejnách a v e-shopu se liší ([R4](PLAN.md#8-log-rozhodnutí)). „Super cena / cena pro všechny“ (Coca-Cola 1,5 l za 32,90) e-shop jako akci neuvádí, protože se rovná běžné ceně.
- Leták na příští týden se zveřejňuje zřejmě v pondělí před středou platnosti **(předpoklad)**.
- Podmínky webu zakazují užití obsahu pro jinou než osobní potřebu ([O6](PLAN.md#7-otevřené-otázky)).

---

## Lidl

**Cesta:** JSON z kampaňových stránek webu (~1/3 letáku) a k tomu leták (PDF s textovou vrstvou → LLM). Náročnost střední.

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

### Pole a pasti
- Potraviny na příští týden jsou často **jen v letáku**, web je zatím nemá.
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
- **Vlastní glyfy ve fontu cen:** `Ǻ` = „,90“ (`24Ǻ` = 24,90), malý glyf z Private Use Area za čárkou = „90“. Odvozeno shodou s API, nedokumentováno a mezi vydáními se může změnit. Validovat (sleva % musí odpovídat poměru cen).
- PDF (`…/files/assets/common/downloads/{DD_MM_YYYY}.pdf`) má kvůli fontu rozbité ceny, nepoužívat.
- Obrázky stran jsou jen pozadí bez textu.

### Pole a pasti
- API pokrývá jen malou část letáku: polotučné mléko v akci bylo **jen v letáku**.
- Víkendové akce mají platnost jen v textu stránky („platí od pátku 2. 10. do neděle 4. 10.“).
- „Jedinečná nabídka“ bez přeškrtnuté ceny není sleva. `lowestPrice` = nejnižší cena za 30 dní.
- **Starý leták zmizí** (404) a API drží jen aktuální týden, takže archivovat ([R10](PLAN.md#8-log-rozhodnutí)).
- Nový leták se objeví zřejmě v úterý nebo ve středu **(předpoklad)**.

---

## Albert

**Cesta:** jen leták. Metadata z GraphQL, obrázky stránek z Publitas a extrakce přes vision LLM. Náročnost střední.

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
| Obrázky stránek | `https://letaky.albert.cz/{slug}/spreads.json` (až 1816×3173, varianty `at1600`, `at2000`, `at2400`) |
| Hotspoty | `https://letaky.albert.cz/{slug}/page/{n}/hotspots_data.json` (jen pár externích odkazů, **žádné produkty**) |

### Pole a pasti
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

## Výsledky testovacích scénářů (leták 30. 9.–6. 10. 2026)

Slouží jako referenční data pro první testy párování.

| | Coca-Cola Zero | Vejce | Polotučné mléko |
|---|---|---|---|
| Kaufland | možná: „Coca-Cola/Fanta/Sprite různé druhy“ 1,5 l 31,90 (`smallPrice`) | M 20 ks 59,90 (z 99,90) | Kunín trvanlivé 1,5 % 1 l 8,90 (`specialItems`) |
| Tesco | 1,5 l „Super cena“ 32,90 (není sleva); e-shop s Clubcard: 0,5 l 24,90, 4×330 ml 69,90 | M 10 ks 29,90 (z 59,90) | UHT 1,5 % 8,90 s Clubcard, 2.–4. 10. |
| Albert | možná: multipacky „vybrané druhy“ | L 10 ks 39,90 (z 64,90) | trvanlivé 1,5 % 8,90 s aplikací / 10,90 bez, 2.–4. 10.; čerstvé Madeta 24,90 (SM), Olma 28,90 (HM) |
| Lidl | ne; příští týden 1,75 l 29,90 (z 34,90), 8.–11. 10., rozšířená nabídka | ne; příští týden M 30 ks 89,90 | ne |
| Penny | ne (jen Pepsi Zero) | M 10 ks 24,90 (z 49,90) | Madeta trvanlivé 1,5 % 9,90, 2.–4. 10.; bez laktózy 17,90 |
