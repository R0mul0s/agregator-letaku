<?php

/**
 * Texty aplikace Slevohlídka.
 *
 * Skupina `ui` se sdílí do Vue přes Inertia (HandleInertiaRequests) a čte se
 * helperem `t()` z resources/js/lib/i18n.js.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

return [

    'meta' => [
        'description' => 'Slevohlídka — rychlý lovec slev. Hlídá akce z letáků obchodů Kaufland, Tesco, Albert, Lidl a Penny.',
    ],

    // App\Enums\Chain
    'chains' => [
        'kaufland' => 'Kaufland',
        'tesco' => 'Tesco',
        'albert' => 'Albert',
        'lidl' => 'Lidl',
        'penny' => 'Penny',
    ],

    // App\Enums\StoreFormat
    'store_formats' => [
        'hypermarket' => 'Hypermarket',
        'supermarket' => 'Supermarket',
    ],

    // Artisan příkazy importu
    'import' => [
        'offers_done' => ':chain — uloženo nabídek: :count',
        'failed' => ':chain — chyba: :error',
        'unknown_chain' => 'Neznámý obchod nebo obchod bez zdroje „:chain“. Dostupné: :available',
        'categories_done' => 'Kategorie — uloženo: :count',
        'categories_failed' => 'Kategorie — chyba: :error',
    ],

    'admin' => [
        'unknown_user' => 'Účet s e-mailem :email neexistuje.',
        'granted' => ':email teď spravuje katalog produktů.',
        'revoked' => ':email už katalog produktů nespravuje.',
    ],

    'ui' => [
        'app_name' => 'Slevohlídka',
        // Název v hlavičce ve dvou barvách jako v logu (resources/js/Layouts/AppLayout.vue)
        'brand' => [
            'first' => 'Slevo',
            'second' => 'hlídka',
            'tagline' => 'Rychlý lovec slev',
        ],
        'skip_to_content' => 'Přeskočit na obsah',

        'nav' => [
            'label' => 'Hlavní navigace',
            'home' => 'Moje slevy',
            'watch_items' => 'Hlídám',
            'preferences' => 'Obchody',
            'offers' => 'Všechny akce',
            'account' => 'Účet',
            'catalog' => 'Katalog',
        ],

        'catalog' => [
            'title' => 'Katalog produktů',
            'intro' => 'Produkty, které jde hlídat. Akce se k nim přiřazují podle slov při každém stažení; přiřazení jde ručně opravit v detailu produktu.',
            'empty' => 'Katalog je zatím prázdný.',
            'name' => 'Název',
            'name_hint' => 'Co člověk hledá, bez ohledu na obchod: „Polotučné mléko“, „Coca-Cola Zero“.',
            'category' => 'Kategorie',
            'category_hint' => 'Nepovinné. Strom kategorií e-shopu Tesco.',
            'category_filter' => 'Zúžit kategorie…',
            'no_category' => '— bez kategorie —',
            'match_count' => ':count akce|:count akce|:count akcí',
            'maybe_count' => '+ :count možná|+ :count možná|+ :count možná',
            'add_title' => 'Nový produkt',
            'add' => 'Přidat',
            'edit_title' => 'Pravidla produktu',
            'edit_hint' => 'Po uložení se akce k produktu přiřadí znovu; ruční opravy zůstanou.',
            'save' => 'Uložit',
            'delete' => 'Smazat produkt',
            'delete_confirm' => 'Smazat produkt „:name“ i s přiřazením akcí?',
            'back' => '← Katalog',
            'assigned' => 'Přiřazené akce',
            'assigned_empty' => 'K produktu teď nepatří žádná akce.',
            'manual' => 'Ručně',
            'exclude' => 'Sem nepatří',
            'excluded' => 'Vyřazené akce',
            'restore' => 'Vrátit',
            'add_offer' => 'Přiřadit akci ručně',
            'include' => 'Sem patří',
        ],

        'watch' => [
            'title' => 'Hlídám',
            'intro' => 'Co hlídáte, to se ukáže v Mých slevách. Nejjednodušší je vybrat produkt z katalogu; co v katalogu není, najdete vlastními slovy.',
            'catalog_title' => 'Z katalogu',
            'catalog_hint' => 'Klepnutím produkt začnete hlídat. Akce k němu hledají sdílená pravidla katalogu.',
            'catalog_filter' => 'Hledat v katalogu…',
            'catalog_empty' => 'V katalogu nic takového není — zkuste vlastní hledání.',
            'watching' => 'Hlídáte',
            'own_title' => 'Vlastní hledání',
            'own_hint' => 'Pro věc, která v katalogu není. Položka najde akce, ve kterých jsou všechna hledaná slova; diakritika ani velká písmena nehrají roli a slovo stačí jako začátek („vejce“ najde i „vejcem“).',
            'name' => 'Název',
            'name_hint' => 'Jak se položka ukáže v Mých slevách.',
            'keywords' => 'Hledaná slova',
            'keywords_hint' => 'Všechna musí být v názvu nebo popisu akce. Alternativy oddělte svislítkem: „mléko polotučné|1,5“.',
            'variant_keywords' => 'Varianta',
            'variant_keywords_hint' => 'Nepovinné. Když ji akce neuvádí, ale je na „různé druhy“, ukáže se jako MOŽNÁ.',
            'exclude_keywords' => 'Vyloučit',
            'exclude_keywords_hint' => 'Nepovinné. Akce s kterýmkoli z těchto slov se neukáže.',
            'product' => 'Produkt z katalogu',
            'keywords_or_product' => 'Zadejte hledaná slova, nebo vyberte produkt z katalogu.',
            'already_watched' => 'Tenhle produkt už hlídáte.',
            'from_catalog' => 'Z katalogu',
            'add' => 'Přidat',
            'save' => 'Uložit',
            'cancel' => 'Zrušit',
            'edit' => 'Upravit',
            'delete' => 'Smazat',
            'stop' => 'Přestat hlídat',
            'delete_confirm' => 'Opravdu přestat hlídat „:name“?',
            'empty' => 'Zatím nic nehlídáte. Vyberte produkt z katalogu vpravo.',
            'limit' => 'Hlídat jde nejvýš :count položek.',
        ],

        'preferences' => [
            'title' => 'Moje obchody',
            'intro' => 'Vyberte obchody, jejichž akce chcete hlídat, a karty nebo aplikace, které máte. Akce jen s kartou, kterou nemáte, se v Mých slevách neukážou.',
            'follow' => 'Sledovat',
            'coming_soon' => 'Připravujeme',
            'store_format' => 'Typ prodejny',
            'all_formats' => 'Všechny',
            'include_online_only' => 'Ukazovat i akce jen z e-shopu',
            'loyalty' => 'Mám :program',
            'save' => 'Uložit',
            'saved' => 'Nastavení je uložené.',
        ],

        // App\Enums\OfferType
        'offer_types' => [
            'discount' => 'Sleva',
            'promo_price' => 'Akční cena',
            'loyalty_only' => 'Jen s kartou',
            'multibuy' => 'Akce na více kusů',
        ],

        // App\Enums\LoyaltyProgram
        'loyalty_programs' => [
            'kaufland_card' => 'Kaufland Card',
            'clubcard' => 'Clubcard',
            'muj_albert' => 'Můj Albert',
            'lidl_plus' => 'Lidl Plus',
            'penny_karta' => 'PENNY karta',
        ],

        // App\Enums\PackageUnit::unitPriceKey()
        'unit_price_units' => [
            'kg' => 'kg',
            'l' => 'l',
            'ks' => 'ks',
        ],

        // Jednotky balení (App\Enums\PackageUnit) a větší jednotky od tisíce (resources/js/lib/format.js)
        'package_units' => [
            'g' => 'g',
            'kg' => 'kg',
            'ml' => 'ml',
            'l' => 'l',
            'ks' => 'ks',
        ],

        'offers' => [
            'title' => 'Všechny akce',
            'search' => 'Hledat',
            'search_placeholder' => 'např. vejce, mléko, Coca-Cola',
            'chain' => 'Obchod',
            'all_chains' => 'Všechny obchody',
            'submit' => 'Hledat',
            'count' => ':count nabídka|:count nabídky|:count nabídek',
            'empty' => 'Žádná aktuální akce neodpovídá hledání.',
            'with_card' => 's kartou :program',
            'regular_price' => 'běžně :price',
            'unit_price' => ':price / :unit',
            'valid' => 'Platí :from – :to',
            'online_only' => 'Jen e-shop',
            'maybe' => 'Možná',
            'maybe_hint' => 'Akce je na více druhů a hledanou variantu neuvádí — ověřte u obchodu.',
            'source' => 'U obchodu',
            'suggestion_product' => 'katalog',
            'pagination' => 'Stránkování',
            'previous' => 'Předchozí',
            'next' => 'Další',
            'page' => 'Strana :current z :last',
        ],

        'theme' => [
            'label' => 'Vzhled',
            'light' => 'Světlý',
            'dark' => 'Tmavý',
            'system' => 'Podle systému',
        ],

        'auth' => [
            // Panel vedle přihlášení a registrace (AuthShowcase.vue)
            'showcase' => [
                'chains' => 'Hlídá akce v Kauflandu, Tescu, Lidlu a Penny — z letáků i e-shopů.',
                'unit_price' => 'Řadí podle ceny za kilo, litr nebo kus, s vaší věrnostní kartou.',
                'mentions' => 'Najde i to, co je v letáku bez ceny.',
            ],
            'logout' => 'Odhlásit se',
            'name' => 'Jméno',
            'email' => 'E-mail',
            'password' => 'Heslo',
            'password_confirmation' => 'Heslo znovu',
            'remember' => 'Zapamatovat si mě',

            'login' => [
                'title' => 'Přihlášení',
                'submit' => 'Přihlásit se',
                'forgot' => 'Zapomenuté heslo',
                'no_account' => 'Ještě nemáte účet?',
                'register' => 'Zaregistrujte se',
            ],

            'register' => [
                'title' => 'Registrace',
                'submit' => 'Zaregistrovat se',
                'has_account' => 'Už máte účet?',
                'login' => 'Přihlaste se',
            ],

            'forgot' => [
                'title' => 'Zapomenuté heslo',
                'intro' => 'Zadejte e-mail účtu a pošleme vám odkaz pro nastavení nového hesla.',
                'submit' => 'Poslat odkaz',
                'back' => 'Zpět na přihlášení',
            ],

            'reset' => [
                'title' => 'Nové heslo',
                'submit' => 'Nastavit heslo',
            ],
        ],

        'home' => [
            'title' => 'Moje slevy',
            'hello' => 'Ahoj, :name!',
            'hero_text' => 'Tohle Slevohlídka ulovila v letácích a e-shopech obchodů, které sledujete.',
            'stat_items' => 'hlídaná položka|hlídané položky|hlídaných položek',
            'stat_offers' => 'akce|akce|akcí',
            'stat_best' => 'nejvyšší sleva',
            'no_chains' => 'Nejdřív vyberte obchody, které chcete sledovat.',
            'no_chains_link' => 'Vybrat obchody',
            'no_watch_items' => 'Zatím nic nehlídáte.',
            'no_watch_items_link' => 'Přidat hlídanou položku',
            'no_offers' => 'Teď v akci není.',
            'count' => ':count akce|:count akce|:count akcí',
            'edit_watch_items' => 'Upravit hlídané',
            'mentions_title' => 'V letáku, ale bez ceny',
            'mentions_hint' => 'Leták obsahuje slova položky, cenu z něj ale přečíst neumíme — podívejte se na stránku letáku.',
            'mention_maybe_hint' => 'Stránka hledanou variantu neuvádí — ověřte v letáku.',
            'mention_page' => 'Stránka :page',
            'mention_leaflet' => 'Akční leták',
        ],

        'account' => [
            'title' => 'Účet',
            'profile' => 'Osobní údaje',
            'password' => 'Změna hesla',
            'current_password' => 'Současné heslo',
            'new_password' => 'Nové heslo',
            'save' => 'Uložit',
            // Kódy stavu, které Fortify vrací po uložení
            'status' => [
                'profile-information-updated' => 'Osobní údaje jsou uložené.',
                'password-updated' => 'Heslo je změněné.',
            ],
        ],
    ],

];
