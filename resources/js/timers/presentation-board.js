import {formatTime} from './engine.js';
import {AdaptiveContent} from './adaptive-content.js';
import {applyTextStyle} from '../workout-text.js';
import {renderLiveLoads} from './suggested-loads.js';

const phases = {idle: 'Pronto para começar', preparation: 'Preparação', work: 'Em execução', rest: 'Descanso', transition: 'Transição', completed: 'Etapa finalizada', cancelled: 'Aula cancelada'};
const states = {idle: 'Aguardando o coach', preparing: 'Contagem de preparação', running: 'Em execução', paused: 'Pausado', completed: 'Etapa finalizada', cancelled: 'Cancelado'};
export const blockLabels = {warmup: 'Warm-up / Aquecimento', skill: 'Técnica / Força', workout: 'Workout principal'};

export function setText(node, value) {
    if (node && node.textContent !== String(value)) node.textContent = String(value);
}

function timerOptions(config) {
    const details = [];
    if (config.preparation_ms > 0) details.push(`Preparação ${formatTime(config.preparation_ms)}`);
    if (config.type === 'emom') details.push(`Intervalo ${formatTime(config.interval_ms)}`);
    if (['tabata', 'interval'].includes(config.type)) {
        for (const [phase, label] of Object.entries({work: 'Trabalho', rest: 'Descanso', transition: 'Transição'})) {
            const segment = config.segments.find(item => item.phase === phase);
            if (segment) details.push(`${label} ${formatTime(segment.duration_ms)}`);
        }
    }
    return details.join(' · ');
}

export class PresentationBoard {
    constructor(root, options) {
        this.root = root;
        this.content = new AdaptiveContent(root, options);
        const fields = ['timer-format', 'round', 'phase-label', 'timer-digits', 'timer-state', 'current-station', 'progress',
            'progress-bar', 'duration-label', 'total-remaining', 'timer-options', 'block-label', 'block-format', 'block-round',
            'block-title', 'session-stages'];
        this.elements = Object.fromEntries(fields.map(name => [name, root.querySelector(`[data-${name}]`)]));
        this.loads = root.querySelector('[data-suggested-loads]');
    }

    showBlock(timer, label) {
        const title = timer.title || blockLabels[timer.block_type];
        setText(this.elements['block-label'], blockLabels[timer.block_type]);
        setText(this.elements['block-title'], title);
        applyTextStyle(this.elements['block-title'], timer.text_style?.title);
        setText(this.elements['timer-format'], label);
        setText(this.elements['block-format'], label);
        setText(this.root.querySelector('[data-capacity-block-title]'), title);
        renderLiveLoads(this.loads, timer);
        const options = timerOptions(timer.config);
        setText(this.elements['timer-options'], options);
        this.elements['timer-options'].hidden = !options;
        this.content.setBlock(timer);
    }

    showStages(timers, position) {
        const signature = JSON.stringify([position, ...timers.map(item => [item.block_type, item.state])]);
        if (this.stagesSignature === signature) return;
        this.stagesSignature = signature;
        this.elements['session-stages'].replaceChildren(...timers.map((item, index) => {
            const stage = document.createElement('li');
            if (index === position) {
                stage.className = 'is-active';
                stage.setAttribute('aria-current', 'step');
            }
            const name = document.createElement('strong');
            name.textContent = `0${index + 1} / ${blockLabels[item.block_type]}`;
            const status = document.createElement('span');
            status.textContent = states[item.state];
            stage.append(name, status);
            return stage;
        }));
    }

    showClock(timer, projection, sessionStatus = 'active') {
        const elements = this.elements;
        this.root.dataset.phase = projection.phase;
        this.root.dataset.timerState = timer.state;
        setText(elements['phase-label'], timer.state === 'paused' ? 'Pausado' : projection.awaiting_confirmation ? 'Limite atingido' : phases[projection.phase]);
        const direction = projection.phase === 'preparation' ? 'down' : timer.config.direction;
        const digits = timer.incomplete ? '--:--' : direction === 'none' ? 'LIVRE' : formatTime(projection.display_ms, direction);
        setText(elements['timer-digits'], digits);
        if (elements['timer-digits'].dataset.digits !== String(digits.length)) {
            elements['timer-digits'].dataset.digits = String(digits.length);
            elements['timer-digits'].style.setProperty('--timer-characters', digits.length);
        }
        setText(elements['timer-state'], sessionStatus === 'completed' ? 'Aula concluída' : sessionStatus === 'cancelled' ? 'Aula cancelada'
            : projection.awaiting_confirmation ? 'Tempo encerrado · aguardando sincronização'
                : projection.phase === 'preparation' ? 'Contagem de preparação' : states[timer.state]);
        const rounds = timer.timer_type === 'emom' ? Math.ceil(timer.config.duration_ms / timer.config.interval_ms) : timer.config.rounds;
        const round = timer.timer_type === 'repetitions' ? `${rounds} rounds programados` : rounds > 1 ? `Round ${projection.round} / ${rounds}` : '';
        setText(elements.round, round);
        setText(elements['block-round'], round);
        const station = projection.station === null ? null : timer.stations[projection.station];
        setText(elements['current-station'], station ? `Estação ${projection.station + 1} · ${station.name}` : '');
        elements['current-station'].hidden = !station;
        this.content.focusStation(projection.station);

        const progress = elements.progress;
        progress.hidden = projection.progress === null;
        if (projection.progress !== null) {
            elements['progress-bar'].style.transform = `scaleX(${projection.progress})`;
            progress.setAttribute('aria-valuenow', String(Math.round(projection.progress * 100)));
        }
        setText(elements['duration-label'], timer.incomplete ? 'Configure a duração' : timer.config.direction === 'none' ? 'Sem cronômetro' : timer.config.duration_ms !== null
            ? `${timer.timer_type === 'for_time' ? 'Time cap' : 'Duração'} ${formatTime(timer.config.duration_ms)}` : 'Sem limite de tempo');
        setText(elements['total-remaining'], projection.total_remaining_ms !== null ? `Restante ${formatTime(projection.total_remaining_ms)}` : '');
    }
}
