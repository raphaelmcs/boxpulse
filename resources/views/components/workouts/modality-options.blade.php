@props(['modalities', 'selected' => [], 'heading' => 'Modalidades oferecidas'])
@php
    $selection = old('modalities', session()->hasOldInput() ? [] : $selected);
    $selection = is_array($selection) ? array_filter($selection, 'is_scalar') : [];
    $descriptions = ['crossfit' => 'Força, técnica e condicionamento em cada WOD.', 'hyrox' => 'Corrida e estações para desenvolver resistência.', 'functional' => 'Movimento, equilíbrio e condicionamento.'];
@endphp
<fieldset class="modality-selection"><legend>{{ $heading }}</legend><p class="input-hint">Selecione ao menos uma modalidade. Você pode ajustar essa escolha depois.</p>
    <div class="modality-options">@foreach($modalities as $option)
        <label class="modality-option"><input type="checkbox" name="modalities[]" value="{{ $option->id }}" @checked(in_array((string) $option->id, array_map('strval', $selection), true))><span class="modality-option-icon" aria-hidden="true"><x-workouts.modality-icon :slug="$option->slug" /></span><span><strong>{{ $option->name }}</strong><small>{{ $descriptions[$option->slug] ?? '' }}</small></span></label>
    @endforeach</div>
    @error('modalities')<p class="field-error" role="alert">Escolha pelo menos uma modalidade válida.</p>@enderror
    @error('modalities.*')<p class="field-error" role="alert">{{ $message }}</p>@enderror
</fieldset>
