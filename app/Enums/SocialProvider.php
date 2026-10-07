<?php

/**
 * Poskytovatelé přihlášení cizím účtem (R96, Seznam R98). Název driveru Socialite = hodnota případu.
 * Poskytovatel bez klíčů v .env (services.<poskytovatel>.client_id) se nenabízí.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-06
 */

declare(strict_types=1);

namespace App\Enums;

enum SocialProvider: string
{
    case Google = 'google';
    // Seznam.cz (R98) — vlastní ovladač Socialite SeznamProvider
    case Seznam = 'seznam';
    case Facebook = 'facebook';

    /**
     * Poskytovatelé s nastavenými klíči — jen ty se ukazují na přihlášení a v účtu.
     *
     * @return list<self>
     */
    public static function configured(): array
    {
        return array_values(array_filter(self::cases(), fn (self $provider): bool => $provider->isConfigured()));
    }

    /**
     * Má poskytovatel v konfiguraci ID i tajemství aplikace?
     */
    public function isConfigured(): bool
    {
        // Prázdná proměnná v .env dává null, config()->string by spadl
        return filled(config('services.'.$this->value.'.client_id'))
            && filled(config('services.'.$this->value.'.client_secret'));
    }

    /**
     * Adresa loga poskytovatele na tlačítku (public/images/social).
     */
    public function logoUrl(): string
    {
        return asset('images/social/'.$this->value.'.svg');
    }

    /**
     * Barví se logo barvou textu tlačítka? Seznam podle manuálu tlačítka smí mít „esko“
     * jen červené na světlém a bílé na tmavém podkladu — barvu dává token, ne obrázek.
     */
    public function hasTintedLogo(): bool
    {
        return $this === self::Seznam;
    }

    /**
     * Název pro zobrazení (lang/cs/app.php, skupina ui.auth.social.providers).
     */
    public function label(): string
    {
        return __('app.ui.auth.social.providers.'.$this->value);
    }
}
