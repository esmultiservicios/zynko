const app=document.querySelector('.app');
document.querySelector('#menu')?.addEventListener('click',()=>app.classList.toggle('menu-open'));
const applyZynkoTheme=(theme)=>{const t=theme||'system';const dark=t==='dark'||(t==='system'&&window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches);document.documentElement.classList.toggle('theme-dark',dark);document.body.classList.toggle('dark',dark);localStorage.setItem('zynko.theme',t);localStorage.setItem('theme',dark?'dark':'light');};
applyZynkoTheme(window.ZYNKO_THEME||localStorage.getItem('zynko.theme')||'system');
document.querySelector('#theme')?.addEventListener('click',async()=>{const current=localStorage.getItem('zynko.theme')||window.ZYNKO_THEME||'system';const next=current==='dark'?'light':'dark';applyZynkoTheme(next);try{const fd=new FormData();fd.append('action','theme_preference');fd.append('theme',next);await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd});}catch(_){}});
if(window.matchMedia){matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change',()=>{if((localStorage.getItem('zynko.theme')||'system')==='system')applyZynkoTheme('system')});}
const sm=document.querySelector('#searchModal'); const openSearch=()=>sm?.classList.add('open'); const closeSearch=()=>sm?.classList.remove('open');
document.querySelector('#globalSearch')?.addEventListener('click',openSearch); document.querySelector('#closeSearch')?.addEventListener('click',closeSearch);
document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();openSearch()} if(e.key==='Escape')closeSearch()});
const fly=document.querySelector('#flyout'); document.querySelector('aside')?.addEventListener('mouseleave',()=>setTimeout(()=>{if(!fly?.matches(':hover'))fly?.classList.remove('open')},120)); fly?.addEventListener('mouseleave',()=>fly.classList.remove('open'));
document.querySelector('#language')?.addEventListener('change',e=>{location.href='?action=lang&lang='+encodeURIComponent(e.target.value)});
if(window.jQuery&&jQuery.fn.select2){jQuery('.select2').select2({width:'100%',minimumResultsForSearch:6});}
document.querySelectorAll('.upload-zone').forEach(zone=>{const input=zone.querySelector('input[type=file]'); if(!input)return; zone.addEventListener('click',e=>{if(e.target!==input)input.click()}); zone.addEventListener('dragover',e=>{e.preventDefault();zone.classList.add('drag')}); zone.addEventListener('dragleave',()=>zone.classList.remove('drag')); zone.addEventListener('drop',e=>{e.preventDefault();zone.classList.remove('drag');if(e.dataTransfer.files.length){input.files=e.dataTransfer.files;zone.querySelector('small').textContent=e.dataTransfer.files[0].name}}); zone.addEventListener('paste',e=>{const f=[...e.clipboardData.files];if(f.length){const dt=new DataTransfer();f.forEach(x=>dt.items.add(x));input.files=dt.files;zone.querySelector('small').textContent=f[0].name}}); input.addEventListener('change',()=>{if(input.files[0])zone.querySelector('small').textContent=input.files[0].name});});
// ZYNKO navigation preference: double click the menu button to hide/show the sidebar completely.
(()=>{const b=document.getElementById('menu');if(!b)return;const key='zynko.sidebar.hidden';const apply=()=>document.body.classList.toggle('sidebar-hidden',localStorage.getItem(key)==='1');apply();b.addEventListener('dblclick',e=>{e.preventDefault();localStorage.setItem(key,document.body.classList.contains('sidebar-hidden')?'0':'1');apply();});})();

// ZYNKO Realtime: authenticated WebSocket with automatic reconnection.
(()=>{
  const cfg=window.ZYNKO_REALTIME;if(!cfg?.url||!cfg?.token)return;
  let ws=null,retry=1000,stopped=false;
  const emit=(name,detail)=>document.dispatchEvent(new CustomEvent(name,{detail}));
  const connect=()=>{if(stopped)return;try{ws=new WebSocket(cfg.url+'/?token='+encodeURIComponent(cfg.token));}catch(e){return schedule();}
    ws.addEventListener('open',()=>{retry=1000;emit('zynko:realtime-status',{connected:true});});
    ws.addEventListener('message',e=>{try{const m=JSON.parse(e.data);if(m.type==='event')emit('zynko:realtime',m);else emit('zynko:realtime-message',m);}catch(_){}});
    ws.addEventListener('close',()=>{emit('zynko:realtime-status',{connected:false});schedule();});
    ws.addEventListener('error',()=>{try{ws.close()}catch(_){}});
  };
  const schedule=()=>{if(stopped)return;setTimeout(connect,retry);retry=Math.min(retry*2,15000);};
  document.addEventListener('visibilitychange',()=>{if(!document.hidden&&(!ws||ws.readyState>1))connect();});
  window.addEventListener('beforeunload',()=>{stopped=true;try{ws?.close()}catch(_){}});
  connect();
})();

