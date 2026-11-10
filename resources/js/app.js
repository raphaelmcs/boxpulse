const hyroxStations = ['SkiErg', 'Sled Push', 'Sled Pull', 'Burpee Broad Jumps', 'Row', 'Farmers Carry', 'Sandbag Lunges', 'Wall Balls'];

const timerRegistry = JSON.parse(document.querySelector('[data-timer-registry]')?.textContent || '{}');

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
    list.append(fragment);
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
        stage.querySelectorAll('[name]').forEach((input) => { input.name = input.name.replace(/^blocks\[\d+\]/, `blocks[${index}]`); });
    });
}
document.querySelectorAll('[data-block-toggle]').forEach((toggle) => {
    toggle.addEventListener('change', () => {
        const stage = document.querySelector(`[data-stage="${toggle.dataset.blockToggle}"]`);
        const hasContent = [...stage.querySelectorAll('textarea, input:not([type=hidden])')].some(input => input.value.trim() && !input.name.includes('timer_settings') && !input.name.includes('duration_'));
        if (!toggle.checked && hasContent && !window.confirm('Remover esta etapa ao salvar? O conteúdo das outras etapas será preservado. Você pode reativá-la antes de salvar.')) { toggle.checked = true; return; }
        updateStages();
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
        if (!button) return;
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
    });
});

document.querySelector('[data-workout-form]')?.addEventListener('submit', (event) => {
    if (event.currentTarget.dataset.saving) {
        event.preventDefault();
        return;
    }
    event.currentTarget.dataset.saving = 'true';
    event.currentTarget.setAttribute('aria-busy', 'true');
    event.currentTarget.querySelector('[data-save-feedback]').textContent = 'Salvando seu treino…';
});

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

window.addEventListener('pageshow', (event) => {
    if (event.persisted && document.querySelector('[data-box-clock]')) window.location.reload();
});
import './timers/presentation-check.js';
