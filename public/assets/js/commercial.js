/* Commercial Phase 2. Delegated listeners limited to zc-* controls. */
(()=>{
 'use strict';
 const q=(s,c=document)=>c.querySelector(s),qa=(s,c=document)=>Array.from(c.querySelectorAll(s));
 const en=()=>document.documentElement.lang==='en';
 const tr=(es,english)=>en()?english:es;
 const notify=(type,title,message)=>{if(typeof window.showNotify==='function')window.showNotify(type,title,message);else console.warn(title,message);};
 const enhance=d=>{window.ZynkoSearchSelects?.(d);if(window.jQuery?.fn?.select2){qa('select.zc-select',d).forEach(el=>{const j=window.jQuery(el);if(!j.hasClass('select2-hidden-accessible')){j.select2({dropdownParent:window.jQuery(d),width:'100%',minimumResultsForSearch:0});j.on('select2:select',()=>el.dispatchEvent(new Event('change',{bubbles:true})));}});}};
 const open=(id,trigger)=>{const d=document.getElementById(id);if(!(d instanceof HTMLDialogElement)||d.open)return false;d.showModal();d._zcTrigger=trigger;enhance(d);return true;};
 document.addEventListener('click',e=>{
  const hide=e.target.closest?.('[data-zc-hide-comparison]');if(hide){const details=hide.closest('.zc-compare details');if(details){details.open=false;const summary=q('summary',details);summary?.focus({preventScroll:true});summary?.scrollIntoView({block:'center',behavior:'instant'});}return;}
  const b=e.target.closest?.('[data-zc-open]');if(b){e.preventDefault();open(b.dataset.zcOpen,b);return;}
  const close=e.target.closest?.('[data-zc-close]');if(close){const d=close.closest('dialog.zc-dialog');if(d?.open)d.close();return;}
  // Direct connection button: hand-off to the existing production Meta flow (no synthetic connection).
  const launch=e.target.closest?.('[data-zc-meta]');if(launch){const f=launch.closest('form'),selected=f?.querySelector('[name=method]:checked')?.value;if(!selected||selected==='help'){notify('info',tr('Selecciona el tipo de conexión','Choose a connection type'),tr('Guarda tu solicitud de asistencia o elige número existente/nuevo.','Save your assistance request or choose existing/new number.'));return;}
   const mode=selected==='existing'?'coexistence':'cloud';const original=q('#metaSignupPilot [data-meta-pilot="'+mode+'"][data-production="1"]');if(!original||original.disabled){notify('info',tr('Autorización pendiente','Authorization pending'),tr('La conexión comercial aún no está habilitada. Guarda el avance y solicita asistencia.','Commercial connection is not enabled yet. Save your progress and request assistance.'));return;}launch.closest('dialog')?.close();original.click();return;}
 });
 document.addEventListener('close',e=>{if(e.target.matches?.('dialog.zc-dialog')){const trigger=e.target._zcTrigger;if(trigger?.isConnected)trigger.focus({preventScroll:true});}},true);
 const sync=()=>{qa('[data-zc-progress-form]').forEach(form=>{
   const boxes=qa('input[name="progress[]"]',form),checked=boxes.filter(x=>x.checked).length,bar=q('[data-zc-progress]',form),label=q('[data-zc-progress-label]',form);if(bar)bar.style.width=(boxes.length?100*checked/boxes.length:0)+'%';if(label)label.textContent=checked+'/'+boxes.length+' '+tr('pasos declarados','reported steps');
   const method=q('input[name="method"]:checked',form)?.value||'';qa('[data-zc-method]',form).forEach(x=>x.hidden=x.dataset.zcMethod!==method);
   const btn=q('[data-zc-meta]',form);if(btn){btn.disabled=method==='help'||btn.dataset.ready!=='1';}
  });qa('[data-zc-pricing-form]').forEach(form=>{const mode=q('[name=pricing_mode]',form)?.value,field=q('[name=price]',form);if(field){field.disabled=mode!=='fixed';field.required=mode==='fixed';}});};
 document.addEventListener('change',e=>{if(e.target.closest?.('[data-zc-progress-form],[data-zc-pricing-form]'))sync();});
 // Activation forms stay open; refresh only the server-rendered tracking content.
 let activationBusy=false;
 const destroySelects=node=>{if(window.jQuery?.fn?.select2)qa('select.select2-hidden-accessible',node).forEach(el=>window.jQuery(el).select2('destroy'));};
 const refreshActivation=async(url)=>{
   const res=await fetch(url,{credentials:'same-origin',cache:'no-store'});if(!res.ok)throw new Error('HTTP '+res.status);
   const doc=new DOMParser().parseFromString(await res.text(),'text/html'),center=q('#zc-activation-center'),fresh=q('#zc-activation-center',doc);
   if(!center||!fresh)throw new Error('Activation view unavailable');
   const body=q('.zc-dialog-body',center),replacement=q('.zc-dialog-body',fresh),pos=body.scrollTop;
   destroySelects(body);body.replaceChildren(...replacement.childNodes);enhance(center);body.scrollTop=pos;
   const current=qa('dialog[id^="zc-track-"],#zc-activation-wizard');
   const incoming=qa('dialog[id^="zc-track-"],#zc-activation-wizard',doc);
   for(const d of current){const next=incoming.find(x=>x.id===d.id);if(d.open){const history=q('[data-zc-track-history]',d),updated=next&&q('[data-zc-track-history]',next);if(history&&updated)history.replaceChildren(...updated.childNodes);continue;}destroySelects(d);if(next)d.replaceWith(next);else d.remove();}
   for(const d of incoming)if(!document.getElementById(d.id))center.after(d);
   sync();
 };
 let trackingUrl=location.href;
 document.addEventListener('submit',async e=>{
   const form=e.target,action=form.elements?.action?.value,filter=form.matches?.('[data-zc-activation-filter]');
   if(!filter&&!['activation_save','activation_update'].includes(action))return;
   e.preventDefault();if(activationBusy||!form.reportValidity())return;activationBusy=true;
   const data=new FormData(form),buttons=qa('button:not([type]),button[type="submit"]',form);buttons.forEach(b=>b.disabled=true);
   let saved=false;
   try{
     if(filter){const url=new URL(location.href);url.search=new URLSearchParams(data).toString();url.hash='';await refreshActivation(url);trackingUrl=url.href;return;}
     const res=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},credentials:'same-origin',body:data});const result=await res.json();
     if(!result.ok){notify('error',tr('No se pudo guardar','Unable to save'),result.message);return;}saved=true;
     if(result.data?.request_id&&form.elements.request_id){form.elements.request_id.value=String(result.data.request_id);const meta=q('[data-zc-meta]',form);if(meta){meta.dataset.ready='1';meta.hidden=false;}}
     if(form.elements.note)form.elements.note.value='';sync();notify('success',tr('Cambios guardados','Changes saved'),result.message);
     await refreshActivation(trackingUrl);
   }catch(err){notify(saved?'warning':'error',saved?tr('Cambios guardados','Changes saved'):tr('No se pudo completar','Unable to complete'),saved?tr('El seguimiento no pudo actualizarse. Usa Filtrar para actualizarlo.','Tracking could not refresh. Use Filter to refresh it.'):tr('Comprueba tu conexión e inténtalo nuevamente.','Check your connection and try again.'));}
   finally{activationBusy=false;buttons.forEach(b=>b.disabled=false);}
 });
 document.addEventListener('click',async e=>{const link=e.target.closest?.('[data-zc-activation-nav]');if(!link)return;e.preventDefault();if(activationBusy)return;activationBusy=true;try{await refreshActivation(link.href);trackingUrl=link.href;}catch(err){notify('error',tr('No se pudo actualizar','Unable to refresh'),tr('Intenta nuevamente.','Try again.'));}finally{activationBusy=false;}});
 const center=q('#zc-activation-center[data-zc-reopen="1"]');if(center)open(center.id,q('[data-zc-open="zc-activation-center"]'));
 const request=q('#zcPlanRequestForm');
 if(request){qa('.plan-request[data-plan-revision]').forEach(btn=>btn.addEventListener('click',()=>{
  request.reset();q('[data-zc-contract-error]',request).textContent='';request.elements.plan_id.value=btn.dataset.id;request.elements.plan_revision.value=btn.dataset.planRevision;
  const title=q('[data-zc-contract-title]'),text=q('[data-zc-contract-price]');if(title)title.textContent=tr('Solicitar ','Request ')+btn.dataset.name;if(text)text.textContent=btn.dataset.price+tr(' /mes',' /month');
  open('zcPlanRequestDialog',btn);
 }));request.addEventListener('submit',async e=>{e.preventDefault();if(!request.reportValidity())return;const button=q('[type=submit]',request);if(button?.disabled)return;button.disabled=true;try{const res=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},credentials:'same-origin',body:new FormData(request)}),data=await res.json();notify(data.ok?'success':'error',data.ok?tr('Solicitud enviada','Request sent'):tr('No se pudo enviar','Request failed'),data.message);if(!data.ok)q('[data-zc-contract-error]',request).textContent=data.message||tr('Error de solicitud','Request failed');if(data.ok){q('#zcPlanRequestDialog')?.close();location.reload();}}catch(err){notify('error',tr('No se pudo enviar','Request failed'),tr('Comprueba tu conexión e inténtalo nuevamente.','Check your connection and try again.'));}finally{button.disabled=false;}});}
 qa('[data-zc-filter]').forEach(input=>input.addEventListener('input',()=>{const scope=document.getElementById(input.dataset.zcFilter);if(!scope)return;const value=input.value.trim().toLowerCase();qa('[data-zc-search]',scope).forEach(x=>x.hidden=!!value&&!x.dataset.zcSearch.toLowerCase().includes(value));}));
 qa('[data-zc-estimate]').forEach(el=>el.addEventListener('input',()=>{const count=Math.max(0,Number(q('[data-zc-count]',el).value)||0),rate=Math.max(0,Number(q('[data-zc-rate]',el).value)||0);q('[data-zc-total]',el).textContent=(count*rate).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2});}));
 const flash=q('[data-zc-flash]');if(flash)notify(flash.dataset.zcFlash==='success'?'success':'error',tr('Gestión comercial','Commercial management'),flash.textContent.trim());
 sync();
})();
