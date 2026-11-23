import {implementsLabels} from './timers/suggested-loads.js';

const LEVELS = {rx: 'RX', intermediate: 'Intermediate', scale: 'Scale'};
const SOURCES = {manual: 'Edição manual', preset: 'Preset da biblioteca', explicit: 'Carga prescrita no texto'};
const MAX_ENTRIES = 12;
let movementListId = 0;
const INITIAL_EQUIPMENT = ['Barbell', 'Dumbbell', 'Kettlebell', 'Wall Ball', 'Sandbag', 'Sled', 'Medicine Ball', 'Plate', 'Outros'];

const key = value => String(value ?? '').normalize('NFD').replace(/\p{M}/gu, '').trim().replace(/\s+/g, ' ').toLowerCase();
const hasLoads = entry => Object.keys(LEVELS).some(level => String(entry[level] ?? '').trim() !== '');
const presetWithLoads = preset => Object.values(preset.levels).some(pair => Object.values(pair).some(value => value != null && value !== ''));

function element(tag, text = '', className = '') {
    const node = document.createElement(tag);
    node.textContent = text;
    node.className = className;
    return node;
}

function button(text, action, className = 'text-button') {
    const node = element('button', text, className);
    node.type = 'button';
    node.addEventListener('click', action);
    return node;
}

function namedField(label, node) {
    const wrapper = element('label', label);
    wrapper.append(node);
    return wrapper;
}

function textInput(value, maxlength, placeholder = '') {
    const input = document.createElement('input');
    input.value = value ?? '';
    input.maxLength = maxlength;
    input.placeholder = placeholder;
    return input;
}

const numberLabel = value => String(value).replace('.', ',');

function pairLabel(pair, unit) {
    if (pair?.male != null && pair?.female != null) return `${numberLabel(pair.male)} / ${numberLabel(pair.female)} ${unit}`;
    if (pair?.male != null) return `${numberLabel(pair.male)} ${unit} (masc.)`;
    if (pair?.female != null) return `${numberLabel(pair.female)} ${unit} (fem.)`;
    return '';
}

function presetValues(preset) {
    return Object.fromEntries(Object.keys(LEVELS).map(level => [level, pairLabel(preset.levels[level], preset.unit)]));
}

function presetName(preset) {
    return [...new Set([preset.name, preset.movement || preset.equipment, preset.reference].filter(Boolean))].join(' · ');
}

function summarize(entry) {
    return Object.entries(LEVELS).filter(([level]) => entry[level]).map(([level, label]) => `${label} ${entry[level]}`).join(' · ');
}

function fieldsForLevels(entry, onChange) {
    const fields = element('div', '', 'load-inputs');
    for (const [level, label] of Object.entries(LEVELS)) {
        const input = textInput(entry[level], 60, 'Ex.: 22,5 / 15 kg');
        input.addEventListener('input', () => onChange(level, input.value));
        fields.append(namedField(label, input));
    }
    return fields;
}

export function applyReviewedLoads(entries, suggestions, mayReplace) {
    const result = entries.map(entry => ({...entry}));
    let applied = 0, skipped = 0;
    for (const suggestion of suggestions) {
        if (!hasLoads(suggestion)) continue;
        const matching = result.findIndex(entry => key(entry.equipment) === key(suggestion.equipment) && key(entry.movement) === key(suggestion.movement));
        const position = matching >= 0 ? matching : result.findIndex(entry => key(entry.equipment) === key(suggestion.equipment) && !entry.movement && !hasLoads(entry));
        if (position >= 0 && hasLoads(result[position]) && !mayReplace(result[position], suggestion)) {
            skipped++;
            continue;
        }
        if (position >= 0) result[position] = {...suggestion};
        else if (result.length < MAX_ENTRIES) result.push({...suggestion});
        else {
            skipped++;
            continue;
        }
        applied++;
    }
    return {entries: result, applied, skipped};
}

