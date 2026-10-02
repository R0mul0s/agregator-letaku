<?php

/**
 * Profilový obrázek přihlášeného uživatele (R40) — zobrazení, nahrání a odebrání.
 * Soubor leží mimo public na disku local: na hostingu nejde spustit `storage:link` (R20)
 * a obrázek patří jen vlastníkovi účtu.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AvatarRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AvatarController extends Controller
{
    /** Kód stavu po uložení nebo odebrání obrázku (Account.vue). */
    public const STATUS_UPDATED = 'avatar-updated';

    /**
     * Pošle obrázek; adresa obsahuje název souboru (User::avatarUrl), proto smí ležet v cache napořád.
     */
    public function show(Request $request): StreamedResponse
    {
        $path = $this->user($request)->avatar_path;
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, headers: ['Cache-Control' => 'private, max-age=31536000, immutable']);
    }

    /**
     * Uloží nový obrázek pod novým názvem a smaže předchozí.
     */
    public function update(AvatarRequest $request): RedirectResponse
    {
        $user = $this->user($request);
        $previous = $user->avatar_path;

        $path = $request->avatar()->store(config()->string('letaky.account.avatar.directory'), 'local');
        $user->forceFill(['avatar_path' => $path === false ? null : $path])->save();
        $this->deleteFile($previous);

        return back()->with('status', self::STATUS_UPDATED);
    }

    /**
     * Odebere obrázek — místo něj se ukážou iniciály.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $this->user($request);
        $this->deleteFile($user->avatar_path);
        $user->forceFill(['avatar_path' => null])->save();

        return back()->with('status', self::STATUS_UPDATED);
    }

    /**
     * Smaže soubor obrázku, pokud nějaký je.
     */
    private function deleteFile(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('local')->delete($path);
        }
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
