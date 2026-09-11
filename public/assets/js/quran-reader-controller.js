import { fetchFragment } from './fetch-client.js';

/** Optional enhancement: normal links/forms remain the authoritative no-JS path. */
export class QuranReaderController {
    constructor({ liveRegion, documentRef = document, windowRef = window }) { Object.assign(this, { liveRegion, documentRef, windowRef }); this.onClick = this.onClick.bind(this); this.onSubmit = this.onSubmit.bind(this); }
    start() { this.documentRef.addEventListener('click', this.onClick); this.documentRef.addEventListener('submit', this.onSubmit); }
    async onClick(event) { const link=event.target.closest?.('a[href^="/quran"]'); if(!(link instanceof HTMLAnchorElement)||event.defaultPrevented||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey||!globalThis.fetch) return; event.preventDefault(); await this.navigate(link.href); }
    async onSubmit(event) { const form=event.target; if(!(form instanceof HTMLFormElement)||!form.matches('[data-qmdb-quran-search]')||!globalThis.fetch) return; event.preventDefault(); const target=new URL(form.action,this.windowRef.location.origin); new FormData(form).forEach((value,key)=>target.searchParams.set(key,String(value))); await this.navigate(target.toString()); }
    async navigate(url) { const main=this.documentRef.querySelector('#main-content'); if(!main) return this.windowRef.location.assign(url); main.setAttribute('aria-busy','true'); try { const result=await fetchFragment(url,{locale:this.documentRef.documentElement.lang}); const fragment=this.documentRef.importNode(result.fragment,true); main.replaceChildren(fragment); this.windowRef.history.pushState({},'',new URL(url).pathname+new URL(url).search); main.focus(); this.liveRegion.announce(this.documentRef.documentElement.lang==='ar'?'تم تحديث مرجع القرآن.':'Qur’an reference updated.'); } catch { this.windowRef.location.assign(url); } finally { main.removeAttribute('aria-busy'); } }
}
