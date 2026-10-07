<?php
if(!isMainOwnerOnly()){header('Location: ?page=dashboard');exit;}
$title='Sitio público · imágenes';
require __DIR__.'/partials/top.php';
$showcaseItems=zynkoLoadPublicShowcase();
$defaults=[];foreach(zynkoShowcaseDefaults() as $d)$defaults[$d['key']]=$d;
?>
<section class="welcome compact-welcome public-showcase-welcome">
  <div><small>SITIO PÚBLICO · OWNER</small><h1>Imágenes de ZYNKO</h1><p>Reemplaza las capturas publicadas sin editar código. Puedes arrastrar, pegar o seleccionar una imagen.</p></div>
  <div class="dashboard-head-actions"><a class="soft" href="?page=home#showcase" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square"></i> Ver sitio publicado</a></div>
</section>
<section class="panel public-showcase-admin">
  <div class="panel-head premium-card-head"><span class="premium-card-head-icon"><i class="fa-regular fa-images"></i></span><div><b>Galería publicada</b><small>Solo el Owner principal puede ver y modificar esta sección. JPG, PNG o WEBP · máximo 12 MB.</small></div></div>
  <div class="public-showcase-owner-note"><i class="fa-solid fa-shield-halved"></i><div><b>Control exclusivo del Owner</b><span>Las imágenes se guardan como contenido persistente del servidor y no ensucian Git. La versión incluida en ZYNKO queda disponible para restaurar.</span></div></div>
  <div class="public-showcase-admin-grid">
    <?php foreach($showcaseItems as $item):$def=$defaults[$item['key']]??$item;$isCustom=$item['src']!==$def['src']; ?>
    <article class="public-showcase-admin-card" data-showcase-card data-key="<?=htmlspecialchars($item['key'])?>">
      <form class="public-showcase-form" enctype="multipart/form-data">
        <input type="hidden" name="action" value="public_showcase_save"><input type="hidden" name="key" value="<?=htmlspecialchars($item['key'])?>">
        <div class="public-showcase-preview"><img src="<?=htmlspecialchars($item['src'])?>" alt="<?=htmlspecialchars($item['title'])?>" loading="lazy"><span class="public-showcase-status <?=$isCustom?'custom':'default'?>"><i class="fa-solid <?=$isCustom?'fa-cloud-arrow-up':'fa-box-archive'?>"></i> <?=$isCustom?'Personalizada':'Incluida'?></span></div>
        <div class="public-showcase-fields"><div class="field"><label>Área</label><input name="section" maxlength="80" value="<?=htmlspecialchars($item['section'])?>" required></div><div class="field"><label>Título</label><input name="title" maxlength="120" value="<?=htmlspecialchars($item['title'])?>" required></div><div class="field full"><label>Descripción</label><textarea name="caption" rows="2" maxlength="280" required><?=htmlspecialchars($item['caption'])?></textarea></div></div>
        <div class="public-showcase-dropzone" tabindex="0" role="button" aria-label="Reemplazar imagen de <?=htmlspecialchars($item['title'])?>">
          <input type="file" name="image" accept="image/jpeg,image/png,image/webp" hidden>
          <span class="dropzone-icon"><i class="fa-solid fa-cloud-arrow-up"></i></span><div><b>Arrastra, pega o selecciona</b><small>Ctrl+V funciona después de seleccionar esta tarjeta.</small></div><button type="button" class="soft public-showcase-select"><i class="fa-regular fa-folder-open"></i> Seleccionar</button>
        </div>
        <div class="public-showcase-file-preview" hidden></div>
        <div class="public-showcase-actions"><button type="submit" class="primary"><i class="fa-solid fa-floppy-disk"></i> Guardar cambios</button><button type="button" class="soft public-showcase-reset" <?=$isCustom?'':'disabled'?>> <i class="fa-solid fa-arrow-rotate-left"></i> Restaurar incluida</button></div>
      </form>
    </article>
    <?php endforeach?>
  </div>
