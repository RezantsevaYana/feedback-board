{{-- The access request was approved (AccessApprovedMail). Neutral copy: the board may hold ideas, bugs, requests, discussions. --}}
<x-mail::message preheader="Ваш запрос одобрен. Ссылка действует {{ $expiresInDays }} дней.">
# Доступ к доске «{{ $boardName }}» открыт

{{ $approverName }} одобрил(а) ваш запрос. Это рабочая доска команды: здесь собирают идеи, баги, запросы и обсуждения.

Ваша роль на доске: **{{ $roleLabel }}**.

<x-mail::button :url="$url">
Открыть доступ
</x-mail::button>

<x-slot:subcopy>
По ссылке можно задать пароль для входа, а если аккаунт уже есть, присоединиться одной кнопкой. Ссылка одноразовая и действует {{ $expiresInDays }} дней.

Кнопка не открывается? Скопируйте ссылку в браузер: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>