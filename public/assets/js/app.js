const app=document.querySelector('.app');
// Preferencias de interfaz por usuario: la BD es la fuente principal; localStorage queda como cache/fallback para carga inmediata.
const zynkoUiPrefs=(()=>{
  const server=(window.ZYNKO_UI_PREFS&&typeof window.ZYNKO_UI_PREFS==='object')?window.ZYNKO_UI_PREFS:{};
  const legacy={
    'sidebar.hidden':'zynko.sidebar.hidden',
    'sidebar.collapsed':'zynko.sidebar.collapsed',
    'directory.users':'zynko.directory.view.users',
    'directory.companies':'zynko.directory.view.companies',
    'directory.billing':'zynko.directory.view.billing'
  };
  const saveRemote=(key,value)=>{try{const fd=new FormData();fd.append('action','ui_preference');fd.append('key',key);fd.append('value',String(value));fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd}).catch(()=>{});}catch(_){}};
  const get=(key,fallback='')=>{
    if(Object.prototype.hasOwnProperty.call(server,key)){const v=String(server[key]);try{if(legacy[key])localStorage.setItem(legacy[key],v)}catch(_){}return v;}
    try{const lk=legacy[key];const cached=lk?localStorage.getItem(lk):null;if(cached!==null){server[key]=cached;setTimeout(()=>saveRemote(key,cached),0);return cached;}}catch(_){}
    return fallback;
  };
  const set=(key,value)=>{const v=String(value);server[key]=v;try{if(legacy[key])localStorage.setItem(legacy[key],v)}catch(_){}saveRemote(key,v);return v;};
  return {get,set};
})();
window.ZynkoUiPrefs=zynkoUiPrefs;
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
if(window.jQuery&&jQuery.fn.select2){jQuery('select:not(.no-select2)').each(function(){const $el=jQuery(this);if(!$el.hasClass('select2-hidden-accessible'))$el.select2({width:'100%',minimumResultsForSearch:6,dropdownAutoWidth:false});});}document.documentElement.classList.remove('select2-preload');
document.querySelectorAll('.upload-zone').forEach(zone=>{const input=zone.querySelector('input[type=file]'); if(!input)return; zone.addEventListener('click',e=>{if(e.target!==input)input.click()}); zone.addEventListener('dragover',e=>{e.preventDefault();zone.classList.add('drag')}); zone.addEventListener('dragleave',()=>zone.classList.remove('drag')); zone.addEventListener('drop',e=>{e.preventDefault();zone.classList.remove('drag');if(e.dataTransfer.files.length){input.files=e.dataTransfer.files;zone.querySelector('small').textContent=e.dataTransfer.files[0].name}}); zone.addEventListener('paste',e=>{const f=[...e.clipboardData.files];if(f.length){const dt=new DataTransfer();f.forEach(x=>dt.items.add(x));input.files=dt.files;zone.querySelector('small').textContent=f[0].name}}); input.addEventListener('change',()=>{if(input.files[0])zone.querySelector('small').textContent=input.files[0].name});});
// ZYNKO navigation preference: double click the menu button to hide/show the sidebar completely.
(()=>{const b=document.getElementById('menu');if(!b)return;const prefKey='sidebar.hidden';const apply=()=>document.body.classList.toggle('sidebar-hidden',zynkoUiPrefs.get(prefKey,'0')==='1');apply();b.addEventListener('dblclick',e=>{e.preventDefault();const next=document.body.classList.contains('sidebar-hidden')?'0':'1';zynkoUiPrefs.set(prefKey,next);apply();});})();

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
 const collapse=$('#sideCollapse'); if(zynkoUiPrefs.get('sidebar.collapsed','0')==='1')document.body.classList.add('sidebar-collapsed');collapse?.addEventListener('click',()=>{document.body.classList.toggle('sidebar-collapsed');zynkoUiPrefs.set('sidebar.collapsed',document.body.classList.contains('sidebar-collapsed')?'1':'0')});
 // real flyouts only where real options exist
 const fly=$('#flyout'); $$('aside nav a[data-menu]').forEach(a=>{a.onmouseenter=()=>{if(innerWidth<=760)return;const list=window.ZYNKO_MENUS?.[a.dataset.menu]||[];if(!list.length){fly.classList.remove('open');return;}fly.innerHTML='<small>ACCESOS</small><b>'+a.querySelector('b').textContent+'</b>'+list.map(x=>`<a href="${x[0]}"><i class="fa-solid ${x[2]}"></i>${x[1]}</a>`).join('');const r=a.getBoundingClientRect();fly.style.top=Math.min(r.top,innerHeight-fly.offsetHeight-20)+'px';fly.classList.add('open')};});
 // deep links del menú lateral: Experiencia y otras secciones deben abrir y enfocarse realmente
 const focusHashSection=()=>{if(!location.hash)return;const target=document.querySelector(location.hash);if(!target)return;requestAnimationFrame(()=>{target.scrollIntoView({behavior:'smooth',block:'start'});target.classList.add('hash-focus');setTimeout(()=>target.classList.remove('hash-focus'),1400)});};
 window.addEventListener('hashchange',focusHashSection);setTimeout(focusHashSection,120);
 // user dropdown/logout with confirmation
 $('#userMenuBtn')?.addEventListener('click',e=>{e.stopPropagation();$('#userDropdown')?.classList.toggle('open')});document.addEventListener('click',()=>$('#userDropdown')?.classList.remove('open'));
 $('#logoutBtn')?.addEventListener('click',async()=>{const ok=await Swal.fire({title:'Cerrar sesión',text:'¿Deseas salir de ZYNKO?',icon:'question',showCancelButton:true,confirmButtonText:'Sí, cerrar sesión',confirmButtonIcon:'fa-solid fa-right-from-bracket',cancelButtonText:'Cancelar',cancelButtonIcon:'fa-solid fa-xmark',allowOutsideClick:false});if(ok.isConfirmed)location.href='?page=logout'});
 // modals
 const openModal=id=>{const m=document.getElementById(id);if(m){m.classList.add('open');m.setAttribute('aria-hidden','false');document.body.classList.add('modal-open');setTimeout(()=>window.ZynkoFocusFirst?.(m,{onlyIfIdle:false,preventScroll:true}),80)}}; const closeModal=m=>{const shell=m.closest('.modal-shell');shell?.classList.remove('open');if(!document.querySelector('.modal-shell.open'))document.body.classList.remove('modal-open')};
 $$('[data-open]').forEach(b=>b.addEventListener('click',()=>openModal(b.dataset.open)));$$('.modal-close').forEach(b=>b.addEventListener('click',()=>closeModal(b)));$$('.modal-shell').forEach(m=>m.addEventListener('click',e=>{if(e.target===m){} }));
 const channelNames={whatsapp:'WhatsApp Business',messenger:'Messenger',instagram:'Instagram Messaging'};
 const openChannelConfig=type=>{if(!channelNames[type])return;$('#channelType').value=type;$('#channelModalTitle').textContent='Configurar '+channelNames[type];document.getElementById('channelCatalogModal')?.classList.remove('open');openModal('channelModal')};
 $('#openChannelCatalog')?.addEventListener('click',()=>openModal('channelCatalogModal'));
 $$('.channel-config,.channel-add').forEach(b=>b.addEventListener('click',()=>openChannelConfig(b.dataset.channel)));
 $$('.channel-catalog-item[data-channel]').forEach(b=>b.addEventListener('click',()=>openChannelConfig(b.dataset.channel)));
 bindAjax('#channelForm',()=>setTimeout(()=>location.reload(),700)); bindAjax('#userForm',()=>setTimeout(()=>location.reload(),700)); bindAjax('#userEditForm',()=>setTimeout(()=>location.reload(),700)); bindAjax('#avatarForm',()=>setTimeout(()=>location.reload(),700)); bindAjax('#settingsForm',(j,f)=>{const t=f.querySelector('[name=theme]')?.value||'system';applyZynkoTheme(t);setTimeout(()=>location.reload(),700)}); bindAjax('#seoForm',()=>setTimeout(()=>location.reload(),550)); bindAjax('#publicSiteForm',()=>setTimeout(()=>location.reload(),550)); bindAjax('#envAdminForm',()=>setTimeout(()=>location.reload(),700)); bindAjax('#apiPolicyForm',()=>setTimeout(()=>location.reload(),550)); bindAjax('#botForm',()=>setTimeout(()=>location.reload(),700)); bindAjax('#openAiProviderForm',()=>setTimeout(()=>location.reload(),550)); bindAjax('#tenantAiForm',()=>setTimeout(()=>location.reload(),550)); bindAjax('#assignForm',()=>document.querySelector('#assignModal')?.classList.remove('open'));
 // search + records-per-page behavior
 const refreshRecordList=(panel)=>{
   const q=(panel.querySelector('.list-search')?.value||'').trim().toLowerCase();
   const select=panel.querySelector('.records-per-page');
   const limit=select?.value==='all'?Infinity:Math.max(1,parseInt(select?.value||'10',10));
   const isUsers=panel.id==='usersDirectoryPanel';
   const role=isUsers?(document.querySelector('#usersRoleFilter')?.value||''):'';
   const status=isUsers?(document.querySelector('#usersStatusFilter')?.value||''):'';
   let shown=0,matchesTotal=0;
   $$('.searchable',panel).forEach(item=>{
     const haystack=((item.dataset.search||'')+' '+item.textContent).toLowerCase();
     const matches=(!q||haystack.includes(q))&&(!role||item.dataset.role===role)&&(!status||item.dataset.status===status);
     if(matches)matchesTotal++;
     const visible=matches&&shown<limit;
     item.hidden=!visible;
     if(visible)shown++;
   });
   const empty=panel.querySelector('.directory-empty-state');
   if(empty)empty.hidden=matchesTotal!==0;
 };
 $$('.list-search').forEach(inp=>{const panel=inp.closest('.panel')||document,wrap=inp.closest('.search-wrap')||inp.parentElement,clear=wrap?.querySelector('.clear-search');const run=()=>{const q=inp.value.trim();if(clear)clear.hidden=!q;refreshRecordList(panel);};inp.addEventListener('input',run);clear?.addEventListener('click',()=>{inp.value='';run();inp.focus()});run();});
 $$('.records-per-page').forEach(sel=>{const panel=sel.closest('.panel')||document;const all=sel.querySelector('option[value="all"]');if(all){const total=$$('.searchable',panel).length;all.textContent=`Todos (${total})`;}sel.addEventListener('change',()=>refreshRecordList(panel));refreshRecordList(panel);});
 ['#usersRoleFilter','#usersStatusFilter'].forEach(sel=>document.querySelector(sel)?.addEventListener('change',()=>refreshRecordList(document.querySelector('#usersDirectoryPanel'))));document.querySelector('#usersFilterClear')?.addEventListener('click',()=>{const r=document.querySelector('#usersRoleFilter'),s=document.querySelector('#usersStatusFilter');if(r)r.value='';if(s)s.value='';if(window.jQuery){jQuery('#usersRoleFilter,#usersStatusFilter').trigger('change.select2')}refreshRecordList(document.querySelector('#usersDirectoryPanel'));});
 // Directory views: detail / mini, persisted per module.
 $$('.directory-view-toggle').forEach(group=>{
   const directory=group.dataset.directory||'directory';
   const panel=group.closest('.panel')||document;
   const list=directory==='companies'?document.querySelector('#companyGrid'):(directory==='billing'?document.querySelector('#billingPlanDirectory'):panel.querySelector('.users-directory-list,.card-list'));
   if(!list)return;
   const key='zynko.directory.view.'+directory;
   const apply=(view,persist=true)=>{
     const safe=view==='mini'?'mini':'detail';
     list.classList.toggle('view-mini',safe==='mini');
     list.classList.toggle('view-detail',safe==='detail');
     group.querySelectorAll('.directory-view-btn').forEach(btn=>{const active=btn.dataset.view===safe;btn.classList.toggle('active',active);btn.setAttribute('aria-pressed',active?'true':'false')});
     document.querySelectorAll(`[data-directory="${directory}"]`).forEach(g=>g.querySelectorAll('.directory-view-btn').forEach(btn=>{const active=btn.dataset.view===safe;btn.classList.toggle('active',active);btn.setAttribute('aria-pressed',active?'true':'false')}));
     const exportScope=panel.closest('.panel')||panel;exportScope.querySelectorAll('[data-export-base]').forEach(a=>{a.href=(a.dataset.exportBase||a.getAttribute('href').split('&view=')[0])+'&view='+safe});
     if(persist)zynkoUiPrefs.set(key,safe)
   };
   const saved=zynkoUiPrefs.get(key,'detail')||'detail'
   apply(saved,false);
   group.addEventListener('click',e=>{const btn=e.target.closest('.directory-view-btn');if(btn)apply(btn.dataset.view)});
 });
 // inbox rich composer
 const emojiPicker=$('#emojiPicker'),emojiBtn=$('#emojiBtn'),messageInput=$('#messageInput');
 emojiBtn?.addEventListener('click',e=>{e.stopPropagation();if(emojiPicker)emojiPicker.hidden=!emojiPicker.hidden});
 emojiPicker?.addEventListener('click',e=>{
   const tab=e.target.closest('[data-emoji-tab]');
   if(tab){const name=tab.dataset.emojiTab;$$('[data-emoji-tab]',emojiPicker).forEach(x=>x.classList.toggle('active',x===tab));$$('[data-emoji-group]',emojiPicker).forEach(x=>x.classList.toggle('active',x.dataset.emojiGroup===name));return;}
   const emoji=e.target.closest('[data-emoji]');
   if(emoji&&messageInput){const value=emoji.dataset.emoji||emoji.textContent||'';const start=messageInput.selectionStart??messageInput.value.length,end=messageInput.selectionEnd??start;messageInput.setRangeText(value,start,end,'end');messageInput.focus();}
 });
 document.addEventListener('click',e=>{if(emojiPicker&&!emojiPicker.hidden&&!emojiPicker.contains(e.target)&&e.target!==emojiBtn)emojiPicker.hidden=true});
 $('#attachBtn')?.addEventListener('click',()=>$('#chatFile')?.click());$('#chatFile')?.addEventListener('change',e=>{$('#attachmentPreview').innerHTML=[...e.target.files].map(f=>`<span><i class="fa-solid fa-paperclip"></i>${f.name}</span>`).join('')});
 $('#nivoAssist')?.addEventListener('click',async()=>{const i=$('#messageInput');if(!i)return;const last=[...document.querySelectorAll('#messages p:not(.me)')].pop()?.textContent?.trim()||i.value.trim();if(!last){showNotify('warning','NIVO necesita contexto','Selecciona una conversación con un mensaje del cliente.');return;}const fd=new FormData();fd.append('action','nivo_suggest');fd.append('query',last);const j=await post(fd);if(j.ok){i.value=j.data.suggestion||'';showNotify('info','Sugerencia de NIVO',(j.data.source?'Fuente: '+j.data.source+'. ':'')+'Confianza: '+(j.data.confidence||'n/a')+'. Revisa antes de enviar.');i.focus();}else showNotify('error','NIVO',j.message);});
 $('#messageForm')?.addEventListener('submit',async e=>{e.preventDefault();const i=$('#messageInput'),body=i.value.trim();if(!body&&!($('#chatFile')?.files?.length)){showNotify('warning','Mensaje vacío','Escribe un mensaje o adjunta un archivo antes de enviar.');return;}const fd=new FormData();fd.append('action','message_send');fd.append('conversation_id',$('#messageForm [name=conversation_id]')?.value||'');fd.append('body',body);[...($('#chatFile')?.files||[])].forEach(f=>fd.append('attachments[]',f));const j=await post(fd);if(j.ok){const m=document.createElement('p');m.className='me';m.textContent=body;$('#messages').append(m);i.value='';$('#attachmentPreview').innerHTML='';$('#chatFile').value='';$('#messages').scrollTop=$('#messages').scrollHeight;showNotify('success','Mensaje listo',j.message);}else showNotify('error','No se pudo enviar',j.message)});
})();


