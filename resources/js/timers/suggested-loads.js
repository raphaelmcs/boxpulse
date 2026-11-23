const levels = ['rx', 'intermediate', 'scale'];
export const implementsLabels = {single: 'Individual', double: 'Duplo · por unidade', total: 'Carga total'};
const present = value => value != null && String(value).trim() !== '';
const number = '[0-9]+(?:[.,][0-9]+)?';
const singleNumber = new RegExp(`^${number}$`);
const barePair = new RegExp(`^${number}\\s*/\\s*${number}$`);
const writtenPair = new RegExp(`^(${number}\\s*/\\s*${number})\\s*(kg|lbs?)$`, 'i');

export function loadCardValue(value, unit) {
    const pair = writtenPair.exec(value);
    return {
        text: pair?.[1] ?? (unit && singleNumber.test(value) ? `${value} ${unit}` : String(value)),
        unit: pair?.[2] ?? (unit && barePair.test(value) ? unit : ''),
    };
}

export function presentationLoads(timer) {
    return [timer.suggested_loads, ...(timer.load_entries ?? [])].filter(entry =>
        entry && levels.some(level => present(entry[level])));
}

export function renderLiveLoads(section, timer) {
    const template = section.querySelector('[data-load-row-template]');
    const rows = presentationLoads(timer).map(entry => {
        const row = template.content.firstElementChild.cloneNode(true);
        const name = [...new Set([entry.equipment, entry.movement].filter(Boolean))].join(' · ');
        row.querySelector('[data-load-name]').textContent = name || 'Carga geral';
        const implement = row.querySelector('[data-load-implement]');
        implement.textContent = implementsLabels[entry.implement] ?? '';
        implement.hidden = !implement.textContent;

        for (const card of row.querySelectorAll('[data-load-level]')) {
            const value = entry[card.dataset.loadLevel];
            if (!present(value)) continue;
            const display = loadCardValue(value, entry.unit);
            card.hidden = false;
            card.querySelector('[data-load-value]').textContent = display.text;
            const pairUnit = card.querySelector('[data-load-unit]');
            pairUnit.textContent = display.unit;
            pairUnit.hidden = !display.unit;
        }
        return row;
    });
    section.querySelector('[data-live-load-entries]').replaceChildren(...rows);
    section.querySelector('[data-load-heading]').textContent = rows.length ? 'CARGAS SUGERIDAS' : '';
    section.hidden = rows.length === 0;
}
