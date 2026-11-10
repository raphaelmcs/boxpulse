/** Measured, paginated text: no raw HTML and no clipped long workouts. */
export class ContentPager {
    constructor(root) {
        this.root = root;
        this.container = root.querySelector('[data-content-pages]');
        this.label = root.querySelector('[data-page-label]');
        this.previous = root.querySelector('[data-page-previous]');
        this.next = root.querySelector('[data-page-next]');
        this.rotate = root.querySelector('[data-page-rotate]');
        this.pages = [];
        this.items = [];
        this.index = 0;
        this.changedAt = performance.now();
        this.previous.addEventListener('click', () => this.show(this.index - 1));
        this.next.addEventListener('click', () => this.show(this.index + 1));
        let scheduled = false;
        this.observer = new ResizeObserver(() => {
            if (scheduled || !this.items.length) return;
            scheduled = true;
            requestAnimationFrame(() => { scheduled = false; this.build(); });
        });
        this.observer.observe(this.container);
    }
    setBlock(timer) {
        this.items = [];
        this.index = 0;
        this.station = null;
        const add = (text, kind = 'exercise', station = null) => {
            for (const line of String(text ?? '').split('\n')) {
                let chars = Array.from(line);
                if (!chars.length) this.items.push({text:'\u00a0',kind,station});
                while (chars.length) {
                    let count = Math.min(180, chars.length);
                    if (count < chars.length) {
                        const boundary = chars.slice(0,count).lastIndexOf(' ');
                        if (boundary > 90) count = boundary + 1;
                    }
                    this.items.push({text:chars.splice(0,count).join(''),kind,station});
                }
            }
        };
        if (timer.description) add(timer.description);
        timer.stations.forEach((station, index) => {
            add(`${String(index + 1).padStart(2,'0')} / ${station.name || 'Estação'}`, 'station', index);
            const metrics = [];
            if (station.distance != null) metrics.push(`${station.distance} m`);
            if (station.repetitions != null) metrics.push(`${station.repetitions} reps`);
            if (station.load != null) metrics.push(`${station.load} kg`);
            for (const [key,label] of Object.entries({duration_seconds:'Trabalho',rest_seconds:'Descanso',transition_seconds:'Transição'})) if (station[key] != null) metrics.push(`${label}: ${station[key]} s`);
            if (metrics.length) add(metrics.join(' · '),'metrics',index);
            if (station.description) add(station.description,'exercise',index);
        });
        if (timer.notes) add(`Observações: ${timer.notes}`,'notes');
        if (!this.items.length) add('Bloco livre. Siga as orientações do coach.');
        this.build();
    }
    build() {
        const oldIndex = this.index;
        this.container.replaceChildren();
        this.pages = [];
        const createPage = () => {
            const previous = this.pages.at(-1);
            if (previous) previous.hidden = true;
            const page = document.createElement('div');
            page.className = 'content-page';
            this.container.append(page);
            this.pages.push(page);
            return page;
        };
        let page = createPage();
        const items = this.items.map((item) => ({...item}));
        for (let i=0;i<items.length;i++) {
            const item = items[i];
            const paragraph = document.createElement('p');
            paragraph.className = `content-line content-${item.kind}`;
            paragraph.textContent = item.text;
            if (item.station !== null) paragraph.dataset.station = String(item.station);
            page.append(paragraph);
            if (page.scrollHeight > page.clientHeight + 1 && page.clientHeight > 0) {
                paragraph.remove();
                if (page.children.length) {
                    page = createPage();
                    i--;
                } else if (Array.from(item.text).length > 1) {
                    const chars = Array.from(item.text);
                    const half = Math.ceil(chars.length/2);
                    items.splice(i,1,{...item,text:chars.slice(0,half).join('')},{...item,text:chars.slice(half).join('')});
                    i--;
                } else {
                    page.append(paragraph);
                }
            }
        }
        this.show(Math.min(oldIndex,this.pages.length-1));
    }
    show(index) {
        this.index = Math.max(0,Math.min(index,this.pages.length-1));
        this.pages.forEach((page,i) => { page.hidden = i !== this.index; });
        this.previous.disabled = this.index <= 0;
        this.next.disabled = this.index >= this.pages.length-1;
        this.label.textContent = `${this.index+1} / ${this.pages.length}`;
        this.changedAt = performance.now();
    }
    focusStation(index) {
        if (index === null || index === this.station) return;
        this.station = index;
        const page = this.pages.findIndex((node) => node.querySelector(`[data-station="${index}"]`));
        if (page >= 0) this.show(page);
    }
    tick(now) {
        if (this.rotate.checked && this.pages.length > 1 && now-this.changedAt >= 10000) this.show((this.index+1)%this.pages.length);
    }
}
