import {setupPresentationChecks} from './timers/presentation-check.js';
import {setupStationText} from './workout-text.js';
import {setupWorkoutEditor, workoutJson} from './workout-editor.js';
import {setupLoadLibrary} from './workout-loads.js';

const hyroxStations = ['SkiErg', 'Sled Push', 'Sled Pull', 'Burpee Broad Jumps', 'Row', 'Farmers Carry', 'Sandbag Lunges', 'Wall Balls'];

const timerRegistry = JSON.parse(document.querySelector('[data-timer-registry]')?.textContent || '{}');
const libraryPanel = document.querySelector('[data-load-library]');
if (libraryPanel && !document.querySelector('[data-workout-form]')) setupLoadLibrary(libraryPanel, workoutJson);

function updateTimerFields(block) {
    const type = block.querySelector('[data-timer-type]').value;
    block.querySelectorAll('[data-timer-for]').forEach((field) => {
        const active = field.classList.contains('duration-minutes-field') && timerRegistry[type] ? timerRegistry[type].duration_allowed : field.dataset.timerFor.split(' ').includes(type);
        field.hidden = !active;
        field.querySelectorAll('input, select').forEach((input) => { input.disabled = !active; });
    });
    const label = block.querySelector('[data-duration-label]');
    if (label) label.textContent = type === 'for_time' ? 'Time cap (minutos, opcional)' : type === 'emom' ? 'Duração total (minutos)' : type === 'stopwatch' ? 'Tempo limite (minutos, opcional)' : 'Duração (minutos)';
}

function renumberStations(editor) {
    const stations = [...editor.querySelector('[data-stations-list]').children];
    editor.querySelector('[data-stations-count]').textContent = `${stations.length} / 64`;
    editor.querySelector('[data-station-add]').disabled = stations.length >= 64;
    stations.forEach((station, index) => {
        station.querySelector('[data-station-title]').textContent = `Estação ${String(index + 1).padStart(2, '0')}`;
        station.querySelectorAll('[name]').forEach((input) => {
            input.name = input.name.replace(/\[stations\]\[(?:\d+|__INDEX__)\]/, `[stations][${index}]`);
        });
        station.querySelector('[data-station-up]').disabled = index === 0;
        station.querySelector('[data-station-down]').disabled = index === stations.length - 1;
    });
}

function addStation(editor, name = '') {
    const list = editor.querySelector('[data-stations-list]');
    if (list.children.length >= 64) return;
    const fragment = editor.querySelector('[data-station-template]').content.cloneNode(true);
    fragment.querySelector('input[name$="[name]"]').value = name;
    const station = fragment.querySelector('[data-station]');
    list.append(fragment);
    setupStationText(station);
    renumberStations(editor);
    updateStages();
}

function updateStages() {
    let position = 0;
    document.querySelectorAll('[data-stage]').forEach((stage, stageIndex) => {
        const toggle = document.querySelector(`[data-block-toggle="${stage.dataset.stage}"]`);
        const enabled = !toggle || toggle.checked;
        stage.disabled = !enabled;
        stage.hidden = !enabled;
        const index = enabled ? position++ : 100 + stageIndex;
        if (enabled) stage.querySelector('[data-stage-position]').textContent = String(index + 1).padStart(2, '0');
        stage.querySelectorAll('[name]').forEach((input) => { input.name = input.name.replace(/^blocks\[\d+\]/, `blocks[${index}]`); });
    });
}
document.querySelector('[data-enable-full-workout]')?.addEventListener('click', event => {
    const form = event.currentTarget.closest('[data-workout-form]');
    form.querySelector('[data-workout-structure]').value = 'full';
    document.querySelector('[data-workout-structure-label]').textContent = 'AULA COMPLETA';
    form.querySelector('[data-full-workout-switch]').hidden = true;
    const controls = form.querySelector('[data-workout-stage-controls]');
    controls.hidden = false;
    controls.querySelector('input').focus();
    form.dispatchEvent(new CustomEvent('workout:change', {bubbles: true}));
});
document.querySelectorAll('[data-block-toggle]').forEach((toggle) => {
    toggle.addEventListener('change', () => {
        const stage = document.querySelector(`[data-stage="${toggle.dataset.blockToggle}"]`);
        const hasContent = [...stage.querySelectorAll('textarea, input:not([type=hidden])')].some(input => {
            if (input.name.includes('timer_settings')) return false;
            return input.name.endsWith('[duration_extra_seconds]') ? Number(input.value) > 0 : Boolean(input.value.trim());
        });
        if (!toggle.checked && hasContent && !window.confirm('Remover esta etapa e salvar o rascunho? O conteúdo das outras etapas será preservado.')) { toggle.checked = true; return; }
        updateStages();
        stage.dispatchEvent(new CustomEvent('workout:change', {bubbles: true, detail: {immediate: !toggle.checked}}));
    });
});
updateStages();

document.querySelectorAll('[data-block-editor]').forEach((block) => {
    block.querySelector('[data-timer-type]').addEventListener('change', () => updateTimerFields(block));
    updateTimerFields(block);
});

