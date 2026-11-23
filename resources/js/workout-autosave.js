export class WorkoutAutosave {
    constructor({version, url, read, send, render, makeId, delay = 1000, schedule = (callback, ms) => setTimeout(callback, ms), cancel = timer => clearTimeout(timer)}) {
        Object.assign(this, {version, url, read, send, render, makeId, delay, schedule, cancel});
        this.revision = 0;
        this.savedRevision = 0;
    }

    get dirty() {
        return this.revision !== this.savedRevision || Boolean(this.active || this.retryTask || this.queuedPublish || this.queuedDraft);
    }

    changed(immediate = false) {
        this.revision++;
        if (this.failure?.status === 422) this.failure = null;
        if (this.failure) return;
        this.cancel(this.timer);
        if (!this.active) this.render('pending');
        if (immediate) {
            this.save();
        } else {
            this.timer = this.schedule(() => this.save(), this.delay);
        }
    }

    save(mode = 'draft', force = false) {
        this.cancel(this.timer);
        if (this.failure?.status === 409) return;
        if (mode === 'publish') {
            this.queuedPublish = {mode, force: true, revision: this.revision, data: this.read()};
        } else {
            this.queuedDraft = {mode, force: force || this.queuedDraft?.force};
        }
        this.failure = null;
        return this.drain();
    }

    retry() {
        if (this.failure?.status === 409) return;
        this.failure = null;
        this.queuedDraft ??= {mode: 'draft', force: !this.retryTask};
        return this.drain();
    }

    async drain() {
        if (this.active || this.failure) return;
        let task = this.retryTask;
        if (!task) {
            const request = this.queuedPublish ?? this.queuedDraft;
            if (this.queuedPublish) this.queuedPublish = null;
            else this.queuedDraft = null;
            if (!request || (!request.force && this.revision === this.savedRevision)) return;
            task = {
                mode: request.mode,
                url: this.url,
                version: this.version,
                id: this.makeId(),
                revision: request.revision ?? this.revision,
                data: request.data ?? this.read(),
            };
        }
        this.active = task;
        this.render('saving');
        try {
            const response = await this.send(task);
            if (!Number.isInteger(response.version) || response.version !== task.version + 1 || !response.update_url) {
                throw new Error('O servidor não confirmou a revisão enviada.');
            }
            this.retryTask = null;
            this.version = response.version;
            this.url = response.update_url;
            this.savedRevision = task.revision;
            if (task.mode === 'publish' && this.queuedPublish?.revision === task.revision) this.queuedPublish = null;
            this.render(this.revision === this.savedRevision ? 'saved' : 'pending', {response, mode: task.mode});
        } catch (error) {
            this.failure = error;
            if ([409, 422].includes(error.status)) {
                this.retryTask = null;
                this.queuedDraft = null;
                this.queuedPublish = null;
            } else {
                this.retryTask = task;
            }
            this.render(error.status === 409 ? 'conflict' : 'failed', {error});
        } finally {
            this.active = null;
        }
        if (!this.failure) return this.drain();
    }
}
