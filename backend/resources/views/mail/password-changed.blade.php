{{-- After a password change in the profile (PasswordChangedMail). Layout and theme: views/vendor/mail. --}}
<x-mail::message preheader="Если это были вы, ничего делать не нужно.">
# Пароль изменён

{{ $name }}, пароль от вашего аккаунта в Feedback Board только что сменили в настройках аккаунта. Если это были вы, ничего делать не нужно.

Если пароль меняли не вы, задайте новый по ссылке: она придёт на эту почту, и старый пароль перестанет работать.

<x-mail::button :url="$resetUrl">
Задать новый пароль
</x-mail::button>

<x-slot:subcopy>
Кнопка не открывается? Скопируйте ссылку в браузер: <span class="break-all">[{{ $resetUrl }}]({{ $resetUrl }})</span>
</x-slot:subcopy>
</x-mail::message>