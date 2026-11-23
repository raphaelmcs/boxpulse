const HEX_COLOR = /^#[0-9a-f]{6}$/i;

export function readableColor(color) {
    if (typeof color !== 'string' || !HEX_COLOR.test(color)) return '';
    const rgb = color.slice(1).match(/../g).map(value => Number.parseInt(value, 16));
    for (let step = 0; step <= 20; step++) {
        const channels = rgb.map(value => Math.round(value + (255 - value) * step / 20));
        const [red, green, blue] = channels.map(value => {
            const channel = value / 255;
            return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
        });
        const luminance = 0.2126 * red + 0.7152 * green + 0.0722 * blue;
        if ((luminance + 0.05) / (0.035 + 0.05) >= 4.5) {
            return '#' + channels.map(value => value.toString(16).padStart(2, '0')).join('');
        }
    }
    return '#ffffff';
}

export function applyTextStyle(element, style = {}) {
    style ??= {};
    element.style.fontWeight = style.font_weight === 'normal' ? '400' : (style.font_weight === 'bold' || style.display_role === 'subtitle') ? '700' : '';
    element.style.color = readableColor(style.text_color);
}

export function remapLineStyles(before, after, styles, prefixLimit = Infinity) {
    let first = 0;
    while (first < Math.min(before.length, after.length, prefixLimit) && before[first] === after[first]) first++;
    let previousEnd = before.length;
    let nextEnd = after.length;
    while (previousEnd > first && nextEnd > first && before[previousEnd - 1] === after[nextEnd - 1]) {
        previousEnd--;
        nextEnd--;
    }
    const result = {};
    const matched = new Set();
    const formerByText = new Map();
    for (let index = first; index < previousEnd; index++) {
        if (!formerByText.has(before[index])) formerByText.set(before[index], []);
        formerByText.get(before[index]).push(index);
    }
    const nextByText = new Map();
    for (let index = first; index < nextEnd; index++) {
        nextByText.set(after[index], (nextByText.get(after[index]) ?? 0) + 1);
    }
    for (let index = first; index < nextEnd; index++) {
        const candidates = formerByText.get(after[index]);
        if (candidates?.length === 1 && nextByText.get(after[index]) === 1) {
            const previous = candidates[0];
            matched.add(previous);
            result[index] = styles[previous];
        }
    }
    const missing = [];
    for (let index = first; index < previousEnd; index++) {
        if (!matched.has(index)) missing.push(index);
    }
    let position = 0;
    for (let index = first; index < nextEnd; index++) {
        if (!(index in result)) result[index] = styles[missing[position++]];
    }
    for (const [index, style] of Object.entries(styles)) {
        const line = Number(index);
        if (line < first) result[line] = style;
        if (line >= previousEnd) result[line + nextEnd - previousEnd] = style;
    }
    return Object.fromEntries(Object.entries(result).filter(([index, style]) => style && after[index]?.trim()));
}

