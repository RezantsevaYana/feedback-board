{{-- The access request was approved (AccessApprovedMail). No list of what the board holds: each board has its own categories. --}}
<x-mail::message preheader="Ваш запрос одобрен. Ссылка действует {{ $expiresInDays }} дней.">
# Доступ к доске «{{ $boardName }}» открыт

{{ $approverName }} одобрил ваш запрос на доступ к доске.

Ваша роль на доске: **{{ $roleLabel }}**.

<x-mail::button :url="$url">
Открыть доступ
</x-mail::button>

<x-slot:subcopy>
По ссылке можно задать пароль для входа, а если аккаунт уже есть, присоединиться одной кнопкой. Ссылка одноразовая и действует {{ $expiresInDays }} дней.

Кнопка не открывается? Скопируйте ссылку в браузер: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>