{{-- To owners / admins of a private board (AccessRequestedMail). Layout and theme: views/vendor/mail. --}}
<x-mail::message preheader="{{ $requesterName }} просит доступ. Решение — на странице участников.">
# Запрос доступа к доске «{{ $boardName }}»

**{{ $requesterName }}** ({{ $requesterEmail }}) открыл(а) ссылку на закрытую доску и просит доступ.

@if ($requestMessage)
<x-mail::panel>
{{ $requestMessage }}
</x-mail::panel>
@endif

Одобрите с подходящей ролью или отклоните, человек получит письмо с ответом.

<x-mail::button :url="$url">
Открыть запросы
</x-mail::button>

<x-slot:subcopy>
Письмо пришло, потому что вы владелец или админ этой доски. Кнопка не открывается? Скопируйте ссылку в браузер: <span class="break-all">[{{ $url }}]({{ $url }})</span>
</x-slot:subcopy>
</x-mail::message>