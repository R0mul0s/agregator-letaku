{{--
    llms.txt (R45, formát llmstxt.org) — popis Slevohlídky pro jazykové modely, Markdown.

    @author Roman Hlaváček
    @created 2026-10-02
--}}
# {{ __('app.ui.app_name') }}

> {{ __('app.llms.summary') }}

{{ __('app.llms.about') }}

## {{ __('app.llms.pages_title') }}

- [{{ __('app.llms.home') }}]({{ $homeUrl }}): {{ __('app.llms.home_description') }}
- [{{ __('app.llms.offers') }}]({{ route('offers') }}): {{ __('app.llms.offers_description') }}
@foreach ($chains as $chain)
- [{{ __('app.llms.chain', ['chain' => $chain['name']]) }}]({{ $chain['url'] }})
@endforeach
- [{{ __('app.llms.terms') }}]({{ route('legal.terms') }})
- [{{ __('app.llms.privacy') }}]({{ route('legal.privacy') }})

## {{ __('app.llms.notes_title') }}

- {{ __('app.llms.note_prices') }}
- {{ __('app.llms.note_validity') }}
- {{ __('app.llms.note_private') }}
- {{ __('app.llms.note_contact', ['email' => config('letaky.operator.email')]) }}
