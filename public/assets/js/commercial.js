/* Commercial Phase 2. Delegated listeners limited to zc-* controls. */
(()=>{
 'use strict';
 const q=(s,c=document)=>c.querySelector(s),qa=(s,c=document)=>Array.from(c.querySelectorAll(s));
 const en=()=>document.documentElement.lang==='en';
 const tr=(es,english)=>en()?english:es;
 const notify=(type,title,message)=>{if(typeof window.showNotify==='function')window.showNotify(type,title,message);else console.warn(title,message);};
 const open=(id,trigger)=>{const d=document.getElementById(id);if(!(d instanceof HTMLDialogElement)||d.open)return false;d.showModal();d._zcTrigger=trigger;if(window.jQuery?.fn?.select2){qa('select.zc-select',d).forEach(el=>{const j=window.jQuery(el);if(!j.hasClass('select2-hidden-accessible')){j.select2({dropdownParent:window.jQuery(d),width:'100%',minimumResultsForSearch:0});j.on('select2:select',()=>el.dispatchEvent(new Event('change',{bubbles:true})));}});}return true;};
 document.addEventListener('click',e=>{
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
