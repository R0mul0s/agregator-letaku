{{--
    Kostra e-mailů Slevohlídky — hlavička s logem, obsah a patička s mottem místo „All rights reserved“
    a provozovatelem (R51: identifikace odesílatele; chybějící údaj se vynechá).

    @author Roman Hlaváček
    @created 2026-10-02
--}}
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
{{ __('app.mail.footer') }}

{{ implode(' · ', array_filter([
    config('letaky.operator.name'),
    config('letaky.operator.company_id') ? __('app.mail.company_id', ['id' => config('letaky.operator.company_id')]) : null,
    app(\App\Support\Operator::class)->address(),
    config('letaky.operator.email'),
])) }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
