<?php

/**
 * Hlášení chyb v akcích (R125): uživatel nahlásí chybu z karty akce, admin na stránce Hlášení
 * vidí otevřená hlášení po akcích a co uživatelé skrývají u produktů katalogu („Tohle ne“).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Catalog\UserFeedback;
use App\Domain\Notifications\Actions\NotifyAdminsOfReport;
use App\Domain\Offers\OfferPresenter;
use App\Http\Requests\OfferReportRequest;
use App\Models\Offer;
use App\Models\OfferReport;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OfferReportController extends Controller
{
    /** Kódy stavu pro toast (R47, lang: ui.toast.messages). */
    public const STATUS_REPORTED = 'offer-reported';

    public const STATUS_RESOLVED = 'offer-report-resolved';

    /**
     * Uloží hlášení; další hlášení stejné akce od stejného uživatele přepíše předchozí
     * a znovu ho otevře.
     */
    public function store(OfferReportRequest $request, NotifyAdminsOfReport $notifyAdmins): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $report = OfferReport::query()->updateOrCreate(
            ['offer_id' => $request->integer('offer_id'), 'user_id' => $user->id],
            [...$request->reportData(), 'resolved_at' => null],
        );
        // Adminům do centra upozornění a hned do telefonu — cron by hlášení poslal až za hodinu
        $notifyAdmins($report);

        // Z okna akce bez toastu — poděkuje okno samo
        return WatchItemExclusionController::done($request, self::STATUS_REPORTED);
    }

    /**
     * Stránka Hlášení pro admina: otevřená hlášení po akcích (nejnovější nahoře), skryté akce
     * a vyloučená slova u produktů katalogu.
     */
    public function index(OfferPresenter $presenter, UserFeedback $feedback): Response
    {
        $reports = OfferReport::query()->open()->with(['offer' => fn ($query) => $query->withoutRaw()])->latest('updated_at')->get();
        $hiddenOffers = $feedback->hiddenOffers();
        $excludedWords = $feedback->excludedWords();
        $products = Product::query()
            ->whereIn('id', [...array_column($hiddenOffers, 'productId'), ...array_column($excludedWords, 'productId')])
            ->get(['id', 'name'])
            ->keyBy('id');
        $product = fn (int $id): array => [
            'name' => $products[$id]->name ?? '',
            'url' => route('catalog.show', $id, absolute: false),
        ];

        // Hlášení po akcích v pořadí nejnovějšího hlášení akce
        $byOffer = [];
        foreach ($reports as $report) {
            $byOffer[$report->offer_id] ??= [
                'offer' => $presenter->toPage($report->offer),
                'reports' => [],
                'resolveUrl' => route('reports.resolve', $report->offer_id, absolute: false),
            ];
            $byOffer[$report->offer_id]['reports'][] = [
                'id' => $report->id,
                'reason' => $report->reason->label(),
                'note' => $report->note,
                'reportedAt' => $report->updated_at->toIso8601String(),
            ];
        }

        return Inertia::render('Reports', [
            'reports' => array_values($byOffer),
            'hiddenOffers' => array_map(fn (array $row): array => [
                'product' => $product($row['productId']),
                'offer' => $presenter->toPage($row['offer']),
                'users' => $row['users'],
            ], $hiddenOffers),
            'excludedWords' => array_map(fn (array $row): array => [
                'product' => $product($row['productId']),
                'word' => $row['word'],
                'users' => $row['users'],
            ], $excludedWords),
        ]);
    }

    /**
     * Označí všechna otevřená hlášení akce jako vyřešená.
     */
    public function resolve(Offer $offer): RedirectResponse
    {
        OfferReport::query()->open()->where('offer_id', $offer->id)->update(['resolved_at' => now()]);

        return back()->with('status', self::STATUS_RESOLVED);
    }
}
