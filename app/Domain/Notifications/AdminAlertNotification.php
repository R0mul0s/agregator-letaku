<?php

/**
 * Záznam centra upozornění jen pro adminy (R125, R126) — hlášení chyby v akci nebo výpadek
 * úloh. Data mají stejný tvar jako zpráva od nás (nadpis, text, odkaz), centrum je ukazuje
 * stejně (AnnouncementRecord::read).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-10
 */

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Enums\NotificationKind;
use Illuminate\Notifications\Notification;

final class AdminAlertNotification extends Notification
{
    /**
     * @param  string|null  $url  Adresa v aplikaci, kam vede tlačítko v detailu záznamu
     */
    public function __construct(
        private readonly NotificationKind $kind,
        private readonly string $title,
        private readonly string $body,
        private readonly ?string $url,
    ) {}

    /**
     * Kanály doručení — jen záznam v databázi; do telefonu posílá AdminAlerts hned po zápisu.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Druh záznamu ve sloupci type.
     */
    public function databaseType(object $notifiable): string
    {
        return $this->kind->value;
    }

    /**
     * Data záznamu.
     *
     * @return array{title: string, body: string, url: string|null}
     */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'url' => $this->url];
    }
}
