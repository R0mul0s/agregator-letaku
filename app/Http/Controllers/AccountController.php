<?php

/**
 * Stránka účtu (R12, R40) — profilový obrázek, jméno a e-mail, heslo (ukládá Fortify),
 * přihlášená zařízení a zrušení účtu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Domain\Account\MailingSubscriptions;
use App\Domain\Account\UserSessions;
use App\Enums\DigestFrequency;
use App\Enums\OffersSort;
use App\Http\Requests\DigestRequest;
use App\Http\Requests\MarketingRequest;
use App\Http\Requests\OffersPreferencesRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /** Sada chyb formuláře odhlášení ostatních zařízení. */
    public const ERROR_BAG_DEVICES = 'logoutOtherDevices';

    /** Sada chyb formuláře zrušení účtu. */
    public const ERROR_BAG_DELETE = 'deleteAccount';

    /** Kód stavu po odhlášení ostatních zařízení — toast (R47, lang: ui.toast.messages). */
    public const STATUS_DEVICES_LOGGED_OUT = 'other-devices-logged-out';

    /** Kód stavu po uložení předvoleb Mých slev — toast (R47, lang: ui.toast.messages). */
    public const STATUS_OFFERS_PREFERENCES_SAVED = 'offers-preferences-saved';

    /** Kód stavu po uložení nastavení souhrnu — toast (R47, lang: ui.toast.messages). */
    public const STATUS_DIGEST_SAVED = 'digest-saved';

    /** Kód stavu po uložení souhlasu s obchodními sděleními — toast (R47, R51). */
    public const STATUS_MARKETING_SAVED = 'marketing-saved';

    /**
     * Zobrazí formuláře účtu; názvy sad chyb musí sedět s akcemi Fortify.
     */
    public function show(Request $request, UserSessions $sessions): Response
    {
        return Inertia::render('Account', [
            'urls' => [
                'profile' => route('user-profile-information.update', absolute: false),
                'password' => route('user-password.update', absolute: false),
                'avatar' => route('account.avatar.update', absolute: false),
                'avatarDelete' => route('account.avatar.destroy', absolute: false),
                'offersPreferences' => route('account.offers-preferences', absolute: false),
                'digest' => route('account.digest', absolute: false),
                'marketing' => route('account.marketing', absolute: false),
                'privacy' => route('legal.privacy', absolute: false),
                'logoutOtherDevices' => route('account.devices.logout', absolute: false),
                'delete' => route('account.destroy', absolute: false),
            ],
            'errorBags' => [
                'profile' => UpdateUserProfileInformation::ERROR_BAG,
                'password' => UpdateUserPassword::ERROR_BAG,
                'devices' => self::ERROR_BAG_DEVICES,
                'delete' => self::ERROR_BAG_DELETE,
                'offersPreferences' => OffersPreferencesRequest::ERROR_BAG,
                'digest' => DigestRequest::ERROR_BAG,
                'marketing' => MarketingRequest::ERROR_BAG,
            ],
            'avatar' => [
                'sizePx' => config()->integer('letaky.account.avatar.size_px'),
                'maxKilobytes' => config()->integer('letaky.account.avatar.max_kilobytes'),
            ],
            'sessions' => $sessions->forUser($this->user($request), $request->session()->getId()),
            'offersPreferences' => [
                'sort' => $this->user($request)->offers_sort->value,
                'minDiscountPercent' => $this->user($request)->min_discount_percent,
                'sortOptions' => array_map(
                    fn (OffersSort $sort): array => ['value' => $sort->value, 'label' => $sort->label()],
                    OffersSort::cases(),
                ),
                'minDiscountOptions' => config()->array('letaky.account.min_discount_options'),
            ],
            'digest' => [
                'frequency' => $this->user($request)->digest_frequency->value,
                'options' => array_map(
                    fn (DigestFrequency $frequency): array => ['value' => $frequency->value, 'label' => $frequency->label()],
                    DigestFrequency::cases(),
                ),
            ],
            'marketingConsent' => $this->user($request)->hasMarketingConsent(),
        ]);
    }

    /**
     * Udělí nebo odvolá souhlas s obchodními sděleními (R51).
     */
    public function updateMarketing(MarketingRequest $request, MailingSubscriptions $subscriptions): RedirectResponse
    {
        $subscriptions->setMarketingConsent($this->user($request), $request->consent());

        return back()->with('status', self::STATUS_MARKETING_SAVED);
    }

    /**
     * Uloží předvolby Mých slev (R41).
     */
    public function updateOffersPreferences(OffersPreferencesRequest $request): RedirectResponse
    {
        $this->user($request)->forceFill($request->preferences())->save();

        return back()->with('status', self::STATUS_OFFERS_PREFERENCES_SAVED);
    }

    /**
     * Uloží četnost e-mailového souhrnu (R42). Po zapnutí přijde první souhrn s přehledem
     * všech aktuálních akcí, ne jen těch od dávno vypnutého souhrnu.
     */
    public function updateDigest(DigestRequest $request): RedirectResponse
    {
        $user = $this->user($request);
        $frequency = $request->frequency();
        $switchedOn = $user->digest_frequency === DigestFrequency::Off && $frequency !== DigestFrequency::Off;

        $user->forceFill([
            'digest_frequency' => $frequency,
            'digest_sent_at' => $switchedOn ? null : $user->digest_sent_at,
        ])->save();

        return back()->with('status', self::STATUS_DIGEST_SAVED);
    }

    /**
     * Odhlásí ostatní zařízení po zadání hesla. Aktuální zařízení zůstane přihlášené;
     * jeho cookie „Zapamatovat si mě“ dostane nový token.
     */
    public function logoutOtherDevices(Request $request, UserSessions $sessions): RedirectResponse
    {
        $request->validateWithBag(self::ERROR_BAG_DEVICES, ['password' => ['required', 'string', 'current_password:web']]);
        $user = $this->user($request);
        $remembered = $request->hasCookie(Auth::guard('web')->getRecallerName());

        $sessions->logoutOthers($user, $request->session()->getId());
        if ($remembered) {
            Auth::guard('web')->login($user, remember: true);
        }

        return back()->with('status', self::STATUS_DEVICES_LOGGED_OUT);
    }

    /**
     * Zruší účet po zadání hesla: hlídané položky a sledované obchody smaže databáze
     * (cizí klíče cascade), profilový obrázek a session smaže aplikace.
     */
    public function destroy(Request $request, UserSessions $sessions): RedirectResponse
    {
        $request->validateWithBag(self::ERROR_BAG_DELETE, ['password' => ['required', 'string', 'current_password:web']]);
        $user = $this->user($request);

        Auth::guard('web')->logout();
        if ($user->avatar_path !== null) {
            Storage::disk('local')->delete($user->avatar_path);
        }
        $sessions->deleteAll($user);
        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with('status', __('app.ui.account.deleted'));
    }

    /**
     * Přihlášený uživatel (routy jsou za middlewarem auth).
     */
    private function user(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();

        return $user;
    }
}
