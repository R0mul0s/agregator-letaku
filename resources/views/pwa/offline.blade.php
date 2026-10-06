{{--
    Stránka „Jste offline“ (R66) — service worker ji má uloženou a ukáže ji místo stránky,
    kterou bez připojení nemá. Odkazy na stránky, které uložené jsou (Moje slevy, nákupní
    seznam), a „Zkusit znovu“ (odkaz na stejnou adresu — vlastní skript CSP nedovolí).

    @author Roman Hlaváček
    @created 2026-10-04
--}}
@extends('layouts.static')

@section('title', __('app.ui.offline.title'))

@section('content')
    <div class="empty-state">
        <img src="/images/brand/mascot-192.webp" width="192" height="192" alt="" class="empty-state__mascot">
        <p class="empty-state__text">{{ __('app.ui.offline.text') }}</p>
        <div class="empty-state__action offline-page__actions">
            <a href="" class="button button--primary">{{ __('app.ui.offline.retry') }}</a>
            @foreach ($pages as $page)
                <a href="{{ $page['url'] }}" class="button button--ghost">{{ $page['label'] }}</a>
            @endforeach
        </div>
    </div>
@endsection