// ZYNKO final functional controls
(()=>{const $=(s,c=document)=>c.querySelector(s),$$=(s,c=document)=>[...c.querySelectorAll(s)];const post=async f=>{const fd=f instanceof FormData?f:new FormData(f);const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd});return r.json()};const bind=(id,reload=true)=>$(id)?.addEventListener('submit',async e=>{e.preventDefault();const j=await post(e.currentTarget);showNotify(j.ok?'success':'error',j.message,j.ok?'Listo':'Error');if(j.ok&&reload)setTimeout(()=>location.reload(),500)});
$$('.subscriptionAssignForm').forEach(f=>f.addEventListener('submit',async e=>{e.preventDefault();const j=await post(e.currentTarget);showNotify(j.ok?'success':'error',j.message,j.ok?'Plan asignado':'Error');if(j.ok)setTimeout(()=>location.reload(),450)}));
 $('#openAiTestBtn')?.addEventListener('click',async()=>{const fd=new FormData();fd.append('action','ai_provider_test');const j=await ajax(fd);showNotify(j.ok?'success':'error',j.message,j.ok?'OpenAI conectado':'Error');if(j.ok)setTimeout(()=>location.reload(),450)});
 $('#openAiUsageBtn')?.addEventListener('click',async()=>{const b=$('#openAiUsageBtn');if(b)b.disabled=true;const fd=new FormData();fd.append('action','ai_provider_refresh_usage');const j=await ajax(fd);showNotify(j.ok?'success':'error',j.message,j.ok?'Consumo actualizado':'Error');if(j.ok)setTimeout(()=>location.reload(),450);else if(b)b.disabled=false;});
$('#apiKeyForm')?.addEventListener('submit',async e=>{e.preventDefault();const j=await post(e.currentTarget);if(j.ok){await Swal.fire({title:'Clave API creada',text:j.data.api_key+' — cópiala ahora y guárdala de forma segura.',icon:'success',confirmButtonText:'Entendido',allowOutsideClick:false});location.reload();}else showNotify('error','Error',j.message)});
$$('.api-key-revoke').forEach(b=>b.addEventListener('click',async()=>{const r=await Swal.fire({title:'Revocar clave API',text:'El sistema externo dejará de poder autenticarse inmediatamente.',icon:'warning',showCancelButton:true,confirmButtonText:'Sí, revocar',cancelButtonText:'Cancelar',allowOutsideClick:false});if(!r.isConfirmed)return;const fd=new FormData();fd.append('action','api_key_revoke');fd.append('api_key_id',b.dataset.id);const j=await post(fd);showNotify(j.ok?'success':'error',j.message,j.ok?'Clave revocada':'Error');if(j.ok)setTimeout(()=>location.reload(),450)}));
$('#helpBtn')?.addEventListener('click',()=>Swal.fire({title:'Ayuda de este módulo',text:'Usa el menú lateral para navegar. Los botones con iconos ejecutan acciones reales; si una integración externa requiere autorización, ZYNKO mostrará el estado pendiente en lugar de simularla.',icon:'info',confirmButtonText:'Entendido'}));
$('#integrationForm')?.addEventListener('submit',async e=>{e.preventDefault();const j=await post(e.currentTarget);if(j.ok){await Swal.fire({title:'Integración creada',html:'Guarda este secreto para validar firmas de ZYNKO:<br><code style="display:block;margin-top:12px;word-break:break-all">'+String(j.data?.webhook_secret||'')+'</code>',icon:'success',confirmButtonText:'Entendido',allowOutsideClick:false});location.reload();}else showNotify('error','Error',j.message)});bind('#userEditForm');bind('#avatarForm');bind('#knowledgeForm');
$$('.user-actions').forEach(b=>b.addEventListener('click',()=>{const u=JSON.parse(b.dataset.user);const m=$('#userEditModal');m.querySelectorAll('[name=user_id]').forEach(x=>x.value=u.id);m.querySelector('[name=name]').value=u.name;m.querySelector('[name=role]').value=u.role_code;m.querySelector('[name=status]').value=u.status;if(window.jQuery)jQuery(m).find('.select2').trigger('change');m.classList.add('open')}));
})();

