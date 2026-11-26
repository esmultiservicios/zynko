(()=>{
  'use strict';
  const esc=v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  const icons={success:'✓',error:'×',info:'i',warning:'!',danger:'!',question:'?'};
  window.showNotify=(type='info',title='',message='')=>{
    type=['success','error','info','warning','danger'].includes(type)?type:'info';
    let host=document.querySelector('.zynko-notify-host');
    if(!host){host=document.createElement('div');host.className='zynko-notify-host';host.setAttribute('aria-live','polite');document.body.appendChild(host)}
    const el=document.createElement('div');el.className=`zynko-notify ${type}`;
    el.innerHTML=`<span class="zynko-notify-icon">${icons[type]}</span><div><b>${esc(title||({success:'Listo',error:'Error',info:'Información',warning:'Atención',danger:'Atención'}[type]))}</b><p>${esc(message)}</p></div><button type="button" aria-label="Cerrar">×</button>`;
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
  // Compatible con las opciones utilizadas por los formularios y canales existentes.
  let activeDialog=null, dialogSequence=0;
  window.Swal.getHtmlContainer=()=>activeDialog?.overlay.querySelector('.zynko-dialog-content')||null;
  window.Swal.getConfirmButton=()=>activeDialog?.overlay.querySelector('.zynko-dialog-confirm')||null;
  window.Swal.clickConfirm=()=>window.Swal.getConfirmButton()?.click();
  window.Swal.getInput=()=>activeDialog?.input||null;
  window.Swal.getPopup=()=>activeDialog?.overlay.querySelector('.zynko-dialog')||null;
  window.Swal.showValidationMessage=(message)=>{if(activeDialog){activeDialog.validation.textContent=String(message||'');activeDialog.validation.hidden=!message;}};
  window.Swal.close=()=>activeDialog?.finish('dismiss');
  window.Swal.fire=(options={})=>new Promise(resolve=>{
    if(typeof options==='string')options={title:options};
    const parent=options.stack?activeDialog:null;
    if(activeDialog&&!parent)activeDialog.finish('dismiss');
    if(parent){parent.overlay.inert=true;parent.overlay.setAttribute('aria-hidden','true');}
    const previous=document.activeElement, confirmText=options.confirmButtonText||'Aceptar', cancelText=options.cancelButtonText||'Cancelar';
    const overlay=document.createElement('div');overlay.className='zynko-dialog-overlay';overlay.style.zIndex=String(100000+(++dialogSequence));
    const icon=options.icon&&icons[options.icon]?`<span class="zynko-dialog-icon ${esc(options.icon)}">${icons[options.icon]}</span>`:'';
    const button=(kind,title,icon)=>`<button type="button" class="zynko-dialog-${kind}"><i class="${esc(icon)}"></i><span>${esc(title)}</span></button>`;
    overlay.innerHTML=`<div class="zynko-dialog" role="dialog" aria-modal="true" aria-labelledby="zynko-dialog-title">${icon}<h2 id="zynko-dialog-title">${esc(options.title||'Confirmación')}</h2>${options.text?`<p>${esc(options.text)}</p>`:''}<div class="zynko-dialog-content"></div><div class="zynko-dialog-validation" role="alert" hidden></div><div class="zynko-dialog-actions">${options.showCancelButton?button('cancel',cancelText,options.cancelButtonIcon||dialogActionIcon(cancelText,false)):''}${options.showDenyButton?button('deny',options.denyButtonText||'No','fa-solid fa-trash-can'):''}${options.showConfirmButton===false?'':button('confirm',confirmText,options.confirmButtonIcon||dialogActionIcon(confirmText,true,options.icon))}</div></div>`;
    const popup=overlay.firstElementChild, content=overlay.querySelector('.zynko-dialog-content'),validation=overlay.querySelector('.zynko-dialog-validation');
    if(options.closeOnly)popup.classList.add('zynko-close-only');
    if(options.width){const w=typeof options.width==='number'?`${options.width}px`:String(options.width);if(CSS.supports('width',w)){popup.style.width=w;popup.style.maxWidth='calc(100vw - 32px)';}}
    const extra=options.customClass?.popup;if(typeof extra==='string')for(const c of extra.split(/\s+/).filter(Boolean))popup.classList.add(c);
    if(options.workspace){
      const title=popup.querySelector('h2');
      const head=document.createElement('div');head.className='modal-head zynko-workspace-head';
      head.innerHTML=`<div class="modal-title-with-icon"><span class="modal-title-icon"><i class="${esc(options.workspace.icon||'fa-solid fa-layer-group')}"></i></span><span class="modal-title-copy"><b id="zynko-dialog-title">${esc(options.title||'')}</b><small>${esc(options.workspace.subtitle||'')}</small></span></div><button type="button" class="modal-close zynko-workspace-close" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button>`;
      title.replaceWith(head);popup.classList.add('zynko-workspace');
    }
    const titleLabel=popup.querySelector('#zynko-dialog-title');if(titleLabel){titleLabel.id='zynko-dialog-title-'+dialogSequence;popup.setAttribute('aria-labelledby',titleLabel.id);}
    if(options.html){if(options.html instanceof Node)content.append(options.html);else content.innerHTML=String(options.html);popup.classList.add('has-content');}
    let input=null;
    if(options.input){
      const field=document.createElement('div');field.className='zynko-dialog-input-wrap field';
      if(options.inputLabel){const label=document.createElement('label');label.textContent=options.inputLabel;field.append(label);}
      if(options.input==='select'){input=document.createElement('select');for(const [value,label] of Object.entries(options.inputOptions||{})){const o=document.createElement('option');o.value=value;o.textContent=String(label);input.append(o);}}
      else if(options.input==='textarea'){input=document.createElement('textarea');input.setAttribute('data-no-rte','1');input.rows=4;}
      else{input=document.createElement('input');input.type=['text','password','email','url','tel','number','search'].includes(options.input)?options.input:'text';}
      input.classList.add('zynko-dialog-input');input.id='zynko-dialog-input';field.querySelector('label')?.setAttribute('for',input.id);if(options.inputPlaceholder)input.placeholder=options.inputPlaceholder;
      for(const [k,v] of Object.entries(options.inputAttributes||{})){if(/^(?:autocomplete|autocapitalize|spellcheck|maxlength|minlength|inputmode|pattern|min|max|step|required|aria-[a-z-]+)$/i.test(k))input.setAttribute(k,String(v));}
      if(options.inputValue!==undefined)input.value=String(options.inputValue??'');field.append(input);content.append(field);
    }
    content.querySelectorAll('textarea').forEach(x=>x.setAttribute('data-no-rte','1'));
    if(!content.childNodes.length)content.hidden=true;
    document.body.appendChild(overlay);document.body.classList.add('zynko-dialog-open');
    let settled=false,busy=false,timer=null;
    const finish=(kind,value)=>{if(settled)return;settled=true;clearTimeout(timer);document.removeEventListener('keydown',key,true);try{options.willClose?.(popup)}catch(_){}if(window.jQuery&&jQuery.fn.select2)jQuery(popup).find('select.select2-hidden-accessible').select2('destroy');if(activeDialog?.overlay===overlay){activeDialog=parent;if(parent){parent.overlay.inert=false;parent.overlay.removeAttribute('aria-hidden');}}overlay.classList.remove('open');overlay.remove();if(!document.querySelector('.zynko-dialog-overlay'))document.body.classList.remove('zynko-dialog-open');if(previous?.isConnected&&!previous.closest('[hidden]'))try{previous.focus({preventScroll:true})}catch(_){}resolve({isConfirmed:kind==='confirm',isDenied:kind==='deny',isDismissed:kind==='dismiss',value});};
    const confirm=async()=>{if(busy||settled)return;busy=true;const btn=overlay.querySelector('.zynko-dialog-confirm');if(btn)btn.disabled=true;validation.hidden=true;try{let value=input?input.value:undefined;if(input&&!input.checkValidity()){Swal.showValidationMessage(input.validationMessage);input.focus();return;}if(options.inputValidator){const msg=await options.inputValidator(value);if(msg){Swal.showValidationMessage(msg);input?.focus();return;}}if(options.preConfirm){const v=await options.preConfirm(value);if(v===false||!validation.hidden)return;if(v!==undefined)value=v;}finish('confirm',value);}catch(e){Swal.showValidationMessage(e?.message||'No se pudo completar.');}finally{busy=false;if(btn)btn.disabled=false;}};
    const key=e=>{if(settled||activeDialog?.overlay!==overlay)return;if(e.key==='Escape'&&options.allowEscapeKey!==false){e.preventDefault();e.stopImmediatePropagation();finish('dismiss');return;}if(e.key==='Enter'&&!e.isComposing&&e.target===input&&input?.tagName!=='TEXTAREA'){e.preventDefault();e.stopImmediatePropagation();confirm();}if(e.key==='Tab'){const list=[...popup.querySelectorAll('button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),a[href]')].filter(x=>x.getClientRects().length&&!x.hidden);if(list.length){const first=list[0],last=list[list.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first.focus();}}}};
    activeDialog={overlay,input,validation,finish};
    overlay.querySelector('.zynko-workspace-close')?.addEventListener('click',()=>{if(!busy)finish('dismiss')});overlay.querySelector('.zynko-dialog-confirm')?.addEventListener('click',confirm);overlay.querySelector('.zynko-dialog-cancel')?.addEventListener('click',()=>finish('dismiss'));overlay.querySelector('.zynko-dialog-deny')?.addEventListener('click',()=>finish('deny',input?.value));
    document.addEventListener('keydown',key,true);
    requestAnimationFrame(()=>{if(settled)return;overlay.classList.add('open');const focus=input||content.querySelector('input:not([disabled]),textarea:not([disabled]),select:not([disabled])')||(options.focusConfirm===false?overlay.querySelector('.zynko-dialog-cancel'):overlay.querySelector('.zynko-dialog-confirm'));focus?.focus({preventScroll:true});try{options.didOpen?.(popup);window.ZynkoSearchSelects?.(popup)}catch(e){Swal.showValidationMessage(e?.message||'No se pudo abrir el formulario.');}});
    if(Number(options.timer)>0)timer=setTimeout(()=>finish('dismiss'),Number(options.timer));
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
 const EXCLUDE='[hidden],.legal-html-source,[data-no-rte],.swal2-textarea,.select2-search__field,.select2-search textarea';
 const esc=s=>String(s??'').replace(/[&<>\"]/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;'}[m]||m));
 const enhance=ta=>{
  if(!ta||ta.dataset.zynkoRteReady==='1'||ta.matches(EXCLUDE)||ta.closest('.legal-rich-editor-shell,.zynko-rte-shell,.select2-container,.select2-dropdown,.select2-search'))return;
  if(ta.type==='hidden'||getComputedStyle(ta).display==='none')return;
  ta.dataset.zynkoRteReady='1';
  const shell=document.createElement('div');shell.className='zynko-rte-shell';
  const toolbar=document.createElement('div');toolbar.className='zynko-rte-toolbar';toolbar.setAttribute('role','toolbar');toolbar.innerHTML='<select class="zynko-rte-format no-select2" aria-label="Formato"><option value="div">Párrafo</option><option value="h4">Título corto</option></select><span></span><button type="button" data-rte-cmd="bold" title="Negrita"><i class="fa-solid fa-bold"></i></button><button type="button" data-rte-cmd="italic" title="Cursiva"><i class="fa-solid fa-italic"></i></button><button type="button" data-rte-cmd="underline" title="Subrayado"><i class="fa-solid fa-underline"></i></button><span></span><button type="button" data-rte-cmd="insertUnorderedList" title="Lista"><i class="fa-solid fa-list-ul"></i></button><button type="button" data-rte-cmd="insertOrderedList" title="Lista numerada"><i class="fa-solid fa-list-ol"></i></button><button type="button" data-rte-link title="Agregar enlace"><i class="fa-solid fa-link"></i></button><span></span><button type="button" data-rte-align="left" title="Alinear izquierda"><i class="fa-solid fa-align-left"></i></button><button type="button" data-rte-align="center" title="Centrar"><i class="fa-solid fa-align-center"></i></button><button type="button" data-rte-align="justify" title="Justificar"><i class="fa-solid fa-align-justify"></i></button><button type="button" data-rte-cmd="removeFormat" title="Limpiar formato"><i class="fa-solid fa-eraser"></i></button>';
  const more=document.createElement('button');more.type='button';more.className='zynko-rte-more';more.title='Más formato';more.setAttribute('aria-label','Más formato');more.setAttribute('aria-expanded','false');more.innerHTML='<i class="fa-solid fa-ellipsis"></i>';const extras=document.createElement('div');extras.className='zynko-rte-extra';toolbar.querySelectorAll('[data-rte-cmd]:not([data-rte-cmd="bold"]):not([data-rte-cmd="italic"]):not([data-rte-cmd="underline"]),[data-rte-link],[data-rte-align]').forEach(b=>{b.classList.add('zynko-rte-secondary')});toolbar.append(more,extras);toolbar.querySelectorAll('.zynko-rte-secondary').forEach(original=>{const item=document.createElement('button');item.type='button';item.className='rte-menu-item';item.innerHTML=original.innerHTML;const label=document.createElement('span');label.textContent=original.title||original.dataset.zynkoTooltip||original.dataset.rteCmd||original.dataset.rteAlign||'Enlace';item.append(label);item.addEventListener('mousedown',e=>e.preventDefault());item.addEventListener('click',()=>{toolbar.classList.remove('rte-extra-open');more.setAttribute('aria-expanded','false');original.click()});extras.append(item)});more.addEventListener('mousedown',e=>e.preventDefault());more.addEventListener('click',()=>{const on=toolbar.classList.toggle('rte-extra-open');more.setAttribute('aria-expanded',String(on));if(on){const r=more.getBoundingClientRect();const popupHeight=Math.min(extras.scrollHeight,320,innerHeight*.6);const roomBelow=innerHeight-r.bottom-8;const roomAbove=r.top-8;const above=roomBelow<popupHeight&&roomAbove>roomBelow;extras.style.maxHeight=Math.max(80,Math.min(popupHeight,above?roomAbove:roomBelow))+'px';extras.style.left=Math.max(8,Math.min(r.right-220,innerWidth-228))+'px';extras.style.top=(above?Math.max(8,r.top-extras.getBoundingClientRect().height-6):r.bottom+6)+'px';}});toolbar.addEventListener('mousedown',e=>{if(e.target.closest('button'))e.preventDefault()});toolbar.addEventListener('click',e=>{if(e.target.closest('.zynko-rte-secondary')){toolbar.classList.remove('rte-extra-open');more.setAttribute('aria-expanded','false')}});
  const editor=document.createElement('div');editor.className='zynko-rte-editor';editor.contentEditable='true';editor.spellcheck=true;editor.setAttribute('role','textbox');editor.setAttribute('aria-multiline','true');editor.dataset.placeholder=ta.placeholder||'Escribe aquí…';
  const status=document.createElement('div');status.className='zynko-rte-status';status.innerHTML='<span><i class="fa-solid fa-pen-nib"></i> Editor de texto enriquecido</span><span class="zynko-rte-count">0 caracteres</span>';
  ta.parentNode.insertBefore(shell,ta);shell.append(toolbar,editor,status,ta);ta.classList.add('zynko-rte-native');
  const render=()=>{editor.innerHTML='';const lines=String(ta.value||'').split(/\r?\n/);lines.forEach((line,i)=>{const d=document.createElement('div');if(!line&&i===lines.length-1)d.innerHTML='<br>';else d.textContent=line;editor.appendChild(d)});updateCount()};
  const updateCount=()=>{const c=shell.querySelector('.zynko-rte-count');if(c)c.textContent=new Intl.NumberFormat('es-HN').format((ta.value||'').length)+' caracteres'};
  const sync=()=>{let val=(editor.innerText||'').replace(/\u00a0/g,' ');if(!val.trim()&&!editor.querySelector('img,video,iframe'))val='';const max=parseInt(ta.getAttribute('maxlength')||'0',10);if(max>0&&val.length>max){val=val.slice(0,max);ta.value=val;render();return}ta.value=val;ta.dispatchEvent(new Event('input',{bubbles:true}));updateCount()};
  editor.addEventListener('input',sync);toolbar.querySelectorAll('[data-rte-cmd]').forEach(b=>b.addEventListener('click',()=>{editor.focus();document.execCommand(b.dataset.rteCmd,false,null);sync()}));toolbar.querySelectorAll('[data-rte-align]').forEach(b=>b.addEventListener('click',()=>{editor.focus();document.execCommand({left:'justifyLeft',center:'justifyCenter',justify:'justifyFull'}[b.dataset.rteAlign]||'justifyLeft',false,null);sync()}));toolbar.querySelector('.zynko-rte-format')?.addEventListener('change',e=>{editor.focus();document.execCommand('formatBlock',false,e.target.value==='h4'?'H4':'DIV');sync()});toolbar.querySelector('[data-rte-link]')?.addEventListener('click',async()=>{const r=await Swal.fire({title:'Agregar enlace',input:'url',inputPlaceholder:'https://ejemplo.com',showCancelButton:true,confirmButtonText:'Agregar',cancelButtonText:'Cancelar',allowOutsideClick:false});if(r.isConfirmed&&r.value){editor.focus();document.execCommand('createLink',false,r.value);sync()}});
  ta.addEventListener('invalid',()=>{setTimeout(()=>editor.focus(),0)});ta.form?.addEventListener('submit',sync,true);render();
 };
 const scan=root=>root.querySelectorAll?.('textarea:not(.legal-html-source):not([hidden]):not([data-no-rte])').forEach(enhance);
 const start=()=>{scan(document);new MutationObserver(ms=>ms.forEach(m=>m.addedNodes.forEach(n=>{if(n.nodeType===1){if(n.matches?.('textarea'))enhance(n);scan(n)}}))).observe(document.body,{childList:true,subtree:true})};
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start);else start();
})();


/* Select2 de formato: aislado de los selectores de formularios y compartido por todos los editores. */
(()=>{
 const selector='.zynko-rte-toolbar select.zynko-rte-format,.legal-editor-toolbar select.legal-format-select,.quick-rte-toolbar select';
 const enhance=select=>{
  if(!window.jQuery||!jQuery.fn.select2||select.dataset.rteSelectReady==='1')return;
  const toolbar=select.closest('.zynko-rte-toolbar,.legal-editor-toolbar,.quick-rte-toolbar');
  const shell=toolbar?.closest('.zynko-rte-shell,.legal-rich-editor-shell');
  const editor=shell?.querySelector('[contenteditable="true"]');
  if(!toolbar||!editor)return;
  const $select=jQuery(select);
  if($select.hasClass('select2-hidden-accessible'))$select.select2('destroy');
  select.dataset.rteSelectReady='1';
  select.classList.add('no-select2');
  const wrapper=document.createElement('div');wrapper.className='zynko-rte-format-control';
  select.before(wrapper);wrapper.append(select);
  let range=null;
  const remember=()=>{const selection=window.getSelection();if(selection?.rangeCount){const current=selection.getRangeAt(0);if(editor.contains(current.startContainer)&&editor.contains(current.endContainer))range=current.cloneRange();}};
  const restore=()=>{editor.focus({preventScroll:true});if(range&&editor.contains(range.startContainer)&&editor.contains(range.endContainer)){const selection=window.getSelection();selection.removeAllRanges();selection.addRange(range);}};
  editor.addEventListener('keyup',remember);editor.addEventListener('mouseup',remember);editor.addEventListener('input',remember);editor.addEventListener('focusout',remember);
  wrapper.addEventListener('pointerdown',remember,true);
  const parent=toolbar.closest('.modal-shell,.zynko-dialog-overlay')||document.body;
  $select.select2({width:'100%',minimumResultsForSearch:0,dropdownAutoWidth:false,dropdownParent:jQuery(parent)});
  const instance=$select.data('select2');
  instance?.$dropdownContainer?.addClass('zynko-rte-format-dropdown');
  instance?.$dropdown?.addClass('zynko-rte-format-dropdown-inner');
  instance?.$selection?.attr('aria-label',select.getAttribute('aria-label')||'Formato');
  $select.on('select2:opening.zynkoRte',remember);
  $select.on('select2:select.zynkoRte',()=>{restore();select.dispatchEvent(new Event('change',{bubbles:true}));remember();});
 };
 const scan=root=>{if(root.matches?.(selector))enhance(root);root.querySelectorAll?.(selector).forEach(enhance);};
 const start=()=>{scan(document);new MutationObserver(records=>records.forEach(record=>record.addedNodes.forEach(node=>{if(node.nodeType===1)scan(node);}))).observe(document.body,{childList:true,subtree:true});};
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

    // Las cabeceras que ya renderizan su propio icono no deben recibir otro
    // icono desde el mejorador universal. Esto evita duplicados visuales.
    if(copy.querySelector(':scope > .env-panel-icon, :scope > .premium-card-head-icon, :scope > .zynko-card-head-icon')){
      head.dataset.zynkoCardHead='1';
      return;
    }
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
