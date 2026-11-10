@props(['workout'])
@if(! auth()->user()->box->offersModality($workout->training_modality_id))<span class="status-badge disabled-modality">Modalidade desativada</span>@endif
