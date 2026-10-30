{{-- The access request wasn't approved (AccessRejectedMail): no links, no name of who decided. --}}
<x-mail::message preheader="Команда доски не одобрила запрос.">
# Доступ к доске «{{ $boardName }}» не одобрен

{{ $name }}, команда доски рассмотрела ваш запрос и пока не открыла доступ.

@if ($reason)
<x-mail::panel>
{{ $reason }}
</x-mail::panel>
@endif

Если доступ всё-таки нужен, свяжитесь с тем, кто дал вам ссылку на доску.
</x-mail::message>