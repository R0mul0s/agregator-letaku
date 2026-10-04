<!--
  Zásady zpracování osobních údajů — stránka /ochrana-udaju (R51, App\Support\Legal\LegalDocuments)
  @author Roman Hlaváček
  @created 2026-10-03

  Text musí odpovídat skutečnosti v kódu — při změně ukládaných údajů, cookies nebo
  příjemců upravit i tento text. Údaje provozovatele doplní {operator}, {company_id},
  {address}, {email} z config/letaky.php. Nadpis stránky dodá Legal.vue, sem nepatří.
  Návrh, ne právní rada; podklady v docs/ZVEREJNENI.md.
-->

Tyto zásady vysvětlují, jaké osobní údaje Slevohlídka zpracovává, proč, jak dlouho
a jaká máte práva. Slevohlídka je webová aplikace, která sleduje akční nabídky
obchodů a ukazuje, kde a za kolik jsou ve slevě položky, které hlídáte.

## 1. Kdo vaše údaje zpracovává

Správcem je {operator}, IČO {company_id}, se sídlem {address}, zapsaný v živnostenském
rejstříku. Kontakt: {email}

Pověřence pro ochranu osobních údajů nemám, zákon ho pro takto malé zpracování
nevyžaduje. Se vším, co se týká vašich údajů, se obracejte na uvedený e-mail.

## 2. Jaké údaje zpracovávám a proč

### Uživatelský účet

| Údaje | Účel | Právní základ |
|---|---|---|
| jméno, e-mail, heslo (uložené jen jako nevratný otisk) | vedení účtu, přihlášení, ověření e-mailu, obnova hesla | plnění smlouvy, tedy podmínek užití (čl. 6 odst. 1 písm. b GDPR) |
| profilový obrázek (nepovinný) | zobrazení ve vašem účtu | plnění smlouvy |
| hlídané položky, nákupní seznam, vybrané obchody a prodejny, věrnostní programy, které máte (jen název programu, ne číslo karty), předvolby zobrazení | zobrazení slev, které vás zajímají | plnění smlouvy |
| čas přijetí podmínek a jejich verze | doložení, s jakými podmínkami jste souhlasili | oprávněný zájem (čl. 6 odst. 1 písm. f GDPR) |

Výběr prodejen může prozradit, kde přibližně nakupujete. Slouží jen k zobrazení akcí
těchto prodejen.

### E-mailový souhrn akcí

Pokud si v účtu zapnete souhrn, posílám vám e-mailem nové akce na hlídané položky —
hned, jak přibudou (nejvýš jednou za hodinu), denně nebo týdně. Souhrn je součást služby,
o kterou jste požádali, právní základ je plnění smlouvy. Chodí jen na ověřenou adresu.
Vypnete ho v účtu nebo jedním klepnutím v patičce každého souhrnu.

### Obchodní sdělení (jen se souhlasem)

Pokud k tomu dáte samostatný souhlas, posílám vám e-mailem novinky o Slevohlídce
a vybrané nabídky partnerů, například obchodů a e-shopů. Váš e-mail partnerům
nepředávám, sdělení posílám já.

- Právní základ je váš souhlas (čl. 6 odst. 1 písm. a GDPR, § 7 zákona č. 480/2004 Sb.).
- Souhlas je dobrovolný, službu můžete používat i bez něj.
- Souhlas můžete kdykoli odvolat v účtu nebo odkazem v každém obchodním sdělení.
  Odvolání nemá vliv na zpracování před ním.
- Ukládám čas udělení a odvolání souhlasu a verzi textu, se kterým jste souhlasili.

### Bezpečnost a provoz

| Údaje | Účel | Právní základ |
|---|---|---|
| IP adresa a identifikace prohlížeče u relace (session) | udržení přihlášení, přehled přihlášených zařízení ve vašem účtu | plnění smlouvy, oprávněný zájem na zabezpečení |
| IP adresa v omezení počtu požadavků | ochrana proti zneužití (hádání hesel, přetížení) | oprávněný zájem na zabezpečení |
| záznamy o chybách aplikace | oprava chyb | oprávněný zájem na provozu služby |

Relace s IP adresou vzniká i u nepřihlášeného návštěvníka, bez ní nefunguje ochrana
formulářů.

### Měření návštěvnosti (jen se souhlasem)

Pokud v liště cookies povolíte analytické cookies, měřím návštěvnost přes **Google
Analytics 4**: které stránky se zobrazují, odkud návštěvník přišel, typ zařízení
a prohlížeče, přibližné místo podle IP adresy a náhodný identifikátor prohlížeče
v cookies. Statistiky mi pomáhají Slevohlídku zlepšovat. Bez souhlasu se Google
Analytics vůbec nenačte a na Google se nic neposílá.