// ZYNKO final interaction pass: fullscreen, plans and new conversations.
(()=>{const $=(s,c=document)=>c.querySelector(s),$$=(s,c=document)=>[...c.querySelectorAll(s)];
 const ajax=async fd=>{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd});return r.json()};
 // V2.31.14 · Fullscreen persistente mediante shell con iframe del mismo origen.
 // El documento principal permanece en Fullscreen y los cambios de módulo navegan dentro del iframe,
 // evitando que el navegador cierre Fullscreen al cambiar de página.
 const isFullscreenFrame=window.self!==window.top;
 const fullscreenElement=()=>document.fullscreenElement||document.webkitFullscreenElement||null;
 const syncFsIcon=active=>{
   const btn=$('#fullscreenBtn'),i=btn?.querySelector('i');
   if(!btn||!i)return;
   const on=active??!!fullscreenElement();
   i.className=on?'fa-solid fa-compress':'fa-solid fa-expand';
   btn.setAttribute('aria-pressed',on?'true':'false');
   btn.title=on?'Salir de pantalla completa':'Pantalla completa · oculta la barra de tareas';
 };
 if(isFullscreenFrame){
   syncFsIcon(true);
   $('#fullscreenBtn')?.addEventListener('click',()=>{try{window.parent.postMessage({type:'zynko:fullscreen-exit'},location.origin)}catch(_){}});
 }else{
   let fsFrame=null,fsExitRequested=false;
   const frameRelativeUrl=url=>{try{const u=new URL(url,location.href);return u.pathname+u.search+u.hash}catch(_){return location.pathname+location.search+location.hash}};
   const mountFullscreenFrame=()=>{
     if(fsFrame?.isConnected)return fsFrame;
     fsFrame=document.createElement('iframe');
     fsFrame.id='zynkoFullscreenFrame';
     fsFrame.name='zynkoFullscreenFrame';
     fsFrame.title='ZYNKO pantalla completa';
     fsFrame.src=location.href;
     fsFrame.setAttribute('allow','clipboard-read; clipboard-write');
     document.body.appendChild(fsFrame);
     document.body.classList.add('zynko-fullscreen-shell');
     fsFrame.addEventListener('load',()=>{try{history.replaceState(history.state,'',frameRelativeUrl(fsFrame.contentWindow.location.href))}catch(_){}});
     return fsFrame;
   };
   const unmountFullscreenFrame=(navigate=true)=>{
     let target='';try{target=fsFrame?.contentWindow?.location?.href||''}catch(_){}
     fsFrame?.remove();fsFrame=null;document.body.classList.remove('zynko-fullscreen-shell');
     if(navigate&&target){const next=frameRelativeUrl(target),current=location.pathname+location.search+location.hash;if(next!==current)location.replace(next)}
   };
   const enterFullscreen=async()=>{
     try{
       const el=document.documentElement;
       if(el.requestFullscreen)await el.requestFullscreen({navigationUI:'hide'});
       else if(el.webkitRequestFullscreen)el.webkitRequestFullscreen();
       else throw new Error('Fullscreen API no disponible');
       mountFullscreenFrame();syncFsIcon(true);
       showNotify('success','Pantalla completa','Puedes navegar por todos los módulos sin salir de pantalla completa. ESC permite salir.');
       return true;
     }catch(e){showNotify('error','No se pudo activar pantalla completa','El navegador no permitió activar la pantalla completa.');return false}
   };
   const exitFullscreen=async()=>{
     fsExitRequested=true;
     try{
       if(document.exitFullscreen)await document.exitFullscreen();
       else if(document.webkitExitFullscreen)document.webkitExitFullscreen();
     }catch(_){}
     if(!fullscreenElement()){unmountFullscreenFrame(true);syncFsIcon(false)}
     fsExitRequested=false;
   };
   $('#fullscreenBtn')?.addEventListener('click',async()=>{if(fullscreenElement())await exitFullscreen();else await enterFullscreen()});
   const handleFsChange=()=>{const active=!!fullscreenElement();syncFsIcon(active);if(active){mountFullscreenFrame();return;}if(fsFrame&&!fsExitRequested)unmountFullscreenFrame(true)};
   document.addEventListener('fullscreenchange',handleFsChange);
   document.addEventListener('webkitfullscreenchange',handleFsChange);
   window.addEventListener('message',e=>{if(e.origin!==location.origin||e.data?.type!=='zynko:fullscreen-exit')return;exitFullscreen()});
   syncFsIcon(false);
 }
 $$('.plan-edit').forEach(b=>b.addEventListener('click',()=>{const p=JSON.parse(b.dataset.plan),m=$('#planModal'),f=$('#planForm');f.reset();f.plan_id.value=p.id;f.name.value=p.name;f.monthly_price.value=p.monthly_price;f.currency.value=p.currency;f.max_users.value=p.max_users||0;f.max_channels.value=p.max_channels||0;f.max_webchat_sites.value=p.max_webchat_sites||0;f.max_daily_chats.value=p.max_daily_chats||0;if(f.max_monthly_chats)f.max_monthly_chats.value=p.max_monthly_chats||0;if(f.is_featured)f.is_featured.checked=String(p.is_featured)==='1';if(f.featured_label)f.featured_label.value=p.featured_label||'';if(f.is_available)f.is_available.checked=String(p.is_available)==='1';if(f.availability_label)f.availability_label.value=p.availability_label||'Próximamente';if(f.availability_message)f.availability_message.value=p.availability_message||'Estamos trabajando para habilitar todos los canales y capacidades de este plan. Estará disponible muy pronto.';if(f.external_ai_included)f.external_ai_included.checked=String(p.external_ai_included)==='1';if(f.external_ai_monthly_tokens)f.external_ai_monthly_tokens.value=p.external_ai_monthly_tokens||0;let aiChannels=[];try{aiChannels=JSON.parse(p.external_ai_channels_json||'[]')||[]}catch(_){aiChannels=[]}f.querySelectorAll('input[name="external_ai_channels[]"]')?.forEach(x=>x.checked=aiChannels.includes(x.value));f.features.value=(JSON.parse(p.features_json||'[]')||[]).join('\n');f.active.checked=String(p.active)==='1';f.is_default_free.checked=String(p.is_default_free)==='1';let allowed=[];try{allowed=JSON.parse(p.allowed_channels_json||'[]')||[]}catch(_){allowed=[]}f.querySelectorAll('input[name="allowed_channels[]"][type=checkbox]').forEach(x=>x.checked=allowed.length?allowed.includes(x.value):true);let mods={};try{mods=JSON.parse(p.module_access_json||'{}')||{}}catch(_){mods={}};['users','chatbot','integrations','api','email','settings'].forEach(k=>{const el=f.querySelector(`[name=module_${k}]`);if(el)el.checked=!!mods[k]});$('#planTitle').textContent='Editar plan';m.classList.add('open');if(window.jQuery)jQuery(f).find('.select2').trigger('change')}));
 $('#planForm')?.addEventListener('submit',async e=>{e.preventDefault();const j=await ajax(new FormData(e.currentTarget));showNotify(j.ok?'success':'error',j.message,j.ok?'Plan guardado':'Error');if(j.ok)setTimeout(()=>location.reload(),450)});
 $$('.plan-delete').forEach(b=>b.addEventListener('click',async()=>{const r=await Swal.fire({title:'Eliminar plan',text:`¿Eliminar “${b.dataset.name}”? Esta acción no se puede deshacer.`,icon:'warning',showCancelButton:true,confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar',allowOutsideClick:false});if(!r.isConfirmed)return;const fd=new FormData();fd.append('action','plan_delete');fd.append('plan_id',b.dataset.id);const j=await ajax(fd);showNotify(j.ok?'success':'error',j.message,j.ok?'Plan eliminado':'Error');if(j.ok)setTimeout(()=>location.reload(),450)}));
 $$('.plan-request').forEach(b=>b.addEventListener('click',async()=>{const r=await Swal.fire({title:'Solicitar '+b.dataset.name,text:`Tu empresa solicitará ${b.dataset.name} por ${b.dataset.price}/mes.`,input:'textarea',inputLabel:'Comentario opcional',inputPlaceholder:'Ej. Necesitamos habilitar WhatsApp y más usuarios',inputAttributes:{maxlength:'500'},icon:'question',showCancelButton:true,confirmButtonText:'<i class="fa-solid fa-paper-plane"></i> Enviar solicitud',cancelButtonText:'<i class="fa-solid fa-xmark"></i> Cancelar',allowOutsideClick:false,focusConfirm:false});if(!r.isConfirmed)return;const fd=new FormData();fd.append('action','plan_request');fd.append('plan_id',b.dataset.id);fd.append('note',r.value||'');const j=await ajax(fd);showNotify(j.ok?'success':'error',j.message,j.ok?'Solicitud enviada':'Error');if(j.ok)setTimeout(()=>location.reload(),550)}));
 $$('.plan-request-resolve').forEach(b=>b.addEventListener('click',async()=>{const approved=b.dataset.resolution==='approved';const r=await Swal.fire({title:approved?'Aprobar solicitud':'Rechazar solicitud',text:`${approved?'Se asignará':'Se rechazará'} el plan ${b.dataset.plan} para ${b.dataset.company}.`,input:'textarea',inputLabel:'Comentario opcional',inputPlaceholder:approved?'Ej. Plan activado según solicitud':'Ej. Contactaremos para ofrecer otra opción',inputAttributes:{maxlength:'500'},icon:approved?'question':'warning',showCancelButton:true,confirmButtonText:approved?'<i class="fa-solid fa-circle-check"></i> Aprobar y asignar':'<i class="fa-solid fa-xmark"></i> Rechazar solicitud',cancelButtonText:'<i class="fa-solid fa-ban"></i> Cancelar',allowOutsideClick:false,focusConfirm:false});if(!r.isConfirmed)return;const fd=new FormData();fd.append('action','plan_request_resolve');fd.append('request_id',b.dataset.id);fd.append('resolution',b.dataset.resolution);fd.append('note',r.value||'');const j=await ajax(fd);showNotify(j.ok?'success':'error',j.message,approved?'Solicitud aprobada':'Solicitud actualizada');if(j.ok)setTimeout(()=>location.reload(),550)}));
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
 const nivoActionNames={ruleForm:'Regla de NIVO',solutionForm:'Solución de NIVO',moduleForm:'Módulo de NIVO'};
 const ajaxForm=(id,done)=>q(id)?.addEventListener('submit',async e=>{e.preventDefault();const f=e.currentTarget,b=f.querySelector('button[type=submit],button:not([type])'),name=nivoActionNames[f.id]||'NIVO';if(b)b.disabled=true;try{const j=await send(new FormData(f));showNotify(j.ok?'success':'error',j.ok?name+' actualizado':'No se pudo completar',j.message|| (j.ok?'La acción se procesó correctamente.':'Ocurrió un error al procesar la acción.'));if(j.ok)done?.();}catch(_){showNotify('error','Error de '+name,'No se pudo procesar la solicitud.');}finally{if(b)b.disabled=false}});
 ajaxForm('#ruleForm',()=>setTimeout(()=>location.reload(),350));
 const ruleForm=q('#ruleForm'),ruleModal=q('#ruleModal'),ruleTitle=q('#ruleModalTitle'),ruleSubtitle=q('#ruleModalSubtitle'),ruleSubmit=q('#ruleSubmitBtn');
 const resetRuleForm=()=>{if(!ruleForm)return;ruleForm.reset();ruleForm.action.value='nivo_rule_add';ruleForm.rule_id.value='0';ruleForm.priority.value='100';ruleForm.active.checked=true;if(ruleTitle)ruleTitle.textContent='Nueva regla de NIVO';if(ruleSubtitle)ruleSubtitle.textContent='Usa palabras o frases separadas por coma. Si coinciden, NIVO responde exactamente lo configurado.';if(ruleSubmit)ruleSubmit.innerHTML='<i class="fa-solid fa-floppy-disk"></i> Guardar regla'};
 qa('[data-open="ruleModal"]').forEach(b=>b.addEventListener('click',()=>setTimeout(resetRuleForm,0)));
 qa('.rule-edit').forEach(b=>b.addEventListener('click',()=>{if(!ruleForm)return;let r={};try{r=JSON.parse(b.dataset.rule||'{}')}catch(_){}ruleForm.action.value='nivo_rule_update';ruleForm.rule_id.value=String(r.id||0);ruleForm.name.value=r.name||'';ruleForm.priority.value=String(r.priority||100);ruleForm.keywords.value=r.keywords||'';ruleForm.response.value=r.response||'';ruleForm.active.checked=Number(r.active)===1;if(ruleTitle)ruleTitle.textContent='Editar regla de NIVO';if(ruleSubtitle)ruleSubtitle.textContent='Actualiza la regla existente. Los cambios se aplican inmediatamente al motor de NIVO.';if(ruleSubmit)ruleSubmit.innerHTML='<i class="fa-solid fa-floppy-disk"></i> Guardar cambios';if(window.ZynkoModal?.open)window.ZynkoModal.open('#ruleModal');else ruleModal?.classList.add('open');setTimeout(()=>ruleForm.name?.focus(),80)}));
 ajaxForm('#solutionForm',()=>setTimeout(()=>location.reload(),350));
 ajaxForm('#moduleForm',()=>setTimeout(()=>location.reload(),350));
 qa('.rule-delete').forEach(b=>b.addEventListener('click',async()=>{const r=await Swal.fire({title:'Eliminar regla',text:'NIVO dejará de utilizar esta regla inmediatamente.',icon:'warning',showCancelButton:true,confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar',allowOutsideClick:false});if(!r.isConfirmed)return;const fd=new FormData();fd.append('action','nivo_rule_delete');fd.append('rule_id',b.dataset.id);const j=await send(fd);showNotify(j.ok?'success':'error',j.ok?'Regla eliminada':'Error',j.message);if(j.ok)setTimeout(()=>location.reload(),350)}));
 q('#knowledgeFile')?.addEventListener('change',async e=>{const file=e.target.files?.[0];if(!file)return;const fd=new FormData();fd.append('action','knowledge_file_add');fd.append('file',file);fd.append('solution_id',q('#knowledgeFileSolution')?.value||'0');fd.append('module_id',q('#knowledgeFileModule')?.value||'0');showNotify('info','Importando conocimiento','Validando y agregando '+file.name+'…');const j=await send(fd);showNotify(j.ok?'success':'error',j.ok?'Conocimiento agregado':'No se pudo importar',j.message);if(j.ok)setTimeout(()=>location.reload(),450);else e.target.value='';});
 qa('.add-module').forEach(b=>b.addEventListener('click',()=>{const modal=q('#moduleModal');q('#moduleSolutionId').value=b.dataset.solution||'';q('#moduleSolutionName').textContent='Organiza funcionalidades dentro de '+(b.dataset.name||'esta solución')+'.';modal?.classList.add('open');setTimeout(()=>q('#moduleForm [name=name]')?.focus(),80)}));
 qa('.knowledge-approve').forEach(b=>b.addEventListener('click',async()=>{const r=await Swal.fire({title:'Publicar conocimiento',text:'Después de aprobarlo NIVO podrá utilizar esta fuente para responder.',icon:'question',showCancelButton:true,confirmButtonText:'Aprobar y publicar',cancelButtonText:'Cancelar',allowOutsideClick:false});if(!r.isConfirmed)return;const fd=new FormData();fd.append('action','knowledge_approve');fd.append('knowledge_id',b.dataset.id);const j=await send(fd);showNotify(j.ok?'success':'error',j.ok?'Conocimiento publicado':'Error',j.message);if(j.ok)setTimeout(()=>location.reload(),350)}));
 const filterModules=(sol,mod)=>{const sid=String(sol?.value||'0');[...mod?.options||[]].forEach(o=>{if(o.value==='0')return;o.hidden=sid!=='0'&&o.dataset.solution!==sid});if(mod?.selectedOptions?.[0]?.hidden)mod.value='0'};
 q('.knowledge-solution')?.addEventListener('change',e=>filterModules(e.currentTarget,q('.knowledge-module')));q('#knowledgeFileSolution')?.addEventListener('change',e=>filterModules(e.currentTarget,q('#knowledgeFileModule')));
 q('#nivoTestBtn')?.addEventListener('click',async()=>{const input=q('#nivoTestQuery'),box=q('#nivoTestResult'),query=input?.value.trim();if(!query){showNotify('warning','Falta el mensaje','Escribe una pregunta para probar NIVO.');input?.focus();return;}const fd=new FormData();fd.append('action','nivo_suggest');fd.append('query',query);fd.append('solution_id',q('#nivoTestSolution')?.value||'0');box.classList.add('loading');box.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i><div><b>Analizando…</b><small>NIVO está buscando reglas y conocimiento autorizado.</small></div>';const j=await send(fd);box.classList.remove('loading');if(!j.ok){box.innerHTML='<i class="fa-solid fa-triangle-exclamation"></i><div><b>No se pudo responder</b><small></small></div>';box.querySelector('small').textContent=j.message;return;}box.innerHTML='<i class="fa-solid fa-robot"></i><div><b>Respuesta de NIVO</b><p></p><small></small></div>';box.querySelector('p').textContent=j.data.suggestion||'';box.querySelector('small').textContent='Confianza: '+(j.data.confidence||'n/a')+(j.data.source?' · Fuente: '+j.data.source:' · Transferencia humana');showNotify('success','Prueba de NIVO completada',(j.data.source?'Fuente: '+j.data.source+' · ':'')+'Confianza: '+(j.data.confidence||'n/a'));});
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

// Premium omnichannel inbox: persistent views, categories, lifecycle and secure actions.
(()=>{
 const form=document.getElementById('inboxFilters'); if(!form)return;
 const post=async fd=>{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd});let j={};try{j=await r.json()}catch(_){j={ok:false,message:'El servidor devolvió una respuesta no válida.'}}return j};
 const savePrefs=async()=>{const fd=new FormData();fd.append('action','inbox_preferences_save');fd.append('channel_type',form.channel?.value||'all');fd.append('assignment_filter',form.assignment?.value||'all');fd.append('priority_filter',form.priority?.value||'all');fd.append('category_id',form.category?.value||'0');fd.append('state_filter',form.state?.value||'active');fd.append('attention_filter',form.attention?.value||'all');try{await post(fd)}catch(_){}};
 form.querySelectorAll('.inbox-filter').forEach(el=>el.addEventListener('change',async()=>{await savePrefs();form.submit()}));
 document.querySelector('.clear-inbox-filters')?.addEventListener('click',async()=>{if(form.channel)form.channel.value='all';if(form.assignment)form.assignment.value='all';if(form.priority)form.priority.value='all';if(form.category)form.category.value='0';if(form.state)form.state.value='active';if(form.attention)form.attention.value='all';await savePrefs();location.href='?page=inbox'});
 const toggle=document.getElementById('bulkToggle'),bar=document.getElementById('bulkBar'),count=document.getElementById('bulkCount'),list=document.querySelector('.conv-list');
 const selected=()=>[...document.querySelectorAll('.conversation .bulk-check input:checked')].map(x=>x.closest('.conversation')?.dataset.conversationId).filter(Boolean);
 const update=()=>{if(count)count.textContent=selected().length};
 toggle?.addEventListener('click',()=>{const on=!list.classList.contains('bulk-mode');list.classList.toggle('bulk-mode',on);if(bar)bar.hidden=!on;if(!on)document.querySelectorAll('.bulk-check input').forEach(x=>x.checked=false);update()});
 document.querySelectorAll('.bulk-check input').forEach(x=>{x.addEventListener('click',e=>e.stopPropagation());x.addEventListener('change',update)});
 const bulk=async action=>{const ids=selected();if(!ids.length){showNotify('warning','Sin selección','Selecciona al menos una conversación.');return}const title=action==='resolve'?'Resolver conversaciones':action==='archive'?'Archivar conversaciones':'Asignarme conversaciones';const ask=await Swal.fire({title,text:`Se aplicará a ${ids.length} conversación(es).`,icon:'question',showCancelButton:true,confirmButtonText:'Continuar',cancelButtonText:'Cancelar',allowOutsideClick:false});if(!ask.isConfirmed)return;const fd=new FormData();fd.append('action','conversation_bulk_action');fd.append('bulk_action',action);ids.forEach(id=>fd.append('conversation_ids[]',id));const j=await post(fd);showNotify(j.ok?'success':'error',j.ok?'Actualizado':'Error',j.message);if(j.ok)setTimeout(()=>location.reload(),300)};
 document.getElementById('bulkAssign')?.addEventListener('click',()=>bulk('assign_me'));
 document.getElementById('bulkResolve')?.addEventListener('click',()=>bulk('resolve'));
 document.getElementById('bulkArchive')?.addEventListener('click',()=>bulk('archive'));
 document.querySelectorAll('.conversation-state-action').forEach(btn=>btn.addEventListener('click',async()=>{
   const action=btn.dataset.action,cid=btn.dataset.conversation;
   const labels={unread:['Marcar como no leída','La conversación volverá a destacarse en la bandeja.'],resolve:['Resolver conversación','La conversación saldrá de la vista activa, pero conservará todo el historial.'],reopen:['Reabrir conversación','La conversación volverá a la vista activa.'],archive:['Archivar conversación','Se ocultará de la vista activa y podrás restaurarla desde Archivadas.'],restore:['Restaurar conversación','Volverá a estar disponible en la bandeja.']};
   const meta=labels[action]||['Actualizar conversación','¿Deseas continuar?'];
   const ask=await Swal.fire({title:meta[0],text:meta[1],icon:'question',showCancelButton:true,confirmButtonText:'Sí, continuar',cancelButtonText:'Cancelar',allowOutsideClick:false});
   if(!ask.isConfirmed)return;
   const fd=new FormData();fd.append('action','conversation_mark_state');fd.append('conversation_id',cid);fd.append('conversation_action',action);
   const j=await post(fd);showNotify(j.ok?'success':'error',j.ok?'Conversación actualizada':'Error',j.message);if(j.ok)setTimeout(()=>location.href='?page=inbox',350);
 }));
 document.querySelector('.conversation-delete-action')?.addEventListener('click',async e=>{
   const cid=e.currentTarget.dataset.conversation;
   const ask=await Swal.fire({title:'Eliminar conversación',html:'Esta acción la retirará de la operación normal y dejará registro de auditoría.<br><b>Escribe tu contraseña para autorizar.</b>',icon:'warning',input:'password',inputPlaceholder:'Contraseña actual',inputAttributes:{autocomplete:'current-password'},showCancelButton:true,confirmButtonText:'Autorizar y eliminar',cancelButtonText:'Cancelar',confirmButtonColor:'#b42318',allowOutsideClick:false,preConfirm:value=>{if(!value){Swal.showValidationMessage('Escribe tu contraseña.');return false}return value}});
   if(!ask.isConfirmed)return;
   const fd=new FormData();fd.append('action','conversation_secure_delete');fd.append('conversation_id',cid);fd.append('password',ask.value);
   const j=await post(fd);showNotify(j.ok?'success':'error',j.ok?'Conversación eliminada':'No se pudo eliminar',j.message);if(j.ok)setTimeout(()=>location.href='?page=inbox',450);
 });
 document.getElementById('nivoSummaryBtn')?.addEventListener('click',()=>{const box=document.getElementById('nivoSummary'),msgs=[...document.querySelectorAll('#messages .message-wrap')].slice(-6).map(x=>x.innerText.trim()).filter(Boolean);if(!box)return;box.hidden=false;box.innerHTML=msgs.length?`<i class="fa-solid fa-wand-magic-sparkles"></i><div><b>Resumen rápido para transferencia</b><p>${msgs.map(x=>x.replace(/\s+/g,' ')).join(' · ').slice(0,700)}</p><small>Resumen local de los últimos mensajes; no inventa información fuera de la conversación.</small></div>`:'<div>No hay mensajes para resumir.</div>'});
 const input=document.getElementById('messageInput'),messageForm=document.getElementById('messageForm');
 input?.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key==='Enter'){e.preventDefault();messageForm?.requestSubmit()}});
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
// NIVO Web Chat uses its dedicated configurator instead of the generic provider modal.
document.querySelectorAll('.channel-add[data-channel="webchat"],.channel-config[data-channel="webchat"],.channel-catalog-item[data-channel="webchat"]').forEach(el=>el.addEventListener('click',e=>{e.preventDefault();e.stopImmediatePropagation();location.href='?page=webchat'} ,true));

// ZYNKO V2.19 — acciones premium de usuarios
(()=>{const postAction=async(data,ok)=>{const fd=new FormData();Object.entries(data).forEach(([k,v])=>fd.append(k,v));try{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd});const j=await r.json();if(!j.ok)throw new Error(j.message||'No se pudo completar la acción.');window.showNotify?showNotify('success','Listo',j.message||ok):void 0;setTimeout(()=>location.reload(),650)}catch(e){window.showNotify?showNotify('error','Error',e.message):void 0}};
 const closeSmartMenus=()=>document.querySelectorAll('.user-action-wrap.open').forEach(w=>{w.classList.remove('open');const t=w.querySelector('.user-action-toggle'),m=w.querySelector('.user-action-menu');t?.setAttribute('aria-expanded','false');if(m){m.classList.remove('user-menu-smart');m.style.removeProperty('--zynko-menu-left');m.style.removeProperty('--zynko-menu-top');m.style.removeProperty('display');m.style.removeProperty('visibility');m.removeAttribute('data-placement')}});
 const placeMenu=wrap=>{const toggle=wrap.querySelector('.user-action-toggle'),menu=wrap.querySelector('.user-action-menu');if(!toggle||!menu)return;menu.classList.add('user-menu-smart');menu.style.visibility='hidden';menu.style.display='grid';const tr=toggle.getBoundingClientRect(),mw=Math.min(300,window.innerWidth-24),mh=Math.min(menu.scrollHeight||280,420,window.innerHeight-24);const gap=8,spaceBelow=window.innerHeight-tr.bottom,spaceAbove=tr.top;let top,placement;if(spaceBelow>=mh+gap||spaceBelow>=spaceAbove){top=Math.min(window.innerHeight-mh-12,tr.bottom+gap);placement='bottom'}else{top=Math.max(12,tr.top-mh-gap);placement='top'}let left=tr.right-mw;if(left<12)left=12;if(left+mw>window.innerWidth-12)left=window.innerWidth-mw-12;menu.style.setProperty('--zynko-menu-left',left+'px');menu.style.setProperty('--zynko-menu-top',top+'px');menu.dataset.placement=placement;menu.style.visibility=''};
 document.addEventListener('click',e=>{const toggle=e.target.closest('.user-action-toggle');if(toggle){const wrap=toggle.closest('.user-action-wrap'),open=!wrap.classList.contains('open');closeSmartMenus();if(open){wrap.classList.add('open');toggle.setAttribute('aria-expanded','true');placeMenu(wrap)}return}if(!e.target.closest('.user-action-menu'))closeSmartMenus();const reset=e.target.closest('.user-reset-password');if(reset){const go=()=>postAction({action:'user_reset_password',user_id:reset.dataset.id},'Enlace enviado.');if(window.Swal)Swal.fire({title:'Restablecer contraseña',text:'Se enviará un enlace seguro al correo del usuario.',icon:'question',showCancelButton:true,confirmButtonText:'Enviar enlace',cancelButtonText:'Cancelar'}).then(r=>r.isConfirmed&&go());else go()}const revoke=e.target.closest('.user-revoke-sessions');if(revoke){const go=()=>postAction({action:'user_revoke_sessions',user_id:revoke.dataset.id},'Sesiones cerradas.');if(window.Swal)Swal.fire({title:'Cerrar sesiones',text:'El usuario tendrá que iniciar sesión nuevamente.',icon:'warning',showCancelButton:true,confirmButtonText:'Cerrar sesiones',cancelButtonText:'Cancelar'}).then(r=>r.isConfirmed&&go());else go()}});
 window.addEventListener('resize',closeSmartMenus,{passive:true});window.addEventListener('scroll',closeSmartMenus,true);
})();
// V2.21 · close action dropdowns, active sessions and account dashboard preferences
(()=>{const closeUserMenus=()=>document.querySelectorAll('.user-action-wrap.open').forEach(w=>{w.classList.remove('open');w.querySelector('.user-action-toggle')?.setAttribute('aria-expanded','false');const m=w.querySelector('.user-action-menu');if(m){m.classList.remove('user-menu-smart');m.style.removeProperty('--zynko-menu-left');m.style.removeProperty('--zynko-menu-top');m.style.removeProperty('display');m.style.removeProperty('visibility');m.removeAttribute('data-placement')}});document.addEventListener('click',e=>{if(e.target.closest('.user-action-menu button'))closeUserMenus()},true);
const sessionEscape=(v='')=>String(v??'').replace(/[&<>'\"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','\"':'&quot;'}[c]));
const sessionDevice=(ua='')=>{const u=String(ua).toLowerCase();let browser=u.includes('edg/')?'Microsoft Edge':u.includes('chrome/')?'Google Chrome':u.includes('firefox/')?'Mozilla Firefox':u.includes('safari/')?'Safari':'Navegador';let os=u.includes('windows')?'Windows':u.includes('android')?'Android':u.includes('iphone')||u.includes('ipad')?'iOS/iPadOS':u.includes('mac os')?'macOS':u.includes('linux')?'Linux':'Sistema desconocido';let device=u.includes('mobile')||u.includes('android')||u.includes('iphone')?'Móvil':'Computadora';return {browser,os,device}};
const sessionsEmpty=(message='Este usuario no tiene sesiones activas en este momento.')=>`<div class="sessions-empty"><span class="sessions-empty-icon"><i class="fa-solid fa-laptop-file"></i></span><b>Sin sesiones activas</b><span>${sessionEscape(message)}</span></div>`;
const renderSessions=(rows,uid)=>{const list=document.querySelector('#sessionsList'),modal=document.querySelector('#sessionsModal'),allBtn=modal?.querySelector('.session-revoke-all-modal');if(allBtn){allBtn.hidden=!rows.length;allBtn.dataset.user=uid}if(!list)return;list.innerHTML=rows.length?'<div class="session-list">'+rows.map(s=>{const d=sessionDevice(s.user_agent||'');return `<article class="session-item"><span class="session-icon"><i class="fa-solid ${d.device==='Móvil'?'fa-mobile-screen-button':'fa-laptop'}"></i></span><div class="session-main"><div class="session-title-row"><b>${sessionEscape(d.device+' · '+d.browser)}</b><span class="ui-badge ${Number(s.remember_me)?'success':'muted'}">${Number(s.remember_me)?'Persistente':'Temporal'}</span></div><div class="session-meta-grid"><span><i class="fa-solid fa-location-dot"></i><b> IP</b> ${sessionEscape(s.ip_address||'No disponible')}</span><span><i class="fa-solid fa-desktop"></i><b> Sistema</b> ${sessionEscape(d.os)}</span><span><i class="fa-regular fa-clock"></i><b> Inicio</b> ${sessionEscape(s.created_at||'No disponible')}</span><span><i class="fa-solid fa-hourglass-end"></i><b> Expira</b> ${sessionEscape(s.expires_at||'No disponible')}</span></div><details><summary><i class="fa-solid fa-circle-info"></i> Detalles técnicos</summary><small>${sessionEscape(s.user_agent||'User-Agent no disponible')}</small></details></div><button type="button" class="soft session-revoke-one" data-session="${Number(s.id)}" data-user="${Number(uid)}"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</button></article>`}).join('')+'</div>':sessionsEmpty()};
document.querySelectorAll('.user-view-sessions').forEach(btn=>btn.addEventListener('click',async()=>{closeUserMenus();const uid=btn.dataset.id,list=document.querySelector('#sessionsList'),modal=document.querySelector('#sessionsModal'),allBtn=modal?.querySelector('.session-revoke-all-modal');if(allBtn){allBtn.hidden=true;allBtn.dataset.user=uid}if(list)list.innerHTML='<div class="sessions-loading"><i class="fa-solid fa-spinner fa-spin"></i><b>Cargando sesiones activas...</b><span>Consultando los accesos vigentes de este usuario.</span></div>';modal?.classList.add('open');document.body.classList.add('modal-open');const fd=new FormData();fd.append('action','user_sessions_list');fd.append('user_id',uid);try{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd}),j=await r.json();if(!j.ok)throw new Error(j.message||'No se pudieron consultar las sesiones.');renderSessions(Array.isArray(j.data?.sessions)?j.data.sessions:[],uid)}catch(err){if(list)list.innerHTML=`<div class="sessions-empty error"><span class="sessions-empty-icon"><i class="fa-solid fa-triangle-exclamation"></i></span><b>No se pudieron cargar las sesiones</b><span>${sessionEscape(err.message)}</span></div>`}}));
document.addEventListener('click',async e=>{const b=e.target.closest('.session-revoke-one');if(!b)return;const go=async()=>{const fd=new FormData();fd.append('action','user_session_revoke');fd.append('session_id',b.dataset.session);fd.append('user_id',b.dataset.user);try{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd}),j=await r.json();showNotify(j.ok?'success':'error',j.ok?'Sesión cerrada':'Error',j.message);if(j.ok){b.closest('.session-item')?.remove();const remain=document.querySelectorAll('#sessionsList .session-item').length;if(!remain){document.querySelector('#sessionsList').innerHTML=sessionsEmpty('La última sesión activa fue revocada.');const all=document.querySelector('#sessionsModal .session-revoke-all-modal');if(all)all.hidden=true}}}catch(err){showNotify('error','Error',err.message)}};if(window.Swal)Swal.fire({title:'Cerrar esta sesión',text:'Solo este acceso será revocado.',icon:'warning',showCancelButton:true,confirmButtonText:'Cerrar sesión',cancelButtonText:'Cancelar'}).then(r=>r.isConfirmed&&go());else go()});
document.addEventListener('click',e=>{const b=e.target.closest('.session-revoke-all-modal');if(!b)return;const uid=b.dataset.user;if(!uid)return;const go=async()=>{const fd=new FormData();fd.append('action','user_revoke_sessions');fd.append('user_id',uid);try{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd}),j=await r.json();if(!j.ok)throw new Error(j.message||'No se pudieron cerrar las sesiones.');showNotify('success','Sesiones cerradas',j.message);document.querySelector('#sessionsList').innerHTML=sessionsEmpty('Todos los accesos de este usuario fueron revocados.');b.hidden=true;}catch(err){showNotify('error','Error',err.message)}};if(window.Swal)Swal.fire({title:'Cerrar todas las sesiones',text:'Se revocarán todos los accesos activos de este usuario.',icon:'warning',showCancelButton:true,confirmButtonText:'Cerrar todas',cancelButtonText:'Cancelar'}).then(r=>r.isConfirmed&&go());else go()});
document.querySelector('.dashboard-customize')?.addEventListener('click',()=>document.querySelector('#dashboardModal')?.classList.add('open'));document.querySelector('#dashboardPrefs')?.addEventListener('submit',async e=>{e.preventDefault();const fd=new FormData(e.currentTarget);fd.append('action','dashboard_preferences_save');try{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd}),j=await r.json();if(!j.ok)throw new Error(j.message);showNotify('success','Dashboard actualizado',j.message);setTimeout(()=>location.reload(),400)}catch(err){showNotify('error','Error',err.message)}});
function initLegalRichEditor(){const form=document.querySelector('#legalTermsForm'),editor=document.querySelector('#legalRichEditor'),source=document.querySelector('#legalHtmlSource'),hidden=document.querySelector('#legalTermsContentInput'),shell=document.querySelector('.legal-rich-editor-shell'),counter=document.querySelector('#legalEditorCounter');if(!form||!editor||!source||!hidden||!shell)return;const updateCounter=()=>{const text=(shell.classList.contains('source-mode')?source.value:editor.innerText||'').trim();if(counter)counter.textContent=new Intl.NumberFormat('es-HN').format(text.length)+' caracteres'};const syncToSource=()=>{source.value=editor.innerHTML.trim();updateCounter()};const syncToEditor=()=>{editor.innerHTML=source.value.trim();updateCounter()};document.querySelectorAll('[data-editor-cmd]').forEach(btn=>btn.addEventListener('click',()=>{editor.focus();document.execCommand(btn.dataset.editorCmd,false,null);syncToSource()}));document.querySelector('[data-editor-block]')?.addEventListener('change',e=>{editor.focus();document.execCommand('formatBlock',false,e.target.value);syncToSource()});document.querySelectorAll('[data-editor-align]').forEach(btn=>btn.addEventListener('click',()=>{editor.focus();const map={left:'justifyLeft',center:'justifyCenter',right:'justifyRight',justify:'justifyFull'};document.execCommand(map[btn.dataset.editorAlign]||'justifyLeft',false,null);syncToSource()}));document.querySelector('[data-editor-link]')?.addEventListener('click',()=>{editor.focus();const url=prompt('Pega el enlace completo (https://...)');if(url&&/^(https?:\/\/|mailto:|tel:|\/)/i.test(url)){document.execCommand('createLink',false,url);syncToSource()}else if(url){showNotify('warning','Enlace no válido','Usa una dirección https://, mailto:, tel: o una ruta interna.')}});document.querySelector('[data-editor-source]')?.addEventListener('click',e=>{const entering=!shell.classList.contains('source-mode');if(entering){syncToSource();shell.classList.add('source-mode');source.focus()}else{syncToEditor();shell.classList.remove('source-mode');editor.focus()}e.currentTarget.classList.toggle('active',entering)});editor.addEventListener('input',syncToSource);source.addEventListener('input',updateCounter);form.addEventListener('submit',()=>{if(shell.classList.contains('source-mode'))syncToEditor();hidden.value=editor.innerHTML.trim()},{capture:true});syncToSource()}
initLegalRichEditor();
document.querySelector('#legalTermsForm')?.addEventListener('submit',async e=>{e.preventDefault();const form=e.currentTarget,btn=form.querySelector('button[type=submit]'),old=btn?.innerHTML;if(btn){btn.disabled=true;btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Publicando…'}try{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:new FormData(form)}),j=await r.json();if(!j.ok)throw new Error(j.message||'No se pudieron publicar los términos.');showNotify('success','Términos publicados',j.message);setTimeout(()=>location.reload(),500)}catch(err){showNotify('error','No se pudo publicar',err.message)}finally{if(btn){btn.disabled=false;btn.innerHTML=old||'<i class="fa-solid fa-cloud-arrow-up"></i> Publicar nueva versión'}}});})();

// V2.22.0 · modal icons + platform version
document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('.modal-head>div:first-child').forEach(h=>{if(h.querySelector('.modal-title-icon'))return;h.classList.add('modal-title-with-icon');const icon=document.createElement('span');icon.className='modal-title-icon';icon.innerHTML='<i class="fa-solid fa-layer-group"></i>';h.prepend(icon)});document.getElementById('systemVersionForm')?.addEventListener('submit',async e=>{e.preventDefault();const f=e.currentTarget,b=f.querySelector('button[type=submit]'),old=b.innerHTML;b.disabled=true;b.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Guardando…';try{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:new FormData(f)}),j=await r.json();showNotify(j.ok?'success':'error',j.ok?'Versión actualizada':'Error',j.message);if(j.ok)setTimeout(()=>location.reload(),450)}catch(x){showNotify('error','Error',x.message)}finally{b.disabled=false;b.innerHTML=old}})});


// ZYNKO V2.30.1 · Editor visual de características del plan
(()=>{
 const form=document.getElementById('planForm'),input=document.getElementById('planFeaturesInput'),list=document.getElementById('planFeaturesList'),empty=document.getElementById('planFeaturesEmpty'),counter=document.getElementById('planFeaturesCount'),addBtn=document.getElementById('planFeatureAdd');
 if(!form||!input||!list)return;
 const clean=s=>String(s||'').replace(/\s+/g,' ').trim();
 const rows=()=>[...list.querySelectorAll('.plan-feature-row')];
 const sync=()=>{
   const values=rows().map(r=>clean(r.querySelector('input')?.value)).filter(Boolean);
   input.value=values.join('\n');
   if(counter)counter.textContent=values.length+' '+(values.length===1?'característica':'características');
   if(empty)empty.hidden=rows().length>0;
   rows().forEach((r,i)=>{const n=r.querySelector('.plan-feature-number');if(n)n.textContent=String(i+1).padStart(2,'0');const up=r.querySelector('[data-feature-up]'),down=r.querySelector('[data-feature-down]');if(up)up.disabled=i===0;if(down)down.disabled=i===rows().length-1;});
 };
 const makeRow=(value='')=>{
   const row=document.createElement('div');row.className='plan-feature-row';
   row.innerHTML='<span class="plan-feature-number">01</span><input type="text" maxlength="180" placeholder="Ej. Soporte prioritario" aria-label="Característica del plan"><div class="plan-feature-actions"><button type="button" class="icon-btn" data-feature-up title="Subir"><i class="fa-solid fa-arrow-up"></i></button><button type="button" class="icon-btn" data-feature-down title="Bajar"><i class="fa-solid fa-arrow-down"></i></button><button type="button" class="icon-btn danger" data-feature-remove title="Eliminar"><i class="fa-solid fa-trash"></i></button></div>';
   const field=row.querySelector('input');field.value=value;
   field.addEventListener('input',sync);
   row.querySelector('[data-feature-remove]').addEventListener('click',()=>{row.remove();sync();});
   row.querySelector('[data-feature-up]').addEventListener('click',()=>{const prev=row.previousElementSibling;if(prev)list.insertBefore(row,prev);sync();});
   row.querySelector('[data-feature-down]').addEventListener('click',()=>{const next=row.nextElementSibling;if(next)list.insertBefore(next,row);sync();});
   list.appendChild(row);sync();return row;
 };
 const load=()=>{list.innerHTML='';const vals=String(input.value||'').split(/\r?\n/).map(clean).filter(Boolean);vals.forEach(makeRow);sync();};
 const reset=()=>{input.value='';list.innerHTML='';sync();};
 addBtn?.addEventListener('click',()=>{const row=makeRow('');row.querySelector('input')?.focus();});
 form.addEventListener('submit',sync,true);
 document.querySelectorAll('.plan-edit').forEach(btn=>btn.addEventListener('click',()=>setTimeout(load,0)));
 document.querySelectorAll('[data-open="planModal"]').forEach(btn=>btn.addEventListener('click',()=>setTimeout(()=>{form.reset();if(form.plan_id)form.plan_id.value='';reset();document.getElementById('planTitle').textContent='Crear plan';if(window.jQuery)jQuery(form).find('.select2').trigger('change');},0)));
 window.ZynkoPlanFeatures={load,reset,sync,add:makeRow};
 sync();
})();

// ZYNKO V2.31.6 — Menú contextual propio de conversaciones (clic derecho / pulsación secundaria).
(()=>{
 const menu=document.getElementById('conversationContextMenu'); if(!menu)return;
 let ctx={id:'',status:'open',archived:false,href:''};
 const close=()=>{menu.classList.remove('open');menu.setAttribute('aria-hidden','true')};
 const place=(x,y)=>{menu.classList.add('open');menu.setAttribute('aria-hidden','false');const r=menu.getBoundingClientRect(),pad=10;menu.style.left=Math.max(pad,Math.min(x,innerWidth-r.width-pad))+'px';menu.style.top=Math.max(pad,Math.min(y,innerHeight-r.height-pad))+'px'};
 const refreshLabels=()=>{const resolve=menu.querySelector('[data-context-action="resolve"]'),archive=menu.querySelector('[data-context-action="archive"]');if(resolve){resolve.querySelector('b').textContent=['resolved','closed'].includes(ctx.status)?'Reabrir':'Resolver';resolve.querySelector('small').textContent=['resolved','closed'].includes(ctx.status)?'Devolver a atención activa':'Finalizar atención sin borrar'}if(archive){archive.querySelector('b').textContent=ctx.archived?'Restaurar':'Archivar';archive.querySelector('small').textContent=ctx.archived?'Regresar a la bandeja':'Ocultar de la vista activa'}};
 const targetInfo=el=>{const c=el.closest('.conversation');if(c)return{id:c.dataset.conversationId||'',status:c.dataset.conversationStatus||'open',archived:c.dataset.conversationArchived==='1',href:c.getAttribute('href')||''};const chat=el.closest('.chat[data-current-conversation]');if(chat)return{id:chat.dataset.currentConversation||'',status:chat.dataset.currentStatus||'open',archived:chat.dataset.currentArchived==='1',href:'?page=inbox&conversation='+encodeURIComponent(chat.dataset.currentConversation||'')};return null};
 document.addEventListener('contextmenu',e=>{const data=targetInfo(e.target);if(!data)return;e.preventDefault();ctx=data;refreshLabels();place(e.clientX,e.clientY)});
 document.addEventListener('click',e=>{if(!menu.contains(e.target))close()});window.addEventListener('blur',close);window.addEventListener('resize',close);document.addEventListener('scroll',close,true);document.addEventListener('keydown',e=>{if(e.key==='Escape')close()});
 const post=async fd=>{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd});let j={};try{j=await r.json()}catch(_){j={ok:false,message:'El servidor devolvió una respuesta no válida.'}}return j};
 menu.addEventListener('click',async e=>{const btn=e.target.closest('[data-context-action]');if(!btn)return;e.stopPropagation();const action=btn.dataset.contextAction;close();if(action==='open'){location.href=ctx.href||('?page=inbox&conversation='+encodeURIComponent(ctx.id));return}if(!ctx.id)return;
   if(action==='delete'){const ask=await Swal.fire({title:'Eliminar conversación',html:'Esta acción la retirará de la operación normal y dejará registro de auditoría.<br><b>Escribe tu contraseña para autorizar.</b>',icon:'warning',input:'password',inputPlaceholder:'Contraseña actual',inputAttributes:{autocomplete:'current-password'},showCancelButton:true,confirmButtonText:'Autorizar y eliminar',cancelButtonText:'Cancelar',confirmButtonColor:'#b42318',allowOutsideClick:false,preConfirm:v=>{if(!v){Swal.showValidationMessage('Escribe tu contraseña.');return false}return v}});if(!ask.isConfirmed)return;const fd=new FormData();fd.append('action','conversation_secure_delete');fd.append('conversation_id',ctx.id);fd.append('password',ask.value);const j=await post(fd);showNotify(j.ok?'success':'error',j.ok?'Conversación eliminada':'No se pudo eliminar',j.message);if(j.ok)setTimeout(()=>location.href='?page=inbox',350);return}
   let real=action;if(action==='resolve'&&['resolved','closed'].includes(ctx.status))real='reopen';if(action==='archive'&&ctx.archived)real='restore';const names={unread:'Marcar como no leída',resolve:'Resolver conversación',reopen:'Reabrir conversación',archive:'Archivar conversación',restore:'Restaurar conversación'};const ask=await Swal.fire({title:names[real]||'Actualizar conversación',text:'¿Deseas continuar?',icon:'question',showCancelButton:true,confirmButtonText:'Sí, continuar',cancelButtonText:'Cancelar',allowOutsideClick:false});if(!ask.isConfirmed)return;const fd=new FormData();fd.append('action','conversation_mark_state');fd.append('conversation_id',ctx.id);fd.append('conversation_action',real);const j=await post(fd);showNotify(j.ok?'success':'error',j.ok?'Conversación actualizada':'Error',j.message);if(j.ok)setTimeout(()=>location.reload(),300)
 });
})();

/* ZYNKO V2.31.9 · Administración de empresas */
(()=>{
 const q=(s,c=document)=>c.querySelector(s),qa=(s,c=document)=>[...c.querySelectorAll(s)],esc=v=>String(v??'').replace(/[&<>'"]/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[m]));
 const post=async fd=>{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd});let j;try{j=await r.json()}catch(_){throw new Error('El servidor devolvió una respuesta no válida.')}if(!j.ok)throw new Error(j.message||'No se pudo completar la acción.');return j};
 const openModal=id=>{if(window.ZynkoModal?.open)window.ZynkoModal.open('#'+id);else{q('#'+id)?.classList.add('open');document.body.classList.add('modal-open')}};
 const bindForm=(id,success)=>q('#'+id)?.addEventListener('submit',async e=>{e.preventDefault();const f=e.currentTarget,b=f.querySelector('button[type=submit],button:not([type])'),old=b?.innerHTML;if(b){b.disabled=true;b.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Guardando…'}try{const j=await post(new FormData(f));showNotify('success','Listo',j.message||success);setTimeout(()=>location.reload(),450)}catch(err){showNotify('error','No se pudo guardar',err.message)}finally{if(b){b.disabled=false;b.innerHTML=old}}});
 if(!q('#companyGrid'))return;
 bindForm('companyCreateForm','Empresa creada.');bindForm('companyEditForm','Empresa actualizada.');bindForm('companyPlanForm','Plan asignado.');
 q('#companyGeneratePassword')?.addEventListener('click',()=>{const A='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%',a=new Uint32Array(14);crypto.getRandomValues(a);q('#companyOwnerPassword').value=[...a].map(n=>A[n%A.length]).join('')});
 qa('.company-edit-btn').forEach(b=>b.addEventListener('click',()=>{const f=q('#companyEditForm');f.tenant_id.value=b.dataset.tenant||'';f.company_name.value=b.dataset.name||'';f.business_id.value=b.dataset.business||'';f.contact_phone.value=b.dataset.phone||'';f.status.value=b.dataset.status||'active';if(window.jQuery)window.jQuery(f.status).trigger('change.select2');openModal('companyEditModal');setTimeout(()=>f.company_name.focus(),80)}));
 qa('.company-plan-btn').forEach(b=>b.addEventListener('click',()=>{const f=q('#companyPlanForm');f.tenant_id.value=b.dataset.tenant||'';f.plan_id.value=b.dataset.plan||'';f.status.value=b.dataset.substatus||'active';q('#companyPlanSubtitle').textContent='Plan para '+(b.dataset.name||'esta empresa')+'. Todos sus usuarios heredan estas capacidades.';if(window.jQuery){window.jQuery(f.plan_id).trigger('change.select2');window.jQuery(f.status).trigger('change.select2')}openModal('companyPlanModal')}));
 const renderUsers=(tid,name)=>{const list=q('#companyUsersList'),rows=window.ZYNKO_COMPANY_USERS?.[tid]||window.ZYNKO_COMPANY_USERS?.[String(tid)]||[];q('#companyUsersTitle').textContent='Usuarios · '+name;if(!rows.length){list.innerHTML='<div class="company-user-empty"><i class="fa-solid fa-user-slash"></i><b>Sin usuarios vinculados</b></div>';return}list.innerHTML=rows.map(u=>`<article class="company-user-card" data-user="${Number(u.id)}" data-tenant="${Number(tid)}"><div class="company-user-avatar">${esc((u.name||'U').split(/\s+/).slice(0,2).map(x=>x[0]||'').join('').toUpperCase())}</div><div class="company-user-copy"><div><b>${esc(u.name)}</b>${Number(u.is_owner)===1?'<span class="ui-badge info">Principal</span>':''}</div><small>${esc(u.email)}</small><em>${u.last_login_at?'Último acceso '+esc(u.last_login_at):'Sin accesos registrados'} · ${Number(u.active_sessions||0)} sesión(es) activa(s)</em></div><div class="company-user-controls"><select class="company-user-role" ${Number(u.is_owner)===1?'disabled':''}><option value="owner" ${u.role_code==='owner'?'selected':''}>Owner</option><option value="admin" ${u.role_code==='admin'?'selected':''}>Admin</option><option value="supervisor" ${u.role_code==='supervisor'?'selected':''}>Supervisor</option><option value="agent" ${u.role_code==='agent'?'selected':''}>Agente</option></select><select class="company-user-status" ${Number(u.is_owner)===1?'disabled':''}><option value="active" ${u.status==='active'?'selected':''}>Activo</option><option value="disabled" ${u.status==='disabled'?'selected':''}>Deshabilitado</option></select></div><div class="company-user-actions"><button type="button" class="soft company-user-save"><i class="fa-solid fa-floppy-disk"></i> Guardar</button><button type="button" class="soft company-user-password"><i class="fa-solid fa-key"></i> Nueva contraseña</button><button type="button" class="primary company-user-assist" ${u.status!=='active'?'disabled':''}><i class="fa-solid fa-right-to-bracket"></i> Entrar como usuario</button></div></article>`).join('')};
 qa('.company-users-btn').forEach(b=>b.addEventListener('click',()=>{renderUsers(b.dataset.tenant,b.dataset.name||'Empresa');openModal('companyUsersModal')}));
 q('#companyUsersList')?.addEventListener('click',async e=>{const card=e.target.closest('.company-user-card');if(!card)return;const tid=card.dataset.tenant,uid=card.dataset.user;
  if(e.target.closest('.company-user-save')){const fd=new FormData();fd.append('action','company_user_manage');fd.append('tenant_id',tid);fd.append('user_id',uid);fd.append('role',q('.company-user-role',card).value);fd.append('status',q('.company-user-status',card).value);try{const j=await post(fd);showNotify('success','Usuario actualizado',j.message);setTimeout(()=>location.reload(),400)}catch(err){showNotify('error','No se pudo actualizar',err.message)}return}
  if(e.target.closest('.company-user-password')){const ask=await Swal.fire({title:'Generar contraseña temporal',html:'La contraseña actual <b>no puede visualizarse</b>. ZYNKO generará una nueva, cerrará las sesiones activas y la mostrará una sola vez.',icon:'warning',showCancelButton:true,confirmButtonText:'Generar contraseña',cancelButtonText:'Cancelar',allowOutsideClick:false});if(!ask.isConfirmed)return;const fd=new FormData();fd.append('action','company_user_temp_password');fd.append('tenant_id',tid);fd.append('user_id',uid);try{const j=await post(fd),pwd=j.data?.temporary_password||'';await Swal.fire({title:'Contraseña temporal creada',html:`<div class="temporary-password-box"><small>${esc(j.data?.user_name||'Usuario')}</small><code id="zynkoTempPassword">${esc(pwd)}</code><p>Guárdala y compártela por un canal seguro. No volverá a mostrarse.</p></div>`,icon:'success',showCancelButton:true,confirmButtonText:'Copiar contraseña',cancelButtonText:'Cerrar',allowOutsideClick:false}).then(async r=>{if(r.isConfirmed){await (window.ZynkoCopyNotify?window.ZynkoCopyNotify(pwd,'Contraseña temporal copiada.'):navigator.clipboard.writeText(pwd))}});}catch(err){showNotify('error','No se pudo restablecer',err.message)}return}
  if(e.target.closest('.company-user-assist')){const ask=await Swal.fire({title:'Entrar en modo asistencia',html:'Verás ZYNKO exactamente dentro de esta empresa y usuario.<br><b>Podrás volver a tu administración desde la barra superior.</b>',icon:'question',showCancelButton:true,confirmButtonText:'Entrar como usuario',cancelButtonText:'Cancelar',allowOutsideClick:false});if(!ask.isConfirmed)return;const fd=new FormData();fd.append('action','company_impersonate');fd.append('tenant_id',tid);fd.append('user_id',uid);try{const j=await post(fd);location.href=j.data?.redirect||'?page=dashboard'}catch(err){showNotify('error','No se pudo iniciar asistencia',err.message)} }
 });
 const cards=qa('.company-card'),empty=q('#companyEmpty'),searchMain=q('#companySearch'),searchQuick=q('#companyQuickSearch'),clearMain=q('#companySearchClear'),clearQuick=q('#companyQuickSearchClear'),statusFilter=q('#companyStatusFilter'),planFilter=q('#companyPlanFilter'),perPageSelect=q('#companyPerPage'),summary=q('#companyDirectorySummary'),pageInfo=q('#companyPaginationInfo'),pagination=q('#companyPagination');
 const companyState={page:1,perPage:9};
 const syncSearchButtons=()=>{const val=(searchQuick?.value||searchMain?.value||'').trim();[clearMain,clearQuick].forEach(btn=>{if(btn)btn.hidden=!val})};
 const syncSearchInputs=(value,source)=>{[searchMain,searchQuick].forEach(inp=>{if(inp&&inp!==source)inp.value=value});syncSearchButtons()};
 const renderPagination=(totalPages,totalItems,startIndex,endIndex)=>{if(summary)summary.textContent=totalItems===1?'1 empresa en directorio':`${totalItems} empresas en directorio`;if(pageInfo)pageInfo.textContent=totalItems?`Mostrando ${startIndex}-${endIndex} de ${totalItems} empresas`:'Mostrando 0 de 0 empresas';if(!pagination)return;pagination.innerHTML='';const makeBtn=(label,page,disabled=false,active=false,icon='')=>{const b=document.createElement('button');b.type='button';b.className='company-page-btn'+(active?' active':'');b.disabled=disabled;b.innerHTML=icon?`<i class="fa-solid ${icon}"></i><span>${label}</span>`:`<span>${label}</span>`;if(!disabled)b.addEventListener('click',()=>{companyState.page=page;applyFilters()});pagination.appendChild(b)};makeBtn('Anterior',Math.max(1,companyState.page-1),companyState.page<=1,false,'fa-chevron-left');const span=2;let from=Math.max(1,companyState.page-span),to=Math.min(totalPages,companyState.page+span);if(from>1)makeBtn('1',1,false,companyState.page===1);if(from>2){const dots=document.createElement('span');dots.className='company-page-dots';dots.textContent='…';pagination.appendChild(dots)}for(let i=from;i<=to;i++)makeBtn(String(i),i,false,companyState.page===i);if(to<totalPages-1){const dots=document.createElement('span');dots.className='company-page-dots';dots.textContent='…';pagination.appendChild(dots)}if(to<totalPages)makeBtn(String(totalPages),totalPages,false,companyState.page===totalPages);makeBtn('Siguiente',Math.min(totalPages,companyState.page+1),companyState.page>=totalPages,false,'fa-chevron-right')};
 const applyFilters=(resetPage=false)=>{if(resetPage)companyState.page=1;const perValue=perPageSelect?.value||'9';companyState.perPage=perValue==='all'?Infinity:(parseInt(perValue,10)||9);const term=(searchQuick?.value||searchMain?.value||'').trim().toLowerCase(),status=statusFilter?.value||'',plan=planFilter?.value||'';const matches=cards.filter(card=>(!term||(card.dataset.search||'').includes(term))&&(!status||card.dataset.status===status)&&(!plan||card.dataset.plan===plan));const total=matches.length,totalPages=companyState.perPage===Infinity?1:Math.max(1,Math.ceil(total/companyState.perPage));if(companyState.page>totalPages)companyState.page=totalPages;const sliceStart=companyState.perPage===Infinity?0:(companyState.page-1)*companyState.perPage,sliceEnd=companyState.perPage===Infinity?total:sliceStart+companyState.perPage;cards.forEach(card=>card.hidden=true);matches.slice(sliceStart,sliceEnd).forEach(card=>card.hidden=false);if(empty)empty.hidden=total!==0;renderPagination(totalPages,total,total?sliceStart+1:0,Math.min(sliceEnd,total));syncSearchButtons()};
 [searchMain,searchQuick].forEach(inp=>inp?.addEventListener('input',e=>{syncSearchInputs(e.currentTarget.value,e.currentTarget);applyFilters(true)}));
 [clearMain,clearQuick].forEach(btn=>btn?.addEventListener('click',()=>{syncSearchInputs('',null);applyFilters(true);(searchQuick||searchMain)?.focus()}));
 statusFilter?.addEventListener('change',()=>applyFilters(true));planFilter?.addEventListener('change',()=>applyFilters(true));perPageSelect?.addEventListener('change',()=>applyFilters(true));q('#companyFilterClear')?.addEventListener('click',()=>{syncSearchInputs('',null);if(statusFilter)statusFilter.value='';if(planFilter)planFilter.value='';if(window.jQuery){window.jQuery('#companyStatusFilter,#companyPlanFilter').trigger('change.select2')}applyFilters(true)});applyFilters(true);
 document.addEventListener('click',e=>{const toggle=e.target.closest('.company-mini-action-toggle');if(toggle){e.stopPropagation();const wrap=toggle.closest('.company-card-actions');const open=!wrap.classList.contains('open');document.querySelectorAll('.company-card-actions.open').forEach(x=>x.classList.remove('open'));wrap.classList.toggle('open',open);toggle.setAttribute('aria-expanded',open?'true':'false');return}if(!e.target.closest('.company-card-actions'))document.querySelectorAll('.company-card-actions.open').forEach(x=>x.classList.remove('open'));});
})();


/* ZYNKO V2.31.25 · Menús de acciones inteligentes portales (Usuarios + Empresas + Suscripciones) */
(()=>{
 const selector='.user-action-wrap,.company-action-wrap,.plan-action-wrap';
 let active=null;
 const restore=()=>{
   if(!active)return;
   const {wrap,toggle,menu,next}=active;
   wrap.classList.remove('open');toggle?.setAttribute('aria-expanded','false');
   menu.classList.remove('zynko-action-portal');menu.style.cssText='';menu.removeAttribute('data-placement');
   if(next&&next.parentNode===wrap)wrap.insertBefore(menu,next);else wrap.appendChild(menu);
   active=null;
 };
 const place=(toggle,menu)=>{
   const gap=10,pad=12,tr=toggle.getBoundingClientRect();
   menu.classList.add('zynko-action-portal');document.body.appendChild(menu);
   menu.style.display='grid';menu.style.visibility='hidden';menu.style.position='fixed';menu.style.zIndex='2147483000';
   const mw=Math.min(310,window.innerWidth-pad*2);menu.style.width=mw+'px';
   const mh=Math.min(menu.scrollHeight||320,440,window.innerHeight-pad*2);
   const below=window.innerHeight-tr.bottom,above=tr.top,right=window.innerWidth-tr.right,left=tr.left;
   let x,y,placement;
   if(below>=mh+gap){placement='bottom';y=tr.bottom+gap;x=tr.right-mw;}
   else if(above>=mh+gap){placement='top';y=tr.top-mh-gap;x=tr.right-mw;}
   else if(right>=mw+gap){placement='right';x=tr.right+gap;y=Math.min(Math.max(pad,tr.top),window.innerHeight-mh-pad);}
   else if(left>=mw+gap){placement='left';x=tr.left-mw-gap;y=Math.min(Math.max(pad,tr.top),window.innerHeight-mh-pad);}
   else{placement=below>=above?'bottom':'top';y=placement==='bottom'?Math.min(window.innerHeight-mh-pad,tr.bottom+gap):Math.max(pad,tr.top-mh-gap);x=tr.right-mw;}
   x=Math.min(Math.max(pad,x),window.innerWidth-mw-pad);y=Math.min(Math.max(pad,y),window.innerHeight-mh-pad);
   menu.style.left=x+'px';menu.style.top=y+'px';menu.style.maxHeight=Math.min(440,window.innerHeight-pad*2)+'px';menu.style.overflow='auto';menu.dataset.placement=placement;menu.style.visibility='visible';
 };
 document.addEventListener('click',e=>{
   const toggle=e.target.closest('.user-action-toggle,.company-action-toggle,.plan-action-toggle');
   if(toggle){
     e.preventDefault();e.stopImmediatePropagation();
     const wrap=toggle.closest(selector);if(!wrap)return;
     if(active?.wrap===wrap){restore();return;}
     restore();
     const menu=wrap.querySelector('.user-action-menu,.company-action-menu,.plan-action-menu');if(!menu)return;
     const next=menu.nextSibling;active={wrap,toggle,menu,next};wrap.classList.add('open');toggle.setAttribute('aria-expanded','true');place(toggle,menu);return;
   }
   if(active && !e.target.closest('.user-action-menu,.company-action-menu,.plan-action-menu'))restore();
 },true);
 document.addEventListener('click',e=>{if(active&&e.target.closest('.user-action-menu button,.company-action-menu button,.plan-action-menu button'))setTimeout(restore,0)},false);
 window.addEventListener('resize',restore,{passive:true});window.addEventListener('scroll',restore,true);window.addEventListener('blur',restore);
 document.addEventListener('keydown',e=>{if(e.key==='Escape')restore()});
})();

/* ZYNKO V2.31.17 · Copiar global con confirmación visual */
(()=>{
 const fallbackCopy=text=>{const ta=document.createElement('textarea');ta.value=text;ta.setAttribute('readonly','');ta.style.position='fixed';ta.style.opacity='0';ta.style.pointerEvents='none';document.body.appendChild(ta);ta.select();ta.setSelectionRange(0,ta.value.length);let ok=false;try{ok=document.execCommand('copy')}catch(_){ok=false}ta.remove();return ok};
 const copyText=async text=>{if(!text)return false;try{if(navigator.clipboard?.writeText){await navigator.clipboard.writeText(text);return true}}catch(_){}return fallbackCopy(text)};
 window.ZynkoCopyText=copyText;
 window.ZynkoCopyNotify=async(text,message='El contenido fue copiado al portapapeles.')=>{const ok=await copyText(text);showNotify(ok?'success':'error',ok?'Copiado':'No se pudo copiar',ok?message:'Tu navegador bloqueó el acceso al portapapeles.');return ok};
 document.addEventListener('click',async e=>{const btn=e.target.closest('[data-copy-target],[data-copy-text]');if(!btn)return;const targetId=btn.dataset.copyTarget||'',target=targetId?document.getElementById(targetId):null,text=(btn.dataset.copyText||target?.textContent||'').trim();if(!text)return;const ok=await window.ZynkoCopyNotify(text,btn.dataset.copyMessage||'El contenido fue copiado al portapapeles.');if(ok){btn.classList.add('copied');setTimeout(()=>btn.classList.remove('copied'),900)}});
})();;


/* ZYNKO V2.31.21 · Suscripciones con regla KPI → filtros → directorio */
(()=>{
 const list=document.getElementById('billingPlanDirectory');if(!list)return;
 const cards=[...list.querySelectorAll('.plan-card')],search=document.getElementById('billingSearch'),clear=document.getElementById('billingSearchClear'),status=document.getElementById('billingStatusFilter'),type=document.getElementById('billingTypeFilter'),per=document.getElementById('billingPerPage'),empty=document.getElementById('billingEmptyState'),summary=document.getElementById('billingDirectorySummary'),pager=document.getElementById('billingPagination');let page=1;
 const renderPager=(pages,total,start,end)=>{if(summary)summary.textContent=`${total} plan${total===1?'':'es'} en directorio`;if(!pager)return;pager.innerHTML='';const add=(label,p,disabled=false,active=false,icon='')=>{const b=document.createElement('button');b.type='button';b.className='company-page-btn'+(active?' active':'');b.disabled=disabled;b.innerHTML=(icon?`<i class="fa-solid ${icon}"></i>`:'')+`<span>${label}</span>`;if(!disabled)b.onclick=()=>{page=p;run()};pager.appendChild(b)};add('Anterior',Math.max(1,page-1),page<=1,false,'fa-chevron-left');for(let i=1;i<=pages;i++){if(pages<=7||i===1||i===pages||Math.abs(i-page)<=1)add(String(i),i,false,i===page);else if((i===2&&page>3)||(i===pages-1&&page<pages-2)){const s=document.createElement('span');s.className='company-page-dots';s.textContent='…';pager.appendChild(s)}}add('Siguiente',Math.min(pages,page+1),page>=pages,false,'fa-chevron-right')};
 const run=(reset=false)=>{if(reset)page=1;const q=(search?.value||'').trim().toLowerCase(),sv=status?.value||'',tv=type?.value||'',pv=per?.value||'6',limit=pv==='all'?Infinity:(parseInt(pv,10)||6);const match=cards.filter(c=>(!q||(c.dataset.search||c.textContent.toLowerCase()).includes(q))&&(!sv||c.dataset.status===sv)&&(!tv||c.dataset.type===tv));const pages=limit===Infinity?1:Math.max(1,Math.ceil(match.length/limit));if(page>pages)page=pages;const start=limit===Infinity?0:(page-1)*limit,end=limit===Infinity?match.length:start+limit;cards.forEach(c=>c.hidden=true);match.slice(start,end).forEach(c=>c.hidden=false);if(empty)empty.hidden=match.length!==0;if(clear)clear.hidden=!q;renderPager(pages,match.length,match.length?start+1:0,Math.min(end,match.length))};
 search?.addEventListener('input',()=>run(true));clear?.addEventListener('click',()=>{search.value='';run(true);search.focus()});status?.addEventListener('change',()=>run(true));type?.addEventListener('change',()=>run(true));per?.addEventListener('change',()=>run(true));document.getElementById('billingFilterClear')?.addEventListener('click',()=>{if(search)search.value='';if(status)status.value='';if(type)type.value='';if(window.jQuery)jQuery('#billingStatusFilter,#billingTypeFilter').trigger('change.select2');run(true)});run(true);
})();

/* ZYNKO V2.31.47 · Fuentes web configurables para NIVO */
(()=>{
 const q=(s,c=document)=>c.querySelector(s),qa=(s,c=document)=>[...c.querySelectorAll(s)];
 const send=async fd=>{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd});let j={ok:false,message:'Respuesta inválida.'};try{j=await r.json()}catch{}return j};
 const form=q('#knowledgeSiteForm'),modal=q('#knowledgeSiteModal');
 if(form){
   form.addEventListener('submit',async e=>{e.preventDefault();const btn=form.querySelector('button[type="submit"]')||form.querySelector('.primary');if(btn){btn.disabled=true;btn.dataset.old=btn.innerHTML;btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Guardando…'}showNotify('info','Fuente web','Validando y guardando la fuente autorizada…');const j=await send(new FormData(form));if(btn){btn.disabled=false;btn.innerHTML=btn.dataset.old||'<i class="fa-solid fa-globe"></i> Guardar fuente'}showNotify(j.ok?'success':'error',j.ok?'Fuente web lista':'No se pudo guardar',j.message);if(j.ok)setTimeout(()=>location.reload(),650)});
   qa('.knowledge-site-edit').forEach(b=>b.addEventListener('click',()=>{let s={};try{s=JSON.parse(b.dataset.site||'{}')}catch{};form.reset();q('[name="action"]',form).value='knowledge_site_update';q('[name="website_id"]',form).value=s.id||0;q('[name="name"]',form).value=s.name||'';q('[name="base_url"]',form).value=s.base_url||'';q('[name="crawl_scope"]',form).value=s.crawl_scope||'domain';q('[name="max_pages"]',form).value=s.max_pages||10;q('[name="refresh_hours"]',form).value=s.refresh_hours||24;q('[name="exclude_paths"]',form).value=s.exclude_paths||'';q('[name="auto_sync"]',form).checked=String(s.auto_sync)==='1';q('[name="active"]',form).checked=String(s.active)!=='0';const syncField=q('#knowledgeSiteSyncNowField');if(syncField)syncField.hidden=true;const title=q('#knowledgeSiteModalTitle');if(title)title.textContent='Editar fuente web';if(window.jQuery)jQuery(form).find('.select2').trigger('change');window.ZynkoModal?.open('#knowledgeSiteModal')}));
   document.querySelector('[data-open="knowledgeSiteModal"]')?.addEventListener('click',()=>{form.reset();q('[name="action"]',form).value='knowledge_site_add';q('[name="website_id"]',form).value='0';q('[name="max_pages"]',form).value='10';q('[name="refresh_hours"]',form).value='24';q('[name="active"]',form).checked=true;const sn=form.querySelector('[name="sync_now"]');if(sn)sn.checked=true;const syncField=q('#knowledgeSiteSyncNowField');if(syncField)syncField.hidden=false;const title=q('#knowledgeSiteModalTitle');if(title)title.textContent='Agregar fuente web';if(window.jQuery)jQuery(form).find('.select2').trigger('change')});
 }
 qa('.knowledge-site-sync').forEach(b=>b.addEventListener('click',async()=>{const fd=new FormData();fd.append('action','knowledge_site_sync');fd.append('website_id',b.dataset.id);b.disabled=true;const old=b.innerHTML;b.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i>';showNotify('info','Sincronizando','NIVO está leyendo el contenido público autorizado.');const j=await send(fd);b.disabled=false;b.innerHTML=old;showNotify(j.ok?'success':'error',j.ok?'Sincronización completada':'No se pudo sincronizar',j.message);if(j.ok)setTimeout(()=>location.reload(),600)}));
 qa('.knowledge-site-delete').forEach(b=>b.addEventListener('click',async()=>{const r=await Swal.fire({title:'Eliminar fuente web',text:'NIVO dejará de utilizar todas las páginas aprendidas desde este sitio.',icon:'warning',showCancelButton:true,confirmButtonText:'Sí, eliminar',cancelButtonText:'Cancelar',allowOutsideClick:false});if(!r.isConfirmed)return;const fd=new FormData();fd.append('action','knowledge_site_delete');fd.append('website_id',b.dataset.id);const j=await send(fd);showNotify(j.ok?'success':'error',j.ok?'Fuente eliminada':'Error',j.message);if(j.ok)setTimeout(()=>location.reload(),450)}));
})();
