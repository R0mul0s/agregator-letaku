<?php

/**
 * Prodejny COOP z coopclub.cz a letáky, které v každé platí (R169, ZDROJE_DAT.md, COOP).
 *
 * Seznam prodejen je jeden JSON ve stránce `/prodejny/` (~1 450 prodejen: odkaz na stránku prodejny,
 * název a adresa, bez ID — kódem je část adresy stránky). Která družstva a tedy letáky prodejna má,
 * zeměpis neříká (Terno v Hradci Králové patří družstvu z Českých Budějovic a nemá žádný leták
 * coopclub.cz, TUTY ve Dvoře Králové jen TIP/TUTY) — rozhoduje oddíl „Letákové akce v této prodejně“
 * na stránce prodejny s odkazy na její letáky. Místo seznamu akcí (Kaufland) proto prodejna vrací
 * zkratky sledovaných letáků („SČ“, „CSC“); akce letáku pak platí v prodejnách, které ho mají.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

namespace App\Domain\Sources\Coop;

use App\Domain\Offers\Parsing\Text;
use App\Domain\Sources\Exceptions\SourceResponseChanged;
use App\Domain\Sources\SourceHttp;
use App\Domain\Sources\StoreSource;
use App\Enums\Chain;

final class CoopStoreSource implements StoreSource
{
    /** Nastavení mapy prodejen v kódu stránky: `var config = '{…}';` (JSON v řetězci JavaScriptu). */
    private const CONFIG_START = "var config = '";

    /** Konec JSON nastavení (složená závorka patří k JSON). */
    private const CONFIG_END = "}';";

    /** Odkaz na stránku prodejny v titulku značky na mapě — část adresy je kód prodejny. */
    private const STORE_LINK_PATTERN = '#/prodejny/([a-z0-9-]+)/?#';

    /** Odkaz na leták na stránce prodejny: „sc-aktualni-letak“, starší „tip-extra-aktualni-letak44-2“. */
    private const LEAFLET_LINK_PATTERN = '#coopclub\.cz/letaky/([a-z0-9-]+?)-aktualni-letak[0-9-]*/#';

    /** Stránka prodejny (i bez letáku) má údaj o družstvu — bez něj je to jiná stránka. */
    private const STORE_PAGE_MARKER = 'Družstvo';

    /** Název družstva pod nadpisem „Družstvo“ („COOP Pardubice, družstvo“). */
    private const COOPERATIVE_PATTERN = '#>Družstvo</h3>\s*(?:<p>)?([^<]+)#u';

    /** Apostrof uvnitř řetězce JavaScriptu JSON nezná. */
    private const JS_APOSTROPHE = "\\'";

    /** Popis značky: „Město,Ulice číslo,PSČ“. */
    private const ADDRESS_SEPARATOR = ',';

    public function __construct(private readonly SourceHttp $http) {}

    /**
     * COOP.
     */
    public function chain(): Chain
    {
        return Chain::Coop;
    }

    /**
     * Prodejny ze značek mapy: kód = část adresy stránky prodejny, název bez dvojitých mezer,
     * město z popisu.
     */
    public function stores(): array
    {
        $page = $this->http->request($this->delay())->get(config()->string('letaky.sources.coop.stores_url'))->body();

        return $this->parseStores($page);
    }

    /**
     * Prodejny ze stránky `/prodejny/` (oddělené kvůli testům s fixture).
     *
     * @return array<string, array{name: string, city: string}>
     *
     * @throws SourceResponseChanged
     */
    public function parseStores(string $page): array
    {
        // Řetězec má ~1,3 MB — regulární výraz by narazil na limit PCRE, hranice se hledají přímo
        $start = strpos($page, self::CONFIG_START);
        $end = $start === false ? false : strpos($page, self::CONFIG_END, $start);
        $config = $start === false || $end === false ? null : json_decode(
            str_replace(self::JS_APOSTROPHE, "'", substr($page, $start + strlen(self::CONFIG_START), $end - $start - strlen(self::CONFIG_START) + 1)),
            true,
        );
        $markers = is_array($config) ? ($config['markers'] ?? null) : null;
        if (! is_array($markers)) {
            throw SourceResponseChanged::because(Chain::Coop, 'stránka prodejen bez seznamu značek mapy');
        }

        $stores = [];
        foreach ($markers as $marker) {
            $title = is_array($marker) && is_string($marker['title'] ?? null) ? $marker['title'] : '';
            $address = is_array($marker) && is_string($marker['description'] ?? null) ? $marker['description'] : '';
            $name = Text::clean(html_entity_decode(strip_tags($title)));
            $city = Text::clean(explode(self::ADDRESS_SEPARATOR, $address)[0]);
            if (preg_match(self::STORE_LINK_PATTERN, $title, $link) === 1 && $name !== null && $city !== null) {
                $stores[$link[1]] = ['name' => $name, 'city' => $city];
            }
        }

        return $stores === [] ? throw SourceResponseChanged::because(Chain::Coop, 'seznam prodejen je prázdný') : $stores;
    }

    /**
     * Zkratky sledovaných letáků, které platí v prodejně („SČ“, „CSC“); prázdné, když žádný.
     */
    public function offerKeys(string $storeCode): array
    {
        $url = sprintf(config()->string('letaky.sources.coop.store_page_url'), $storeCode);

        return $this->parseLeaflets($this->http->request($this->delay())->get($url)->body(), $storeCode);
    }

    /**
     * Letáky ze stránky prodejny (oddělené kvůli testům s fixture). Odkazy na nesledované letáky
     * (TIP, TUTY — jen obrázky) se vynechají.
     *
     * @return list<string>
     *
     * @throws SourceResponseChanged
     */
    public function parseLeaflets(string $page, string $storeCode): array
    {
        if (! str_contains($page, self::STORE_PAGE_MARKER)) {
            throw SourceResponseChanged::because(Chain::Coop, "stránka prodejny {$storeCode} bez údaje o družstvu");
        }

        $slugs = config()->array('letaky.sources.coop.leaflet_slugs');
        preg_match_all(self::LEAFLET_LINK_PATTERN, $page, $matches);
        $leaflets = [];
        foreach ($matches[1] as $slug) {
            if (is_string($slugs[$slug] ?? null)) {
                $leaflets[$slugs[$slug]] = true;
            }
        }

        // Leták VČ žádná stránka prodejny neodkazuje — prodejna bez odkazu na jakýkoli leták ho dostane
        // podle družstva z patičky letáku (TIP a TUTY téhož družstva mají vlastní leták a odkaz na něj)
        $cooperative = preg_match(self::COOPERATIVE_PATTERN, $page, $m) === 1 ? Text::clean(html_entity_decode($m[1])) : null;
        if ($matches[1] === [] && $cooperative !== null) {
            foreach (config()->array('letaky.sources.coop.leaflet_cooperatives') as $leaflet => $pattern) {
                if (is_string($pattern) && preg_match($pattern, $cooperative) === 1) {
                    $leaflets[(string) $leaflet] = true;
                }
            }
        }

        return array_map(strval(...), array_keys($leaflets));
    }

    /**
     * Pauza mezi požadavky — stránek prodejen je ~1 450, stahují se po dávkách (`store_lists_per_run`).
     */
    private function delay(): int
    {
        return config()->integer('letaky.sources.coop.store_page_delay_ms');
    }
}
