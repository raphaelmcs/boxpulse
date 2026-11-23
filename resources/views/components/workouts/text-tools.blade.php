@props(['field'])
@php($multiline = in_array($field, ['description', 'notes'], true))
<details class="text-tools" data-text-tools="{{ $field }}">
    <summary>{{ $multiline ? 'Formatar e organizar linhas' : ($field === 'name' ? 'Formatar exercício' : 'Formatar título') }}</summary>
    <div class="text-toolbar">
        <div class="button-row" role="group" aria-label="Peso e estilo">
            <button class="secondary-button" type="button" data-text-weight="bold" aria-pressed="false">Negrito</button>
            <button class="secondary-button" type="button" data-text-weight="normal" aria-pressed="false">Texto normal</button>
            @if($multiline)<button class="secondary-button" type="button" data-text-role aria-pressed="false">Subtítulo</button>@endif
            <button class="text-button" type="button" data-text-reset>Restaurar padrão</button>
        </div>
        <div class="button-row text-colors" role="group" aria-label="Cor do texto">
            @foreach(['primary' => 'Primária da box', 'secondary' => 'Secundária da box', '#FFFFFF' => 'Branco', '#FACC15' => 'Amarelo', '#4ADE80' => 'Verde', '#60A5FA' => 'Azul', '#FB923C' => 'Laranja', '#F87171' => 'Vermelho'] as $color => $label)
                <button type="button" data-text-color="{{ $color }}" @class(['text-swatch', "text-swatch-$color" => ! str_starts_with($color, '#')]) @if(str_starts_with($color, '#')) style="--swatch:{{ $color }}" @endif aria-label="{{ $label }}" title="{{ $label }}" aria-pressed="false"></button>
            @endforeach
            <label class="color-picker-label">Cor personalizada <input type="color" data-text-picker value="#FFFFFF"></label>
        </div>
        @if($multiline)
        <div class="button-row" role="group" aria-label="Organizar linhas">
            <label>Linha <select data-text-line aria-label="Selecionar linha"></select></label>
            <button class="text-button" type="button" data-text-move="-1" aria-label="Mover linha para cima">↑</button>
            <button class="text-button" type="button" data-text-move="1" aria-label="Mover linha para baixo">↓</button>
            <button class="text-button" type="button" data-text-add>Inserir abaixo</button>
            <button class="text-button" type="button" data-text-remove>Remover linha</button>
        </div>
        @endif
        <span class="text-line-preview" data-text-sample></span>
    </div>
</details>
