<!--
  Odložené úkoly — Slevohlídka
  @author Roman Hlaváček
  @created 2026-10-02
-->

# TODO: odložené úkoly

Věci, na kterých jsme se domluvili, ale záměrně jsme je odložili, a nápady
zapsané na později. Co se rozhodne a udělá, se odsud smaže a popíše
v [PLAN.md](PLAN.md). Větší celky se z toho stávají etapou.

---

## Upozornění

**Odkud:** zadání 2026-10-02. E-mailová upozornění hned / denně / týdně (R42, R58), upozornění v telefonu (web push, R66) a centrum upozornění s novými, končícími a nejlevnějšími akcemi a zprávami od nás (R74, etapa 11) jsou hotová.

- **zprávy od nás (R74, 11d):** vzít odeslanou zprávu zpět (smazat její záznamy — dnes je spojuje jen `announcementId` v JSON datech); propagační zprávy do telefonu jen po rozšíření textu souhlasu s obchodními sděleními (dnes zní na e-mail) a zvýšení `marketing_consent_version`
- **upozornění na zlevnění běžící akce** (R74, odloženo z 11c): import cenu u stejného klíče tiše přepíše — muselo by si pamatovat původní cenu (`previous_price`, `price_dropped_at`) a porovnávat srovnatelnou cenu (`PriceHistory::comparablePrice`, ne přechod na akci s kartou). Měření 5. 10. 2026: 3 zlevnění z 10 254 běžících akcí za 2–3 dny (Kaufland 1, Billa 2), ostatní obchody 0
- e-mailový souhrn s odkazem do centra upozornění (dnes „Otevřít Moje slevy“)
- tlačítko „Do seznamu“ přímo v upozornění v telefonu — service worker nemá token CSRF, potřeboval by podepsanou adresu jako odhlášení z e-mailů
- odběr, který prohlížeč sám vymění (`pushsubscriptionchange`), se dnes obnoví až zapnutím v Můj účet — obnovovat ho při startu aplikace
- volba, jestli upozornění v telefonu chodí i v době, kdy jde e-mail (dnes nezávisle — kdo má obojí „hned“, dostane obojí)
- zmínky v letácích bez ceny (R27) v souhrnu — dnes jen akce s cenou
- tabulka `watch_matches` (co už uživatel viděl / dostal), pokud nebude stačit čas posledního souhrnu a `offers.created_at`

## Hlídání — rozšíření

**Odkud:** etapa 3, R18 a R19 v PLAN.md.

- víc šablon podle toho, co se v praxi hlídá (káva, pivo, toaletní papír…); vyloučená slova odvozovat ze skutečných nabídek
- náhled „co by položka teď našla“ přímo ve formuláři Hlídám, aby šlo ladit vyloučená slova bez přepínání stránek
- u „Možná“ ukázat, které slovo chybí

## Historie a porovnání cen

**Odkud:** R10 v PLAN.md. Nabídky se nemažou.

- graf vývoje ceny u produktu (podnět uživatelů 5. 10. 2026) — nejnižší cena po týdnech napříč obchody (produkt katalogu přes `offer_product`, hlídaná položka) nebo jedné položky obchodu (`chain` + `external_id`); data jsou až od 2. 10. 2026 (5. 10. mělo víc cen jen 4 položky z ~10 600), vrátit se k tomu za pár týdnů. Srovnání s dřívějšími akcemi stejné položky je hotové (R59)
- „je tahle akce opravdu výhodná?“ hotové (R59) — dál: srovnání i s běžnou cenou mimo akci (e-shopy Tesca a Billy ji mají)
- Penny a Albert uvádějí nejnižší cenu za 30 dní, dá se uložit jako další údaj

## Další obchody

