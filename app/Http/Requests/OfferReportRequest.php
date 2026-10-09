<?php

/**
 * Hlášení chyby v akci (R125): akce, důvod a nepovinný popis. Jeden uživatel pošle za den
 * nejvýš `letaky.offer_reports.max_per_user_per_day` hlášení.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OfferReportReason;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class OfferReportRequest extends FormRequest
{
    /**
     * Pravidla validace.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'offer_id' => ['required', 'integer', Rule::exists('offers', 'id')],
            'reason' => ['required', Rule::enum(OfferReportReason::class)],
            'note' => ['nullable', 'string', 'max:'.config()->integer('letaky.offer_reports.note_max_length')],
        ];
    }

    /**
     * Nad denní limit hlášení je chyba.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();
                $limit = config()->integer('letaky.offer_reports.max_per_user_per_day');
                if ($user instanceof User && $user->offerReports()->where('updated_at', '>=', now()->subDay())->count() >= $limit) {
                    $validator->errors()->add('reason', __('app.ui.offer_reports.limit'));
                }
            },
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
            'reason' => __('app.ui.offer_reports.reason'),
            'note' => __('app.ui.offer_reports.note'),
        ];
    }

    /**
     * Data k uložení; prázdný popis je null.
     *
     * @return array{reason: OfferReportReason, note: string|null}
     */
    public function reportData(): array
    {
        $note = trim($this->string('note')->toString());

        return [
            'reason' => $this->enum('reason', OfferReportReason::class) ?? OfferReportReason::Other,
            'note' => $note === '' ? null : $note,
        ];
    }
}
