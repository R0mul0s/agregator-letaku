{{--
    Kanál RSS 2.0 akcí obchodu nebo produktu katalogu (R149, App\Http\Controllers\OfferFeedController).
    Texty od obchodu jdou přes {{ }} (escapované) — čtečka je bere jako text, ne HTML.

    @author Roman Hlaváček
    @created 2026-10-13
--}}
{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ $channel['title'] }}</title>
        <link>{{ $channel['link'] }}</link>
        <description>{{ $channel['description'] }}</description>
        <language>cs</language>
        <atom:link href="{{ $channel['self'] }}" rel="self" type="application/rss+xml" />
@if ($channel['updatedAt'])
        <lastBuildDate>{{ $channel['updatedAt']->toRssString() }}</lastBuildDate>
@endif
        <ttl>{{ $channel['ttl'] }}</ttl>
@foreach ($channel['items'] as $item)
        <item>
            <title>{{ $item['title'] }}</title>
            <link>{{ $item['link'] }}</link>
            <guid isPermaLink="false">{{ $item['guid'] }}</guid>
            <description>{{ $item['description'] }}</description>
            <pubDate>{{ $item['pubDate'] }}</pubDate>
        </item>
@endforeach
    </channel>
</rss>
