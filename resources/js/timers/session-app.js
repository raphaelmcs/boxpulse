import { SessionClock } from './engine.js';
import { PollingTransport } from './polling-transport.js';
import { SoundSignals } from './sound-signals.js';
import {PresentationBoard, setText as text} from './presentation-board.js';

export class SessionApp {
    constructor(root) {
        this.root=root;
        this.control=root.dataset.view==='control';
        this.publicTv=root.dataset.view==='box-tv';
        this.client=this.control ? crypto.randomUUID() : null;
        this.transport=new PollingTransport(root);
        this.clock=new SessionClock();
        this.board=new PresentationBoard(root);
        this.sounds=new SoundSignals(root.querySelector('[data-audio-toggle]'));
        this.online=false;
        this.authorized=true;
        this.pending=null;
        this.claiming=false;
        this.polling=false;
        this.lastRenewal=-Infinity;
        this.interval=Math.max(500,Number(root.dataset.pollMs)||1000);
        this.elements={};
        for(const name of ['sync-status','session-feedback','ownership-label','claim-control','live-clock','live-date','live-box-name','live-logo']) this.elements[name]=root.querySelector(`[data-${name}]`);
        root.querySelectorAll('[data-session-command]').forEach((button)=>button.addEventListener('click',()=>this.send(button.dataset.sessionCommand)));
        this.elements['claim-control']?.addEventListener('click',()=>this.claim(true));
        this.fullscreen=root.querySelector('[data-fullscreen]');
        this.fullscreen?.addEventListener('click',async()=>{
            try {
                if(document.fullscreenElement) await document.exitFullscreen();
                else await root.requestFullscreen();
            } catch { this.feedback('Tela cheia indisponível neste navegador.'); }
        });
        document.addEventListener('fullscreenchange',()=>text(this.fullscreen,document.fullscreenElement?'Sair da tela cheia (Esc)':'Entrar em tela cheia'));
        window.addEventListener('online',()=>this.pollNow());
        window.addEventListener('offline',()=>{if (!this.publicTv || this.authorized) this.network(false);});
        if (this.publicTv) window.addEventListener('hashchange', () => window.location.reload());
        document.addEventListener('visibilitychange',()=>{if(!document.hidden)this.pollNow();});
        window.addEventListener('pageshow',(event)=>{if(event.persisted)window.location.reload();});
        window.addEventListener('pagehide',()=>{this.stopped=true;clearTimeout(this.timeout);cancelAnimationFrame(this.frame);this.board.content.destroy();this.clearLogo();});
        if (this.publicTv && !this.transport.token) this.unavailable();
        else this.poll();
        this.renderFrame();
    }
    feedback(message) { text(this.elements['session-feedback'],message); }
    network(online) {
        this.online=online;
        this.root.dataset.online=String(online);
        text(this.elements['sync-status'],online?'● Sincronizado':'● Sem conexão · tentando reconectar');
    }
    accept(result) {
        if (this.publicTv) {
            this.acceptPublicTv(result);
            return;
        }
        this.network(true);
        if([401,403,404,419].includes(result.status)) {
            this.authorized=false;
            this.feedback('Acesso indisponível ou sessão de login expirada. Entre novamente no painel.');
        }
        if(result.payload.data && this.clock.accept(result.payload.data,result.sentAt,result.receivedAt)) this.updateSnapshot();
    }
    setPublicStatus(status) {
        const messages = {
            waiting: 'Aguardando o coach iniciar a aula',
            ended: 'Aula encerrada',
            unavailable: 'Sessão indisponível',
        };
        this.root.dataset.tvStatus = status;
        this.root.querySelector('[data-box-tv-waiting]').hidden = status === 'active';
        text(this.root.querySelector('[data-box-tv-message]'), messages[status] ?? '');
        if (status !== 'active') {
            this.clock.snapshot = null;
            this.blockId = null;
            delete this.root.dataset.phase;
            delete this.root.dataset.timerState;
            delete this.root.dataset.sessionStatus;
            text(this.root.querySelector('[data-screen-title]'), '');
        }
    }
    unavailable() {
        this.authorized = false;
        this.setPublicStatus('unavailable');
        this.publicBox = null;
        this.syncLogo(null);
        document.body.style.cssText = '';
        this.timeFormat = null;
        this.root.dataset.online = 'false';
        text(this.elements['live-box-name'], 'BoxPulse');
        text(this.elements['live-clock'], '--:--:--');
        text(this.elements['live-date'], '');
        text(this.root.querySelector('[data-screen-modality]'), '');
        text(this.elements['sync-status'], 'Abra o link atualizado da TV.');
    }
    acceptPublicTv(result) {
        if (result.status === 404) {
            this.unavailable();
            return;
        }
        if ((!result.ok && result.status !== 304) || !(result.serverTime > 0)) {
            this.network(false);
            return;
        }
        this.network(true);
        const {sentAt, receivedAt, serverTime} = result;
        this.clock.syncServerTime(serverTime, sentAt, receivedAt);
        if (result.status === 304) {
            if (this.clock.snapshot) this.clock.accept({...this.clock.snapshot, server_time_ms: serverTime}, sentAt, receivedAt);
        } else {
            const {box, data, status} = result.payload;
            this.publicBox = box;
            this.setPublicStatus(status);
            this.updateBox(box);
            text(this.root.querySelector('[data-screen-modality]'), box.modality ?? '');
            text(this.root.querySelector('[data-screen-title]'), data?.title ?? '');
            if (data && this.clock.accept({...data, server_time_ms: serverTime}, sentAt, receivedAt)) this.updateSnapshot();
        }
        this.syncLogo(this.publicBox?.logo_version);
    }
    clearLogo() {
        if (!this.logoObjectUrl) return;
        URL.revokeObjectURL(this.logoObjectUrl);
        this.logoObjectUrl = null;
        this.elements['live-logo'].hidden = true;
        this.elements['live-logo'].removeAttribute('src');
    }
    async syncLogo(version) {
        if (version !== this.logoVersion) {
            this.logoVersion = version;
            this.clearLogo();
        }
        if (!version || this.logoObjectUrl || this.loadingLogo) return;
        this.loadingLogo = true;
        try {
            const {payload: image} = await this.transport.readLogo();
            if (version !== this.logoVersion || this.stopped || !image || !/^image\/(png|jpeg|webp)$/i.test(image.type)) return;
            this.logoObjectUrl = URL.createObjectURL(image);
            this.elements['live-logo'].src = this.logoObjectUrl;
            this.elements['live-logo'].hidden = false;
        } catch {
            // The next successful poll can retry the logo.
        } finally {
            this.loadingLogo = false;
        }
    }
    updateSnapshot() {
        const snapshot=this.clock.snapshot;
        const timer=snapshot.timers[snapshot.current_position];
        this.root.dataset.sessionStatus=snapshot.status;
        const ended=this.root.querySelector('[data-session-ended]');
        if(ended)ended.hidden=snapshot.status==='active';
        const activeControls = this.root.querySelector('[data-session-active]');
        if (activeControls) activeControls.hidden = snapshot.status !== 'active';
        const notice=this.root.querySelector('[data-source-notice]');
        if(notice && snapshot.source) {
            const source=snapshot.source;
            notice.hidden=!(source.replacement_url || ((source.updated || source.modality_enabled===false) && snapshot.status==='active'));
            text(notice.querySelector('[data-source-title]'),source.title);
            text(notice.querySelector('[data-source-version]'),`Versão ${source.version} ${source.published?'publicada':'em rascunho'} · esta aula: ${source.session_version ?? 'não confirmada'}`);
            text(notice.querySelector('[data-source-notice-title]'),source.replacement_url?'Esta preparação foi substituída.':source.modality_enabled===false?'Modalidade desativada.':source.session_version===null?'Confira o treino desta aula.':'O treino cadastrado foi atualizado.');
            text(notice.querySelector('[data-source-notice-description]'),source.replacement_url?'O conteúdo anterior permanece no histórico. Abra a nova sessão para usar o treino atualizado.':source.modality_enabled===false?(snapshot.execution_started?'Esta aula pode continuar com seu conteúdo original. Novas apresentações dependem da reativação da modalidade.':'Reative a modalidade em Configurações antes de iniciar esta preparação.'):!source.published?'Esta aula mantém o conteúdo original. Publique as alterações do treino para preparar a nova versão.':!snapshot.execution_started?'Esta preparação ainda não foi iniciada. Prepare a versão publicada atual para começar.':'Você pode continuar esta aula com o conteúdo original ou confirmar uma nova execução com o treino atualizado.');
            notice.querySelector('[data-source-preview]').href=source.preview_url;
            const link=notice.querySelector('[data-replacement-link]');
            link.hidden=!source.replacement_url;
            if(source.replacement_url)link.href=source.replacement_url;
            const form=notice.querySelector('[data-source-restart]');
            if(form) {
                text(form.querySelector('[data-source-restart-submit]'),snapshot.execution_started?'Reiniciar aula com a versão publicada atual':'Preparar aula com a versão publicada atual');
                form.hidden=!(source.updated && source.published && snapshot.status==='active' && snapshot.can_control);
                form.querySelector('[data-restart-client]').value=this.client;
                form.querySelector('[data-restart-version]').value=snapshot.version;
                form.querySelector('[data-restart-workout-version]').value=source.version;
            }
        }
        if(this.blockId!==timer.id) {
            this.blockId=timer.id;
            this.board.showBlock(timer, snapshot.timer_formats?.[timer.timer_type] || timer.timer_type);
        }
        this.board.showStages(snapshot.timers, snapshot.current_position);
        if(snapshot.box) this.updateBox(snapshot.box);
    }
    updateBox(box) {
        if(this.lastTheme!==box.theme) { document.body.style.cssText=box.theme; this.lastTheme=box.theme; }
        text(this.elements['live-box-name'],box.name);
        if (!this.publicTv) {
            const logo=this.elements['live-logo'];
            if(logo) {
                logo.hidden=!box.logo_url;
                if(box.logo_url && logo.getAttribute('src')!==box.logo_url) logo.src=box.logo_url;
            }
        }
        if (this.timezone === box.timezone) return;
        this.timezone = box.timezone;
        this.dateFormat=new Intl.DateTimeFormat('pt-BR',{timeZone:box.timezone,day:'2-digit',month:'2-digit',year:'numeric'});
        this.timeFormat=new Intl.DateTimeFormat('pt-BR',{timeZone:box.timezone,hour:'2-digit',minute:'2-digit',second:'2-digit'});
    }
    ownsControl() {
        const snapshot=this.clock.snapshot;
        return this.control && this.online && this.authorized && snapshot?.can_control && snapshot.controller_client_id===this.client && snapshot.controller_lease_expires_at_ms>this.clock.serverNow();
    }
    updateControls() {
        const snapshot=this.clock.snapshot;
        if(!snapshot) return;
        const timer=snapshot.timers[snapshot.current_position];
        const active=snapshot.status==='active';
        const owns=this.ownsControl();
        const stalePending=!snapshot.execution_started && ((snapshot.source?.updated && snapshot.source.published) || snapshot.source?.modality_enabled===false);
        const last = snapshot.current_position === snapshot.timers.length - 1;
        const visible = {
            start: timer.state === 'idle',
            pause: ['preparing', 'running'].includes(timer.state),
            resume: timer.state === 'paused',
            next: !last,
            complete_block: !['completed', 'cancelled'].includes(timer.state),
            finish: last && timer.state === 'completed',
            previous: snapshot.current_position > 0,
            reset: true,
            cancel: true,
        };
        this.root.querySelectorAll('[data-session-command]').forEach(button => {
            const action = button.dataset.sessionCommand;
            button.hidden = !(active && visible[action]);
            const staleRestricted = ['start', 'resume', 'next', 'previous', 'complete_block'].includes(action);
            button.disabled = button.hidden || !owns || !!this.pending || (stalePending && staleRestricted);
        });
        const restart=this.root.querySelector('[data-source-restart-submit]');
        if(restart)restart.disabled=!(active && owns && !this.pending && snapshot.source?.updated && snapshot.source.published);
        const claim=this.elements['claim-control'];
        if(claim) {
            claim.hidden=!(active && snapshot.can_control && !owns);
            claim.disabled=!this.online || this.claiming || !this.authorized;
        }
        text(this.elements['ownership-label'],!active?'Aula encerrada. Consulte o resumo.':owns?'Esta aba controla a aula.':this.online?'Modo de leitura · controle em outra aba ou aguardando conexão.':'Controles indisponíveis até reconectar.');
    }
    async claim(takeover=false) {
        if(this.claiming || !this.control || !this.clock.snapshot?.can_control) return;
        this.claiming=true;
        try {
            const response=await this.transport.claim({client_id:this.client,takeover});
            this.accept(response);
            this.lastRenewal=performance.now();
            if(takeover && !response.ok) this.feedback(response.payload.message || 'Não foi possível assumir o controle.');
        } catch { this.network(false); }
        finally { this.claiming=false;this.updateControls(); }
    }
    async send(action) {
        if(!this.ownsControl() || this.pending || this.clock.snapshot.status!=='active') return;
        if(action==='reset' && !window.confirm('Reiniciar o cronômetro desta etapa? O tempo acumulado será zerado e o timer ficará parado.')) return;
        if(action==='finish' && !window.confirm('Finalizar esta aula e manter seu registro no histórico?')) return;
        if(action==='cancel' && !window.confirm('Cancelar esta aula? O histórico será preservado.')) return;
        this.pending={client_id:this.client,command_id:crypto.randomUUID(),expected_version:this.clock.snapshot.version,action,...(action==='reset'?{confirmation:true}:{})};
        this.feedback('Confirmando comando com o servidor…');
        this.updateControls();
        await this.deliverPending();
    }
    async deliverPending() {
        if(!this.pending || this.delivering) return;
        this.delivering=true;
        try {
            const result=await this.transport.command(this.pending);
            this.accept(result);
            this.pending=null;
            const errors=result.payload.errors?Object.values(result.payload.errors).flat().join(' '):'';
            this.feedback(result.ok?'Comando confirmado.':errors || result.payload.message || 'O comando não foi aplicado. Confira o estado atualizado.');
        } catch {
            this.network(false);
            this.feedback('Comando ainda não confirmado. A confirmação será consultada após reconectar.');
        } finally {this.delivering=false;this.updateControls();}
    }
    pollNow() {clearTimeout(this.timeout);if(!this.polling && !this.stopped)this.poll();}
    async poll() {
        if(this.polling || this.stopped || (this.publicTv && !this.authorized)) return;
        this.polling=true;
        try {
            const result=await this.transport.read();
            this.accept(result);
            const snapshot=this.clock.snapshot;
            if(result.ok && snapshot && this.control && snapshot.status==='active' && snapshot.can_control && this.authorized) {
                if(this.pending) await this.deliverPending();
                if(!this.pending && performance.now()-this.lastRenewal>1500 && (snapshot.controller_client_id===this.client || !snapshot.controller_lease_expires_at_ms || snapshot.controller_lease_expires_at_ms<=this.clock.serverNow())) await this.claim();
            }
        } catch { this.network(false); }
        finally {
            this.polling=false;
            this.updateControls();
            const interval = this.publicTv && this.root.dataset.tvStatus === 'waiting' ? Math.max(3000, this.interval) : this.interval;
            if(!this.stopped && (!this.publicTv || this.authorized)) this.timeout=setTimeout(()=>this.poll(),this.online?interval:Math.min(5000,interval*3));
        }
    }
    renderFrame() {
        if(this.stopped) return;
        const snapshot=this.clock.snapshot;
        if(snapshot) {
            const timer=snapshot.timers[snapshot.current_position];
            const projection=this.clock.project();
            this.board.showClock(timer, projection, snapshot.status);
            const now=performance.now();
            this.sounds.update(timer,projection,now);
            this.updateControls();
        }
        if(this.elements['live-clock'] && this.timeFormat) {
            const date=new Date(this.clock.serverNow());
            text(this.elements['live-clock'],this.timeFormat.format(date));
            text(this.elements['live-date'],this.dateFormat.format(date));
        }
        this.frame=requestAnimationFrame(()=>this.renderFrame());
    }
}
const root=document.querySelector('[data-session-app]');
if(root)new SessionApp(root);
