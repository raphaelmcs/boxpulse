/** Pure projection. Elapsed includes preparation; no mutations, wall clock or network. */
export function projectTimer(config, accumulated, state = 'running') {
    const raw = Math.max(0, accumulated);
    const preparation = config.preparation_ms;
    const active = Math.max(0, raw - preparation);
    const limit = config.limit_ms;
    const progressLimit = limit ?? (config.type === 'for_time' ? config.duration_ms : null);
    const reachedLimit = limit !== null && active >= limit;
    const completed = state === 'completed' || reachedLimit;
    const elapsed = reachedLimit ? limit : active;
    const result = {
        state, phase: 'work', round: 1, station: null, elapsed_ms: elapsed,
        display_ms: config.direction === 'down' ? Math.max(0, (limit ?? 0) - elapsed) : elapsed,
        progress: progressLimit !== null ? Math.min(1, elapsed / progressLimit) : null,
        completed, awaiting_confirmation: reachedLimit && !['completed', 'cancelled'].includes(state),
        cap_reached: config.type === 'for_time' && config.duration_ms !== null && active >= config.duration_ms,
        total_remaining_ms: limit !== null ? Math.max(0, limit - elapsed) : null,
    };
    if (state === 'idle') return { ...result, phase: 'idle', completed: false, awaiting_confirmation: false, elapsed_ms: 0, progress: progressLimit !== null ? 0 : null, display_ms: config.direction === 'down' ? (config.type === 'emom' ? config.interval_ms : limit ?? 0) : 0 };
    if (state === 'cancelled') return { ...result, phase: 'cancelled', completed: false, awaiting_confirmation: false };
    if (raw < preparation && !completed) return { ...result, phase: 'preparation', display_ms: preparation - raw, progress: preparation ? raw / preparation : 0 };
    if (completed) {
        const position = Math.max(0, Math.min(elapsed, limit === null ? elapsed : limit - 1));
        const round = config.type === 'emom' ? Math.floor(position / config.interval_ms) + 1 : config.cycle_ms > 0 ? Math.floor(position / config.cycle_ms) + 1 : 1;
        return { ...result, phase: 'completed', round, display_ms: config.direction === 'up' ? elapsed : Math.max(0, (limit ?? 0) - elapsed) };
    }
    if (config.type === 'emom') {
        result.round = Math.floor(elapsed / config.interval_ms) + 1;
        result.display_ms = Math.min(config.interval_ms - (elapsed % config.interval_ms), result.total_remaining_ms);
    }
    if (config.cycle_ms > 0) {
        result.round = Math.floor(elapsed / config.cycle_ms) + 1;
        let offset = elapsed % config.cycle_ms;
        for (const segment of config.segments) {
            if (offset < segment.duration_ms) {
                result.phase = segment.phase;
                result.station = segment.station;
                result.display_ms = segment.duration_ms - offset;
                break;
            }
            offset -= segment.duration_ms;
        }
    }
    return result;
}

export function formatTime(milliseconds, direction = 'down') {
    const seconds = direction === 'down' ? Math.ceil(Math.max(0, milliseconds) / 1000) : Math.floor(Math.max(0, milliseconds) / 1000);
    const hours = Math.floor(seconds / 3600);
    return `${hours ? `${String(hours).padStart(2, '0')}:` : ''}${String(Math.floor(seconds / 60) % 60).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
}

/** Reconstructs server elapsed against a fresh, injectable monotonic anchor. */
export class SessionClock {
    constructor(now = () => performance.now()) { this.now = now; this.snapshot = null; this.anchor = 0; this.base = 0; }
    accept(snapshot, sentAt, receivedAt = this.now()) {
        if (this.snapshot && snapshot.version < this.snapshot.version) return false;
        const timer = snapshot.timers[snapshot.current_position];
        let elapsed = timer.elapsed_ms;
        if (['preparing', 'running'].includes(timer.state)) elapsed += Math.max(0, snapshot.server_time_ms - timer.effective_started_at_ms + Math.max(0, receivedAt - sentAt) / 2);
        if (this.snapshot?.version === snapshot.version && this.snapshot.current_position === snapshot.current_position && ['preparing', 'running'].includes(timer.state)) elapsed = Math.max(elapsed, this.elapsed(receivedAt));
        this.snapshot = snapshot;
        this.base = elapsed;
        this.anchor = receivedAt;
        this.serverAnchor = snapshot.server_time_ms + Math.max(0, receivedAt - sentAt) / 2;
        return true;
    }
    elapsed(at = this.now()) {
        const timer = this.snapshot?.timers[this.snapshot.current_position];
        return this.base + (timer && ['preparing', 'running'].includes(timer.state) ? Math.max(0, at - this.anchor) : 0);
    }
    project() {
        const timer = this.snapshot.timers[this.snapshot.current_position];
        return projectTimer(timer.config, this.elapsed(), timer.state);
    }
    serverNow() { return this.serverAnchor + Math.max(0, this.now() - this.anchor); }
}
