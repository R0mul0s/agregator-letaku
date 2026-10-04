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

**Odkud:** zadání 2026-10-02. E-mailový souhrn denně / týdně je hotový (R42).

- okamžitý e-mail (případně Telegram), když se hlídaná položka objeví v akci — dnes jen souhrn jednou denně
- zmínky v letácích bez ceny (R27) v souhrnu — dnes jen akce s cenou
- tabulka `watch_matches` (co už uživatel viděl / dostal), pokud nebude stačit čas posledního souhrnu a `offers.created_at`

## Hlídání — rozšíření

**Odkud:** etapa 3, R18 a R19 v PLAN.md.

- víc šablon podle toho, co se v praxi hlídá (káva, pivo, toaletní papír…); vyloučená slova odvozovat ze skutečných nabídek
- náhled „co by položka teď našla“ přímo ve formuláři Hlídám, aby šlo ladit vyloučená slova bez přepínání stránek
- u „Možná“ ukázat, které slovo chybí

## Historie a porovnání cen

**Odkud:** R10 v PLAN.md. Nabídky se nemažou.

- graf ceny produktu v čase napříč obchody
- „je tahle akce opravdu výhodná?“ = porovnání s nejnižší akční cenou za posledních N týdnů
- Penny a Albert uvádějí nejnižší cenu za 30 dní, dá se uložit jako další údaj

## Další obchody

- Globus (R46) a Billa (R48) hotové. Billa: zmínky z letáku Publitas (jako Albert R36) by doplnily „různé druhy“ — zatím ne.
- Norma, Coop, Rossmann, dm. Každý potřebuje vlastní průzkum zdroje dat jako v [ZDROJE_DAT.md](ZDROJE_DAT.md).
- **Makro** — průzkum 2026-10-02: makro.cz je za ochranou proti robotům (403 i `robots.txt`), jinde jen agregátory (R1). Čeká na oficiální přístup, viz [ZDROJE_DAT.md](ZDROJE_DAT.md#makro-průzkum-2026-10-02--zatím-bez-zdroje).

## Stahování — rozšíření

**Odkud:** etapa 2, R15–R17 v PLAN.md.

- **Albert: ceny z textu stránek** (R36) — text Publitas má názvy, balení i ceny, ale ceny rozsekané („31“ „90“) a bez polohy; zkusit párování podle pořadí bloků, nebo vision LLM nad obrázkem stránky (etapa 6)
- **Lidl: ceny ze zbytku letáku** (R23, R25) — potraviny jen v letáku dnes ukazujeme jako zmínky bez ceny (R27); cenu by dal až text PDF nebo obrázek stránky přes LLM
- **Zmínky bez ceny (R27):** u zmínky ukázat, které slovo ji našlo; víc frází pro stránky bez akcí (recepty, soutěže); zmínky i pro Kaufland (`keyWords` v API letáků Schwarz) a Tesco (seznam produktů letáku)
- **Lidl: nepotravinové akce** (R25) — dnes se ukládají jen `category: Food`
- **Penny: neověřené dlaždice letáku** (R26) — ~260 cen z ~560 bez ověření cenou za jednotku; tokeny stránky s polohami předat LLM
- **Tesco „Super ceny“ z letáku** (R17): položky letáku bez akce v e-shopu chybí — doplnit z obrázků stránek letáku (vision LLM, etapa 6)
- **plánované spouštění** importů cron URL na Websupportu (R20, etapa 7) — dnes jen ručně artisan příkazem
- **řazení výsledků hledání** podle shody nebo slevy — dnes podle začátku platnosti, takže dlouhodobé akce e-shopu jsou nahoře

## Katalog produktů — rozšíření

**Odkud:** etapa 5, R28–R30 v PLAN.md.

- rozšiřovat katalog (R33, R37; dnes 164 produktů, ~52 % akcí) — hlavně o věci, které uživatelé hlídají vlastními slovy; „Celé kuře“ potřebuje pravidlo, které odliší „kuře“ od „kuřecí“
- zápis „celé slovo“ v pravidlech (např. `=rum`) — krátká slova jako začátek slova chytají i jiná slova („rum“ → „Rump“), dnes to řeší vyloučená slova
- seznam **nepřiřazených akcí** v katalogu (potraviny bez produktu) jako podklad pro nové produkty; později třídění přes LLM (etapa 6)
- akce Tesca mají v e-shopu i polici stromu (`superDepartmentName`, `departmentName`; regál a police jde doplnit do dotazu) — zařadit je do kategorií rovnou, bez pravidel
- filtr podle kategorie ve Všech akcích a procházení katalogu stromem
- v detailu produktu ukázat, které slovo akci našlo, a náhled změny pravidel před uložením
- přepočet automatického přiřazení po změně pravidel nechává staré přiřazení u skončených akcí (historie) — zvážit, jestli je přepočítat taky

## Upřesnění „různých druhů“

**Odkud:** O5 v PLAN.md.

- Tesco: hotspoty letáku vyjmenovávají konkrétní varianty, takže stav „možná“ jde povýšit na „shoda“
- Albert: katalog `productSearch` jako zdroj variant a obrázků
- Kaufland: URL obrázku obsahuje EAN

## Zobrazení

- manifest je hotový (R55); service worker až s upozorněními (web push, offline)
- „Jsem v obchodě“ (R55): kompaktní řádkový režim karet (cena, obchod, cena za jednotku) — velké karty s obrázkem znamenají v obchodě hodně posouvání
- „Hlídat tohle“ přímo z karty ve Všech akcích (i pro nepřihlášené — uložit lokálně, po registraci převést)
- nákupní seznam s odškrtáváním, seřazený podle obchodu (z revize 4. 10. 2026)
- spodní lišta záložek na telefonu místo hamburgeru (Moje slevy / Hlídám / Akce)
