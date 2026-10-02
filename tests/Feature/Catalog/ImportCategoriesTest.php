<?php

/**
 * Import stromu kategorií katalogu z e-shopu Tesco (R28).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

use App\Models\Category;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Http::fake(['https://xapi.tesco.com/*' => Http::response(responseFixture('tesco/taxonomy-2026-10-02.json'))]);
});

it('uloží strom kategorií bez vyloučených oddělení', function (): void {
    $this->artisan('letaky:import-categories')->assertSuccessful();

    // Fixture má 60 uzlů; „Top výběr“ (4) a „Domov a zábava“ (2) se nepřebírají
    expect(Category::query()->count())->toBe(54)
        ->and(Category::query()->whereNull('parent_id')->orderBy('position')->pluck('name')->all())
        ->toBe(['Mléčné, vejce a margaríny', 'Nápoje']);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-apikey', 'test-api-key')
        && str_contains($request->body(), 'taxonomy'));
});

it('uloží úrovně stromu s nadřazenou kategorií a pořadím', function (): void {
    $this->artisan('letaky:import-categories');

    $shelf = Category::query()->where('name', 'Polotučné mléko')->where('depth', 3)
        ->whereHas('parent', fn ($query) => $query->where('name', 'Trvanlivé mléko'))
        ->sole();

    expect($shelf->parent?->parent?->name)->toBe('Mléko, mléčné a jogurtové nápoje')
        ->and($shelf->parent?->parent?->parent?->name)->toBe('Mléčné, vejce a margaríny')
        ->and($shelf->parent?->parent?->parent?->depth)->toBe(0)
        ->and($shelf->position)->toBe(1);
});

it('opakované stažení kategorie nezdvojí a nic nesmaže', function (): void {
    $this->artisan('letaky:import-categories');
    $ids = Category::query()->orderBy('id')->pluck('id')->all();

    $this->artisan('letaky:import-categories')->assertSuccessful();

    expect(Category::query()->orderBy('id')->pluck('id')->all())->toBe($ids);
});

it('bez klíče API kategorie nestáhne', function (): void {
    config(['letaky.sources.tesco.eshop_api_key' => null]);

    $this->artisan('letaky:import-categories')->assertFailed();

    expect(Category::query()->count())->toBe(0);
    Http::assertNothingSent();
});
