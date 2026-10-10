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
@if (in_array($component, ['Landing', 'Offers', 'Weekly', 'Contact', 'Legal', 'Auth/Login', 'Auth/Register'], true))
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

            {{-- Nejlepší slevy týdne (R128): žebříček, slevy po obchodech a odkazy na sousední týdny a archiv --}}
            @case('Weekly')
                <p>{{ $seo['description'] }}</p>
                @include('seo.offers', ['offers' => $props['top'] ?? [], 'title' => __('app.ui.weekly.top_title'), 'withDiscount' => true])
                @foreach ($props['chainSections'] ?? [] as $section)
                    @php $chain = \App\Enums\Chain::from($section['chain']); @endphp
                    @include('seo.offers', ['offers' => $section['offers'], 'title' => __('app.ui.weekly.chain_title', ['chain' => $chain->label()]), 'withDiscount' => true])
                    <p><a href="{{ $section['url'] }}">{{ __('app.seo.content.chain_link', ['chain' => $chain->genitive()]) }}</a></p>
                @endforeach
                <p>
                    @if (! empty($props['olderUrl']))
                        <a href="{{ $props['olderUrl'] }}">{{ __('app.ui.weekly.older') }}</a>
                    @endif
                    @if (! empty($props['newerUrl']))
                        @if (! empty($props['olderUrl'])) · @endif<a href="{{ $props['newerUrl'] }}">{{ __('app.ui.weekly.newer') }}</a>
                    @endif
                </p>
                <h2>{{ __('app.ui.weekly.archive_title') }}</h2>
                <ul>
                    @foreach ($props['archive'] ?? [] as $item)
                        <li><a href="{{ $item['url'] }}">{{ __('app.ui.weekly.archive_item', ['number' => $item['number'], 'year' => $item['year'], 'range' => $item['range']]) }}</a></li>
                    @endforeach
                </ul>
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

            {{-- Přihlášení a registrace (R123): nadpis, k čemu účet je, a cesta na druhou stránku --}}
            @case('Auth/Login')
                <p>{{ $seo['description'] }}</p>
                <p><a href="{{ route('register') }}">{{ __('app.ui.auth.login.register') }}</a></p>
                @break

            @case('Auth/Register')
                <p>{{ $seo['description'] }}</p>
                <p><a href="{{ route('login') }}">{{ __('app.ui.auth.register.login') }}</a></p>
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
            {{-- Nejlepší slevy týdne (R128) — aktuální týden přímo, ne přes přesměrování z /tyden --}}
            @php $weekly = app(\App\Domain\Offers\WeeklyDeals::class); @endphp
            <a href="{{ $weekly->url($weekly->currentWeek(), absolute: true) }}">{{ __('app.ui.footer.weekly') }}</a>
            @foreach (\App\Support\Seo\PublicPages::PAGES as $route => $kind)
                · <a href="{{ route($route) }}">{{ __("app.ui.footer.$kind") }}</a>
            @endforeach
        </p>
        <p>{{ __('app.ui.footer.disclaimer') }}</p>
    </div>
@endif
