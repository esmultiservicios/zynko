(()=>{
  'use strict';
  const esc=v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const icons={success:'✓',error:'×',info:'i',warning:'!'};
  window.showNotify=(type='info',message='',title='')=>{
    type=['success','error','info','warning'].includes(type)?type:'info';
    let host=document.querySelector('.zynko-notify-host');
    if(!host){host=document.createElement('div');host.className='zynko-notify-host';host.setAttribute('aria-live','polite');document.body.appendChild(host)}
    const el=document.createElement('div');el.className=`zynko-notify ${type}`;
    el.innerHTML=`<span class="zynko-notify-icon">${icons[type]}</span><div><b>${esc(title||({success:'Listo',error:'Error',info:'Información',warning:'Atención'}[type]))}</b><p>${esc(message)}</p></div><button type="button" aria-label="Cerrar">×</button>`;
    host.appendChild(el); requestAnimationFrame(()=>el.classList.add('show'));
    const close=()=>{el.classList.remove('show');setTimeout(()=>el.remove(),180)}; el.querySelector('button').onclick=close; setTimeout(close,type==='error'?6500:4500); return el;
  };
  window.Swal=window.Swal||{};
  window.Swal.fire=(options={})=>new Promise(resolve=>{
    if(typeof options==='string')options={title:options};
    const overlay=document.createElement('div');overlay.className='zynko-dialog-overlay';
    const icon=options.icon&&icons[options.icon]?`<span class="zynko-dialog-icon ${esc(options.icon)}">${icons[options.icon]}</span>`:'';
    overlay.innerHTML=`<div class="zynko-dialog" role="dialog" aria-modal="true" aria-labelledby="zynko-dialog-title">${icon}<h2 id="zynko-dialog-title">${esc(options.title||'Confirmación')}</h2>${options.text?`<p>${esc(options.text)}</p>`:''}<div class="zynko-dialog-actions">${options.showCancelButton?`<button type="button" class="zynko-dialog-cancel">${esc(options.cancelButtonText||'Cancelar')}</button>`:''}<button type="button" class="zynko-dialog-confirm">${esc(options.confirmButtonText||'Aceptar')}</button></div></div>`;
    document.body.appendChild(overlay);document.body.classList.add('zynko-dialog-open');
    const done=isConfirmed=>{overlay.classList.remove('open');document.body.classList.remove('zynko-dialog-open');setTimeout(()=>overlay.remove(),150);resolve({isConfirmed,isDismissed:!isConfirmed})};
    overlay.querySelector('.zynko-dialog-confirm').onclick=()=>done(true);overlay.querySelector('.zynko-dialog-cancel')?.addEventListener('click',()=>done(false));
    overlay.addEventListener('click',e=>{if(e.target===overlay&&options.allowOutsideClick!==false)done(false)});
    const key=e=>{if(e.key==='Escape'&&options.allowEscapeKey!==false){document.removeEventListener('keydown',key);done(false)}};document.addEventListener('keydown',key,{once:true});
    requestAnimationFrame(()=>{overlay.classList.add('open');overlay.querySelector('.zynko-dialog-confirm')?.focus()});
  });
  document.addEventListener('submit',async e=>{
    const form=e.target;if(!(form instanceof HTMLFormElement)||!form.matches('[data-confirm]'))return;e.preventDefault();
    const r=await Swal.fire({title:form.dataset.confirmTitle||'¿Confirmar?',text:form.dataset.confirm||'',icon:form.dataset.confirmIcon||'warning',showCancelButton:true,confirmButtonText:form.dataset.confirmOk||'Sí, continuar',cancelButtonText:form.dataset.confirmCancel||'Cancelar',allowOutsideClick:false});
    if(r.isConfirmed)form.submit();
  });
})();
