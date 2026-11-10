@props(['blocks' => [], 'editUrl' => null])
<section class="form-panel presentation-check" data-presentation-check>
    <h3>Legibilidade na TV</h3><p class="input-hint">Confira as etapas antes de apresentar. O ajuste usa até duas colunas e mantém um tamanho mínimo de leitura; volumes excessivos precisam ser reorganizados.</p>
    <label>Resolução da apresentação<select data-capacity-resolution><option value="1920,1080">Full HD · 1920 × 1080</option><option value="2560,1440">QHD · 2560 × 1440</option><option value="3840,2160">4K · 3840 × 2160</option></select></label>
    <p class="presentation-check-result" data-capacity-result role="status">Conferindo o conteúdo…</p>
    <a class="text-link" data-capacity-edit @if($editUrl) href="{{ $editUrl }}" @else href="#" @endif>Reorganizar ou resumir conteúdo →</a>
    <script type="application/json" data-capacity-blocks>@json($blocks)</script>
</section>
