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

- **upozornění na chyby e-mailem** (log kanál `mail` nebo denní souhrn chyb) — výpadky stahování a úloh cronu už hlásí centrum upozornění adminům (R126), chyby v logu (výjimky, které výpadek nezpůsobí) dál vidí jen ten, kdo otevře logy přes FTP
- **kontroly kvality v `build-upload.ps1`** — Pest a PHPStan před sestavením balíčku (CI není, R14)
- **retence:** `offers.raw` se vyprazdňuje (R113); zbývá čistit `offer_stores` a `leaflet_pages` skončených akcí a `scrape_runs`, skryté akce skončených akcí (`watch_item_offer_exclusions`, R125) a vyřešená hlášení (`offer_reports`)
- **cron „Spuštění PHP souboru“ místo URL** (DEPLOYMENT.md) — bez limitu délky požadavku a tokenu v URL, vyřešilo by O8; ověřit, jestli ho Websupport umí
- **nasazení přes FTP není atomické** — režim údržby (`storage/framework/down`) během nahrávání, případně nová složka a přepnutí kořene webu
- **parsery letáků (Penny SVG, Lidl a Albert PDF) jsou křehké vůči změně rozvržení** — po každé změně měřit na celém letáku (R85–R87); hlídat propad počtu akcí z letáku

## Z kritické revize 7. 10. 2026 (R106) — zatím neudělané

**Odkud:** revize kódu, provozu a frontendu 7. 10. 2026. Hotové body jsou v R106.

- **zálohy:** seznam tabulek v DEPLOYMENT.md (*Záloha databáze*) chybí `social_accounts` (účty bez hesla se po obnově nepřihlásí), `shopping_list_items`, `push_subscriptions`, `notifications`, `announcements`, `watch_item_offer_exclusions` a `offer_reports` (R125) a ruční řádky `offer_product` — zálohovat celou databázi kromě `offers`, `offer_stores`, `leaflet_pages`, `sessions`, `cache`; obnovu jednou vyzkoušet v Dockeru
- **vypínač obchodu v `.env`**, který skryje i už uložené akce — výzvě obchodu (O6) vyhovět bez nasazení kódu
- **test shody SQL skriptů s migracemi:** pustit `deploy/migrations-*.sql` na prázdnou databázi a porovnat `SHOW CREATE TABLE` s výsledkem `migrate`
- UptimeRobot i na `/up` (`/health/tasks` pro prodejny, upozornění, úklid a kategorie hotové, R115)
- frontend: ESLint a test klíčů `t('…')` hotové (R113); zbývá `jsconfig.json` s `checkJs` a případně Vitest pro `lib/format`, `lib/i18n`, `lib/offer`
- trvalý layout (`defineOptions({ layout: AppLayout })`) a `Inertia::once` pro statické sdílené props (`chainInfo`, `siteFooter`, `pwa`, `cookieConsent`) — před změnou ověřit fokus po přechodu (`lib/a11y.js`)
- `RecordNewOffers`, `RecordStartingOffers` a `SendDigests` počítají `MyOffers::forUser` pro stejného uživatele až třikrát (~0,2 s každé, R113) a `RecordStartingOffers` ho počítá každému s hlídáním, i když se ho dnešní akce netýkají — sdílet výsledek v rámci požadavku jen s omezenou pamětí (limit 512 MB), u začínajících akcí nejdřív levně ověřit průnik
- Offers.vue: logika filtrů do `useOfferFilters`, okno Filtry do komponenty (sledování sekce Účtu je `lib/scrollSpy.js`, R113)
- dva výčty řazení (`OffersSort` pro Moje slevy, `OfferListSort` pro Všechny akce) se stejnými volbami pod jinými hodnotami — sjednotit

## Z auditu technického dluhu 9. 10. 2026 (R113) — zatím neudělané

**Odkud:** audit kódu 9. 10. 2026. Opravené chyby, výkon a sjednocení kopií jsou v R113.

