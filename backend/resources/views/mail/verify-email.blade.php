{{-- Email confirmation (AppServiceProvider, VerifyEmail::toMailUsing). Layout and theme: views/vendor/mail. --}}
<x-mail::message preheader="Одна кнопка — и адрес подтверждён. Ссылка действует сутки.">
# Подтвердите почту

{{ $name }}, спасибо за регистрацию в Feedback Board. Подтвердите, что этот адрес ваш: на него придёт ссылка, если вы забудете пароль.

<x-mail::button :url="$url">
Подтвердить почту
</x-mail::button>

<x-slot:subcopy>
Ссылка действует сутки. Если вы не регистрировались в Feedback Board, просто удалите это письмо.

Кнопка не открывается? Скопируйте ссылку в браузер: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>