/** ZYNKO · Explorador de secciones. Mantiene controles/formularios existentes. */
(()=>{'use strict';
 const boot=()=>{
  const nav=document.getElementById('zynkoSectionNavigator');if(!nav)return;
  const root=nav.closest('main')||document;
  const panels=[...root.querySelectorAll('section.panel')].filter(el=>el!==nav&&!el.closest('.modal-shell')&&!el.closest('section.panel section.panel'));
  if(panels.length<2){nav.remove();document.body.classList.add('zynko-section-ready');return;}
  const title=panel=>{const heading=panel.querySelector('.panel-head b,.panel-head strong,.premium-card-head b,.nivo-section-title,h2,h3');return (heading?.textContent||panel.id||'Sección').trim().replace(/\s+/g,' ').slice(0,90)};
  const data=panels.map((el,index)=>({el,index,name:title(el)}));
  nav.innerHTML='<div class="panel-head premium-card-head"><span class="premium-card-head-icon"><i class="fa-solid fa-layer-group"></i></span><div><b>Explorar secciones</b><small>Busca o selecciona qué configuración deseas consultar. No se pierden los cambios de los formularios.</small></div></div><div class="zynko-section-nav-controls"><div class="zynko-section-search"><i class="fa-solid fa-magnifying-glass"></i><input id="zynkoSectionSearch" type="text" placeholder="Buscar sección o configuración…" autocomplete="off" aria-label="Buscar secciones"><button type="button" id="zynkoSectionClear" title="Borrar búsqueda" aria-label="Borrar búsqueda" hidden><i class="fa-solid fa-xmark"></i></button></div><div class="field"><label for="zynkoSectionSelect">Sección</label><select id="zynkoSectionSelect" class="no-select2" aria-label="Seleccionar sección"></select></div></div><div id="zynkoSectionCount" class="field-help" role="status" aria-live="polite"></div>';
  const search=nav.querySelector('#zynkoSectionSearch'),select=nav.querySelector('#zynkoSectionSelect'),clear=nav.querySelector('#zynkoSectionClear'),counter=nav.querySelector('#zynkoSectionCount');
  let current=-1;
  const $=window.jQuery;
  const refreshSelect=()=>{if($&&$.fn.select2&&$(select).data('select2'))$(select).trigger('change.select2');};
  const activate=index=>{
    const item=data.find(x=>x.index===Number(index))||data[0];if(!item)return;
    current=item.index;
    // Update the original select only AFTER receiving the user's choice.
    if(select.value!==String(current))select.value=String(current);
    data.forEach(x=>{const hide=x.index!==current;x.el.hidden=hide;x.el.classList.toggle('zynko-section-hidden',hide);});
    counter.textContent=select.options.length+' sección(es) encontrada(s) · '+item.name;
    refreshSelect();
  };
  const render=()=>{
    const query=search.value.toLocaleLowerCase('es').trim();
    const matched=data.filter(x=>!query||x.name.toLocaleLowerCase('es').includes(query)||x.el.textContent.toLocaleLowerCase('es').includes(query));
    select.replaceChildren();
    matched.forEach(x=>select.add(new Option(x.name,String(x.index))));
    clear.hidden=!query;
    if(matched.length)activate(matched.some(x=>x.index===current)?current:matched[0].index);
    else{data.forEach(x=>{x.el.hidden=true;x.el.classList.add('zynko-section-hidden');});counter.textContent='No se encontraron secciones. Prueba otra palabra.';refreshSelect();}
  };
  const choose=value=>{
    const index=Number(value);
    if(value==null||value===''||!Number.isInteger(index)||!data.some(x=>x.index===index))return;
    activate(index);
  };
  // Listen to native AND Select2 change. Other scripts can reset the widget's displayed state;
  // the selected value remains authoritative and no form gets rebuilt.
  select.addEventListener('change',()=>choose(select.value));
  if($){$(select).on('change.zynkoSectionNav select2:select.zynkoSectionNav',e=>{
      const selected=e.params?.data?.id ?? $(select).val();choose(selected);
    });}
  search.addEventListener('input',render);
  clear.addEventListener('click',()=>{search.value='';render();search.focus();});
  render();
  if($&&$.fn.select2){
    const $select=$(select);
    if($select.data('select2'))$select.select2('destroy');
    // Body positioning avoids the right-clipped dropdown produced by nested panels.
    $select.removeClass('no-select2').addClass('select2');
    $select.select2({width:'100%',dropdownParent:$(document.body),minimumResultsForSearch:0,dropdownCssClass:'zynko-section-dropdown'});
    $select.on('select2:close.zynkoSectionNav',()=>choose($select.val()));
    refreshSelect();
  }
  document.body.classList.add('zynko-section-ready');
 };
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else boot();
})();
