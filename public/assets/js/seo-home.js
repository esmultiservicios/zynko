(()=>{
  'use strict';
  const esc=v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
  if(typeof window.showNotify!=='function'){
    const icons={success:'✓',error:'×',info:'i',warning:'!'};
    window.showNotify=(type='info',title='',message='')=>{
      type=['success','error','info','warning'].includes(type)?type:'info';
      let host=document.querySelector('.zynko-notify-host');
      if(!host){host=document.createElement('div');host.className='zynko-notify-host';host.setAttribute('aria-live','polite');document.body.appendChild(host)}
      const el=document.createElement('div');el.className=`zynko-notify ${type}`;
      el.innerHTML=`<span class="zynko-notify-icon">${icons[type]}</span><div><b>${esc(title||({success:'Listo',error:'Error',info:'Información',warning:'Atención'}[type]))}</b><p>${esc(message)}</p></div><button type="button" aria-label="Cerrar">×</button>`;
      host.appendChild(el);requestAnimationFrame(()=>el.classList.add('show'));
      const close=()=>{el.classList.remove('show');setTimeout(()=>el.remove(),180)};
      el.querySelector('button')?.addEventListener('click',close);setTimeout(close,type==='error'?6500:4800);return el;
    };
  }

  const publicFallbackCopy=text=>{const ta=document.createElement('textarea');ta.value=text;ta.setAttribute('readonly','');ta.style.position='fixed';ta.style.opacity='0';document.body.appendChild(ta);ta.select();let ok=false;try{ok=document.execCommand('copy')}catch(_){ok=false}ta.remove();return ok};
  const publicCopyText=async text=>{if(!text)return false;try{if(navigator.clipboard?.writeText){await navigator.clipboard.writeText(text);return true}}catch(_){}return publicFallbackCopy(text)};
  document.addEventListener('click',async event=>{const btn=event.target.closest('[data-copy-target]');if(!btn)return;const target=document.getElementById(btn.dataset.copyTarget||'');if(!target)return;const ok=await publicCopyText(target.textContent.trim());showNotify(ok?'success':'error',ok?'Copiado':'No se pudo copiar',ok?'El ejemplo quedó listo para pegar donde lo necesites.':'El navegador bloqueó el acceso al portapapeles.');if(ok){btn.classList.add('copied');setTimeout(()=>btn.classList.remove('copied'),900)}});

  const reduced=window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  const header=document.querySelector('.site-header');
  const onScroll=()=>header?.classList.toggle('scrolled',window.scrollY>8);
  onScroll();addEventListener('scroll',onScroll,{passive:true});

  const menuToggle=document.getElementById('siteMenuToggle');
  const mobileMenu=document.getElementById('siteMobileMenu');
  const closeMobileMenu=()=>{if(!menuToggle||!mobileMenu)return;header?.classList.remove('menu-open');mobileMenu.hidden=true;menuToggle.setAttribute('aria-expanded','false');menuToggle.setAttribute('aria-label','Abrir menú');menuToggle.innerHTML='<i class="fa-solid fa-bars"></i>';};
  const openMobileMenu=()=>{if(!menuToggle||!mobileMenu)return;header?.classList.add('menu-open');mobileMenu.hidden=false;menuToggle.setAttribute('aria-expanded','true');menuToggle.setAttribute('aria-label','Cerrar menú');menuToggle.innerHTML='<i class="fa-solid fa-xmark"></i>';};
  menuToggle?.addEventListener('click',()=>menuToggle.getAttribute('aria-expanded')==='true'?closeMobileMenu():openMobileMenu());
  mobileMenu?.querySelectorAll('a').forEach(a=>a.addEventListener('click',closeMobileMenu));
  addEventListener('resize',()=>{if(innerWidth>1060)closeMobileMenu()},{passive:true});

  const reveal=[...document.querySelectorAll('[data-reveal]')];
  if(reduced||!('IntersectionObserver' in window)) reveal.forEach(el=>el.classList.add('is-visible'));
  else{
    const io=new IntersectionObserver(entries=>entries.forEach(entry=>{
      if(entry.isIntersecting){entry.target.classList.add('is-visible');io.unobserve(entry.target)}
    }),{threshold:.12,rootMargin:'0px 0px -30px'});
    reveal.forEach(el=>io.observe(el));
  }

  const faq=[...document.querySelectorAll('.faq-grid details')];
  faq.forEach(item=>item.addEventListener('toggle',()=>{
    if(!item.open)return;faq.forEach(other=>{if(other!==item&&other.open)other.open=false});
  }));

  const lightbox=document.getElementById('showcaseLightbox');
  const lightboxImage=document.getElementById('showcaseLightboxImage');
  const lightboxTitle=document.getElementById('showcaseLightboxTitle');
  const lightboxCaption=document.getElementById('showcaseLightboxCaption');
  const openerButtons=[...document.querySelectorAll('[data-lightbox-src]')];
  const closeLightbox=()=>{if(!lightbox)return;lightbox.setAttribute('hidden','hidden');document.body.classList.remove('lightbox-open');if(lightboxImage)lightboxImage.src=''};
  const openLightbox=button=>{if(!lightbox||!lightboxImage)return;lightboxImage.src=button.dataset.lightboxSrc||'';if(lightboxTitle)lightboxTitle.textContent=button.dataset.lightboxTitle||'';if(lightboxCaption)lightboxCaption.textContent=button.dataset.lightboxCaption||'';lightbox.removeAttribute('hidden');document.body.classList.add('lightbox-open')};
  openerButtons.forEach(button=>button.addEventListener('click',()=>openLightbox(button)));
  lightbox?.querySelectorAll('[data-lightbox-close]').forEach(el=>el.addEventListener('click',closeLightbox));
  document.addEventListener('keydown',event=>{if(event.key==='Escape'&&lightbox&&!lightbox.hasAttribute('hidden'))closeLightbox()});

  const contactForm=document.getElementById('publicContactForm');
  if(contactForm){
    const subject=contactForm.querySelector('#contactSubject');
    const source=contactForm.querySelector('#contactSource');
    const sourceOtherWrap=contactForm.querySelector('#contactSourceOther');
    const sourceOther=contactForm.querySelector('#contactSourceOtherInput');
    const message=contactForm.querySelector('#contactMessage');
    const count=contactForm.querySelector('#contactMessageCount');
    const submit=contactForm.querySelector('button[type="submit"]');
    const turnstileEnabled=contactForm.dataset.turnstileEnabled==='1';
    const turnstileSiteKey=(contactForm.dataset.turnstileSitekey||'').trim();
    const turnstileHost=document.getElementById('contactTurnstile');
    const turnstileToken=document.getElementById('contactTurnstileToken');
    let turnstileWidgetId=null,turnstileReadyPromise=null,pendingTurnstileResolve=null,pendingTurnstileReject=null;

    if(window.jQuery&&jQuery.fn&&jQuery.fn.select2){
      jQuery(contactForm).find('.contact-select2').each(function(){
        const $el=jQuery(this);
        if($el.hasClass('select2-hidden-accessible'))return;
        $el.select2({width:'100%',minimumResultsForSearch:0,placeholder:$el.data('placeholder')||'Selecciona una opción',dropdownCssClass:'zynko-contact-select2-dropdown'});
      });
    }

    const syncSource=()=>{
      const other=source?.value==='other';
      if(sourceOtherWrap)sourceOtherWrap.hidden=!other;
      if(sourceOther){sourceOther.required=other;sourceOther.setAttribute('aria-required',other?'true':'false');if(!other)sourceOther.value=''}
    };
    const syncCount=()=>{if(count&&message)count.textContent=String(message.value.length)};
    source?.addEventListener('change',syncSource);message?.addEventListener('input',syncCount);syncSource();syncCount();

    const loadTurnstile=()=>{
      if(!turnstileEnabled||!turnstileSiteKey)return Promise.resolve(false);
      if(window.turnstile)return Promise.resolve(true);
      if(turnstileReadyPromise)return turnstileReadyPromise;
      turnstileReadyPromise=new Promise((resolve,reject)=>{
        const script=document.createElement('script');
        script.src='https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        script.async=true;script.defer=true;
        script.onload=()=>resolve(!!window.turnstile);
        script.onerror=()=>reject(new Error('No se pudo cargar la validación anti-spam.'));
        document.head.appendChild(script);
      });
      return turnstileReadyPromise;
    };
    const renderTurnstile=async()=>{
      if(!turnstileEnabled||!turnstileSiteKey||!turnstileHost)return false;
      await loadTurnstile();
      if(turnstileWidgetId!==null)return true;
      turnstileWidgetId=window.turnstile.render(turnstileHost,{
        sitekey:turnstileSiteKey,
        theme:'light',
        appearance:'interaction-only',
        execution:'execute',
        action:'public_contact',
        callback:token=>{
          if(turnstileToken)turnstileToken.value=token||'';
          if(pendingTurnstileResolve){pendingTurnstileResolve(token||'');pendingTurnstileResolve=null;pendingTurnstileReject=null;}
        },
        'expired-callback':()=>{if(turnstileToken)turnstileToken.value='';},
        'error-callback':()=>{
          if(turnstileToken)turnstileToken.value='';
          if(pendingTurnstileReject){pendingTurnstileReject(new Error('No se pudo completar la validación anti-spam.'));pendingTurnstileResolve=null;pendingTurnstileReject=null;}
        }
      });
      window.turnstile.execute(turnstileWidgetId);
      return true;
    };
    const ensureTurnstileToken=async()=>{
      if(!turnstileEnabled)return '';
      if(turnstileToken?.value)return turnstileToken.value;
      await renderTurnstile();
      if(turnstileToken?.value)return turnstileToken.value;
      return await new Promise((resolve,reject)=>{
        pendingTurnstileResolve=resolve;pendingTurnstileReject=reject;
        try{window.turnstile.execute(turnstileWidgetId);}catch(error){pendingTurnstileResolve=null;pendingTurnstileReject=null;reject(error);}
        setTimeout(()=>{if(pendingTurnstileReject){pendingTurnstileReject(new Error('La validación anti-spam tardó demasiado. Intenta nuevamente.'));pendingTurnstileResolve=null;pendingTurnstileReject=null;}},12000);
      });
    };
    if(turnstileEnabled&&turnstileSiteKey){renderTurnstile().catch(()=>{});}

    contactForm.addEventListener('submit',async event=>{
      event.preventDefault();syncSource();
      if(!subject?.value){showNotify('warning','Campo obligatorio','Selecciona sobre qué quieres consultar.');if(window.jQuery&&jQuery.fn?.select2)jQuery(subject).select2('open');return;}
      if(!source?.value){showNotify('warning','Campo obligatorio','Selecciona cómo conociste ZYNKO.');if(window.jQuery&&jQuery.fn?.select2)jQuery(source).select2('open');return;}
      if(!contactForm.reportValidity())return;
      const old=submit?.innerHTML||'';
      contactForm.classList.add('is-sending');
      if(submit){submit.disabled=true;submit.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Enviando…'}
      try{
        if(turnstileEnabled)await ensureTurnstileToken();
        const response=await fetch(location.href,{method:'POST',headers:{'X-ZYNKO-AJAX':'1','Accept':'application/json'},body:new FormData(contactForm)});
        const data=await response.json();
        if(!response.ok||!data.ok)throw new Error(data.message||'No se pudo enviar la consulta.');
        const confirmed=data?.data?.confirmation_sent!==false;
        showNotify(confirmed?'success':'warning',confirmed?'Consulta enviada':'Consulta recibida',data.message||'Recibimos tu consulta.');
        contactForm.reset();
        if(window.jQuery&&jQuery.fn?.select2)jQuery(contactForm).find('.contact-select2').val(null).trigger('change');
        syncSource();syncCount();
        if(turnstileEnabled&&window.turnstile&&turnstileWidgetId!==null){window.turnstile.reset(turnstileWidgetId);if(turnstileToken)turnstileToken.value='';window.turnstile.execute(turnstileWidgetId);}
      }catch(error){showNotify('error','No se pudo enviar',error?.message||'Ocurrió un error inesperado. Intenta nuevamente.');}
      finally{contactForm.classList.remove('is-sending');if(submit){submit.disabled=false;submit.innerHTML=old}}
    });
  }

})();
