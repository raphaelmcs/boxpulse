@props(['workout'])
@php($last = $workout->latestSession)
@php($active = $workout->activeSession)
@php($needsNewSession = ! $active && $last)
@php($reusesSession = $active && ($active->workout_version === $workout->content_version || $active->hasExecutionActivity() || auth()->user()->cannot('control', $active)))
@if($workout->status === 'published' && auth()->user()->box->offersModality($workout->training_modality_id))
<section class="form-panel prepare-session-panel">
    <h2>Iniciar apresentação</h2>
    <p class="muted">O cronômetro começa quando o coach comandar o início da etapa.</p>
    <form method="POST" action="{{ route('sessions.store', $workout) }}" class="form-stack">
        @csrf
        @if($needsNewSession)<input type="hidden" name="previous_session_id" value="{{ $last->id }}"><label class="checkbox-label"><input type="checkbox" name="new_session_confirmation" value="1" required>Confirmo iniciar outra aula. A sessão anterior #{{ $last->id }} permanece no histórico.</label>@endif
        @if($reusesSession)
            <p class="input-hint">A aula #{{ $active->id }} já tem uma configuração de execução.</p>
        @else
            <x-sessions.preparation-options :workout="$workout" />
        @endif
        <button class="primary-button compact" type="submit">{{ $reusesSession ? 'Continuar apresentação' : 'Iniciar apresentação' }} ↗</button>
    </form>
</section>
@endif
