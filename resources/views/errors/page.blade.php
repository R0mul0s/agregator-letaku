{{--
    Česká chybová stránka (R51) pro běžné načtení stránky — 404, 403, 419, 429, 500, 503…
    Jen Blade, ne Inertia: při chybě mimo routy neběží session ani sdílená data Inertie.
    Text podle kódu z lang/cs/app.php (errors), jinak obecný pro 4xx / 5xx.

    @author Roman Hlaváček
    @created 2026-10-03
--}}
@php
    $status = $exception instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface ? $exception->getStatusCode() : 500;
    $key = trans()->has('app.errors.'.$status.'.title') ? (string) $status : ($status >= 500 ? '5xx' : '4xx');
@endphp
@extends('layouts.static')

@section('title', __('app.errors.'.$key.'.title'))

@section('content')
    <div class="empty-state">
        <img src="/images/brand/mascot-192.webp" width="192" height="192" alt="" class="empty-state__mascot">
        <p class="empty-state__text">{{ __('app.errors.'.$key.'.text') }}</p>
        <div class="empty-state__action">
            <a href="{{ route('home') }}" class="button button--primary">{{ __('app.errors.home') }}</a>
        </div>
    </div>
@endsection
