<?php

/**
 * Data záznamu „zpráva od nás“ v centru upozornění (R74, etapa 11d): nadpis, text, odkaz
 * a jestli jde i do telefonu. Záznamy se zapisují hromadně (SendAnnouncement), ne přes notify —
 * zpráva jde všem uživatelům najednou.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Models\Announcement;
use Illuminate\Notifications\DatabaseNotification;

final class AnnouncementRecord
{
    /**
     * Data záznamu ze zprávy.
     *
     * @return array{announcementId: int, title: string, body: string, url: string|null, push: bool}
     */
    public static function payload(Announcement $announcement): array
    {
        return [
            'announcementId' => $announcement->id,
            'title' => $announcement->title,
            'body' => $announcement->body,
            'url' => $announcement->url,
            'push' => $announcement->push,
        ];
    }

    /**
     * Data uloženého záznamu; chybějící části prázdné.
     *
     * @return array{title: string, body: string, url: string|null, push: bool}
     */
    public static function read(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return [
            'title' => is_string($data['title'] ?? null) ? $data['title'] : '',
            'body' => is_string($data['body'] ?? null) ? $data['body'] : '',
            'url' => is_string($data['url'] ?? null) ? $data['url'] : null,
            'push' => ($data['push'] ?? false) === true,
        ];
    }

    /**
     * Je odkaz mimo aplikaci (otevře se v novém okně)?
     */
    public static function isExternal(string $url): bool
    {
        return ! str_starts_with($url, '/');
    }
}
