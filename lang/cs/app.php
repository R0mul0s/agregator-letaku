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

    // Společné pro všechny e-maily (resources/views/vendor/mail)
    'mail' => [
        'footer' => 'Slevohlídka — rychlý lovec slev',
    ],

    // E-mailový souhrn nových akcí (R42, App\Mail\DigestMail)
    'digest' => [
        'subject' => 'Slevohlídka: :count nová akce na hlídané zboží|Slevohlídka: :count nové akce na hlídané zboží|Slevohlídka: :count nových akcí na hlídané zboží',
        'greeting' => 'Ahoj, :name!',
        'intro' => 'Od posledního souhrnu Slevohlídka ulovila tyhle akce ve vašich obchodech:',
        'no_price' => 'cena v letáku',
        'valid_to' => 'do :date',
        'more' => 'a :count další akce v Mých slevách|a :count další akce v Mých slevách|a :count dalších akcí v Mých slevách',
        'count' => ':count nová|:count nové|:count nových',
        'button' => 'Otevřít Moje slevy',
        'footer' => 'Souhrn chodí :frequency.',
        'settings_link' => 'Změnit nebo vypnout',
        'done' => 'Souhrny — odesláno: :count',
        'failed' => 'Souhrny — chyba: :error',
    ],

    // Hlídání stahování (/health/imports) — prostý text pro monitoring
    'health' => [
        'ok' => ':chain — OK, naposledy :at',
        'outage' => ':chain — VÝPADEK: poslední úspěšné stažení :at',
        'never' => 'nikdy',
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
            'catalog' => 'Katalog',
        ],

        'catalog' => [
            'title' => 'Katalog produktů',
            'intro' => 'Produkty, které jde hlídat. Akce se k nim přiřazují podle slov při každém stažení; přiřazení jde ručně opravit v detailu produktu.',
            'empty' => 'Katalog je zatím prázdný.',
            'close_form' => 'Zavřít formulář',
            'search' => 'Hledat v katalogu',
            'search_placeholder' => 'název, kategorie nebo slovo',
            'department' => 'Oddělení',
            'all_departments' => 'Všechna oddělení',
            'count' => ':count produkt|:count produkty|:count produktů',
            'no_results' => 'Hledání neodpovídá žádný produkt.',
            'load_more' => 'Načíst další :count produkt|Načíst další :count produkty|Načíst dalších :count produktů',
            'columns' => [
                'name' => 'Produkt',
                'category' => 'Kategorie',
                'keywords' => 'Hledaná slova',
                'offers' => 'Akce',
                'watchers' => 'Hlídá',
            ],
            'exclude_count' => 'vylučuje :count slovo|vylučuje :count slova|vylučuje :count slov',
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
            'intro' => 'Co hlídáte, to se ukáže v Mých slevách.',
            'add_label' => 'Co chcete hlídat?',
            'add_placeholder' => 'např. máslo, pivo, Coca-Cola Zero',
            'add_hint' => 'Vyberte produkt z katalogu. Co v katalogu není, pohlídáte vlastními slovy.',
            'own_option' => 'Hlídat „:text“ vlastními slovy',
            'own_link' => 'Hlídat vlastními slovy',
            'watching' => 'Hlídáte',
            'list_title' => 'Hlídané položky',
            'source_own' => 'Vlastní slova',
            'offers_count' => ':count akce|:count akce|:count akcí',
            'lowest_price' => 'od :price',
            'no_offers' => 'Teď v akci není',
            'mentions_count' => ':count zmínka v letáku|:count zmínky v letáku|:count zmínek v letáku',
            'show_offers' => 'Zobrazit v Mých slevách',
            'browse_title' => 'Procházet katalog',
            'browse_count' => ':count produkt|:count produkty|:count produktů',
            'all_departments' => 'Vše',
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
            'empty' => 'Zatím nic nehlídáte. Napište nahoře, co chcete hlídat, nebo projděte katalog.',
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

        // App\Enums\OffersSort — řazení v Mých slevách (R41)
        'offers_sort' => [
            'unit_price' => 'nejnižší ceny za jednotku',
            'discount' => 'nejvyšší slevy',
            'ending_soon' => 'konce platnosti',
        ],

        // App\Enums\DigestFrequency — e-mailový souhrn (R42)
        'digest_frequency' => [
            'off' => 'Neposílat',
            'daily' => 'Denně',
            'weekly' => 'Jednou týdně',
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
            'source' => 'Do obchodu',
            'suggestion_product' => 'katalog',
            'pagination' => 'Stránkování',
            'previous' => 'Předchozí',
            'next' => 'Další',
            'load_more' => 'Načíst další :count akci|Načíst další :count akce|Načíst dalších :count akcí',
            'shown' => 'Zobrazeno :from–:to z :total',
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
            'expand_all' => 'Rozbalit vše',
            'collapse_all' => 'Sbalit vše',
            'sorted_by' => 'Řazeno od :sort',
            'min_discount_note' => 'jen slevy od :percent %',
            'change_preferences' => 'Změnit',
            'mentions_title' => 'V letáku, ale bez ceny',
            'mentions_hint' => 'Leták obsahuje slova položky, cenu z něj ale přečíst neumíme — podívejte se na stránku letáku.',
            'mention_maybe_hint' => 'Stránka hledanou variantu neuvádí — ověřte v letáku.',
            'mention_page' => 'Stránka :page',
            'mention_leaflet' => 'Akční leták',
        ],

        // Menu pod avatarem vpravo nahoře (UserMenu.vue, R40)
        'user_menu' => [
            'label' => 'Účet a nastavení',
        ],

        'account' => [
            'title' => 'Můj účet',
            'profile' => 'Osobní údaje',
            'password' => 'Změna hesla',
            'current_password' => 'Současné heslo',
            'new_password' => 'Nové heslo',
            'save' => 'Uložit',
            'offers_title' => 'Moje slevy',
            'offers_hint' => 'Jak řadit akce u každé hlídané položky a které ukazovat.',
            'offers_sort' => 'Řadit od',
            'min_discount' => 'Ukazovat',
            'min_discount_all' => 'Všechny akce',
            'min_discount_option' => 'Jen slevy od :percent %',
            'min_discount_hint' => 'Akční ceny bez uvedené původní ceny a akce na více kusů slevu v procentech nemají — s hranicí se neukážou.',
            'digest_title' => 'E-mailový souhrn',
            'digest_hint' => 'Ráno po stažení letáků pošleme na :email nové akce na hlídané zboží — jen když nějaké přibudou. První souhrn ukáže všechny aktuální akce.',
            'digest_frequency' => 'Posílat',
            'avatar' => 'Profilový obrázek',
            'avatar_hint' => 'Obrázek se ořízne na čtverec. Bez obrázku se ukazují iniciály.',
            'avatar_upload' => 'Nahrát obrázek',
            'avatar_change' => 'Změnit obrázek',
            'avatar_remove' => 'Odebrat',
            'avatar_unreadable' => 'Obrázek se nepodařilo načíst — zkuste JPG nebo PNG.',
            'devices' => 'Přihlášená zařízení',
            'devices_hint' => 'Kde jste teď přihlášeni. Když jste se přihlásili na cizím počítači, odhlaste ostatní zařízení.',
            'devices_empty' => 'Seznam přihlášení teď není k dispozici.',
            'this_device' => 'Toto zařízení',
            'last_active' => 'naposledy :at',
            'confirm_password' => 'Heslo pro potvrzení',
            'logout_others' => 'Odhlásit ostatní zařízení',
            'unknown_device' => 'Neznámé zařízení',
            'delete_title' => 'Zrušení účtu',
            'delete_hint' => 'Smaže účet, hlídané položky i nastavení obchodů. Nejde to vrátit.',
            'delete_submit' => 'Zrušit účet',
            'delete_confirm' => 'Opravdu zrušit účet? Hlídané položky a nastavení se smažou a nepůjdou vrátit.',
            'deleted' => 'Účet je zrušený. Díky, že jste Slevohlídku vyzkoušeli.',
            // Kódy stavu po uložení (Fortify a AccountController / AvatarController)
            'status' => [
                'profile-information-updated' => 'Osobní údaje jsou uložené.',
                'password-updated' => 'Heslo je změněné.',
                'avatar-updated' => 'Profilový obrázek je uložený.',
                'other-devices-logged-out' => 'Ostatní zařízení jsou odhlášená.',
                'offers-preferences-saved' => 'Předvolby Mých slev jsou uložené.',
                'digest-saved' => 'Nastavení souhrnu je uložené.',
            ],
        ],
    ],

];