</section>
<script>
(()=>{
 const cards=[...document.querySelectorAll('[data-showcase-card]')];let activeCard=null;
 const notify=(type,title,msg)=>window.showNotify?showNotify(type,title,msg):alert(msg||title);
 const post=async fd=>{const r=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1'},body:fd});const j=await r.json();if(!j.ok)throw new Error(j.message||'No se pudo completar la operación.');return j};
 const setFile=(card,file)=>{if(!file||!file.type?.startsWith('image/')){notify('warning','Archivo no válido','Selecciona una imagen JPG, PNG o WEBP.');return;}if(file.size>12*1024*1024){notify('warning','Imagen demasiado grande','El máximo permitido es 12 MB.');return;}const input=card.querySelector('input[type=file]');const dt=new DataTransfer();dt.items.add(file);input.files=dt.files;const box=card.querySelector('.public-showcase-file-preview');const url=URL.createObjectURL(file);box.innerHTML=`<img src="${url}" alt="Vista previa"><div><b>${file.name.replace(/[<>]/g,'')}</b><small>${Math.max(1,Math.round(file.size/1024))} KB · lista para guardar</small></div><button type="button" aria-label="Quitar"><i class="fa-solid fa-xmark"></i></button>`;box.hidden=false;box.querySelector('button').onclick=()=>{input.value='';box.hidden=true;box.innerHTML=''};activeCard=card;};
 cards.forEach(card=>{const zone=card.querySelector('.public-showcase-dropzone'),input=card.querySelector('input[type=file]'),select=card.querySelector('.public-showcase-select'),form=card.querySelector('form');
  const choose=()=>{activeCard=card;input.click()};select.addEventListener('click',e=>{e.stopPropagation();choose()});zone.addEventListener('click',e=>{if(!e.target.closest('button'))choose()});zone.addEventListener('focus',()=>activeCard=card);input.addEventListener('change',()=>setFile(card,input.files?.[0]));
  ['dragenter','dragover'].forEach(ev=>zone.addEventListener(ev,e=>{e.preventDefault();activeCard=card;zone.classList.add('is-drag')}));['dragleave','drop'].forEach(ev=>zone.addEventListener(ev,e=>{e.preventDefault();zone.classList.remove('is-drag')}));zone.addEventListener('drop',e=>setFile(card,e.dataTransfer?.files?.[0]));
  form.addEventListener('submit',async e=>{e.preventDefault();const btn=form.querySelector('button[type=submit]'),old=btn.innerHTML;btn.disabled=true;btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Guardando…';try{const j=await post(new FormData(form));notify('success','Sitio público',j.message);setTimeout(()=>location.reload(),550)}catch(err){notify('error','No se pudo guardar',err.message)}finally{btn.disabled=false;btn.innerHTML=old}});
  card.querySelector('.public-showcase-reset')?.addEventListener('click',async e=>{const btn=e.currentTarget;if(btn.disabled)return;const ask=await Swal.fire({title:'Restaurar imagen incluida',text:'Se eliminará la personalización de esta vista y volverá la captura incluida en esta versión.',icon:'question',showCancelButton:true,confirmButtonText:'Sí, restaurar',cancelButtonText:'Cancelar',allowOutsideClick:false});if(!ask.isConfirmed)return;const fd=new FormData();fd.append('action','public_showcase_reset');fd.append('key',card.dataset.key);try{const j=await post(fd);notify('success','Restaurada',j.message);setTimeout(()=>location.reload(),500)}catch(err){notify('error','No se pudo restaurar',err.message)}});
 });
 document.addEventListener('paste',e=>{const items=[...(e.clipboardData?.items||[])];const img=items.find(i=>i.type?.startsWith('image/'));if(!img)return;const card=activeCard||document.querySelector('[data-showcase-card]');if(card){e.preventDefault();setFile(card,img.getAsFile())}});
})();
</script>
<?php require __DIR__.'/partials/bottom.php'; ?>
