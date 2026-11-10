<!DOCTYPE html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="color-scheme" content="dark"><title>{{ $session->title }} · BOXPULSE TV</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body class="tv-body" style="{{ \App\Support\BoxTheme::variables($box) }}">
<div class="session-app tv-shell" data-session-app data-view="tv" data-state-url="{{ route('sessions.state', $session) }}" data-poll-ms="{{ config('boxpulse.poll_interval_ms') }}">
<header class="tv-header"><div class="tv-box"><img class="tv-logo" data-live-logo @if($box->logo_path) src="{{ route('box.logo') }}" @else hidden @endif alt="Logo da box"><div><strong data-live-box-name>{{ $box->name }}</strong><span>{{ $session->modality_name }}</span></div></div><h1>{{ $session->title }}</h1><div class="tv-date"><span data-live-date></span><strong data-live-clock>--:--:--</strong></div></header>
<x-sessions.board :session="$session" />
<footer class="tv-footer"><span class="tv-wordmark">BOX<span>PULSE</span></span><span class="sync-status" data-sync-status role="status">Sincronizando…</span><span class="session-feedback" data-session-feedback role="status"></span><div class="button-row"><button class="text-button" type="button" data-audio-toggle>Ativar sons</button><button class="text-button" type="button" data-fullscreen>Entrar em tela cheia</button><a class="text-button" href="{{ route('sessions.control', $session) }}">Painel do coach ↗</a></div></footer>
</div></body></html>
