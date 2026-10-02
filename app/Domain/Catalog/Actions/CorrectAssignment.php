<?php

/**
 * Ruční opravy přiřazení nabídky k produktu katalogu (R30); přepočet po importu je zachová.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Enums\MatchStatus;
use App\Models\Offer;
use App\Models\OfferProduct;
use App\Models\OfferProductExclusion;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class CorrectAssignment
{
    public function __construct(private readonly AssignProducts $assign) {}

    /**
     * „Sem patří“ — ruční přiřazení jako shoda; zruší případné vyřazení.
     */
    public function include(Product $product, Offer $offer): void
    {
        DB::transaction(function () use ($product, $offer): void {
            $this->exclusion($product, $offer)->delete();
            OfferProduct::query()->upsert(
                [['offer_id' => $offer->id, 'product_id' => $product->id, 'status' => MatchStatus::Match->value, 'is_manual' => true, 'created_at' => CarbonImmutable::now(), 'updated_at' => CarbonImmutable::now()]],
                ['offer_id', 'product_id'],
                ['status', 'is_manual', 'updated_at'],
            );
        });
    }

    /**
     * „Sem nepatří“ — zruší přiřazení (automatické i ruční) a zapamatuje si vyřazení.
     */
    public function exclude(Product $product, Offer $offer): void
    {
        DB::transaction(function () use ($product, $offer): void {
            OfferProduct::query()->where('offer_id', $offer->id)->where('product_id', $product->id)->delete();
            OfferProductExclusion::query()->insertOrIgnore([
                'offer_id' => $offer->id, 'product_id' => $product->id, 'created_at' => CarbonImmutable::now(), 'updated_at' => CarbonImmutable::now(),
            ]);
        });
    }

    /**
     * Zruší vyřazení — o přiřazení zase rozhodnou pravidla produktu.
     */
    public function restore(Product $product, Offer $offer): void
    {
        DB::transaction(function () use ($product, $offer): void {
            $this->exclusion($product, $offer)->delete();
            $this->assign->forProduct($product);
        });
    }

    /**
     * Dotaz na vyřazení dvojice.
     *
     * @return Builder<OfferProductExclusion>
     */
    private function exclusion(Product $product, Offer $offer): Builder
    {
        return OfferProductExclusion::query()->where('offer_id', $offer->id)->where('product_id', $product->id);
    }
}