- **Proces:** CI (GitHub Actions s MariaDB 11.4 — testy nesahají na síť) a nasazení podle `git diff --name-status <nasazený>..HEAD` (seznam k nahrání i ke smazání, vždy `version.txt` — u 27. nasazení zůstal starý); CSP z `public/.htaccess` lokálně neběží (nginx) — test, který porovná CSP s doménami v `consent.js`
- **Import:** `AssignProducts::forChain` přepočítává uvnitř transakce importu všechny akce obchodu × všechny produkty (Tesco ~1 s, roste s katalogem) — jen nové a změněné akce; upsert přepisuje i nezměněné řádky včetně `raw`
- **Penny a Lidl:** odstranění duplicit Lidlu normalizuje název webové akce znovu pro každou akci letáku (Penny už předpočítává, R113); Penny každá stránka letáku se zpracuje dvakrát (`tileOffsets` + `offers`)
- **ID akcí z letáku** Albertu a Penny jsou otisk `mb_strtolower` názvu, ne `TextNormalizer` jako Lidl a Globus — jiná mezera v PDF (nová verze `pdftotext`) udělá z akcí nové; změnit jen s plánovaným nasazením (jednorázově nová upozornění)
- **PDF parsery:** `PennyLeafletParser` (1 139 ř.) je mimo `PdfBox` / `PdfLayout` — převod by chtěl otočit osu y tokenů SVG a přepsat párování; při úklidu 9. 10. odloženo, riziko neodpovídá přínosu (parser je pokrytý měřením R85). Albert a Globus mají dál každý vlastní `prices()` a `tiles()` — liší se pravidly (celé koruny jedním slovem, řádky ceny za jednotku), sdílené části jsou v `PdfLayout`. Každou změnu měřit snímkem celých letáků (`storage/app/proto/audit-snapshot.php`, není v repu)
- **Account.vue** (~550 ř.): sekce (profil, upozornění, Moje slevy, zabezpečení, zrušení) jako komponenty
- **Ponecháno záměrně:** odpověď cron URL nese text výjimky (za tokenem, WebAdmin ho ukáže v e-mailu o chybě); `set_time_limit(180)` pod limitem hostingu 600 s (timeout proxy Websupportu neznáme, O8); `SocialLoginController` posílá do toastu text odmítnutí (stejný text je i chybou formuláře přihlášení); na Všech akcích jsou filtry v okně na telefonu a v pruhu na širokém displeji dvakrát — jsou to různé ovládací prvky (štítky × výběry)
- **Testy a prostředí:** stejné pomocné funkce pod třemi názvy (`importedOffer`, `centerImportedOffer`, `pushImportedOffer`) a token cronu v 7 testech do `tests/Pest.php`; `pcov` pro měření pokrytí; Pint `declare_strict_types`; kontejner bez `icu-data-full` a GD, `max_execution_time` 300 proti 600 na hostingu; `axllent/mailpit:latest` bez verze

## Měření používání (Clarity) — co ubrat

**Odkud:** revize 7. 10. 2026 — za týden přibylo přes sto rozhodnutí a Moje slevy mají výběr obchodu,
dva pohledy, řazení, karty / řádky, Rozbalit vše, čtyři štítky a dvě sbalené sekce. Než přibude další
funkce, změřit, co lidé opravdu používají, a nepoužívané schovat nebo zrušit.

- **kdy:** po 2–3 týdnech provozu s Clarity (R103); vzorek jsou jen lidé se souhlasem s analytickými cookies
- **co sledovat** (Clarity → *Heatmaps* klikání na `/` a `/akce`, *Smart events* / filtr podle adresy):
  štítky Nové / Končí brzy / Jen jisté / Moje prodejny, pohled Podle obchodů, přepínač karty / řádky,
  Rozbalit vše, sekce Brzy a Zatím bez akce, čísla v úvodním pruhu, „Jsem v obchodě“ (výběr obchodu),
  ve Všech akcích Sleva od, Kategorie a řazení, na úvodní stránce hra „Co je levnější?“ a ukázka hlídání,
  nákupní seznam, centrum upozornění; *Dead clicks* a *Rage clicks* ukážou, co mate