// ZYNKO premium shell + functional controls
(()=>{
 const $=(s,c=document)=>c.querySelector(s), $$=(s,c=document)=>[...c.querySelectorAll(s)];
 const post=async(form)=>{const fd=form instanceof FormData?form:new FormData(form);const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd});return r.json();};
 const bindAjax=(sel,after)=>$$(sel).forEach(f=>f.addEventListener('submit',async e=>{e.preventDefault();const btn=$('button[type=submit],button:not([type])',f);if(btn){btn.disabled=true;btn.dataset.old=btn.innerHTML;btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Guardando…'}try{const j=await post(f);showNotify(j.ok?'success':'error',j.ok?'Listo':'No se pudo completar',j.message);if(j.ok&&after)after(j,f);}catch(x){showNotify('error','Error','No fue posible procesar la solicitud.');}finally{if(btn){btn.disabled=false;btn.innerHTML=btn.dataset.old||'Guardar'}}}));
 // sidebar collapse + mobile
 const collapse=$('#sideCollapse'); if(localStorage.getItem('zynko.sidebar.collapsed')==='1')document.body.classList.add('sidebar-collapsed');collapse?.addEventListener('click',()=>{document.body.classList.toggle('sidebar-collapsed');localStorage.setItem('zynko.sidebar.collapsed',document.body.classList.contains('sidebar-collapsed')?'1':'0')});
 // real flyouts only where real options exist
 const fly=$('#flyout'); $$('aside nav a[data-menu]').forEach(a=>{a.onmouseenter=()=>{if(innerWidth<=760)return;const list=window.ZYNKO_MENUS?.[a.dataset.menu]||[];if(!list.length){fly.classList.remove('open');return;}fly.innerHTML='<small>ACCESOS</small><b>'+a.querySelector('b').textContent+'</b>'+list.map(x=>`<a href="${x[0]}"><i class="fa-solid ${x[2]}"></i>${x[1]}</a>`).join('');const r=a.getBoundingClientRect();fly.style.top=Math.min(r.top,innerHeight-fly.offsetHeight-20)+'px';fly.classList.add('open')};});
 // user dropdown/logout with confirmation
 $('#userMenuBtn')?.addEventListener('click',e=>{e.stopPropagation();$('#userDropdown')?.classList.toggle('open')});document.addEventListener('click',()=>$('#userDropdown')?.classList.remove('open'));
 $('#logoutBtn')?.addEventListener('click',async()=>{const ok=await Swal.fire({title:'Cerrar sesión',text:'¿Deseas salir de ZYNKO?',icon:'question',showCancelButton:true,confirmButtonText:'Sí, cerrar sesión',confirmButtonIcon:'fa-solid fa-right-from-bracket',cancelButtonText:'Cancelar',cancelButtonIcon:'fa-solid fa-xmark',allowOutsideClick:false});if(ok.isConfirmed)location.href='?page=logout'});
 // modals
 const openModal=id=>{const m=document.getElementById(id);if(m){m.classList.add('open');m.setAttribute('aria-hidden','false');setTimeout(()=>m.querySelector('input:not([type=hidden]),select,textarea')?.focus(),80)}}; const closeModal=m=>{m.closest('.modal-shell')?.classList.remove('open')};
 $$('[data-open]').forEach(b=>b.addEventListener('click',()=>openModal(b.dataset.open)));$$('.modal-close').forEach(b=>b.addEventListener('click',()=>closeModal(b)));$$('.modal-shell').forEach(m=>m.addEventListener('click',e=>{if(e.target===m){} }));
 const channelNames={whatsapp:'WhatsApp Business',messenger:'Messenger',instagram:'Instagram Messaging'};
 const openChannelConfig=type=>{if(!channelNames[type])return;$('#channelType').value=type;$('#channelModalTitle').textContent='Configurar '+channelNames[type];document.getElementById('channelCatalogModal')?.classList.remove('open');openModal('channelModal')};
 $('#openChannelCatalog')?.addEventListener('click',()=>openModal('channelCatalogModal'));
 $$('.channel-config,.channel-add').forEach(b=>b.addEventListener('click',()=>openChannelConfig(b.dataset.channel)));
 $$('.channel-catalog-item[data-channel]').forEach(b=>b.addEventListener('click',()=>openChannelConfig(b.dataset.channel)));
 bindAjax('#channelForm',()=>setTimeout(()=>location.reload(),700)); bindAjax('#userForm',()=>setTimeout(()=>location.reload(),700)); bindAjax('#settingsForm',(j,f)=>{const t=f.querySelector('[name=theme]')?.value||'system';applyZynkoTheme(t);setTimeout(()=>location.reload(),700)}); bindAjax('#botForm',()=>setTimeout(()=>location.reload(),700)); bindAjax('#assignForm',()=>document.querySelector('#assignModal')?.classList.remove('open'));
 // search clear behavior
 $$('.list-search').forEach(inp=>{const wrap=inp.closest('.search-wrap')||inp.parentElement,clear=wrap?.querySelector('.clear-search');const run=()=>{const q=inp.value.trim().toLowerCase();if(clear)clear.hidden=!q;const root=inp.closest('.panel')||document;$$('.searchable',root).forEach(x=>x.hidden=q&&!x.textContent.toLowerCase().includes(q));};inp.addEventListener('input',run);clear?.addEventListener('click',()=>{inp.value='';run();inp.focus()})});
 // inbox rich composer
 $('#emojiBtn')?.addEventListener('click',()=>{const p=$('#emojiPicker');p.hidden=!p.hidden});$('#emojiPicker')?.addEventListener('click',e=>{if(e.target===e.currentTarget){const sel=getSelection()?.toString();} const ch=e.target.textContent?.trim();});
 if($('#emojiPicker')){$('#emojiPicker').innerHTML=$('#emojiPicker').textContent.trim().split(/\s+/).map(e=>`<button type="button">${e}</button>`).join('');$$('#emojiPicker button').forEach(b=>b.onclick=()=>{$('#messageInput').value+=b.textContent;$('#messageInput').focus()})}
 $('#attachBtn')?.addEventListener('click',()=>$('#chatFile')?.click());$('#chatFile')?.addEventListener('change',e=>{$('#attachmentPreview').innerHTML=[...e.target.files].map(f=>`<span><i class="fa-solid fa-paperclip"></i>${f.name}</span>`).join('')});
 $('#nivoAssist')?.addEventListener('click',async()=>{const i=$('#messageInput');if(!i)return;const last=[...document.querySelectorAll('#messages p:not(.me)')].pop()?.textContent?.trim()||i.value.trim();if(!last){showNotify('warning','NIVO necesita contexto','Selecciona una conversación con un mensaje del cliente.');return;}const fd=new FormData();fd.append('action','nivo_suggest');fd.append('query',last);const j=await post(fd);if(j.ok){i.value=j.data.suggestion||'';showNotify('info','Sugerencia de NIVO',(j.data.source?'Fuente: '+j.data.source+'. ':'')+'Confianza: '+(j.data.confidence||'n/a')+'. Revisa antes de enviar.');i.focus();}else showNotify('error','NIVO',j.message);});
 $('#messageForm')?.addEventListener('submit',async e=>{e.preventDefault();const i=$('#messageInput'),body=i.value.trim();if(!body&&!($('#chatFile')?.files?.length)){showNotify('warning','Mensaje vacío','Escribe un mensaje o adjunta un archivo antes de enviar.');return;}const fd=new FormData();fd.append('action','message_send');fd.append('conversation_id',$('#messageForm [name=conversation_id]')?.value||'');fd.append('body',body);[...($('#chatFile')?.files||[])].forEach(f=>fd.append('attachments[]',f));const j=await post(fd);if(j.ok){const m=document.createElement('p');m.className='me';m.textContent=body;$('#messages').append(m);i.value='';$('#attachmentPreview').innerHTML='';$('#chatFile').value='';$('#messages').scrollTop=$('#messages').scrollHeight;showNotify('success','Mensaje listo',j.message);}else showNotify('error','No se pudo enviar',j.message)});
})();


// ZYNKO final functional controls
(()=>{const $=(s,c=document)=>c.querySelector(s),$$=(s,c=document)=>[...c.querySelectorAll(s)];const post=async f=>{const fd=f instanceof FormData?f:new FormData(f);const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd});return r.json()};const bind=(id,reload=true)=>$(id)?.addEventListener('submit',async e=>{e.preventDefault();const j=await post(e.currentTarget);showNotify(j.ok?'success':'error',j.message,j.ok?'Listo':'Error');if(j.ok&&reload)setTimeout(()=>location.reload(),500)});
$$('.subscriptionAssignForm').forEach(f=>f.addEventListener('submit',async e=>{e.preventDefault();const j=await post(e.currentTarget);showNotify(j.ok?'success':'error',j.message,j.ok?'Plan asignado':'Error');if(j.ok)setTimeout(()=>location.reload(),450)}));
$('#apiKeyForm')?.addEventListener('submit',async e=>{e.preventDefault();const j=await post(e.currentTarget);if(j.ok){await Swal.fire({title:'Clave API creada',text:j.data.api_key+' — cópiala ahora y guárdala de forma segura.',icon:'success',confirmButtonText:'Entendido',allowOutsideClick:false});location.reload();}else showNotify('error','Error',j.message)});
$$('.api-key-revoke').forEach(b=>b.addEventListener('click',async()=>{const r=await Swal.fire({title:'Revocar clave API',text:'El sistema externo dejará de poder autenticarse inmediatamente.',icon:'warning',showCancelButton:true,confirmButtonText:'Sí, revocar',cancelButtonText:'Cancelar',allowOutsideClick:false});if(!r.isConfirmed)return;const fd=new FormData();fd.append('action','api_key_revoke');fd.append('api_key_id',b.dataset.id);const j=await post(fd);showNotify(j.ok?'success':'error',j.message,j.ok?'Clave revocada':'Error');if(j.ok)setTimeout(()=>location.reload(),450)}));
$('#helpBtn')?.addEventListener('click',()=>Swal.fire({title:'Ayuda de este módulo',text:'Usa el menú lateral para navegar. Los botones con iconos ejecutan acciones reales; si una integración externa requiere autorización, ZYNKO mostrará el estado pendiente en lugar de simularla.',icon:'info',confirmButtonText:'Entendido'}));
bind('#integrationForm');bind('#userEditForm');bind('#avatarForm');bind('#knowledgeForm');
$$('.user-actions').forEach(b=>b.addEventListener('click',()=>{const u=JSON.parse(b.dataset.user);const m=$('#userEditModal');m.querySelectorAll('[name=user_id]').forEach(x=>x.value=u.id);m.querySelector('[name=name]').value=u.name;m.querySelector('[name=role]').value=u.role_code;m.querySelector('[name=status]').value=u.status;if(window.jQuery)jQuery(m).find('.select2').trigger('change');m.classList.add('open')}));
})();

