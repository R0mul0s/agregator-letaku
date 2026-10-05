<?php

/**
 * Zpráva od nás do centra upozornění (R74, etapa 11d). Zpráva o službě jde všem uživatelům,
 * propagační jen těm s platným souhlasem s obchodními sděleními (R51, R69). Záznamy se zapíšou
 * hromadně po dávkách (stovky uživatelů = jednotky dotazů); do telefonu je pak pošle cron
 * souhrnů (SendPushNotifications), pokud zpráva o službě má zaškrtnuté „i do telefonu“.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Notifications\AnnouncementRecord;
use App\Enums\AnnouncementCategory;
use App\Enums\NotificationKind;
use App\Models\Announcement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SendAnnouncement
{
    /** Kolik záznamů zapsat jedním dotazem. */
    private const INSERT_CHUNK = 500;

    /**
     * Uloží zprávu a zapíše ji příjemcům do centra; vrátí uloženou zprávu s počtem příjemců.
     *
     * @param  array{title: string, body: string, url: string|null, category: AnnouncementCategory, push: bool}  $data
     */
    public function __invoke(User $author, array $data): Announcement
    {
        return DB::transaction(function () use ($author, $data): Announcement {
            $announcement = Announcement::query()->create([
                ...$data,
                'user_id' => $author->id,
                // Propagační zpráva do telefonu nesmí — souhlas zní na e-mail
                'push' => $data['push'] && $data['category']->allowsPush(),
            ]);

            $payload = json_encode(AnnouncementRecord::payload($announcement), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $now = CarbonImmutable::now();
            $recipients = 0;

            $this->recipients($announcement->category)->chunkById(self::INSERT_CHUNK, function (Collection $users) use ($payload, $now, &$recipients): void {
                DatabaseNotification::query()->insert($users->map(fn (User $user): array => [
                    'id' => (string) Str::uuid(),
                    'type' => NotificationKind::Announcement->value,
                    'notifiable_type' => $user->getMorphClass(),
                    'notifiable_id' => $user->id,
                    'data' => $payload,
                    'read_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
                $recipients += $users->count();
            });

            $announcement->update(['recipients' => $recipients]);

            return $announcement;
        });
    }

    /**
     * Počet příjemců zprávy daného druhu (formulář ho ukáže před odesláním).
     */
    public function recipientCount(AnnouncementCategory $category): int
    {
        return $this->recipients($category)->count();
    }

    /**
     * Příjemci podle druhu zprávy: všichni, u propagační jen s platným souhlasem — udělení
     * novější než poslední odvolání (User::hasMarketingConsent, R69).
     *
     * @return Builder<User>
     */
    private function recipients(AnnouncementCategory $category): Builder
    {
        return User::query()
            ->select('id')
            ->when($category === AnnouncementCategory::Marketing, fn (Builder $query) => $query
                ->whereNotNull('marketing_consent_at')
                ->where(fn (Builder $query) => $query
                    ->whereNull('marketing_consent_withdrawn_at')
                    ->orWhereColumn('marketing_consent_withdrawn_at', '<', 'marketing_consent_at')));
    }
}
