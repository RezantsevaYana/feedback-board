{{-- The access request was approved (AccessApprovedMail). No list of what the board holds: each board has its own categories. --}}
<x-mail::message preheader="Ваш запрос на доступ одобрен.">
# Доступ к доске «{{ $boardName }}» открыт

{{ $approverName }} одобрил ваш запрос на доступ к доске.

Ваша роль на доске: **{{ $roleLabel }}**.

<x-mail::button :url="$url">
Перейти на доску
</x-mail::button>

<x-slot:subcopy>
@if ($joined)
Вы уже добавлены на доску. Если вы вошли в аккаунт, она откроется сразу.
@else
По ссылке можно задать пароль для входа, а если аккаунт уже есть, присоединиться одной кнопкой. Ссылка одноразовая и действует {{ $expiresInDays }} дней.
@endif

Кнопка не открывается? Скопируйте ссылку в браузер: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>