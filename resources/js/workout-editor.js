import {WorkoutAutosave} from './workout-autosave.js';
import {setupWorkoutText} from './workout-text.js';
import {setupWorkoutLoads} from './workout-loads.js';

export function readWorkoutForm(form) {
    const data = {};
    for (const [name, value] of new FormData(form)) {
        if (name.startsWith('_') || ['expected_edit_version', 'editor_write_id', 'editor_intent'].includes(name)) continue;
        const path = name.match(/[^\[\]]+/g);
        let target = data;
        path.slice(0, -1).forEach((key, index) => {
            target[key] ??= /^\d+$/.test(path[index + 1]) ? [] : {};
            target = target[key];
        });
        target[path.at(-1)] = value;
    }
    const editors = form.querySelectorAll('[data-stage]:not([disabled]) [data-block-editor]');
    editors.forEach((editor, index) => {
        const block = data.blocks[index];
        const style = node => {
            const fields = Object.fromEntries(Object.entries(node.workoutTextStyle).filter(([, value]) => value && Object.keys(value).length));
            return Object.keys(fields).length ? structuredClone(fields) : null;
        };
        editor.querySelectorAll('[data-station]').forEach((station, position) => { block.stations[position].text_style = style(station); });
        const entries = editor.readWorkoutLoads();
        block.text_style = style(editor);
        block.load_entries = entries.length ? entries : null;
    });
    return data;
}

export async function workoutJson(url, method = 'GET', data = undefined) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 15000);
    try {
        const response = await fetch(url, {
            method, credentials: 'same-origin', signal: controller.signal,
            headers: {
                Accept: 'application/json', 'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: data === undefined ? undefined : JSON.stringify(data),
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
            const error = new Error(Object.values(body.errors ?? {}).flat()[0] || body.message || 'Falha na comunicação com o servidor.');
            error.status = response.status;
            throw error;
        }
        return body;
    } finally {
        clearTimeout(timeout);
    }
}

export function setupWorkoutEditor(form) {
    if (!form) return;
    setupWorkoutText(form);
    setupWorkoutLoads(form, workoutJson);
    const feedback = form.querySelector('[data-save-feedback]');
    const retry = form.querySelector('[data-save-retry]');
    const backup = form.querySelector('[data-save-backup]');
    const latest = form.querySelector('[data-save-latest]');
    const publication = form.querySelector('[data-save-publication]');
    const publish = form.querySelector('[data-editor-publish]');
    const preview = form.querySelector('[data-editor-preview]');
    const initialVersion = Number(form.dataset.editorVersion);
    const schedule = document.querySelector('[data-workout-schedule]');
    let editUrl = form.dataset.editorEditUrl;
    let previewWindow = null;
    let previewRequested = false;
    if (form.dataset.editorPreviewUrl) {
        preview.href = form.dataset.editorPreviewUrl;
        preview.hidden = false;
    }
    const openPreview = () => {
        if (previewWindow && !previewWindow.closed) previewWindow.location.replace(preview.href);
        previewWindow = null;
        previewRequested = false;
    };
    const render = (state, {response, error, mode} = {}) => {
        retry.hidden = state !== 'failed';
        backup.hidden = !['conflict', 'failed'].includes(state);
        latest.hidden = state !== 'conflict' || !editUrl;
        if (editUrl) latest.href = editUrl;
        if (response) {
            if (!editUrl) window.history.replaceState(window.history.state, '', response.edit_url);
            editUrl = response.edit_url;
            form.action = response.update_url;
            preview.href = response.preview_url;
            if (schedule && response.schedule_url) schedule.href = response.schedule_url;
            preview.hidden = false;
            publication.textContent = response.has_draft ? 'Publicado · rascunho de alterações' : response.published ? 'Publicado' : 'Rascunho';
            if (publish) publish.textContent = response.published ? 'Republicar treino' : 'Publicar treino';
            if (state === 'saved' && previewRequested) openPreview();
        }
        if (['failed', 'conflict'].includes(state) && previewRequested) {
            previewWindow?.close();
            previewWindow = null;
            previewRequested = false;
        }
        const labels = {
            pending: 'Alterações pendentes.',
            saving: 'Salvando…',
            saved: mode === 'publish' ? 'Revisão publicada.' : 'Salvo automaticamente.',
            failed: 'Falha ao salvar. As alterações continuam nesta aba.',
            conflict: 'Outra aba ou pessoa salvou uma revisão. As alterações continuam nesta aba.',
        };
        feedback.textContent = labels[state] + (state === 'failed' && error?.status === 422 ? ` ${error.message}` : '');
        feedback.dataset.state = state;
    };

    const editor = new WorkoutAutosave({
        version: initialVersion,
        url: form.action,
        read: () => readWorkoutForm(form),
        makeId: () => crypto.randomUUID(),
        send: task => workoutJson(task.url, task.version === 0 ? 'POST' : 'PUT', {
            ...task.data, editor_mode: task.mode, expected_edit_version: task.version, editor_write_id: task.id,
        }),
        render,
    });

    const change = event => {
        if (!event.target.name) return;
        editor.changed();
    };
    form.addEventListener('input', change);
    form.addEventListener('change', change);
    form.addEventListener('workout:change', event => editor.changed(event.detail?.immediate));
    form.addEventListener('submit', event => {
        event.preventDefault();
        const mode = event.submitter?.value ?? 'draft';
        if (mode === 'preview' && editor.failure?.status !== 409) {
            previewRequested = true;
            previewWindow = window.open('about:blank', '_blank');
            if (previewWindow) previewWindow.opener = null;
            if (!editor.dirty && !editor.failure && !preview.hidden) {
                openPreview();
                return;
            }
        }
        editor.save(mode === 'publish' ? 'publish' : 'draft', mode !== 'publish' && (editor.version === 0 || Boolean(editor.failure)));
    });
    retry.addEventListener('click', () => editor.retry());
    backup.addEventListener('click', () => {
        const file = new Blob([JSON.stringify(readWorkoutForm(form), null, 2)], {type: 'application/json'});
        const url = URL.createObjectURL(file);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'boxpulse-alteracoes.json';
        link.click();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    });
    window.addEventListener('beforeunload', event => {
        if (!editor.dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
}
