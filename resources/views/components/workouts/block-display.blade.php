@props(['block'])
<article class="display-block">
    <header><span class="block-number">0{{ $block->position + 1 }}</span><div><p class="eyebrow">{{ ['warmup' => 'WARM-UP', 'skill' => 'TÉCNICA / FORÇA', 'workout' => 'PRINCIPAL'][$block->type] ?? $block->type }}</p><h2>{{ $block->title ?: ['warmup' => 'Aquecimento', 'skill' => 'Técnica', 'workout' => 'Workout'][$block->type] }}</h2></div><span class="timer-label">{{ \App\Workouts\TimerConfiguration::LABELS[$block->timer_type] ?? $block->timer_type }}</span></header>
    @if($block->duration_seconds)<p class="timer-config">{{ $block->timer_type === 'for_time' ? 'TIME CAP' : 'DURAÇÃO' }} <strong>{{ sprintf('%02d:%02d:%02d', intdiv($block->duration_seconds, 3600), intdiv($block->duration_seconds % 3600, 60), $block->duration_seconds % 60) }}</strong></p>@endif
    <div class="exercise-description">{{ $block->description }}</div>
    @if($block->stations->isNotEmpty())<ol class="display-stations">@foreach($block->stations as $station)<li><strong>{{ $station->name ?: 'Estação sem nome' }}</strong><div class="station-metrics">@if($station->distance !== null)<span>{{ $station->distance }} m</span>@endif @if($station->repetitions !== null)<span>{{ $station->repetitions }} reps</span>@endif @if($station->load !== null)<span>{{ $station->load }} kg</span>@endif @foreach(['duration_seconds' => 'Trabalho', 'rest_seconds' => 'Descanso', 'transition_seconds' => 'Transição'] as $key => $label)@if($station->$key !== null)<span>{{ $label }}: {{ $station->$key }} s</span>@endif @endforeach</div><div class="exercise-description">{{ $station->description }}</div></li>@endforeach</ol>@endif
    <x-workouts.suggested-loads :loads="$block->suggested_loads" />
    @if($block->notes)<p class="block-notes exercise-description">{{ $block->notes }}</p>@endif
    @php($visibleSettings = collect($block->timer_settings ?? [])->except('version')->filter(fn ($value, $key) => $value !== null && ($key !== 'preparation_seconds' || ! in_array($block->timer_type, ['none', 'repetitions']))))
    @if($visibleSettings->isNotEmpty())
        <div class="timer-settings">@foreach(['interval_seconds' => 'Intervalo', 'work_seconds' => 'Trabalho', 'rest_seconds' => 'Descanso', 'rounds' => 'Rounds', 'transition_seconds' => 'Transição', 'preparation_seconds' => 'Preparação'] as $key => $label)@if($visibleSettings->has($key))<span>{{ $label }}: <strong>{{ $visibleSettings->get($key) }}{{ $key === 'rounds' ? '' : ' s' }}</strong></span>@endif @endforeach</div>
    @endif
</article>
