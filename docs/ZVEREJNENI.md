<!--
  Zveřejnění Slevohlídky — co je potřeba udělat před spuštěním pro cizí uživatele
  @author Roman Hlaváček
  @created 2026-10-03
-->

# Zveřejnění: checklist

Slevohlídka byla dělaná pro vlastní použití (R5). Tenhle dokument sepisuje, co
chybí ke spuštění pro veřejnost. Vychází z průzkumu kódu ze 3. 10. 2026 a kritické
revize ze 4. 10. 2026. Technická a GDPR část je hotová v [R51](ROZHODNUTI.md):
podmínky a zásady (`resources/legal`), patička, souhlasy při registraci, ověření e-mailu,
odhlášení z e-mailů jedním klepnutím, české chybové stránky; ochrana účtů a úklid
v [R53](ROZHODNUTI.md); opravy a funkce z revize (souhrny po dávkách, pojistky
importu, heslo při změně e-mailu, první kroky po registraci, nákupní seznam…)
v [R54–R65](ROZHODNUTI.md); zabezpečení, SEO a soukromí z revize připravenosti
(adresy bez `/public`, odhlášení zařízení po změně hesla, limit e-mailů, `security.txt`, GA bez
tokenů, datum účinnosti textů…) v [R67–R69](ROZHODNUTI.md); tón webu a stránka Kontakt (R72),
přehled uživatelů (R84), úvodní stránka, kontakt a patička pro veřejnost (R90–R92) a postup
přestěhování na `slevohlidka.cz` (R93, [DEPLOYMENT.md](../deploy/DEPLOYMENT.md#přestěhování-na-slevohlidkacz-r93)).
Revize checklistu 6. 10. 2026: úložiště v prohlížeči i kategorie cookies sedí se zásadami.
Hotové body se odsud mažou a popisují v [PLAN.md](PLAN.md); technické nápady z revize jsou
v [TODO.md](TODO.md#provoz-a-údržba).

Značení: **[R]** = rozhodne nebo zařídí Roman, **[K]** = kód.

## 1. Organizační a právní (před spuštěním)

- [ ] **[R] O6 — smíme akce obchodů veřejně ukazovat?** Největší riziko spuštění.
  Rozbor a podklady pro advokáta jsou v [kap. 5](#5-právo-k-obsahu-obchodů-o6--podklady-pro-advokáta).
  Konzultace s advokátem (IT/autorské právo) **před** spuštěním, nejpozději před monetizací.
- [ ] **[R] Obory živnosti:** pokrývá živnostenský list provoz webového portálu a reklamu?
  (volná živnost, obory „Poskytování software… a webové portály“ a „Reklamní činnost,
  marketing…“) — doplnění oboru je ohlášení v RŽP.
- [x] **[R] Přestěhování na `slevohlidka.cz`** (R93) — hotovo v 19. nasazení (`6d328eb`). Doména i hosting byly připravené, předtím na nich
  běžela stránka „Brzy spouštíme“ (R79); kontaktní e-mail a User-Agent už jsou na nové doméně (R80).
  Postup krok za krokem v [DEPLOYMENT.md](../deploy/DEPLOYMENT.md#přestěhování-na-slevohlidkacz-r93):
  kontrola hostingu `hosting-check.php`, celá databáze (v ní se nic nemění), `.env` se stejným
  `APP_KEY` a klíči VAPID, cron přepnout (ne zdvojit), stará subdoména přesměruje 301,
  Search Console *Změna adresy*. **SPF, DKIM (selektor `mail`) a DMARC pro `slevohlidka.cz`
  v DNS jsou** (ověřeno 6. 10. 2026) — po přesunu poslat zkušební e-mail na mail-tester.com.
- [ ] **[R] Zpracovatelská smlouva s Websupportem** — ověřit, že je součástí jejich VOP,
  a přesný název společnosti v zásadách (`resources/legal/privacy.md`, kap. 4).
- [ ] **[R] Záznamy o činnostech zpracování** (čl. 30 GDPR) — jednostránkový interní
  dokument. Lze vzít tabulky z kap. 2 zásad.
- [ ] **[R] Přečíst a schválit** `resources/legal/terms.md` a `privacy.md` — hlavně zálohy
  „nejdéle 6 měsíců“ (postup mazání je v DEPLOYMENT.md). Doba uchování e-mailové komunikace
  3 roky (obecná promlčecí lhůta) schválena 4. 10. 2026. `letaky.legal.effective_from` je
  2026-10-04 (R69) — při změně textů ho posunout na den nasazení.
- [ ] **[R] Google Analytics — nastavení služby** (R52): Správce → Uchovávání dat na **14 měsíců**
  (zásady to tak uvádějí, výchozí jsou 2 měsíce); přijmout dodatek o zpracování dat (Správce →
  Nastavení účtu); Google signály zapnout jen pokud bude reklama. Měření změn historie
  prohlížeče v rozšířeném měření je vypnuté (R69, hotovo 4. 10. 2026). Po nasazení ověřit v Realtime,
  že měření běží až po „Přijmout“ a že po „Přijmout vše“ nevznikají jiné cookies než `_ga`, `_ga_<ID>`.
- [ ] **[R] `www.slevohlidka.rhsoft.cz`** odpovídá s certifikátem jiné domény (chyba TLS, ověřeno
  znovu 6. 10. 2026) — při přestěhování smazat záznam DNS `www` subdomény.
- [ ] **[R]+[K] Nařízení o digitálních službách (DSA, 2022/2065).** Uživatelé u nás ukládají vlastní
  obsah (názvy hlídaných položek, profilový obrázek), služba je tedy technicky hostingem. I malého
  provozovatele se týká: jednotné kontaktní místo pro úřady a uživatele s uvedeným jazykem (čl. 11,
  12), v podmínkách popsat, jaký obsah omezujeme a jak (čl. 14), a způsob oznámení nezákonného obsahu
  (čl. 16). Prakticky pár vět do `resources/legal/terms.md` (kontakt `info@slevohlidka.cz`, čeština,
  oznámení e-mailem) — ověřit s advokátem (kap. 5, otázka 7), změna podmínek = zvýšit
  `letaky.legal.terms_version`.
- [ ] **[R] Název „Slevohlídka“:** rešerše v rejstřících ochranných známek
  ([ÚPV](https://isdv.upv.gov.cz), [EUIPO eSearch](https://euipo.europa.eu/eSearch)) před veřejným
  spuštěním, ať nás nedožene cizí starší známka; zvážit vlastní přihlášku (třídy 35 a 42).

## 2. Doporučené před spuštěním

- [ ] **[R] O8 — změřit limit délky požadavku** na hostingu, zapsat do PLAN.md.
- [ ] **[R]+[K] Monitoring:** UptimeRobot i na `/up`. Upozornění na chyby e-mailem
  (log kanál `mail` nebo denní souhrn chyb). Do `/health/imports` přidat import prodejen.
- [ ] **[R] Zálohy:** doplnit `offer_product`, `shopping_list_items` a avatary
  (`storage/app/private/avatars` přes FTP), ověřit, jak dlouho drží automatické zálohy
  Websupportu (zásady slibují nejdéle 6 měsíců); jednou vyzkoušet obnovu.
- [ ] **[R] Přihlášení přes Google a Facebook** (R96): založit aplikace u Googlu a Mety, klíče do `.env`
  (postup v DEPLOYMENT.md); Google *In production*, Facebook *Live* s odkazy na zásady, podmínky
  a pokyny ke smazání dat. Ověřit návrat do aplikace z plochy iPhonu (Safari má vlastní cookies).
- [ ] **[R] Měkké spuštění:** nejdřív 20–50 lidem z okolí na dva týdny a sledovat, co opravdu
  používají (doporučení revize 4. 10. 2026), teprve pak veřejně.

## 3. Po spuštění / podle potřeby

- [ ] Export dat tlačítkem v Účtu (zatím stačí vyřídit žádost e-mailem do měsíce).
- [ ] Rušení dlouho neaktivních účtů (např. po 2 letech s upozorněním) — pak doplnit do zásad.
- [ ] Cache pro Moje slevy, až přibudou uživatelé (úvodní stránka je v cache od R95).
- [ ] **[K] První obchodní sdělení:** Mailable jen uživatelům s `hasMarketingConsent()`
  (v SQL: `marketing_consent_at` vyplněné a novější než `marketing_consent_withdrawn_at` —
  odvolání čas udělení nemaže, R69) a ověřeným e-mailem, v předmětu nebo úvodu označené jako obchodní sdělení, patička
  níže, odkaz a hlavičky odhlášení na `MailingList::Marketing` (vzor `DigestMail`). Společná
  patička e-mailů provozovatele neuvádí (R81) — obchodní sdělení nesmí skrývat odesílatele, do jeho
  patičky proto přidat provozovatele (`letaky.operator`) nebo aspoň odkaz na `/kontakt`.
  Text patičky:
  > Toto je obchodní sdělení. Dostáváte ho, protože jste souhlasili se zasíláním novinek
  > a nabídek Slevohlídky. [Odhlásit se z obchodních sdělení]

## 4. Až přijde monetizace

- **Cookie lišta, Google Analytics a Microsoft Clarity jsou hotové** (R52, R103). **Reklamní a affiliate sítě s cookies**
  se smí načíst jen za souhlasem v kategorii Marketingové (`consentState.marketing`),
  s úpravou CSP, zásad a zvýšením `letaky.cookie_consent.version`.
- **Úvodní stránka slibuje „Zdarma a bez reklam“ a registrace „Žádné reklamy“** (`lang/cs/app.php`,
  `landing.features.free`, `auth.register.trust.no_ads`). Dokud reklama na webu není, nechávají se
  (rozhodnutí 4. 10. 2026); před reklamou oba texty upravit.
- **Placené nebo partnerské nabídky** ve výpisech a souhrnech musí být viditelně
  označené jako reklama (zákon o regulaci reklamy). Souhrn s partnerskými nabídkami
  už je obchodní sdělení, posílat jen se souhlasem.
- **Nikdy nepředávat e-maily partnerům** — souhlas je jen na sdělení posílaná
  provozovatelem. Jinak nový souhlas s konkrétními příjemci.
- **Placené funkce** = smlouva se spotřebitelem za úplatu: VOP, poučení o odstoupení,
  platební brána, účtenky a DPH podle obratu.

## 5. Právo k obsahu obchodů (O6) — podklady pro advokáta

Orientační rozbor ze 3. 10. 2026, ne právní rada. Slouží jako podklad ke konzultaci.

### Jak to dělají jiní

- **Kupi.cz** (skupina Seznam.cz) letáky nestahuje proti vůli obchodů: na
  [kupi.cz/partner](https://www.kupi.cz/partner) **prodává obchodům propagaci letáků**
  (2,5 mil. uživatelů měsíčně, cílení podle místa a zájmů); obchod leták dodá nebo si ho
  nechá vytvořit a platí za zobrazení. Je to placený reklamní kanál se souhlasem obchodů.
- **iletaky.cz** se nepodařilo ověřit (web nešel stáhnout); předpoklad podobného modelu.
- **Rozdíl:** oni mají souhlas obchodů, Slevohlídka zatím ne.

### Rizika podle druhu obsahu

| Co | Riziko | Proč |
|---|---|---|
| Ceny, názvy, platnost akcí | nízké | Fakta autorský zákon nechrání. |
| Právo pořizovatele databáze (§ 88 an. AZ, směrnice 96/9/ES) | nízké až střední | Chrání se vklad do *získání, ověření a předvedení* obsahu, ne do jeho *vytvoření* — obchod si ceny stanoví sám (SDEU C-203/02 *British Horseracing Board*). Vytěžování porušuje právo, jen když ohrožuje návratnost investice pořizovatele (C-762/19 *CV-Online Latvia*, 2021) — Slevohlídka obchodům posílá zákazníky odkazem „Do obchodu“. Proti: metavyhledávač, který převzal podstatnou část databáze, právo porušil (C-202/12 *Innoweb*). |
| Fotky produktů (odkaz na CDN obchodu, R22) | střední | Fotky jsou díla. Vložení volně dostupného díla odkazem není nové sdělení veřejnosti, pokud se neobchází technická ochrana (C-466/12 *Svensson*, C-392/19 *VG Bild-Kunst*). `referrerpolicy="no-referrer"` je kvůli soukromí, ne obcházení — ověřit, že žádné CDN neblokuje cizí weby podle Referer. |
| Loga a názvy obchodů | nízké | Popisné užití ochranné známky, které nevzbuzuje dojem spolupráce; upozornění v patičce. Loga jsou citlivější než názvy — případně nahradit textem. |
| Podmínky webů: Tesco jen osobní užití, Albert zákaz stahování | střední | U volně přístupného webu bez výslovného přijetí podmínek je smluvní vazba slabá; databázi, kterou zákon nechrání, ale smí provozovatel omezit smlouvou (C-30/14 *Ryanair*). Tesco: voláme API s klíčem z jejich webu (veřejný, ale horší dojem než čtení stránky). |
| Stahování celých PDF letáků (R86–R89: Lidl, Albert, Globus, Billa) | nízké až střední | Leták jako celek (grafika, texty) je dílo; bereme z něj jen fakta (název, cena, balení, platnost), PDF se neukládá (R5), po zpracování zmizí. Pro dočasnou kopii k vytěžení dat je výjimka pro vytěžování textů a dat (§ 39c AZ, čl. 4 směrnice 2019/790) — **neplatí, když si ji autor vhodně vyhradí** (strojově čitelně, u webu typicky v podmínkách nebo robots.txt). Albert má zákaz stahování v podmínkách webu. |
| Nekalá soutěž (§ 2976 OZ) | nízké | Hrozila by při klamání nebo parazitování na pověsti; Slevohlídka jen odkazuje na obchod. |

### Realistický scénář

Nejhorší reálný případ je **výzva obchodu, ať ho přestaneme zobrazovat** — při rychlém
vyhovění jsou škoda a soud nepravděpodobné (kontakt pro obchody je v podmínkách, čl. 10).
Riziko **roste s monetizací**: reklama = komerční užití cizího obsahu a konkurence placených
kanálů jako Kupi.

### Co dělat

- [ ] **[R] Hodina u advokáta** před monetizací — s touto kapitolou a [ZDROJE_DAT.md](ZDROJE_DAT.md).
- [ ] **[R] Zvážit vypnutí nebo omezení obchodů s výslovným zákazem** (Tesco, Albert),
  případně u nich nezobrazovat fotky. Albert má od R87 i akce s cenou z PDF letáku (dřív jen
  zmínky z textu letáku, R36) — návrat jen ke zmínkám je úprava zdroje `AlbertOfferSource`, ne konfigurace.
- [ ] **[R] Oslovit obchody** (souhlas, partnerství, affiliate) — nejčistší cesta a zdroj
  příjmu; dává smysl, až budou čísla návštěvnosti.
- Co už platí: jen fakta s odkazem na zdroj, fotky se neukládají (R5, R22), robots.txt
  a šetrné stahování, upozornění v patičce, kontakt pro obchody, rychlé vyřízení žádosti.

### Otázky na advokáta

1. Je nabídka akcí obchodu chráněná právem pořizovatele databáze, když ceny obchod sám vytváří?
2. Jsou pro nás závazné podmínky webu Tesca a Alberta, které jsme výslovně nepřijali?
   Mění něco volání API s klíčem veřejně uvedeným v jejich HTML?
3. Smíme zobrazovat fotky produktů odkazem na CDN obchodu? A loga obchodů?
4. Co se změní s reklamou nebo partnerskými nabídkami na webu (komerční užití)?
5. Jak formulovat podmínky a postup pro žádost obchodu o stažení obsahu?
6. Stačí na výjimku pro vytěžování textů a dat (§ 39c AZ) při čtení cen z PDF letáků, že PDF
   neukládáme? Je zákaz stahování v podmínkách webu Alberta „vhodná výhrada“, která ji vylučuje?
7. Které povinnosti nařízení o digitálních službách (DSA) se nás týkají a stačí kontakt
   a postup oznámení v podmínkách? (Uživatelský obsah: názvy hlídaných položek, profilový obrázek.)
