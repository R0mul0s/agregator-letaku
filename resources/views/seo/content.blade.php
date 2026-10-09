{{--
    Obsah veřejné stránky ze serveru pro roboty, které nespouštějí JavaScript (R94) — aplikace
    je SPA bez SSR (hosting nemá Node, R20) a robot by jinak viděl jen hlavičku. Stejná data
    jako stránka ve Vue (props Inertie): nadpis, akce s cenou, odkazy na akce obchodů
    a produktů, kontakt, právní text. Lidé ho nevidí (.seo-content) a app.js ho po spuštění
    aplikace odstraní.

    @author Roman Hlaváček
    @created 2026-10-06
--}}
@php
    $component = $page['component'] ?? '';
    $props = $page['props'] ?? [];
    $offerPages = app(\App\Domain\Offers\OfferPages::class);
    $withLinks = in_array($component, ['Landing', 'Offers'], true);
@endphp
@if (in_array($component, ['Landing', 'Offers', 'Contact', 'Legal'], true))
    <div class="seo-content" data-seo-content>
        <h1>{{ $seo['heading'] }}</h1>

        @switch($component)
            @case('Landing')
                <p>{{ __('app.ui.landing.lead') }}</p>
                {{-- Účel aplikace přímo na úvodní stránce — ověření značky u Googlu (R96) ho bez JavaScriptu nenašlo --}}
                <h2>{{ __('app.ui.landing.features_title') }}</h2>
                <ul>
                    @foreach (__('app.ui.landing.features') as $feature)
                        <li><strong>{{ $feature['title'] }}</strong> — {{ $feature['text'] }}</li>
                    @endforeach
                </ul>
                <p>
                    <a href="{{ route('register') }}">{{ __('app.ui.landing.register') }}</a> ·
                    <a href="{{ route('offers') }}">{{ __('app.ui.landing.browse') }}</a>
                </p>
                @include('seo.offers', ['offers' => $props['topOffers'] ?? [], 'title' => __('app.ui.landing.top_title')])
                @break

            @case('Offers')
                <p>{{ $seo['description'] }}</p>
                @include('seo.offers', ['offers' => $props['offers']['data'] ?? [], 'title' => __('app.seo.content.offers_title')])
                @if (! empty($props['pagination']['nextUrl']))
                    <p><a href="{{ $props['pagination']['nextUrl'] }}">{{ __('app.seo.content.next_page') }}</a></p>
                @endif
                @break

            @case('Contact')
                <p>{{ __('app.ui.contact.lead_before') }} {{ __('app.ui.contact.pigeon') }}{{ __('app.ui.contact.lead_after') }}</p>
                <h2>{{ __('app.seo.content.operator_title') }}</h2>
                <p>
                    {{ $props['operator']['name'] ?? '' }}, {{ __('app.ui.contact.company_id', ['id' => $props['operator']['companyId'] ?? '']) }},
                    {{ $props['operator']['address'] ?? '' }} · {{ $props['operator']['email'] ?? '' }}
                </p>
                <h2>{{ __('app.seo.content.faq_title') }}</h2>
                @foreach (__('app.ui.contact.faq') as $item)
                    <h3>{{ $item['question'] }}</h3>
                    <p>{{ $item['answer'] }}</p>
                @endforeach
                @break

            @case('Legal')
                {{-- Vlastní právní text převedený z Markdownu bez HTML ze zdroje (LegalDocuments, R51) --}}
                {!! $props['html'] ?? '' !!}
                @break
        @endswitch

        @if ($withLinks)
            <h2>{{ __('app.seo.content.chains_title') }}</h2>
            <ul>
                @foreach (app(\App\Domain\Sources\SourceRegistry::class)->chainsWithOffers() as $chain)
                    <li><a href="{{ $offerPages->chainUrl($chain) }}">{{ __('app.seo.content.chain_link', ['chain' => $chain->genitive()]) }}</a></li>
                @endforeach
            </ul>
            <h2>{{ __('app.seo.content.products_title') }}</h2>
            <ul>
                @foreach ($offerPages->productsWithOffers() as $productId)
                    <li><a href="{{ $offerPages->productUrl($productId) }}">{{ __('app.seo.content.product_link', ['product' => $offerPages->productName($productId)]) }}</a></li>
                @endforeach
            </ul>
        @endif

        {{-- Patička jako ve Vue (AppFooter.vue): provozovatel a právní stránky z každé stránky --}}
        <p>
            @foreach (\App\Support\Seo\PublicPages::PAGES as $route => $kind)
                @if (! $loop->first) · @endif<a href="{{ route($route) }}">{{ __("app.ui.footer.$kind") }}</a>
            @endforeach
        </p>
        <p>{{ __('app.ui.footer.disclaimer') }}</p>
    </div>
@endif
