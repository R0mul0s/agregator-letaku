<?php

/**
 * Úplnost textů pro výčty — chybějící klíč se zobrazí jako holý text (CODING_GUIDELINES, sekce 7).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Domain\Account\Social\SocialLoginRefused;
use App\Enums\Chain;
use App\Enums\LoyaltyProgram;
use App\Enums\MailingList;
use App\Enums\OfferReportReason;
use App\Enums\OfferType;
use App\Enums\SocialProvider;
use App\Enums\StoreFormat;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\OfferReportController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\ShoppingListController;
use App\Http\Controllers\ShoppingPreferencesController;
use App\Http\Controllers\SocialLoginController;
use App\Http\Controllers\UnsubscribeController;
use App\Http\Controllers\WatchItemController;
use App\Http\Controllers\WatchItemExclusionController;
use App\Http\Responses\ErrorToast;
use App\Http\Responses\RegisterResponse;
use App\Http\Responses\VerifyEmailResponse;

it('má název pro každý obchod v 1. i 2. pádě', function (Chain $chain): void {
    expect(trans()->has('app.chains.'.$chain->value))->toBeTrue()
        ->and(trans()->has('app.chains_genitive.'.$chain->value))->toBeTrue();
})->with(Chain::cases());

it('má název pro každý formát prodejny', function (StoreFormat $format): void {
    expect(trans()->has('app.store_formats.'.$format->value))->toBeTrue();
})->with(StoreFormat::cases());

it('má texty pro každý typ akce a věrnostní program', function (): void {
    foreach (OfferType::cases() as $type) {
        expect(trans()->has('app.ui.offer_types.'.$type->value))->toBeTrue();
    }
    foreach (LoyaltyProgram::cases() as $program) {
        expect(trans()->has('app.ui.loyalty_programs.'.$program->value))->toBeTrue();
    }
});

it('má text toastu pro každý kód stavu po uložení (R47)', function (string $status): void {
    expect(trans()->has('app.ui.toast.messages.'.$status))->toBeTrue();
})->with([
    // Fortify
    'profile-information-updated',
    'password-updated',
    AccountController::STATUS_DEVICES_LOGGED_OUT,
    AccountController::STATUS_OFFERS_PREFERENCES_SAVED,
    AccountController::STATUS_DIGEST_SAVED,
    AccountController::STATUS_MARKETING_SAVED,
    AccountController::STATUS_DELETED,
    UnsubscribeController::STATUS_UNSUBSCRIBED,
    VerifyEmailResponse::STATUS_VERIFIED,
    RegisterResponse::STATUS_REGISTERED,
    'verification-link-sent',
    ...array_values(ErrorToast::STATUSES),
    AvatarController::STATUS_UPDATED,
    ShoppingPreferencesController::STATUS_SAVED,
    WatchItemController::STATUS_ADDED,
    ShoppingListController::STATUS_ADDED,
    ShoppingListController::STATUS_REMOVED,
    ShoppingListController::STATUS_CLEARED,
    PushSubscriptionController::STATUS_ENABLED,
    PushSubscriptionController::STATUS_DISABLED,
    PushSubscriptionController::STATUS_TEST_SENT,
    PushSubscriptionController::STATUS_TEST_FAILED,
    WatchItemController::STATUS_UPDATED,
    WatchItemController::STATUS_REMOVED,
    CatalogController::STATUS_PRODUCT_SAVED,
    CatalogController::STATUS_PRODUCT_DELETED,
    CatalogController::STATUS_ASSIGNMENT_CHANGED,
    SocialLoginController::STATUS_LINKED,
    SocialLoginController::STATUS_UNLINKED,
    SocialLoginController::STATUS_CONFIRMED,
    WatchItemExclusionController::STATUS_OFFER_HIDDEN,
    WatchItemExclusionController::STATUS_OFFER_RESTORED,
    WatchItemExclusionController::STATUS_WORD_EXCLUDED,
    WatchItemExclusionController::STATUS_WORD_RESTORED,
    OfferReportController::STATUS_REPORTED,
    OfferReportController::STATUS_RESOLVED,
]);

it('má název pro každý důvod hlášení chyby v akci (R125)', function (OfferReportReason $reason): void {
    expect(trans()->has('app.ui.offer_reports.reasons.'.$reason->value))->toBeTrue();
})->with(OfferReportReason::cases());

it('má název a důvody odmítnutí pro přihlášení přes poskytovatele (R96)', function (): void {
    foreach (SocialProvider::cases() as $provider) {
        expect(trans()->has('app.ui.auth.social.providers.'.$provider->value))->toBeTrue()
            ->and(trans()->has('app.ui.auth.social.buttons.'.$provider->value))->toBeTrue();
    }

    $reasons = (new ReflectionClass(SocialLoginRefused::class))->getConstants();
    foreach ($reasons as $reason) {
        expect(trans()->has('app.ui.auth.social.refused.'.$reason))->toBeTrue();
    }
});

it('má název pro každý druh e-mailů k odhlášení (R51)', function (MailingList $list): void {
    expect(trans()->has('app.ui.mailing_lists.'.$list->value))->toBeTrue();
})->with(MailingList::cases());

it('má text pro každý pevný klíč t(…) a lookup(…) ve frontendu (R113)', function (): void {
    $missing = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('js'), FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if (! in_array($file->getExtension(), ['js', 'vue'], true)) {
            continue;
        }
        // Jen klíče jako literál — klíče skládané za běhu (`unit_price_units.${unit}`) test nevidí
        preg_match_all('/\b(?:t|lookup\([^,()]+,)\(?\s*\'([a-z0-9_.\-]+)\'/i', (string) file_get_contents($file->getPathname()), $matches);
        foreach ($matches[1] as $key) {
            if (! trans()->has('app.ui.'.$key)) {
                $missing[] = str_replace(resource_path('js').DIRECTORY_SEPARATOR, '', $file->getPathname()).': '.$key;
            }
        }
    }

    expect($missing)->toBe([]);
});
