@props(['session' => null, 'preview' => false, 'showFullText' => true])
@php($currentTimer = $session?->timers->firstWhere('position', $session->current_position))
<section class="session-board" aria-label="Apresentação da aula">
    <div class="session-timing" data-session-timing>
        <div class="scoreboard">
            <div class="timer-heading"><span data-timer-format>CRONÔMETRO</span><span data-round></span></div>
            <p class="timer-phase" data-phase-label>Preparando ambiente</p>
            <div class="timer-digits" data-timer-digits aria-label="Tempo">--:--</div>
            <p class="timer-state" data-timer-state>{{ $preview ? 'Prévia visual' : 'Sincronizando…' }}</p>
            <p class="timer-station" data-current-station hidden></p>
            <div class="timer-progress" role="progressbar" aria-label="Progresso do bloco" aria-valuemin="0" aria-valuemax="100" data-progress><span data-progress-bar></span></div>
            <div class="timer-detail-row"><span data-duration-label></span><span data-total-remaining></span></div>
            <p class="timer-options" data-timer-options hidden></p>
        </div>
        <x-workouts.suggested-loads :loads="$currentTimer?->suggested_loads" :entries="$currentTimer?->load_entries" :live="true" />
        <p class="presentation-capacity-warning load-capacity-warning" data-load-capacity-warning hidden role="alert">As cargas desta etapa excedem o espaço legível da TV. Reorganize as referências na Gestão.</p>
    </div>
    <article class="session-content">
        <p class="eyebrow session-block-meta"><span data-block-label>BLOCO ATUAL</span><span data-block-format></span><span data-block-round></span></p>
        <h2 data-block-title @if(isset($currentTimer?->text_style['title'])) style="{{ \App\Workouts\WorkoutTextStyle::css($currentTimer->text_style['title']) }}" @endif>{{ $currentTimer?->title ?: $session?->title }}</h2>
        <div class="content-pages" data-content-pages aria-label="Exercícios do bloco"></div>
        <p class="presentation-capacity-warning" data-content-capacity-warning hidden role="alert"><strong data-capacity-block-title></strong>O conteúdo desta etapa excede a capacidade de exibição simultânea. Reorganize ou resuma o treino na Gestão antes de apresentar.</p>
    </article>
</section>
<ol class="session-stages" data-session-stages aria-label="Etapas da aula"></ol>
@if(! $preview && $showFullText)
<details class="presentation-full-text" data-excess-full hidden>
    <summary>Consultar conteúdo completo para reorganizar a apresentação</summary>
    <pre data-excess-text></pre>
</details>
@endif
