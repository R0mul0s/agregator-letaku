<?php

/**
 * Service worker aplikace v telefonu (R66) a stránka „Jste offline“. Skript service workeru
 * je v resources/pwa/service-worker.js (mimo Vite — musí mít stálou adresu /sw.js v kořeni
 * webu); server před něj doplní nastavení: verzi a soubory buildu k uložení podle
 * public/build/manifest.json. Každý nový build změní verzi, prohlížeč service worker
 * aktualizuje a staré soubory z cache smaže. Verzi assetů (stejnou jako Inertia) service worker
 * řekne otevřené stránce — ta podle ní pozná, že běží se starým buildem (R78).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-04
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\Chain;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use JsonException;

class ServiceWorkerController extends Controller
{
    /** Zdroj skriptu service workeru. */
    private const SCRIPT_PATH = 'pwa/service-worker.js';

    /** Soubory buildu Vite — z jejich seznamu se skládá cache. */
    private const BUILD_DIRECTORY = 'build';

    private const BUILD_MANIFEST = 'build/manifest.json';

    /**
     * Písmo se ukládá jen v sadách znaků pro češtinu — azbuka a vietnamština by jen zabíraly místo
     * (stáhnou se samy, kdyby je stránka potřebovala).
     */
    private const FONT_SUBSETS = ['-latin-wght-', '-latin-ext-wght-'];

    private const FONT_EXTENSION = '.woff2';

    /** Obrázky a skripty mimo build, které stránky potřebují i offline. */
    private const STATIC_FILES = [
        '/theme-init.js',
        '/images/brand/logo-mark.png',
        '/images/brand/icon-192.png',
        '/images/brand/badge-96.png',
    ];

    /**
     * Skript service workeru s nastavením. Prohlížeč se na něj ptá při každé návštěvě
     * (no-cache), aby novou verzi poznal hned po nasazení.
     */
    public function script(Request $request, HandleInertiaRequests $inertia): Response
    {
        $manifestPath = public_path(self::BUILD_MANIFEST);
        abort_unless(is_file($manifestPath), Response::HTTP_NOT_FOUND);

        $manifest = (string) file_get_contents($manifestPath);
        $source = (string) file_get_contents(resource_path(self::SCRIPT_PATH));

        $config = [
            'version' => substr(hash('sha256', $manifest.$source), 0, 16),
            // Verze assetů jako u Inertie — stránka s jinou běží se starým buildem (R78)
            'assetVersion' => $inertia->version($request),
            'precache' => [...$this->buildFiles($manifest), ...self::STATIC_FILES, ...$this->chainLogos(), route('offline', absolute: false)],
            'offlineUrl' => route('offline', absolute: false),
            'offlinePaths' => config()->array('letaky.pwa.offline_paths'),
            'networkTimeoutMs' => config()->integer('letaky.pwa.network_timeout_ms'),
            'notification' => [
                'icon' => '/images/brand/icon-192.png',
                'badge' => '/images/brand/badge-96.png',
            ],
        ];

        return response('self.SW_CONFIG = '.json_encode($config, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).";\n\n".$source, Response::HTTP_OK, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }

    /**
     * Stránka bez připojení — service worker ji ukáže místo stránky, kterou nemá uloženou.
     */
    public function offline(): View
    {
        return view('pwa.offline', [
            'pages' => array_map(fn (string $route): array => [
                'url' => route($route, absolute: false),
                'label' => __('app.ui.nav.'.($route === 'home' ? 'home' : 'shopping_list')),
            ], ['home', 'shopping-list.index']),
        ]);
    }

    /**
     * JS a CSS z buildu a písmo pro češtinu, jako adresy od kořene webu.
     *
     * @return list<string>
     */
    private function buildFiles(string $manifest): array
    {
        try {
            /** @var array<string, array{file: string, css?: list<string>, assets?: list<string>}> $entries */
            $entries = json_decode($manifest, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $files = [];
        foreach ($entries as $entry) {
            foreach ([$entry['file'], ...($entry['css'] ?? []), ...($entry['assets'] ?? [])] as $file) {
                $files[$file] = true;
            }
        }

        return array_values(array_map(
            fn (string $file): string => '/'.self::BUILD_DIRECTORY.'/'.$file,
            array_filter(array_keys($files), fn (string $file): bool => $this->isPrecached($file)),
        ));
    }

    /**
     * Ukládá se všechno kromě písma v sadách znaků, které čeština nepotřebuje.
     */
    private function isPrecached(string $file): bool
    {
        if (! str_ends_with($file, self::FONT_EXTENSION)) {
            return true;
        }

        foreach (self::FONT_SUBSETS as $subset) {
            if (str_contains($file, $subset)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Loga obchodů (public/images/chains) — nákupní seznam a Moje slevy je ukazují u každé akce.
     *
     * @return list<string>
     */
    private function chainLogos(): array
    {
        return array_map(
            fn (Chain $chain): string => '/'.sprintf(config()->string('letaky.chain_logo_path'), $chain->value),
            Chain::cases(),
        );
    }
}