// ZYNKO final interaction pass: fullscreen, plans and new conversations.
(()=>{const $=(s,c=document)=>c.querySelector(s),$$=(s,c=document)=>[...c.querySelectorAll(s)];
 const ajax=async fd=>{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd});return r.json()};
 // Pantalla completa persistente: el documento anfitrión permanece en fullscreen y
 // la navegación interna ocurre dentro de un frame del mismo origen. Así cambiar de
 // Dashboard a Bandeja/Canales/etc. no saca al usuario de pantalla completa.
 const fsKey='zynko.fullscreen.persistent';
 const inFsFrame=()=>window.self!==window.top&&window.frameElement?.id==='zynkoFullscreenFrame';
 const topDoc=()=>{try{return window.top.document}catch(_){return document}};
 const syncFsIcon=()=>{const i=$('#fullscreenBtn i');if(!i)return;let active=false;try{active=!!topDoc().fullscreenElement||inFsFrame()}catch(_){}i.className=active?'fa-solid fa-compress':'fa-solid fa-expand';};
 const buildFsFrame=(href)=>{
   let frame=document.getElementById('zynkoFullscreenFrame');
   if(!frame){frame=document.createElement('iframe');frame.id='zynkoFullscreenFrame';frame.title='ZYNKO · Pantalla completa';frame.setAttribute('allow','fullscreen');Object.assign(frame.style,{position:'fixed',inset:'0',width:'100%',height:'100%',border:'0',background:'#fff',zIndex:'2147483646'});document.body.appendChild(frame);}
   frame.src=href||location.href;return frame;
 };
 const enterPersistentFullscreen=async()=>{
   if(inFsFrame()){try{localStorage.setItem(fsKey,'1');const td=topDoc();if(!td.fullscreenElement)await td.documentElement.requestFullscreen();}catch(_){}syncFsIcon();return;}
   try{localStorage.setItem(fsKey,'1');if(!document.fullscreenElement)await document.documentElement.requestFullscreen();buildFsFrame(location.href);}catch(e){localStorage.removeItem(fsKey);showNotify('error','No se pudo cambiar la vista','El navegador bloqueó el modo de pantalla completa.');}
 };
 const exitPersistentFullscreen=async()=>{
   localStorage.removeItem(fsKey);
   if(inFsFrame()){try{const td=topDoc();td.getElementById('zynkoFullscreenFrame')?.remove();if(td.fullscreenElement)await td.exitFullscreen();}catch(_){}return;}
   document.getElementById('zynkoFullscreenFrame')?.remove();try{if(document.fullscreenElement)await document.exitFullscreen();}catch(_){}syncFsIcon();
 };
 $('#fullscreenBtn')?.addEventListener('click',async()=>{let active=false;try{active=!!topDoc().fullscreenElement||inFsFrame()}catch(_){}if(active)await exitPersistentFullscreen();else await enterPersistentFullscreen();});
 document.addEventListener('fullscreenchange',()=>{if(!document.fullscreenElement&&!inFsFrame()){localStorage.removeItem(fsKey);document.getElementById('zynkoFullscreenFrame')?.remove();}syncFsIcon();});
 syncFsIcon();
 $$('.plan-edit').forEach(b=>b.addEventListener('click',()=>{const p=JSON.parse(b.dataset.plan),m=$('#planModal'),f=$('#planForm');f.plan_id.value=p.id;f.name.value=p.name;f.monthly_price.value=p.monthly_price;f.currency.value=p.currency;f.max_users.value=p.max_users||0;f.max_channels.value=p.max_channels||0;f.features.value=(JSON.parse(p.features_json||'[]')||[]).join('\n');f.active.checked=String(p.active)==='1';$('#planTitle').textContent='Editar plan';m.classList.add('open');if(window.jQuery)jQuery(f).find('.select2').trigger('change')}));
 $('#planForm')?.addEventListener('submit',async e=>{e.preventDefault();const j=await ajax(new FormData(e.currentTarget));showNotify(j.ok?'success':'error',j.message,j.ok?'Plan guardado':'Error');if(j.ok)setTimeout(()=>location.reload(),450)});
 $$('.plan-delete').forEach(b=>b.addEventListener('click',async()=>{const r=await Swal.fire({title:'Eliminar plan',text:`¿Eliminar “${b.dataset.name}”? Esta acción no se puede deshacer.`,icon:'warning',showCancelButton:true,confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar',allowOutsideClick:false});if(!r.isConfirmed)return;const fd=new FormData();fd.append('action','plan_delete');fd.append('plan_id',b.dataset.id);const j=await ajax(fd);showNotify(j.ok?'success':'error',j.message,j.ok?'Plan eliminado':'Error');if(j.ok)setTimeout(()=>location.reload(),450)}));
 $('#conversationCreateForm')?.addEventListener('submit',async e=>{e.preventDefault();const j=await ajax(new FormData(e.currentTarget));showNotify(j.ok?'success':'error',j.message,j.ok?'Conversación creada':'Error');if(j.ok)location.href='?page=inbox&conversation='+encodeURIComponent(j.data.conversation_id)});
 // Ensure conversation id is always posted by the rich composer.
 const mf=$('#messageForm');if(mf&&!mf.dataset.cidFix){mf.dataset.cidFix='1';mf.addEventListener('submit',()=>{},true)}
})();
(()=>{const $$=(s,c=document)=>[...c.querySelectorAll(s)];const send=async fd=>{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd});return r.json()};
 $$('.email-activate').forEach(b=>b.onclick=async()=>{const fd=new FormData();fd.append('action','email_activate');fd.append('correo_id',b.dataset.id);const j=await send(fd);showNotify(j.ok?'success':'error',j.message,j.ok?'Proveedor activado':'Error');if(j.ok)setTimeout(()=>location.reload(),450)});
 $$('.email-delete').forEach(b=>b.onclick=async()=>{const r=await Swal.fire({title:'Eliminar proveedor',text:'¿Deseas eliminar esta configuración de correo?',icon:'warning',showCancelButton:true,confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar',allowOutsideClick:false});if(!r.isConfirmed)return;const fd=new FormData();fd.append('action','email_delete');fd.append('correo_id',b.dataset.id);const j=await send(fd);showNotify(j.ok?'success':'error',j.message,j.ok?'Proveedor eliminado':'Error');if(j.ok)setTimeout(()=>location.reload(),450)});
})();
(()=>{const $$=(s,c=document)=>[...c.querySelectorAll(s)];const send=async fd=>{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd});return r.json()};$$('.knowledge-delete').forEach(b=>b.addEventListener('click',async()=>{const r=await Swal.fire({title:'Eliminar conocimiento',text:'NIVO dejará de utilizar esta fuente inmediatamente.',icon:'warning',showCancelButton:true,confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar',allowOutsideClick:false});if(!r.isConfirmed)return;const fd=new FormData();fd.append('action','knowledge_delete');fd.append('knowledge_id',b.dataset.id);const j=await send(fd);showNotify(j.ok?'success':'error',j.message,j.ok?'Fuente eliminada':'Error');if(j.ok)setTimeout(()=>location.reload(),400)}));})();

// NIVO local intelligence controls
(()=>{
 const q=(s,c=document)=>c.querySelector(s), qa=(s,c=document)=>[...c.querySelectorAll(s)];
 const send=async fd=>{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd});return r.json()};
 const ajaxForm=(id,done)=>q(id)?.addEventListener('submit',async e=>{e.preventDefault();const f=e.currentTarget,b=f.querySelector('button[type=submit],button:not([type])');if(b)b.disabled=true;try{const j=await send(new FormData(f));showNotify(j.ok?'success':'error',j.ok?'Listo':'Error',j.message);if(j.ok)done?.();}catch(_){showNotify('error','Error','No se pudo procesar la solicitud.');}finally{if(b)b.disabled=false}});
 ajaxForm('#ruleForm',()=>setTimeout(()=>location.reload(),350));
 qa('.rule-delete').forEach(b=>b.addEventListener('click',async()=>{const r=await Swal.fire({title:'Eliminar regla',text:'NIVO dejará de utilizar esta regla inmediatamente.',icon:'warning',showCancelButton:true,confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar',allowOutsideClick:false});if(!r.isConfirmed)return;const fd=new FormData();fd.append('action','nivo_rule_delete');fd.append('rule_id',b.dataset.id);const j=await send(fd);showNotify(j.ok?'success':'error',j.ok?'Regla eliminada':'Error',j.message);if(j.ok)setTimeout(()=>location.reload(),350)}));
 q('#knowledgeFile')?.addEventListener('change',async e=>{const file=e.target.files?.[0];if(!file)return;const fd=new FormData();fd.append('action','knowledge_file_add');fd.append('file',file);showNotify('info','Importando conocimiento','Validando y agregando '+file.name+'…');const j=await send(fd);showNotify(j.ok?'success':'error',j.ok?'Conocimiento agregado':'No se pudo importar',j.message);if(j.ok)setTimeout(()=>location.reload(),450);else e.target.value='';});
 q('#nivoTestBtn')?.addEventListener('click',async()=>{const input=q('#nivoTestQuery'),box=q('#nivoTestResult'),query=input?.value.trim();if(!query){showNotify('warning','Falta el mensaje','Escribe una pregunta para probar NIVO.');input?.focus();return;}const fd=new FormData();fd.append('action','nivo_suggest');fd.append('query',query);box.classList.add('loading');box.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i><div><b>Analizando…</b><small>NIVO está buscando reglas y conocimiento autorizado.</small></div>';const j=await send(fd);box.classList.remove('loading');if(!j.ok){box.innerHTML='<i class="fa-solid fa-triangle-exclamation"></i><div><b>No se pudo responder</b><small></small></div>';box.querySelector('small').textContent=j.message;return;}box.innerHTML='<i class="fa-solid fa-robot"></i><div><b>Respuesta de NIVO</b><p></p><small></small></div>';box.querySelector('p').textContent=j.data.suggestion||'';box.querySelector('small').textContent='Confianza: '+(j.data.confidence||'n/a')+(j.data.source?' · Fuente: '+j.data.source:' · Transferencia humana');});
})();

// NIVO handoff and demo-agent controls
(()=>{const $=(s,c=document)=>c.querySelector(s),$$=(s,c=document)=>[...c.querySelectorAll(s)];const send=async fd=>{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd});return r.json()};
$('#autoAssignBtn')?.addEventListener('click',async e=>{const fd=new FormData();fd.append('action','conversation_auto_assign');fd.append('conversation_id',e.currentTarget.dataset.conversation);const j=await send(fd);showNotify(j.ok?'success':'error',j.ok?'Transferencia':'Error',j.message);if(j.ok)setTimeout(()=>location.reload(),450)});
$('#aliasForm')?.addEventListener('submit',async e=>{e.preventDefault();const j=await send(new FormData(e.currentTarget));showNotify(j.ok?'success':'error',j.ok?'Agente agregado':'Error',j.message);if(j.ok)setTimeout(()=>location.reload(),350)});
$$('.alias-delete').forEach(b=>b.onclick=async()=>{const r=await Swal.fire({title:'Eliminar agente de demostración',text:'Ya no será usado en asignaciones de prueba.',icon:'warning',showCancelButton:true,confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar'});if(!r.isConfirmed)return;const fd=new FormData();fd.append('action','nivo_alias_delete');fd.append('alias_id',b.dataset.id);const j=await send(fd);showNotify(j.ok?'success':'error',j.ok?'Eliminado':'Error',j.message);if(j.ok)setTimeout(()=>location.reload(),350)});
})();

// ZYNKO Inbox CRM + realtime refresh. WebSocket is primary; light polling is fallback.
(()=>{
 const $=(s,c=document)=>c.querySelector(s), send=async fd=>{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd});return r.json()};
 const bind=(id,reload=false)=>$(id)?.addEventListener('submit',async e=>{e.preventDefault();const j=await send(new FormData(e.currentTarget));showNotify(j.ok?'success':'error',j.ok?'Listo':'Error',j.message);if(j.ok&&reload)setTimeout(()=>location.reload(),300)});
 bind('#contactProfileForm',true);bind('#contactCategoriesForm',true);bind('#followupForm',true);bind('#noteForm',true);bind('#categoryForm',true);
 const form=$('#messageForm'), cid=form?.querySelector('[name=conversation_id]')?.value, box=$('#messages');
 if(!cid||!box)return;
 const esc=v=>{const d=document.createElement('div');d.textContent=v??'';return d.innerHTML};
 const render=rows=>{box.innerHTML=rows.map(m=>{const out=m.direction==='out';let who='';if(out&&m.sender_name)who=`<b class="message-sender">${esc((m.sender_name+' · '+(window.ZYNKO_COMPANY||'')).toUpperCase())}</b>`;else if(m.sender_type==='bot')who=`<b class="message-sender">${esc(('NIVO · '+(window.ZYNKO_COMPANY||'')).toUpperCase())}</b>`;let media='';try{media=(JSON.parse(m.media_json||'[]')||[]).map(f=>`<a class="chat-attachment" href="${esc(f.url)}" target="_blank"><i class="fa-solid fa-paperclip"></i>${esc(f.name||'Adjunto')}</a>`).join('')}catch(_){}return `<div class="message-wrap ${out?'out':'in'}">${who}<p class="${out?'me':'them'}">${esc(m.body||'').replace(/\n/g,'<br>')}</p>${media}</div>`}).join('');box.scrollTop=box.scrollHeight};
 let busy=false,last='';const refresh=async()=>{if(busy)return;busy=true;try{const fd=new FormData();fd.append('action','conversation_snapshot');fd.append('conversation_id',cid);const j=await send(fd);if(j.ok){const sig=JSON.stringify(j.data.messages.map(x=>[x.id,x.body,x.status]));if(sig!==last){last=sig;render(j.data.messages)}}}finally{busy=false}};
 document.addEventListener('zynko:realtime',e=>{const d=e.detail;if(String(d?.data?.conversation_id||d?.entity_id||'')===String(cid))refresh();else if(d?.event==='conversation.created')location.reload()});
 // If the local WebSocket daemon is not running, the inbox still refreshes without manual reload.
 setInterval(()=>{if(!document.hidden)refresh()},5000);refresh();
})();

// ZYNKO Premium Experience v1: realtime indicator, command center and navigation feedback.
(()=>{
 const pill=document.getElementById('realtimePill');
 const setRealtime=connected=>{if(!pill)return;const total=Number(pill.dataset.total||0),linked=Number(pill.dataset.connected||0),warning=Number(pill.dataset.warning||0);pill.classList.toggle('connected',!!connected&&total>0&&linked===total);pill.classList.toggle('offline',!connected||linked<total);const health=total?`Canales ${linked}/${total}`:'Sin canales';pill.querySelector('span').textContent=health+(connected?' · Tiempo real':' · Auto');pill.title=`Estado omnicanal: ${linked} de ${total} canales conectados${warning?`, ${warning} con advertencia`:''}. `+(connected?'WebSocket conectado: eventos instantáneos activos.':'Sin WebSocket: actualización automática de respaldo cada 5 segundos.');};
 document.addEventListener('zynko:realtime-status',e=>setRealtime(!!e.detail?.connected));
 const modal=document.getElementById('searchModal'), input=document.getElementById('commandSearch'), results=document.getElementById('commandResults');
 const items=[
  ['Dashboard','Resumen operativo y métricas','?page=dashboard','fa-chart-line','General'],['Bandeja','Conversaciones y atención al cliente','?page=inbox','fa-inbox','Operación'],['Sin asignar','Conversaciones pendientes de agente','?page=inbox&filter=unassigned','fa-user-clock','Operación'],['Canales','WhatsApp, Messenger y conexiones','?page=channels','fa-comments','Configuración'],['Usuarios','Directorio, agentes y equipos','?page=users','fa-users','Administración'],['NIVO','Reglas, conocimiento y transferencia','?page=chatbot','fa-wand-magic-sparkles','IA'],['Integraciones','API, webhooks y sistemas externos','?page=integrations','fa-plug','Desarrollo'],['Suscripción','Planes, límites y empresas','?page=billing','fa-credit-card','Administración'],['Correo','SMTP, Graph y notificaciones','?page=email','fa-envelope','Configuración'],['Configuración','Empresa, marca y experiencia','?page=settings','fa-gear','Configuración'],['Inicio guiado','Configuración inicial de ZYNKO','?page=onboarding','fa-compass','Ayuda']
 ]; let active=0, filtered=[];
 const draw=()=>{if(!results)return;const q=(input?.value||'').trim().toLowerCase();filtered=items.filter(x=>(x[0]+' '+x[1]+' '+x[4]).toLowerCase().includes(q));active=Math.min(active,Math.max(0,filtered.length-1));results.innerHTML=filtered.length?filtered.map((x,i)=>`<button type="button" class="command-item ${i===active?'active':''}" data-url="${x[2]}"><i class="fa-solid ${x[3]}"></i><div><b>${x[0]}</b><small>${x[1]}</small></div><span>${x[4]}</span></button>`).join(''):'<div class="command-empty"><i class="fa-solid fa-magnifying-glass"></i><p>No encontramos una acción con ese nombre.</p></div>';results.querySelectorAll('.command-item').forEach(b=>b.onclick=()=>location.href=b.dataset.url);};
 document.getElementById('globalSearch')?.addEventListener('click',()=>setTimeout(()=>{input?.focus();draw()},20)); input?.addEventListener('input',()=>{active=0;draw()});
 input?.addEventListener('keydown',e=>{if(e.key==='ArrowDown'){e.preventDefault();active=Math.min(active+1,filtered.length-1);draw()}else if(e.key==='ArrowUp'){e.preventDefault();active=Math.max(0,active-1);draw()}else if(e.key==='Enter'&&filtered[active]){e.preventDefault();location.href=filtered[active][2]}}); draw();
 const bar=document.createElement('div');bar.id='zynkoProgress';document.body.appendChild(bar);const start=()=>{bar.classList.add('run');bar.style.width='68%'};window.addEventListener('beforeunload',start);document.addEventListener('click',e=>{const a=e.target.closest('a[href]');if(a&&!a.target&&!a.hasAttribute('download')&&!a.href.startsWith('javascript:'))start()});window.addEventListener('pageshow',()=>{bar.style.width='100%';setTimeout(()=>{bar.classList.remove('run');bar.style.width='0'},180)});
})();

// Premium omnichannel inbox: persistent views, bulk productivity and local handoff summary.
(()=>{
 const form=document.getElementById('inboxFilters'); if(!form)return;
 const post=async fd=>{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd});return r.json()};
 form.querySelectorAll('.inbox-filter').forEach(el=>el.addEventListener('change',async()=>{const fd=new FormData();fd.append('action','inbox_preferences_save');fd.append('channel_type',form.channel.value);fd.append('assignment_filter',form.assignment.value);fd.append('priority_filter',form.priority.value);try{await post(fd)}catch(_){} form.submit()}));
 document.querySelector('.clear-inbox-filters')?.addEventListener('click',async()=>{form.channel.value='all';form.assignment.value='all';form.priority.value='all';const fd=new FormData();fd.append('action','inbox_preferences_save');fd.append('channel_type','all');fd.append('assignment_filter','all');fd.append('priority_filter','all');try{await post(fd)}catch(_){} location.href='?page=inbox'});
 const toggle=document.getElementById('bulkToggle'),bar=document.getElementById('bulkBar'),count=document.getElementById('bulkCount'),list=document.querySelector('.conv-list');
 const selected=()=>[...document.querySelectorAll('.conversation .bulk-check input:checked')].map(x=>x.closest('.conversation')?.dataset.conversationId).filter(Boolean);
 const update=()=>{if(count)count.textContent=selected().length};
 toggle?.addEventListener('click',()=>{const on=!list.classList.contains('bulk-mode');list.classList.toggle('bulk-mode',on);if(bar)bar.hidden=!on;if(!on)document.querySelectorAll('.bulk-check input').forEach(x=>x.checked=false);update()});
 document.querySelectorAll('.bulk-check input').forEach(x=>{x.addEventListener('click',e=>e.stopPropagation());x.addEventListener('change',update)});
 const bulk=async action=>{const ids=selected();if(!ids.length){showNotify('warning','Sin selección','Selecciona al menos una conversación.');return}const ask=await Swal.fire({title:action==='resolve'?'Resolver conversaciones':'Asignarme conversaciones',text:`Se aplicará a ${ids.length} conversación(es).`,icon:'question',showCancelButton:true,confirmButtonText:'Continuar',cancelButtonText:'Cancelar'});if(!ask.isConfirmed)return;const fd=new FormData();fd.append('action','conversation_bulk_action');fd.append('bulk_action',action);ids.forEach(id=>fd.append('conversation_ids[]',id));const j=await post(fd);showNotify(j.ok?'success':'error',j.ok?'Actualizado':'Error',j.message);if(j.ok)setTimeout(()=>location.reload(),300)};
 document.getElementById('bulkAssign')?.addEventListener('click',()=>bulk('assign_me'));document.getElementById('bulkResolve')?.addEventListener('click',()=>bulk('resolve'));
 document.getElementById('nivoSummaryBtn')?.addEventListener('click',()=>{const box=document.getElementById('nivoSummary'),msgs=[...document.querySelectorAll('#messages .message-wrap')].slice(-6).map(x=>x.innerText.trim()).filter(Boolean);if(!box)return;box.hidden=false;box.innerHTML=msgs.length?`<i class="fa-solid fa-wand-magic-sparkles"></i><div><b>Resumen rápido para transferencia</b><p>${msgs.map(x=>x.replace(/\s+/g,' ')).join(' · ').slice(0,700)}</p><small>Resumen local de los últimos mensajes; no inventa información fuera de la conversación.</small></div>`:'<div>No hay mensajes para resumir.</div>'});
})();

// ZYNKO Premium: inbox filters always expose Select2 search; larger selects remain searchable automatically.
if(window.jQuery&&jQuery.fn.select2){
  jQuery('.inbox-filter').each(function(){
    const $el=jQuery(this);
    if($el.hasClass('select2-hidden-accessible')) $el.select2('destroy');
    $el.select2({width:'100%',minimumResultsForSearch:0,dropdownAutoWidth:false});
  });
}

// V2.7 — Channel catalog: functional admin modal + clear feedback for connectors in development.
(()=>{
 const open=id=>{const m=document.getElementById(id);if(!m)return;m.classList.add('open');m.setAttribute('aria-hidden','false');setTimeout(()=>m.querySelector('input:not([type=hidden]):not([disabled]),select:not([disabled]),textarea,button')?.focus(),80)};
 const post=async form=>{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:new FormData(form)});let j={};try{j=await r.json()}catch(_){throw new Error('El servidor devolvió una respuesta no válida.')}if(!r.ok&&!j.message)throw new Error('No se pudo procesar la solicitud.');return j};
 document.getElementById('openChannelAdmin')?.addEventListener('click',()=>open('channelAdminModal'));
 document.querySelectorAll('.channel-unavailable').forEach(btn=>btn.addEventListener('click',()=>{
   const name=btn.dataset.channelName||'Este canal';
   const ready=btn.dataset.connectorReady==='1';
   const message=ready
     ? `${name} existe en el catálogo, pero actualmente está deshabilitado para vinculación. El administrador principal puede revisar su disponibilidad en “Administrar catálogo”.`
     : `${name} ya está contemplado en ZYNKO, pero su conector real todavía está en desarrollo. No se simulará una conexión. Cuando el conector esté implementado podrás habilitarlo desde “Administrar catálogo”.`;
   if(window.Swal) Swal.fire({title:ready?'Canal no habilitado':'Conector en desarrollo',text:message,icon:'info',confirmButtonText:'Entendido'});
   else if(window.showNotify) showNotify('info',ready?'Canal no habilitado':'Conector en desarrollo',message);
 }));
 document.querySelectorAll('.channelCatalogForm').forEach(form=>form.addEventListener('submit',async e=>{
   e.preventDefault();const btn=form.querySelector('button[type=submit],button:not([type])');const old=btn?.innerHTML;if(btn){btn.disabled=true;btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Guardando…'}
   try{const j=await post(form);showNotify(j.ok?'success':'error',j.ok?'Catálogo actualizado':'No se pudo actualizar',j.message||'');if(j.ok)setTimeout(()=>location.reload(),550)}catch(err){showNotify('error','No se pudo actualizar',err.message||'Ocurrió un error inesperado.')}finally{if(btn){btn.disabled=false;btn.innerHTML=old||'<i class="fa-solid fa-floppy-disk"></i> Guardar'}}
 }));
})();
