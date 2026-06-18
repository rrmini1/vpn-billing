<!doctype html>
<html lang="{{ $isRussian ? 'ru' : 'en' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $isRussian ? 'Подтверждение email' : 'Confirm email' }}</title>
</head>
<body>
    @if ($isRussian)
        <p>Здравствуйте!</p>
        <p>Нажмите кнопку ниже, чтобы добавить этот email к Telegram-аккаунту Cors Port Solutions.</p>
        <p><a href="{{ $actionUrl }}">Подтвердить email</a></p>
        <p>После подтверждения нужно будет создать пароль.</p>
        <p>Если вы не запрашивали добавление email, просто проигнорируйте это письмо.</p>
    @else
        <p>Hello!</p>
        <p>Click the button below to add this email to your Cors Port Solutions Telegram account.</p>
        <p><a href="{{ $actionUrl }}">Confirm email</a></p>
        <p>After confirmation, you will create a password.</p>
        <p>If you did not request this, no further action is required.</p>
    @endif
</body>
</html>
