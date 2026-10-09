<?php

/**
 * Zrušení účtu (R51, R113) — zásady slibují, že se účet smaže okamžitě se vším, co k němu
 * patří. Hlídané položky, obchody, nákupní seznam, odběry upozornění v telefonu a propojené
 * účty smaže databáze (cizí klíče cascade); záznamy centra upozornění (polymorfní vazba bez
 * cizího klíče), relace, odkazy na obnovu hesla a profilový obrázek smaže tahle třída.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-09
 */

declare(strict_types=1);

namespace App\Domain\Account\Actions;

use App\Domain\Account\UserSessions;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final readonly class DeleteAccount
{
    private const PASSWORD_RESETS_TABLE = 'password_reset_tokens';

    public function __construct(private UserSessions $sessions) {}

    /**
     * Smaže účet a jeho data v jedné transakci; profilový obrázek až po jejím potvrzení,
     * ať při chybě nezůstane účet bez obrázku.
     */
    public function handle(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user->notifications()->delete();
            DB::table(self::PASSWORD_RESETS_TABLE)->where('email', $user->email)->delete();
            $this->sessions->deleteAll($user);
            $user->delete();
        });

        if ($user->avatar_path !== null) {
            Storage::disk('local')->delete($user->avatar_path);
        }
    }
}
