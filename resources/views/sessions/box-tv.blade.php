<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <title>TV do Box · BoxPulse</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="tv-body" style="{{ \App\Support\BoxTheme::variables(null) }}">
    <x-sessions.screen :public-tv="true" data-session-app data-view="box-tv" data-tv-status="waiting"
        :data-state-url="route('box.tv.state', absolute: false)" :data-logo-url="route('box.tv.logo', absolute: false)"
        :data-poll-ms="max(1500, config('boxpulse.poll_interval_ms'))" />
</body>
</html>
