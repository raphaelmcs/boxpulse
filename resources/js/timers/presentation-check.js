import {AdaptiveContent} from './adaptive-content.js';

for(const check of document.querySelectorAll('[data-presentation-check]')) {
    const resolution=check.querySelector('[data-capacity-resolution]');
    const output=check.querySelector('[data-capacity-result]');
    const form=check.closest('[data-workout-form]');
    let instances=[],surfaces=[],scheduled;
    const blocks=()=>{
        if(!form)return JSON.parse(check.querySelector('[data-capacity-blocks]').textContent);
        return [...form.querySelectorAll('[data-stage]:not([disabled])')].map(stage=>{
            const value=(suffix)=>stage.querySelector(`[name$="[${suffix}]"]`)?.value || '';
            return {title:value('title'),description:value('description'),notes:value('notes'),suggested_loads:['rx','intermediate','scale'].some(key=>value(key))?{rx:value('rx')}:null,stations:[...stage.querySelectorAll('[data-station]')].map(station=>Object.fromEntries([...station.querySelectorAll('input,textarea')].map(input=>[input.name.match(/\[([^\]]+)\]$/)?.[1],input.value]).filter(([key,value])=>key && value!=='')))};
        }).filter(block=>block.title.trim() || block.description.trim() || block.notes.trim() || block.stations.length || block.suggested_loads);
    };
    const run=()=>{
        instances.forEach(instance=>instance.destroy());surfaces.forEach(surface=>surface.remove());instances=[];surfaces=[];
        const [width,height]=resolution.value.split(',').map(Number),scale=Math.min(width/1920,height/1080);
        const source=blocks();
        if(!source.length){output.textContent='Adicione conteúdo a uma etapa para conferir sua exibição.';check.dataset.capacity='empty';return;}
        const results=new Map();
        source.forEach((block,index)=>{
            const surface=document.createElement('div');surface.className='presentation-measure';surface.setAttribute('aria-hidden','true');
            const title=document.createElement('h2');title.textContent=block.title || 'Etapa da aula';title.style.cssText=`font-size:${36*scale}px;line-height:1.2;width:${width*.68}px`;
            surface.append(title);document.body.append(surface);
            const content=document.createElement('div');content.className='adaptive-content';content.setAttribute('data-content-pages','');content.style.height=`${Math.max(120*scale,height*.67-title.getBoundingClientRect().height-(block.suggested_loads?145*scale:0)-50*scale)}px`;surface.replaceChildren(content);surfaces.push(surface);
            const instance=new AdaptiveContent(surface,{screen:{width,height},onResult:result=>{
                results.set(index,result.fits);
                const excessive=[...results.entries()].filter(([,fits])=>!fits).map(([i])=>`etapa ${i+1}`);
                check.dataset.capacity=excessive.length?'excess':'fits';
                output.textContent=excessive.length?`Conteúdo excessivo para exibição simultânea: ${excessive.join(', ')}. Reorganize ou resuma as instruções antes de apresentar. O texto original será preservado.`:`${source.length} etapa(s) conferida(s) para ${width} × ${height}. Conteúdo dentro da referência de legibilidade. A TV ajustará o espaço real disponível.`;
            }});instances.push(instance);instance.setBlock(block);
        });
    };
    const schedule=()=>{clearTimeout(scheduled);scheduled=setTimeout(run,250);};
    resolution.addEventListener('change',run);
    form?.addEventListener('input',schedule);form?.addEventListener('change',schedule);
    check.querySelector('[data-capacity-edit]').addEventListener('click',event=>{
        if(!form)return;event.preventDefault();
        const stage=form.querySelector('[data-stage]:not([disabled])');
        if(stage){stage.querySelector('details').open=true;stage.querySelector('textarea')?.focus();stage.scrollIntoView({block:'center',behavior:'smooth'});}
    });
    run();
    window.addEventListener('pagehide',()=>{clearTimeout(scheduled);instances.forEach(instance=>instance.destroy());surfaces.forEach(surface=>surface.remove());});
}
