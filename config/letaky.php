<?php

/**
 * Konstanty aplikace Slevohlídka (repozitář agregator-letaku).
 *
 * Do .env patří jen infrastruktura a tajemství; tady jsou výchozí hodnoty
 * a vše, co se v kódu nesmí objevit jako magic number.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Domain\Sources\Albert\AlbertOfferSource;
use App\Domain\Sources\Billa\BillaOfferSource;
use App\Domain\Sources\Globus\GlobusOfferSource;
use App\Domain\Sources\Kaufland\KauflandOfferSource;
use App\Domain\Sources\Kaufland\KauflandStoreSource;
use App\Domain\Sources\Lidl\LidlOfferSource;
use App\Domain\Sources\Penny\PennyOfferSource;
use App\Domain\Sources\Tesco\TescoOfferSource;

return [

    /*
    | Časová zóna pro zobrazení a pro „místní datum“ platnosti akcí (R7).
    | Aplikace i databáze běží v UTC.
    */
    'display_timezone' => env('LETAKY_DISPLAY_TIMEZONE', 'Europe/Prague'),

    /*
    | Loga obchodů v public/ (sprintf s hodnotou App\Enums\Chain) — zdroje v hlavičkách souborů.
    */
    'chain_logo_path' => 'images/chains/%s.svg',

    /*
    | Barva lišty prohlížeče na mobilu podle režimu (= --color-bg v base/_tokens.scss).
    */
    'theme_colors' => [
        'light' => '#f4f6f8',
        'dark' => '#16181d',
    ],

    /*
    | Provozovatel (R51) — patička webu a e-mailů, podmínky a zásady (resources/legal).
    | Podnikatel musí mít na webu jméno, IČO a sídlo (§ 435 OZ) — všechny jsou v podmínkách
    | a zásadách; patička webu ukazuje jméno a sídlo, IČO ne. Chybějící údaj ukážou právní
    | stránky jako „[doplnit]“, patička ho vynechá.
    */
    'operator' => [
        'name' => 'Roman Hlaváček',
        'company_id' => '88688143',
        // Sídlo po řádcích — patička je vypíše pod sebou, texty a e-maily spojí čárkou (App\Support\Operator)
        'address' => ['Rodov 133', '503 03 Smiřice'],
        'email' => 'roman.hlavacek@rhsoft.cz',
    ],

    /*
    | Podmínky užití a zásady zpracování osobních údajů (R51): Markdown v resources/legal.
    | Verze podmínek se ukládá k uživateli při registraci (users.terms_version), verze textu
    | souhlasu s obchodními sděleními k souhlasu (users.marketing_consent_version) — při
    | podstatné změně textu zvýšit. Datum účinnosti null = na stránce se neukáže.
    */
    'legal' => [
        'directory' => 'legal',
        'terms_version' => 1,
        'marketing_consent_version' => 1,
        'effective_from' => null,
    ],

    /*
    | Souhlas s cookies a Google Analytics 4 (R52). GA se načte jen na produkci a jen po souhlasu
    | s analytickými cookies (resources/js/lib/consent.js); jiné ID nebo prázdné (vypnuto)
    | jde nastavit v .env. Verze souhlasu: při změně kategorií nebo nástrojů zvýšit — všichni
    | se pak vyberou znovu. Platnost volby 6 měsíců, pak se lišta ukáže znovu (doporučení ÚOOÚ).
    */
    'cookie_consent' => [
        'google_measurement_id' => env('LETAKY_GA_MEASUREMENT_ID', 'G-BM3CZ7M4PD'),
        'version' => 1,
        'max_age_days' => 180,
    ],

    /*
    | Přihlášení a registrace (R12, R53).
    | - Pokusy o přihlášení za minutu: pro dvojici e-mail + IP (hádání hesla k jednomu účtu)
    |   a pro samotnou IP (zkoušení uniklých přihlašovacích údajů přes různé e-maily).
    | - Heslo: nejmenší délka a kontrola proti únikům Have I Been Pwned — posílá se jen
    |   prvních 5 znaků SHA-1 otisku hesla (k-anonymita). V testech vypnutá (testy nesmí na síť).
    | - Registrace proti botům: skryté pole (vyplní jen robot) a podepsaný čas načtení
    |   formuláře — rychlejší odeslání, než zvládne člověk, nebo příliš starý formulář neprojde.
    */
    'auth' => [
        'login_attempts_per_minute' => 5,
        'login_attempts_per_minute_per_ip' => 20,
        'password' => [
            'min_length' => 8,
            'uncompromised' => (bool) env('LETAKY_PASSWORD_UNCOMPROMISED', true),
        ],
        'registration' => [
            'min_seconds' => 3,
            'max_age_minutes' => 120,
        ],
        // Kolik akcí s nejvyšší slevou ukáže panel vedle přihlášení a registrace (R56)
        'showcase_deals' => 5,
    ],

    /*
    | Stahování od obchodů — šetrně a s identifikovatelným User-Agentem (R5, R53).
    */
    'http' => [
        // Adresa bez schématu (R65): UA s „https://“ vypadá jako robot vyhledávače a Albert takový
        // požadavek pošle přes prerender, který GraphQL dotaz rozbije (400)
        'user_agent' => env('LETAKY_USER_AGENT', 'Slevohlidka/1.0 (+slevohlidka.rhsoft.cz)'),
        'timeout_seconds' => 30,
        'retries' => 2,
        'retry_delay_ms' => 2000,
        // Pauza mezi požadavky na stejný obchod
        'request_delay_ms' => (int) env('LETAKY_REQUEST_DELAY_MS', 1500),
    ],

    /*
    | Zdroje obchodů (docs/ZDROJE_DAT.md). Obchod bez offers_source se nestahuje.
    */
    'sources' => [
        'kaufland' => [
            'offers_source' => KauflandOfferSource::class,
            // Uživatel volí typ prodejny (HM / SM) a akce jen z e-shopu (R19)
            'has_store_formats' => false,
            'has_eshop' => false,
            // Web kamenných prodejen — www.kaufland.cz je marketplace za Cloudflare
            'offers_url' => 'https://prodejny.kaufland.cz/nabidka/prehled.html',
            // Prodejny a akce po prodejnách (R49): seznam prodejen a akce každé z nich (jen klNr a platnost)
            'stores_source' => KauflandStoreSource::class,
            'stores_url' => 'https://prodejny.kaufland.cz/.klstorefinder.json',
            'store_offers_base_url' => 'https://prodejny.kaufland.cz/',
            'store_offers_path' => '.kloffers.storeName=%s.json',
            'store_name_prefix' => 'Kaufland ',
            // 149 malých souborů (~40 kB) — kratší pauza, ať se stažení vejde do limitu hostingu (O8)
            'store_offers_delay_ms' => (int) env('LETAKY_KAUFLAND_STORES_DELAY_MS', 300),
            // Stránka nabídky konkrétní prodejny se volí cookie
            'store_cookie' => 'x-aem-variant',
            // Kolik stránek prodejen nejvýš stáhnout navíc k výchozí, aby měly detail všechny akce
            // všech prodejen (3. 10. 2026 jich stačilo 24)
            'max_store_pages' => 40,
            // Pauza před stránkou prodejny (~2,5 MB) — 24 stránek se musí vejít do limitu hostingu (O8)
            'store_page_delay_ms' => (int) env('LETAKY_KAUFLAND_STORE_PAGE_DELAY_MS', 1000),
            // Seznam akcí prodejny starší než tohle se při určení prodejen akce nebere v úvahu
            'store_offers_max_age_hours' => 36,
        ],
        'lidl' => [
            'offers_source' => LidlOfferSource::class,
            'has_store_formats' => false,
            'has_eshop' => false,
            'base_url' => 'https://www.lidl.cz',
            // Úvodní stránka odkazuje na kampaně týdne (/c/{slug}/a{id}), na nich jsou akce
            'campaign_index_path' => '/',
            // Trvalé ceny, ne akce (R8)
            'excluded_campaigns' => ['ceny-v-klidu'],
            // Akce kamenných prodejen — nepotravinové zboží (móda, dílna…) se zatím nesleduje
            'categories' => ['Food'],
            // Kampaní je ~40 malých stránek — kratší pauza, ať stažení nepřesáhne limit hostingu (O8)
            'request_delay_ms' => (int) env('LETAKY_LIDL_REQUEST_DELAY_MS', 500),
            // Letáky pro zmínky bez ceny (R27): seznam letáků, jen potravinové, API letáků Schwarz
            'leaflets_page_path' => '/c/akcni-letak/s10008644',
            'leaflet_slug_prefixes' => ['akcni-letak-od-'],
            'flyer_api_url' => 'https://endpoints.leaflets.schwarz/v4/flyer',
            'flyer_page_path' => '/l/cs/letak/%s/view/flyer/page/%d',
        ],
        'penny' => [
            'offers_source' => PennyOfferSource::class,
            'has_store_formats' => false,
            'has_eshop' => false,
            'base_url' => 'https://www.penny.cz',
            'products_api_path' => '/api/product-discovery/products',
            'product_url_path' => '/products/',
            'page_size' => 100,
            // Stránka s odkazem na leták (…/PennyIntLeaflet/CZ/DD_MM_YYYY/)
            'leaflets_page_path' => '/nabidky/letaky',
            'leaflet_base_url' => 'https://files.rewe.co.at/PennyIntLeaflet/CZ/',
            // Vektorová vrstva stránky letáku (číslo stránky od 1)
            'leaflet_page_svg_path' => 'files/assets/common/page-vectorlayers/%04d.svg',
            // Leták má ~40 stránek — kratší pauza, ať stažení nepřesáhne limit hostingu (O8)
            'request_delay_ms' => (int) env('LETAKY_PENNY_REQUEST_DELAY_MS', 500),
        ],
        'globus' => [
            // Veřejné REST API webu (R46): akce s cenou v prodejně a k nim položky letáku
            'offers_source' => GlobusOfferSource::class,
            'has_store_formats' => false,
            'has_eshop' => false,
            'api_url' => 'https://www.globus.cz/api/v1/gsoa/actionOffers/houses/%d/',
            'catalog_path' => 'actionProductsCatalog',
            'leaflet_items_path' => 'actionProducts',
            // Hypermarkety se liší jen pár krátkými místními akcemi — stačí jeden (4005 = Praha Čakovice)
            'house_id' => 4005,
            // Nejvíc, co API dovolí
            'page_size' => 200,
            // Typ ceny akce; VKP0 (pult, platnost do 9999) ani ZTP0 (doprodej) akce z letáku nejsou
            'action_price_types' => ['VKA0'],
            // Skupiny zboží (první 3 znaky `warengroup`), které se nesledují: oblečení (700, 701, 707),
            // obuv (710), bytový textil (654, 661, 706), kabelky a kufry (675). Potraviny, drogerie, krmiva
            // a domácí potřeby zůstávají.
            'excluded_ware_groups' => ['654', '661', '675', '700', '701', '706', '707', '710'],
            'ware_group_prefix_length' => 3,
            'offers_page_url' => 'https://www.globus.cz/globus/hypermarket/akcni-nabidka',
        ],
        'billa' => [
            // Product-discovery API jako Penny, ale s celým katalogem (R48); akce vybere parser
            'offers_source' => BillaOfferSource::class,
            'has_store_formats' => false,
            // Část akcí platí jen v e-shopu (odznak eshop-only) — uživatel je může skrýt (R19)
            'has_eshop' => true,
            'base_url' => 'https://www.billa.cz',
            'products_api_path' => '/api/product-discovery/products',
            'product_url_path' => '/produkt/',
            // Nejvíc, co API dovolí (víc = 400)
            'page_size' => 500,
            // Štítky akce u price.regular; pt-abverkauf (doprodej) akce není
            'promotion_tags' => ['pt-aktion', 'pt-multi'],
            'loyalty_tag' => 'pt-loyalclub',
            'eshop_only_badge' => 'eshop-only',
            // Akce na množství od tolika kusů; „pt-multi“ s 0,001 kg u váženého zboží je obyčejná akce
            'multibuy_min_quantity' => 2,
            // API nemá platnost akcí — akční týden jako leták: středa (ISO 3) až úterý
            'week_start_iso_day' => 3,
            // Platnost odvozujeme sami: akce, která se stejnou cenou pokračuje do dalšího týdne,
            // prodlouží svůj řádek, místo aby vznikla „nová“ (R54, ImportChainOffers::continuePrevious)
            'extends_continuing_offers' => true,
            'offers_page_url' => 'https://www.billa.cz/akcni-letaky',
            // ~25 stránek po ~1 MB — pauza kratší než výchozí, ať stažení nepřesáhne limit hostingu (O8)
            'request_delay_ms' => (int) env('LETAKY_BILLA_REQUEST_DELAY_MS', 1000),
        ],
        'albert' => [
            // Jen zmínky v letácích bez ceny (R27, R36): text stránek z prohlížeče Publitas
            'offers_source' => AlbertOfferSource::class,
            // Stažení bez akcí s cenou není chyba, stačí stránky letáku (u ostatních obchodů je, R54)
            'mentions_only' => true,
            'has_store_formats' => true,
            'has_eshop' => false,
            'api_url' => 'https://www.albert.cz/api/v1/',
            // locationType v GraphQL => formát prodejny (App\Enums\StoreFormat)
            'location_types' => ['HYPERMARKET' => 'hypermarket', 'SUPERMARKET' => 'supermarket'],
            // Soubory prohlížeče letáku (viewUrl z GraphQL + cesta)
            'spreads_path' => 'spreads.json',
            'page_path' => 'page/%d',
            'page_image_base_url' => 'https://letaky.albert.cz',
            // Náhled stránky (151 × 263 px) — stačí na kartu zmínky
            'page_image_size' => 'at200',
            'request_delay_ms' => (int) env('LETAKY_ALBERT_REQUEST_DELAY_MS', 500),
        ],
        'tesco' => [
            'offers_source' => TescoOfferSource::class,
            'has_store_formats' => true,
            'has_eshop' => true,
            'eshop_api_url' => 'https://xapi.tesco.com/',
            // Veřejný klíč z HTML e-shopu (mangoApiKey), může se změnit
            'eshop_api_key' => env('TESCO_API_KEY'),
            'eshop_page_size' => 200,
            'eshop_product_url' => 'https://nakup.itesco.cz/groceries/cs-CZ/products/',
            'eshop_promotions_url' => 'https://nakup.itesco.cz/groceries/cs-CZ/promotions',
            'leaflets_api_url' => 'https://api.prod.retail.tesco.com/marketing/leaflets-be/graphql',
            // ID produktu v letáku a v e-shopu se shodují v posledních 8 číslicích (ZDROJE_DAT.md)
            'leaflet_product_id_suffix_length' => 8,
        ],
    ],

    /*
    | Cron URL pro produkci (R20, R38) — WebAdmin Websupportu umí jen zavolat URL.
    | Token: docker compose exec app php -r "echo bin2hex(random_bytes(24));"
    | Prázdný token cron URL vypíná (odpovídají 404).
    */
    /*
    | Účet uživatele (R40). Profilový obrázek ořízne a zmenší prohlížeč na čtverec
    | size_px (hosting nemusí mít knihovnu na úpravu obrázků), server ho jen ověří.
    */
    'account' => [
        'avatar' => [
            'directory' => 'avatars',
            'size_px' => 256,
            'max_kilobytes' => 512,
            'mimes' => ['webp', 'png', 'jpg'],
        ],
        // Nabídka hranic minimální slevy v Mých slevách (R41), v procentech
        'min_discount_options' => [10, 20, 30, 50],
    ],

    /*
    | E-mailový souhrn nových akcí (R42). Cron ho volá každou hodinu od 6:30 do 22:30 (R54, R58)
    | — jedno volání zpracuje dávku uživatelů, kterým je čas a od jejichž posledního souhrnu
    | doběhlo stažení akcí; interval je o pár hodin kratší než den / týden, aby posun cronu
    | souhrn nepřeskočil. Okno začíná po ranním stažení, takže denní souhrn chodí ráno.
    */
    'digest' => [
        // Uživatelů na jedno volání: vejde se do limitu běhu (~1 s na e-mail) i do limitu
        // Websupportu 300 e-mailů za hodinu ze schránky
        'users_per_run' => 100,
        'interval_hours' => [
            // Okamžité upozornění (R58): po stažení, které přineslo nové akce, ale nejvýš jednou za hodinu
            'instant' => 1,
            'daily' => 20,
            'weekly' => 164,
        ],
        // Kolik akcí jedné hlídané položky e-mail vypíše (zbytek odkaz na Moje slevy)
        'max_offers_per_item' => 5,
    ],

    /*
    | Import akcí (ImportChainOffers). Chybí-li v novém stažení víc než tento podíl neskončených
    | akcí obchodu, zdroj nejspíš vrátil jen část nabídky — chybějící se neoznačí jako stažené
    | (R16) a stažení skončí jako částečné (R54). Běžně chybí jednotky procent; Globus po vyřazení
    | oblečení 24 %, výpadek jednoho ze dvou letáků Penny kolem 50 %.
    */
    'import' => [
        'max_withdrawn_share' => 0.4,
        // Zámek jednoho stažení obchodu (R57): delší než limit běhu cronu (cron.time_limit_seconds),
        // aby nevypršel během stažení; stažení „běží“ déle než tohle = nedoběhlo, označí se jako chyba
        'lock_seconds' => 600,
    ],

    /*
    | „Je to opravdu sleva?“ (R59, App\Domain\Offers\PriceHistory): s dřívějšími akcemi stejné
    | položky u stejného obchodu za kolik týdnů zpátky se cena akce porovná.
    */
    'price_history' => [
        'weeks' => 12,
    ],

    /*
    | Nákupní seznam (R61): nejvýš tolik akcí na uživatele.
    */
    'shopping_list' => [
        'max_items' => 200,
    ],

    /*
    | Aplikace v telefonu (PWA, R66): service worker (/sw.js) a offline režim. Stránky v seznamu
    | offline_paths si service worker ukládá a bez připojení ukáže poslední verzi; ostatní
    | stránky ukážou bez připojení stránku „Jste offline“. Po network_timeout_ms bez odpovědi
    | serveru (slabý signál v obchodě) dostane uživatel uloženou verzi a nová se uloží na příště.
    */
    'pwa' => [
        'offline_paths' => ['/', '/seznam', '/hlidam'],
        'network_timeout_ms' => 4000,
        // Po návratu do aplikace z pozadí se stránka načte znovu, když je starší než tohle —
        // nainstalovaná aplikace nemá tlačítko pro obnovení a v telefonu běží klidně dny
        'refresh_after_minutes' => 30,
        // Výzva k přidání na plochu: kolik dní po zavření se znovu neukáže
        'install_prompt_snooze_days' => 30,
        // Úvodní obrazovka iPhonu při spuštění z plochy — iOS ji nebere z manifestu, chce obrázek
        // přesně na rozlišení displeje: [šířka, výška v CSS px, hustota]. Obrázky
        // public/images/brand/splash-{šířka}x{výška}x{hustota}.png kreslí resources/brand/splash.html
        // (jméno bez „-“ a osmi znaků před příponou — .htaccess by ho jako build cachoval napořád).
        'startup_images' => [
            [440, 956, 3],
            [402, 874, 3],
            [430, 932, 3],
            [393, 852, 3],
            [428, 926, 3],
            [390, 844, 3],
            [414, 896, 3],
            [375, 812, 3],
            [414, 896, 2],
            [375, 667, 2],
        ],
    ],

    /*
    | Upozornění v telefonu — web push (R66). Klíče VAPID vygeneruje `php artisan letaky:push-keys`
    | (veřejný jde do prohlížeče, soukromý jen do .env); bez nich je funkce vypnutá. Cron
    | /cron/send-digests pošle po stažení s novými akcemi upozornění dávce uživatelů, nejvýš
    | jednou za interval_hours. Upozornění se smí posílat jen na adresy push služeb prohlížečů
    | (allowed_hosts, i subdomény) — jinak by šlo server přimět posílat požadavky kamkoli (SSRF).
    */
    'push' => [
        'vapid' => [
            'public_key' => env('LETAKY_VAPID_PUBLIC_KEY'),
            'private_key' => env('LETAKY_VAPID_PRIVATE_KEY'),
        ],
        // Jak dlouho push služba upozornění drží pro vypnutý telefon (sekundy) — ráno je včerejší pozdě
        'ttl_seconds' => 43200,
        'timeout_seconds' => 10,
        'users_per_run' => 200,
        'interval_hours' => 1,
        // Kolik akcí upozornění vypíše (zbytek „a další…“) — text v telefonu má pár řádků
        'max_offers' => 3,
        'max_subscriptions_per_user' => 10,
        'allowed_hosts' => [
            'fcm.googleapis.com',
            'android.googleapis.com',
            'updates.push.services.mozilla.com',
            'push.apple.com',
            'notify.windows.com',
        ],
    ],

    'cron' => [
        'token' => env('LETAKY_CRON_TOKEN'),
        // Limit běhu jednoho volání — stažení Tesca trvá ~45 s; hosting ho může omezit i tak (O8)
        'time_limit_seconds' => 180,
    ],

    /*
    | Hlídání stahování (/health/imports): obchod bez úspěšného stažení za tuto dobu = výpadek.
    | Cron stahuje jednou až dvakrát denně, rezerva na jeden vynechaný běh.
    */
    'health' => [
        'max_import_age_hours' => 26,
    ],

    /*
    | Kategorie katalogu = strom e-shopu Tesco (R28). Oddělení s marketingovými výběry
    | („Top výběr“, „Novinky“) a nepotravinové zboží se nepřebírají.
    */
    'categories' => [
        'excluded_roots' => ['Top výběr', 'Novinky', 'Domov a zábava'],
    ],

    /*
    | Prodejny (R49): kolik si jich uživatel u obchodu nejvýš vybere.
    */
    'stores' => [
        'max_selected' => 10,
    ],

    /*
    | Katalog produktů (R29, R30) — správa pro admina.
    */
    'catalog' => [
        // Kolik nalezených nabídek se ukáže při ručním přiřazování k produktu
        'search_results' => 20,
        'per_page' => 50,
        'search_max_length' => 100,
        // Ikona dlaždice oddělení v Hlídám (R47, DepartmentIcon.vue) podle názvu oddělení ze
        // stromu Tesca; oddělení, které tu není, dostane ikonu „other“
        'department_icons' => [
            'Ovoce a zelenina' => 'produce',
            'Mléčné, vejce a margaríny' => 'dairy',
            'Pekárna' => 'bakery',
            'Maso a lahůdky' => 'meat',
            'Mražené' => 'frozen',
            'Trvanlivé' => 'pantry',
            'Nápoje' => 'drinks',
            'Speciální výživa' => 'special',
            'Úklid' => 'cleaning',
            'Drogerie' => 'drugstore',
            'Dítě' => 'baby',
            'Zvíře' => 'pets',
        ],
    ],

    /*
    | Úvodní stránka pro nepřihlášené (R44): kolik akcí s nejvyšší slevou ukázat a z kolikrát
    | většího výběru je brát (ať se v ukázce vystřídají obchody).
    */
    'landing' => [
        'top_offers' => 6,
        'top_offers_candidates_factor' => 5,
    ],

    /*
    | Omezení počtu požadavků za minutu (R45, App\Support\RateLimits). Běžný uživatel se
    | k limitům nepřiblíží; brání hádání hesel a tokenu cronu a zahlcení našeptávače.
    */
    'rate_limits' => [
        'public_per_minute' => 120,
        'suggestions_per_minute' => 60,
        'cron_per_minute' => 20,
        'writes_per_minute' => 60,
        'sensitive_writes_per_minute' => 5,
    ],

    /*
    | Stránkování s „Načíst další“ (R43) — Všechny akce i katalog: nejvýš tolik stránek
    | najednou; kolik čísel stránek ukázat kolem načteného rozsahu (vždy i první a poslední).
    */
    'pagination' => [
        'max_loaded_pages' => 10,
        'page_link_neighbours' => 1,
    ],

    /*
    | Přehled nabídek.
    */
    'offers' => [
        'per_page' => 50,
        'search_max_length' => 100,
        // Našeptávač hledání: od kolika znaků a kolik návrhů
        'suggest_min_length' => 2,
        'suggest_limit' => 8,
    ],

    /*
    | Krmivo pro zvířata (R50, App\Domain\Matching\PetFood). Hlídání „hovězí“ by jinak našlo
    | „Friskies hovězí v želé“ — krmivo se ukáže jen u hlídání, které je samo o zvířatech.
    | Krmivo pozná kategorie obchodu, nebo začátek slova v textu akce (bez diakritiky; mezera
    | na konci = celé slovo). Pozor na dvojznačná slova: „podestýlk“ (vejce z podestýlky),
    | „dog“ (Bull Dog sprej) — ověřeno na všech akcích 3. 10. 2026.
    */
    'pet_food' => [
        'categories' => [
            'Pro kočky', 'Pro psy', 'Pro hlodavce', 'Pro ptáky',                  // Tesco
            'Krmivo pro kocky', 'Krmivo pro psy', 'Hlodavci, ptaci, akvaristika',   // Globus
            'Konzervy a kapsičky', 'Suché krmivo', 'Pamlsky a jiné',                // Billa (Mazlíčci)
        ],
        'offer_words' => [
            'pro psy', 'pro psa', 'pro pejsk', 'pro kocky', 'pro kocku', 'pro kocicky', 'pro kotata',
            'pro stenata', 'pro hlodavce', 'pro ptaky', 'pro morcata', 'krmivo', 'granule', 'pamlsek',
            'pamlsky', 'stelivo', 'friskies', 'cesar ', 'felix ', 'whiskas', 'pedigree', 'sheba', 'kitekat',
            'purina', 'dreamies', 'perfect fit', 'darling', 'chappi', 'frolic', 'adventuros', 'akinu',
            'meat care', 'vitakraft', 'catsan', 'gourmet gold',
        ],
        // Hlídání je o zvířatech, když některé hledané slovo začíná takhle
        'rule_words' => [
            'krmiv', 'granul', 'pamlsk', 'kock', 'kocic', 'kotat', 'pes', 'psi', 'psy', 'pejsk', 'stenat',
            'hlodav', 'ptac', 'ptak', 'steliv', 'friskies', 'cesar', 'felix', 'whiskas', 'pedigree', 'sheba',
            'kitekat', 'purina', 'dreamies', 'darling', 'chappi', 'frolic', 'vitakraft', 'brit',
        ],
    ],

    /*
    | Zmínky v letácích bez ceny (R27). Stránky s receptem vyjmenovávají suroviny („vejce“,
    | „máslo“), které v akci nejsou — poznají se podle těchto frází (bez ohledu na diakritiku).
    */
    'mentions' => [
        'excluded_page_phrases' => ['postup přípravy', 'nákupní seznam', 'recept na'],
    ],

    /*
    | Hlídané položky (R18, R31) — produkt z katalogu, nebo vlastní slova (zápis viz
    | App\Domain\Matching\WatchRule). Výchozí produkty katalogu jsou v Database\Seeders\CatalogSeeder.
    */
    'watch' => [
        'max_items_per_user' => 50,
        'name_max_length' => 100,
        'keywords_max_length' => 255,
        // Nejdelší hledané slovo aspoň takhle dlouhé — jedno písmeno nebo číslice by pustily
        // do předvýběru skoro všechny nabídky (R54, App\Rules\SearchableKeywords); „wc“ projde
        'min_search_word_length' => 2,
    ],

];
