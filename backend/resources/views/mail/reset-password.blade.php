{{-- Password reset (AppServiceProvider, ResetPassword::toMailUsing). Layout and theme: views/vendor/mail. --}}
<x-mail::message :preheader="'Ссылка для нового пароля, действует '.$minutes.' минут.'">
# Новый пароль

{{ $name }}, кто-то запросил сброс пароля для вашего аккаунта в Feedback Board. Если это были вы, задайте новый пароль по кнопке.

<x-mail::button :url="$url">
Задать новый пароль
</x-mail::button>

<x-slot:subcopy>
Ссылка действует {{ $minutes }} минут и сработает один раз. Если вы не запрашивали сброс, просто удалите это письмо: пароль останется прежним.

Кнопка не открывается? Скопируйте ссылку в браузер: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>