document.querySelectorAll('[data-stations-editor]').forEach((editor) => {
    renumberStations(editor);
    editor.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button || !button.matches('[data-station-add], [data-station-remove], [data-station-up], [data-station-down], [data-hyrox-template]')) return;
        const station = button.closest('[data-station]');
        if (button.hasAttribute('data-station-add')) {
            addStation(editor);
            editor.querySelector('[data-stations-list]').lastElementChild?.querySelector('input')?.focus();
        }
        if (button.hasAttribute('data-station-remove')) station.remove();
        if (button.hasAttribute('data-station-up') && station.previousElementSibling) station.previousElementSibling.before(station);
        if (button.hasAttribute('data-station-down') && station.nextElementSibling) station.nextElementSibling.after(station);
        if (button.hasAttribute('data-hyrox-template')) {
            const available = 64 - editor.querySelector('[data-stations-list]').children.length;
            if (available < 8) {
                editor.querySelector('[data-station-feedback]').textContent = 'São necessários oito espaços livres para adicionar esta sugestão.';
                return;
            }
            hyroxStations.forEach((name) => addStation(editor, name));
            editor.querySelector('[data-station-feedback]').textContent = 'Oito sugestões adicionadas. Revise distâncias, cargas e repetições; você pode remover ou adicionar estações.';
        }
        renumberStations(editor);
        editor.dispatchEvent(new CustomEvent('workout:change', {bubbles: true}));
    });
});

setupWorkoutEditor(document.querySelector('[data-workout-form]'));

import './box-settings.js';
import './timers/session-app.js';


// Wall clock for the daily list; session timers continue using SessionClock.
document.querySelectorAll('[data-box-clock]').forEach((clock) => {
    const initial = Number(clock.dataset.serverMs);
    const anchor = performance.now();
    const timeFormat = new Intl.DateTimeFormat('pt-BR', {timeZone:clock.dataset.timezone,hour:'2-digit',minute:'2-digit',second:'2-digit'});
    const dayFormat = new Intl.DateTimeFormat('en-CA', {timeZone:clock.dataset.timezone,year:'numeric',month:'2-digit',day:'2-digit'});
    const originalDay = dayFormat.format(new Date(initial));
    const render = () => {
        const current = new Date(initial + performance.now() - anchor);
        clock.textContent = timeFormat.format(current);
        const notice = document.querySelector('[data-day-changed]');
        if (notice) notice.hidden = dayFormat.format(current) === originalDay;
    };
    render();
    const interval = setInterval(render, 1000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) render(); });
    window.addEventListener('pagehide', () => clearInterval(interval), {once:true});
});
document.querySelectorAll('[data-prepare-class]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (form.dataset.preparing) { event.preventDefault(); return; }
        form.dataset.preparing = 'true';
        form.setAttribute('aria-busy', 'true');
        form.querySelector('button[type=submit]').disabled = true;
        form.querySelector('[data-prepare-feedback]').textContent = 'Preparando sua aula…';
    });
});

document.querySelectorAll('[data-preparation-options]').forEach(options => {
    const choice = options.querySelector('[data-preparation-choice]');
    const label = options.querySelector('[data-custom-preparation]');
    const input = label.querySelector('input');
    const sync = () => {
        const custom = choice.value === 'custom';
        label.hidden = input.disabled = !custom;
        input.required = custom;
    };
    choice.addEventListener('change', sync);
    sync();
});

const tvLink = document.querySelector('[data-box-tv-link]');
if (tvLink) {
    const feedback = document.querySelector('[data-box-tv-copy-feedback]');
    document.querySelector('[data-copy-box-tv]').addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(tvLink.value);
            feedback.textContent = 'Link copiado.';
        } catch {
            tvLink.select();
            feedback.textContent = document.execCommand('copy') ? 'Link copiado.' : 'O link está selecionado para copiar.';
        }
        feedback.hidden = false;
    });

    const presence = document.querySelector('[data-box-tv-presence]');
    const status = presence.querySelector('[data-tv-online]');
    const lastSeen = presence.querySelector('[data-tv-last-seen]');
    async function refreshPresence() {
        if (document.hidden) return;
        try {
            const response = await fetch(presence.dataset.boxTvPresence, {cache: 'no-store', headers: {Accept: 'application/json'}});
            if (!response.ok) throw new Error();
            const data = await response.json();
            status.textContent = data.online ? 'TV conectada' : 'Sem comunicação recente da TV';
            lastSeen.textContent = data.last_seen_at ? `Última comunicação em ${data.last_seen_at}` : 'Aguardando a primeira comunicação.';
        } catch {
            status.textContent = 'Estado da conexão indisponível';
        }
    }
    const timer = setInterval(refreshPresence, 10000);
    document.addEventListener('visibilitychange', refreshPresence);
    window.addEventListener('pagehide', () => clearInterval(timer), {once: true});
}

window.addEventListener('pageshow', (event) => {
    if (event.persisted && document.querySelector('[data-box-clock]')) window.location.reload();
});
setupPresentationChecks();
