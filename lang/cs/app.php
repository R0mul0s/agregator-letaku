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
            'contact' => [
                'title' => 'Kontakt · Slevohlídka',
                'description' => 'Kdo Slevohlídku provozuje a jak se nám ozvat — chybná cena, nápad, spolupráce s obchody nebo dotaz k osobním údajům.',
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
        '403' => ['title' => 'Sem je vstup jen pro zasvěcené', 'text' => 'Na tuhle stránku nemáte oprávnění. Jestli si myslíte, že sem patříte, zkuste se přihlásit.'],
        '404' => ['title' => 'Tahle stránka nám utekla', 'text' => 'Hledali jsme všude, i v letácích z minulého týdne. Možná zmizela, možná tu nikdy nebyla.'],
        '419' => ['title' => 'Stránka nám trochu vystydla', 'text' => 'Byla otevřená moc dlouho. Načtěte ji znovu a zkuste to ještě jednou.'],
        '429' => ['title' => 'Pomalu, lovče slev!', 'text' => 'Tolik požadavků najednou nestíháme. Dejte nám chvilku na nádech a zkuste to znovu.'],
        '500' => ['title' => 'Tohle se nám nepovedlo', 'text' => 'Na naší straně se něco zadrhlo. Zkuste to prosím za chvilku znovu.'],
        '503' => ['title' => 'Na chvilku jsme zavřeli', 'text' => 'Doplňujeme regály — Slevohlídka bude za pár minut zpátky.'],
        '4xx' => ['title' => 'Tudy cesta nevede', 'text' => 'Požadavek se nepodařilo zpracovat. Zkuste to prosím znovu nebo jinou cestou.'],
        '5xx' => ['title' => 'Tohle se nám nepovedlo', 'text' => 'Na naší straně se něco zadrhlo. Zkuste to prosím za chvilku znovu.'],
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
        'contact' => 'Kontakt',
    ],

    // Úklid osobních údajů po vypršení (R53, PruneExpiredSessions)
    'maintenance' => [
        'sessions_pruned' => 'Úklid — smazáno vypršelých relací: :count',
    ],

    // E-mailový souhrn nových akcí (R42, App\Mail\DigestMail)
    'digest' => [
        'subject' => 'Slevohlídka: :count nová akce na hlídané zboží|Slevohlídka: :count nové akce na hlídané zboží|Slevohlídka: :count nových akcí na hlídané zboží',
        'greeting' => 'Ahoj, :name!',
        'intro' => 'Máme úlovek! Od posledního souhrnu jsme ve vašich obchodech našli tyhle akce:',
        'no_price' => 'cena v letáku',
        'valid_to' => 'do :date',
        // Akce, která ještě nezačala (R76)
        'valid_range' => 'od :from do :to',
        'more' => 'a :count další akce v Mých slevách|a :count další akce v Mých slevách|a :count dalších akcí v Mých slevách',
        'count' => ':count nová|:count nové|:count nových',
        'button' => 'Otevřít Moje slevy',
        'footer' => 'Tenhle e-mail vám posíláme, protože máte ve Slevohlídce zapnutý souhrn akcí — chodí :frequency. Ať se vám nákup vydaří!',
        'unsubscribe_link' => 'Vypnout souhrn',
        'settings_link' => 'Nastavení účtu',
        'done' => 'Souhrny — odesláno: :count',
        'failed' => 'Souhrny — chyba: :error',
    ],

    // Centrum upozornění (R74, RecordNewOffers, NotificationPresenter) — nadpisy i pro upozornění v telefonu
    'notifications' => [
        'new_offers' => [
            'title_one' => ':name je v akci',
            // Akce je nejlevnější za sledované období (PriceHistory, R59; etapa 11c)
            'title_lowest' => ':name je nejlevněji za :weeks týdnů',
            'title_many' => ':count nová akce na hlídané zboží|:count nové akce na hlídané zboží|:count nových akcí na hlídané zboží',
        ],
        'ending_soon' => [
            'title' => 'Zítra končí :count akce z vašeho seznamu|Zítra končí :count akce z vašeho seznamu|Zítra končí :count akcí z vašeho seznamu',
        ],
        // Akce, které dnes začínají a známe je dopředu (R76, RecordStartingOffers)
        'starting_today' => [
            'title' => 'Od dneška platí :count akce, na kterou čekáte|Od dneška platí :count akce, na které čekáte|Od dneška platí :count akcí, na které čekáte',
            'shopping_list' => 'Nákupní seznam',
        ],
        'done' => 'Centrum upozornění — zapsáno: :count',
        'failed' => 'Centrum upozornění — chyba: :error',
    ],

    // Končící akce z nákupního seznamu v centru upozornění (R74, RecordEndingOffers) — výstup cronu
    'ending_soon' => [
        'done' => 'Končící akce ze seznamu — zapsáno: :count',
        'failed' => 'Končící akce ze seznamu — chyba: :error',
    ],

    // Dnes začínající akce v centru upozornění (R76, RecordStartingOffers) — výstup cronu
    'starting_today' => [
        'done' => 'Dnes začínající akce — zapsáno: :count',
        'failed' => 'Dnes začínající akce — chyba: :error',
    ],

    // Upozornění v telefonu — web push (R66, SendPushNotifications)
    'push' => [
        'line' => ':name — :price, :chain',
        // Akce nejlevnější za sledované období (etapa 11c)
        'line_lowest' => ':name — :price, :chain · nejlevněji za :weeks týdnů',
        // Dovětek akce, která ještě nezačala (R76)
        'starts' => ':line · od :date',
        'more' => 'a :count další…|a :count další…|a :count dalších…',
        'test_title' => 'Upozornění fungují',
        'test_body' => 'Přesně takhle vám zaťukáme, až bude hlídané zboží v akci.',
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
            'install_text' => 'Mějte Slevohlídku po ruce na ploše telefonu — otevře se jedním klepnutím, nákupní seznam funguje i v obchodě bez signálu a o nových akcích vám dá vědět sama.',
            'install' => 'Přidat na plochu',
            'install_later' => 'Teď ne',
            'install_ios_steps' => 'V Safari klepněte dole na Sdílet (čtverec se šipkou) a pak na Přidat na plochu.',
            'install_browser_menu' => 'V menu prohlížeče zvolte Přidat na plochu nebo Nainstalovat aplikaci.',
            'installed' => 'Slevohlídku máte na ploše — lov může začít.',
            'settings_title' => 'Aplikace v telefonu',
            'offline' => 'Jste offline — ukazujeme, co jsme si uložili.',
            'stale' => 'Signál zlobí — ukazujeme uloženou verzi.',
            'fetched_at' => 'Stav z :at.',
            'offline_navigation' => 'Jste offline a tahle stránka není uložená. Moje slevy a nákupní seznam fungují i bez signálu.',
            // Nová verze po nasazení (R78, UpdateBar.vue, PhoneAppSettings.vue)
            'update_available' => 'Máme pro vás novou verzi Slevohlídky.',
            'update_apply' => 'Načíst',
            'version' => 'Verze aplikace :version',
            'update_check' => 'Zkontrolovat aktualizace',
            'update_checking' => 'Kontrolujeme…',
            'update_current' => 'Máte nejnovější verzi, nic čerstvějšího zatím nemáme.',
            'update_found' => 'Našli jsme novou verzi — hned ji načteme.',
        ],

        // Stránka bez připojení (R66, resources/views/pwa/offline.blade.php)
        'offline' => [
            'title' => 'Jste offline',
            'text' => 'Bez signálu tahle stránka nejde. Moje slevy a nákupní seznam ale máte uložené v telefonu — ty fungují i teď.',
            'retry' => 'Zkusit znovu',
        ],

        // Upozornění v telefonu — web push (R66, PhoneAppSettings.vue)
        'push' => [
            'title' => 'Upozornění v telefonu',
            'hint' => 'Jakmile bude hlídané zboží v akci, telefon vám to oznámí — po stažení letáků, nejvýš jednou za hodinu a jen přes den.',
            'enable' => 'Posílat upozornění na toto zařízení',
            'test' => 'Poslat zkušební upozornění',
            'other_devices' => 'Upozornění chodí také na: :devices.',
            'unsupported' => 'Tenhle prohlížeč upozornění bohužel neumí. Zkuste Chrome, Edge, Firefox nebo Samsung Internet.',
            'ios_install_first' => 'Na iPhonu upozornění fungují, jen když máte Slevohlídku přidanou na plochu — pak je zapnete tady v aplikaci.',
            'denied' => 'Upozornění máte pro Slevohlídku v prohlížeči zakázaná. Povolte je v nastavení webu (ikona zámku u adresy) nebo v nastavení telefonu.',
            'failed' => 'Upozornění se nepodařilo nastavit. Zkusíte to ještě jednou?',
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
            'intro' => 'Co tady zadáte, to pro vás hlídáme. Úlovky pak najdete v Mých slevách.',
            'add_label' => 'Co chcete hlídat?',
            // Příklady, které se střídají v prázdném poli (R71, lib/placeholder.js)
            'examples' => ['máslo', 'pivo', 'Coca-Cola Zero', 'káva', 'banány', 'jogurt'],
            'add_hint' => 'Vyberte produkt z katalogu. Co v katalogu chybí, pohlídáme podle vašich slov.',
            'own_option' => 'Hlídat „:text“ vlastními slovy',
            'own_meta' => 'Pro věc, která v katalogu není',
            'did_you_mean' => 'Přesně tohle nemáme — nemysleli jste:',
            'no_offers_now' => 'teď bez akce',
            // Náhled vlastních slov (R71) — co by položka teď našla
            'preview_loading' => 'Prohledáváme akce…',
            'preview_none' => 'Teď by nenašlo nic — ale hlídáme dál a dáme vědět, až se něco objeví.',
            'preview_count' => 'Teď by našlo :count akci|Teď by našlo :count akce|Teď by našlo :count akcí',
            'preview_hint' => 'Chytá i něco jiného? Doplňte slovo do pole Vyloučit.',
            'own_link' => 'Hlídat vlastními slovy',
            'watching' => 'Hlídáte',
            'list_title' => 'Hlídané položky',
            'source_own' => 'Vlastní slova',
            'offers_count' => ':count akce|:count akce|:count akcí',
            'lowest_price' => 'od :price',
            // Akce, které ještě nezačaly, v hlavičce skupiny Mých slev (R76)
            'upcoming_count' => '+ :count brzy',
            // „Vyplatí se počkat“ (R76, WaitAdvice)
            'wait_tip' => 'Vyplatí se počkat',
            'wait_tip_text' => ':chain od :date za :price (:unit_price) — o :percent % levněji než nejlevnější akce dnes.',
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
            'delete_confirm' => '„:name“ zmizí z Hlídám i z Mých slev a slevy na to vám už hlásit nebudeme.',
            'empty' => 'Zatím nic nehlídáte. Napište nahoře, na co máte chuť, nebo se projděte katalogem.',
            'limit' => 'Hlídat jde nejvýš :count položek.',
        ],

        'preferences' => [
            'title' => 'Moje obchody',
            'intro' => 'Vyberte obchody, kde nakupujete, a karty nebo aplikace, které nosíte v peněžence. Akce jen s kartou, kterou nemáte, vás pak nebudou zbytečně lákat.',
            'follow' => 'Sledovat :chain',
            'followed' => 'Sledujete',
            'not_followed' => 'Akce z tohoto obchodu vám neukážeme.',
            'followed_count' => 'Sledujete :count z :total obchodů|Sledujete :count z :total obchodů|Sledujete :count z :total obchodů',
            'coming_soon' => 'Připravujeme',
            'store_format' => 'Typ prodejny',
            'all_formats' => 'Všechny',
            // Přidá akce platné jen při nákupu online (R4) — ostatní akce jsou vidět vždy
            'include_online_only' => 'Ukazovat i akce jen pro e-shop',
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
            'saving' => 'Ukládáme…',
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
            'nothing' => 'Pro „:text“ jsme v akcích teď nic neulovili.',
            'keys_move' => 'vybrat',
            'keys_choose' => 'potvrdit',
            'keys_close' => 'zavřít',
            // Jen akce, které ještě nezačaly (R76)
            'upcoming_only' => 'Brzy začnou',
            'product_filter' => 'Produkt: :name',
            'remove_filter' => 'Zrušit filtr',
            'correction' => '„:original“ jsme nenašli, tak ukazujeme „:corrected“. Prsty někdy kliknou vedle.',
            'empty_text' => '„:text“ teď v akci není. Pohlídejte si to — dáme vědět, jakmile se objeví.',
        ],

        'offers' => [
            'title' => 'Všechny akce',
            'search' => 'Hledat',
            'search_placeholder' => 'např. vejce, mléko, Coca-Cola',
            'chain' => 'Obchod',
            'all_chains' => 'Všechny obchody',
            'submit' => 'Hledat',
            'count' => ':count nabídka|:count nabídky|:count nabídek',
            'empty' => 'Tentokrát jsme nic neulovili. Zkuste jiné slovo nebo jiný obchod.',
            'with_card' => 's kartou :program',
            'regular_price' => 'běžně :price',
            'unit_price' => ':price / :unit',
            'valid' => 'Platí :from – :to',
            // Akce, která ještě nezačala (R76) — štítek; :date jako „st 8. 10.“
            'starts_tomorrow' => 'Od zítra',
            'starts_on' => 'Od :date',
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
                'intro' => 'Zdarma a za minutu. Pak už nám jen řeknete, co kupujete.',
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
                'bot_check' => 'Registraci jsme nedokázali ověřit — skoro to vypadalo na robota. Načtěte prosím stránku znovu a zkuste to ještě jednou.',
                'marketing' => 'Chci dostávat e-mailem novinky o Slevohlídce a vybrané nabídky partnerů. Souhlas můžu kdykoli odvolat v účtu nebo odkazem v každém e-mailu.',
            ],

            // Lišta pro neověřený e-mail (R51, EmailVerificationBar.vue)
            'verify' => [
                'text' => 'Ještě jedna věc: potvrďte prosím e-mail :email odkazem, který jsme vám poslali. Do té doby vám souhrn akcí posílat nemůžeme.',
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
            'lead' => 'Každý den za vás prolistujeme letáky a e-shopy Kauflandu, Tesca, Albertu, Lidlu, Penny, Globusu a Billy. Vy nám řeknete, co kupujete — my ukážeme, kde je to zrovna ve slevě a kde nejlevněji za kilo nebo litr.',
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
                'watch' => ['title' => 'Hlídá, co kupujete', 'text' => 'Vyberte z katalogu máslo, pivo nebo Coca-Colu Zero, nebo napište vlastní slova. Ostatní akce vás rušit nebudou — slibujeme.'],
                'unit_price' => ['title' => 'Cena za kilo a litr', 'text' => 'Řadíme podle ceny za jednotku, takže vás obří balení s velkým nápisem AKCE neoblafne.'],
                'cards' => ['title' => 'S vaší kartou', 'text' => 'Clubcard, Lidl Plus, Kaufland Card… Akce jen s kartou uvidíte, jen když kartu máte.'],
                'mentions' => ['title' => 'I to, co je v letáku bez ceny', 'text' => 'Když leták zmíní, co hlídáte, ale cenu z něj přečíst nejde, dostanete odkaz přímo na stránku letáku.'],
                'digest' => ['title' => 'Upozornění e-mailem', 'text' => 'Nové akce na to, co hlídáte, přijdou e-mailem hned, jak se objeví, nebo jako souhrn denně či jednou týdně — jen když je co hlásit.'],
                'free' => ['title' => 'Zdarma a bez reklam', 'text' => 'Žádné blikající bannery, měření návštěvnosti jen s vaším souhlasem. Jen akce z letáků, seřazené tak, aby se daly porovnat.'],
            ],
            'steps_title' => 'Jak to funguje',
            'steps' => [
                'chains' => ['title' => 'Vyberte obchody', 'text' => 'Kde nakupujete, jaký typ prodejny a které karty máte.'],
                'watch' => ['title' => 'Řekněte, co hlídat', 'text' => 'Produkty z katalogu jedním klepnutím, nebo vlastní slova.'],
                'hunt' => ['title' => 'Slevohlídka loví', 'text' => 'Každý den pročeše letáky a v Mých slevách najdete jen to, co vás zajímá.'],
            ],
            'top_title' => 'Právě teď nejvyšší slevy',
            'top_more' => 'Všechny akce',
            'cta_title' => 'Ať slevy loví Slevohlídka, ne vy',
            'cta_text' => 'Registrace zabere minutu a nestojí ani korunu.',
        ],

        // Výzva k registraci nad Všemi akcemi pro nepřihlášené (R44)
        'offers_guest' => [
            'text' => 'Chcete vidět jen akce na to, co kupujete, seřazené podle ceny za kilo?',
            'register' => 'Zaregistrujte se zdarma',
        ],

        // Nákupní seznam (R61, ShoppingList.vue, ShoppingToggle.vue)
        'shopping' => [
            'title' => 'Nákupní seznam',
            'intro' => 'Akce, které chcete koupit, rozdělené podle obchodu. V obchodě je jen odškrtávejte.',
            'empty' => 'Seznam zeje prázdnotou. Akce do něj přidáte tlačítkem „Do seznamu“ v Mých slevách nebo ve Všech akcích.',
            'add' => '+ Do seznamu',
            'added' => '✓ V seznamu',
            // Kompaktní řádek (R62): tlačítko jen s ikonou, stav nese aria-pressed
            'add_label' => 'Nákupní seznam',
            'remove' => 'Odebrat :name ze seznamu',
            'check' => 'Koupeno: :name',
            'expired' => 'akce skončila',
            'valid_to' => 'do :date',
            // Akce, která ještě nezačala (R76): odškrtnutí se potvrzuje
            'starts' => 'platí až od :date',
            'upcoming_confirm_title' => 'Akce ještě neplatí',
            'upcoming_confirm' => '„:name“ bude v akci až od :date — do té doby za akční cenu v obchodě nebude. Odškrtnout i tak?',
            'upcoming_confirm_label' => 'Odškrtnout',
            'share_line_upcoming' => '– :name, :price (od :date)',
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
            'share_empty' => 'Všechno nakoupeno — v seznamu už nic nezbývá.',
            'share_copied' => 'Seznam je zkopírovaný — vložte ho do zprávy.',
            'share_failed' => 'Seznam se nepodařilo zkopírovat.',
            'wake_lock' => 'Nezhasínat displej',
        ],

        // Zprávy od nás pro admina (R74, etapa 11d, Announcements.vue)
        'announcements' => [
            'title' => 'Zprávy uživatelům',
            'intro' => 'Zpráva se objeví v centru upozornění pod zvonkem. Zpráva o službě jde všem, propagační jen těm, kdo souhlasí s novinkami.',
            'title_field' => 'Nadpis',
            'body' => 'Text',
            'url' => 'Odkaz',
            'url_hint' => 'Nepovinný. Cesta v aplikaci (/akce) nebo adresa začínající https://.',
            'url_invalid' => 'Odkaz musí být cesta v aplikaci (/akce) nebo adresa začínající https://.',
            'category' => 'Druh zprávy',
            'categories' => [
                'service' => 'O službě',
                'marketing' => 'Propagační',
            ],
            'category_hints' => [
                'service' => 'Nový obchod, změna podmínek, výpadek — dostanou všichni (:count).',
                'marketing' => 'Novinky a nabídky partnerů — jen se souhlasem s novinkami (:count), do telefonu ne.',
            ],
            'push' => 'Poslat i do telefonu',
            'push_hint' => 'Upozornění přijde s dalším během cronu souhrnů těm, kdo mají upozornění v telefonu zapnutá.',
            'push_marketing' => 'Propagační zprávu do telefonu poslat nejde — souhlas s novinkami platí jen pro e-mail.',
            'chars' => ':count / :max znaků',
            'submit' => 'Odeslat zprávu',
            'confirm_title' => 'Odeslat zprávu?',
            'confirm' => 'Zprávu dostane :count uživatel do centra upozornění. Odeslanou zprávu už nejde vzít zpět.|Zprávu dostanou :count uživatelé do centra upozornění. Odeslanou zprávu už nejde vzít zpět.|Zprávu dostane :count uživatelů do centra upozornění. Odeslanou zprávu už nejde vzít zpět.',
            'confirm_label' => 'Odeslat',
            'sent_title' => 'Odeslané zprávy',
            'sent_empty' => 'Zatím jsme nic neposlali.',
            'recipients' => ':count příjemce|:count příjemci|:count příjemců',
            'with_push' => 'i do telefonu',
        ],

        // Centrum upozornění (R74, Notifications.vue, NotificationDetail.vue, NotificationBell.vue)
        'notifications' => [
            'title' => 'Upozornění',
            'intro' => 'Všechno, na co jsme vás za posledních :days dní upozornili — i když máte upozornění v telefonu a e-mailem vypnutá.',
            'bell' => 'Upozornění',
            'bell_unread' => 'Upozornění, :count nepřečtené|Upozornění, :count nepřečtená|Upozornění, :count nepřečtených',
            'unread' => 'Nové',
            'today' => 'Dnes',
            'yesterday' => 'Včera',
            'empty_title' => 'Zatím je tu ticho',
            'empty' => 'Jakmile bude hlídané zboží v akci, najdete to tady. Hlídáme za vás každé stažení letáků.',
            'empty_link' => 'Co hlídám',
            'back' => 'Všechna upozornění',
            'ended' => 'Skončila',
            'all_ended' => 'Tyhle akce už skončily — příště buďte rychlejší než ostatní lovci slev.',
            'open_shopping_list' => 'Otevřít nákupní seznam',
            // Zpráva od nás (11d): tlačítko s odkazem ze zprávy
            'open_link' => 'Více',
        ],

        'home' => [
            'title' => 'Moje slevy',
            'hello' => 'Ahoj, :name!',
            'hero_text' => 'Tohle jsme pro vás ulovili v letácích a e-shopech obchodů, které sledujete.',
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
            'mentions_hint' => 'Leták o tom píše, ale cenu z něj přečíst neumíme — mrkněte přímo na stránku letáku.',
            'mention_maybe_hint' => 'Stránka hledanou variantu neuvádí — ověřte v letáku.',
            'mention_page' => 'Stránka :page',
            'mention_leaflet' => 'Akční leták',
            // Akce, které ještě nezačaly (R76) — sbalená sekce pod hlídanými položkami
            'upcoming_title' => 'Brzy',
            'upcoming_summary' => ':count akce, první od :date|:count akce, první od :date|:count akcí, první od :date',
            'upcoming_hint' => 'Obchody už tyhle akce zveřejnily, ale začnou až v příštích dnech — v obchodě zatím neplatí. Ráno v den začátku vám připomeneme.',
        ],

        // Menu pod avatarem vpravo nahoře (UserMenu.vue, R40)
        'user_menu' => [
            'label' => 'Účet a nastavení',
            // Jen admin (R74, 11d)
            'announcements' => 'Zprávy uživatelům',
        ],

        'account' => [
            'title' => 'Můj účet',
            // Sekce stránky s navigací (R63) — nadpis a vysvětlení vlevo, pole vpravo
            'nav_label' => 'Sekce účtu',
            'sections' => [
                'profile' => ['title' => 'Profil', 'hint' => 'Jak vás máme oslovovat a kam posílat e-maily.'],
                'notifications' => ['title' => 'Upozornění', 'hint' => 'Kdy vám dáme vědět o nových akcích na hlídané zboží.'],
                'offers' => ['title' => 'Moje slevy', 'hint' => 'Jak řadit akce u každé hlídané položky a které ukazovat.'],
                'security' => ['title' => 'Zabezpečení', 'hint' => 'Heslo a zařízení, na kterých jste přihlášeni.'],
                'delete' => ['title' => 'Zrušení účtu', 'hint' => 'Smaže účet, hlídané položky, nákupní seznam i nastavení obchodů. Vrátit to nepůjde — a bude nám smutno.'],
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
            'deleted' => 'Účet je zrušený. Díky, že jste to se Slevohlídkou zkusili — kdyby se vám zastesklo, víte, kde nás najdete.',
        ],

        // Patička (R51, AppFooter.vue) — upozornění, provozovatel, odkazy
        'footer' => [
            'about' => 'Každý den za vás prolistujeme letáky a e-shopy sedmi obchodů a ukážeme, kde je to, co kupujete, zrovna ve slevě — a kde nejlevněji za kilo nebo litr.',
            'nav_title' => 'Slevohlídka',
            'info_title' => 'Informace',
            'contact_title' => 'Kontakt',
            'disclaimer' => 'Slevohlídka není oficiálním webem žádného obchodu. Názvy a loga obchodů jsou ochranné známky jejich vlastníků. Ceny jsou orientační, závazná je vždy cena v obchodě.',
            'copyright' => '© :year Slevohlídka',
            'terms' => 'Podmínky užití',
            'privacy' => 'Ochrana osobních údajů',
            'contact' => 'Kontakt',
            'cookies' => 'Nastavení cookies',
        ],

        // Souhlas s cookies (R52, CookieConsent.vue) — odmítnout stejně snadno jako přijmout
        'cookies' => [
            'title' => 'Dáte si cookies?',
            'intro' => 'Bez nezbytných cookies by web nefungoval. S vaším souhlasem použijeme i analytické (Google Analytics), abychom věděli, co na webu používáte, a marketingové pro měření reklamy. Volbu můžete kdykoli změnit v patičce.',
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
            'analytics_text' => 'Google Analytics — statistiky návštěvnosti (které stránky se čtou, z jakého zařízení), podle kterých Slevohlídku vylepšujeme.',
            'marketing_title' => 'Marketingové',
            'marketing_text' => 'Dovolí Googlu použít data z návštěvy pro měření a cílení reklamy. Reklamu zatím nezobrazujeme.',
        ],

        // Stránka Kontakt (R72, Contact.vue) — předmět e-mailu se u témat předvyplní
        'contact' => [
            'title' => 'Kontakt',
            'lead' => 'Napište nám, zavolejte, pošlete holuba. O slevách si povídáme moc rádi — a o chybách v cenách ještě raději, protože je pak můžeme opravit.',
            'operator_title' => 'Kdo Slevohlídku provozuje',
            'company_id' => 'IČO :id',
            'registered_office' => 'se sídlem :address',
            'trade_register' => 'Fyzická osoba zapsaná v živnostenském rejstříku (:office)',
            'email' => 'E-mail',
            'phone' => 'Telefon',
            'reply' => 'Odpovídáme zpravidla do dvou pracovních dnů. Rychleji, když nesháníme slevy na kafe.',
            'topics_title' => 'S čím se ozvat',
            'write' => 'Napsat e-mail',
            'topics' => [
                'price' => [
                    'title' => 'Cena nesedí',
                    'text' => 'V obchodě je to jinak než u nás? Pošlete název akce a obchod — podíváme se na to a opravíme.',
                    'subject' => 'Slevohlídka: cena nesedí',
                ],
                'idea' => [
                    'title' => 'Nápad nebo chyba webu',
                    'text' => 'Něco nefunguje, něco by šlo udělat líp, nebo vám chybí obchod? Každý nápad si přečteme.',
                    'subject' => 'Slevohlídka: nápad',
                ],
                'chains' => [
                    'title' => 'Obchody a partneři',
                    'text' => 'Zastupujete obchod nebo značku? Rádi se domluvíme na spolupráci — a když nesouhlasíte se zobrazením svého obsahu, vyřídíme to bez zbytečného odkladu.',
                    'subject' => 'Slevohlídka: obchody a partneři',
                ],
                'privacy' => [
                    'title' => 'Osobní údaje',
                    'text' => 'Chcete vědět, co o vás víme, nebo údaje smazat? Žádost vyřídíme nejpozději do měsíce.',
                    'subject' => 'Slevohlídka: osobní údaje',
                    'link' => 'Zásady zpracování osobních údajů',
                ],
                'security' => [
                    'title' => 'Bezpečnost',
                    'text' => 'Našli jste ve Slevohlídce bezpečnostní chybu? Napište nám dřív, než o ní řeknete světu — rádi poděkujeme.',
                    'subject' => 'Slevohlídka: bezpečnost',
                ],
            ],
            'faq_title' => 'Časté otázky',
            'faq' => [
                'free' => [
                    'question' => 'Je Slevohlídka opravdu zdarma?',
                    'answer' => 'Ano. Žádné předplatné, žádné skryté poplatky, žádný háček.',
                ],
                'source' => [
                    'question' => 'Odkud berete ceny?',
                    'answer' => 'Z veřejných letáků a e-shopů obchodů. Stahujeme je dvakrát denně, takže máte přehled dřív, než doběhnete do schránky pro leták.',
                ],
                'different' => [
                    'question' => 'Proč v obchodě stojí zboží jinak?',
                    'answer' => 'Ceny přebíráme automaticky a obchody je občas změní, nebo platí jen v některých prodejnách či s kartou. Závazná je vždy cena v obchodě — a když nám nesrovnalost pošlete, opravíme ji.',
                ],
                'chains' => [
                    'question' => 'Patříte k některému obchodu?',
                    'answer' => 'Ne. Slevohlídka je nezávislá služba a s obchody nijak nespolupracuje. Názvy a loga obchodů patří jejich vlastníkům.',
                ],
                'delete' => [
                    'question' => 'Jak zruším účet?',
                    'answer' => 'V Můj účet → Zrušení účtu. Smaže se hned i se vším, co k němu patří.',
                ],
            ],
            'documents' => 'Podrobnosti najdete v dokumentech',
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
            'done' => 'Hotovo — na :email už :list neposíláme. Kdyby se vám zastesklo, zapnete si to v nastavení účtu.',
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
                'announcement-sent' => 'Zpráva je odeslaná do centra upozornění.',
                // Ověření e-mailu (R51, Fortify a VerifyEmailResponse)
                'verification-link-sent' => 'Odkaz pro potvrzení e-mailu je na cestě.',
                'email-verified' => 'E-mail je potvrzený. Díky!',
                // UnsubscribeController (R51)
                'unsubscribed' => 'Hotovo, e-maily už vám posílat nebudeme.',
                // Chyby u formuláře (R51, ErrorToast)
                'session-expired' => 'Stránka byla otevřená moc dlouho. Zkuste to prosím znovu.',
                'too-many-requests' => 'Moc pokusů najednou. Dejte tomu chvilku a zkuste to znovu.',
                // ShoppingPreferencesController
                'preferences-saved' => 'Nastavení obchodů je uložené.',
                // RegisterResponse (R55)
                'registered' => 'Vítejte na palubě! Sledujeme pro vás všechny obchody — teď vyberte, co hlídat.',
                // WatchItemController
                'watch-item-added' => 'Hotovo, hlídáme to pro vás.',
                'watch-item-updated' => 'Hlídaná položka je uložená.',
                'watch-item-removed' => 'Už to nehlídáme.',
                // ShoppingListController (R61)
                'shopping-added' => 'Přidáno do nákupního seznamu.',
                'shopping-removed' => 'Odebráno z nákupního seznamu.',
                'shopping-cleared' => 'Odškrtnuté položky jsme uklidili.',
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
