@props(['preheader' => null])
{{-- Every letter of the service: <x-mail::message preheader="…"> — the preheader is the inbox preview line. --}}
<x-mail::layout :preheader="$preheader">
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.frontend_url')">
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
Письмо отправлено автоматически, отвечать на него не нужно.<br>
© {{ date('Y') }} {{ config('app.name') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>