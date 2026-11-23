@props(['entries' => []])
<fieldset class="suggested-loads-editor" data-load-editor>
    <legend>Cargas sugeridas <small>(opcional)</small></legend>
    <p class="input-hint">Nos pares, informe masculino / feminino. Ajuste a sugestão ao movimento, ao volume e ao estímulo da aula.</p>
    <div class="button-row equipment-buttons" data-equipment-buttons aria-label="Equipamentos"></div>
    <div data-load-list></div>
    <div class="button-row">
        <button type="button" class="secondary-button" data-load-analyze>Analisar treino e sugerir cargas</button>
        <a class="text-link" href="#workout-load-library" data-open-library>Ver biblioteca</a>
    </div>
    <div class="load-review" data-load-review hidden></div>
    <p class="input-hint" role="status" data-load-feedback></p>
    <script type="application/json" data-load-initial>@json($entries)</script>
</fieldset>
