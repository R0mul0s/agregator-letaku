<!--
  Odložené úkoly — Agregátor letáků
  @author Roman Hlaváček
  @created 2026-10-02
-->

# TODO: odložené úkoly

Věci, na kterých jsme se domluvili, ale záměrně jsme je odložili, a nápady
zapsané na později. Co se rozhodne a udělá, se odsud smaže a popíše
v [PLAN.md](PLAN.md). Větší celky se z toho stávají etapou.

---

## Upozornění

**Odkud:** zadání 2026-10-02. Výstupem je zatím webový seznam.

- e-mail (případně Telegram), když se hlídaná položka objeví v akci
- souhrn jednou týdně po vydání nových letáků

## Historie a porovnání cen

**Odkud:** R10 v PLAN.md. Nabídky se nemažou.

- graf ceny produktu v čase napříč obchody
- „je tahle akce opravdu výhodná?“ = porovnání s nejnižší akční cenou za posledních N týdnů
- Penny a Albert uvádějí nejnižší cenu za 30 dní, dá se uložit jako další údaj

## Další obchody

- Billa, Globus, Norma, Coop, Makro, Rossmann, dm. Každý potřebuje vlastní průzkum zdroje dat jako v [ZDROJE_DAT.md](ZDROJE_DAT.md).

## Stahování — rozšíření

**Odkud:** etapa 2, R15–R17 v PLAN.md.

- **Kaufland po prodejnách** (R15): až si uživatelé vyberou prodejny, stahovat i jejich variantu nabídky (cookie `x-aem-variant`) a ukládat rozdíly s `store_id`
- **Tesco „Super ceny“ z letáku** (R17): položky letáku bez akce v e-shopu chybí — doplnit z obrázků stránek letáku (vision LLM, etapa 6)
- **seznam prodejen Tesco** (O4) — `storeId` v API letáků existuje, zdroj seznamu neověřený
- **plánované spouštění** importů podle hostingu (O1) — dnes jen ručně artisan příkazem
- **řazení výsledků hledání** podle shody nebo slevy — dnes podle začátku platnosti, takže dlouhodobé akce e-shopu jsou nahoře

## Upřesnění „různých druhů“

**Odkud:** O5 v PLAN.md.

- Tesco: hotspoty letáku vyjmenovávají konkrétní varianty, takže stav „možná“ jde povýšit na „shoda“
- Albert: katalog `productSearch` jako zdroj variant a obrázků
- Kaufland: URL obrázku obsahuje EAN

## Zobrazení

- mapa vybraných prodejen
- aplikace na plochu telefonu (manifest), jako u Počasí
