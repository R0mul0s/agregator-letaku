<?php

/**
 * Formulář zprávy od nás (R74, etapa 11d): nadpis, text, odkaz (cesta v aplikaci nebo https
 * adresa), druh zprávy a „i do telefonu“ — to jen u zprávy o službě (souhlas s obchodními
 * sděleními zní na e-mail, R51).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-05
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AnnouncementCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnnouncementRequest extends FormRequest
{
    /**
     * Pravidla validace.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.config()->integer('letaky.notifications.announcement.title_max_length')],
            'body' => ['required', 'string', 'max:'.config()->integer('letaky.notifications.announcement.body_max_length')],
            // Cesta v aplikaci (/akce), nebo https adresa — jiné schéma (javascript:, http:) ne;
            // cesta bez zpětného lomítka — prohlížeč čte „/\evil.com“ jako „//evil.com“ (R113)
            'url' => [
                'nullable', 'string', 'max:'.config()->integer('letaky.notifications.announcement.url_max_length'),
                'regex:~^(/(?!/)[^\s\\\\]*|https://\S+)$~',
            ],
            'category' => ['required', Rule::enum(AnnouncementCategory::class)],
            'push' => ['boolean', Rule::prohibitedIf(fn (): bool => $this->boolean('push') && $this->input('category') === AnnouncementCategory::Marketing->value)],
        ];
    }

    /**
     * Názvy polí v chybových hláškách.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => __('app.ui.announcements.title_field'),
            'body' => __('app.ui.announcements.body'),
            'url' => __('app.ui.announcements.url'),
            'category' => __('app.ui.announcements.category'),
            'push' => __('app.ui.announcements.push'),
        ];
    }

    /**
     * Vlastní hlášky — formát odkazu a telefon u propagační zprávy.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'url.regex' => __('app.ui.announcements.url_invalid'),
            'push.prohibited' => __('app.ui.announcements.push_marketing'),
        ];
    }

    /**
     * Ověřená data pro SendAnnouncement.
     *
     * @return array{title: string, body: string, url: string|null, category: AnnouncementCategory, push: bool}
     */
    public function announcement(): array
    {
        return [
            'title' => $this->string('title')->trim()->toString(),
            'body' => $this->string('body')->trim()->toString(),
            'url' => $this->filled('url') ? $this->string('url')->trim()->toString() : null,
            'category' => $this->enum('category', AnnouncementCategory::class) ?? AnnouncementCategory::Service,
            'push' => $this->boolean('push'),
        ];
    }
}
