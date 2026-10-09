<?php

/**
 * Přehled uživatelů pro admina (R84, /uzivatele): kdo je online a kdy byl kdo naposledy,
 * souhrn podle aktivity a nastavení a u každého uživatele jeho nastavení (obchody, karty,
 * hlídané položky, e-maily, upozornění v telefonu). Hledání, filtr, řazení a stránkování
 * na serveru (R43).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Account\UserDirectory;
use App\Http\Requests\UsersIndexRequest;
use App\Models\User;
use App\Support\Like;
use App\Support\Pagination\PaginationLinks;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    public function __construct(private readonly UserDirectory $directory) {}

    /**
     * Souhrn a seznam uživatelů podle hledání, filtru a řazení.
     */
    public function index(UsersIndexRequest $request): Response
    {
        $query = $this->directory->filter(User::query(), $request->filter());
        foreach ($request->searchWords() as $word) {
            $pattern = Like::contains($word);
            $query->where(fn (Builder $query) => $query->where('name', 'like', $pattern)->orWhere('email', 'like', $pattern));
        }

        $total = $query->count();
        $window = $request->pageWindow(config()->integer('letaky.users.per_page'));
        $window = $window->within($window->lastPage($total));

        $users = $query
            ->with('followedChains')
            ->withCount(['watchItems', 'shoppingListItems', 'pushSubscriptions'])
            ->tap(fn (Builder $query) => $this->orderBy($query, $request->sort()))
            ->offset($window->offset())
            ->limit($window->limit())
            ->get();

        return Inertia::render('Users', [
            'indexUrl' => route('users.index', absolute: false),
            'filters' => [
                'q' => implode(' ', $request->searchWords()),
                'filter' => $request->filter(),
                'sort' => $request->sort(),
            ],
            'sorts' => UsersIndexRequest::SORTS,
            'activeDays' => UsersIndexRequest::activeDays(),
            'counts' => $this->directory->counts(),
            'onlineMinutes' => config()->integer('letaky.account.presence.online_minutes'),
            'total' => $total,
            'users' => $users->map($this->directory->present(...))->all(),
            'pagination' => PaginationLinks::for($window, $total, fn (int $page, ?int $from): string => route('users.index', [
                ...$request->listParameters(),
                ...PaginationLinks::parameters($page, $from, UsersIndexRequest::PAGE, UsersIndexRequest::FROM_PAGE),
            ], absolute: false)),
        ]);
    }

    /**
     * Profilový obrázek uživatele (R40) pro přehled — soubor je na disku local, ne veřejně.
     */
    public function avatar(User $user): StreamedResponse
    {
        $path = $user->avatar_path;
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, headers: ['Cache-Control' => 'private, max-age=31536000, immutable']);
    }

    /**
     * Řazení seznamu; při shodě novější účet první.
     *
     * @param  Builder<User>  $query
     */
    private function orderBy(Builder $query, string $sort): void
    {
        match ($sort) {
            // Kdo ještě nepřišel (null), je v MariaDB při sestupném řazení na konci
            'last_seen' => $query->orderByDesc('last_seen_at'),
            'registered' => $query->orderByDesc('created_at'),
            'name' => $query->orderBy('name'),
            'watch_items' => $query->orderByDesc('watch_items_count'),
            default => null,
        };
        $query->orderByDesc('id');
    }
}
