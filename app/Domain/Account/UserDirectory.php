<?php

/**
 * Přehled uživatelů pro admina (R84): filtry (online, aktivní za N dní, nastavení), jejich
 * počty pro souhrn nahoře a data jednoho uživatele pro stránku — poslední aktivita,
 * sledované obchody, karty, hlídané položky, nákupní seznam, e-maily a upozornění v telefonu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Domain\Account;

use App\Enums\Chain;
use App\Enums\DigestFrequency;
use App\Enums\LoyaltyProgram;
use App\Http\Requests\UsersIndexRequest;
use App\Models\FollowedChain;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class UserDirectory
{
    public function __construct(private readonly UserPresence $presence) {}

    /**
     * Uživatelé podle filtru; null = všichni.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public function filter(Builder $query, ?string $filter): Builder
    {
        if ($filter !== null && str_starts_with($filter, UsersIndexRequest::ACTIVE_PREFIX)) {
            $days = (int) substr($filter, strlen(UsersIndexRequest::ACTIVE_PREFIX));

            return $query->where('last_seen_at', '>=', CarbonImmutable::now()->subDays($days));
        }

        return match ($filter) {
            'online' => $query->where('last_seen_at', '>=', $this->presence->onlineSince()),
            'telefon' => $query->whereHas('pushSubscriptions'),
            'souhrn' => $query->where('digest_frequency', '!=', DigestFrequency::Off->value),
            'novinky' => $query->withMarketingConsent(),
            'neovereni' => $query->whereNull('email_verified_at'),
            default => $query,
        };
    }

    /**
     * Počet uživatelů u každého filtru a celkem (klíč `all`) — souhrn nahoře.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = ['all' => User::query()->count()];
        foreach (UsersIndexRequest::filters() as $filter) {
            $counts[$filter] = $this->filter(User::query(), $filter)->count();
        }

        return $counts;
    }

    /**
     * Uživatel pro stránku. Potřebuje načtené followedChains a počty watch_items_count,
     * shopping_list_items_count, push_subscriptions_count.
     *
     * @return array<string, mixed>
     */
    public function present(User $user): array
    {
        $chainOrder = array_flip(array_map(fn (Chain $chain): string => $chain->value, Chain::cases()));
        $chains = $user->followedChains
            ->sortBy(fn (FollowedChain $followed): int => $chainOrder[$followed->chain->value])
            ->map(fn (FollowedChain $followed): array => [
                'chain' => $followed->chain->value,
                'detail' => $this->chainDetail($followed),
            ])
            ->values()
            ->all();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatarUrl' => $user->avatar_path === null
                ? null
                : route('users.avatar', ['user' => $user->id, 'v' => pathinfo($user->avatar_path, PATHINFO_FILENAME)], absolute: false),
            'isAdmin' => $user->is_admin,
            'emailVerified' => $user->email_verified_at !== null,
            'online' => $this->presence->isOnline($user),
            'lastSeenAt' => $user->last_seen_at?->toIso8601String(),
            'registeredAt' => $user->created_at->toIso8601String(),
            'chains' => $chains,
            'loyaltyPrograms' => ($user->loyalty_programs ?? collect())
                ->map(fn (LoyaltyProgram $program): string => $program->label())
                ->values()
                ->all(),
            'digestFrequency' => $user->digest_frequency->value,
            'marketingConsent' => $user->hasMarketingConsent(),
            'offersSort' => $user->offers_sort->value,
            'minDiscountPercent' => $user->min_discount_percent,
            'watchItems' => (int) $user->getAttribute('watch_items_count'),
            'shoppingListItems' => (int) $user->getAttribute('shopping_list_items_count'),
            'pushDevices' => (int) $user->getAttribute('push_subscriptions_count'),
        ];
    }

    /**
     * Upřesnění sledovaného obchodu: typ prodejny, počet vybraných prodejen, bez akcí e-shopu;
     * null = celý obchod.
     */
    private function chainDetail(FollowedChain $followed): ?string
    {
        $parts = [];
        if ($followed->store_format !== null) {
            $parts[] = $followed->store_format->label();
        }
        if ($followed->store_codes !== null) {
            $parts[] = trans_choice('app.ui.users.stores', count($followed->store_codes));
        }
        if (! $followed->include_online_only) {
            $parts[] = $this->withoutEshopLabel();
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Popisek obchodu sledovaného bez akcí e-shopu.
     */
    private function withoutEshopLabel(): string
    {
        return __('app.ui.users.without_eshop');
    }
}
