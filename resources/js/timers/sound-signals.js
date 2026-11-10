/** Original oscillator signals; audio is created only after a user gesture. */
export class SoundSignals {
    constructor(button) {
        this.button = button;
        this.enabled = false;
        this.last = null;
        button?.addEventListener('click', async () => {
            if (this.enabled) {
                this.enabled = false;
                await this.context.suspend();
            } else {
                const Audio = window.AudioContext || window.webkitAudioContext;
                if (!Audio) { button.textContent='Áudio indisponível'; return; }
                this.context ??= new Audio();
                await this.context.resume();
                this.enabled = true;
                this.beep(660,.08);
            }
            button.textContent = this.enabled ? 'Desativar sons' : 'Ativar sons';
            button.setAttribute('aria-pressed',String(this.enabled));
        });
    }
    beep(frequency,duration=.14,delay=0) {
        if (!this.enabled || this.context.state !== 'running') return;
        const start = this.context.currentTime+delay;
        const oscillator = this.context.createOscillator();
        const gain = this.context.createGain();
        oscillator.frequency.value=frequency;
        oscillator.type='sine';
        gain.gain.setValueAtTime(0,start);
        gain.gain.linearRampToValueAtTime(.16,start+.01);
        gain.gain.exponentialRampToValueAtTime(.001,start+duration);
        oscillator.connect(gain).connect(this.context.destination);
        oscillator.start(start);
        oscillator.stop(start+duration);
    }
    update(timer,projection,now) {
        const current = {key:`${timer.id}:${projection.phase}:${projection.round}:${projection.station}`,seconds:Math.ceil(projection.display_ms/1000),cap:projection.cap_reached,time:now,phase:projection.phase};
        const previous=this.last;
        this.last=current;
        if (!previous || now-previous.time>1500 || !this.enabled || ['idle','paused','cancelled'].includes(timer.state)) return;
        if (current.cap && !previous.cap) { this.beep(300,.35); this.beep(300,.35,.45); }
        else if (current.key !== previous.key) {
            if (current.phase==='completed') { this.beep(880,.3); this.beep(660,.3,.35); }
            else if (current.phase==='rest') this.beep(440,.25);
            else if (current.phase==='transition') this.beep(550,.12);
            else { this.beep(1000,.2); this.beep(1000,.1,.25); }
        } else if (current.seconds !== previous.seconds && current.seconds>0 && current.seconds<=3 && timer.config.direction !== 'none' && (projection.phase==='preparation' || timer.config.direction==='down')) this.beep(880,.08);
    }
}