- **rozhodnutí:** co skoro nikdo nepoužívá, schovat do okna Filtry nebo zrušit (nový záznam R…);
  co lidé hledají a nenajdou, posunout výš

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

**Odkud:** R96 (2026-10-06) — Google a Facebook jsou hotové, Seznam R98.

- **Microsoft** (osobní účty Outlook.com / Hotmail i pracovní) — zváženo 9. 10. 2026 a odloženo: české publikum má účet hlavně u Seznamu a Googlu. Půl dne až den: vlastní ovladač jako Seznam (OAuth 2.0 `login.microsoftonline.com/common`, jméno a e-mail z Graph `/me`), aplikace v Microsoft Entra zdarma; e-mail **brát jako neověřený** (připojení podle e-mailu = převzetí účtu, „nOAuth“), bez ověření vydavatele (Microsoft Partner Network) ukáže souhlasová obrazovka „neověřený vydavatel“; texty a logo podle pravidel značky Microsoftu, zásady (další správce)
- **Apple** (Sign in with Apple) — Apple Developer Program 99 USD ročně, balíček `socialiteproviders/apple`, odpověď přichází jako POST z cizí domény (cookie relace `SameSite=Lax` nepřijde → výjimka z CSRF a stav bez relace), klíč klienta je JWT platný nejvýš 6 měsíců, jméno jen při prvním přihlášení; skrytý e-mail (`@privaterelay.appleid.com`) přijímá jen poštu z domény registrované u Applu (SPF/DKIM) — jinak nedojdou souhrny ani ověření
- Google One Tap (přihlášení bez přesměrování) — skript `accounts.google.com` do CSP a jeho cookies až po souhlasu
- v přehledu uživatelů pro admina (R84) ukázat, jak se kdo přihlašuje

## Nápady 9. 10. 2026

**Odkud:** návrh nových funkcí 9. 10. 2026 (po R124).

- **„Kam dnes na nákup?“** — z nákupního seznamu nebo hlídaných položek spočítat, ve kterém obchodě (nebo kombinaci dvou) vyjde nákup nejlevněji a kolik se ušetří; podklad je cena za jednotku a `UserPricing`. Odliší nás od agregátorů: ne „kde je co v akci“, ale „kam jít“
- **cílová cena u hlídané položky** — „ozvi se, až bude máslo pod 180 Kč/kg“ (cena za jednotku); dnes jen minimální sleva pro celý účet (R41). Méně, ale trefnějších upozornění
- „Tohle ne“ a hlášení chyb hotové (R125) — dál: z hlášení rovnou opravit akci (ručně přepsat cenu nebo ji skrýt všem), e-mail adminovi při novém hlášení (upozornění v telefonu je hotové), poděkovat uživateli v centru upozornění, až je hlášení vyřešené
- **sdílení akce z karty** (Web Share API, „pošli to partnerovi“) — dnes ho má jen nákupní seznam
- **jak často bývá zboží v akci** — „Máslo bývá v Lidlu v akci zhruba každé 3 týdny, naposledy 28. 9.“; navazuje na „Vyplatí se počkat“ (R76) a graf ceny (*Historie a porovnání cen*), smysl má až s historií za několik týdnů
- Nejlepší slevy týdne hotové (R128) — dál: vlastní obrázek pro sdílení (OG) s číslem týdne a třemi nejvyššími slevami, příspěvek na sociální sítě v pondělí
- **admin přehled kvality dat** — u každého obchodu a letáku vývoj počtu akcí za posledních N stažení a podíl neověřených dlaždic, propad vidět hned (rozšiřuje „hlídat propad počtu akcí z letáku“ v *Provoz a údržba*)
