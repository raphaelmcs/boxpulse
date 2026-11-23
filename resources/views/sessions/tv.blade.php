<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="dark"><title>{{ $session->title }} · BOXPULSE TV</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="tv-body" style="{{ \App\Support\BoxTheme::variables($box) }}">
<x-sessions.screen :box="$box" :title="$session->title" :modality="$session->modality_name" :session="$session"
    data-session-app data-view="tv" :data-session-status="$session->status" :data-state-url="route('sessions.state', $session)" :data-poll-ms="config('boxpulse.poll_interval_ms')" />
</body></html>
