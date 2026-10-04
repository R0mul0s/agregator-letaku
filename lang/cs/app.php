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
        'description' => 'Slevohlídka — rychlý lovec slev. Hlídá akce z letáků obchodů Kaufland, Tesco, Albert, Lidl, Penny, Globus a Billa.',
    ],

    // Hlavička HTML pro vyhledávače a sdílení (R45, App\Support\Seo\SeoMeta). Titulek do 60 znaků,
    // popis do 160 (delší Google zkrátí, R68). Titulek veřejných stránek dostává i Vue (seoTitle).
    'seo' => [
        'pages' => [
            'home' => [
                'title' => 'Slevohlídka — akce z letáků Kauflandu, Tesca, Lidlu a dalších',
                'description' => 'Akce z letáků Kauflandu, Tesca, Albertu, Lidlu, Penny, Globusu a Billy na jednom místě. Hlídá, co kupujete, a ukáže nejnižší cenu za kilo nebo litr. Zdarma.',
            ],
            'offers' => [
                'title' => 'Všechny akce z letáků · Slevohlídka',
                'description' => 'Aktuální akce z letáků a e-shopů Kauflandu, Tesca, Lidlu, Penny, Globusu a Billy na jednom místě — s cenou za kilo nebo litr a cenou s věrnostní kartou.',
            ],
            'offers_chain' => [
                'title' => 'Aktuální akce :chain · Slevohlídka',
                'description' => 'Aktuální akce :chain na jednom místě — s cenou za kilo nebo litr a cenou s věrnostní kartou. Přehled od Slevohlídky.',
            ],
            'terms' => [
                'title' => 'Podmínky užití · Slevohlídka',
                'description' => 'Podmínky užití služby Slevohlídka — co služba dělá a co ne, správnost cen převzatých z letáků, uživatelský účet, e-maily a upozornění.',
            ],
            'privacy' => [
                'title' => 'Zásady zpracování osobních údajů · Slevohlídka',
                'description' => 'Jaké osobní údaje Slevohlídka zpracovává, proč a jak dlouho, komu je předává, jaké používá cookies a jaká máte práva.',
            ],
            'default' => [
                'title' => 'Slevohlídka',
                'description' => 'Slevohlídka — rychlý lovec slev. Hlídá akce z letáků obchodů Kaufland, Tesco, Albert, Lidl, Penny, Globus a Billa.',
            ],
        ],
        'og_image_alt' => 'Slevohlídka — rychlý lovec slev. Maskot s nákupním košíkem a cenovkou.',
        'organization_description' => 'Slevohlídka hlídá akce z letáků obchodů Kaufland, Tesco, Albert, Lidl, Penny, Globus a Billa.',
    ],

    // App\Enums\Chain
    'chains' => [
        'kaufland' => 'Kaufland',
        'tesco' => 'Tesco',
        'albert' => 'Albert',
        'lidl' => 'Lidl',
        'penny' => 'Penny',
        'globus' => 'Globus',
        'billa' => 'Billa',
    ],

    // Názvy obchodů ve 2. pádě (Chain::genitive) — „Aktuální akce Kauflandu“
    'chains_genitive' => [
        'kaufland' => 'Kauflandu',
        'tesco' => 'Tesca',
        'albert' => 'Albertu',
        'lidl' => 'Lidlu',
        'penny' => 'Penny',
        'globus' => 'Globusu',
        'billa' => 'Billy',
    ],

    // App\Enums\StoreFormat
    'store_formats' => [
        'hypermarket' => 'Hypermarket',
        'supermarket' => 'Supermarket',
    ],

    // Artisan příkazy importu
    'import' => [
        'offers_done' => ':chain — uloženo nabídek: :count',
        'offers_partial' => ':chain — uloženo nabídek: :count, ale chybějící akce se neoznačily jako stažené (:error)',
        'failed' => ':chain — chyba: :error',
        // Záznam stažení, které hosting ukončil dřív, než se uzavřelo (R57)
        'stuck' => 'Stažení nedoběhlo — proces nejspíš ukončil hosting (časový limit nebo paměť).',
        'unknown_chain' => 'Neznámý obchod nebo obchod bez zdroje „:chain“. Dostupné: :available',
        'categories_done' => 'Kategorie — uloženo: :count',
        'categories_failed' => 'Kategorie — chyba: :error',
        'stores_done' => ':chain — prodejen: :stores, seznamů akcí: :lists',
    ],

    // Společné pro všechny e-maily (resources/views/vendor/mail)
    'mail' => [
        'footer' => 'Slevohlídka — rychlý lovec slev',
        'company_id' => 'IČO :id',
    ],

    // Právní stránky (R51, resources/legal) — titulky; chybějící údaj provozovatele v textu
    'legal' => [
        'terms' => 'Podmínky užití',
        'privacy' => 'Zásady zpracování osobních údajů',
        'missing' => '[doplnit]',
    ],

    // České chybové stránky (R51, resources/views/errors) — podle kódu, jinak obecné 4xx / 5xx
    'errors' => [
        'home' => 'Zpět na úvodní stránku',
        '403' => ['title' => 'Sem nemáte přístup', 'text' => 'Na tuhle stránku nemáte oprávnění.'],
        '404' => ['title' => 'Stránka nenalezena', 'text' => 'Tahle stránka neexistuje nebo už zmizela — jako akce z minulého týdne.'],
        '419' => ['title' => 'Platnost stránky vypršela', 'text' => 'Stránka byla otevřená příliš dlouho. Načtěte ji znovu a zkuste to ještě jednou.'],
        '429' => ['title' => 'Příliš mnoho požadavků', 'text' => 'Zpomalte prosím — za chvíli to půjde znovu.'],
        '500' => ['title' => 'Něco se pokazilo', 'text' => 'Na naší straně nastala chyba. Zkuste to prosím za chvíli.'],
        '503' => ['title' => 'Probíhá údržba', 'text' => 'Slevohlídka se právě aktualizuje. Za pár minut bude zpět.'],
        '4xx' => ['title' => 'Stránku nejde zobrazit', 'text' => 'Požadavek se nepodařilo zpracovat.'],
        '5xx' => ['title' => 'Něco se pokazilo', 'text' => 'Na naší straně nastala chyba. Zkuste to prosím za chvíli.'],
    ],

    // llms.txt (R45) — popis webu pro jazykové modely
    'llms' => [
        'summary' => 'Slevohlídka je česká webová aplikace, která každý den stahuje akční nabídky z letáků a e-shopů obchodů Kaufland, Tesco, Albert, Lidl, Penny, Globus a Billa a ukazuje je přehledně na jednom místě.',
        'about' => 'Přihlášený uživatel si vybere obchody, věrnostní karty a položky, které chce hlídat (produkt z katalogu nebo vlastní slova). Slevohlídka mu pak ukáže jen akce na tyto položky, seřazené podle ceny za kilogram, litr nebo kus, a u akce řekne, jestli je to opravdu sleva oproti dřívějším cenám. Akce si uživatel přidá do nákupního seznamu rozděleného podle obchodů a v obchodě („Jsem v obchodě“) je vidí jako kompaktní seznam. O nových akcích ho Slevohlídka upozorní e-mailem nebo v telefonu. Jde přidat na plochu telefonu jako aplikace; Moje slevy a nákupní seznam fungují i bez signálu. Přehled všech aktuálních akcí je veřejný.',
        'pages_title' => 'Veřejné stránky',
        'home' => 'Úvodní stránka',
        'home_description' => 'co Slevohlídka umí a ukázka akcí s nejvyšší slevou',
        'offers' => 'Všechny akce',
        'offers_description' => 'aktuální akce všech obchodů s hledáním, filtrem obchodu, cenou za jednotku a cenou s věrnostní kartou',
        'chain' => 'Akce :chain',
        'notes_title' => 'Poznámky k datům',
        'note_prices' => 'Ceny jsou v českých korunách včetně DPH, převzaté z letáků a e-shopů obchodů; závazná je vždy cena v obchodě.',
        'note_validity' => 'U každé akce je uvedena platnost (místní datum, Europe/Prague) a odkaz na zdroj u obchodu.',
        'note_private' => 'Hlídané položky, nastavení a účty uživatelů jsou soukromé a nejsou veřejně dostupné.',
        'note_contact' => 'Kontakt na provozovatele: :email',
        'terms' => 'Podmínky užití',
        'privacy' => 'Zásady zpracování osobních údajů',
    ],

    // Úklid osobních údajů po vypršení (R53, PruneExpiredSessions)
    'maintenance' => [
        'sessions_pruned' => 'Úklid — smazáno vypršelých relací: :count',
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
        'footer' => 'Tento e-mail dostáváte, protože máte ve Slevohlídce zapnutý souhrn akcí — chodí :frequency.',
        'unsubscribe_link' => 'Vypnout souhrn',
        'settings_link' => 'Nastavení účtu',
        'done' => 'Souhrny — odesláno: :count',
        'failed' => 'Souhrny — chyba: :error',
    ],

    // Upozornění v telefonu — web push (R66, SendPushNotifications)
    'push' => [
        'title_one' => ':name je v akci',
        'title_many' => ':count nová akce na hlídané zboží|:count nové akce na hlídané zboží|:count nových akcí na hlídané zboží',
        'line' => ':name — :price, :chain',
        'more' => 'a :count další…|a :count další…|a :count dalších…',
        'test_title' => 'Upozornění fungují',
        'test_body' => 'Takhle vám dáme vědět, až bude hlídané zboží v akci.',
        'done' => 'Upozornění v telefonu — odesláno: :count',
        'failed' => 'Upozornění v telefonu — chyba: :error',
        'keys_generated' => 'Klíče VAPID — vložte je do .env (na produkci do .env na hostingu):',
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
        'back_to_top' => 'Nahoru na začátek stránky',

        'nav' => [
            'label' => 'Hlavní navigace',
            'open' => 'Otevřít menu',
            'close' => 'Zavřít menu',
            'home' => 'Moje slevy',
            'watch_items' => 'Hlídám',
            'shopping_list' => 'Seznam',
            'preferences' => 'Obchody',
            'offers' => 'Všechny akce',
            'catalog' => 'Katalog',
            // Spodní lišta záložek na telefonu (R66, TabBar.vue) — krátké názvy, ať se jich pět vejde
            'tabs_label' => 'Hlavní stránky',
            'tabs' => [
                'home' => 'Moje slevy',
                'watch_items' => 'Hlídám',
                'shopping_list' => 'Seznam',
                'preferences' => 'Obchody',
                'offers' => 'Akce',
            ],
        ],

        // Aplikace v telefonu (R66): přidání na plochu, offline režim (InstallPrompt, OfflineBar, PhoneAppSettings)
        'pwa' => [
            'install_title' => 'Slevohlídka jako aplikace',
            'install_text' => 'Přidejte si Slevohlídku na plochu telefonu — otevře se jedním klepnutím, nákupní seznam funguje i bez signálu a může vás upozornit na nové akce.',
            'install' => 'Přidat na plochu',
            'install_later' => 'Teď ne',
            'install_ios_steps' => 'V Safari klepněte dole na Sdílet (čtverec se šipkou) a pak na Přidat na plochu.',
            'install_browser_menu' => 'V menu prohlížeče zvolte Přidat na plochu nebo Nainstalovat aplikaci.',
            'installed' => 'Slevohlídku máte na ploše — běží jako aplikace.',
            'settings_title' => 'Aplikace v telefonu',
            'offline' => 'Jste offline — ukazujeme uloženou verzi.',
            'stale' => 'Slabý signál — ukazujeme uloženou verzi.',
            'fetched_at' => 'Stav z :at.',
            'offline_navigation' => 'Jste offline a tahle stránka není uložená. Moje slevy a nákupní seznam fungují i bez signálu.',
        ],

        // Stránka bez připojení (R66, resources/views/pwa/offline.blade.php)
        'offline' => [
            'title' => 'Jste offline',
            'text' => 'Tahle stránka bez signálu není k dispozici. Moje slevy a nákupní seznam máte v telefonu uložené.',
            'retry' => 'Zkusit znovu',
        ],

        // Upozornění v telefonu — web push (R66, PhoneAppSettings.vue)
        'push' => [
            'title' => 'Upozornění v telefonu',
            'hint' => 'Jakmile bude hlídané zboží v akci, telefon vám to oznámí — po stažení letáků, nejvýš jednou za hodinu a jen přes den.',
            'enable' => 'Posílat upozornění na toto zařízení',
            'test' => 'Poslat zkušební upozornění',
            'other_devices' => 'Upozornění chodí také na: :devices.',
            'unsupported' => 'Tento prohlížeč upozornění neumí. Zkuste Chrome, Edge, Firefox nebo Samsung Internet.',
            'ios_install_first' => 'Na iPhonu upozornění fungují, jen když máte Slevohlídku přidanou na plochu — pak je zapnete tady v aplikaci.',
            'denied' => 'Upozornění máte pro Slevohlídku v prohlížeči zakázaná. Povolte je v nastavení webu (ikona zámku u adresy) nebo v nastavení telefonu.',
            'failed' => 'Upozornění se nepodařilo nastavit. Zkuste to prosím znovu.',
            'invalid_endpoint' => 'Upozornění tohoto prohlížeče nepodporujeme.',
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
            'delete_confirm_title' => 'Smazat produkt?',
            'delete_confirm' => 'Produkt „:name“ se smaže i s přiřazením akcí. Kdo ho hlídá, dostane jeho pravidla jako vlastní slova.',
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
            // Příklady, které se střídají v prázdném poli (R71, lib/placeholder.js)
            'examples' => ['máslo', 'pivo', 'Coca-Cola Zero', 'káva', 'banány', 'jogurt'],
            'add_hint' => 'Vyberte produkt z katalogu. Co v katalogu není, pohlídáte vlastními slovy.',
            'own_option' => 'Hlídat „:text“ vlastními slovy',
            'own_meta' => 'Pro věc, která v katalogu není',
            'did_you_mean' => 'Nic přesně neodpovídá — nemysleli jste:',
            'no_offers_now' => 'teď bez akce',
            // Náhled vlastních slov (R71) — co by položka teď našla
            'preview_loading' => 'Hledání akcí…',
            'preview_none' => 'Teď by nenašlo žádnou akci — upozorní, až nějaká bude.',
            'preview_count' => 'Teď by našlo :count akci|Teď by našlo :count akce|Teď by našlo :count akcí',
            'preview_hint' => 'Chytá i něco jiného? Doplňte slovo do pole Vyloučit.',
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
            'browse_hint' => 'Vyberte oddělení a klepnutím na produkt ho začněte hlídat.',
            'browse_back' => 'Všechna oddělení',
            'watched_count' => 'hlídáte :count',
            'other_department' => 'Ostatní',
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
            'keywords_too_short' => 'Aspoň jedno hledané slovo musí mít :min znaky nebo víc (jedno písmeno najde skoro všechno).',
            'already_watched' => 'Tenhle produkt už hlídáte.',
            'from_catalog' => 'Z katalogu',
            'add' => 'Přidat',
            'save' => 'Uložit',
            'cancel' => 'Zrušit',
            'edit' => 'Upravit',
            'delete' => 'Smazat',
            'stop' => 'Přestat hlídat',
            'delete_confirm_title' => 'Přestat hlídat?',
            'delete_confirm' => '„:name“ zmizí z Hlídám i z Mých slev.',
            'empty' => 'Zatím nic nehlídáte. Napište nahoře, co chcete hlídat, nebo projděte katalog.',
            'limit' => 'Hlídat jde nejvýš :count položek.',
        ],

        'preferences' => [
            'title' => 'Moje obchody',
            'intro' => 'Vyberte obchody, jejichž akce chcete hlídat, a karty nebo aplikace, které máte. Akce jen s kartou, kterou nemáte, se v Mých slevách neukážou.',
            'follow' => 'Sledovat :chain',
            'followed' => 'Sledujete',
            'not_followed' => 'Akce z tohoto obchodu neuvidíte.',
            'followed_count' => 'Sledujete :count z :total obchodů|Sledujete :count z :total obchodů|Sledujete :count z :total obchodů',
            'coming_soon' => 'Připravujeme',
            'store_format' => 'Typ prodejny',
            'all_formats' => 'Všechny',
            'include_online_only' => 'Akce jen z e-shopu',
            // Výběr prodejen (R49, StoreSelect.vue)
            'stores' => 'Moje prodejny',
            'stores_hint' => 'Pultové maso, ryby a pár dalších akcí se liší po prodejnách. Bez výběru uvidíte akce všech prodejen.',
            'stores_search' => 'Hledat prodejnu nebo město',
            'stores_all' => 'Všechny prodejny',
            'stores_remove' => 'Odebrat prodejnu :store',
            'stores_none_found' => 'Žádná prodejna neodpovídá.',
            'stores_max' => 'Vybrat jde nejvýš :count prodejen.',
            'loyalty' => 'Mám :program',
            // Ukládání hned po každé změně (R64)
            'autosave' => 'Změny se ukládají hned.',
            'saving' => 'Ukládám…',
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
            'instant' => 'Hned, jak akce přibude',
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
            'muj_globus' => 'Můj Globus',
            'billa_klub' => 'BILLA Klub',
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

        // Hledání s našeptávačem (R71, SearchSuggest.vue, Offers.vue)
        'search' => [
            // Příklady, které se střídají v prázdném poli (lib/placeholder.js)
            'examples' => ['máslo', 'Coca-Cola Zero', 'pivo', 'káva', 'banány', 'pizza'],
            'try' => 'Zkuste „:example“',
            'back' => 'Zpět',
            'recent' => 'Poslední hledání',
            'clear_recent' => 'Smazat',
            'forget' => 'Zapomenout toto hledání',
            'popular' => 'Teď nejvíc v akci',
            'products' => 'Produkty z katalogu',
            'offers' => 'Akce',
            'product_offers' => ':count akce|:count akce|:count akcí',
            'from' => 'od :price',
            'show_all' => 'Zobrazit :count výsledek pro „:text“|Zobrazit všechny :count výsledky pro „:text“|Zobrazit všech :count výsledků pro „:text“',
            'corrected' => 'Výsledky pro „:text“',
            'nothing' => 'Pro „:text“ teď nic v akci není.',
            'keys_move' => 'vybrat',
            'keys_choose' => 'potvrdit',
            'keys_close' => 'zavřít',
            'discounts_only' => 'Jen slevy',
            'product_filter' => 'Produkt: :name',
            'remove_filter' => 'Zrušit filtr',
            'correction' => '„:original“ nic nenašlo — výsledky jsou pro „:corrected“.',
            'empty_text' => '„:text“ teď v akci není. Pohlídejte si to — Slevohlídka dá vědět, až bude.',
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
            // Akce, která neplatí ve všech prodejnách (R49)
            'only_in_stores' => 'Jen :stores',
            'only_in_count' => 'Jen v :count prodejně|Jen ve :count prodejnách|Jen v :count prodejnách',
            'only_in_title' => 'Akce platí jen v některých prodejnách — klepnutím jejich seznam',
            'not_in_my_stores' => 'Není ve vašich prodejnách',
            'stores_dialog_title' => 'Kde akce platí',
            'stores_dialog_count' => ':chain — :count prodejna|:chain — :count prodejny|:chain — :count prodejen',
            'stores_dialog_mine' => 'vaše prodejna',
            'stores_dialog_close' => 'Zavřít',
            'maybe' => 'Možná',
            'maybe_hint' => 'Akce je na více druhů a hledanou variantu neuvádí — ověřte u obchodu.',
            // „Je to opravdu sleva?“ (R59) — srovnání s dřívějšími akcemi stejné položky u obchodu
            // „Hlídat“ z karty ve Všech akcích (R60, WatchOfferButton.vue)
            'watch' => [
                'product' => '+ Hlídat :name',
                'own' => '+ Hlídat',
                'watched' => '✓ Hlídáte :name',
            ],
            'history' => [
                'lowest' => 'Nejlevněji za :weeks týdnů',
                'same' => 'Stejně levně jako před týdnem|Stejně levně jako před :count týdny|Stejně levně jako před :count týdny',
                'cheaper_before' => 'Před týdnem stálo :price|Před :count týdny stálo :price|Před :count týdny stálo :price',
            ],
            'source' => 'Do obchodu',
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
            // Skutečné akce místo obecných slibů (R56); počet akcí bere landing.stats.offers
            'showcase' => [
                'headline' => 'Letáky projdeme za vás.',
                'lead' => 'Řeknete, co kupujete, a Slevohlídka vám ukáže, kde je to právě nejlevnější.',
                'deals_title' => 'Právě teď ulovené',
            ],
            'logout' => 'Odhlásit se',
            'name' => 'Jméno',
            'email' => 'E-mail',
            'password' => 'Heslo',
            'password_confirmation' => 'Heslo znovu',
            'password_show' => 'Ukázat heslo',
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
                // Karta formuláře (R56): cesta ve třech krocích, nadpis s přínosem, co čekat
                'steps_label' => 'Kroky registrace',
                'steps' => [
                    'account' => 'Účet',
                    'watch' => 'Co kupujete',
                    'hunt' => 'Slevy hlídáme my',
                ],
                'heading' => 'Začněte hlídat slevy',
                'intro' => 'Zdarma a za minutu. Pak jen vyberete, co kupujete.',
                'password_hint' => 'Aspoň :min znaků.',
                'trust' => [
                    'free' => 'Zdarma',
                    'no_ads' => 'Žádné reklamy',
                    'cancel' => 'Účet zrušíte kdykoli',
                ],
                'submit' => 'Začít hlídat slevy',
                'has_account' => 'Už máte účet?',
                'login' => 'Přihlaste se',
                // Souhlasy (R51): podmínky povinné, obchodní sdělení dobrovolná a nezaškrtnutá
                'terms_before' => 'Souhlasím s',
                'terms_link' => 'podmínkami užití',
                'privacy_before' => 'Jak Slevohlídka zachází s vašimi údaji, popisují',
                'privacy_link' => 'zásady zpracování osobních údajů',
                'terms_required' => 'Bez souhlasu s podmínkami užití účet založit nejde.',
                // Ochrana proti botům (R53): skryté pole a chyba podezřelého odeslání
                'trap' => 'Nevyplňujte',
                'bot_check' => 'Registraci se nepodařilo ověřit. Načtěte prosím stránku znovu a zkuste to ještě jednou.',
                'marketing' => 'Chci dostávat e-mailem novinky o Slevohlídce a vybrané nabídky partnerů. Souhlas můžu kdykoli odvolat v účtu nebo odkazem v každém e-mailu.',
            ],

            // Lišta pro neověřený e-mail (R51, EmailVerificationBar.vue)
            'verify' => [
                'text' => 'Potvrďte prosím e-mail :email odkazem, který jsme vám poslali. Do té doby vám nepošleme souhrn akcí.',
                'resend' => 'Poslat odkaz znovu',
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

        // Úvodní stránka pro nepřihlášené (R44, Landing.vue)
        'landing' => [
            'eyebrow' => 'Rychlý lovec slev',
            'headline' => 'Slevy z letáků na to, co opravdu kupujete',
            'lead' => 'Slevohlídka každý den projde letáky a e-shopy Kauflandu, Tesca, Albertu, Lidlu, Penny, Globusu a Billy. Řeknete jí, co kupujete — a ona vám ukáže, kde je to právě ve slevě a kde nejlevněji za kilo nebo litr.',
            'register' => 'Začít zdarma',
            'browse' => 'Prohlédnout akce',
            'login_hint' => 'Už máte účet?',
            'login' => 'Přihlaste se',
            'stats' => [
                'offers' => 'akce právě teď|akce právě teď|akcí právě teď',
                'chains' => 'obchod|obchody|obchodů',
                'products' => 'produkt v katalogu|produkty v katalogu|produktů v katalogu',
                'updates' => 'aktualizace denně',
            ],
            'features_title' => 'Co Slevohlídka umí',
            'features' => [
                'watch' => ['title' => 'Hlídá, co kupujete', 'text' => 'Vyberte z katalogu máslo, pivo nebo Coca-Colu Zero, nebo napište vlastní slova. Ostatní akce vás nebudou rušit.'],
                'unit_price' => ['title' => 'Cena za kilo a litr', 'text' => 'Akce řadí podle ceny za jednotku, takže velké balení nepřebije menší, ale levnější.'],
                'cards' => ['title' => 'S vaší kartou', 'text' => 'Clubcard, Lidl Plus, Kaufland Card… Akce jen s kartou uvidíte, jen když kartu máte.'],
                'mentions' => ['title' => 'I to, co je v letáku bez ceny', 'text' => 'Když leták zmíní, co hlídáte, ale cenu z něj přečíst nejde, dostanete odkaz přímo na stránku letáku.'],
                'digest' => ['title' => 'Upozornění e-mailem', 'text' => 'Nové akce na to, co hlídáte, přijdou e-mailem hned, jak se objeví, nebo jako souhrn denně či jednou týdně — jen když je co hlásit.'],
                'free' => ['title' => 'Zdarma a bez reklam', 'text' => 'Žádné reklamní bannery, měření návštěvnosti jen s vaším souhlasem. Jen akce z letáků, seřazené tak, aby se daly porovnat.'],
            ],
            'steps_title' => 'Jak to funguje',
            'steps' => [
                'chains' => ['title' => 'Vyberte obchody', 'text' => 'Kde nakupujete, jaký typ prodejny a které karty máte.'],
                'watch' => ['title' => 'Řekněte, co hlídat', 'text' => 'Produkty z katalogu jedním klepnutím, nebo vlastní slova.'],
                'hunt' => ['title' => 'Slevohlídka loví', 'text' => 'Každé ráno projde letáky a v Mých slevách máte jen to, co vás zajímá.'],
            ],
            'top_title' => 'Právě teď nejvyšší slevy',
            'top_more' => 'Všechny akce',
            'cta_title' => 'Ať slevy loví Slevohlídka, ne vy',
            'cta_text' => 'Registrace zabere minutu a nic nestojí.',
        ],

        // Výzva k registraci nad Všemi akcemi pro nepřihlášené (R44)
        'offers_guest' => [
            'text' => 'Chcete vidět jen akce na to, co kupujete, seřazené podle ceny za kilo?',
            'register' => 'Zaregistrujte se zdarma',
        ],

        // Nákupní seznam (R61, ShoppingList.vue, ShoppingToggle.vue)
        'shopping' => [
            'title' => 'Nákupní seznam',
            'intro' => 'Akce, které chcete koupit, seřazené podle obchodu. V obchodě je odškrtávejte.',
            'empty' => 'Seznam je prázdný. Akce do něj přidáte tlačítkem „Do seznamu“ v Mých slevách nebo ve Všech akcích.',
            'add' => '+ Do seznamu',
            'added' => '✓ V seznamu',
            // Kompaktní řádek (R62): tlačítko jen s ikonou, stav nese aria-pressed
            'add_label' => 'Nákupní seznam',
            'remove' => 'Odebrat :name ze seznamu',
            'check' => 'Koupeno: :name',
            'expired' => 'akce skončila',
            'valid_to' => 'do :date',
            'remaining' => 'zbývá :count|zbývají :count|zbývá :count',
            'clear_checked' => 'Smazat odškrtnuté',
            'clear_checked_confirm_title' => 'Smazat odškrtnuté?',
            'clear_checked_confirm' => 'Odškrtnuté položky zmizí ze seznamu.',
            'clear_checked_confirm_label' => 'Smazat',
            'limit' => 'Do seznamu se vejde nejvýš :count akcí.',
            // V obchodě (R66): odškrtnutí bez signálu, poslání seznamu, nezhasínání displeje
            'pending' => 'Odškrtnutí bez signálu jsou uložená v telefonu — odešleme je, až budete online.',
            'share' => 'Poslat seznam',
            'share_line' => '– :name, :price',
            'share_empty' => 'V seznamu už nic nezbývá koupit.',
            'share_copied' => 'Seznam je zkopírovaný — vložte ho do zprávy.',
            'share_failed' => 'Seznam se nepodařilo zkopírovat.',
            'wake_lock' => 'Nezhasínat displej',
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
            'no_offers_hint' => 'Hlídáme dál — jakmile bude v akci, objeví se tady.',
            'no_offers_digest_on' => 'Dáme vědět i e-mailem, souhrn chodí :frequency.',
            'no_offers_digest_off' => 'Můžeme vám to poslat i e-mailem v denním nebo týdenním souhrnu.',
            'no_offers_digest_link' => 'Zapnout v účtu',
            'count' => ':count akce|:count akce|:count akcí',
            'edit_watch_items' => 'Upravit hlídané',
            'chain_filter' => 'Jsem v obchodě',
            // V obchodě akce jako řádky, nebo karty s obrázkem (R62)
            'view_rows' => 'Zobrazit řádky',
            'view_cards' => 'Zobrazit karty',
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
            // Sekce stránky s navigací (R63) — nadpis a vysvětlení vlevo, pole vpravo
            'nav_label' => 'Sekce účtu',
            'sections' => [
                'profile' => ['title' => 'Profil', 'hint' => 'Jak vás Slevohlídka oslovuje a kam posílá e-maily.'],
                'notifications' => ['title' => 'Upozornění', 'hint' => 'Kdy vám dáme vědět o nových akcích na hlídané zboží.'],
                'offers' => ['title' => 'Moje slevy', 'hint' => 'Jak řadit akce u každé hlídané položky a které ukazovat.'],
                'security' => ['title' => 'Zabezpečení', 'hint' => 'Heslo a zařízení, na kterých jste přihlášeni.'],
                'delete' => ['title' => 'Zrušení účtu', 'hint' => 'Smaže účet, hlídané položky, nákupní seznam i nastavení obchodů. Nejde to vrátit.'],
            ],
            'autosave' => 'Změny se ukládají hned.',
            'cancel' => 'Zpět',
            'password' => 'Změna hesla',
            'password_submit' => 'Změnit heslo',
            'current_password' => 'Současné heslo',
            'email_change_password_hint' => 'Změnu e-mailu potvrďte heslem. Na novou adresu pošleme odkaz k ověření.',
            'new_password' => 'Nové heslo',
            'save' => 'Uložit',
            'offers_sort' => 'Řadit od',
            'min_discount' => 'Ukazovat',
            'min_discount_all' => 'Všechny akce',
            'min_discount_option' => 'Jen slevy od :percent %',
            'min_discount_hint' => 'Akční ceny bez uvedené původní ceny a akce na více kusů slevu v procentech nemají — s hranicí se neukážou.',
            'digest_title' => 'E-mailový souhrn',
            'digest_hint' => 'Nové akce na hlídané zboží pošleme na :email — jen když nějaké přibudou. První e-mail ukáže všechny aktuální akce.',
            // Vysvětlení u každé volby (R63)
            'digest_options' => [
                'off' => 'Akce uvidíte jen v Mých slevách.',
                'instant' => 'Do hodiny po stažení letáků, nejvýš jednou za hodinu.',
                'daily' => 'Jeden souhrn ráno po stažení letáků.',
                'weekly' => 'Jeden souhrn za týden.',
            ],
            'digest_frequency' => 'Posílat',
            'digest_unverified' => 'Souhrn začne chodit, až potvrdíte e-mail.',
            'marketing_title' => 'Novinky a nabídky',
            'marketing_hint' => 'Občas vám pošleme novinky o Slevohlídce a vybrané nabídky partnerů. Váš e-mail nikomu nepředáme.',
            'marketing_label' => 'Chci dostávat novinky a nabídky e-mailem',
            'privacy_link' => 'Zásady zpracování osobních údajů',
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
            'logout_others_start' => 'Odhlásit ostatní zařízení…',
            'devices_only_this' => 'Jste přihlášeni jen na tomto zařízení.',
            'unknown_device' => 'Neznámé zařízení',
            'delete_submit' => 'Zrušit účet',
            'delete_start' => 'Zrušit účet…',
            'delete_confirm_title' => 'Zrušit účet?',
            'delete_confirm' => 'Účet, hlídané položky i nastavení se smažou a nepůjde to vrátit.',
            'deleted' => 'Účet je zrušený. Díky, že jste Slevohlídku vyzkoušeli.',
        ],

        // Patička (R51, AppFooter.vue) — upozornění, provozovatel, odkazy
        'footer' => [
            'about' => 'Každý den projde letáky a e-shopy sedmi obchodů a ukáže, kde je to, co kupujete, právě ve slevě — a kde nejlevněji za kilo nebo litr.',
            'nav_title' => 'Slevohlídka',
            'info_title' => 'Informace',
            'operator_title' => 'Provozovatel',
            'disclaimer' => 'Slevohlídka není oficiálním webem žádného obchodu. Názvy a loga obchodů jsou ochranné známky jejich vlastníků. Ceny jsou orientační, závazná je vždy cena v obchodě.',
            'copyright' => '© :year Slevohlídka',
            'terms' => 'Podmínky užití',
            'privacy' => 'Ochrana osobních údajů',
            'contact' => 'Kontakt',
            'cookies' => 'Nastavení cookies',
        ],

        // Souhlas s cookies (R52, CookieConsent.vue) — odmítnout stejně snadno jako přijmout
        'cookies' => [
            'title' => 'Cookies na Slevohlídce',
            'intro' => 'Nezbytné cookies Slevohlídka potřebuje, aby web fungoval. S vaším souhlasem použije i analytické cookies (Google Analytics), aby věděla, co lidé na webu používají, a marketingové pro měření reklamy. Souhlas můžete kdykoli změnit v patičce.',
            'more' => 'Více o cookies',
            'settings' => 'Nastavení',
            'accept_all' => 'Přijmout vše',
            'reject_all' => 'Odmítnout vše',
            'save' => 'Uložit výběr',
            'settings_title' => 'Nastavení cookies',
            'settings_intro' => 'Vyberte, které cookies smíme použít. Nezbytné jsou zapnuté vždy, ostatní jen s vaším souhlasem.',
            'always_on' => 'Vždy zapnuté',
            'necessary_title' => 'Nezbytné',
            'necessary_text' => 'Přihlášení, ochrana formulářů, vaše volba cookies a vzhled webu. Bez nich web nefunguje.',
            'analytics_title' => 'Analytické',
            'analytics_text' => 'Google Analytics — statistiky návštěvnosti (které stránky se čtou, z jakého zařízení), podle kterých Slevohlídku zlepšujeme.',
            'marketing_title' => 'Marketingové',
            'marketing_text' => 'Dovolí Googlu použít data z návštěvy pro měření a cílení reklamy. Reklama se na Slevohlídce zatím nezobrazuje.',
        ],

        // Právní stránky (R51, Legal.vue)
        'legal' => [
            'effective_from' => 'Účinné od :date',
            'toc' => 'Obsah',
        ],

        // E-maily, ze kterých se jde odhlásit (R51, App\Enums\MailingList)
        'mailing_lists' => [
            'souhrn' => 'souhrn akcí',
            'novinky' => 'novinky a nabídky',
        ],

        // Odhlášení z e-mailů bez přihlášení (R51, Unsubscribe.vue)
        'unsubscribe' => [
            'title' => 'Odhlášení z e-mailů',
            'confirm' => 'Opravdu už nechcete dostávat :list na :email?',
            'submit' => 'Odhlásit',
            'done' => 'Na :email už :list nechodí. E-maily znovu zapnete kdykoli v nastavení účtu.',
            'home' => 'Na úvodní stránku',
        ],

        // Potvrzovací okno nevratné akce (R47, ConfirmDialog.vue) — „Zpět“, ne „Zrušit“: vedle
        // „Zrušit účet“ by bylo matoucí
        'confirm' => [
            'cancel' => 'Zpět',
        ],

        // Potvrzení po uložení jako toast (R47, Toaster.vue): kód stavu ze session('status') => text.
        // Stav, který tu není (věta od Fortify, „Účet je zrušený…“), se ukáže tak, jak je.
        'toast' => [
            'close' => 'Zavřít zprávu',
            // Tlačítko v toastu po přidání hlídané položky (R71)
            'undo' => 'Vrátit',
            'messages' => [
                // Fortify a AccountController / AvatarController
                'profile-information-updated' => 'Osobní údaje jsou uložené.',
                'password-updated' => 'Heslo je změněné.',
                'avatar-updated' => 'Profilový obrázek je uložený.',
                'other-devices-logged-out' => 'Ostatní zařízení jsou odhlášená.',
                'offers-preferences-saved' => 'Předvolby Mých slev jsou uložené.',
                'digest-saved' => 'Nastavení souhrnu je uložené.',
                'marketing-saved' => 'Nastavení novinek je uložené.',
                // Ověření e-mailu (R51, Fortify a VerifyEmailResponse)
                'verification-link-sent' => 'Odkaz pro potvrzení e-mailu je na cestě.',
                'email-verified' => 'E-mail je potvrzený. Díky!',
                // UnsubscribeController (R51)
                'unsubscribed' => 'Hotovo, e-maily už vám posílat nebudeme.',
                // Chyby u formuláře (R51, ErrorToast)
                'session-expired' => 'Stránka byla otevřená příliš dlouho. Zkuste to prosím znovu.',
                'too-many-requests' => 'Příliš mnoho pokusů. Zkuste to prosím za chvíli.',
                // ShoppingPreferencesController
                'preferences-saved' => 'Nastavení obchodů je uložené.',
                // RegisterResponse (R55)
                'registered' => 'Vítejte! Sledujeme pro vás všechny obchody — teď vyberte, co hlídat.',
                // WatchItemController
                'watch-item-added' => 'Položka je mezi hlídanými.',
                'watch-item-updated' => 'Hlídaná položka je uložená.',
                'watch-item-removed' => 'Položku už nehlídáte.',
                // ShoppingListController (R61)
                'shopping-added' => 'Přidáno do nákupního seznamu.',
                'shopping-removed' => 'Odebráno z nákupního seznamu.',
                'shopping-cleared' => 'Odškrtnuté položky jsou pryč.',
                // PushSubscriptionController (R66)
                'push-enabled' => 'Upozornění na tomto zařízení jsou zapnutá.',
                'push-disabled' => 'Upozornění na tomto zařízení jsou vypnutá.',
                'push-test-sent' => 'Zkušební upozornění je na cestě.',
                'push-test-failed' => 'Zkušební upozornění se nepodařilo poslat. Zkuste upozornění vypnout a znovu zapnout.',
                // CatalogController
                'product-saved' => 'Produkt je uložený.',
                'product-deleted' => 'Produkt je smazaný.',
                'assignment-changed' => 'Přiřazení akce je opravené.',
            ],
        ],
    ],

];
