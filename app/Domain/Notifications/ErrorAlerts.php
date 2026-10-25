<?php

/**
 * Chyby aplikace adminům (R154): výjimka, která jde do logu, se zapíše adminům do centra
 * upozornění a hned do telefonu (AdminAlerts) — na hostingu bez SSH log nikdo nečte. Stejná
 * chyba (třída, soubor, řádek) jednou za `repeat_minutes`, všech nejvýš `max_per_hour` za hodinu,
 * ať chyba v každém požadavku nezahltí telefon. Co je známo, je v cache.
 *
 * Volá ji `report` v bootstrap/app.php. Sama nesmí nic vyhodit ani se zacyklit — chyba při
 * zápisu (databáze nejede, push služba) se jen zahodí; výjimka je v logu tak jako tak.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Enums\NotificationKind;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\QueryException;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

// Jedna instance — příznak $reporting musí platit i pro report() volaný z hlášení samotného
#[Singleton]
final class ErrorAlerts
{
    /** Cache: předpona klíče už ohlášené chyby (podle otisku). */
    public const CACHE_PREFIX = 'error-alerts.';

    /** Probíhá ohlášení? Chyba uvnitř (AdminAlerts hlásí chybu push služby) se znovu nehlásí. */
    private bool $reporting = false;

    public function __construct(private readonly AdminAlerts $alerts) {}

    /**
     * Ohlásí výjimku adminům, pokud je zapnuto a stejná chyba ani limit za hodinu to nebrání.
     */
    public function report(Throwable $exception): void
    {
        if ($this->reporting || ! config()->boolean('letaky.error_alerts.enabled')) {
            return;
        }

        $this->reporting = true;
        try {
            if ($this->shouldSend($exception)) {
                $this->alerts->send(
                    NotificationKind::SystemAlert,
                    __('app.notifications.error_alert.title', ['class' => class_basename($exception)]),
                    $this->body($exception),
                    null,
                );
            }
        } catch (Throwable) {
            // Výjimka je v logu; hlášení se zkusí znovu u dalšího výskytu
        } finally {
            $this->reporting = false;
        }
    }

    /**
     * Ještě neohlášená chyba a v limitu hodiny? Obojí zapíše do cache.
     */
    private function shouldSend(Throwable $exception): bool
    {
        $fingerprint = sha1($exception::class.'|'.$exception->getFile().'|'.$exception->getLine());
        $repeat = now()->addMinutes(config()->integer('letaky.error_alerts.repeat_minutes'));
        if (! Cache::add(self::CACHE_PREFIX.$fingerprint, true, $repeat)) {
            return false;
        }

        $hourKey = self::CACHE_PREFIX.'hour.'.now()->format('YmdH');
        Cache::add($hourKey, 0, now()->addHour());

        return (int) Cache::increment($hourKey) <= config()->integer('letaky.error_alerts.max_per_hour');
    }

    /**
     * Text záznamu: zpráva výjimky (zkrácená), místo v kódu a kde nastala — u požadavku
     * metoda a vzor routy bez parametrů (cron URL nese token v dotazu, obnova hesla a sdílený
     * seznam v cestě, R170), jinak příkaz konzole.
     */
    private function body(Throwable $exception): string
    {
        $message = $this->message($exception);
        $location = Str::after($exception->getFile(), base_path().DIRECTORY_SEPARATOR).':'.$exception->getLine();
        $route = request()->route();
        $context = match (true) {
            $route instanceof Route => __('app.notifications.error_alert.request', ['method' => request()->method(), 'path' => '/'.ltrim($route->uri(), '/')]),
            app()->runningInConsole() => __('app.notifications.error_alert.console', ['command' => implode(' ', array_slice($_SERVER['argv'] ?? [], 1, 2))]),
            // Chyba před výběrem routy — cesta bez dotazu
            default => __('app.notifications.error_alert.request', ['method' => request()->method(), 'path' => '/'.ltrim(request()->path(), '/')]),
        };

        return implode("\n", [
            Str::limit($message === '' ? $exception::class : $message, config()->integer('letaky.error_alerts.message_length')),
            __('app.notifications.error_alert.location', ['location' => $location]),
            $context,
        ]);
    }

    /**
     * Zpráva výjimky. U chyby databáze jen kód — její text nese SQL s dosazenými hodnotami
     * a hlášení databáze cituje zapisovanou hodnotu („Duplicate entry 'roman@…'“), záznam
     * přitom jde i na zamčenou obrazovku telefonu (R170).
     */
    private function message(Throwable $exception): string
    {
        if ($exception instanceof QueryException) {
            return __('app.notifications.error_alert.query', [
                'sqlstate' => (string) ($exception->errorInfo[0] ?? $exception->getCode()),
                'code' => (string) ($exception->errorInfo[1] ?? '?'),
            ]);
        }

        return trim($exception->getMessage());
    }
}
