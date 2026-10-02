<!--
  Fixtures — uložené odpovědi obchodů pro testy
  @author Roman Hlaváček
  @created 2026-10-02
-->

# Fixtures

Zkrácené **skutečné odpovědi** obchodů (R11 v [docs/PLAN.md](../../docs/PLAN.md)). Testy
nikdy nesahají na síť, odpovědi podávají přes `Http::fake()`. Datum v názvu souboru je
den stažení. Tvar dat se nevymýšlí: když obchod rozhraní změní, přidá se nová fixture
s novým datem.

| Soubor | Odkud | Co obsahuje |
|---|---|---|
| `kaufland/prehled-2026-10-02.html` | `prodejny.kaufland.cz/nabidka/prehled.html?kloffer-week=current` | stav komponenty OfferTemplate s 9 položkami (8 různých): vejce (i duplicitně v Superkaufu), trvanlivé mléko (`specialItems`), Coca-Cola 1,5 l (`smallPrice`, různé druhy), smetana s Kaufland Card a dvojím balením, máslo jen s kartou, položka bez `title` a položka bez názvu, borůvky ze „Startu týdne“ |
| `tesco/leaflets-2026-10-02.json` | leaflets-be, dotaz `leaflets` | letáky HM, SM a katalog CAT (celá odpověď) |
| `tesco/leaflet-hm-2026-10-02.json`, `leaflet-sm-…` | leaflets-be, dotaz `leafletBySlug` | jen stránky s produkty, které mají akci v promotions fixtures, a pár dalších |
| `lidl/home-2026-10-02.html` | `www.lidl.cz/` | jen odkazy na 4 kampaně (čtvrteční nabídka dvakrát) |
| `lidl/ctvrtecni-nabidka-…`, `1-1-zdarma-…`, `vikendova-nabidka-…`, `vdechni-latkam-zivot-…` | `www.lidl.cz/c/{slug}/a{id}` | vybrané dlaždice `data-grid-data`: Lidl Plus bez ceny bez aplikace (Mochi) i s ní (borůvky, smetana), „Ušetřete*“ (Kofola, mozzarella „…Kč/PP“), „Super cena“ s balením jen jako cenou za jednotku (rýže), 1+1 zdarma, položky bez data (prosecco, Tokaji), nepotravinová kampaň |
| `lidl/letaky-2026-10-02.html` | `www.lidl.cz/c/akcni-letak/s10008644` | jen odkazy na 3 letáky: potravinový od 8. 10., spotřební zboží a hity týdne (ty se nestahují) |
| `lidl/flyer-akcni-letak-od-ctvrtka-8-10-11-10-2026-2026-10-02.json` | `endpoints.leaflets.schwarz/v4/flyer` | údaje letáku a stránky 1 (vejce, máslo), 10 (dýně máslová), 18 a 49 (recepty), 28 (Coca-Cola bez Zero) s `keyWords`, `altText` a `thumbnail` |
| `albert/leaflets-hypermarket-…`, `leaflets-supermarket-…` | GraphQL `getLeaflets` na albert.cz | celé odpovědi: hlavní leták HM, hlavní leták SM a lokální varianta SM (`isDefault=false`) |
| `albert/spreads-40sm-2026-10-02.json` | `letaky.albert.cz/40sm_akcni_letak/spreads.json` | stránky 2 (Eidam, kuře), 3 a 31 (Coca-Cola) s `text` a náhledem `at200` |
| `penny/products-2026-10-02.json` | `www.penny.cz/api/product-discovery/products` | celá odpověď (33 položek) |
| `penny/letaky-…`, `penny/leaflet-index-…` | stránka letáků, index letáku | odkaz na leták; odkazy na stránky 2–4 |
| `penny/page-0001-…`, `page-0004-…`, `page-0030-…` | `…/page-vectorlayers/NNNN.svg` | skutečné strany 1 (titulní, položky z API), 4 (mléčné výrobky) a 30 (víkend, mléko 9,90) **bez vložených písem** (`<svg:style>`) — text a polohy beze změny |
| `tesco/taxonomy-2026-10-02.json` | xapi `taxonomy` | 60 uzlů stromu kategorií: celé větve mléka, másla a vajec, limonády a kolové nápoje, k tomu vyloučená oddělení „Top výběr“ a „Domov a zábava“ |
| `tesco/promotions-page1-2026-10-02.json`, `page2-…` | xapi `promotionType("all")` | 9 produktů po 5 na stránku: sleva jen v letáku HM (kuřecí řízky na váhu), sleva v HM i SM (vejce, mandarinky na váhu, okurka za kus), Clubcard v obou letácích (mléko), Clubcard na váhu jen online (česnek), Clubcard jen online (Coca-Cola Zero 0,5 l), „3 za cenu 2“ (pomazánka), „Super cena“ s původní cenou (káva) |

Fixtures vygeneroval jednorázový skript z odpovědí uložených při průzkumu. Položky jsou
beze změny; skript jen vybral podmnožinu a akce e-shopu Tesco přeskládal po 5 na stránku
(`info.total`, `page`, `count`, `offset`), aby šlo testovat stránkování.
