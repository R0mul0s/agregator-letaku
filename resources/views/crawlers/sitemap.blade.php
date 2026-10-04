{{--
    sitemap.xml (R45) — veřejné indexované stránky, App\Http\Controllers\CrawlerFilesController.
    Bez changefreq a priority — Google je ignoruje (R68).

    @author Roman Hlaváček
    @created 2026-10-02
--}}
{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
@if ($url['lastmod'])
        <lastmod>{{ $url['lastmod'] }}</lastmod>
@endif
    </url>
@endforeach
</urlset>
