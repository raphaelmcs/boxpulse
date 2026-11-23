function wholeNumber(value, minimum, maximum) {
    if (value == null || value === '') return null;
    const number = Number(value);
    return Number.isInteger(number) && number >= minimum && number <= maximum ? number : null;
}

function durationSeconds(block) {
    if ('duration_seconds' in block) return wholeNumber(block.duration_seconds, 1, 86400);
    const minutes = wholeNumber(block.duration_minutes || 0, 0, 1440);
    const extra = wholeNumber(block.duration_extra_seconds || 0, 0, 59);
    return minutes === null || extra === null ? null : wholeNumber(minutes * 60 + extra, 1, 86400);
}

function decimalMetric(value) {
    return value != null && value !== '' && Number.isFinite(Number(value)) ? Number(value).toFixed(2) : null;
}

function normalizedLoads(loads) {
    return loads && Object.fromEntries(Object.entries(loads).map(([key, value]) => [key, typeof value === 'string' ? value.trim() : value]));
}

/** Builds a stopped example from unsaved inputs. Prepared sessions always use the server snapshot. */
export function previewTimer(block, defaults, preparation) {
    const timer = {...block, block_type: block.type, state: 'idle', stations: block.stations ?? []};
    timer.title = timer.title?.trim();
    timer.suggested_loads = normalizedLoads(timer.suggested_loads);
    timer.load_entries = timer.load_entries?.map(normalizedLoads);
    if (block.config) return timer;

    timer.stations = timer.stations.map(station => ({
        ...station,
        name: station.name?.trim(),
        distance: decimalMetric(station.distance),
        load: decimalMetric(station.load),
        repetitions: wholeNumber(station.repetitions, 1, 1000000),
        duration_seconds: wholeNumber(station.duration_seconds, 1, 86400),
        rest_seconds: wholeNumber(station.rest_seconds, 0, 86400),
        transition_seconds: wholeNumber(station.transition_seconds, 0, 3600),
    }));

    const type = block.timer_type;
    const settings = block.timer_settings ?? {};
    const free = ['none', 'repetitions'].includes(type);
    const down = ['countdown', 'amrap', 'emom', 'tabata', 'interval', 'circuit'].includes(type);
    const duration = durationSeconds(block);
    const config = {
        version: 1, type, direction: free ? 'none' : down ? 'down' : 'up',
        preparation_ms: free ? 0 : (wholeNumber(preparation, 0, 60) ?? wholeNumber(settings.preparation_seconds, 0, 60) ?? defaults.preparation_seconds) * 1000,
        duration_ms: duration === null ? null : duration * 1000,
        limit_ms: null,
        interval_ms: (wholeNumber(settings.interval_seconds, 1, 86400) ?? defaults.interval_seconds) * 1000,
        rounds: wholeNumber(settings.rounds, 1, 1000) ?? 1,
        cycle_ms: 0,
        segments: [],
    };
    config.limit_ms = free || (type === 'for_time' && ['0', 0, false].includes(settings.stop_at_cap)) ? null : config.duration_ms;

    if (['tabata', 'interval', 'circuit'].includes(type)) {
        const stations = type === 'circuit' ? timer.stations : [{}];
        let tail = 0;
        for (const [index, station] of stations.entries()) {
            const work = wholeNumber(station.duration_seconds, 1, 86400) ?? wholeNumber(settings.work_seconds, 1, 86400) ?? 0;
            const rest = wholeNumber(station.rest_seconds, 0, 86400) ?? wholeNumber(settings.rest_seconds, 0, 86400) ?? 0;
            const transition = type === 'tabata' ? 0
                : wholeNumber(station.transition_seconds, 0, 3600) ?? wholeNumber(settings.transition_seconds, 0, 3600) ?? 0;
            for (const [phase, seconds] of Object.entries({work, rest, transition})) {
                if (seconds > 0) config.segments.push({phase, duration_ms: seconds * 1000, station: type === 'circuit' ? index : null});
            }
            config.cycle_ms += (work + rest + transition) * 1000;
            tail = (rest + transition) * 1000;
        }
        config.duration_ms = config.limit_ms = config.cycle_ms * config.rounds - tail || null;
    }
    return {...timer, config, incomplete: down && config.duration_ms === null};
}
