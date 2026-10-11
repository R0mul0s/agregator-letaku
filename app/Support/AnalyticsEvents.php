<?php

/**
 * Události pro Google Analytics z odpovědi serveru (R134): kontroler je po úspěšném uložení
 * odloží do relace jako flash, příští stránka je dostane ve sdíleném propu `analyticsEvents`
 * a `lib/consent.js` je pošle — jen se souhlasem s analytickými cookies, jinak zahodí.
 * Parametry nesmí nést osobní údaje ani obsah (název položky, hledaná slova), jen druh.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-11
 */

declare(strict_types=1);

namespace App\Support;

use App\Enums\AnalyticsEvent;
use Illuminate\Http\Request;

final class AnalyticsEvents
{
    /** Klíč v relaci (flash — vydrží jen do příštího požadavku, jako toast). */
    public const SESSION_KEY = 'analytics_events';

    /**
     * Přidá událost k odeslání s příští stránkou. Spojuje jen události tohoto požadavku
     * (atribut požadavku) — flash z předchozího požadavku by se jinak poslal podruhé.
     *
     * @param  array<string, string>  $params
     */
    public static function flash(Request $request, AnalyticsEvent $event, array $params = []): void
    {
        /** @var list<array{name: string, params: array<string, string>}> $events */
        $events = $request->attributes->get(self::SESSION_KEY, []);
        $events[] = ['name' => $event->value, 'params' => $params];
        $request->attributes->set(self::SESSION_KEY, $events);
        $request->session()->flash(self::SESSION_KEY, $events);
    }

    /**
     * Odložené události pro sdílený prop.
     *
     * @return list<array{name: string, params: array<string, string>}>
     */
    public static function pending(Request $request): array
    {
        /** @var list<array{name: string, params: array<string, string>}> $events */
        $events = $request->session()->get(self::SESSION_KEY, []);

        return $events;
    }
}
