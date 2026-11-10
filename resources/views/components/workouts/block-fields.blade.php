@props(['index', 'block', 'modality'])
@php
    $block = app(\App\Workouts\WorkoutDurationInput::class)->editor($block);
    $type = $block['type'];
    $title = ['warmup' => 'Warm-up / Aquecimento', 'skill' => ($modality->slug === 'crossfit' ? 'Skill / Técnica' : 'Técnica ou força'), 'workout' => ($modality->slug === 'crossfit' ? 'WOD' : ($modality->slug === 'hyrox' ? 'Workout' : 'Circuito principal'))][$type];
    $formats = \App\Workouts\TimerConfiguration::formats($modality->slug, $type);
    $prefix = "blocks[$index]";
@endphp
<details class="block-editor" open data-block-editor>
    <summary><span class="block-number">0{{ $index + 1 }}</span><strong>{{ $title }}</strong><span>Expandir / recolher</span></summary>
    <div class="block-fields">
        <input type="hidden" name="{{ $prefix }}[type]" value="{{ $type }}">
        <label>{{ $type === 'skill' ? 'Título da técnica (opcional)' : 'Título (opcional)' }}<input name="{{ $prefix }}[title]" value="{{ $block['title'] ?? '' }}" maxlength="160" @if($type === 'skill') list="skill-suggestions" @endif placeholder="{{ $type === 'skill' ? 'Digite livremente ou use uma sugestão' : 'Dê um nome para este bloco' }}"></label>
        <label>Descrição {{ $type !== 'workout' || $modality->slug === 'crossfit' ? '*' : '(ou adicione estações)' }}<textarea name="{{ $prefix }}[description]" rows="6" maxlength="20000" placeholder="Descreva os exercícios, uma linha de cada vez…">{{ $block['description'] ?? '' }}</textarea></label>
        <div class="field-grid"><label>Formato <span class="required">*</span><select name="{{ $prefix }}[timer_type]" data-timer-type>@foreach($formats as $value => $label)<option value="{{ $value }}" @selected(($block['timer_type'] ?? array_key_first($formats)) === $value)>{{ $label }}</option>@endforeach</select></label>
        <div class="duration-minutes-field" data-timer-for="stopwatch countdown for_time amrap emom">
            <label><span data-duration-label>Duração (minutos)</span><input type="number" name="{{ $prefix }}[duration_minutes]" value="{{ $block['duration_minutes'] }}" min="0" max="1440" step="1" inputmode="numeric"><small>Minutos inteiros. FOR TIME: cap opcional; AMRAP e EMOM: duração obrigatória.</small></label>
            <details class="duration-extra" @if((int) $block['duration_extra_seconds'] > 0) open @endif><summary>Segundos adicionais (opcional)</summary><label>Segundos adicionais<input type="number" name="{{ $prefix }}[duration_extra_seconds]" value="{{ $block['duration_extra_seconds'] }}" min="0" max="59" step="1" inputmode="numeric"><small>Exemplo: 1 minuto + 30 segundos. Sem valores fracionários.</small></label></details>
        </div>
        @foreach(['interval_seconds' => ['Intervalo EMOM (segundos)', 'emom', 1, 86400], 'work_seconds' => ['Trabalho (segundos)', 'tabata interval circuit', 1, 86400], 'rest_seconds' => ['Descanso (segundos)', 'tabata interval circuit', 0, 86400], 'rounds' => ['Rounds / ciclos', 'tabata interval circuit repetitions', 1, 1000], 'transition_seconds' => ['Transição (segundos)', 'interval circuit', 0, 3600]] as $key => [$label, $timerTypes, $min, $max])
            <label data-timer-for="{{ $timerTypes }}">{{ $label }}<input type="number" name="{{ $prefix }}[timer_settings][{{ $key }}]" value="{{ $block['timer_settings'][$key] ?? (\App\Workouts\TimerConfiguration::defaults()[$key] ?? '') }}" min="{{ $min }}" max="{{ $max }}"></label>
        @endforeach
        <label data-timer-for="for_time">Ao atingir o time cap<select name="{{ $prefix }}[timer_settings][stop_at_cap]"><option value="1" @selected(($block['timer_settings']['stop_at_cap'] ?? true))>Finalizar automaticamente</option><option value="0" @selected(! ($block['timer_settings']['stop_at_cap'] ?? true))>Sinalizar e continuar</option></select></label>
        <label data-timer-for="stopwatch countdown for_time amrap emom tabata interval circuit">Preparação (segundos)<input type="number" name="{{ $prefix }}[timer_settings][preparation_seconds]" value="{{ $block['timer_settings']['preparation_seconds'] ?? \App\Workouts\TimerConfiguration::defaults()['preparation_seconds'] }}" min="0" max="60"></label></div>
        <label>Observações (opcional)<textarea name="{{ $prefix }}[notes]" rows="2" maxlength="5000">{{ $block['notes'] ?? '' }}</textarea></label>
        @if($type === 'workout')
        <fieldset class="suggested-loads-editor"><legend>Cargas sugeridas <small>(opcional)</small></legend><p class="input-hint">Referências por nível para este bloco. Texto livre, sem conversão de unidades.</p>
            <label>Equipamento ou referência<input name="{{ $prefix }}[suggested_loads][equipment]" value="{{ $block['suggested_loads']['equipment'] ?? '' }}" maxlength="80" list="equipment-suggestions" placeholder="Ex.: DB, Kettlebell, Barbell, Sled ou outro"></label>
            <datalist id="equipment-suggestions">@foreach(['Dumbbell (DB)', 'Kettlebell (KB)', 'Barbell', 'Wall Ball', 'Sandbag', 'Sled', 'Outro'] as $equipment)<option value="{{ $equipment }}">@endforeach</datalist>
            <div class="load-inputs">@foreach(\App\Workouts\SuggestedLoads::LEVELS as $key => $label)<label>{{ $label }}<input name="{{ $prefix }}[suggested_loads][{{ $key }}]" value="{{ $block['suggested_loads'][$key] ?? '' }}" maxlength="60" placeholder="Ex.: 22/16 ou 40/30 kg"></label>@endforeach</div>
            <label>Unidade para números isolados (opcional)<select name="{{ $prefix }}[suggested_loads][unit]"><option value="">Sem unidade adicional</option>@foreach(['kg', 'lb'] as $unit)<option value="{{ $unit }}" @selected(($block['suggested_loads']['unit'] ?? '') === $unit)>{{ $unit }}</option>@endforeach</select></label><p class="input-hint">A unidade selecionada só acompanha valores numéricos isolados. Pares ou descrições, como 22/16 ou 40/30 kg, aparecem exatamente como cadastrados. Deixe os três níveis vazios para remover as cargas.</p>
        </fieldset>
        @endif
        <section class="stations-editor" data-stations-editor><div class="section-heading"><h3>Estações <small>(opcional, exceto circuito por estações)</small></h3><span data-stations-count></span></div><p class="input-hint">Use os campos para organizar o circuito. A ordem pode ser alterada pelas setas. Campos de tempo vazios usam a configuração do bloco.</p><div data-stations-list>@foreach($block['stations'] ?? [] as $stationIndex => $station)<x-workouts.station-fields :index="$index" :station-index="$stationIndex" :station="$station" />@endforeach</div><template data-station-template><x-workouts.station-fields :index="$index" station-index="__INDEX__" /></template><div class="button-row"><button type="button" class="secondary-button" data-station-add>+ Adicionar estação</button>@if($modality->slug === 'hyrox')<button type="button" class="text-button" data-hyrox-template>Adicionar sugestão de 8 estações</button>@endif</div><p class="input-hint" data-station-feedback role="status"></p></section>

    </div>
</details>
