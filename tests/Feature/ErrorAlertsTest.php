<?php

/**
 * Chyby aplikace adminům (R154): výjimka, která jde do logu, se zapíše adminům do centra
 * upozornění — stejná chyba jednou za den, všech nejvýš pár za hodinu; vypnuté mimo produkci.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-25
 */

declare(strict_types=1);

use App\Enums\NotificationKind;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    $this->travelTo('2026-10-25 10:00:00');
    config(['letaky.error_alerts.enabled' => true]);
    $this->admin = User::factory()->create(['is_admin' => true]);
});

/**
 * Výjimka vytvořená vždy na stejném řádku — stejný otisk chyby.
 */
function sameError(string $message): RuntimeException
{
    return new RuntimeException($message);
}

/**
 * Texty záznamů o chybě v centru upozornění admina, od nejstaršího.
 *
 * @return list<array{title: string, body: string}>
 */
function errorAlerts(User $admin): array
{
    return $admin->notifications()->where('type', NotificationKind::SystemAlert->value)->reorder()->oldest()->get()
        ->map(fn ($notification): array => ['title' => $notification->data['title'], 'body' => $notification->data['body']])->all();
}

it('ohlásí chybu adminovi se zprávou a místem v kódu, běžnému uživateli ne', function (): void {
    $user = User::factory()->create();

    report(sameError('Spojení s databází selhalo'));

    $alerts = errorAlerts($this->admin);
    expect($alerts)->toHaveCount(1)
        ->and($alerts[0]['title'])->toBe('Chyba: RuntimeException')
        ->and($alerts[0]['body'])->toStartWith("Spojení s databází selhalo\nMísto: tests")
        ->and($alerts[0]['body'])->toContain('ErrorAlertsTest.php:')
        ->and($user->notifications()->count())->toBe(0);
});

it('stejnou chybu ohlásí jednou za den, jinou hned', function (): void {
    report(sameError('první'));
    report(sameError('druhý výskyt'));
    report(new LogicException('jiná chyba'));

    // Obě vznikly ve stejné vteřině — pořadí není dané
    expect(array_column(errorAlerts($this->admin), 'title'))->toEqualCanonicalizing(['Chyba: RuntimeException', 'Chyba: LogicException']);

    $this->travel(1)->day();
    report(sameError('zase'));

    expect(errorAlerts($this->admin))->toHaveCount(3);
});

it('za hodinu ohlásí nejvýš max_per_hour chyb', function (): void {
    config(['letaky.error_alerts.max_per_hour' => 2]);

    // Každá na jiném řádku — jiný otisk
    report(new RuntimeException('a'));
    report(new RuntimeException('b'));
    report(new RuntimeException('c'));

    expect(errorAlerts($this->admin))->toHaveCount(2);

    // Další hodina má nový limit
    $this->travel(1)->hour();
    report(new RuntimeException('d'));

    expect(errorAlerts($this->admin))->toHaveCount(3);
});

it('vypnuté nic nezapíše', function (): void {
    config(['letaky.error_alerts.enabled' => false]);

    report(sameError('nic'));

    expect(errorAlerts($this->admin))->toBe([]);
});

it('dlouhou zprávu zkrátí', function (): void {
    config(['letaky.error_alerts.message_length' => 20]);

    report(sameError(str_repeat('x', 100)));

    expect(strtok(errorAlerts($this->admin)[0]['body'], "\n"))->toBe(str_repeat('x', 20).'...');
});

it('u požadavku uvede vzor routy, ne tokeny z cesty (R170)', function (): void {
    Route::get('/test-chyba/{token}', fn () => throw new RuntimeException('chyba v požadavku'));

    $this->get('/test-chyba/tajny-token-123')->assertServerError();

    $body = errorAlerts($this->admin)[0]['body'];
    expect($body)->toContain('Požadavek: GET /test-chyba/{token}')
        ->and($body)->not->toContain('tajny-token-123');
});

it('u chyby databáze pošle jen kód, ne SQL s hodnotami (R170)', function (): void {
    $pdo = new PDOException("SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'roman@example.com'");
    $pdo->errorInfo = ['23000', 1062, "Duplicate entry 'roman@example.com' for key 'users_email_unique'"];

    report(new QueryException('mariadb', 'insert into users (email) values (?)', ['roman@example.com'], $pdo));

    $body = errorAlerts($this->admin)[0]['body'];
    expect(strtok($body, "\n"))->toBe('Chyba databáze SQLSTATE 23000 (kód 1062)')
        ->and($body)->not->toContain('roman@example.com');
});
