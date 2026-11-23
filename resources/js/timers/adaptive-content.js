import {applyTextStyle} from '../workout-text.js';

/** Preserves instructions and source order; a group is never split across columns. */
export function contentGroups(timer) {
    const groups=[];
    const add = (text, kind, styles) => {
        const chunks = String(text ?? '').replace(/\r\n?/g, '\n').split(/(\n\s*\n)/);
        const multiple = chunks.filter((chunk, index) => index % 2 === 0 && chunk.trim()).length > 1;
        const append = (lines, first) => {
            const group = {text: lines.join('\n'), kind, station: null};
            const lineStyles = lines.map((_, index) => styles?.[first + index] ?? null);
            if (lineStyles.some(Boolean)) group.lineStyles = lineStyles;
            groups.push(group);
        };
        let offset = 0;
        for (const [chunkIndex, chunk] of chunks.entries()) {
            const lines = chunk.split('\n');
            if (chunkIndex % 2 === 0 && chunk.trim()) {
                if (multiple) append(lines, offset);
                else {
                    for (let index = 0; index < lines.length; index++) {
                        if (!lines[index].trim()) continue;
                        const first = index;
                        if ((styles?.[offset + index]?.display_role === 'subtitle' || /:\s*$/.test(lines[index]) || /^\d+\s+(?:rounds|rondas|séries)/i.test(lines[index])) && index + 1 < lines.length) index++;
                        while (index + 1 < lines.length && /^\s+\S/.test(lines[index + 1])) index++;
                        append(lines.slice(first, index + 1), offset + first);
                    }
                }
            }
            offset += lines.length - 1;
        }
    };
    if (timer.description) add(timer.description, 'exercise', timer.text_style?.description);
    for(const [index,station]of (timer.stations || []).entries()) {
        const metrics=[];
        for(const [key,label]of Object.entries({distance:'m',repetitions:'reps',load:'kg',duration_seconds:'s de trabalho',rest_seconds:'s de descanso',transition_seconds:'s de transição'}))if(station[key]!=null)metrics.push(`${station[key]} ${label}`);
        const header = [`${index + 1} / ${station.name || 'Estação'}`, metrics.join(' · ')].filter(Boolean);
        const instructions = String(station.description ?? '').replace(/\r\n?/g, '\n').split('\n');
        const group = {text: [...header, station.description].filter(Boolean).join('\n'), kind: 'station', station: index};
        if (station.text_style) {
            group.lineStyles = [station.text_style.name ?? null, ...header.slice(1).map(() => null),
                ...instructions.map((_, line) => station.text_style.description?.[line] ?? null)];
        }
        groups.push(group);
    }
    if(timer.notes)add(`Observações: ${timer.notes}`,'notes',timer.text_style?.notes);
    if(!groups.length)groups.push({text:'Siga as orientações do coach.',kind:'exercise',station:null});
    return groups;
}

export function balancedSplit(heights,gap=0) {
    if(heights.length<2)return heights.length;
    let best=1,score=Infinity,leftSum=0;
    const total=heights.reduce((sum,height)=>sum+height,0);
    for(let split=1;split<heights.length;split++) {
        leftSum+=heights[split-1];
        const left=leftSum+gap*(split-1);
        const right=total-leftSum+gap*(heights.length-split-1);
        const candidate=Math.max(left,right);
        if(candidate<score){score=candidate;best=split;}
    }
    return best;
}

export function largestFittingFont(minimum, maximum, fits) {
    let best = null;
    while (minimum <= maximum) {
        const size = Math.floor((minimum + maximum) / 2);
        if (fits(size)) {
            best = size;
            minimum = size + 1;
        } else {
            maximum = size - 1;
        }
    }
    return best;
}

function instruction(group) {
    const paragraph = document.createElement('p');
    paragraph.className = `adaptive-instruction content-${group.kind}`;
    if (group.lineStyles) {
        group.text.split('\n').forEach((line, index) => {
            if (index) paragraph.append('\n');
            const span = document.createElement('span');
            const style = group.lineStyles[index];
            span.textContent = line;
            if (style?.display_role === 'subtitle') span.className = 'adaptive-subtitle';
            applyTextStyle(span, style);
            paragraph.append(span);
        });
    } else {
        paragraph.textContent = group.text;
    }
    if (group.station !== null) paragraph.dataset.station = String(group.station);
    return paragraph;
}

export class AdaptiveContent {
    constructor(root, {observe = true, minimum = 22, maximum = root.classList.contains('tv-shell') ? 76 : 44} = {}) {
        this.root = root;
        this.container = root.querySelector('[data-content-pages]');
        this.panel = this.container.closest('.session-content');
        this.notice = root.querySelector('[data-content-capacity-warning]');
        this.loads = root.querySelector('[data-suggested-loads]');
        this.timing = root.querySelector('[data-session-timing]');
        this.score = this.timing.querySelector('.scoreboard');
        this.loadNotice = this.timing.querySelector('[data-load-capacity-warning]');
        this.full = root.querySelector('[data-excess-full]');
        this.tv = root.classList.contains('tv-shell');
        this.minimum = minimum;
        this.maximum = maximum;
        this.columns = Array.from({length: 2}, () => {
            const column = document.createElement('div');
            column.className = 'adaptive-column';
            return column;
        });

        if (observe) {
            this.observer = new ResizeObserver(() => this.schedule());
            for (const element of [this.panel, this.container, this.timing, this.score]) this.observer.observe(element);
            if (this.tv) this.observer.observe(root);
            this.resize = () => this.schedule();
            window.addEventListener('resize', this.resize);
            document.fonts?.ready.then(() => this.schedule(true));
        }
        this.observe = observe;
    }

