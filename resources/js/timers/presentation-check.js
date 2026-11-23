import {readWorkoutForm} from '../workout-editor.js';
import {projectTimer} from './engine.js';
import {blockLabels, PresentationBoard, setText} from './presentation-board.js';
import {previewTimer} from './preview-timer.js';

const referenceWidth = 1920;
const referenceHeight = 1080;

function getBlockLabel(block) {
    const stage = blockLabels[block.type];
    const title = block.title?.trim();
    return title ? `${stage} · ${title}` : stage;
}

export function hasPresentationContent(block) {
    const filled = value => value != null && String(value).trim() !== '';
    const hasLoads = loads => ['rx', 'intermediate', 'scale'].some(level => filled(loads?.[level]));
    return [block.title, block.description, block.notes].some(filled)
        || (block.stations ?? []).some(({text_style, ...station}) => Object.values(station).some(filled))
        || hasLoads(block.suggested_loads)
        || (block.load_entries ?? []).some(hasLoads);
}

function setupPresentationCheck(check) {
    const form = document.getElementById(check.dataset.previewForm ?? '');
    const region = check.querySelector('[data-preview-region]');
    const viewport = check.querySelector('[data-preview-viewport]');
    const screen = check.querySelector('[data-preview-screen]');
    const select = check.querySelector('[data-preview-block]');
    const controls = check.querySelector('[data-preview-controls]');
    const fullscreen = check.querySelector('[data-preview-fullscreen]');
    const feedback = check.querySelector('[data-preview-feedback]');
    const output = check.querySelector('[data-capacity-result]');
    const edit = check.querySelector('[data-capacity-edit]');
    const {labels, defaults} = JSON.parse(check.querySelector('[data-preview-timers]').textContent);
    const savedBlocks = JSON.parse(check.querySelector('[data-capacity-blocks]').textContent);
    const board = new PresentationBoard(screen, {observe: false});
    const title = screen.querySelector('[data-screen-title]');
    const boxModality = screen.querySelector('.tv-box span').textContent;
    let blocks = [], excess = [], scheduled, selectionSignature;

    screen.style.inlineSize = `${referenceWidth}px`;
    screen.style.blockSize = `${referenceHeight}px`;

    function scaleScreen() {
        const scale = Math.min(viewport.clientWidth / referenceWidth, viewport.clientHeight / referenceHeight);
        if (!(scale > 0)) return;
        screen.style.setProperty('--preview-scale', scale);
        screen.style.setProperty('--preview-offset-x', `${(viewport.clientWidth - referenceWidth * scale) / 2}px`);
        screen.style.setProperty('--preview-offset-y', `${(viewport.clientHeight - referenceHeight * scale) / 2}px`);
    }

    const label = type => labels[type] ?? type;

    function show(index) {
        const timer = blocks[index];
        board.showBlock(timer, label(timer.timer_type));
        board.showClock(timer, projectTimer(timer.config, 0, 'idle'));
        return board.content.fit();
    }

    function showSelected() {
        if (!blocks.length) return;
        const index = Math.max(0, select.selectedIndex);
        board.showStages(blocks, index);
        show(index);
    }

    function refresh() {
        clearTimeout(scheduled);
        if (!check.open) return;
        const selectedType = select.value;
        const values = form ? readWorkoutForm(form) : {blocks: savedBlocks};
        const inputs = (values.blocks ?? []).filter(hasPresentationContent);
        const newOptions = inputs.map(block => [block.type, getBlockLabel(block)]);
        const signature = JSON.stringify(newOptions);

        if (signature !== selectionSignature) {
            selectionSignature = signature;
            select.replaceChildren(...newOptions.map(([type, name]) => new Option(name, type)));
            select.value = newOptions.some(([type]) => type === selectedType) ? selectedType : newOptions[0]?.[0] ?? '';
        }
        controls.hidden = inputs.length < 2;
        region.hidden = !inputs.length;
        if (!inputs.length) {
            blocks = [];
            edit.hidden = true;
            check.dataset.capacity = 'empty';
            output.textContent = 'Preencha uma etapa para visualizar sua apresentação.';
            output.hidden = false;
            return;
        }

        scaleScreen();
        if (form) setText(title, values.title?.trim() || `Treino de ${boxModality}`);
        blocks = inputs.map(block => previewTimer(block, defaults, check.dataset.previewPreparation));

        // Measure the same board for every block, with the same header and footer. Leave the selected block visible.
        const selectedIndex = Math.max(0, select.selectedIndex);
        board.showStages(blocks, selectedIndex);
        excess = [];
        const measure = index => {
            const result = show(index);
            if (!result?.fits) excess.push({index, result});
        };
        blocks.forEach((_, index) => { if (index !== selectedIndex) measure(index); });
        measure(selectedIndex);
        excess.sort((left, right) => left.index - right.index);
        check.dataset.capacity = excess.length ? 'excess' : 'fits';
        output.textContent = excess.length
            ? `Conteúdo acima da capacidade da TV: ${excess.map(({index, result}) => {
                const areas = [result?.textFits ? null : 'treino', result?.loadsFit === false ? 'cargas sugeridas' : null].filter(Boolean);
                return `${getBlockLabel(inputs[index])} (${areas.join(' e ')})`;
            }).join(' · ')}. Reorganize as áreas indicadas para preservar a leitura.`
            : '';
        output.hidden = !excess.length;
        edit.hidden = !excess.length;
    }

    const timeZone = check.dataset.timezone;
    const clockFormat = new Intl.DateTimeFormat('pt-BR', {timeZone, hour: '2-digit', minute: '2-digit', second: '2-digit'});
    const dateFormat = new Intl.DateTimeFormat('pt-BR', {timeZone, day: '2-digit', month: '2-digit', year: 'numeric'});
    const observer = new ResizeObserver(scaleScreen);

    document.querySelectorAll('[data-open-tv-preview]').forEach(button => {
        if (button.getAttribute('aria-controls') !== check.id) return;
        button.addEventListener('click', () => {
            button.focus({preventScroll: true});
            check.showModal();
            const now = new Date();
            setText(screen.querySelector('[data-live-clock]'), clockFormat.format(now));
            setText(screen.querySelector('[data-live-date]'), dateFormat.format(now));
            refresh();
            observer.observe(viewport);
        });
    });
    check.querySelector('[data-close-tv-preview]').addEventListener('click', () => check.close());
    check.addEventListener('close', () => {
        if (check.open) return;
        clearTimeout(scheduled);
        observer.disconnect();
        feedback.hidden = true;
    });

    const schedule = () => {
        if (!check.open) return;
        clearTimeout(scheduled);
        scheduled = setTimeout(refresh, 140);
    };

    form?.addEventListener('input', schedule);
    form?.addEventListener('change', schedule);
    form?.addEventListener('workout:change', schedule);
    select.addEventListener('change', showSelected);
    fullscreen.addEventListener('click', async () => {
        try {
            if (document.fullscreenElement === region) await document.exitFullscreen();
            else await region.requestFullscreen();
            feedback.hidden = true;
        } catch {
            feedback.textContent = 'Tela cheia indisponível neste navegador.';
            feedback.hidden = false;
        }
    });
    document.addEventListener('fullscreenchange', () => {
        fullscreen.textContent = document.fullscreenElement === region ? 'Sair da tela cheia (Esc)' : 'Simular tela cheia';
    });
    edit.addEventListener('click', event => {
        if (!form || !excess.length) return;
        event.preventDefault();
        const {index, result} = excess.find(item => item.index === select.selectedIndex) ?? excess[0];
        const stage = [...form.querySelectorAll('[data-stage]')].find(item => item.dataset.stage === blocks[index].block_type);
        if (!stage) return;
        check.close();
        stage.querySelector('details').open = true;
        stage.querySelector(result?.loadsFit === false ? '[data-load-analyze]' : '[data-edit-text="description"]').focus();
        stage.scrollIntoView({block: 'center'});
    });

    document.fonts?.ready.then(() => {
        board.content.lastMeasurement = null;
        refresh();
    });
    window.addEventListener('pagehide', () => {
        clearTimeout(scheduled);
        observer.disconnect();
    });
    window.addEventListener('pageshow', event => {
        if (event.persisted && check.open) {
            observer.observe(viewport);
            refresh();
        }
    });
}

export function setupPresentationChecks() {
    document.querySelectorAll('[data-presentation-check]').forEach(setupPresentationCheck);
}
