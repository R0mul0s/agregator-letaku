<?php

/**
 * Ovladač přihlášení přes Seznam (R98): žádost o token jako JSON, údaje o uživateli
 * s tokenem v hlavičce a převod odpovědi /user (tvar podle vyvojari.seznam.cz/oauth/scopes).
 * Síť nahrazuje MockHandler Guzzlu (R11).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

use App\Domain\Account\Social\SeznamProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;

/**
 * Ovladač s podvrženými odpověďmi Seznamu; odeslané požadavky se zapisují do $history.
 *
 * @param  list<Response>  $responses
 * @param  array<int, array<string, mixed>>  $history
 */
function seznamProvider(array $responses, array &$history): SeznamProvider
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($history));

    $provider = new SeznamProvider(Request::create('/prihlaseni/seznam/navrat', 'GET', ['code' => 'kod-z-navratu']), 'klient', 'tajemstvi', 'https://slevohlidka.cz/prihlaseni/seznam/navrat');
    $provider->stateless()->setHttpClient(new Client(['handler' => $stack]));

    return $provider;
}

it('pošle uživatele na přihlášení Seznamu s rozsahem identity', function (): void {
    $history = [];
    $url = seznamProvider([], $history)->redirect()->getTargetUrl();

    expect($url)->toStartWith('https://login.seznam.cz/api/v1/oauth/auth?')
        ->and($url)->toContain('client_id=klient')
        ->and($url)->toContain('scope=identity')
        ->and($url)->toContain('response_type=code')
        ->and($url)->toContain('redirect_uri='.urlencode('https://slevohlidka.cz/prihlaseni/seznam/navrat'));
});

it('vymění kód za token JSONem a převede údaje o uživateli', function (): void {
    $history = [];
    $user = seznamProvider([
        new Response(200, [], (string) json_encode(['access_token' => 'token-123', 'token_type' => 'bearer', 'oauth_user_id' => 'abc123', 'account_name' => 'jan.novak@seznam.cz'])),
        new Response(200, [], (string) json_encode(['oauth_user_id' => 'abc123', 'email' => 'jan.novak@seznam.cz', 'firstname' => 'Jan', 'lastname' => 'Novák', 'advert_user_id' => 'x'])),
    ], $history)->user();

    expect($user->getId())->toBe('abc123')
        ->and($user->getName())->toBe('Jan Novák')
        ->and($user->getEmail())->toBe('jan.novak@seznam.cz');

    $tokenRequest = $history[0]['request'];
    expect((string) $tokenRequest->getUri())->toBe('https://login.seznam.cz/api/v1/oauth/token')
        ->and($tokenRequest->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and(json_decode((string) $tokenRequest->getBody(), true))->toMatchArray([
            'grant_type' => 'authorization_code',
            'code' => 'kod-z-navratu',
            'client_id' => 'klient',
            'client_secret' => 'tajemstvi',
            'redirect_uri' => 'https://slevohlidka.cz/prihlaseni/seznam/navrat',
        ]);

    $userRequest = $history[1]['request'];
    expect((string) $userRequest->getUri())->toBe('https://login.seznam.cz/api/v1/user')
        ->and($userRequest->getHeaderLine('Authorization'))->toBe('bearer token-123');
});

it('bez jména a e-mailu vrátí prázdné jméno a null e-mail', function (): void {
    $history = [];
    $user = seznamProvider([
        new Response(200, [], (string) json_encode(['access_token' => 'token-123'])),
        new Response(200, [], (string) json_encode(['oauth_user_id' => 'abc123', 'email' => null, 'firstname' => null, 'lastname' => null])),
    ], $history)->user();

    expect($user->getName())->toBe('')
        ->and($user->getEmail())->toBeNull();
});