    setBlock(timer) {
        const signature = JSON.stringify([timer.block_type, timer.title, timer.description, timer.stations, timer.notes, timer.text_style, timer.suggested_loads, timer.load_entries]);
        if (signature === this.signature) return;
        this.signature = signature;
        this.groups = contentGroups(timer);
        this.nodes = this.groups.map(instruction);
        for (const node of this.nodes) {
            node.classList.toggle('is-current-station', Number(node.dataset.station) === this.focusedStation);
        }
        if (this.observe) this.schedule();
    }

    schedule(force = false) {
        if (force) this.lastMeasurement = null;
        if (this.destroyed || this.frame || !this.nodes) return;
        this.frame = requestAnimationFrame(() => {
            this.frame = null;
            this.fit();
        });
    }

    dimensions() {
        return [this.panel.clientWidth, this.panel.clientHeight, this.container.clientWidth, this.container.clientHeight,
            this.timing.clientWidth, this.timing.clientHeight, this.score.offsetHeight,
            this.tv ? this.root.clientWidth : window.innerWidth, this.tv ? this.root.clientHeight : window.innerHeight];
    }

    fitLoads(scale) {
        this.timing.dataset.loadExcess = 'false';
        this.timing.dataset.loadDensity = 'normal';
        this.loadNotice.hidden = true;
        if (!this.tv || this.loads.hidden) return true;

        const timingStyle = getComputedStyle(this.timing);
        const height = this.timing.clientHeight - parseFloat(timingStyle.paddingTop) - parseFloat(timingStyle.paddingBottom);
        const gap = parseFloat(getComputedStyle(this.loads).marginTop);
        const modes = ['normal', 'compact'];
        if (this.loads.clientWidth >= 520 * scale && this.loads.querySelectorAll('[data-load-row]').length > 1) modes.push('columns');

        for (const mode of modes) {
            this.timing.dataset.loadDensity = mode;
            if (this.score.offsetHeight + gap + this.loads.offsetHeight <= height + 1
                && this.loads.scrollWidth <= this.loads.clientWidth + 1) return true;
        }

        this.timing.dataset.loadExcess = 'true';
        this.timing.dataset.loadDensity = 'normal';
        this.loadNotice.hidden = false;
        return false;
    }

    draw(columns, size, scale, renderedScale) {
        const font = size * scale;
        const gap = Math.max(4, Math.min(size * .21, 19)) * scale;
        this.panel.style.setProperty('--adaptive-font', `${font}px`);
        this.panel.style.setProperty('--adaptive-gap', `${gap}px`);
        this.panel.style.setProperty('--adaptive-leading', String(1.12 + (size - this.minimum) * .0018));
        this.container.dataset.columns = String(columns);
        const [first, second] = this.columns;
        first.replaceChildren(...this.nodes);
        this.container.replaceChildren(...this.columns.slice(0, columns));

        if (columns === 2) {
            const heights = this.nodes.map(node => node.getBoundingClientRect().height / renderedScale);
            second.append(...this.nodes.slice(balancedSplit(heights, gap)));
        }
        const area = this.container.getBoundingClientRect();
        return this.columns.slice(0, columns).every(column => {
            const bounds = column.getBoundingClientRect();
            return bounds.bottom <= area.bottom + .25 * renderedScale && column.scrollWidth <= column.clientWidth + 1;
        });
    }

    fit() {
        if (this.destroyed || !this.nodes || !this.panel.clientWidth) return null;
        const key = JSON.stringify([this.signature, ...this.dimensions()]);
        if (key === this.lastMeasurement) return this.result;

        const width = this.tv ? this.root.clientWidth : window.innerWidth;
        const height = this.tv ? this.root.clientHeight : window.innerHeight;
        const scale = Math.max(.65, Math.min(width / 1920, height / 1080));
        const loadsFit = this.fitLoads(scale);
        // The editor scales the finished TV screen for display. DOM measurements must use reference pixels.
        const renderedScale = this.tv ? this.root.getBoundingClientRect().width / width || 1 : 1;
        const canSplit = this.nodes.length > 1 && this.container.clientWidth >= 540 * scale;
        let best = null;

        for (const columns of canSplit ? [1, 2] : [1]) {
            const minimum = best ? best.size + 1 : this.minimum;
            const size = largestFittingFont(minimum, this.maximum, value => this.draw(columns, value, scale, renderedScale));
            if (size !== null) best = {columns, size};
        }

        if (best) this.draw(best.columns, best.size, scale, renderedScale);
        else this.container.replaceChildren();
        this.notice.hidden = Boolean(best);
        this.root.dataset.contentExcess = String(!best);
        this.root.dataset.loadExcess = String(!loadsFit);
        this.root.dataset.contentColumns = String(best?.columns ?? 0);
        this.lastMeasurement = JSON.stringify([this.signature, ...this.dimensions()]);
        this.result = {fits: Boolean(best) && loadsFit, textFits: Boolean(best), loadsFit, font: (best?.size ?? this.minimum) * scale, columns: best?.columns ?? 0};

        if (this.full && !this.tv) {
            this.full.hidden = Boolean(best);
            if (!best) {
                const title = this.root.querySelector('[data-block-title]').textContent;
                this.full.querySelector('[data-excess-text]').textContent = [title, ...this.groups.map(group => group.text)].join('\n\n');
            }
        }
        return this.result;
    }

    focusStation(index) {
        if (this.focusedStation === index) return;
        this.focusedStation = index;
        for (const node of this.nodes ?? []) {
            node.classList.toggle('is-current-station', Number(node.dataset.station) === index);
        }
    }

    destroy() {
        this.destroyed = true;
        this.observer?.disconnect();
        if (this.resize) window.removeEventListener('resize', this.resize);
        cancelAnimationFrame(this.frame);
    }
}
