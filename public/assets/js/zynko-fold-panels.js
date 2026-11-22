/** ZYNKO — Tarjetas plegables, un solo diseño para todos los módulos.
 * Conserva los nodos y formularios originales (sin clonar controles ni reemplazar eventos).
 */
(()=>{'use strict';
 const start=()=>{
  const page=new URLSearchParams(location.search).get('page')||'';
  const supported=['dashboard','users','companies','chatbot','billing','webchat'];
  if(!supported.includes(page))return;
  const source=document.querySelector('script[src*="zynko-fold-panels.js"]');
  const prefix='zynko:panel:v1:'+String(source?.dataset.tenant||'0')+':'+String(source?.dataset.user||'0')+':'+page+':';
  const get=id=>{try{return localStorage.getItem(prefix+id)}catch(_){return null}};
  const save=(id,value)=>{try{localStorage.setItem(prefix+id,value)}catch(_){}};
  const el=(tag,cls,text)=>{const node=document.createElement(tag);if(cls)node.className=cls;if(text!==undefined)node.textContent=text;return node};
  const icon=(name)=>{const i=el('i','fa-solid fa-'+name);i.setAttribute('aria-hidden','true');return i};
  const toggle=(head,body,id,defaultOpen=true)=>{
    if(!head||!body||head.querySelector('.zynko-fold-button'))return;
    let open=get(id)!=='closed'&&(get(id)==='open'||defaultOpen);
    const button=el('button','zynko-fold-button');button.type='button';
    const glyph=icon(open?'chevron-up':'chevron-down'),label=el('span','',open?'Ocultar':'Mostrar');
    button.append(glyph,label);button.setAttribute('aria-label',(open?'Ocultar':'Mostrar')+' '+id.replace(/-/g,' '));
    // Accessibility: keep aria-expanded synchronized with the content state.
    const contentId='zynko-fold-'+page+'-'+id;body.id=body.id||contentId;button.setAttribute('aria-controls',body.id);
    head.append(button);
    const apply=()=>{body.hidden=!open;button.setAttribute('aria-expanded',String(open));label.textContent=open?'Ocultar':'Mostrar';glyph.className='fa-solid fa-'+(open?'chevron-up':'chevron-down');head.closest('.zynko-fold-shell')?.classList.toggle('zynko-fold-closed',!open)};
    button.addEventListener('click',()=>{open=!open;save(id,open?'open':'closed');apply()});apply();
  };
  const shell=(target,id,title,description,fa,defaultOpen=true)=>{
    if(!target||target.dataset.zynkoFolded)return;
    const wrapper=el('div','panel spaced zynko-fold-shell');
    wrapper.dataset.zynkoFold=id;wrapper.setAttribute('aria-label',title);
    if(target.classList.contains('widget-hidden'))wrapper.classList.add('widget-hidden');
    const head=el('div','panel-head premium-card-head zynko-fold-head');
    const mark=el('span','premium-card-head-icon');mark.append(icon(fa||'layer-group'));
    const copy=el('div','zynko-fold-head-copy');copy.append(el('b','',title),el('small','',description));head.append(mark,copy);
    const body=el('div','zynko-fold-content');target.before(wrapper);wrapper.append(head,body);body.append(target);target.dataset.zynkoFolded='1';
    toggle(head,body,id,defaultOpen);return wrapper;
  };
  const internal=(section,id,defaultOpen=true)=>{
    if(!section||section.dataset.zynkoFolded)return;
    const head=section.querySelector(':scope > .panel-head, :scope > .premium-card-head');if(!head)return;
    // Preserve forms, buttons and labels exactly where their existing listeners expect them.
    const body=el('div','zynko-fold-content');
    const other=[...section.children].filter(child=>child!==head);
    if(!other.length)return;
    head.after(body);other.forEach(child=>body.append(child));
    section.classList.add('zynko-fold-shell');head.classList.add('zynko-fold-existing-head');section.dataset.zynkoFolded='1';
    toggle(head,body,id,defaultOpen);
  };
  const choose=(selector,id,title,description,fa,defaultOpen)=>shell(document.querySelector(selector),id,title,description,fa,defaultOpen);
  if(page==='dashboard'){
    internal(document.querySelector('.dashboard-quick-actions'),'quick-actions',true);
    internal(document.querySelector('.service-center'),'critical-services',true);
    choose('section[data-widget="kpis"]','channel-kpis','Indicadores de canales','Canales configurados y conversaciones abiertas.','chart-simple',true);
    choose('section.dashboard-grid','activity-channels','Actividad de mensajes y canales','Últimos mensajes y estado de tus canales.','chart-column',true);
  }
  if(page==='users'){
    choose('.users-kpis','users-kpis','Indicadores de usuarios','Resumen actualizado de tu equipo.','users',true);
    internal(document.querySelector('.users-filter-panel'),'users-filters',true);
  }
  if(page==='companies'){
    choose('section.company-kpis','companies-kpis','Indicadores de empresas','Resumen de empresas y suscripciones.','building',true);
    internal(document.querySelector('.company-control-panel'),'companies-filters',true);
  }
  if(page==='billing'){
    choose('.billing-kpis','billing-kpis','Indicadores de suscripciones','Estado general del catálogo comercial.','layer-group',true);
    internal(document.querySelector('.billing-filter-panel'),'billing-filters',true);
  }
  if(page==='chatbot'){
    choose('.nivo-kpis','nivo-kpis','Indicadores de NIVO IA','Estado, conocimiento, reglas y aprendizaje.','robot',true);
    choose('.nivo-tabs','nivo-filters','Accesos a secciones de NIVO','Atajos de configuración y aprendizaje.','filter',true);
    choose('.nivo-ai-explainer','nivo-flow','NIVO IA y Web Chat','Reglas, conocimiento, transferencia y bandeja.','diagram-project',true);
    // El explorador de secciones existente conserva su propia navegación y guardado.
  }
  if(page==='webchat'){
    // Single section navigator; the fixed right preview/operations rail is never hidden.
    const root=document.querySelector('.nivo-webchat-page');
    const layout=root?.querySelector('.webchat-layout');
    if(root&&layout&&!root.querySelector('.zynko-webchat-nav')){
      const sections={design:layout.querySelector(':scope > section.panel'),installations:root.querySelector('#installations'),kpis:root.querySelector('.webchat-kpis')};
      const entries=[['design','Diseño y comportamiento'],['installations','Instalación en sitios web'],['kpis','Indicadores de uso']].filter(([key])=>sections[key]);
      const navigator=el('div','panel zynko-webchat-nav');
      navigator.innerHTML='<div class="panel-head premium-card-head"><span class="premium-card-head-icon"><i class="fa-solid fa-layer-group"></i></span><div><b>Explorar secciones</b><small>Selecciona una sección para configurar; la vista previa permanece visible.</small></div></div><div class="zynko-section-nav-controls"><div class="zynko-section-search"><i class="fa-solid fa-magnifying-glass"></i><input id="zynkoWebchatSectionSearch" placeholder="Buscar sección…" autocomplete="off"><button type="button" id="zynkoWebchatSectionClear" hidden aria-label="Borrar búsqueda"><i class="fa-solid fa-xmark"></i></button></div><div class="field"><label for="zynkoWebchatSectionSelect">Sección</label><select id="zynkoWebchatSectionSelect" class="no-select2"></select></div></div>';
      const select=navigator.querySelector('#zynkoWebchatSectionSelect');
      entries.forEach(([id,title])=>{const option=el('option','',title);option.value=id;select.append(option)});
      layout.before(navigator);
      // The original installations and KPI panels were below the two-column layout,
      // leaving a tall empty area whenever the design panel was hidden. Put the
      // original nodes (not clones) into the left column, leaving the preview intact.
      const left=el('div','zynko-webchat-config-column');
      const preview=layout.querySelector(':scope > .nivo-webchat-rail');
      layout.insertBefore(left,preview);
      entries.forEach(([id])=>left.append(sections[id]));
      const search=navigator.querySelector('#zynkoWebchatSectionSearch');
      const clear=navigator.querySelector('#zynkoWebchatSectionClear');
      const show=key=>{
        if(!sections[key])return;
        entries.forEach(([id])=>sections[id].classList.toggle('zynko-webchat-section-hidden',id!==key));
        select.value=key;
      };
      select.addEventListener('change',()=>show(select.value));
      search.addEventListener('input',()=>{
        const q=search.value.trim().toLocaleLowerCase();
        entries.forEach(([id,title])=>{const option=[...select.options].find(o=>o.value===id);if(option)option.hidden=!!q&&!title.toLocaleLowerCase().includes(q)});
        clear.hidden=!q;
        const matches=entries.filter(([,title])=>title.toLocaleLowerCase().includes(q));
        if(q&&matches.length&&!matches.some(([id])=>id===select.value))show(matches[0][0]);
      });
      clear.addEventListener('click',()=>{search.value='';search.dispatchEvent(new Event('input'));search.focus()});
      // Native styled select: no portalled Select2 dropdown to escape the viewport.
      show(entries[0]?.[0]||'design');
    }
  }
 };
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
})();
