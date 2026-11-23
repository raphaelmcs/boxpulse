@props(['isAdmin' => false, 'management' => false])
<section id="workout-load-library" class="form-panel workout-load-library" data-load-library
    data-library-url="{{ route('loads.index') }}" data-library-store-url="{{ route('loads.store') }}"
    data-movement-store-url="{{ route('loads.movements.store') }}">
    @if($management)<h2>Biblioteca de Movimentos e Cargas</h2>@endif
    <details data-library-panel @if($management) open @endif>
        <summary>{{ $management ? 'Consultar e gerenciar biblioteca' : 'Biblioteca de Movimentos e Cargas' }}</summary>
        <p class="input-hint">Os presets são sugestões editáveis. Nos pares, a ordem é masculino / feminino.</p>
        <div class="field-grid two-columns">
            <label>Equipamento<select data-library-equipment></select></label>
            <label>Buscar movimento<input type="search" data-library-search placeholder="Ex.: Power Clean"></label>
            <label>Movimento<select data-library-movement></select></label>
        </div>
        @if($isAdmin)
            <div class="button-row library-actions">
                <button type="button" class="secondary-button compact" data-movement-new>Adicionar movimento</button>
                <button type="button" class="secondary-button compact" data-library-new>Adicionar preset</button>
            </div>
        @endif
        <div data-library-list></div>
        @if($isAdmin)
        <div data-movement-edit class="form-stack library-edit" hidden>
            <h3>Movimento da box</h3>
            <div class="field-grid two-columns">
                <label>Nome<input data-movement-field="name" maxlength="120" required></label>
                <label>Equipamento<input data-movement-field="equipment" maxlength="80" list="library-equipment" required></label>
                <label>Variação técnica<input data-movement-field="variation" maxlength="80"></label>
                <label>Sinônimos e abreviações<input data-movement-field="aliases" placeholder="Separe por vírgulas"></label>
            </div>
            <fieldset><legend>Modalidades compatíveis</legend>
                @foreach(['crossfit' => 'CrossFit', 'hyrox' => 'HYROX', 'functional' => 'Funcional'] as $slug => $name)
                    <label class="checkbox-label"><input type="checkbox" data-movement-modality value="{{ $slug }}">{{ $name }}</label>
                @endforeach
            </fieldset>
            <label class="checkbox-label"><input type="checkbox" data-movement-field="active" checked>Movimento ativo</label>
            <div class="button-row"><button type="button" class="primary-button compact" data-movement-save>Salvar movimento</button><button type="button" class="text-button" data-movement-cancel>Cancelar</button></div>
        </div>
        <div data-library-edit class="form-stack library-edit" hidden>
            <h3>Preset de carga</h3>
            <div class="field-grid two-columns">
                <label>Nome do preset<input data-preset-field="name" maxlength="120" required></label>
                <label>Equipamento<input data-preset-field="equipment" maxlength="80" list="library-equipment" required></label>
                <label>Movimento<select data-preset-catalog></select></label>
                <label>Contexto<select data-preset-field="context"><option value="">Qualquer contexto</option>@foreach(\App\Workouts\LoadLibrary::CONTEXTS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                <label>Origem ou referência<input data-preset-field="reference" maxlength="160"></label>
                <label class="checkbox-label"><input type="checkbox" data-preset-field="active" checked>Preset ativo</label>
            </div>
            <label>Observações<textarea data-preset-field="notes" maxlength="1500" rows="2"></textarea></label>
            <div class="preset-levels">@foreach(\App\Workouts\SuggestedLoads::LEVELS as $key => $label)
                <fieldset><legend>{{ $label }}</legend>
                    <label>Masculino (kg)<input type="text" inputmode="decimal" data-preset-level="{{ $key }}.male" maxlength="10" pattern="[0-9]+([.,][0-9]{1,3})?"></label>
                    <label>Feminino (kg)<input type="text" inputmode="decimal" data-preset-level="{{ $key }}.female" maxlength="10" pattern="[0-9]+([.,][0-9]{1,3})?"></label>
                </fieldset>
            @endforeach</div>
            <div class="button-row">
                <button type="button" class="primary-button compact" data-library-save>Salvar preset</button>
                <button type="button" class="secondary-button compact" data-library-restore>Restaurar sugestão</button>
                <button type="button" class="text-button" data-library-cancel>Cancelar</button>
            </div>
        </div>
        <datalist id="library-equipment"></datalist>
        @endif
        <p class="input-hint" role="status" data-library-feedback></p>
    </details>
</section>
