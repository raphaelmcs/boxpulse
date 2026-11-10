@extends('layouts.app')
@section('title', 'Controle da aula')
@section('workspace', 'coach')
@section('content')
<x-sessions.navigation />
<div class="page-heading session-control-heading"><div class="classroom-control-identity">@if($box->logo_path)<img class="control-box-logo" src="{{ route('box.logo') }}" alt="Logo de {{ $box->name }}">@endif<div><p class="eyebrow">{{ $box->name }} / {{ $session->modality_name }} · AULA #{{ $session->id }}</p><h1>{{ $session->title }}<span>.</span></h1><p class="input-hint">Preparada em {{ $session->created_at->setTimezone($box->timezone)->format('d/m/Y H:i') }} · {{ $box->timezone }}</p></div></div><a class="secondary-button" href="{{ route('sessions.tv', $session) }}" target="_blank" rel="noopener">Abrir na TV ↗</a></div>
<div class="session-app coach-session" data-session-app data-view="control" data-state-url="{{ route('sessions.state', $session) }}" data-lease-url="{{ route('sessions.lease', $session) }}" data-command-url="{{ route('sessions.commands', $session) }}" data-poll-ms="{{ config('boxpulse.poll_interval_ms') }}">
    <section class="notice source-version-notice" data-source-notice hidden role="status">
        <strong data-source-notice-title>O treino cadastrado foi atualizado.</strong>
        <p data-source-notice-description>Esta aula mantém o conteúdo com que foi preparada.</p>
        <p>Treino cadastrado: <strong data-source-title></strong> · <span data-source-version></span></p>
        <a class="text-link" data-source-preview target="_blank" rel="noopener">Revisar treino cadastrado ↗</a>
        <a class="text-link" data-replacement-link hidden>Abrir nova sessão →</a>
        @if($canControl)
        <form method="POST" action="{{ route('sessions.restart', $session) }}" data-source-restart hidden>
            @csrf
            <input type="hidden" name="client_id" data-restart-client>
            <input type="hidden" name="expected_version" data-restart-version>
            <input type="hidden" name="expected_workout_version" data-restart-workout-version>
            <label class="checkbox-label"><input type="checkbox" name="confirmation" value="1" required>Confirmo encerrar esta execução e preparar uma nova aula com o treino publicado atual. O histórico será preservado.</label>
            <button type="submit" class="secondary-button" data-source-restart-submit disabled>Reiniciar aula com a versão publicada atual</button>
        </form>
        @endif
    </section>
    <div class="session-network-row"><span class="sync-status" data-sync-status role="status">Conectando ao servidor…</span><div class="button-row"><button class="text-button" type="button" data-audio-toggle>Ativar sons</button><button class="text-button" type="button" data-fullscreen>Entrar em tela cheia</button></div></div>
    @if($canControl)<div class="coach-ownership"><span data-ownership-label>Conectando controles…</span><button class="secondary-button" type="button" data-claim-control hidden>Assumir controle</button></div>
    <div class="coach-controls coach-primary-controls" aria-label="Controles principais"><button type="button" class="primary-button compact" data-session-command="start" disabled>Iniciar etapa</button><button type="button" class="secondary-button" data-session-command="pause" disabled>Pausar</button><button type="button" class="secondary-button" data-session-command="resume" disabled>Continuar</button><button type="button" class="primary-button compact" data-session-command="next" disabled>Próxima etapa →</button></div>@endif
    <x-sessions.board :session="$session" />
    <p class="session-feedback" data-session-feedback role="status"></p>
    @if($canControl)
    <details class="coach-secondary-controls"><summary>Mais controles da aula</summary><div class="coach-controls"><button type="button" class="secondary-button" data-session-command="reset" disabled>Reiniciar cronômetro</button><button type="button" class="secondary-button" data-session-command="previous" disabled>← Etapa anterior</button><button type="button" class="secondary-button" data-session-command="complete_block" disabled>Finalizar etapa</button><button type="button" class="secondary-button" data-session-command="finish" disabled>Finalizar aula</button><button type="button" class="text-button danger" data-session-command="cancel" disabled>Cancelar aula</button></div></details>
    @else<p class="notice">Você acompanha esta aula em modo de leitura. O coach responsável ou um administrador pode controlá-la.</p>@endif
    <section class="classroom-ended notice" data-session-ended hidden role="status"><h2>Aula encerrada.</h2><p>O histórico está preservado. Consulte o resumo ou escolha a próxima aula.</p><div class="button-row"><a class="secondary-button" href="{{ route('sessions.summary', $session) }}">Ver resumo da sessão</a><a class="primary-button compact" href="{{ route('coach.today') }}">Iniciar outra aula →</a></div></section>
    <div class="session-end-links"><a class="text-link" href="{{ route('sessions.summary', $session) }}">Ver resumo da sessão →</a><a class="text-link" href="{{ route('coach.today') }}">Voltar ao Modo Aula →</a><span class="input-hint">Avanço automático: {{ $session->auto_advance ? 'habilitado explicitamente' : 'desabilitado; o coach escolhe a próxima etapa' }}.</span></div>
</div>
@endsection
