/** ZYNKO · Explorador de secciones. Mantiene controles/formularios existentes. */
(()=>{'use strict';
 const boot=()=>{
  const nav=document.getElementById('zynkoSectionNavigator');if(!nav)return;
  const root=nav.closest('main')||document;
  const panels=[...root.querySelectorAll('section.panel')].filter(el=>el!==nav&&!el.closest('.modal-shell')&&!el.closest('section.panel section.panel'));
  if(panels.length<2){nav.remove();document.body.classList.add('zynko-section-ready');return;}
  const title=panel=>{const heading=panel.querySelector('.panel-head b,.panel-head strong,.premium-card-head b,.nivo-section-title,h2,h3');return (heading?.textContent||panel.id||'Sección').trim().replace(/\s+/g,' ').slice(0,90)};
  const data=panels.map((el,index)=>({el,index,name:title(el)}));
  nav.innerHTML='<div class="panel-head premium-card-head"><span class="premium-card-head-icon"><i class="fa-solid fa-layer-group"></i></span><div><b>Explorar secciones</b><small>Busca o selecciona qué configuración deseas consultar. No se pierden los cambios de los formularios.</small></div></div><div class="zynko-section-nav-controls"><div class="zynko-section-search"><i class="fa-solid fa-magnifying-glass"></i><input id="zynkoSectionSearch" type="text" placeholder="Buscar sección o configuración…" autocomplete="off" aria-label="Buscar secciones"><button type="button" id="zynkoSectionClear" title="Borrar búsqueda" aria-label="Borrar búsqueda" hidden><i class="fa-solid fa-xmark"></i></button></div><div class="field"><label for="zynkoSectionSelect">Sección</label><select id="zynkoSectionSelect" aria-label="Seleccionar sección"></select></div></div><div id="zynkoSectionCount" class="field-help" role="status" aria-live="polite"></div>';
  const search=nav.querySelector('#zynkoSectionSearch'),select=nav.querySelector('#zynkoSectionSelect'),clear=nav.querySelector('#zynkoSectionClear'),counter=nav.querySelector('#zynkoSectionCount');
  let current=0;
  const activate=index=>{
   const item=data.find(x=>x.index===index)||data[0];if(!item)return;
   current=item.index;select.value=String(current);
   data.forEach(x=>{const hidden=x.index!==current;x.el.hidden=hidden;x.el.classList.toggle('zynko-section-hidden',hidden)});
   const count=select.options.length;
   counter.textContent=count?`${count} sección(es) encontrada(s) · ${item.name}`:'No se encontraron secciones. Prueba con otra palabra.';
   if(window.jQuery&&jQuery.fn.select2&&jQuery(select).data('select2'))jQuery(select).trigger('change.select2');
  };
  const render=()=>{
   const f=search.value.toLocaleLowerCase('es').trim();const matches=data.filter(x=>!f||x.name.toLocaleLowerCase('es').includes(f)||x.el.textContent.toLocaleLowerCase('es').includes(f));
   select.replaceChildren();matches.forEach(x=>{const option=document.createElement('option');option.value=String(x.index);option.textContent=x.name;select.appendChild(option)});
   clear.hidden=!search.value;
   if(matches.length)activate(matches.some(x=>x.index===current)?current:matches[0].index);
   else {data.forEach(x=>{x.el.hidden=true;x.el.classList.add('zynko-section-hidden')});counter.textContent='No se encontraron secciones. Prueba con otra palabra.';}
  };
  select.addEventListener('change',()=>{if(select.value!=='')activate(Number(select.value))});
  search.addEventListener('input',render);
  clear.addEventListener('click',()=>{search.value='';render();search.focus()});
  const target=location.hash.slice(1),initial=data.find(x=>x.el.id===target);
  current=initial?initial.index:0;
  render();
  if(window.jQuery&&jQuery.fn.select2&&!jQuery(select).data('select2'))jQuery(select).select2({width:'100%',dropdownParent:jQuery(document.body),minimumResultsForSearch:0});
  document.body.classList.add('zynko-section-ready');
 };
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
})();
