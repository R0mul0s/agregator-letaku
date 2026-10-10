{{--
    Seznam akcí v obsahu pro roboty (R94, seo/content.blade.php): název, obchod, cena (bez ceny
    běžné cena s kartou), sleva (jen s `withDiscount`), cena za jednotku a platnost. Data z OfferPresenter::toPage.

    @author Roman Hlaváček
    @created 2026-10-06
--}}
@php
    $prices = app(\App\Support\PriceFormatter::class);
    $dateFormat = 'j. n.';
@endphp
<h2>{{ $title }}</h2>
@if ($offers === [])
    <p>{{ __('app.seo.content.offers_empty') }}</p>
@else
    <ul>
        @foreach ($offers as $offer)
            @php
                $withCard = $offer['price'] === null && $offer['loyaltyPrice'] !== null;
                $price = $offer['price'] ?? $offer['loyaltyPrice'];
                $unitPrice = $withCard ? $offer['loyaltyUnitPrice'] : $offer['unitPrice'];
            @endphp
            <li>
                <strong>{{ $offer['name'] }}</strong> · {{ $offer['chainName'] }}
                @if ($price !== null)
                    · {{ $prices->format($price) }}
                    @if ($withCard)
                        {{ __('app.seo.content.with_card', ['program' => $offer['loyaltyProgramName'] ?? '']) }}
                    @endif
                @endif
                {{-- Nejlepší slevy týdne (R128): sleva je důvod, proč akce na stránce je --}}
                @if (($withDiscount ?? false) && $offer['discountPercent'] !== null)
                    · {{ __('app.seo.content.discount', ['percent' => $offer['discountPercent']]) }}
                @endif
                @if ($unitPrice !== null && $offer['unitPriceUnit'] !== null)
                    ({{ __('app.seo.content.unit_price', ['price' => $prices->format($unitPrice), 'unit' => __('app.ui.unit_price_units.'.$offer['unitPriceUnit'])]) }})
                @endif
                · {{ __('app.seo.content.valid', [
                    'from' => \Carbon\CarbonImmutable::parse($offer['validFrom'])->format($dateFormat),
                    'to' => \Carbon\CarbonImmutable::parse($offer['validTo'])->format($dateFormat),
                ]) }}
            </li>
        @endforeach
    </ul>
@endif
