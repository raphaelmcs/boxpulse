@props(['workout', 'enabled' => null])
@if(! ($enabled ?? auth()->user()->box->offersModality($workout->training_modality_id)))<span class="status-badge disabled-modality">Modalidade desativada</span>@endif
