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
    choose('.webchat-kpis','webchat-kpis','Indicadores de NIVO Web Chat','Sitios, visitantes y mensajes.','chart-column',true);
    internal(document.querySelector('.webchat-layout > section.panel'),'webchat-design',true);
    internal(document.querySelector('#installations'),'webchat-installations',false);
    // Navegación por secciones sobre los mismos paneles sin desplazar la vista previa.
    const root=document.querySelector('.nivo-webchat-page');
    const first=root?.querySelector('.webchat-layout');
    if(root&&first&&!root.querySelector('.zynko-webchat-nav')){
      const navigator=el('div','panel zynko-webchat-nav');
      navigator.innerHTML='<div class="panel-head premium-card-head"><span class="premium-card-head-icon"><i class="fa-solid fa-layer-group"></i></span><div><b>Explorar secciones</b><small>Abre únicamente las opciones que necesitas configurar.</small></div></div><div class="zynko-section-nav-controls"><div class="zynko-section-search"><i class="fa-solid fa-magnifying-glass"></i><input id="zynkoWebchatSectionSearch" placeholder="Buscar sección…" autocomplete="off"><button type="button" id="zynkoWebchatSectionClear" hidden aria-label="Borrar búsqueda"><i class="fa-solid fa-xmark"></i></button></div><div class="field"><label for="zynkoWebchatSectionSelect">Sección</label><select id="zynkoWebchatSectionSelect" class="no-select2"><option value="design">Diseño y comportamiento</option><option value="installations">Instalación en sitios web</option><option value="kpis">Indicadores de uso</option></select></div></div>';
      first.before(navigator);
      const search=navigator.querySelector('#zynkoWebchatSectionSearch'),sel=navigator.querySelector('#zynkoWebchatSectionSelect'),clear=navigator.querySelector('#zynkoWebchatSectionClear');
      const sections={design:document.querySelector('.webchat-layout > section.panel'),installations:document.querySelector('#installations'),kpis:document.querySelector('.webchat-kpis')};
      const keys=Object.keys(sections).filter(key=>sections[key]);
      const selectSection=key=>{
        if(!sections[key])return;
        keys.forEach(k=>{const card=sections[k];const btn=card.closest('.zynko-fold-shell')?.querySelector('.zynko-fold-button')||card.querySelector('.zynko-fold-button');if(btn&&btn.getAttribute('aria-expanded')!==String(k===key))btn.click();});
        sel.value=key;
      };
      sel.addEventListener('change',()=>selectSection(sel.value));
      search.addEventListener('input',()=>{const query=search.value.toLowerCase().trim();[...sel.options].forEach(option=>{option.hidden=!!query&&!option.text.toLowerCase().includes(query)});clear.hidden=!query});
      clear.addEventListener('click',()=>{search.value='';search.dispatchEvent(new Event('input'));search.focus()});
      // Keep Select2 styling consistent with the standard ZYNKO selector.
      if(window.jQuery&&jQuery.fn.select2){
        const $sel=jQuery(sel);
        // Attach the dropdown to the viewport, not the card: the card may clip/offset it.
        $sel.removeClass('no-select2').select2({width:'100%',dropdownParent:jQuery(document.body),minimumResultsForSearch:0});
        $sel.on('change',()=>selectSection(sel.value));
        const keepInsideViewport=()=>{
          const container=document.querySelector('body > .select2-container--open');
          const dropdown=container?.querySelector('.select2-dropdown');
          const trigger=$sel.next('.select2-container')[0];
          if(!container||!dropdown||!trigger)return;
          const r=trigger.getBoundingClientRect(),g=12;
          const width=Math.min(Math.max(200,r.width),window.innerWidth-g*2);
          const left=Math.max(g,Math.min(r.left,window.innerWidth-width-g));
          // Select2 positions its portalled container by page coordinates.
          container.style.setProperty('left',left+window.scrollX+'px','important');
          container.style.setProperty('width',width+'px','important');
          dropdown.style.setProperty('width','100%','important');
          dropdown.style.setProperty('min-width','0','important');
        };
        $sel.on('select2:open',()=>requestAnimationFrame(keepInsideViewport));
      }
    }
  }
 };
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
})();
