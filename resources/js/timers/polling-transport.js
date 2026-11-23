/** Transport boundary: replaceable by a future broadcasting adapter. */
export class PollingTransport {
    constructor(root) {
        this.urls={state:root.dataset.stateUrl,lease:root.dataset.leaseUrl,command:root.dataset.commandUrl,logo:root.dataset.logoUrl};
        this.csrf=document.querySelector('meta[name="csrf-token"]')?.content;
        this.publicTv = root.dataset.view === 'box-tv';
        if (this.publicTv) {
            this.token = /^#[a-f0-9]{64}$/.test(location.hash) ? location.hash.slice(1) : null;
        }
    }
    async request(kind,body) {
        const sentAt=performance.now();
        const controller=new AbortController();
        const timeout=setTimeout(()=>controller.abort(),6000);
        try {
            const headers = {Accept: kind === 'logo' ? 'image/*' : 'application/json'};
            if (body) {
                headers['Content-Type'] = 'application/json';
                headers['X-CSRF-TOKEN'] = this.csrf;
            }
            if (this.publicTv) {
                headers.Authorization = `Bearer ${this.token}`;
                if (kind === 'state' && this.etag) headers['If-None-Match'] = this.etag;
            }
            const response=await fetch(this.urls[kind],{
                method:body?'POST':'GET', credentials:this.publicTv?'omit':'same-origin',cache:'no-store',signal:controller.signal,
                headers,
                ...(this.publicTv ? {mode: 'same-origin', redirect: 'error', referrerPolicy: 'no-referrer'} : {}),
                ...(body?{body:JSON.stringify(body)}:{}),
            });
            if (this.publicTv && kind === 'state' && response.ok) this.etag = response.headers.get('ETag');
            const payload = kind === 'logo' ? (response.ok ? await response.blob() : null)
                : response.status === 304 ? null : await response.json();
            return {ok:response.ok,status:response.status,payload,sentAt,receivedAt:performance.now(),
                serverTime: Number(response.headers.get('X-Server-Time-Ms'))};
        } finally { clearTimeout(timeout); }
    }
    read() { return this.request('state'); }
    readLogo() { return this.request('logo'); }
    claim(body) { return this.request('lease',body); }
    command(body) { return this.request('command',body); }
}
