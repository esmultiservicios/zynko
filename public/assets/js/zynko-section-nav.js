/** ZYNKO · Navegación accesible de secciones. No modifica los formularios. */
(()=>{'use strict';
 const boot=()=>{
  const nav=document.getElementById('zynkoSectionNavigator');if(!nav)return;
  const type=nav.dataset.sectionPage;
  const root=nav.closest('main')||document;
  let panels=[...root.querySelectorAll('section.panel')].filter(x=>!x.closest('.modal-shell')&&!x.closest('section.panel section.panel'));
  if(type==='settings')panels=panels.filter(x=>x!==nav);
  if(type==='chatbot')panels=panels.filter(x=>x!==nav);
  if(panels.length<2){nav.remove();return;}
  const title=panel=>{const heading=panel.querySelector('.panel-head b,.panel-head strong,.premium-card-head b,.nivo-section-title, h2,h3');return (heading?.textContent||panel.id||'Sección').trim().replace(/\s+/g,' ').slice(0,90);};
  const data=panels.map((el,i)=>({el,index:i,name:title(el)}));
  nav.innerHTML='<div class="panel-head premium-card-head"><span class="premium-card-head-icon"><i class="fa-solid fa-layer-group"></i></span><div><b>Explorar secciones</b><small>Busca o selecciona qué configuración deseas consultar. No se pierden los cambios de los formularios.</small></div></div><div class="zynko-section-nav-controls"><div class="zynko-section-search"><i class="fa-solid fa-magnifying-glass"></i><input id="zynkoSectionSearch" type="text" placeholder="Buscar sección o configuración…" autocomplete="off" aria-label="Buscar secciones"><button type="button" id="zynkoSectionClear" title="Borrar búsqueda" aria-label="Borrar búsqueda" hidden><i class="fa-solid fa-xmark"></i></button></div><div class="field"><label for="zynkoSectionSelect">Sección</label><select id="zynkoSectionSelect" aria-label="Seleccionar sección"></select></div></div><div id="zynkoSectionCount" class="field-help"></div>';
  const search=nav.querySelector('#zynkoSectionSearch'),select=nav.querySelector('#zynkoSectionSelect'),clear=nav.querySelector('#zynkoSectionClear'),counter=nav.querySelector('#zynkoSectionCount');
  const render=(filter='')=>{
    const f=filter.toLocaleLowerCase('es').trim();const matches=data.filter(x=>!f||x.name.toLocaleLowerCase('es').includes(f)||x.el.textContent.toLocaleLowerCase('es').includes(f));
    const prev=Number(select.value);select.replaceChildren();
    matches.forEach(x=>{const option=document.createElement('option');option.value=String(x.index);option.textContent=x.name;select.appendChild(option)});
    const active=matches.find(x=>x.index===prev)||matches[0];if(active)select.value=String(active.index);
    data.forEach(x=>{x.el.hidden=!active||x.index!==active.index;x.el.classList.toggle('zynko-section-hidden',!active||x.index!==active.index)});
    counter.textContent=matches.length?`${matches.length} sección(es) encontrada(s) · ${active?.name||''}`:'No se encontraron secciones. Prueba con otra palabra.';
    clear.hidden=!search.value;if(window.jQuery&&jQuery.fn.select2&&jQuery(select).data('select2'))jQuery(select).trigger('change.select2');
  };
  select.addEventListener('change',()=>{const active=Number(select.value);data.forEach(x=>{x.el.hidden=x.index!==active;x.el.classList.toggle('zynko-section-hidden',x.index!==active)})});
  search.addEventListener('input',()=>render(search.value));clear.addEventListener('click',()=>{search.value='';render();search.focus()});
  const target=location.hash.slice(1);let initial=data.find(x=>x.el.id===target);if(initial){render();select.value=String(initial.index);select.dispatchEvent(new Event('change'));}else render();
  if(window.jQuery&&jQuery.fn.select2&&!jQuery(select).data('select2'))jQuery(select).select2({width:'100%',dropdownParent:jQuery(nav),minimumResultsForSearch:0});
 };
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
})();