Pokud povolíte i marketingové cookies, smí Google data z návštěvy použít pro měření
a cílení reklamy (Google signály). Reklamu zatím nezobrazuji.

- Právní základ je váš souhlas (čl. 6 odst. 1 písm. a GDPR, § 89 odst. 3 zákona
  č. 127/2005 Sb.).
- Souhlas změníte nebo odvoláte kdykoli odkazem **Nastavení cookies** v patičce webu.
  Po odvolání se cookies Google Analytics smažou.

Údaje nepoužívám k profilování ani automatizovanému rozhodování.

## 3. Jak dlouho údaje uchovávám

| Údaje | Doba |
|---|---|
| účet a vše, co k němu patří (hlídané položky, nákupní seznam, obchody, předvolby, obrázek, souhlasy) | do zrušení účtu |
| relace (IP adresa, prohlížeč) | 2 hodiny od poslední aktivity, potom se průběžně maže |
| přihlášení „Zapamatovat si mě“ | nejdéle 400 dní nebo do odhlášení |
| odkaz pro obnovu hesla | 60 minut |
| omezení počtu požadavků | několik minut |
| záznamy o chybách | 14 dní |
| volba cookies | 6 měsíců, pak se vás zeptám znovu |
| data Google Analytics | 14 měsíců (nastavení uchování v Google Analytics) |
| zálohy databáze | nejdéle 6 měsíců, pak se přepíšou |

Účet zrušíte sami v sekci Účet. Smaže se okamžitě se vším, co k němu patří.
V zálohách údaje zůstanou nejdéle do jejich přepsání.

## 4. Kdo k údajům má přístup

- **Websupport s.r.o.** zajišťuje hosting aplikace, databáze a odesílání e-mailů.
  Zpracovává údaje jako zpracovatel podle mých pokynů na serverech v EU. Jeho servery
  také vedou běžné záznamy o přístupech (IP adresa, čas, adresa stránky).
- **Obchody, jejichž akce zobrazuji** (Albert, Billa, Globus, Kaufland, Lidl, Penny,
  Tesco): obrázky produktů a stránek letáků se načítají přímo z jejich serverů. Váš
  prohlížeč jim přitom sdělí vaši IP adresu a údaje o prohlížeči, ne adresu stránky
  Slevohlídky. Zpracování na jejich straně se řídí jejich zásadami.
- **Google Ireland Limited** (Gordon House, Barrow Street, Dublin 4, Irsko) — jen pokud
  povolíte analytické nebo marketingové cookies: měření návštěvnosti Google Analytics.
  Google může údaje předávat do USA; předání se opírá o rámec EU–USA pro ochranu
  osobních údajů (Data Privacy Framework), ke kterému se Google LLC přihlásila.

Údaje neprodávám ani nepředávám za úplatu. Kromě Google Analytics (se souhlasem)
je nepředávám mimo EU. Orgánům veřejné moci je poskytnu jen tehdy, když to ukládá zákon.

## 5. Cookies a úložiště v prohlížeči

Při první návštěvě se vás lišta zeptá, které cookies smím použít. **Nezbytné** cookies
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

- na **přístup** k údajům, tedy vědět, co o vás zpracovávám,
- na **opravu**: jméno a e-mail změníte sami v účtu,
- na **výmaz**: účet zrušíte sami v účtu, nebo mi napište,
- na **omezení zpracování**,
- na **přenositelnost**: údaje vám pošlu ve strojově čitelném formátu (JSON),
- **vznést námitku** proti zpracování z oprávněného zájmu,
- **odvolat souhlas** s obchodními sděleními,
- podat **stížnost** u Úřadu pro ochranu osobních údajů (www.uoou.gov.cz,
  Pplk. Sochora 27, 170 00 Praha 7).

Žádosti posílejte na {email} z adresy, na kterou je účet založený. Vyřídím je
nejpozději do měsíce.

## 7. Zabezpečení

Komunikace je šifrovaná (HTTPS), hesla jsou uložená jen jako otisk (bcrypt), profilový
obrázek vidíte jen vy. Přihlášení a citlivé formuláře mají omezený počet pokusů.

Nové heslo ověřuji proti databázi uniklých hesel služby Have I Been Pwned. Služba dostane
jen prvních pět znaků otisku hesla, ne heslo ani váš e-mail, takže z dotazu nic nezjistí.

## 8. Věk

Služba je určena osobám od 15 let.

## 9. Změny zásad

Zásady mohu změnit, například když přibude nová funkce. Podstatnou změnu oznámím
předem e-mailem nebo při přihlášení. Předchozí verze vám na požádání pošlu.
