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
| `kaufland/stores-2026-10-02.json` | `prodejny.kaufland.cz/.klstorefinder.json` | první 3 prodejny |
| `tesco/leaflets-2026-10-02.json` | leaflets-be, dotaz `leaflets` | letáky HM, SM a katalog CAT (celá odpověď) |
| `tesco/leaflet-hm-2026-10-02.json`, `leaflet-sm-…` | leaflets-be, dotaz `leafletBySlug` | jen stránky s produkty, které mají akci v promotions fixtures, a pár dalších |
| `tesco/promotions-page1-2026-10-02.json`, `page2-…` | xapi `promotionType("all")` | 9 produktů po 5 na stránku: sleva jen v letáku HM (kuřecí řízky na váhu), sleva v HM i SM (vejce, mandarinky na váhu, okurka za kus), Clubcard v obou letácích (mléko), Clubcard na váhu jen online (česnek), Clubcard jen online (Coca-Cola Zero 0,5 l), „3 za cenu 2“ (pomazánka), „Super cena“ s původní cenou (káva) |

Fixtures vygeneroval jednorázový skript z odpovědí uložených při průzkumu. Položky jsou
beze změny; skript jen vybral podmnožinu a akce e-shopu Tesco přeskládal po 5 na stránku
(`info.total`, `page`, `count`, `offset`), aby šlo testovat stránkování.
