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
Letáky bez strukturovaných dat čte deterministický parser textu s polohou (Penny SVG, Lidl
a Albert PDF přes `pdftotext`, [R26, R85–R87](ROZHODNUTI.md)); co neověří cena za jednotku (u balení 1 kg / 1 ks
jednoznačná poloha a sedící štítek slevy, [R107](ROZHODNUTI.md)),
zůstane zmínkou bez ceny ([R27](ROZHODNUTI.md)). LLM jen pokud bude potřeba ([R23](ROZHODNUTI.md)).

- **Vývoj:** `http://localhost:54720` (Docker, viz [CLAUDE.md](../CLAUDE.md))
- **Produkce:** sdílený hosting Websupport, `https://slevohlidka.cz` (do R93 `slevohlidka.rhsoft.cz`, ta přesměrovává), nasazeno 2026-10-02 ([R20, R38](ROZHODNUTI.md), [DEPLOYMENT.md](../deploy/DEPLOYMENT.md))
- **Repozitář:** [github.com/R0mul0s/agregator-letaku](https://github.com/R0mul0s/agregator-letaku), osobní projekt
- **Pravidla pro psaní kódu:** [CODING_GUIDELINES.md](CODING_GUIDELINES.md)
- **Zdroje dat jednotlivých obchodů (endpointy, pole, pasti):** [ZDROJE_DAT.md](ZDROJE_DAT.md)

### Co platí a co ne (stav k 4. 10. 2026, po revizi před spuštěním)

Log rozhodnutí (kap. 8) se nepřepisuje — starší rozhodnutí nahrazují novější. Tady je výsledek:

| Platí | Neplatí (čím nahrazeno) |
|---|---|
| Obchody se sledují celé, u Tesca (a Albertu) podle **typu prodejny** HM / SM (R19, R21); **u Kauflandu výběr více prodejen** (R49) | Výběr **konkrétních prodejen** a seznamy prodejen (R3 → R21), výběr prodejen Kauflandu (R19 → R21) |
| Kaufland po prodejnách (R49): seznam 149 prodejen a jejich akcí (cron `import-stores`), k výchozí nabídce stránky prodejen s chybějícími akcemi; akce, která neplatí všude, má prodejny (`offer_stores`); Moje slevy jen akce vybraných prodejen, u akce „Jen Trutnov“ | Jedna výchozí varianta pro všechny prodejny (R15 → R49); stahování jen prodejen vybraných uživateli (R3) |
| Bez LLM: Kaufland, Tesco, Lidl (kampaně na webu + PDF letáku), Penny (API + parser SVG letáku), Albert (PDF letáku), Globus (REST API webu), Billa (API celého katalogu + PDF budoucích letáků) (R23, R25, R26, R46, R48, R85–R89); dlaždice 1 kg / 1 ks bez ceny za jednotku u Albertu a Penny podle polohy a štítku slevy (R107) | LLM jako hlavní cesta pro letáky (R6 → R23); LLM jen v etapě 6, pokud bude potřeba |
| **Zmínky v letácích bez ceny** — Lidl, Penny a Albert (R27, R36); vyloučená slova položky jen v okolí hledaného slova, zmínku skryje i akce, která už skončila, ale platila v období letáku (R107) | Vyhledávací API letáků Lidlu (zakázané v robots.txt); zmínky bez vyloučených slov (R27 → R107) |
| Hlídaná položka = slova + varianta + vyloučení (R18); katalog produktů (R24, R28–R31) — kategorie ze stromu Tesca, produkty spravuje admin, přiřazení nabídek se ukládá s ručními opravami; hlídaná položka = produkt z katalogu, nebo vlastní slova | Dva oddělené typy hlídání produkt / kategorie (R9 → R18; tři stavy shody platí dál); vymýšlení vlastních kategorií (→ R28); šablony hlídaných položek v konfiguraci (→ produkty katalogu, R31) |
| Obrázky produktů odkazem na CDN obchodu (R22), u Billy a Penny menší varianta CDN (R108); loga obchodů jako soubory aplikace (R32) | Ukládání obrázků; originály fotek Billy a Penny (→ R108) |
| Katalog 206 produktů ověřených na skutečných akcích (R33, R37, R70), data v `database/seeders/data/catalog-products.php`; admin ho spravuje v tabulce `/katalog` | Startovní sada 4 produktů ze šablon (→ R33, R37) |
| Název **Slevohlídka**, motto „Rychlý lovec slev“, barvy a motivy z loga (R34, R35): písmo Nunito, cenovky slev, maskot v prázdných stavech, vodoznak loga obchodu v kartách | Pracovní název Agregátor letáků; vzhled v růžové barvě cenovky |
| Hledání ve Všech akcích s našeptávačem; výběr obchodu s logy (R32); čísla stránek a „Načíst další“ s rozsahem v adrese (R43), stejně v tabulce katalogu s hledáním a řazením na serveru | Jen „Předchozí / Další“; katalog filtrovaný a řazený v prohlížeči |
| **Tón (R72):** web i právní texty mluví za provozovatele „my“, vřele s jemným humorem (právní texty věcně, genderově neutrálně); stránka `/kontakt`; v patičce místo sekce Kontakt loga obchodů a čerstvost akcí (R92) | Texty za „Slevohlídku“ ve 3. osobě a právní texty za jednotlivce („já“, „zapsaný“); v patičce „Provozovatel“ a odkaz Kontakt jako mailto |
| **České adresy (R73):** přihlášení `/prihlaseni`, registrace `/registrace`, obnova hesla `/zapomenute-heslo` a `/nove-heslo`, ověření e-mailu `/overeni-emailu`, změna údajů a hesla pod `/ucet` (cesty Fortify v `config/fortify.php` `paths`); staré anglické adresy přesměrovává 301 | Výchozí anglické adresy Fortify (`/login`, `/register`, `/user/password`…) |
| **Centrum upozornění (R74):** `/upozorneni` se zvonkem a počtem nepřečtených v hlavičce; záznam (databázová notifikace Laravelu) vznikne po stažení s novými akcemi každému uživateli, i bez zapnutých upozornění; upozornění v telefonu se skládá ze záznamů a vede na detail, číslo na ikoně aplikace = nepřečtené; odpoledne záznam „Zítra končí…“ s akcemi z nákupního seznamu (11b); akce nejlevnější za 12 týdnů v nadpisu a se štítkem (11c); zprávy od nás od admina — o službě všem (i do telefonu), propagační jen se souhlasem a jen do centra (11d); 30 dní | Upozornění v telefonu jen s textem a odkazem na Moje slevy, nic se neukládá (R66); číslo na ikoně mazala návštěva Mých slev |
| **Hledání (R71):** živé výsledky bez tlačítka, od začátku slova, podle relevance (název → značka → popis); našeptávač s produkty (počet akcí, cena od, Hlídat), akcemi (obrázek, obchod, cena), posledními a oblíbenými hledáními; oprava překlepu; filtr produktu („Jen slevy“ zrušené, R77); na telefonu přes celou obrazovku. Hlídám: návrhy s počtem akcí a cenou, náhled vlastních slov, „Vrátit“ v toastu | Hledání podřetězce kdekoli (`%slovo%`) a výsledky podle data; našeptávač jen s texty; hledání až po tlačítku |
| Hlavička pro vyhledávače a sdílení ze serveru (meta, canonical, OG, schema.org; akce jako `Offer` bez vnořeného `Product`, R111), `robots.txt` / `sitemap.xml` / `llms.txt` z rout, limity požadavků, `trustProxies` (R45) | Statický `public/robots.txt` povolující vše; `itemOffered` typu `Product` v akci (R99 → R111); jen obecný popis v hlavičce; registrace a obnova hesla bez limitu |
| Úvodní stránka pro nepřihlášené na `/` (co Slevohlídka umí, počty, ukázka akcí, výzva k registraci); Všechny akce veřejné (R44); hledání, živá ukázka hlídání místo kroků a hra „Co je levnější?“ (R90) | Celá aplikace jen po přihlášení; `/` nepřihlášeného přesměrovalo na přihlášení |
| Moje slevy: sbalitelné skupiny s přehledem, akcemi upravit / přestat hlídat a „Rozbalit vše“ (R43); výběr „Jsem v obchodě“ s akcemi jednoho obchodu rozbalenými (R55) | Všechny akce všech položek rozbalené pod sebou |
| **Všechny akce a zobrazení (R82):** víc obchodů najednou (`?chain=kaufland,lidl`, přihlášený má předvybrané sledované, `?chain=vse` všechny), štítek „Bez e-shopu“ (`?bez-eshopu=1`); přepínač karty / řádky ve Všech akcích i v Mých slevách (volba společná, „Jsem v obchodě“ má vlastní a začíná v řádcích) | Výběr jen jednoho obchodu; řádky jen v režimu „Jsem v obchodě“ (R62) |
| **Řazení a filtry výpisů (R100):** Všechny akce řadí podle volby (`?razeni=`, výchozí Doporučené, s textem relevance, u produktu cena za jednotku) a přihlášenému uplatní nastavení Mých obchodů — prodejny, karty, e-shop (`ShoppingPreferencesScope`, `?moje-obchody=0` vypne, počet skrytých akcí pod štítky); Moje slevy mají řazení nad skupinami, položky bez akce ve sbalené sekci dole a obchod u nejnižší ceny; štítky Nové, Končí brzy, Sleva od a Zrušit filtry ve Všech akcích, Nové, Končí brzy, Jen jisté shody a dočasně všechny prodejny v Mých slevách (R101); kategorie přes katalog, Moje slevy Podle obchodů, menší úvodní pruh a na telefonu Seřadit / Filtry v oknech zespodu (R102) | Všechny akce bez hledání od nejdříve platných a bez nastavení Mých obchodů; řazení Mých slev jen v Mém účtu |
| **Neutrální tmavý režim (R83):** červená jen tlačítka a cenovky slev, ceny bílé, odkazy a aktivní položky šedé | Tmavý režim se světle lososovým akcentem, růžovými cenami a odkazy (R35) |
| **Akce, které ještě nezačaly (R76):** v Mých slevách ve sbalené sekci Brzy (v obchodě vůbec), „Vyplatí se počkat“ u výrazně levnější budoucí akce, štítek s datem začátku, filtr „Brzy začnou“, v nákupním seznamu za platnými; upozornění hned po zveřejnění s datem začátku a ráno „Od dneška platí…“ | Budoucí akce mezi platnými bez rozlišení |
| Nový účet sleduje všechny obchody a po registraci jde do Hlídám (R55) | Po registraci prázdné Moje slevy s výzvou vybrat obchody |
| **Aplikace v telefonu** (R66): manifest se zkratkami a maskovatelnou ikonou, úvodní obrazovky iPhonu, spodní lišta záložek, výzva k přidání na plochu; service worker — Moje slevy, nákupní seznam a Hlídám fungují offline, odškrtávání bez signálu; **upozornění v telefonu** (web push) z cronu souhrnů; nová verze po nasazení se načte sama nebo lištou „Načíst“ (R78); v aplikaci z plochy stažení stránky dolů načte data znovu (R112) | Jen manifest bez service workeru (R55 bod 5); navigace přihlášeného pod hamburgerem na telefonu; jen e-mailová upozornění |
| Účet v menu pod avatarem (vlastní obrázek nebo iniciály), přihlášená zařízení s odhlášením ostatních, zrušení účtu (R40); admin má v menu i katalog a zprávy uživatelům (R75); řazení a minimální sleva v Mých slevách jako předvolba účtu (R41); e-mailový souhrn nových akcí denně / týdně (R42) | Položka „Účet“ v hlavní navigaci, přepínač vzhledu a odhlášení přímo v hlavičce |
| **Přihlášení přes Google, Seznam (R98) a Facebook (R96):** tlačítka nad přihlášením a registrací, nový účet dokončí registraci se souhlasy a nemá heslo; k existujícímu účtu se sám připojí jen e-mail ověřený Googlem (Seznam a Facebook ne); v Mém účtu propojit / odpojit, účet bez hesla potvrzuje citlivé změny přihlášením u poskytovatele | Přihlášení jen e-mailem a heslem; Instagram (Meta ho pro běžné uživatele zrušila) a Apple (placený program) zatím ne |
| Hlídám: jedno pole s našeptávačem katalogu a volbou vlastních slov, dlaždice položek s počtem akcí a nejnižší cenou (R39); katalog k procházení jako v e-shopu — dlaždice oddělení s ikonou, po klepnutí pododdělení s produkty (R47) | Seznam katalogu a formulář vlastních slov stále rozbalené vedle seznamu položek (R31 → R39); sbalený seznam všech produktů s čipy oddělení (R39 → R47) |
| Potvrzení po uložení jako toast dole uprostřed obrazovky (kód stavu v `session('status')` → `ui.toast.messages`); nevratné akce potvrzuje vlastní okno (`<dialog>`); Moje obchody jako karty s přepínači; oslovení v 5. pádě („Ahoj, Romane!“) (R47) | Zpráva o uložení v obsahu stránky jen na Účtu a v Mých obchodech, jinde nic; `window.confirm`; Moje obchody se zaškrtávátky a červeným rámečkem u každého obchodu |
| Globus z REST API webu: jeden hypermarket, jen akce VKA0, bez oblečení a obuvi, cena s aplikací Můj Globus (R46) | Globus jen jako budoucí průzkum; Makro bez zdroje (ochrana proti robotům) |
| Billa z API celého katalogu (kvůli akcím jen s BILLA Klubem), platnost = akční týden středa–úterý, který obsahuje dnešek (R48); letáky, které ještě nezačaly, z PDF spárované s katalogem — akce celého týdne pod kódem produktu, jiná platnost pod předběžným ID (R89) | Jen filtr `inPromotion` (bez akcí s Klubem) |
| Krmivo pro zvířata se ukáže jen u hlídání o zvířatech (R50) — pozná ho kategorie obchodu nebo slova a značky v textu, platí pro hlídané položky i katalog | Vylučovat krmivo vyjmenovanými slovy u každého produktu zvlášť (Friskies, Cesar… chyběly) |
| Příprava na zveřejnění (R51): podmínky užití a zásady zpracování osobních údajů (`resources/legal`), patička webu s provozovatelem (e-maily jen s mottem, R81), povinný souhlas s podmínkami a dobrovolný souhlas s obchodními sděleními při registraci, **ověření e-mailu** (souhrn jen na ověřenou adresu), odhlášení z e-mailů jedním klepnutím, české chybové stránky; lišta souhlasu s cookies a Google Analytics až po souhlasu (R52) | Ověření e-mailu vypnuté (R13 → R51); souhrn na neověřenou adresu; odhlášení jen po přihlášení; anglické chybové stránky Laravelu; provozovatel v patičce e-mailů (R51 → R81) |
| Odkaz akce Kauflandu vede na kategorii s otevřeným detailem akce (`kloffer-articleID` = `klNr`, R109) | Odkaz na celý přehled nabídky; textový fragment na dlaždici (→ R109) |
| Odkaz akce na stránku letáku má text „Do letáku“, ostatní „Do obchodu“ — podle adresy odkazu (`letaky.offers.leaflet_link_prefixes`, R110) | Vždy „Do obchodu“; přímé vložení do košíku Tesca (vyžaduje přihlášení u Tesca, R110) |
| Produkce Websupport, cron URL, SQL skripty migrací, bez fronty (R20); balíček v `deploy/` pro `slevohlidka.cz` (R93), cron po obchodech, `/health/imports` pro UptimeRobot (R38) | GitHub CI (R14 — zatím ne); jedna cron URL pro všechny obchody (O8) |
| Import s pojistkami (R54): nula akcí je chyba i se stránkami letáku (kromě Alberta), chybí-li víc než 40 % neskončených akcí, žádná se neoznačí jako stažená (stav `partial`); Billa prodlužuje pokračující akce se stejnou cenou; souhrny po dávkách 100 uživatelů, cron každou hodinu 6:30–22:30 s okamžitým upozorněním (R58) | Stažení všeho, co v novém stažení chybí (R16 bez pojistky); každý týden nový řádek akce Billy; souhrny všem v jednom požadavku jednou denně |
| Jedno stažení obchodu najednou (zámek v cache, R57); User-Agent `Slevohlidka/1.0 (+slevohlidka.cz)` bez schématu (R65, R80) | Souběžná stažení bez zámku; UA s `https://…` (R53 bod 6) |
| Upozornění a souhrny berou akce do **horizontu úplných akcí** (před začátkem běžícího stažení) a cron je zpracovává po dávkách s **časovým rozpočtem** sdíleným mezi kroky; uživatel s opakovanou chybou se po 3 pokusech přeskočí (R106). Seznam prodejen akce až po otevření okna, „Načíst další“ jen s novou stránkou (R106) | Hranice nových akcí = čas cronu (akce stažení běžícího během cronu se neohlásily); pevné dávky bez časového limitu; seznam prodejen u každé akce; „Načíst další“ s celým načteným rozsahem |
| Upozornění e-mailem hned (nejvýš jednou za hodinu), denně nebo týdně (R58); u akce srovnání s dřívějšími akcemi stejné položky za 12 týdnů (R59) | Jen souhrn denně / týdně |
| „Hlídat“ přímo z karty ve Všech akcích, nepřihlášený přes registraci (R60); nákupní seznam po obchodech s odškrtáváním (R61); „Jsem v obchodě“ jako kompaktní řádky (R62) | Hlídání jen ze stránky Hlídám; akce v obchodě jen jako velké karty |
| Registrace a přihlášení s panelem skutečných akcí, heslo při registraci jen jednou s tlačítkem Ukázat (R56); Můj účet jako sekce pod sebou s navigací (R63); nastavení v Účtu i v Mých obchodech se ukládá hned po změně (R63, R64) | Panel s maskotem a obecnými větami; „Heslo znovu“ při registraci; mřížka karet účtu se šesti tlačítky Uložit; lišta Uložit v Mých obchodech |
| Revize před spuštěním (R67–R69): adresy vždy z `APP_URL` (`URL::forceRootUrl`), `/public/…` a lomítko na konci přesměrují (THE_REQUEST), proxy bez `X-Forwarded-Prefix`; po změně nebo obnově hesla odhlášení ostatních zařízení; hodinový limit formulářů s e-mailem; upozornění v telefonu jen ověřenému účtu a zrušení odběru při odhlášení; titulky ze serveru i ve Vue, Albert `noindex`, loga na úvodní stránce jako odkazy, `security.txt`; GA dostává adresy bez tokenů; odvolání souhlasu nechá doklad o udělení; úklid prošlé cache | Canonical a odkazy v e-mailech z adresy požadavku; měření stránek GA podle historie prohlížeče; odvolání souhlasu mazalo čas udělení |

---

## 1. Rozsah a cíl

### Co systém dělá
- Jednou až dvakrát denně stáhne aktuální akční nabídku z obchodů **Kaufland, Tesco, Albert, Lidl, Penny, Globus a Billa**, včetně příštího týdne, pokud už je zveřejněný.
- Nabídky převede do jednotného tvaru: obchod, název, balení, cena, původní cena, cena s kartou nebo aplikací, cena za jednotku, platnost od–do a typ akce ([R7](ROZHODNUTI.md), [R8](ROZHODNUTI.md)).
- Uživatel si založí účet, vybere **obchody** (u Tesca typ prodejny, akce jen z e-shopu) a věrnostní programy, které používá ([R19](ROZHODNUTI.md), [R21](ROZHODNUTI.md)).
- Uživatel zadá **hlídané položky**, buď konkrétní produkt, nebo kategorii bez ohledu na značku ([R18](ROZHODNUTI.md)).
- Zobrazí **seznam aktuálních slev** k hlídaným položkám ve sledovaných obchodech, porovnatelný podle ceny za jednotku.
- Akce, které obchod zveřejnil dopředu, ukáže zvlášť jako **„brzy“** — poradí, kdy se vyplatí počkat, a ráno v den začátku připomene ([R76](ROZHODNUTI.md)).
- Ukáže i **zmínky v letácích bez ceny** — položka je na stránce letáku, ale cenu z něj neumíme přečíst ([R27](ROZHODNUTI.md)).
- Nabídky archivuje, takže zůstává historie cen i po zmizení letáku ([R10](ROZHODNUTI.md)); u akce ukáže srovnání s dřívějšími akcemi stejné položky ([R59](ROZHODNUTI.md)).
- Pošle e-mailem nové akce na hlídané položky — hned po stažení, denně, nebo týdně ([R42](ROZHODNUTI.md), [R58](ROZHODNUTI.md)) — a upozorní na ně v telefonu ([R66](ROZHODNUTI.md)).
- Jde přidat na plochu telefonu jako aplikace; Moje slevy a nákupní seznam fungují i bez signálu ([R66](ROZHODNUTI.md)).
- V **centru upozornění** pod zvonkem v hlavičce ukáže, na co za posledních 30 dní upozornil, i když upozornění v telefonu ani e-mailem zapnutá nejsou ([R74](ROZHODNUTI.md)).
- Akce jde dát do **nákupního seznamu** po obchodech a v obchodě je odškrtávat ([R61](ROZHODNUTI.md)).

### Co systém nedělá
- Nebere data z agregátorů (kupi.cz, akcniceny.cz), jen přímo od obchodů ([R1](ROZHODNUTI.md)).
- Nepřebírá letáky ani fotky produktů. Ukládá fakta a odkaz na zdroj ([R5](ROZHODNUTI.md)).
- Neřeší nákupní košík ani objednávky — nákupní seznam je jen pro uživatele.

### Typické scénáře (z průzkumu)

| Scénář | Typ hledání | Na co dát pozor |
|---|---|---|
| Coca-Cola Zero | konkrétní produkt | Kaufland a Albert mají jen „Coca-Cola různé druhy“ → stav **možná** ([R9](ROZHODNUTI.md)); Tesco má „Super cenu“, která není slevou ([R8](ROZHODNUTI.md)) |
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
| **Lidl** | JSON v HTML kampaňových stránek `lidl.cz/c/…` (`data-grid-data`), ~140 potravin týdně + PDF letáku přes `pdftotext` (R86), ~90 akcí navíc z každého potravinového letáku — hotovo | LLM pro neověřené dlaždice letáku | web ~1/3, s letákem ~60 % cen letáku | střední |
| **Penny** | JSON API `penny.cz/api/product-discovery` + parser vektorové vrstvy letáku (R26, R85) — hotovo, ~500 akcí týdně | LLM pro neověřené dlaždice letáku | API 33 položek, s letákem ~85 % cen letáku | střední až vysoká |
| **Albert** | GraphQL `getLeaflets` + Publitas `data.json` → **PDF letáku přes `pdftotext`** (R87), ~400 akcí v hypermarketu a ~300 v supermarketu týdně; text stránek pro zmínky (R36) — hotovo | LLM pro neověřené dlaždice letáku | ~70 % cen letáku | střední |
| **Globus** | REST API webu `globus.cz/api/v1/gsoa/actionOffers` — katalog akcí hypermarketu s cenou, platností a cenou Můj Globus, popis z položek letáku (R46) — hotovo, ~650 akcí bez oblečení; API má jen akce, které už platí, **budoucí leták z PDF přes `pdftotext`** (R88), ~140 akcí na příští týden | LLM pro neověřené dlaždice letáku | platné ~100 %, budoucí ~55 % cen letáku | nízká až střední |
| **Billa** | JSON API `billa.cz/api/product-discovery` s celým katalogem (R48) — hotovo, ~3 400 akcí včetně ~370 jen s BILLA Klubem; letáky, které ještě nezačaly, z **PDF přes `pdftotext`** spárované s katalogem podle běžné ceny (R89), ~190 akcí na příští týden | LLM pro neověřené dlaždice letáku | API nemá platnost akcí — akční týden st–út; budoucí ~50 % ověřených dlaždic letáku | nízká až střední |

Ověřeno na všech obchodech: **nikde není potřeba headless prohlížeč ani obcházení
ochrany proti botům**. Stačí HTTP klient Laravelu.

### Prodejny a varianty nabídky

| Obchod | Liší se nabídka podle prodejny? | Jak |
|---|---|---|
| Kaufland | mírně: 646 akcí všude, 70 jen v některých (pultové maso, ryby…), 74 různých kombinací ze 149 prodejen (3. 10. 2026) | cookie `x-aem-variant=CZxxxx`, seznam 149 prodejen v `.klstorefinder.json`, akce prodejny `.kloffers.storeName=CZxxxx.json` (R49) |
| Tesco | podle formátu (hypermarket / supermarket) + výjimky | leták HM / SM; e-shop zvlášť ([R4](ROZHODNUTI.md)) |
| Albert | podle formátu + lokální varianty | leták HM / SM, `getLeaflets` vrací seznam prodejen letáku |
| Lidl | celostátně, „Rozšířená nabídka“ jen ve vybraných prodejnách | příznak u položky |
| Penny | celostátně | — |
| Billa | velký a malý leták podle velikosti prodejny, API jedna celostátní cena | — |
| Globus | jen krátké místní akce (Brno × Čakovice: 900 z 912 stejně) | stahuje se jeden hypermarket (4005 Čakovice) |

---

## 3. Technologie

Stejný stack jako projekt Počasí ([R2](ROZHODNUTI.md)).

| Vrstva | Volba | Poznámka |
|---|---|---|
| Jazyk | PHP 8.4 | |
| Framework | Laravel 13 | HTTP klient (Guzzle) pro scrapery |
| Frontend | Inertia + Vue 3 | |
| Styly | SCSS, BEM, design tokeny v CSS proměnných | bez Tailwindu |
| Přihlášení | Laravel Fortify + vlastní Vue stránky | [R12](ROZHODNUTI.md) |
| Databáze | MariaDB 11.4 | |
| Extrakce letáků | LLM s vision (Claude API) | etapa 6, model a rozpočet viz [O2](#7-otevřené-otázky) |
| Testy | Pest, Larastan level 8, Pint | HTTP odpovědi obchodů jako fixtures ([R11](ROZHODNUTI.md)) |
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
| `valid_from`, `valid_to` | platnost, **místní datum** ([R7](ROZHODNUTI.md)); null u průběžných akcí e-shopu |
| `fetched_at` | kdy se naposledy stáhl |

Klíč je `chain` + `kind` + `external_id` (Kaufland `nabidka-2026-09-30`, Tesco `708`, e-shop Tesco `eshop`).

### `offers`: akční nabídky
| Sloupec | Význam |
|---|---|
| `chain`, `leaflet_id` | obchod a zdroj; nabídka e-shopu Tesco, která je v letáku, patří k letáku ([R17](ROZHODNUTI.md)) |
| `store_format` | `hypermarket` / `supermarket`; null = všechny prodejny obchodu |
| prodejny (`offer_stores`) | jen u akce, která neplatí ve všech prodejnách (Kaufland, R49); bez řádků = všude |
| `scrape_run_id`, `withdrawn_at` | stažení, ve kterém se nabídka naposledy objevila; kdy ji obchod stáhl před koncem platnosti ([R16](ROZHODNUTI.md)) |
| `external_id` | ID položky u obchodu (Kaufland `klNr`, Tesco `id` produktu…) |
| `name`, `brand`, `description` | |
| `variant_note` | „různé druhy“, „vybrané druhy“ → párování „možná“ ([R9](ROZHODNUTI.md)) |
| `package_text`, `quantity`, `unit` | balení: text obchodu a rozparsované množství (g / ml / ks); nejednoznačné („250 ml/500 ml“) bez množství |
| `price`, `original_price`, `loyalty_price` | **v haléřích** ([R7](ROZHODNUTI.md)); `price` = bez karty (null, když ji obchod neuvádí), `loyalty_price` = s kartou nebo aplikací |
| `loyalty_program` | `kaufland_card` / `clubcard` / `muj_albert` / `lidl_plus` / `penny_karta` / null |
| `discount_percent` | sleva podle obchodu, jen u typu `discount` |
| `offer_type` | `discount` / `promo_price` / `loyalty_only` / `multibuy` ([R8](ROZHODNUTI.md)) |
| `promotion_text` | popis akce od obchodu („3 za cenu 2“, „Více než o polovinu nižší cena s Clubcard“) |
| `online_only` | jen e-shop Tesco ([R4](ROZHODNUTI.md)) |
| `valid_from`, `valid_to` | platnost položky (víkendové akce mají kratší než leták) |
| `source_category` | kategorie u obchodu |
| `image_url`, `source_url` | odkazy u obchodu, obrázky se nestahují ([R5](ROZHODNUTI.md)) |
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
- `users`: účty (Fortify), `password` null = účet bez hesla založený přes Google nebo Facebook (R96); `loyalty_programs` = JSON seznam karet a aplikací, které uživatel má ([R19](ROZHODNUTI.md)); předvolby Mých slev `offers_sort`, `min_discount_percent` (R41); upozornění `digest_frequency` (off / instant / daily / weekly) a `digest_sent_at` = poslední zpracování (R42, R54, R58); souhlasy `terms_*`, `marketing_consent_*` (R51); `avatar_path` (R40)
- `followed_chains`: sledované obchody — `chain`, `store_format` (null = všechny typy prodejen), `include_online_only`, `store_codes` (vybrané prodejny Kauflandu, R49) ([R19](ROZHODNUTI.md)); nový účet sleduje všechny obchody (R55)
- `watch_items`: hlídané položky — `name`, `product_id` (produkt katalogu, R31) nebo vlastní `keywords`, `variant_keywords`, `exclude_keywords` ([R18](ROZHODNUTI.md))
- `shopping_list_items`: nákupní seznam — `user_id`, `offer_id` (unikátní dvojice), `checked_at` = odškrtnuto v obchodě ([R61](ROZHODNUTI.md))
- `social_accounts`: propojené účty Google a Facebook — `user_id`, `provider`, `provider_user_id` (unikátní dvojice poskytovatel + ID i uživatel + poskytovatel) ([R96](ROZHODNUTI.md))

Shody hlídaných položek s nabídkami se neukládají, počítají se při zobrazení ([R19](ROZHODNUTI.md)).
Upozornění e-mailem pozná „novou“ akci podle `offers.created_at` a `users.digest_sent_at` (R42, R58);
tabulka `watch_matches` zatím není potřeba (TODO).

### Provoz
- `scrape_runs`: každé stažení obchodu (začátek, konec, stav, počet uložených a stažených nabídek, chyba). **Nula položek je chyba**, ne „žádné akce“ (kromě Alberta jen se zmínkami). Stav `partial`: nabídky uložené, ale chybějící akce se neoznačily jako stažené, protože jich chybělo podezřele mnoho (R54).
- `llm_extractions`: (etapa 6) vytěžené stránky letáků, aby se stránka neposílala do LLM dvakrát.

---

## 5. Toky dat

```
php artisan letaky:import-offers [obchod…]      (lokálně ručně, na produkci cron URL po obchodech — R20, R38)
   └─▶ ImportChainOffers (pro každý obchod, selhání jednoho nezastaví ostatní)
          ├─ zámek obchodu v cache; zaseknutá stažení obchodu → chyba (R57)
          ├─ zdroj obchodu (Sources/<Obchod>, SourceHttp s pauzami) ──HTTP──▶ web / API obchodu
          │     └─ převod na OfferData: cena v haléřích, balení, typ akce, místní platnost
          ├─ Billa: pokračující akce se stejnou cenou převezme začátek uložené (R54)
          ├─ upsert leaflets + offers (deduplikace podle klíče), v jedné transakci
          ├─ nula akcí (kromě Alberta jen se zmínkami) → chyba (R54)
          ├─ neskončené nabídky obchodu, které chyběly → withdrawn_at (R16);
          │     chybí-li víc než 40 % → nic, stažení „partial“ (R54)
          ├─ AssignProducts::forChain: nabídky obchodu → produkty katalogu (offer_product, R30)
          └─▶ scrape_runs (úspěch / částečné / chyba)

php artisan letaky:import-stores kaufland       (před stažením Kauflandu; akce 149 prodejen, R49)
php artisan letaky:import-categories            (občas; strom kategorií e-shopu Tesco, R28)
/katalog (admin): uložení produktu → AssignProducts::forProduct; „sem patří / nepatří“ → CorrectAssignment

php artisan letaky:send-digests                 (na produkci cron každou hodinu 6:30–22:30 — R42, R54, R58)
   └─▶ SendDigests: dávka nejvýš 100 uživatelů, kterým je čas a od jejichž posledního souhrnu
          doběhlo stažení → MyOffers bez zmínek → nové akce (created_at) → e-mail; digest_sent_at
```

Moje slevy (etapa 3) se počítají při zobrazení stránky:

```
GET / ──▶ MyOffers::forUser
             ├─ položka z katalogu (R31): nabídky z offer_product jejího produktu (sledované obchody)
             ├─ kandidáti: neskončené a nestažené nabídky sledovaných obchodů (typ prodejny,
             │  akce jen z e-shopu, vybrané prodejny R49), které obsahují nejdelší slovo některé hlídané položky (SQL LIKE, R54)
             ├─ WatchItemMatcher: všechna slova, vyloučení, varianta → shoda / možná (R18, R9)
             ├─ akce jen s kartou, kterou uživatel nemá, vynechá (R19)
             ├─ řazení: shody, pak akce s cenou od nejnižší ceny za jednotku (s kartou, pokud ji má),
             │  akce na více kusů, nakonec „možná“
             └─ zmínky bez ceny (R27): stránky neskončených letáků sledovaných obchodů (SQL LIKE),
                bez stránek s receptem, celá slova, vyloučená slova jen v okolí slova (R107), chybí varianta → „možná“;
                leták, kde má obchod k položce akci s cenou ve stejném období (i už skončenou, R107), se přeskočí
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

Produkce na Websupportu nemá scheduler ani frontu ([R20](ROZHODNUTI.md)): úlohy spouští
cron WebAdminu voláním URL s tokenem. Proto je každá úloha **Action** volatelná
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
| 6 | **LLM** (R23), jen pokud bude potřeba: neověřené dlaždice letáků (Albert, Lidl, Penny), „Super ceny“ Tesca, třídění nepřiřazených nabídek. Albert a zbytek letáku Lidlu vyřešil bez LLM `pdftotext` (R86, R87) | |
| 7 | **Nasazení na Websupport** (R20, R38): cron URL pro stahování, hlídání stažení (`/health/imports`), HTTPS a bezpečnostní hlavičky v `public/.htaccess`, SQL skripty schématu a katalogu, build balíčku, [deploy/DEPLOYMENT.md](../deploy/DEPLOYMENT.md), ověření O8 | nasazeno 2026-10-02 (první verze `c5d45d7`, aktualizace `20ef035` s R39–R48, `ae88b48` s R49 2026-10-03; další verze v řádcích 8 a 9 a v [DEPLOYMENT.md](../deploy/DEPLOYMENT.md#nasazené-verze)) |
| 8 | **Zveřejnění** (R51–R53, [ZVEREJNENI.md](ZVEREJNENI.md)): podmínky a zásady, patička, souhlasy při registraci, ověření e-mailu, odhlášení z e-mailů jedním klepnutím, české chybové stránky, cookie lišta a GA4, ochrana účtů; zbývá právní posouzení O6 a organizační body checklistu | nasazeno 2026-10-03 (`aed786f`, `b2996c0`) |
| 9 | **Kritická revize před spuštěním** (R54–R65): pojistky importu a zámek stažení, prodlužování akcí Billy, souhrny po dávkách a okamžité upozornění; první kroky po registraci, „Jsem v obchodě“ s kompaktními řádky, „Je to opravdu sleva?“, „Hlídat“ z karty, nákupní seznam, manifest; nová registrace, Můj účet a Moje obchody s ukládáním hned; User-Agent bez `https://` | nasazeno 2026-10-04 (`10072aa`, R54–R64); R65 v kódu, na produkci zatím přes `.env` |
| 10 | **Aplikace v telefonu** (R66): manifest, iPhone, spodní lišta záložek, výzva k přidání na plochu, obnovení po návratu do aplikace, nezhasínání displeje a poslání seznamu; service worker s offline režimem a odškrtáváním bez signálu; upozornění v telefonu (web push) | nasazeno 2026-10-04 (`046d8eb`) |
| 11 | **Centrum upozornění** (R74): 11a nové akce — záznamy z cronu, zvonek, stránka a detail, upozornění v telefonu ze záznamů; 11b akce ze seznamu brzy končí; 11c nejlevněji za 12 týdnů (zlevnění běžící akce ne — vzácné); 11d zprávy od nás | hotovo 2026-10-05 |
| 12 | **Akce, které ještě nezačaly** (R76): sekce Brzy v Mých slevách, „Vyplatí se počkat“, štítek „Od čt 8. 10.“, filtr „Brzy začnou“ ve Všech akcích, nákupní seznam, upozornění „Od dneška platí…“ | hotovo 2026-10-05 |

---

## 7. Otevřené otázky

| # | Otázka | Stav |
|---|---|---|
| O1 | **Kde poběží produkce?** Shared hosting jako Počasí (Websupport: bez SSH, fronty a scheduleru, cron umí jen volat URL), nebo VPS? | rozhodnuto (R20): Websupport |
| O2 | **LLM pro extrakci letáků:** je potřeba a jde na shared hostingu? | rozhodnuto (R23): zatím bez LLM; jde to (jen volání API). Albert ani zbytek Lidlu LLM nepotřebují — PDF přes `pdftotext` (R86, R87) |
| O3 | **Kategorie:** jak párovat „polotučné mléko“, když obchod píše jen „tuk 1,5 %“, a jak nehlídat stejná pravidla u každého uživatele zvlášť? Návrh: sdílený katalog produktů se štítky a pravidly, nabídky se k produktům přiřadí automaticky při importu — viz *Návrh katalogu produktů* níž | rozhodnuto (R24): katalog produktů, etapa 5 |
| O4 | **Seznamy prodejen** Tesco, Lidl a Penny: odkud je brát | rozhodnuto (R21): nejsou potřeba, výběr prodejen i jejich seznam zrušené |
| O5 | **„Různé druhy“:** jde konkrétní variantu dohledat? Hotspoty letáku Tesco obsahují jednotlivé varianty (COCA-COLA ZERO 1,5l), Albert má katalog `productSearch`. U Kauflandu a Penny zřejmě ne | zatím stačí stav „Možná“ (R18); zpřesnění v [TODO.md](TODO.md) |
| O6 | **Zveřejnění aplikace:** před zpřístupněním dalším lidem právně posoudit. Podmínky Tesco výslovně zakazují užití obsahu pro jinou než osobní potřebu, VOP Albert zakazují stahování obsahu e-shopu a aplikace; dále autorský zákon a právo pořizovatele databáze. Viz [R5](ROZHODNUTI.md) — technická a GDPR část v R51 a [ZVEREJNENI.md](ZVEREJNENI.md), zbývá právo k obsahu obchodů. Rozbor 3. 10. 2026 (kap. 5 ZVEREJNENI.md): ceny jsou fakta, právo pořizovatele databáze sporné (obchod ceny vytváří, C-203/02; C-762/19 vyžaduje újmu pořizovateli), střední riziko fotky přes hotlink a podmínky Tesca/Alberta; Kupi.cz má souhlas obchodů (placená propagace), my ne. Nejhorší reálný případ výzva k ukončení, riziko roste s monetizací — advokát před monetizací | odloženo do zveřejnění |
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

Log rozhodnutí R1–R… je v samostatném dokumentu [ROZHODNUTI.md](ROZHODNUTI.md) (R106) — nepřepisuje se,
nové rozhodnutí dostane další volné číslo. Co z něj dnes platí, shrnuje tabulka *Co platí a co ne*
na začátku tohoto dokumentu.
