/** Transport boundary: replaceable by a future broadcasting adapter. */
export class PollingTransport {
    constructor(root) {
        this.urls={state:root.dataset.stateUrl,lease:root.dataset.leaseUrl,command:root.dataset.commandUrl};
        this.csrf=document.querySelector('meta[name="csrf-token"]')?.content;
    }
    async request(kind,body) {
        const sentAt=performance.now();
        const controller=new AbortController();
        const timeout=setTimeout(()=>controller.abort(),6000);
        try {
            const response=await fetch(this.urls[kind],{
                method:body?'POST':'GET', credentials:'same-origin',cache:'no-store',signal:controller.signal,
                headers:{Accept:'application/json',...(body?{'Content-Type':'application/json','X-CSRF-TOKEN':this.csrf}:{})},
                ...(body?{body:JSON.stringify(body)}:{}),
            });
            const payload=await response.json();
            return {ok:response.ok,status:response.status,payload,sentAt,receivedAt:performance.now()};
        } finally { clearTimeout(timeout); }
    }
    read() { return this.request('state'); }
    claim(body) { return this.request('lease',body); }
    command(body) { return this.request('command',body); }
}
