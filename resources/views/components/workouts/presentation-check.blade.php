@props(['box', 'modality', 'title' => '', 'blocks' => [], 'editUrl' => null, 'preparation' => null, 'formId' => null])
@unless($formId)
    <button type="button" class="secondary-button compact" data-open-tv-preview aria-haspopup="dialog" aria-controls="tv-preview">Visualizar na TV</button>
@endunless
<dialog class="presentation-check" id="tv-preview" aria-labelledby="tv-preview-title" data-presentation-check
    data-timezone="{{ $box->timezone }}" data-preview-preparation="{{ $preparation }}" @if($formId) data-preview-form="{{ $formId }}" @endif>
    <div class="tv-preview-heading">
        <h2 id="tv-preview-title">Prévia da TV</h2>
        <button type="button" class="secondary-button compact" data-close-tv-preview autofocus>Fechar</button>
    </div>
    <label class="tv-preview-controls" data-preview-controls>Etapa<select data-preview-block></select></label>
    <p class="presentation-check-result" data-capacity-result role="status"></p>
    <a class="text-link" data-capacity-edit href="{{ $editUrl ?? '#' }}" hidden>Editar etapa com excesso →</a>
    <div class="tv-preview-region" data-preview-region>
        <div class="tv-preview-viewport" data-preview-viewport>
            <x-sessions.screen :box="$box" :title="$title" :modality="$modality" data-preview-screen data-view="preview" />
        </div>
        <button type="button" class="secondary-button compact" data-preview-fullscreen>Simular tela cheia</button>
    </div>
    <p class="input-hint" data-preview-feedback role="status" hidden></p>
    <script type="application/json" data-capacity-blocks>@json($blocks)</script>
    <script type="application/json" data-preview-timers>@json(['labels' => \App\Workouts\TimerConfiguration::LABELS, 'defaults' => \App\Workouts\TimerConfiguration::defaults()])</script>
</dialog>