function setupTextTools(editor, tools) {
    const field = tools.dataset.textTools;
    const input = editor.querySelector(`[data-edit-text="${field}"]`);
    const select = tools.querySelector('[data-text-line]');
    const picker = tools.querySelector('[data-text-picker]');
    const sample = tools.querySelector('[data-text-sample]');
    const metadata = editor.workoutTextStyle;
    const readColors = getComputedStyle(document.body);
    const colors = Object.fromEntries(['primary', 'secondary'].map(key => [key, readColors.getPropertyValue(`--box-${key}`).trim().toUpperCase()]));
    let lines = input.value.split('\n');
    let current = 0, editPrefix = Infinity;
    const style = () => (select ? metadata[field]?.[current] : metadata[field]) ?? {};
    const update = () => {
        if (select && tools.open) {
            const options = lines.map((line, index) => new Option(`${index + 1} · ${line.trim().slice(0, 44) || 'Linha em branco'}`, String(index)));
            select.replaceChildren(...options);
            select.value = String(current);
        }
        const value = style();
        sample.textContent = lines[current] || (select ? 'Linha em branco' : 'Título do bloco');
        applyTextStyle(sample, value);
        if (!select) applyTextStyle(input, value);
        picker.value = value.text_color || '#FFFFFF';
        for (const button of tools.querySelectorAll('[data-text-weight], [data-text-color], [data-text-role]')) {
            const {textWeight, textColor} = button.dataset;
            const selected = textWeight ? value.font_weight === textWeight
                : textColor ? value.text_color === (colors[textColor] ?? textColor) : value.display_role === 'subtitle';
            button.setAttribute('aria-pressed', String(selected));
        }
        if (select) {
            tools.querySelector('[data-text-move="-1"]').disabled = current === 0;
            tools.querySelector('[data-text-move="1"]').disabled = current === lines.length - 1;
        }
    };
    const notify = () => editor.dispatchEvent(new CustomEvent('workout:change', {bubbles: true}));
    const setStyle = value => {
        if (select && !lines[current]?.trim()) return;
        if (select) {
            metadata[field] ??= {};
            if (Object.keys(value).length) metadata[field][current] = value;
            else delete metadata[field][current];
        } else {
            metadata[field] = Object.keys(value).length ? value : null;
        }
        update();
        notify();
    };
    const selectLine = (index, focus = false) => {
        current = Math.max(0, Math.min(index, lines.length - 1));
        const start = lines.slice(0, current).reduce((offset, line) => offset + line.length + 1, 0);
        if (focus) input.focus();
        input.setSelectionRange(select ? start : input.selectionStart, select ? start + lines[current].length : input.selectionEnd);
        update();
    };
    const fromCaret = () => {
        if (select) current = input.value.slice(0, input.selectionStart).split('\n').length - 1;
        update();
    };
    input.addEventListener('beforeinput', () => {
        const prefix = input.value.slice(0, input.selectionStart).split('\n');
        editPrefix = prefix.length - 1 + Number(prefix.at(-1) !== '');
    });
    input.addEventListener('input', () => {
        const next = input.value.split('\n');
        if (select) metadata[field] = remapLineStyles(lines, next, metadata[field] ?? {}, lines.length === next.length ? Infinity : editPrefix);
        editPrefix = Infinity;
        lines = next;
        fromCaret();
    });
    for (const event of ['click', 'keyup', 'select']) input.addEventListener(event, fromCaret);
    tools.addEventListener('toggle', update);
    select?.addEventListener('change', () => selectLine(Number(select.value)));
    picker.addEventListener('input', () => setStyle({...style(), text_color: picker.value.toUpperCase()}));
    tools.addEventListener('click', event => {
        const button = event.target.closest('button');
        if (!button) return;
        const {textWeight, textColor, textMove} = button.dataset;
        if (textWeight) return setStyle({...style(), font_weight: textWeight});
        if (textColor) return setStyle({...style(), text_color: colors[textColor] ?? textColor});
        if (button.hasAttribute('data-text-reset')) return setStyle({});
        if (button.hasAttribute('data-text-role')) {
            const {display_role, ...other} = style();
            return setStyle(display_role ? other : {...other, display_role: 'subtitle'});
        }
        const rows = lines.map((text, index) => ({text, style: metadata[field]?.[index]}));
        let next = current;
        if (textMove) {
            next = current + Number(textMove);
            const [row] = rows.splice(current, 1);
            rows.splice(next, 0, row);
        } else if (button.hasAttribute('data-text-add')) {
            if (input.value.length >= input.maxLength) return;
            next = current + 1;
            rows.splice(next, 0, {text: ''});
        } else if (button.hasAttribute('data-text-remove')) {
            if (lines[current].trim() && !window.confirm('Remover esta linha?')) return;
            rows.splice(current, 1);
        } else {
            return;
        }
        lines = rows.length ? rows.map(row => row.text) : [''];
        metadata[field] = Object.fromEntries(rows.map((row, index) => [index, row.style]).filter(([, value]) => value));
        input.value = lines.join('\n');
        selectLine(next, true);
        notify();
    });
    update();
}

function setupText(container, tools, multiline) {
    container.workoutTextStyle = {...JSON.parse(container.querySelector('[data-text-initial]').textContent)};
    for (const field of multiline) container.workoutTextStyle[field] = {...container.workoutTextStyle[field]};
    for (const toolbar of tools) setupTextTools(container, toolbar);
}

export function setupStationText(station) {
    setupText(station, station.querySelectorAll('[data-text-tools]'), ['description']);
}

export function setupWorkoutText(form) {
    for (const editor of form.querySelectorAll('[data-block-editor]')) {
        setupText(editor, [...editor.querySelectorAll('[data-text-tools]')].filter(tools => !tools.closest('[data-station]')), ['description', 'notes']);
        for (const station of editor.querySelectorAll('[data-station]')) setupStationText(station);
    }
}
