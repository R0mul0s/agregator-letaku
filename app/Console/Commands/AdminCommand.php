<?php

/**
 * Příkaz letaky:admin — udělí nebo odebere uživateli správu katalogu produktů (R29).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class AdminCommand extends Command
{
    /** @var string */
    protected $signature = 'letaky:admin {email : e-mail účtu} {--revoke : odebrat správu katalogu}';

    /** @var string */
    protected $description = 'Udělí (nebo odebere) uživateli správu katalogu produktů';

    /**
     * Nastaví příznak admina podle e-mailu; neznámý e-mail je chyba.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            $this->error(__('app.admin.unknown_user', ['email' => $email]));

            return self::FAILURE;
        }

        $user->is_admin = ! $this->option('revoke');
        $user->save();

        $this->info(__($user->is_admin ? 'app.admin.granted' : 'app.admin.revoked', ['email' => $email]));

        return self::SUCCESS;
    }
}
