<!--
  Zásady zpracování osobních údajů — stránka /ochrana-udaju (R51, App\Support\Legal\LegalDocuments)
  @author Roman Hlaváček
  @created 2026-10-03

  Text musí odpovídat skutečnosti v kódu — při změně ukládaných údajů, cookies nebo
  příjemců upravit i tento text. Údaje provozovatele doplní {operator}, {company_id},
  {address}, {trade_office}, {email} z config/letaky.php. Nadpis stránky dodá Legal.vue,
  sem nepatří. Psáno za provozovatele v 1. osobě množného čísla („my“) a genderově
  neutrálně (R72). Návrh, ne právní rada; podklady v docs/ZVEREJNENI.md.
-->

Tyto zásady vysvětlují, jaké osobní údaje Slevohlídka zpracovává, proč, jak dlouho
a jaká máte práva. Slevohlídka je webová aplikace, která sleduje akční nabídky
obchodů a ukazuje, kde a za kolik jsou ve slevě položky, které hlídáte.

## 1. Kdo vaše údaje zpracovává

Správcem osobních údajů je {operator}, IČO {company_id}, se sídlem {address}, fyzická
osoba zapsaná v živnostenském rejstříku ({trade_office}), provozovatel Slevohlídky
(dále „my“). Kontakt: {email}

Pověřence pro ochranu osobních údajů jsme nejmenovali, zákon ho pro takto malé zpracování
nevyžaduje. Se vším, co se týká vašich údajů, se obracejte na uvedený e-mail.

## 2. Jaké údaje zpracováváme a proč

### Uživatelský účet

| Údaje | Účel | Právní základ |
|---|---|---|
| jméno, e-mail, heslo (uložené jen jako nevratný otisk) | vedení účtu, přihlášení, ověření e-mailu, obnova hesla | plnění smlouvy, tedy podmínek užití (čl. 6 odst. 1 písm. b GDPR) |
| identifikátor účtu u Googlu nebo Facebooku (jen když se přes něj přihlašujete) | přihlášení přes Google nebo Facebook, potvrzení, že jste to vy, před změnou e-mailu nebo zrušením účtu | plnění smlouvy |
| profilový obrázek (nepovinný) | zobrazení ve vašem účtu | plnění smlouvy |
| hlídané položky, nákupní seznam, vybrané obchody a prodejny, věrnostní programy, které máte (jen název programu, ne číslo karty), předvolby zobrazení | zobrazení slev, které vás zajímají | plnění smlouvy |
| čas přijetí podmínek a jejich verze | doložení, s jakými podmínkami jste souhlasili | oprávněný zájem (čl. 6 odst. 1 písm. f GDPR) |

Výběr prodejen může prozradit, kde přibližně nakupujete. Slouží jen k zobrazení akcí
těchto prodejen.

### Přihlášení přes Google nebo Facebook

Účet si můžete založit a přihlašovat se do něj i přes svůj účet u Googlu nebo Facebooku.
Přihlásíte se na jejich stránce a oni nám s vaším svolením předají jen tyto údaje:

| Údaje od Googlu nebo Facebooku | K čemu je používáme | Právní základ |
|---|---|---|
| identifikátor vašeho účtu u Googlu nebo Facebooku | abychom vás při příštím přihlášení poznali a abyste před změnou e-mailu nebo zrušením účtu mohli potvrdit, že jste to vy | plnění smlouvy |
| jméno | oslovení ve Slevohlídce (při registraci ho můžete změnit) | plnění smlouvy |
| e-mailová adresa | adresa vašeho účtu: přihlášení, upozornění na akce, které si zapnete, a obnova hesla | plnění smlouvy |

Žádáme jen o základní oprávnění (u Googlu `openid`, `email` a `profile`, u Facebooku
`public_profile` a `email`). Heslo k vašemu účtu u Googlu nebo Facebooku k nám nikdy
nedorazí. Profilový obrázek, kontakty, přátele, e-maily, kalendář ani žádná jiná data
z vašeho účtu nedostáváme a nežádáme o ně.

