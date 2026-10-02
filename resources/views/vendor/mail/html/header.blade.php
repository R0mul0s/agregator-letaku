{{--
    Hlavička e-mailů Slevohlídky — logo místo názvu aplikace (téma slevohlidka.css).

    @author Roman Hlaváček
    @created 2026-10-02
--}}
@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}">
<img src="{{ asset('images/brand/logo.png') }}" class="logo" alt="{{ config('app.name') }}">
</a>
</td>
</tr>