export function setupLoadLibrary(section, request, onChange = () => {}, form = null) {
    const listing = section.querySelector('[data-library-list]');
    const presetEditor = section.querySelector('[data-library-edit]');
    const movementEditor = section.querySelector('[data-movement-edit]');
    const feedback = section.querySelector('[data-library-feedback]');
    const equipmentFilter = section.querySelector('[data-library-equipment]');
    const search = section.querySelector('[data-library-search]');
    const movementFilter = section.querySelector('[data-library-movement]');
    const state = {equipment: INITIAL_EQUIPMENT, presets: [], movements: [], contexts: {}};
    let loaded = false, loading, selectedPreset, selectedMovement, saving = false;
    const field = (editor, prefix, name) => editor.querySelector(`[data-${prefix}-field="${name}"]`);
    const equipmentNames = () => [...new Set([...state.equipment, ...[...state.movements, ...state.presets].map(item => item.equipment)])];
    const matchesMovement = (preset, movement) => preset.movement_id
        ? String(preset.movement_id) === String(movement.id)
        : key(preset.movement) === key(movement.name) && key(preset.equipment) === key(movement.equipment);
    const editingEquipment = () => field(presetEditor, 'preset', 'equipment');
    const catalogChoice = () => presetEditor.querySelector('[data-preset-catalog]');
    let legacyMovement;

    function renderPreset(preset, movement = null) {
        const row = element('li');
        const type = preset.origin === 'base' ? 'Sugestão da biblioteca-base' : (preset.active ? 'Preset da box' : 'Inativo na box');
        row.append(element('strong', presetName(preset)),
            element('p', [type, state.contexts[preset.context], summarize(presetValues(preset)) || 'Sem pesos cadastrados'].filter(Boolean).join(' · '), 'input-hint'));
        if (preset.notes) row.append(element('p', preset.notes, 'input-hint'));
        if (presetEditor) {
            row.append(button(preset.id ? 'Editar cargas' : 'Personalizar', () => editPreset(preset, movement)));
            if (preset.id && preset.active) row.append(button('Desativar preset', async () => {
                if (!window.confirm(`Desativar o preset ${preset.name ?? preset.movement ?? preset.equipment}?`)) return;
                await mutate(`${section.dataset.libraryStoreUrl}/${preset.id}`, 'PUT', {
                    ...Object.fromEntries(['equipment', 'movement', 'movement_id', 'name', 'context', 'reference', 'notes', 'levels', 'unit'].map(name => [name, preset[name]])),
                    active: false,
                }, 'Preset desativado.');
            }));
        }
        return row;
    }

    function render() {
        const equipment = equipmentFilter.value;
        equipmentFilter.replaceChildren(new Option('Todos os equipamentos', ''), ...equipmentNames().map(name => new Option(name)));
        equipmentFilter.value = equipment;
        const query = key(search.value);
        const equipmentMatches = item => !equipment || key(item.equipment) === key(equipment);
        const known = state.movements.filter(equipmentMatches);
        const movements = known.filter(item => !query || [item.name, ...item.aliases].some(name => key(name).includes(query)));
        const selected = movementFilter.value;
        movementFilter.replaceChildren(new Option('Todos os movimentos', ''), ...movements.map(item => new Option(`${item.name} · ${item.equipment}`, item.key)),
            ...(!query ? [new Option('Presets genéricos e legados', 'legacy')] : []));
        if ([...movementFilter.options].some(option => option.value === selected)) movementFilter.value = selected;
        const choice = movementFilter.value;
        const rows = movements.filter(item => !choice || choice === item.key).map(item => {
            const group = element('section', '', 'library-movement');
            group.append(element('h3', item.name), element('p', [item.equipment, item.variation, item.active ? null : 'Inativo'].filter(Boolean).join(' · '), 'input-hint'));
            const presets = state.presets.filter(preset => matchesMovement(preset, item));
            const list = element('ul', '', 'library-list');
            if (presets.length) list.append(...presets.map(preset => renderPreset(preset, item)));
            else list.append(element('li', 'Sem presets para este movimento.', 'input-hint'));
            group.append(list);
            if (presetEditor && item.active) group.append(button('Adicionar preset', () => editPreset(null, item)));
            if (movementEditor && item.id) group.append(button('Editar movimento', () => editMovement(item)));
            return group;
        });
        if (!choice || choice === 'legacy') {
            const legacy = state.presets.filter(preset => equipmentMatches(preset)
                && !known.some(item => matchesMovement(preset, item))
                && (!query || key(preset.movement).includes(query)));
            if (legacy.length) {
                const group = element('section', '', 'library-movement');
                const list = element('ul', '', 'library-list');
                list.append(...legacy.map(preset => renderPreset(preset)));
                group.append(element('h3', 'Presets genéricos e legados'), list);
                rows.push(group);
            }
        }
        listing.replaceChildren(...(rows.length ? rows : [element('p', 'Nenhum movimento encontrado.', 'input-hint')]));
        section.querySelector('#library-equipment')?.replaceChildren(...equipmentNames().map(name => new Option(name)));
        onChange(equipmentNames());
    }

    async function load() {
        if (loaded) return state;
        if (loading) return loading;
        feedback.textContent = 'Carregando biblioteca…';
        loading = request(section.dataset.libraryUrl).then(data => {
            Object.assign(state, data);
            loaded = true;
            feedback.textContent = '';
            render();
            return state;
        }).catch(error => {
            feedback.textContent = `Falha ao consultar a biblioteca. ${error.message}`;
            throw error;
        }).finally(() => { loading = null; });
        return loading;
    }

    async function mutate(url, method, data, message) {
        if (saving) return false;
        saving = true;
        try {
            await request(url, method, data);
            loaded = false;
            await load();
            feedback.textContent = message;
            return true;
        } catch (error) {
            feedback.textContent = error.message;
            return false;
        } finally {
            saving = false;
        }
    }

    function readFields(editor, prefix) {
        return Object.fromEntries([...editor.querySelectorAll(`[data-${prefix}-field]`)].map(field => [
            field.getAttribute(`data-${prefix}-field`), field.type === 'checkbox' ? field.checked : field.value.trim(),
        ]));
    }

    function fillFields(editor, prefix, data) {
        for (const input of editor.querySelectorAll(`[data-${prefix}-field]`)) {
            const name = input.getAttribute(`data-${prefix}-field`);
            if (input.type === 'checkbox') input.checked = data?.[name] ?? true;
            else input.value = data?.[name] ?? '';
        }
    }

    function refreshCatalog(preferred = catalogChoice().value) {
        const options = state.movements.filter(item => key(item.equipment) === key(editingEquipment().value) && (item.active || item.key === preferred));
        catalogChoice().replaceChildren(new Option('Genérico · sem movimento', ''), ...options.map(item => new Option(item.name, item.key)));
        if (legacyMovement?.equipment === editingEquipment().value) catalogChoice().append(new Option(legacyMovement.name, 'legacy'));
        if ([...catalogChoice().options].some(option => option.value === preferred)) catalogChoice().value = preferred;
        presetEditor.querySelector('[data-library-restore]').disabled = !baseSuggestion();
    }

    function baseSuggestion() {
        const movement = state.movements.find(item => item.key === catalogChoice().value);
        return movement && state.presets.find(preset => preset.origin === 'base' && key(preset.equipment) === key(movement.equipment)
            && key(preset.movement) === key(movement.name));
    }

    function fillLevels(levels) {
        for (const input of presetEditor.querySelectorAll('[data-preset-level]')) {
            const [level, group] = input.dataset.presetLevel.split('.');
            input.value = levels?.[level]?.[group] ?? '';
        }
    }

    function editPreset(preset, movement = state.movements.find(item => item.key === movementFilter.value)) {
        selectedPreset = preset?.id ? preset : null;
        legacyMovement = !movement && preset?.movement ? {name: preset.movement, equipment: preset.equipment} : null;
        movementEditor.hidden = true;
        presetEditor.hidden = false;
        fillFields(presetEditor, 'preset', {...preset, equipment: preset?.equipment ?? movement?.equipment ?? equipmentFilter.value, active: preset?.active ?? true});
        fillLevels(preset?.levels);
        refreshCatalog(movement?.key ?? (legacyMovement ? 'legacy' : ''));
        field(presetEditor, 'preset', 'name').focus();
    }

    function editMovement(movement = null) {
        selectedMovement = movement;
        presetEditor.hidden = true;
        movementEditor.hidden = false;
        fillFields(movementEditor, 'movement', {...movement, equipment: movement?.equipment ?? equipmentFilter.value, aliases: movement?.aliases.join(', ')});
        for (const input of movementEditor.querySelectorAll('[data-movement-modality]')) input.checked = movement?.modalities.includes(input.value) ?? input.value === 'crossfit';
        field(movementEditor, 'movement', 'name').focus();
    }

    const panel = section.querySelector('[data-library-panel]');
    panel.addEventListener('toggle', () => { if (panel.open) load().catch(() => {}); });
    form?.querySelectorAll('[data-open-library]').forEach(link => link.addEventListener('click', event => {
        event.preventDefault();
        panel.open = true;
        panel.querySelector('summary').focus();
        section.scrollIntoView({block: 'start'});
    }));
    equipmentFilter.addEventListener('change', render);
    search.addEventListener('input', () => { movementFilter.value = ''; render(); });
    movementFilter.addEventListener('change', render);
    if (presetEditor) {
        section.querySelector('[data-library-new]').addEventListener('click', async () => { if (await load().catch(() => null)) editPreset(null); });
        section.querySelector('[data-movement-new]').addEventListener('click', () => editMovement());
        presetEditor.querySelector('[data-library-cancel]').addEventListener('click', () => { presetEditor.hidden = true; });
        movementEditor.querySelector('[data-movement-cancel]').addEventListener('click', () => { movementEditor.hidden = true; });
        editingEquipment().addEventListener('input', () => refreshCatalog());
        catalogChoice().addEventListener('change', () => refreshCatalog());
        presetEditor.querySelector('[data-library-restore]').addEventListener('click', () => {
            const preset = baseSuggestion();
            if (preset && window.confirm('Restaurar os pesos da sugestão da biblioteca-base neste preset?')) fillLevels(preset.levels);
        });
        presetEditor.querySelector('[data-library-save]').addEventListener('click', async () => {
            if ([...presetEditor.querySelectorAll('input')].some(input => !input.reportValidity())) return;
            const movement = state.movements.find(item => item.key === catalogChoice().value);
            const data = {...readFields(presetEditor, 'preset'), unit: 'kg', levels: {},
                movement_id: movement?.id ?? null, movement: movement?.name ?? (catalogChoice().value === 'legacy' ? legacyMovement?.name : null)};
            for (const input of presetEditor.querySelectorAll('[data-preset-level]')) {
                const [level, group] = input.dataset.presetLevel.split('.');
                data.levels[level] ??= {};
                data.levels[level][group] = input.value.trim() || null;
            }
            const origin = section.dataset.libraryStoreUrl;
            if (await mutate(selectedPreset ? `${origin}/${selectedPreset.id}` : origin, selectedPreset ? 'PUT' : 'POST', data, 'Preset salvo.')) presetEditor.hidden = true;
        });
        movementEditor.querySelector('[data-movement-save]').addEventListener('click', async () => {
            if ([...movementEditor.querySelectorAll('input')].some(input => !input.reportValidity())) return;
            const data = readFields(movementEditor, 'movement');
            data.aliases = data.aliases.split(',').map(alias => alias.trim()).filter(Boolean);
            data.modalities = [...movementEditor.querySelectorAll('[data-movement-modality]:checked')].map(input => input.value);
            const origin = section.dataset.movementStoreUrl;
            if (await mutate(selectedMovement ? `${origin}/${selectedMovement.id}` : origin, selectedMovement ? 'PUT' : 'POST', data, 'Movimento salvo.')) movementEditor.hidden = true;
        });
    }
    if (panel.open) void load().catch(() => {});
    return {state, load};
}

