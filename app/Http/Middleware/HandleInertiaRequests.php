<?php

/**
 * Sdílená data pro všechny Inertia stránky.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Chain;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Kořenová Blade šablona (resources/views/app.blade.php).
     *
     * @var string
     */
    protected $rootView = 'app';

    /** Položky hlavní navigace přihlášeného uživatele: název routy => klíč textu v app.ui.nav. */
    private const NAVIGATION = [
        'home' => 'home',
        'watch-items.index' => 'watch_items',
        'preferences' => 'preferences',
        'offers' => 'offers',
    ];

    /** Položky navigace navíc pro admina (R29). */
    private const ADMIN_NAVIGATION = [
        'catalog.index' => 'catalog',
    ];

    /**
     * Sdílí s každou stránkou texty UI, jazyk a zónu, přihlášeného uživatele,
     * stavovou zprávu Fortify a navigaci.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'translations' => fn () => trans('app.ui'),
            // Názvy a loga obchodů pro ChainLogo a výběr obchodu (public/images/chains)
            'chainInfo' => fn (): array => array_combine(
                array_map(fn (Chain $chain): string => $chain->value, Chain::cases()),
                array_map(fn (Chain $chain): array => [
                    'name' => $chain->label(),
                    'logo' => asset(sprintf(config()->string('letaky.chain_logo_path'), $chain->value)),
                ], Chain::cases()),
            ),
            'locale' => app()->getLocale(),
            'timezone' => config('letaky.display_timezone'),
            'auth' => [
                'user' => $user instanceof User ? [
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatarUrl' => $user->avatarUrl(),
                ] : null,
                'logoutUrl' => route('logout', absolute: false),
                // Účet je v menu pod avatarem vpravo nahoře (R40), ne v hlavní navigaci
                'accountUrl' => route('account', absolute: false),
                'accountActive' => $request->routeIs('account'),
            ],
            // Zpráva Fortify po akci: přeložený text (odkaz na obnovu hesla odeslán)
            // nebo kód (profile-information-updated, password-updated)
            'status' => fn (): ?string => $request->session()->get('status'),
            'navigation' => fn (): array => $user === null ? [] : array_map(
                fn (string $routeName, string $labelKey): array => [
                    'url' => route($routeName, absolute: false),
                    'label' => 'nav.'.$labelKey,
                    'active' => $request->routeIs($routeName) || $request->routeIs(str_replace('.index', '.*', $routeName)),
                ],
                array_keys($this->navigation($user)),
                $this->navigation($user),
            ),
        ];
    }

    /**
     * Položky navigace uživatele: název routy => klíč textu; admin má navíc katalog.
     *
     * @return array<string, string>
     */
    private function navigation(User $user): array
    {
        return $user->is_admin ? [...self::NAVIGATION, ...self::ADMIN_NAVIGATION] : self::NAVIGATION;
    }
}