Údaje od Googlu a Facebooku **nikomu nepředáváme, neprodáváme a nepoužíváme k reklamě**,
k profilování ani k trénování modelů umělé inteligence. Ukládáme je na našich serverech
v EU u ostatních údajů vašeho účtu (kap. 7 Zabezpečení) a uchováváme je, dokud propojení
nezrušíte (Můj účet → Zabezpečení) nebo nezrušíte účet — pak je okamžitě smažeme. Přístup
Slevohlídky můžete kdykoli odebrat i u poskytovatele: v nastavení účtu Google (Zabezpečení →
Aplikace a služby třetích stran) nebo na Facebooku (Nastavení → Aplikace a weby).

Využití údajů, které Slevohlídka získá z rozhraní Google API, se řídí
[Zásadami pro uživatelská data služeb Google API](https://developers.google.com/terms/api-services-user-data-policy)
(Google API Services User Data Policy), včetně požadavků na omezené použití (Limited Use).

### E-mailový souhrn akcí

Pokud si v účtu zapnete souhrn, posíláme vám e-mailem nové akce na hlídané položky —
hned, jak přibudou (nejvýš jednou za hodinu), denně nebo týdně. Souhrn je součást služby,
o kterou jste požádali, právní základ je plnění smlouvy. Chodí jen na ověřenou adresu.
Vypnete ho v účtu nebo jedním klepnutím v patičce každého souhrnu.

### Upozornění v telefonu

Pokud si v účtu zapnete upozornění na svém zařízení, váš prohlížeč vytvoří u své push
služby odběr a my si uložíme jeho adresu, šifrovací klíče a název zařízení (např.
„Chrome · Android“, odvozený z identifikace prohlížeče). Na odběr pak posíláme upozornění
na nové akce hlídaných položek. Obsah upozornění je šifrovaný, push služba ho nepřečte.
Upozornění jsou součást služby, o kterou jste požádali, právní základ je plnění smlouvy.
Chodí jen na účet s ověřenou adresou. Vypnete je v účtu nebo v nastavení prohlížeče
či telefonu; když se na zařízení odhlásíte, odběr se zruší.

### Centrum upozornění

Po každém stažení letáků si zapíšeme, které nové akce na hlídané zboží jsme pro vás našli
(názvy hlídaných položek a odkazy na akce), ráno akce, na které čekáte a které ten den
začínají, odpoledne také akce z vašeho nákupního seznamu, které zítra končí, a zprávy od nás o službě (například nový obchod nebo změna podmínek),
a kdy jste si záznam přečetli. Záznamy vidíte
v sekci Upozornění pod zvonkem, i když upozornění v telefonu ani e-mailem zapnutá nemáte.
Jsou součást služby, právní základ je plnění smlouvy.

### Obchodní sdělení (jen se souhlasem)

Pokud k tomu dáte samostatný souhlas, posíláme vám e-mailem novinky o Slevohlídce
a vybrané nabídky partnerů, například obchodů a e-shopů. Váš e-mail partnerům
nepředáváme, sdělení posíláme sami. Se souhlasem je ukážeme i v centru upozornění,
do telefonu je neposíláme.

- Právní základ je váš souhlas (čl. 6 odst. 1 písm. a GDPR, § 7 zákona č. 480/2004 Sb.).
- Souhlas je dobrovolný, službu můžete používat i bez něj.
- Souhlas můžete kdykoli odvolat v účtu nebo odkazem v každém obchodním sdělení.
  Odvolání nemá vliv na zpracování před ním.
- Ukládáme čas udělení a odvolání souhlasu a verzi textu, se kterým jste souhlasili.

### Bezpečnost a provoz

| Údaje | Účel | Právní základ |
|---|---|---|
| IP adresa a identifikace prohlížeče u relace (session) | udržení přihlášení, přehled přihlášených zařízení ve vašem účtu | plnění smlouvy, oprávněný zájem na zabezpečení |
| IP adresa, u přihlášení i e-mail, v omezení počtu požadavků | ochrana proti zneužití (hádání hesel, přetížení, rozesílání e-mailů na cizí adresy) | oprávněný zájem na zabezpečení |
| čas poslední aktivity v účtu (s přesností na minutu) | přehled o tom, kolik lidí službu používá, a správa účtů | oprávněný zájem na provozu služby |
| záznamy o chybách aplikace | oprava chyb | oprávněný zájem na provozu služby |

Relace s IP adresou vzniká i u nepřihlášeného návštěvníka, bez ní nefunguje ochrana
formulářů.

### Když nám napíšete nebo zavoláte

| Údaje | Účel | Právní základ |
|---|---|---|
| e-mail nebo telefonní číslo a obsah zprávy či hovoru (dotaz, nahlášení chyby, žádost obchodu o stažení obsahu) | vyřízení dotazu nebo žádosti | oprávněný zájem odpovědět na vaši zprávu |
| e-mail a obsah žádosti o uplatnění práv podle GDPR (kap. 6) | vyřízení žádosti a doložení, jak jsme ji vyřídili | právní povinnost (čl. 6 odst. 1 písm. c GDPR) |

### Měření návštěvnosti (jen se souhlasem)

Pokud v liště cookies povolíte analytické cookies, měříme návštěvnost přes **Google
Analytics 4**: které stránky se zobrazují, odkud návštěvník přišel, typ zařízení
a prohlížeče, přibližné místo podle IP adresy a náhodný identifikátor prohlížeče
v cookies. Statistiky nám pomáhají Slevohlídku zlepšovat. Bez souhlasu se Google
Analytics vůbec nenačte a na Google se nic neposílá. U stránek, jejichž adresa obsahuje
váš e-mail nebo bezpečnostní kód (odkaz pro obnovu hesla, ověření e-mailu, odhlášení
z e-mailů), posíláme jen začátek adresy bez těchto údajů.

Pokud povolíte i marketingové cookies, smí Google data z návštěvy použít pro měření
a cílení reklamy (Google signály). Reklamu zatím nezobrazujeme.

- Právní základ je váš souhlas (čl. 6 odst. 1 písm. a GDPR, § 89 odst. 3 zákona
  č. 127/2005 Sb.).
- Souhlas změníte nebo odvoláte kdykoli odkazem **Nastavení cookies** v patičce webu.
  Po odvolání se cookies Google Analytics smažou.

Údaje nepoužíváme k profilování ani automatizovanému rozhodování.

## 3. Jak dlouho údaje uchováváme

| Údaje | Doba |
|---|---|
| účet a vše, co k němu patří (hlídané položky, nákupní seznam, obchody, předvolby, obrázek, souhlasy, čas poslední aktivity, propojení s Googlem nebo Facebookem) | do zrušení účtu; propojení s Googlem nebo Facebookem do jeho zrušení |
| záznamy v centru upozornění | {notifications_retention_days} dní, potom se smažou nejpozději do 24 hodin |
| odběr upozornění v telefonu | do vypnutí upozornění, odhlášení na zařízení nebo zrušení účtu; odběr, který push služba přestane přijímat, se smaže při dalším upozornění |
| relace (IP adresa, prohlížeč) | 2 hodiny od poslední aktivity, potom se smaže nejpozději do 24 hodin |
| přihlášení „Zapamatovat si mě“ | nejdéle 400 dní nebo do odhlášení |
| odkaz pro obnovu hesla | platí 60 minut, záznam se smaže nejpozději do 24 hodin po vypršení |
| omezení počtu požadavků | nejvýš hodinu, potom se smaže nejpozději do 24 hodin |
| záznamy o chybách | 14 dní |
| volba cookies | 6 měsíců, pak se vás zeptáme znovu |
| data Google Analytics | 14 měsíců (nastavení uchování v Google Analytics) |
| e-mailová a telefonická komunikace | po dobu vyřízení, potom nejdéle 3 roky kvůli případným nárokům; žádosti podle GDPR 3 roky od vyřízení |
| zálohy databáze | nejdéle 6 měsíců, potom se mažou |

Účet zrušíte sami v sekci Můj účet. Smaže se okamžitě se vším, co k němu patří.
V zálohách údaje zůstanou nejdéle do smazání zálohy.

## 4. Kdo k údajům má přístup

- **Websupport s.r.o.** zajišťuje hosting aplikace, databáze a odesílání e-mailů.
  Zpracovává údaje jako zpracovatel podle našich pokynů na serverech v EU. Jeho servery
  také vedou běžné záznamy o přístupech (IP adresa, čas, adresa stránky).
- **Obchody, jejichž akce zobrazujeme** (Albert, Billa, Globus, Kaufland, Lidl, Penny,
  Tesco): obrázky produktů a stránek letáků se načítají přímo ze serverů obchodů nebo
  jejich poskytovatelů (například sítí pro doručování obsahu, CDN). Váš prohlížeč jim
  přitom sdělí vaši IP adresu a údaje o prohlížeči, ne adresu stránky Slevohlídky.
  Zpracování na jejich straně se řídí jejich zásadami.
- **Push služby prohlížečů** — jen pokud zapnete upozornění v telefonu: služba výrobce
  vašeho prohlížeče (Google pro Chrome a Android, Apple pro Safari a iPhone, Mozilla pro
  Firefox, Microsoft pro Edge ve Windows) upozornění doručí do zařízení. Dostane adresu
  odběru a zašifrovaný obsah, který nepřečte; její servery mohou být i mimo EU.
- **Google Ireland Limited** (Gordon House, Barrow Street, Dublin 4, Irsko) — jen pokud
  povolíte analytické nebo marketingové cookies: měření návštěvnosti Google Analytics.
  Google může údaje předávat do USA; předání se opírá o rámec EU–USA pro ochranu
  osobních údajů (Data Privacy Framework), ke kterému se Google LLC přihlásila.
- **Google Ireland Limited** a **Meta Platforms Ireland Limited** (Merrion Road, Dublin 4,
  Irsko) — jen pokud se přes Google nebo Facebook přihlašujete: přihlášení proběhne na
  jejich stránce a dozvědí se, že se přihlašujete do Slevohlídky. Jsou to samostatní
  správci, zpracování na jejich straně se řídí jejich zásadami a jejich cookies; údaje
  mohou předávat do USA v rámci Data Privacy Framework.

Údaje neprodáváme ani nepředáváme za úplatu. Kromě Google Analytics (se souhlasem)
a push služby vašeho prohlížeče (jen se zapnutými upozorněními v telefonu) je nepředáváme
mimo EU. Orgánům veřejné moci je poskytneme jen tehdy, když to ukládá zákon.

## 5. Cookies a úložiště v prohlížeči

Při první návštěvě se vás lišta zeptá, které cookies smíme použít. **Nezbytné** cookies
a úložiště pro vaše nastavení fungují vždy, souhlas k nim zákon nevyžaduje (§ 89 odst. 3
zákona č. 127/2005 Sb.). **Analytické** a **marketingové** jen s vaším souhlasem.
Volbu změníte kdykoli odkazem **Nastavení cookies** v patičce webu; odmítnout jde stejně
snadno jako přijmout.

**Nezbytné (vždy)**

| Název | Typ | K čemu slouží | Platnost |
|---|---|---|---|
| `slevohlidka-session` | cookie | udržení přihlášení a relace | 2 hodiny |
| `XSRF-TOKEN` | cookie | ochrana formulářů proti podvržení | 2 hodiny |
| `remember_web_…` | cookie | „Zapamatovat si mě“, jen když ho zaškrtnete | 400 dní |
| `slevohlidka-consent` | cookie | vaše volba cookies | 6 měsíců |
| `letaky-theme` | localStorage | zvolený světlý nebo tmavý vzhled | do smazání v prohlížeči |
| `slevohlidka.home.expanded` | localStorage | které skupiny v Mých slevách máte rozbalené | do smazání v prohlížeči |
| `slevohlidka.home.rows` | localStorage | v Mých slevách po výběru obchodu akce jako řádky, nebo karty | do smazání v prohlížeči |
| `slevohlidka.view.compact` | localStorage | ve Všech akcích a Mých slevách akce jako karty, nebo kompaktní řádky | do smazání v prohlížeči |
| `slevohlidka.search.recent` | localStorage | posledních 5 hledání ve Všech akcích, abyste je měli po ruce | do smazání v hledání nebo odhlášení |
| `slevohlidka.install.dismissed_at` | localStorage | kdy jste zavřeli výzvu k přidání Slevohlídky na plochu (30 dní se neukáže) | do smazání v prohlížeči |
| `slevohlidka.shopping.wake_lock` | localStorage | v nákupním seznamu nezhasínat displej | do smazání v prohlížeči |
| `slevohlidka.shopping.pending` | localStorage | odškrtnutí v nákupním seznamu udělaná bez signálu, než se odešlou | do odeslání nebo odhlášení |
| `slevohlidka-static-…` | úložiště aplikace (Cache Storage) | soubory webu, aby se aplikace v telefonu načetla rychle a bez signálu | do další verze webu |
| `slevohlidka-pages` | úložiště aplikace (Cache Storage) | poslední verze Mých slev, nákupního seznamu a Hlídám pro použití bez signálu | do odhlášení |

**Analytické (se souhlasem)** — Google Analytics 4

| Název | Typ | K čemu slouží | Platnost |
|---|---|---|---|
| `_ga` | cookie | rozlišení návštěvníků (náhodný identifikátor) | 2 roky |
| `_ga_<ID>` | cookie | udržení stavu návštěvy | 2 roky |

**Marketingové (se souhlasem)** — vlastní cookies nezakládají; Googlu dovolí použít data
z Google Analytics pro měření a cílení reklamy.

Cookies i úložiště můžete v prohlížeči také smazat nebo zablokovat. Bez nezbytných
cookies se ale nepřihlásíte.

## 6. Vaše práva

Máte právo:

- na **přístup** k údajům, tedy vědět, co o vás zpracováváme,
- na **opravu**: jméno a e-mail změníte sami v účtu,
- na **výmaz**: účet zrušíte sami v účtu, nebo nám napište,
- na **omezení zpracování**,
- na **přenositelnost**: údaje vám pošleme ve strojově čitelném formátu (JSON),
- **vznést námitku** proti zpracování z oprávněného zájmu,
- **odvolat souhlas** s obchodními sděleními,
- podat **stížnost** u Úřadu pro ochranu osobních údajů (www.uoou.gov.cz,
  Pplk. Sochora 27, 170 00 Praha 7).

Žádosti posílejte na {email} z adresy, na kterou je účet založený. Vyřídíme je
nejpozději do měsíce.

## 7. Zabezpečení

Komunikace je šifrovaná (HTTPS), hesla jsou uložená jen jako otisk (bcrypt), profilový
obrázek vidíte jen vy. Přihlášení a citlivé formuláře mají omezený počet pokusů.

Nové heslo ověřujeme proti databázi uniklých hesel služby Have I Been Pwned. Služba dostane
jen prvních pět znaků otisku hesla, ne heslo ani váš e-mail, takže z dotazu nic nezjistí.

## 8. Věk

Služba je určena osobám od 15 let.

## 9. Změny zásad

Zásady můžeme změnit, například když přibude nová funkce. Podstatnou změnu oznámíme
předem e-mailem. Předchozí verze vám na požádání pošleme.