function setupBlockLoads(editor, form, library, request) {
    const root = editor.querySelector('[data-load-editor]');
    const listing = root.querySelector('[data-load-list]');
    const equipmentButtons = root.querySelector('[data-equipment-buttons]');
    const feedback = root.querySelector('[data-load-feedback]');
    const review = root.querySelector('[data-load-review]');
    let entries = JSON.parse(root.querySelector('[data-load-initial]').textContent);
    if (!Array.isArray(entries)) entries = [];
    editor.readWorkoutLoads = () => structuredClone(entries.filter(entry => hasLoads(entry) || entry.equipment?.trim()));
    const notify = () => editor.dispatchEvent(new CustomEvent('workout:change', {bubbles: true}));
    const confirmReplacement = entry => window.confirm(`Substituir as cargas de ${entry.movement || entry.equipment || 'esta referência'}?`);
    const renderEntries = () => listing.replaceChildren(...entries.map(renderEntry));

    function implementChoice(value) {
        const select = document.createElement('select');
        select.append(new Option('Não informado', ''), ...Object.entries(implementsLabels).map(([id, label]) => new Option(label, id)));
        select.value = value ?? '';
        return select;
    }

    function renderEntry(entry) {
        const row = element('section', '', 'load-entry');
        const equipment = textInput(entry.equipment, 80, 'Ex.: Dumbbell');
        const movement = textInput(entry.movement, 120, 'Ex.: Dumbbell Snatch');
        const labels = element('div', '', 'field-grid two-columns');
        const unit = document.createElement('select');
        unit.append(new Option('Sem unidade adicional', ''), new Option('kg', 'kg'), new Option('lb', 'lb'));
        unit.value = entry.unit ?? '';
        const implement = implementChoice(entry.implement);
        const movements = document.createElement('datalist');
        movements.id = `load-movements-${++movementListId}`;
        movement.setAttribute('list', movements.id);
        labels.append(namedField('Equipamento', equipment), namedField('Movimento (opcional)', movement),
            namedField('Unidade para números isolados', unit), namedField('Implemento e unidade da carga', implement), movements);
        const source = element('p', '', 'input-hint');
        const showSource = () => { source.textContent = [SOURCES[entry.source] || SOURCES.manual, entry.reference].filter(Boolean).join(' · '); };
        const options = document.createElement('select');
        options.setAttribute('aria-label', 'Preset disponível');
        const genericOptions = document.createElement('select');
        let presets = [], generics = [];
        const apply = button('Usar preset', () => usePreset(presets[Number(options.value)]));
        const applyGeneric = button('Usar preset genérico', () => {
            if (genericOptions.value && window.confirm('Aplicar este preset genérico como referência manual para a carga?')) {
                usePreset(generics[Number(genericOptions.value)]);
            }
        });
        const restore = button('Restaurar sugestão', () => usePreset(originalPreset()));
        const originalPreset = () => [...presets, ...generics].find(preset => entry.preset_key
            ? preset.key === entry.preset_key : (preset.reference || presetName(preset)) === entry.reference);
        const presetControls = element('div', '', 'button-row');
        const genericControls = element('div', '', 'button-row');
        genericControls.append(namedField('Fallback manual', genericOptions), applyGeneric);
        const toggleActions = () => {
            apply.disabled = !options.value;
            applyGeneric.disabled = !genericOptions.value;
            restore.disabled = !originalPreset();
        };
        function filterPresets() {
            const equipmentMatches = item => key(item.equipment) === key(entry.equipment);
            const names = library.state.movements.filter(item => equipmentMatches(item) && item.active
                && (!form.dataset.workoutModality || item.modalities.includes(form.dataset.workoutModality)));
            movements.replaceChildren(...[...new Set(names.map(item => item.name))].map(name => new Option(name)));
            const available = library.state.presets.filter(preset => equipmentMatches(preset) && preset.active
                && preset.movement_active !== false && presetWithLoads(preset));
            presets = available.filter(preset => preset.movement && key(preset.movement) === key(entry.movement));
            generics = available.filter(preset => !preset.movement);
            const choice = preset => [presetName(preset), library.state.contexts[preset.context], summarize(presetValues(preset))].filter(Boolean).join(' · ');
            options.replaceChildren(new Option(presets.length ? 'Escolher um preset' : 'Nenhum preset específico', ''),
                ...presets.map((preset, index) => new Option(choice(preset), String(index))));
            genericOptions.replaceChildren(new Option('Escolher um preset genérico', ''),
                ...generics.map((preset, index) => new Option(choice(preset), String(index))));
            options.disabled = !presets.length;
            genericControls.hidden = !generics.length;
            toggleActions();
        }
        options.addEventListener('change', toggleActions);
        genericOptions.addEventListener('change', toggleActions);
        options.addEventListener('library:update', filterPresets);
        function usePreset(preset) {
            if (!preset || (hasLoads(entry) && !confirmReplacement(entry))) return;
            Object.assign(entry, presetValues(preset), {
                equipment: preset.equipment, movement: preset.movement ?? entry.movement,
                unit: preset.unit, source: 'preset', reference: preset.reference || presetName(preset),
                preset_key: preset.key,
            });
            renderEntries();
            notify();
        }
        const update = (field, value) => {
            entry[field] = value;
            entry.source = 'manual';
            showSource();
            notify();
        };
        unit.addEventListener('change', () => update('unit', unit.value));
        implement.addEventListener('change', () => update('implement', implement.value || null));
        for (const [field, input] of Object.entries({equipment, movement})) {
            input.addEventListener('input', () => update(field, input.value));
            input.addEventListener('change', () => {
                filterPresets();
                library.load().then(filterPresets).catch(() => { feedback.textContent = 'Falha ao carregar os presets. Você pode preencher os valores manualmente.'; });
            });
        }
        presetControls.append(namedField('Presets disponíveis', options), apply, restore, button('Remover referência', () => {
            if (hasLoads(entry) && !window.confirm('Remover esta referência de carga?')) return;
            entries = entries.filter(item => item !== entry);
            renderEntries();
            notify();
        }));
        row.append(labels, presetControls, genericControls, fieldsForLevels(entry, update), source);
        filterPresets();
        showSource();
        return row;
    }

    function addEquipment(name) {
        if (entries.length >= MAX_ENTRIES) {
            feedback.textContent = `Cada bloco pode ter até ${MAX_ENTRIES} referências.`;
            return;
        }
        entries.push({equipment: name === 'Outros' ? '' : name, source: 'manual'});
        renderEntries();
        listing.lastElementChild.querySelector('input').focus();
        notify();
        library.load().catch(() => { feedback.textContent = 'Falha ao carregar os presets. O equipamento pode ser preenchido manualmente.'; });
    }

    function renderReview(items) {
        review.hidden = false;
        const title = element('h4', 'Movimentos e equipamentos identificados');
        if (!items.length) {
            review.replaceChildren(title, element('p', 'Nenhum termo reconhecido. As referências podem ser preenchidas manualmente.', 'input-hint'));
            return;
        }
        const changes = [];
        const rows = items.map(item => {
            const row = element('section', '', 'load-review-item');
            const check = document.createElement('input');
            check.type = 'checkbox';
            const heading = namedField(item.movement ?? item.equipment, check);
            heading.className = 'checkbox-label';
            const fieldNames = {title: 'Título', description: 'Descrição', notes: 'Observações', station: 'Estação'};
            const snippet = element('p', `${fieldNames[item.field]} ${item.line}: ${item.text}`, 'input-hint');
            const existing = entries.find(entry => entry.source === 'manual' && hasLoads(entry)
                && key(entry.movement) === key(item.movement) && key(entry.equipment) === key(item.equipment));
            const origin = element('p', [
                item.equipment, item.variation, library.state.contexts[item.context] ?? ({technique:'Técnica', strength:'Força', conditioning:'Condicionamento'})[item.context],
                item.explicit ? `Carga prescrita: ${item.explicit.female == null ? `${item.explicit.male} ${item.explicit.unit}` : `${item.explicit.male} / ${item.explicit.female} ${item.explicit.unit}`}` : null,
                item.relative ? `Carga relativa: ${item.relative}${item.explicit ? '' : ' · sem conversão em kg'}` : null,
                !item.presets.length ? 'Nenhum preset específico compatível.' : null,
            ].filter(Boolean).join(' · '), 'input-hint');
            const select = document.createElement('select');
            select.append(new Option('Preencher manualmente', 'manual'));
            if (existing) select.append(new Option('Manter valores manuais do bloco', 'current'));
            if (item.explicit) select.append(new Option('Usar a carga do texto no campo RX', 'explicit'));
            item.presets.forEach((preset, index) => select.append(new Option(`${presetName(preset)} · ${summarize(presetValues(preset))}`, String(index))));
            const values = element('div');
            const empty = () => ({equipment: item.equipment, movement: item.movement, source: 'manual', implement: item.implement});
            const change = {check, entry: empty(), edited: false};
            changes.push(change);
            function draw() {
                const implement = implementChoice(change.entry.implement);
                implement.addEventListener('change', () => { change.entry.implement = implement.value || null; change.edited = true; });
                values.replaceChildren(fieldsForLevels(change.entry, (level, value) => {
                    change.entry[level] = value;
                    change.entry.source = 'manual';
                    change.edited = true;
                }), namedField('Implemento e unidade da carga', implement));
            }
            function applyChoice() {
                select.dataset.previous = select.value;
                change.choice = select.value;
                const preset = item.presets[Number(select.value)];
                change.entry = empty();
                change.edited = false;
                if (select.value === 'current') {
                    change.entry = {...existing};
                } else if (select.value === 'explicit') {
                    const {male, female, unit} = item.explicit;
                    Object.assign(change.entry, {rx: female == null ? `${male} ${unit}` : `${male} / ${female} ${unit}`, source: 'explicit', unit, reference: `${fieldNames[item.field]} ${item.line} · carga informada`});
                } else if (preset) {
                    Object.assign(change.entry, presetValues(preset), {source: 'preset', unit: preset.unit, reference: preset.reference || presetName(preset),
                        movement: item.movement, preset_key: preset.key});
                }
                draw();
            }
            select.addEventListener('change', () => {
                if (change.edited && !window.confirm('Substituir os valores desta sugestão em revisão?')) {
                    select.value = select.dataset.previous ?? 'manual';
                    return;
                }
                applyChoice();
            });
            select.value = item.explicit ? 'explicit' : existing ? 'current' : (!item.review && !item.relative && item.presets.length ? '0' : 'manual');
            applyChoice();
            row.append(heading, snippet, origin);
            if (item.review) row.append(element('p', 'Revise a associação do movimento, do equipamento ou dos pesos antes de aplicar.', 'input-hint'));
            row.append(namedField('Origem dos valores a aplicar', select), values);
            return row;
        });
        const apply = button('Aplicar sugestões selecionadas', () => {
            const checked = changes.filter(item => item.check.checked && hasLoads(item.entry));
            const selected = checked.filter(item => item.choice !== 'current' || item.edited).map(item => item.entry);
            if (!selected.length) {
                feedback.textContent = checked.length ? 'Os valores manuais foram preservados.' : 'Selecione ao menos um item com valores preenchidos.';
                return;
            }
            const result = applyReviewedLoads(entries, selected, confirmReplacement);
            entries = result.entries;
            renderEntries();
            if (result.applied) notify();
            feedback.textContent = `${result.applied} referência(s) aplicada(s).${result.skipped ? ` ${result.skipped} preservada(s) ou sem espaço no bloco.` : ''}`;
            if (result.applied && !result.skipped) review.hidden = true;
        }, 'primary-button compact');
        review.replaceChildren(title, ...rows, apply, button('Fechar revisão', () => { review.hidden = true; }));
    }

    const analyze = root.querySelector('[data-load-analyze]');
    const readAnalysis = () => {
        const field = name => editor.querySelector(`[data-edit-text="${name}"]`).value;
        return {
            type: editor.closest('[data-stage]').dataset.stage, modality: form.dataset.workoutModality, title: field('title'), description: field('description'), notes: field('notes'),
            stations: [...editor.querySelectorAll('[data-station]')].map(station => Object.fromEntries(['name', 'description', 'load'].map(name => [name, station.querySelector(`[name$="[${name}]"]`).value || null]))),
        };
    };
    let analyzed;
    const invalidateReview = () => {
        if (!review.hidden && analyzed !== JSON.stringify(readAnalysis())) {
            review.hidden = true;
            feedback.textContent = 'O texto mudou. Analise novamente para revisar as cargas atuais.';
        }
    };
    editor.addEventListener('input', event => { if (!root.contains(event.target)) invalidateReview(); });
    editor.addEventListener('workout:change', invalidateReview);
    analyze.addEventListener('click', async () => {
        analyze.disabled = true;
        review.hidden = true;
        feedback.textContent = 'Analisando o bloco…';
        try {
            const input = readAnalysis();
            analyzed = JSON.stringify(input);
            const data = await request(form.dataset.analysisUrl, 'POST', input);
            void library.load().catch(() => {});
            if (analyzed === JSON.stringify(readAnalysis())) {
                renderReview(data.items);
                feedback.textContent = '';
            } else {
                feedback.textContent = 'O texto mudou durante a análise. Tente novamente.';
            }
        } catch (error) {
            feedback.textContent = `Falha ao analisar o bloco. ${error.message}`;
        } finally {
            analyze.disabled = false;
        }
    });
    renderEntries();
    editor.addEventListener('focusin', event => {
        if (listing.contains(event.target)) void library.load().catch(() => {});
    });
    return names => {
        equipmentButtons.replaceChildren(...names.map(name => button(name, () => addEquipment(name), 'secondary-button compact')));
        for (const select of listing.querySelectorAll('select')) {
            // Refresh suggestions when the shared library changes, keeping manual input and focus.
            select.dispatchEvent(new Event('library:update'));
        }
    };
}

export function setupWorkoutLoads(form, request) {
    const blocks = [];
    const library = setupLoadLibrary(document.querySelector('[data-load-library]'), request, equipment => blocks.forEach(update => update(equipment)), form);
    for (const editor of form.querySelectorAll('[data-block-editor]')) {
        blocks.push(setupBlockLoads(editor, form, library, request));
    }
    blocks.forEach(update => update(INITIAL_EQUIPMENT));
}