- Globus (R46) a Billa (R48) hotové. Billa: zmínky z letáku Publitas (jako Albert R36) by doplnily „různé druhy“ — zatím ne.
- Norma, Coop, Rossmann, dm. Každý potřebuje vlastní průzkum zdroje dat jako v [ZDROJE_DAT.md](ZDROJE_DAT.md).
- **Makro** — průzkum 2026-10-02: makro.cz je za ochranou proti robotům (403 i `robots.txt`), jinde jen agregátory (R1). Čeká na oficiální přístup, viz [ZDROJE_DAT.md](ZDROJE_DAT.md#makro-průzkum-2026-10-02--zatím-bez-zdroje).

## Stahování — rozšíření

**Odkud:** etapa 2, R15–R17 v PLAN.md.

- **Albert a Lidl: neověřené dlaždice PDF** (R86, R87) — balení 1 kg / 1 l / 1 ks bez ceny za jednotku, „cena za 100 g“, konzervy s cenou z hmotnosti po odkapání (Albert ~30 %, Lidl ~40 % velkých cen); případně ověřit polohou jako Penny (R85) nebo LLM
- **Lidl: leták zmizí ze seznamu dřív, než jeho akce skončí** (R86) — akce z PDF by se označily jako stažené obchodem (R16); sledovat
- **Zmínky bez ceny (R27):** ověřit, jestli najdou něco u položek z katalogu — zmínky hledají celá slova, pravidla katalogu jsou začátky slov („eidamsk“), takže nejspíš ne (revize 4. 10. 2026); u zmínky ukázat, které slovo ji našlo; víc frází pro stránky bez akcí (recepty, soutěže); zmínky i pro Kaufland (`keyWords` v API letáků Schwarz) a Tesco (seznam produktů letáku)
- **Lidl: nepotravinové akce** (R25) — dnes se ukládají jen `category: Food`
- **Penny: neověřené dlaždice letáku** (R26, R85) — ~85 cen z ~575 (nepotraviny bez balení, velké dlaždice ovoce a zeleniny, drogerie na tmavém pozadí)
- **Tesco „Super ceny“ z letáku** (R17): položky letáku bez akce v e-shopu chybí — doplnit z obrázků stránek letáku (vision LLM, etapa 6)
- **Tesco: akce příštího týdne z PDF letáku** (R17, R86) — e-shop ukazuje akci až od začátku, proto Tesco v „Brzy“ nemá nic. Ověřeno 6. 10. 2026 na letáku HM od 7. 10.: `pdftotext -bbox-layout` dá **ceny čitelně** („Běžná cena 399,90“, „20 %“, „319 90“, „Clubcard cena“, „7. 10.–13 10.“), ale **názvy produktů jsou rozbité** („ě“, „e l“, „de“). Šlo by: názvy z hotspotů `leafletBySlug` (`positions[].calculatedPositionX/Y` + `products[].promoOfferName`, odkaz do e-shopu) a k bodu přiřadit nejbližší cenu z PDF; po začátku převzetí řádku akcí z e-shopu (`supersedes`, R88). Rizika: přiřazení bodu k ceně bez ověření cenou za jednotku (není-li čitelná), sloučení s e-shopem
- **řazení výsledků hledání** podle shody nebo slevy — dnes podle začátku platnosti, takže dlouhodobé akce e-shopu jsou nahoře

## Provoz a údržba

**Odkud:** kritická revize 4. 10. 2026 (R54–R65 vyřešily chyby importu, souhrnů a zámek stažení).

- **upozornění na chyby e-mailem** (log kanál `mail` nebo denní souhrn chyb) — dnes chyby vidí jen ten, kdo otevře logy přes FTP; Albert padal den, než se na to přišlo (R65)
- **kontroly kvality v `build-upload.ps1`** — Pest a PHPStan před sestavením balíčku (CI není, R14)
- **`/health/imports` i pro import prodejen Kauflandu** — se zastaralými seznamy prodejen zmizí ~10 % akcí, pod hranicí pojistky R54
- **retence:** smazat `offers.raw` u akcí skončených před N měsíci (Billa ~400 MB ročně), čistit `offer_stores` a `leaflet_pages` skončených akcí
- **cron „Spuštění PHP souboru“ místo URL** (DEPLOYMENT.md) — bez limitu délky požadavku a tokenu v URL, vyřešilo by O8; ověřit, jestli ho Websupport umí
- **nasazení přes FTP není atomické** — režim údržby (`storage/framework/down`) během nahrávání, případně nová složka a přepnutí kořene webu
- **parsery letáků (Penny SVG, Lidl a Albert PDF) jsou křehké vůči změně rozvržení** — po každé změně měřit na celém letáku (R85–R87); hlídat propad počtu akcí z letáku

## Katalog produktů — rozšíření

**Odkud:** etapa 5, R28–R30 v PLAN.md.

- rozšiřovat katalog (R33, R37, R70; dnes 206 produktů, ~64 % akcí) — hlavně o věci, které uživatelé hlídají vlastními slovy, a sezónní (husa a kachna k Martinu, kapr na Vánoce, mák, prášek do pečiva a vanilkový cukr na cukroví — teď skoro bez akcí, pravidla nejdou ověřit); „Celé kuře“ potřebuje pravidlo, které odliší „kuře“ od „kuřecí“
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

- aplikace v telefonu hotová (R66) — dál: snímky obrazovky v manifestu (bohatší dialog instalace na Androidu, potřebují snímky přihlášené aplikace), `share_target` (sdílení textu z jiné aplikace rovnou do Hlídám), tmavé úvodní obrazovky iPhonu
- skener čárového kódu v obchodě („je tohle jinde ve slevě?“) — `BarcodeDetector` umí jen Chromium na Androidu a EAN mají jen některé zdroje (Globus, Kaufland v URL obrázku)
- „Jsem v obchodě“ podle polohy — prodejny nemají souřadnice; poloha je citlivý údaj
- kompaktní řádky „Jsem v obchodě“ (R62) i ve Všech akcích a Mých slevách (R82) hotové — dál: „Hlídat“ i v řádku
- „Hlídat“ z karty hotové (R60) — dál: i v Mých slevách a na úvodní stránce
- nákupní seznam hotový (R61) — dál: sdílení seznamu s rodinou, přidání vlastní položky bez akce

## Filtry a přehlednost výpisů

**Odkud:** návrh 7. 10. 2026 (situace: upozornění, plánování nákupu, v obchodě, brouzdání, hledání, příchod z Googlu). Hotové P1–P3 (R100–R102): řazení, „Podle Mých obchodů“, štítky Nové / Končí brzy / Sleva od / Kategorie, v Mých slevách i Jen jisté shody, dočasně všechny prodejny a pohled Podle obchodů, položky bez akce dole, obchod u nejnižší ceny, menší úvodní pruh, na telefonu okna Seřadit / Filtry.

- **filtr „Nejlevnější za 12 týdnů“** (`PriceHistory::LOWEST` jako SQL — dřívější skončená akce stejné položky za vyšší cenu): odloženo, data jsou od 2. 10. 2026 a filtr by skoro nic nenašel; vrátit se, až bude historie aspoň pár týdnů
- kategorie i pro akce bez produktu katalogu (~35 %): převodní tabulka kategorií obchodů na oddělení (Billa 86, Tesco 87, Globus 49 kategorií, Albert žádné) — R102 bere jen přiřazení k produktu
- „Nové“ v Mých slevách od poslední návštěvy nebo upozornění místo pevných 2 dnů — chtělo by to pamatovat si čas návštěvy

## Přihlášení přes účty

**Odkud:** R96 (2026-10-06) — Google a Facebook jsou hotové.

- **Apple** (Sign in with Apple) — Apple Developer Program 99 USD ročně, balíček `socialiteproviders/apple`, odpověď přichází jako POST z cizí domény (cookie relace `SameSite=Lax` nepřijde → výjimka z CSRF a stav bez relace), klíč klienta je JWT platný nejvýš 6 měsíců, jméno jen při prvním přihlášení; skrytý e-mail (`@privaterelay.appleid.com`) přijímá jen poštu z domény registrované u Applu (SPF/DKIM) — jinak nedojdou souhrny ani ověření
- Google One Tap (přihlášení bez přesměrování) — skript `accounts.google.com` do CSP a jeho cookies až po souhlasu
- v přehledu uživatelů pro admina (R84) ukázat, jak se kdo přihlašuje
