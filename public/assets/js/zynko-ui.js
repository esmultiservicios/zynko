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
  // V2.27.2 · foco inteligente global.
  // Mantiene el cursor en el primer campo realmente utilizable de formularios y modales,
  // sin robar el foco al buscador global/público del dashboard ni a controles ocultos.
  const zynkoFieldIsUsable=(el)=>{
    if(!el || !(el instanceof HTMLElement))return false;
    if(el.matches('[disabled],[readonly],[hidden],[aria-hidden="true"],[tabindex="-1"]'))return false;
    if(el.closest('[hidden],[aria-hidden="true"],.select2-container--disabled'))return false;
    if(el.closest('header,.top-left,.global-search,#globalSearch,#searchModal,.command-overlay,.command-palette,.zynko-dialog-overlay'))return false;
    if(el instanceof HTMLInputElement){
      const type=(el.type||'text').toLowerCase();
      if(['hidden','button','submit','reset','checkbox','radio','file','image'].includes(type))return false;
    }
    const style=getComputedStyle(el);
    if(style.display==='none'||style.visibility==='hidden')return false;
    const r=el.getBoundingClientRect();
    return r.width>0&&r.height>0;
  };
  window.ZynkoFocusFirst=(container=document,options={})=>{
    const root=typeof container==='string'?document.querySelector(container):container;
    if(!root)return null;
    const selector='input, textarea, select, [contenteditable="true"]';
    const explicit=[...root.querySelectorAll('[autofocus]')].find(zynkoFieldIsUsable);
    const first=explicit||[...root.querySelectorAll(selector)].find(zynkoFieldIsUsable);
    if(!first)return null;
    if(options.onlyIfIdle!==false){
      const active=document.activeElement;
      if(active&&active!==document.body&&active!==document.documentElement&&active!==first)return null;
    }
    try{
      first.focus({preventScroll:options.preventScroll!==false});
      if(options.selectText&&typeof first.select==='function')first.select();
    }catch(_){try{first.focus()}catch(__){}}
    return first;
  };
  const zynkoAutoFocusPage=()=>{
    if(document.querySelector('.modal-shell.open,.zynko-dialog-overlay.open'))return;
    // Priorizamos formularios de autenticación/registro y luego formularios reales del contenido.
    const scopes=[
      '.auth-card form:not(.resend-form)',
      '.login-card form',
      'main form[data-autofocus-form]',
      'main form:not([data-no-autofocus])'
    ];
    for(const sel of scopes){
      const scope=[...document.querySelectorAll(sel)].find(el=>el.getClientRects().length>0);
      if(scope&&window.ZynkoFocusFirst(scope,{onlyIfIdle:true,preventScroll:true}))break;
    }
  };
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>setTimeout(zynkoAutoFocusPage,90));
  else setTimeout(zynkoAutoFocusPage,90);
  window.ZynkoModal={
    open(target){const modal=typeof target==='string'?document.querySelector(target):target;if(!modal)return false;modal.classList.add('open');modal.setAttribute('aria-hidden','false');document.body.classList.add('modal-open');requestAnimationFrame(()=>window.ZynkoFocusFirst?.(modal,{onlyIfIdle:false,preventScroll:true}));return true},
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


/* ZYNKO V2.31.12 · Tooltip premium global */
(()=>{
  const tip=document.createElement('div');tip.className='zynko-tooltip';tip.setAttribute('role','tooltip');document.body.appendChild(tip);let current=null,hideTimer=0;
  const prepare=root=>{(root.matches?.('[title]')?[root]:[]).concat([...(root.querySelectorAll?.('[title]')||[])]).forEach(el=>{const value=(el.getAttribute('title')||'').trim();if(!value)return;el.dataset.zynkoTooltip=value;el.removeAttribute('title');if(!el.hasAttribute('aria-label')&&!el.textContent.trim())el.setAttribute('aria-label',value)})};
  const place=el=>{const r=el.getBoundingClientRect(),tr=tip.getBoundingClientRect(),pad=10;let top=r.top-tr.height-9,placement='top';if(top<pad){top=r.bottom+9;placement='bottom'}let left=r.left+r.width/2-tr.width/2;left=Math.max(pad,Math.min(left,innerWidth-tr.width-pad));tip.style.left=Math.round(left)+'px';tip.style.top=Math.round(top)+'px';tip.dataset.placement=placement};
  const show=el=>{const value=el?.dataset?.zynkoTooltip;if(!value)return;clearTimeout(hideTimer);current=el;tip.textContent=value;tip.classList.add('is-visible');requestAnimationFrame(()=>place(el))};
  const hide=()=>{hideTimer=setTimeout(()=>{tip.classList.remove('is-visible');current=null},55)};
  prepare(document);
  document.addEventListener('mouseover',e=>{const el=e.target.closest?.('[data-zynko-tooltip]');if(el)show(el)});
  document.addEventListener('mouseout',e=>{const el=e.target.closest?.('[data-zynko-tooltip]');if(el&&!el.contains(e.relatedTarget))hide()});
  document.addEventListener('focusin',e=>{const el=e.target.closest?.('[data-zynko-tooltip]');if(el)show(el)});
  document.addEventListener('focusout',e=>{if(e.target.closest?.('[data-zynko-tooltip]'))hide()});
  window.addEventListener('scroll',()=>{if(current)place(current)},{passive:true});window.addEventListener('resize',()=>{if(current)place(current)},{passive:true});
  new MutationObserver(records=>records.forEach(r=>r.addedNodes.forEach(n=>{if(n.nodeType===1)prepare(n)}))).observe(document.body,{childList:true,subtree:true});
})();


/* ZYNKO · RTE universal seguro: conserva el valor plano de backend para no romper lógica existente. */
(()=>{
 const EXCLUDE='[hidden],.legal-html-source,[data-no-rte],.swal2-textarea';
 const esc=s=>String(s??'').replace(/[&<>\"]/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;'}[m]||m));
 const enhance=ta=>{
  if(!ta||ta.dataset.zynkoRteReady==='1'||ta.matches(EXCLUDE)||ta.closest('.legal-rich-editor-shell,.zynko-rte-shell'))return;
  if(ta.type==='hidden'||getComputedStyle(ta).display==='none')return;
  ta.dataset.zynkoRteReady='1';
  const shell=document.createElement('div');shell.className='zynko-rte-shell';
  const toolbar=document.createElement('div');toolbar.className='zynko-rte-toolbar';toolbar.setAttribute('role','toolbar');toolbar.innerHTML='<select class="zynko-rte-format" aria-label="Formato"><option value="div">Párrafo</option><option value="h4">Título corto</option></select><span></span><button type="button" data-rte-cmd="bold" title="Negrita"><i class="fa-solid fa-bold"></i></button><button type="button" data-rte-cmd="italic" title="Cursiva"><i class="fa-solid fa-italic"></i></button><button type="button" data-rte-cmd="underline" title="Subrayado"><i class="fa-solid fa-underline"></i></button><span></span><button type="button" data-rte-cmd="insertUnorderedList" title="Lista"><i class="fa-solid fa-list-ul"></i></button><button type="button" data-rte-cmd="insertOrderedList" title="Lista numerada"><i class="fa-solid fa-list-ol"></i></button><button type="button" data-rte-link title="Agregar enlace"><i class="fa-solid fa-link"></i></button><span></span><button type="button" data-rte-align="left" title="Alinear izquierda"><i class="fa-solid fa-align-left"></i></button><button type="button" data-rte-align="center" title="Centrar"><i class="fa-solid fa-align-center"></i></button><button type="button" data-rte-align="justify" title="Justificar"><i class="fa-solid fa-align-justify"></i></button><button type="button" data-rte-cmd="removeFormat" title="Limpiar formato"><i class="fa-solid fa-eraser"></i></button>';
  const editor=document.createElement('div');editor.className='zynko-rte-editor';editor.contentEditable='true';editor.spellcheck=true;editor.setAttribute('role','textbox');editor.setAttribute('aria-multiline','true');editor.dataset.placeholder=ta.placeholder||'Escribe aquí…';
  const status=document.createElement('div');status.className='zynko-rte-status';status.innerHTML='<span><i class="fa-solid fa-pen-nib"></i> Editor de texto enriquecido</span><span class="zynko-rte-count">0 caracteres</span>';
  ta.parentNode.insertBefore(shell,ta);shell.append(toolbar,editor,status,ta);ta.classList.add('zynko-rte-native');
  const render=()=>{editor.innerHTML='';const lines=String(ta.value||'').split(/\r?\n/);lines.forEach((line,i)=>{const d=document.createElement('div');if(!line&&i===lines.length-1)d.innerHTML='<br>';else d.textContent=line;editor.appendChild(d)});updateCount()};
  const updateCount=()=>{const c=shell.querySelector('.zynko-rte-count');if(c)c.textContent=new Intl.NumberFormat('es-HN').format((editor.innerText||'').length)+' caracteres'};
  const sync=()=>{let val=(editor.innerText||'').replace(/\u00a0/g,' ');const max=parseInt(ta.getAttribute('maxlength')||'0',10);if(max>0&&val.length>max){val=val.slice(0,max);ta.value=val;render();return}ta.value=val;ta.dispatchEvent(new Event('input',{bubbles:true}));updateCount()};
  editor.addEventListener('input',sync);toolbar.querySelectorAll('[data-rte-cmd]').forEach(b=>b.addEventListener('click',()=>{editor.focus();document.execCommand(b.dataset.rteCmd,false,null);sync()}));toolbar.querySelectorAll('[data-rte-align]').forEach(b=>b.addEventListener('click',()=>{editor.focus();document.execCommand({left:'justifyLeft',center:'justifyCenter',justify:'justifyFull'}[b.dataset.rteAlign]||'justifyLeft',false,null);sync()}));toolbar.querySelector('.zynko-rte-format')?.addEventListener('change',e=>{editor.focus();document.execCommand('formatBlock',false,e.target.value==='h4'?'H4':'DIV');sync()});toolbar.querySelector('[data-rte-link]')?.addEventListener('click',async()=>{const r=await Swal.fire({title:'Agregar enlace',input:'url',inputPlaceholder:'https://ejemplo.com',showCancelButton:true,confirmButtonText:'Agregar',cancelButtonText:'Cancelar',allowOutsideClick:false});if(r.isConfirmed&&r.value){editor.focus();document.execCommand('createLink',false,r.value);sync()}});
  ta.addEventListener('invalid',()=>{setTimeout(()=>editor.focus(),0)});ta.form?.addEventListener('submit',sync,true);render();
 };
 const scan=root=>root.querySelectorAll?.('textarea:not(.legal-html-source):not([hidden]):not([data-no-rte])').forEach(enhance);
 const start=()=>{scan(document);new MutationObserver(ms=>ms.forEach(m=>m.addedNodes.forEach(n=>{if(n.nodeType===1){if(n.matches?.('textarea'))enhance(n);scan(n)}}))).observe(document.body,{childList:true,subtree:true})};
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
})();


/* ZYNKO V2.31.67 · Cabeceras premium universales para tarjetas administrativas */
(()=>{
  const rules=[
    [/sitio público|redes sociales/i,['fa-solid fa-globe','Sitio público']],
    [/servidor|dominio|\.env/i,['fa-solid fa-server','Infraestructura']],
    [/proveedor de envío|correo|smtp|microsoft graph/i,['fa-solid fa-envelope-circle-check','Mensajería']],
    [/identidad|empresa/i,['fa-solid fa-building','Empresa']],
    [/branding|apariencia|marca/i,['fa-solid fa-palette','Identidad visual']],
    [/experiencia|preferencias/i,['fa-solid fa-sliders','Experiencia']],
    [/seo|posicionamiento|robots|sitemap/i,['fa-solid fa-magnifying-glass-chart','SEO']],
    [/versión|version/i,['fa-solid fa-code-branch','Sistema']],
    [/nivo|chatbot|ia/i,['fa-solid fa-robot','NIVO']],
    [/integracion|api|webhook/i,['fa-solid fa-plug','Integraciones']],
    [/canal|whatsapp|messenger/i,['fa-solid fa-tower-broadcast','Canales']],
    [/suscrip|plan|factur|billing/i,['fa-solid fa-credit-card','Suscripciones']],
    [/usuario|equipo|rol/i,['fa-solid fa-users-gear','Accesos']],
    [/seguridad|sesion/i,['fa-solid fa-shield-halved','Seguridad']],
    [/dashboard|resumen|actividad|métrica/i,['fa-solid fa-chart-line','Resumen']],
    [/convers|bandeja|contacto/i,['fa-solid fa-inbox','Conversaciones']],
    [/onboarding|inicio guiado/i,['fa-solid fa-route','Configuración']],
  ];
  const findMeta=text=>{
    for(const [rx,meta] of rules)if(rx.test(text))return meta;
    return ['fa-solid fa-layer-group','ZYNKO'];
  };
  const enhance=head=>{
    if(!head||head.dataset.zynkoCardHead==='1'||head.classList.contains('premium-card-head'))return;
    if(head.closest('.conv-list,.chat,.info,.modal-shell,.hero-panel,.metric-grid'))return;
    const copy=head.querySelector(':scope > div');
    if(!copy)return;
    const title=copy.querySelector('b,h2,h3,h4');
    if(!title)return;
    const text=(title.textContent||'').trim();
    if(!text)return;
    const [iconClass,context]=findMeta(text);
    let icon=title.querySelector(':scope > i');
    const iconBox=document.createElement('span');
    iconBox.className='zynko-card-head-icon';
    if(icon){icon.remove();iconBox.append(icon)}else{icon=document.createElement('i');icon.className=iconClass;iconBox.append(icon)}
    copy.classList.add('zynko-card-head-copy');
    const inner=document.createElement('span');inner.className='zynko-card-head-text';
    while(copy.firstChild)inner.append(copy.firstChild);
    copy.append(iconBox,inner);
    head.classList.add('zynko-card-head');
    head.dataset.zynkoCardHead='1';
    if(head.children.length===1){
      const chip=document.createElement('span');chip.className='zynko-card-head-context';chip.innerHTML=`<i class="fa-solid fa-circle-check"></i><span>${context}</span>`;head.append(chip);
    }else{
      [...head.children].slice(1).forEach(el=>el.classList.add('zynko-card-head-side'));
    }
  };
  const scan=root=>root.querySelectorAll?.('.panel > .panel-head,.panel.spaced > .panel-head').forEach(enhance);
  const start=()=>{
    scan(document);
    new MutationObserver(records=>records.forEach(r=>r.addedNodes.forEach(n=>{if(n.nodeType===1){if(n.matches?.('.panel-head'))enhance(n);scan(n)}}))).observe(document.body,{childList:true,subtree:true});
  };
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
})();
