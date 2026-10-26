{{-- Email confirmation (AppServiceProvider, VerifyEmail::toMailUsing). Own text instead of the stock
     notification template, which adds English lines («Regards», «If you're having trouble…»). --}}
<x-mail::message>
# Здравствуйте, {{ $name }}!

Подтвердите адрес почты для Feedback Board. На него придёт ссылка, если вы забудете пароль.

<x-mail::button :url="$url">
Подтвердить почту
</x-mail::button>

Ссылка действует сутки. Если вы не регистрировались в Feedback Board, просто удалите это письмо.

<x-mail::subcopy>
Если кнопка не открывается, скопируйте ссылку в браузер: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-mail::subcopy>
</x-mail::message>