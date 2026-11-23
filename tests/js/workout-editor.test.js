import test from 'node:test';
import assert from 'node:assert/strict';
import {WorkoutAutosave} from '../../resources/js/workout-autosave.js';
import {applyReviewedLoads} from '../../resources/js/workout-loads.js';
import {readableColor, remapLineStyles} from '../../resources/js/workout-text.js';

function harness({version = 4} = {}) {
    const requests = [];
    const states = [];
    let callback, wait, id = 0, value = '';
    const saver = new WorkoutAutosave({
        version,
        url: version ? '/workouts/42' : '/workouts',
        makeId: () => `write-${++id}`,
        read: () => ({title: value}),
        render: state => states.push(state),
        send: task => new Promise((resolve, reject) => {
            requests.push({
                ...task,
                resolve: (newVersion = task.version + 1) => resolve({version: newVersion, update_url: '/workouts/42'}),
                reject,
            });
        }),
        schedule: (handler, delay) => { callback = handler; wait = delay; return 1; },
        cancel: () => { callback = null; },
    });

    return {
        requests, states, saver,
        type: (next, immediate) => { value = next; saver.changed(immediate); },
        debounce: () => { assert.equal(wait, 1000); return callback(); },
    };
}

const settle = () => new Promise(resolve => setImmediate(resolve));

test('debounce waits one second and sends only the last edit as a draft', async () => {
    const h = harness();
    h.type('T');
    h.type('Treino');
    assert.equal(h.requests.length, 0);
    assert.equal(h.states.at(-1), 'pending');
    const save = h.debounce();
    assert.equal(h.requests.length, 1);
    assert.equal(h.requests[0].data.title, 'Treino');
    assert.equal(h.requests[0].mode, 'draft');
    h.requests[0].resolve();
    await save;
    assert.equal(h.states.at(-1), 'saved');
    assert.equal(h.saver.dirty, false);
});

test('the normal save button bypasses debounce and serializes edits received in flight', async () => {
    const h = harness();
    h.type('Primeira versão');
    const save = h.saver.save();
    assert.equal(h.requests.length, 1);
    h.type('Segunda versão');
    h.debounce();
    assert.equal(h.requests.length, 1);
    h.requests[0].resolve();
    await settle();
    assert.equal(h.requests.length, 2);
    assert.equal(h.requests[1].version, 5);
    assert.equal(h.requests[1].data.title, 'Segunda versão');
    assert.equal(h.saver.dirty, true);
    h.requests[1].resolve();
    await save;
    assert.equal(h.saver.dirty, false);
});

test('an offline retry preserves the original write id, payload and creation route', async () => {
    const h = harness({version: 0});
    h.type('Antes da falha');
    const first = h.debounce();
    h.type('Digitado sem rede');
    h.requests[0].reject(new TypeError('fetch failed'));
    await first;
    assert.equal(h.states.at(-1), 'failed');
    assert.equal(h.saver.dirty, true);

    const retry = h.saver.retry();
    assert.equal(h.requests[1].data, h.requests[0].data);
    assert.equal(h.requests[1].id, h.requests[0].id);
    assert.equal(h.requests[1].url, '/workouts');
    h.requests[1].resolve();
    await settle();
    assert.equal(h.requests[2].url, '/workouts/42');
    assert.equal(h.requests[2].data.title, 'Digitado sem rede');
    assert.equal(h.requests[2].version, 1);
    h.requests[2].resolve();
    await retry;
    assert.equal(h.saver.dirty, false);
});

test('a concurrent edit conflict retains unsaved content and prevents further writes in that tab', async () => {
    const h = harness();
    h.type('Conteúdo nesta aba');
    const first = h.debounce();
    h.requests[0].reject(Object.assign(new Error('Conflito'), {status: 409}));
    await first;
    h.type('Editado localmente');
    await h.saver.save();
    await h.saver.retry();
    assert.equal(h.requests.length, 1);
    assert.equal(h.saver.dirty, true);
    assert.equal(h.states.at(-1), 'conflict');
});

test('a stale acknowledgement does not advance the editor or clear unsaved changes', async () => {
    const h = harness();
    h.type('Esta versão ainda não foi confirmada');
    const save = h.debounce();
    h.requests[0].resolve(3);
    await save;
    assert.equal(h.saver.version, 4);
    assert.equal(h.saver.dirty, true);
    assert.equal(h.states.at(-1), 'failed');
});

test('publication submits the content explicitly reviewed at the click', async () => {
    const h = harness();
    h.type('Rascunho inicial');
    const saving = h.debounce();
    h.type('Revisado para publicar');
    h.saver.save('publish');
    h.type('Digitado depois da publicação');
    h.debounce();
    h.requests[0].resolve();
    await settle();
    assert.equal(h.requests[1].mode, 'publish');
    assert.equal(h.requests[1].data.title, 'Revisado para publicar');
    h.requests[1].resolve();
    await settle();
    assert.equal(h.requests[2].mode, 'draft');
    assert.equal(h.requests[2].data.title, 'Digitado depois da publicação');
    h.requests[2].resolve();
    await saving;
});

test('structural removal can send immediately', async () => {
    const h = harness();
    h.type('Etapa removida', true);
    assert.equal(h.requests.length, 1);
    h.requests[0].resolve();
    await settle();
    assert.equal(h.saver.dirty, false);
});

test('line styles stay attached after inserting and reordering lines and kept apart after editing', () => {
    const styles = {0: {font_weight: 'bold'}, 2: {text_color: '#ffffff'}};
    const original = ['A', 'B', 'C'];
    assert.deepEqual(remapLineStyles(original, ['A', 'Nova', 'B', 'C'], styles), {0: styles[0], 3: styles[2]});
    assert.deepEqual(remapLineStyles(original, ['C', 'A', 'B'], styles), {0: styles[2], 1: styles[0]});
    assert.deepEqual(remapLineStyles(original, ['A', 'B', 'C editada'], styles), styles);
    assert.deepEqual(remapLineStyles(original, ['A', 'C'], styles), {0: styles[0], 1: styles[2]});
    assert.equal(readableColor('red;display:none'), '');
    assert.equal(readableColor('#ffffff'), '#ffffff');
    assert.notEqual(readableColor('#000000'), '#000000');
});

test('selection applies reviewed suggestions and requires confirmation to replace a manual load', () => {
    const existing = [{equipment: 'Dumbbell', movement: 'Dumbbell Snatch', rx: '20 / 16 kg', source: 'manual'}];
    const prescription = {equipment: 'Dumbbell', movement: 'Dumbbell Snatch', rx: '22,5 / 15 kg', source: 'explicit'};
    const chosen = {equipment: 'Wall Ball', movement: 'Wall Ball', rx: '9 / 6 kg', source: 'preset'};
    const result = applyReviewedLoads(existing, [prescription, chosen], () => false);
    assert.deepEqual(result, {entries: [existing[0], chosen], applied: 1, skipped: 1});
    assert.deepEqual(applyReviewedLoads(existing, [prescription], () => true).entries, [prescription]);
    assert.equal(existing[0].rx, '20 / 16 kg');
});
