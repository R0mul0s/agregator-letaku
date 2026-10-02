<?php

/**
 * Konstanty aplikace Agregátor letáků.
 *
 * Do .env patří jen infrastruktura a tajemství; tady jsou výchozí hodnoty
 * a vše, co se v kódu nesmí objevit jako magic number.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Domain\Sources\Kaufland\KauflandOfferSource;
use App\Domain\Sources\Kaufland\KauflandStoreSource;
use App\Domain\Sources\Tesco\TescoOfferSource;

return [

    /*
    | Časová zóna pro zobrazení a pro „místní datum“ platnosti akcí (R7).
    | Aplikace i databáze běží v UTC.
    */
    'display_timezone' => env('LETAKY_DISPLAY_TIMEZONE', 'Europe/Prague'),

    /*
    | Barva lišty prohlížeče na mobilu podle režimu (= --color-bg v base/_tokens.scss).
    */
    'theme_colors' => [
        'light' => '#f4f6f8',
        'dark' => '#11161d',
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
            'stores_source' => KauflandStoreSource::class,
            // Uživatel volí typ prodejny (HM / SM) a akce jen z e-shopu (R19)
            'has_store_formats' => false,
            'has_eshop' => false,
            // Web kamenných prodejen — www.kaufland.cz je marketplace za Cloudflare
            'offers_url' => 'https://prodejny.kaufland.cz/nabidka/prehled.html',
            'stores_url' => 'https://prodejny.kaufland.cz/.klstorefinder.json',
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
    | Přehled nabídek.
    */
    'offers' => [
        'per_page' => 50,
        'search_max_length' => 100,
    ],

    /*
    | Hlídané položky (R18). Šablony předvyplní formulář; zápis slov viz App\Domain\Matching\WatchRule.
    | Vyloučená slova jsou ze skutečných nabídek 2. 10. 2026: „MAGGI Přidej vejce“, „toustový chléb
    | s vejcem“, „Ruské vejce“, ochucený „Lipánek“ (tuk 1,3–1,5 %), „máslová dýně“, „máslový karamel“.
    | Slova se hledají jako začátek slova, takže „máslov“ vyřadí máslová, máslový i máslové.
    */
    'watch' => [
        'max_items_per_user' => 50,
        'name_max_length' => 100,
        'keywords_max_length' => 255,
        'templates' => [
            'eggs' => [
                'keywords' => 'vejce',
                'variant_keywords' => null,
                'exclude_keywords' => 'maggi polévka těstoviny toust aspik pomazánka bageta ruské',
            ],
            'semi_skimmed_milk' => [
                'keywords' => 'mléko polotučné|1,5',
                'variant_keywords' => null,
                'exclude_keywords' => 'kefír kokos zakysané acidofil čokoláda lipánek ochucené',
            ],
            'butter' => [
                'keywords' => 'máslo',
                'variant_keywords' => null,
                'exclude_keywords' => 'máslov arašíd kakao bylink pomazánk sušenk',
            ],
            'coca_cola_zero' => [
                'keywords' => 'coca cola',
                'variant_keywords' => 'zero',
                'exclude_keywords' => null,
            ],
        ],
    ],

];
