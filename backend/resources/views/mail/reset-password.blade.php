{{-- Password reset (AppServiceProvider, ResetPassword::toMailUsing). Own text, like mail/verify-email. --}}
<x-mail::message>
# Здравствуйте, {{ $name }}!

Кто-то запросил сброс пароля для вашего аккаунта в Feedback Board. Если это были вы, задайте новый пароль по кнопке.

<x-mail::button :url="$url">
Задать новый пароль
</x-mail::button>

Ссылка действует {{ $minutes }} минут и сработает один раз. Если вы не запрашивали сброс, просто удалите это письмо: пароль останется прежним.

<x-mail::subcopy>
Если кнопка не открывается, скопируйте ссылку в браузер: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-mail::subcopy>
</x-mail::message>
