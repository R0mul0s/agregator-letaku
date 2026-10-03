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
    | Přihlášení (R12) — pokusy za minutu pro dvojici e-mail + IP.
    */
    'auth' => [
        'login_attempts_per_minute' => 5,
    ],

    /*
    | Stahování od obchodů — šetrně a s identifikovatelným User-Agentem (R5).
    */
    'http' => [
        'user_agent' => env('LETAKY_USER_AGENT', 'AgregatorLetaku/0.1 (osobni projekt; +https://github.com/R0mul0s/agregator-letaku)'),
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
            'offers_page_url' => 'https://www.billa.cz/akcni-letaky',
            // ~25 stránek po ~1 MB — pauza kratší než výchozí, ať stažení nepřesáhne limit hostingu (O8)
            'request_delay_ms' => (int) env('LETAKY_BILLA_REQUEST_DELAY_MS', 1000),
        ],
        'albert' => [
            // Jen zmínky v letácích bez ceny (R27, R36): text stránek z prohlížeče Publitas
            'offers_source' => AlbertOfferSource::class,
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
    | E-mailový souhrn nových akcí (R42). Cron ho volá jednou denně po ranním stažení;
    | interval je o pár hodin kratší než den / týden, aby posun cronu souhrn nepřeskočil.
    */
    'digest' => [
        'interval_hours' => [
            'daily' => 20,
            'weekly' => 164,
        ],
        // Kolik akcí jedné hlídané položky e-mail vypíše (zbytek odkaz na Moje slevy)
        'max_offers_per_item' => 5,
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
    ],

];
