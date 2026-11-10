/** Preserves instructions and source order; a group is never split across columns. */
export function contentGroups(timer) {
    const groups=[];
    const add=(text,kind='exercise',station=null)=>{
        const paragraphs=String(text ?? '').replace(/\r\n?/g,'\n').split(/\n\s*\n/).filter(paragraph=>paragraph.trim());
        for(const paragraph of paragraphs) {
            const lines=paragraph.split('\n');
            if(paragraphs.length>1) {
                if(paragraph.trim())groups.push({text:paragraph,kind,station});
                continue;
            }
            for(let index=0;index<lines.length;index++) {
                let line=lines[index];
                if(!line.trim())continue;
                if((/:\s*$/.test(line) || /^\d+\s+(?:rounds|rondas|séries)/i.test(line)) && index+1<lines.length) line+='\n'+lines[++index];
                while(index+1<lines.length && /^\s+\S/.test(lines[index+1]))line+='\n'+lines[++index];
                groups.push({text:line,kind,station});
            }
        }
    };
    if(timer.description)add(timer.description);
    for(const [index,station]of (timer.stations || []).entries()) {
        const metrics=[];
        for(const [key,label]of Object.entries({distance:'m',repetitions:'reps',load:'kg',duration_seconds:'s de trabalho',rest_seconds:'s de descanso',transition_seconds:'s de transição'}))if(station[key]!=null)metrics.push(`${station[key]} ${label}`);
        groups.push({text:[`${index+1} / ${station.name || 'Estação'}`,metrics.join(' · '),station.description].filter(Boolean).join('\n'),kind:'station',station:index});
    }
    if(timer.notes)add(`Observações: ${timer.notes}`,'notes');
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

export class AdaptiveContent {
    constructor(root,{onResult=null,screen=null}={}) {
        this.root=root;
        this.container=root.querySelector('[data-content-pages]');
        this.notice=root.querySelector('[data-content-capacity-warning]');
        this.screen=screen;
        this.onResult=onResult;
        this.groups=[];
        this.signature='';
        this.pending=false;
        const schedule=()=>this.schedule();
        this.resize=schedule;
        this.observer=new ResizeObserver(schedule);
        this.observer.observe(this.container);
        window.addEventListener('resize',schedule);
        document.fonts?.ready.then(schedule);
    }
    setBlock(timer) {
        const signature=JSON.stringify([timer.description,timer.stations,timer.notes]);
        if(signature===this.signature)return;
        this.signature=signature;
        this.groups=contentGroups(timer);
        this.schedule();
    }
    schedule() {
        if(this.destroyed || this.pending || !this.groups.length)return;
        this.pending=true;
        requestAnimationFrame(()=>{this.pending=false;this.fit();});
    }
    draw(columns,font,gap) {
        this.container.style.setProperty('--adaptive-font',`${font}px`);
        this.container.style.setProperty('--adaptive-gap',`${gap}px`);
        this.container.style.gridTemplateColumns=`repeat(${columns},minmax(0,1fr))`;
        const nodes=this.groups.map((group)=>{
            const paragraph=document.createElement('p');
            paragraph.className=`adaptive-instruction content-${group.kind}`;
            paragraph.textContent=group.text;
            if(group.station!==null)paragraph.dataset.station=String(group.station);
            return paragraph;
        });
        const column=document.createElement('div');column.className='adaptive-column';
        column.append(...nodes);this.container.replaceChildren(column);
        if(columns===2) {
            const heights=nodes.map(node=>node.getBoundingClientRect().height);
            const split=balancedSplit(heights,gap);
            const second=document.createElement('div');second.className='adaptive-column';second.append(...nodes.slice(split));this.container.append(second);
        }
        return [...this.container.children].every(node=>node.scrollHeight<=this.container.clientHeight+1 && node.scrollWidth<=node.clientWidth+1);
    }
    fit() {
        if(this.destroyed)return;
        const width=this.screen?.width || window.innerWidth,height=this.screen?.height || window.innerHeight;
        const scale=Math.max(.65,Math.min(width/1920,height/1080));
        const key=JSON.stringify([this.signature,width,height,this.container.clientWidth,this.container.clientHeight]);
        if(key===this.lastMeasurement)return;
        if(this.notice)this.notice.hidden=true;
        this.root.dataset.contentExcess='false';
        let fits=false,result={fits:false,font:22*scale,columns:2,mode:'wide'};
        for(const [mode,columns,font,gap]of [['comfortable',1,32,10],['compact',1,28,7],['wide',2,28,7],['wide',2,25,5],['wide',2,22,3]]) {
            this.root.dataset.contentLayout=mode;
            if(this.screen) {
                const factor=mode==='comfortable'?.48:mode==='compact'?.60:.68;
                this.root.style.width=`${width*factor}px`;
            }
            fits=this.draw(columns,font*scale,gap*scale);
            result={fits,font:font*scale,columns,mode};
            if(fits)break;
        }
        this.root.dataset.contentExcess=String(!fits);
        this.root.dataset.contentColumns=String(result.columns);
        if(this.notice)this.notice.hidden=fits;
        this.lastMeasurement=JSON.stringify([this.signature,width,height,this.container.clientWidth,this.container.clientHeight]);
        const full=this.root.querySelector('[data-excess-full]');
        if(full) {
            full.hidden=fits || this.root.dataset.view==='tv';
            if(!fits)full.querySelector('[data-excess-text]').textContent=this.groups.map(group=>group.text).join('\n\n');
        }
        this.onResult?.(result);
    }
    focusStation(index) {
        this.container.querySelectorAll('[data-station]').forEach(node=>node.classList.toggle('is-current-station',Number(node.dataset.station)===index));
    }
    destroy() {this.destroyed=true;this.observer.disconnect();window.removeEventListener('resize',this.resize);}
}
