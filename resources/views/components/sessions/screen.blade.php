@props(['box' => null, 'title' => '', 'modality' => '', 'session' => null, 'publicTv' => false])
@php($preview = $session === null && ! $publicTv)
<div {{ $attributes->class(['session-app', 'tv-shell']) }}>
    <div class="tv-frame">
        <header class="tv-header">
            <div class="tv-box">
                <img class="tv-logo" data-live-logo @if($box?->logo_path) src="{{ route('box.logo') }}" @else hidden @endif alt="Logo da box">
                <div><strong data-live-box-name>{{ $box?->name ?? 'BoxPulse' }}</strong><span data-screen-modality>{{ $modality }}</span></div>
            </div>
            <h1 data-screen-title>{{ $title }}</h1>
            <div class="tv-date"><span data-live-date></span><strong data-live-clock>--:--:--</strong></div>
        </header>
        @if($publicTv)
            <section class="box-tv-waiting" data-box-tv-waiting>
                <h2 data-box-tv-message role="status">Aguardando o coach iniciar a aula</h2>
            </section>
        @elseif($session)
            <section class="box-tv-waiting" data-session-ended @if($session->status === 'active') hidden @endif>
                <h2 role="status">Aula encerrada</h2>
            </section>
        @endif
        <x-sessions.board :session="$session" :preview="$preview" :show-full-text="! $publicTv" />
        <footer class="tv-footer">
            <span class="tv-wordmark">BOX<span>PULSE</span></span>
            @if($preview)
            <span class="sync-status">Prévia visual</span>
            @else
            <span class="sync-status" data-sync-status role="status">Sincronizando…</span>
            <span class="session-feedback" data-session-feedback role="status"></span>
            <div class="button-row">
                <button class="text-button" type="button" data-audio-toggle>Ativar sons</button>
                <button class="text-button" type="button" data-fullscreen>Entrar em tela cheia</button>
                @unless($publicTv)<a class="text-button" href="{{ route('sessions.control', $session) }}">Painel do coach ↗</a>@endunless
            </div>
            @endif
        </footer>
    </div>
</div>
