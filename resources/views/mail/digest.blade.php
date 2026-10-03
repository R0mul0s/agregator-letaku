{{--
    E-mailový souhrn nových akcí hlídaných položek (R42) — App\Mail\DigestMail.
    Akce jsou karty jako na webu (třídy v tématu vendor/mail/html/themes/slevohlidka.css).
    HTML bloky bez odsazení a prázdných řádků — jinak je Markdown vezme jako kód nebo text.

    @author Roman Hlaváček
    @created 2026-10-02
--}}
<x-mail::message>
# {{ __('app.digest.greeting', ['name' => $firstName]) }}

{{ __('app.digest.intro') }}

@foreach ($items as $item)
<h2>{{ $item['name'] }} <span class="count">{{ trans_choice('app.digest.count', $item['total'], ['count' => $item['total']]) }}</span></h2>
<table class="offers" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($item['offers'] as $offer)
<tr><td class="offer">
<table width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr>
<td><span class="offer-name">{{ $offer['name'] }}</span><br><span class="offer-meta">{{ $offer['chain'] }} · {{ __('app.digest.valid_to', ['date' => $offer['validTo']]) }}</span></td>
<td class="offer-price-cell">@if ($offer['discountPercent'])<span class="offer-discount">−{{ $offer['discountPercent'] }}&nbsp;%</span><br>@endif<span class="offer-price">{{ $offer['price'] ?? __('app.digest.no_price') }}</span></td>
</tr></table>
</td></tr>
<tr><td class="offer-gap">&nbsp;</td></tr>
@endforeach
</table>
@if ($item['more'] > 0)
<p class="offer-more">{{ trans_choice('app.digest.more', $item['more'], ['count' => $item['more']]) }}</p>
@endif
@endforeach

<x-mail::button :url="$homeUrl">
{{ __('app.digest.button') }}
</x-mail::button>

{{ __('app.digest.footer', ['frequency' => mb_strtolower($frequency)]) }}

[{{ __('app.digest.unsubscribe_link') }}]({{ $unsubscribeUrl }}) · [{{ __('app.digest.settings_link') }}]({{ $accountUrl }})
</x-mail::message>
