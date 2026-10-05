<!--
  Zveřejnění Slevohlídky — co je potřeba udělat před spuštěním pro cizí uživatele
  @author Roman Hlaváček
  @created 2026-10-03
-->

# Zveřejnění: checklist

Slevohlídka byla dělaná pro vlastní použití (R5). Tenhle dokument sepisuje, co
chybí ke spuštění pro veřejnost. Vychází z průzkumu kódu ze 3. 10. 2026 a kritické
revize ze 4. 10. 2026. Technická a GDPR část je hotová v [R51](PLAN.md#8-log-rozhodnutí):
podmínky a zásady (`resources/legal`), patička, souhlasy při registraci, ověření e-mailu,
odhlášení z e-mailů jedním klepnutím, české chybové stránky; ochrana účtů a úklid
v [R53](PLAN.md#8-log-rozhodnutí); opravy a funkce z revize (souhrny po dávkách, pojistky
importu, heslo při změně e-mailu, první kroky po registraci, nákupní seznam…)
v [R54–R65](PLAN.md#8-log-rozhodnutí); zabezpečení, SEO a soukromí z revize připravenosti
(adresy bez `/public`, odhlášení zařízení po změně hesla, limit e-mailů, `security.txt`, GA bez
tokenů, datum účinnosti textů…) v [R67–R69](PLAN.md#8-log-rozhodnutí). Hotové body se odsud mažou a popisují
v [PLAN.md](PLAN.md); technické nápady z revize jsou v [TODO.md](TODO.md#provoz-a-údržba).

Značení: **[R]** = rozhodne nebo zařídí Roman, **[K]** = kód.

## 1. Organizační a právní (před spuštěním)

- [ ] **[R] O6 — smíme akce obchodů veřejně ukazovat?** Největší riziko spuštění.
  Rozbor a podklady pro advokáta jsou v [kap. 5](#5-právo-k-obsahu-obchodů-o6--podklady-pro-advokáta).
  Konzultace s advokátem (IT/autorské právo) **před** spuštěním, nejpozději před monetizací.
- [ ] **[R] Obory živnosti:** pokrývá živnostenský list provoz webového portálu a reklamu?
  (volná živnost, obory „Poskytování software… a webové portály“ a „Reklamní činnost,
  marketing…“) — doplnění oboru je ohlášení v RŽP.
- [ ] **[R] Doména a schránka `info@slevohlidka.cz`.** Doména koupená (2026-10-05), do
  přestěhování na ní běží stránka „Brzy spouštíme“ (`deploy/coming-soon`, R79).
  `letaky.operator.email` je `info@slevohlidka.cz` a User-Agent `+slevohlidka.cz` (R80, hotovo).
  Zbývá přestěhování na `slevohlidka.cz`: aplikace místo stránky „Brzy“, přesměrování 301
  ze subdomény, `APP_URL`, cron a ověřovací adresy v DEPLOYMENT.md, Search Console.
- [ ] **[R] SPF, DKIM a DMARC** pro odesílací doménu (DNS). Bez nich souhrny i odkazy
  na ověření e-mailu padají do spamu.
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
- [ ] **[R] `www.slevohlidka.rhsoft.cz`** odpovídá s certifikátem jiné domény (chyba TLS) — DNS
  záznam odstranit, nebo nastavit certifikát a přesměrování na adresu bez `www`.

## 2. Doporučené před spuštěním

- [ ] **[R] O8 — změřit limit délky požadavku** na hostingu, zapsat do PLAN.md.
- [ ] **[R]+[K] Monitoring:** UptimeRobot i na `/up`. Upozornění na chyby e-mailem
  (log kanál `mail` nebo denní souhrn chyb). Do `/health/imports` přidat import prodejen.
- [ ] **[R] Zálohy:** doplnit `offer_product`, `shopping_list_items` a avatary
  (`storage/app/private/avatars` přes FTP), ověřit, jak dlouho drží automatické zálohy
  Websupportu (zásady slibují nejdéle 6 měsíců); jednou vyzkoušet obnovu.
- [ ] **[R] Měkké spuštění:** nejdřív 20–50 lidem z okolí na dva týdny a sledovat, co opravdu
  používají (doporučení revize 4. 10. 2026), teprve pak veřejně.

## 3. Po spuštění / podle potřeby

- [ ] Export dat tlačítkem v Účtu (zatím stačí vyřídit žádost e-mailem do měsíce).
- [ ] Rušení dlouho neaktivních účtů (např. po 2 letech s upozorněním) — pak doplnit do zásad.
- [ ] Cache pro Moje slevy a počty na úvodní stránce, až přibudou uživatelé.
- [ ] **[K] První obchodní sdělení:** Mailable jen uživatelům s `hasMarketingConsent()`
  (v SQL: `marketing_consent_at` vyplněné a novější než `marketing_consent_withdrawn_at` —
  odvolání čas udělení nemaže, R69) a ověřeným e-mailem, v předmětu nebo úvodu označené jako obchodní sdělení, patička
  níže, odkaz a hlavičky odhlášení na `MailingList::Marketing` (vzor `DigestMail`).
  Text patičky:
  > Toto je obchodní sdělení. Dostáváte ho, protože jste souhlasili se zasíláním novinek
  > a nabídek Slevohlídky. [Odhlásit se z obchodních sdělení]

## 4. Až přijde monetizace

- **Cookie lišta a Google Analytics jsou hotové** (R52). **Reklamní a affiliate sítě s cookies**
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
| Nekalá soutěž (§ 2976 OZ) | nízké | Hrozila by při klamání nebo parazitování na pověsti; Slevohlídka jen odkazuje na obchod. |

### Realistický scénář

Nejhorší reálný případ je **výzva obchodu, ať ho přestaneme zobrazovat** — při rychlém
vyhovění jsou škoda a soud nepravděpodobné (kontakt pro obchody je v podmínkách, čl. 10).
Riziko **roste s monetizací**: reklama = komerční užití cizího obsahu a konkurence placených
kanálů jako Kupi.

### Co dělat

- [ ] **[R] Hodina u advokáta** před monetizací — s touto kapitolou a [ZDROJE_DAT.md](ZDROJE_DAT.md).
- [ ] **[R] Zvážit vypnutí nebo omezení obchodů s výslovným zákazem** (Tesco, Albert),
  případně u nich nezobrazovat fotky. Albert už teď jen zmínky z textu letáku (R36).
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
