import { SessionClock, formatTime } from './engine.js';
import { PollingTransport } from './polling-transport.js';
import { ContentPager } from './content-pager.js';
import { SoundSignals } from './sound-signals.js';

const phases={idle:'Pronto para começar',preparation:'Preparação',work:'Em execução',rest:'Descanso',transition:'Transição',completed:'Etapa finalizada',cancelled:'Aula cancelada'};
const states={idle:'Aguardando o coach',preparing:'Contagem de preparação',running:'Em execução',paused:'Pausado',completed:'Etapa finalizada',cancelled:'Cancelado'};
const formats={none:'Sem tempo',repetitions:'Circuito por repetições',stopwatch:'Contagem progressiva',countdown:'Contagem regressiva',for_time:'FOR TIME',amrap:'AMRAP',emom:'EMOM',tabata:'TABATA',interval:'INTERVALADO',circuit:'CIRCUITO'};
const blocks={warmup:'Warm-up / Aquecimento',skill:'Técnica / Força',workout:'Workout principal'};
const text=(node,value)=>{ if(node && node.textContent!==String(value)) node.textContent=String(value); };

export class SessionApp {
    constructor(root) {
        this.root=root;
        this.control=root.dataset.view==='control';
        this.client=crypto.randomUUID();
        this.transport=new PollingTransport(root);
        this.clock=new SessionClock();
        this.pager=new ContentPager(root);
        this.sounds=new SoundSignals(root.querySelector('[data-audio-toggle]'));
        this.online=false;
        this.authorized=true;
        this.pending=null;
        this.claiming=false;
        this.polling=false;
        this.lastRenewal=-Infinity;
        this.interval=Math.max(500,Number(root.dataset.pollMs)||1000);
        this.elements={};
        for(const name of ['timer-format','round','phase-label','timer-digits','timer-state','current-station','progress','progress-bar','duration-label','total-remaining','block-label','block-title','session-stages','sync-status','session-feedback','ownership-label','claim-control','live-clock','live-date','live-box-name','live-logo']) this.elements[name]=root.querySelector(`[data-${name}]`);
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
        document.addEventListener('visibilitychange',()=>{if(!document.hidden)this.pollNow();});
        window.addEventListener('pageshow',(event)=>{if(event.persisted)window.location.reload();});
        window.addEventListener('pagehide',()=>{this.stopped=true;clearTimeout(this.timeout);cancelAnimationFrame(this.frame);});
        this.poll();
        this.renderFrame();
    }
    feedback(message) { text(this.elements['session-feedback'],message); }
    network(online) {
        this.online=online;
        this.root.dataset.online=String(online);
        text(this.elements['sync-status'],online?'● Sincronizado':'● Sem conexão · aguardando reconexão');
    }
    accept(result) {
        this.network(true);
        if([401,403,404,419].includes(result.status)) {
            this.authorized=false;
            this.feedback('Acesso indisponível ou sessão de login expirada. Entre novamente no painel.');
        }
        if(result.payload.data && this.clock.accept(result.payload.data,result.sentAt,result.receivedAt)) this.updateSnapshot();
    }
    updateSnapshot() {
        const snapshot=this.clock.snapshot;
        const timer=snapshot.timers[snapshot.current_position];
        this.root.dataset.sessionStatus=snapshot.status;
        const ended=this.root.querySelector('[data-session-ended]');
        if(ended)ended.hidden=snapshot.status==='active';
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
            this.pager.station=null;
            const loads=timer.suggested_loads;
            const section=this.root.querySelector('[data-suggested-loads]');
            if(section) {
                section.hidden=!loads || !['rx','intermediate','scale'].some((key)=>loads[key]!==null && loads[key]!==undefined && loads[key]!=='');
                text(section.querySelector('[data-load-equipment]'),loads?.equipment || '');
                section.querySelectorAll('[data-load-level]').forEach((card)=>{
                    const value=loads?.[card.dataset.loadLevel];
                    card.hidden=value===null || value===undefined || value==='';
                    text(card.querySelector('[data-load-value]'),value!==null && value!==undefined ? `${value}${loads?.unit && /^[0-9]+(?:[.,][0-9]+)?$/.test(value)?` ${loads.unit}`:''}`:'');
                });
            }
            this.pager.setBlock(timer);
        }
        text(this.elements['block-label'],blocks[timer.block_type]);
        text(this.elements['block-title'],timer.title || blocks[timer.block_type]);
        text(this.elements['timer-format'],formats[timer.timer_type]);
        const signature=JSON.stringify([snapshot.current_position,...snapshot.timers.map((item)=>[item.id,item.state])]);
        if(signature!==this.stagesSignature) {
            this.stagesSignature=signature;
            this.elements['session-stages'].replaceChildren(...snapshot.timers.map((item,index)=>{
                const li=document.createElement('li');
                li.className=index===snapshot.current_position?'is-active':'';
                if(index===snapshot.current_position) li.setAttribute('aria-current','step');
                const name=document.createElement('strong');
                name.textContent=`0${index+1} / ${blocks[item.block_type]}`;
                const status=document.createElement('span');
                status.textContent=states[item.state];
                li.append(name,status);
                return li;
            }));
        }
        if(snapshot.box) {
            if(this.lastTheme!==snapshot.box.theme) { document.body.style.cssText=snapshot.box.theme; this.lastTheme=snapshot.box.theme; }
            text(this.elements['live-box-name'],snapshot.box.name);
            const logo=this.elements['live-logo'];
            if(logo) {
                logo.hidden=!snapshot.box.logo_url;
                if(snapshot.box.logo_url && logo.getAttribute('src')!==snapshot.box.logo_url) logo.src=snapshot.box.logo_url;
            }
            this.dateFormat=new Intl.DateTimeFormat('pt-BR',{timeZone:snapshot.box.timezone,day:'2-digit',month:'2-digit',year:'numeric'});
            this.timeFormat=new Intl.DateTimeFormat('pt-BR',{timeZone:snapshot.box.timezone,hour:'2-digit',minute:'2-digit',second:'2-digit'});
        }
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
        const allowed={start:timer.state==='idle' && !stalePending,pause:['preparing','running'].includes(timer.state),resume:timer.state==='paused',reset:true,previous:snapshot.current_position>0 && !stalePending,next:snapshot.current_position<snapshot.timers.length-1 && !stalePending,complete_block:!['completed','cancelled'].includes(timer.state) && !stalePending,finish:true,cancel:true};
        this.root.querySelectorAll('[data-session-command]').forEach((button)=>{button.disabled=!(active && owns && !this.pending && allowed[button.dataset.sessionCommand]);});
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
        if(action==='finish' && !window.confirm('Finalizar esta aula? O cronômetro será encerrado e o histórico ficará disponível para consulta.')) return;
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
        if(this.polling || this.stopped) return;
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
            if(!this.stopped)this.timeout=setTimeout(()=>this.poll(),this.online?this.interval:Math.min(5000,this.interval*3));
        }
    }
    renderFrame() {
        if(this.stopped) return;
        const snapshot=this.clock.snapshot;
        if(snapshot) {
            const timer=snapshot.timers[snapshot.current_position];
            const projection=this.clock.project();
            this.root.dataset.phase=projection.phase;
            this.root.dataset.timerState=timer.state;
            text(this.elements['phase-label'],timer.state==='paused'?'Pausado':projection.awaiting_confirmation?'Limite atingido':phases[projection.phase]);
            const direction=projection.phase==='preparation'?'down':timer.config.direction;
            text(this.elements['timer-digits'],direction==='none'?'LIVRE':formatTime(projection.display_ms,direction));
            text(this.elements['timer-state'],snapshot.status==='completed'?'Aula concluída':snapshot.status==='cancelled'?'Aula cancelada':projection.awaiting_confirmation?'Tempo encerrado · aguardando sincronização':timer.state==='paused'?'Pausado':projection.phase==='preparation'?'Contagem de preparação':states[timer.state]);
            const rounds=timer.timer_type==='emom'?Math.ceil(timer.config.duration_ms/timer.config.interval_ms):timer.config.rounds;
            text(this.elements.round,timer.timer_type==='repetitions'?`${rounds} rounds programados`:rounds>1?`Round ${projection.round} / ${rounds}`:'');
            const station=projection.station===null?null:timer.stations[projection.station];
            text(this.elements['current-station'],station?`Estação ${projection.station+1} · ${station.name}`:'');
            this.pager.focusStation(projection.station);
            const progress=this.elements.progress;
            progress.hidden=projection.progress===null;
            if(projection.progress!==null) {
                this.elements['progress-bar'].style.transform=`scaleX(${projection.progress})`;
                progress.setAttribute('aria-valuenow',String(Math.round(projection.progress*100)));
            }
            text(this.elements['duration-label'],timer.config.direction==='none'?'Sem cronômetro':timer.config.duration_ms!==null?`${timer.timer_type==='for_time'?'Time cap':'Duração'} ${formatTime(timer.config.duration_ms)}`:'Sem limite de tempo');
            text(this.elements['total-remaining'],projection.total_remaining_ms!==null?`Restante ${formatTime(projection.total_remaining_ms)}`:'');
            if(this.elements['live-clock'] && this.timeFormat) {
                const date=new Date(this.clock.serverNow());
                text(this.elements['live-clock'],this.timeFormat.format(date));
                text(this.elements['live-date'],this.dateFormat.format(date));
            }
            const now=performance.now();
            this.sounds.update(timer,projection,now);
            this.pager.tick(now);
            this.updateControls();
        }
        this.frame=requestAnimationFrame(()=>this.renderFrame());
    }
}
const root=document.querySelector('[data-session-app]');
if(root)new SessionApp(root);
