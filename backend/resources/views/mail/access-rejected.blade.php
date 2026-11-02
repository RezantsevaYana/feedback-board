{{-- The access request wasn't approved (AccessRejectedMail): no links, no name of who decided. --}}
<x-mail::message preheader="Команда доски не одобрила запрос.">
# Доступ к доске «{{ $boardName }}» не одобрен

{{ $name }}, ваш запрос рассмотрели и пока не открыли доступ.

@if ($reason)
<x-mail::panel>
{{ $reason }}
</x-mail::panel>
@endif

Если доступ всё-таки нужен, свяжитесь с тем, кто дал вам ссылку на доску.
</x-mail::message>