{{--
    sitemap.xml (R45) — veřejné indexované stránky, App\Http\Controllers\CrawlerFilesController.

    @author Roman Hlaváček
    @created 2026-10-02
--}}
{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url }}</loc>
@if ($lastModified)
        <lastmod>{{ $lastModified }}</lastmod>
@endif
        <changefreq>daily</changefreq>
    </url>
@endforeach
</urlset>
