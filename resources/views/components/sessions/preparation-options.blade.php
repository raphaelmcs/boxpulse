@props(['workout'])
@php
    $storedPreparation = $workout->presentation_options['preparation_seconds'] ?? null;
    $preparation = $storedPreparation === null ? null : (int) $storedPreparation;
    $custom = $preparation !== null && ! in_array($preparation, [0, 10, 20, 30], true);
@endphp
<div class="form-stack" data-preparation-options>
    <label>Contagem de preparação
        <select name="preparation_seconds" data-preparation-choice>
            <option value="" @selected($preparation === null)>Por etapa (padrão de 10 s)</option>
            <option value="0" @selected($preparation === 0)>Sem contagem</option>
            <option value="10" @selected($preparation === 10)>10 segundos — padrão</option>
            <option value="20" @selected($preparation === 20)>20 segundos</option>
            <option value="30" @selected($preparation === 30)>30 segundos</option>
            <option value="custom" @selected($custom)>Personalizar</option>
        </select>
    </label>
    <label data-custom-preparation>Contagem personalizada em segundos
        <input name="custom_preparation_seconds" type="number" min="0" max="60" step="1" value="{{ $custom ? $preparation : '' }}">
    </label>
    <p class="input-hint">Uma contagem geral substitui a preparação de cada etapa cronometrada nesta aula.</p>
    <input type="hidden" name="auto_advance" value="0">
    <label class="checkbox-label"><input type="checkbox" name="auto_advance" value="1" @checked($workout->presentation_options['auto_advance'] ?? false)>Avançar automaticamente para a próxima etapa ao concluir o cronômetro.</label>
</div>
