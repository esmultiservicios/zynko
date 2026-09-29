(()=>{
  'use strict';
  const esc=v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const icons={success:'✓',error:'×',info:'i',warning:'!',question:'?'};
  window.showNotify=(type='info',title='',message='')=>{
    type=['success','error','info','warning'].includes(type)?type:'info';
    let host=document.querySelector('.zynko-notify-host');
    if(!host){host=document.createElement('div');host.className='zynko-notify-host';host.setAttribute('aria-live','polite');document.body.appendChild(host)}
    const el=document.createElement('div');el.className=`zynko-notify ${type}`;
    el.innerHTML=`<span class="zynko-notify-icon">${icons[type]}</span><div><b>${esc(title||({success:'Listo',error:'Error',info:'Información',warning:'Atención'}[type]))}</b><p>${esc(message)}</p></div><button type="button" aria-label="Cerrar">×</button>`;
    host.appendChild(el); requestAnimationFrame(()=>el.classList.add('show'));
    const close=()=>{el.classList.remove('show');setTimeout(()=>el.remove(),180)}; el.querySelector('button').onclick=close; setTimeout(close,type==='error'?6500:4500); return el;
  };
  window.Swal=window.Swal||{};
  const dialogActionIcon=(label='',confirmed=true,kind='')=>{
    const t=String(label||'').toLowerCase();
    if(!confirmed||/cancelar|cerrar ventana|volver|no,/.test(t))return 'fa-solid fa-xmark';
    if(/eliminar|borrar/.test(t))return 'fa-solid fa-trash-can';
    if(/revocar|desautorizar|cerrar sesi|salir/.test(t))return 'fa-solid fa-right-from-bracket';
    if(/enviar|correo|enlace/.test(t))return 'fa-solid fa-paper-plane';
    if(/guardar|actualizar/.test(t))return 'fa-solid fa-floppy-disk';
    if(/crear|agregar|añadir/.test(t))return 'fa-solid fa-plus';
    if(/aprobar|publicar|continuar|confirmar|sí/.test(t))return 'fa-solid fa-check';
    if(/entendido|aceptar/.test(t))return 'fa-solid fa-check';
    return kind==='warning'?'fa-solid fa-check':'fa-solid fa-check';
  };
  window.Swal.fire=(options={})=>new Promise(resolve=>{
    if(typeof options==='string')options={title:options};
    const confirmText=options.confirmButtonText||'Aceptar';
    const cancelText=options.cancelButtonText||'Cancelar';
    const confirmIcon=options.confirmButtonIcon||dialogActionIcon(confirmText,true,options.icon);
    const cancelIcon=options.cancelButtonIcon||dialogActionIcon(cancelText,false,options.icon);
    const overlay=document.createElement('div');overlay.className='zynko-dialog-overlay';
    const icon=options.icon&&icons[options.icon]?`<span class="zynko-dialog-icon ${esc(options.icon)}">${icons[options.icon]}</span>`:'';
    overlay.innerHTML=`<div class="zynko-dialog" role="dialog" aria-modal="true" aria-labelledby="zynko-dialog-title">${icon}<h2 id="zynko-dialog-title">${esc(options.title||'Confirmación')}</h2>${options.text?`<p>${esc(options.text)}</p>`:''}<div class="zynko-dialog-actions">${options.showCancelButton?`<button type="button" class="zynko-dialog-cancel">${cancelIcon?`<i class="${esc(cancelIcon)}"></i>`:''}<span>${esc(cancelText)}</span></button>`:''}<button type="button" class="zynko-dialog-confirm">${confirmIcon?`<i class="${esc(confirmIcon)}"></i>`:''}<span>${esc(confirmText)}</span></button></div></div>`;
    document.body.appendChild(overlay);document.body.classList.add('zynko-dialog-open');
    const done=isConfirmed=>{overlay.classList.remove('open');document.body.classList.remove('zynko-dialog-open');setTimeout(()=>overlay.remove(),150);resolve({isConfirmed,isDismissed:!isConfirmed})};
    overlay.querySelector('.zynko-dialog-confirm').onclick=()=>done(true);overlay.querySelector('.zynko-dialog-cancel')?.addEventListener('click',()=>done(false));
    overlay.addEventListener('click',e=>{if(e.target===overlay&&options.allowOutsideClick!==false)done(false)});
    const key=e=>{if(e.key==='Escape'&&options.allowEscapeKey!==false){document.removeEventListener('keydown',key);done(false)}};document.addEventListener('keydown',key,{once:true});
    requestAnimationFrame(()=>{overlay.classList.add('open');overlay.querySelector('.zynko-dialog-confirm')?.focus()});
  });
  // API única para modales premium ZYNKO. Evita diálogos nativos y mantiene el mismo comportamiento visual.
  window.ZynkoModal={
    open(target){const modal=typeof target==='string'?document.querySelector(target):target;if(!modal)return false;modal.classList.add('open');modal.setAttribute('aria-hidden','false');document.body.classList.add('modal-open');requestAnimationFrame(()=>modal.querySelector('input:not([type=hidden]),select,textarea,button:not(.modal-close)')?.focus());return true},
    close(target){const modal=typeof target==='string'?document.querySelector(target):target;if(!modal)return false;modal.classList.remove('open');modal.setAttribute('aria-hidden','true');if(!document.querySelector('.modal-shell.open'))document.body.classList.remove('modal-open');return true}
  };
  document.addEventListener('click',e=>{const close=e.target.closest('.modal-close');if(close){const modal=close.closest('.modal-shell');if(modal)ZynkoModal.close(modal)}});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'){const modal=document.querySelector('.modal-shell.open');if(modal)ZynkoModal.close(modal)}});
  document.addEventListener('submit',async e=>{
    const form=e.target;if(!(form instanceof HTMLFormElement)||!form.matches('[data-confirm]'))return;e.preventDefault();
    const r=await Swal.fire({title:form.dataset.confirmTitle||'¿Confirmar?',text:form.dataset.confirm||'',icon:form.dataset.confirmIcon||'warning',showCancelButton:true,confirmButtonText:form.dataset.confirmOk||'Sí, continuar',confirmButtonIcon:form.dataset.confirmOkIcon||undefined,cancelButtonText:form.dataset.confirmCancel||'Cancelar',cancelButtonIcon:form.dataset.confirmCancelIcon||undefined,allowOutsideClick:false});
    if(r.isConfirmed)form.submit();
  });
})();
