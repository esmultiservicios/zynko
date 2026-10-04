(() => {
  'use strict';

  const script = document.currentScript;
  const key = script?.dataset.zynkoKey;

  if (!key) {
    return;
  }

  const apiUrl = new URL('webchat-api.php', script.src);
  apiUrl.searchParams.set('key', key);

  const api = apiUrl.href;
  const mascotUrl = new URL('assets/img/nivo-email.png', script.src).href;
  const storagePrefix = `zynko.nivo.${key}`;

  const state = {
    visitor_token: localStorage.getItem(storagePrefix) || '',
    conversation_id: 0,
    ws: null,
    opened: false,
    profile: {
      name: '',
      email: ''
    },
    shadow: null,
    lastCount: 0,
    widget: null,
    poll: null,
    sending: false,
    restarted: false,
    originalTitle: document.title,
    idleNudgeTimer: null,
    idleCloseTimer: null,
    initialGreetingShown: false,
    statusTimer: null,
    initialMessages: [],
    conversationClosed: false,
    surveyConversationId: 0,
    selectedRating: 0
  };

  const esc = value => String(value ?? '').replace(/[&<>"']/g, character => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;'
  }[character]));

  const call = async data => {
    const payload = {
      key,
      visitor_token: state.visitor_token,
      client_hour: new Date().getHours(),
      client_timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
      ...data
    };
    const form = new URLSearchParams();

    Object.entries(payload).forEach(([field, value]) => {
      if (value !== undefined && value !== null) {
        form.set(field, String(value));
      }
    });

    const response = await fetch(api, {
      method: 'POST',
      body: form,
      mode: 'cors',
      credentials: 'omit'
    });
    const raw = await response.text();
    let json;

    try {
      json = JSON.parse(raw);
    } catch {
      throw new Error('ZYNKO no devolvió una respuesta válida del Web Chat.');
    }

    if (!response.ok || !json.ok) {
      throw new Error(json.message || `Error HTTP ${response.status}`);
    }

    return json.data;
  };

  const brandedLauncher = widget => {
    const raw = String(widget.launcher_label || '').trim();
    const plain = raw.replace(/\*\*/g, '').trim();

    if (!raw || plain.toLowerCase() === '¿necesitas ayuda?') {
      return '**NIVO Web Chat** · ¿Necesitas ayuda?';
    }

    return raw;
  };

  const launcherMarkup = value => {
    let html = esc(String(value ?? ''));
    html = html
      .replace(/\*\*(.+?)\*\*/gs, '<strong>$1</strong>')
      .replace(/__(.+?)__/gs, '<em>$1</em>')
      .replace(/\n/g, '<br>');
    return html;
  };

  const launcherPlain = value => String(value ?? '').replace(/\*\*|__/g, '').trim();

  const pathList = raw => String(raw || '')
    .split(/[\n,;]+/)
    .map(value => value.trim())
    .filter(Boolean);

  const pathMatch = (path, rule) => {
    if (rule === '/' || rule === '*') {
      return true;
    }

    return path === rule || path.startsWith(rule.endsWith('/') ? rule : `${rule}/`);
  };

  const pathAllowed = experience => {
    const path = location.pathname || '/';
    const blocked = pathList(experience.blocked_paths);

    if (blocked.some(rule => pathMatch(path, rule))) {
      return false;
    }

    const allowed = pathList(experience.allowed_paths);
    return !allowed.length || allowed.some(rule => pathMatch(path, rule));
  };

  const touchSession = () => {
    localStorage.setItem(`${storagePrefix}.last`, String(Date.now()));
    scheduleInactivity();
  };

  const sleep = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));

  const clearInactivityTimers = () => {
    if (state.idleNudgeTimer) {
      clearTimeout(state.idleNudgeTimer);
      state.idleNudgeTimer = null;
    }

    if (state.idleCloseTimer) {
      clearTimeout(state.idleCloseTimer);
      state.idleCloseTimer = null;
    }
  };

  const setPresence = (label, temporary = false) => {
    if (!state.shadow) {
      return;
    }

    const status = state.shadow.querySelector('.presence-text');
    if (!status) {
      return;
    }

    status.textContent = label;

    if (state.statusTimer) {
      clearTimeout(state.statusTimer);
      state.statusTimer = null;
    }

    if (temporary) {
      state.statusTimer = setTimeout(() => {
        status.textContent = state.conversation_id ? 'Esperando tu respuesta' : 'Listo para ayudarte';
      }, 2400);
    }
  };

  const scheduleInactivity = () => {
    clearInactivityTimers();

    if (!state.widget || !state.conversation_id) {
      return;
    }

    const experience = state.widget.experience || {};
    const nudgeMinutes = Math.max(1, Math.min(120, parseInt(experience.inactivity_nudge_minutes || 5, 10)));
    const closeMinutes = Math.max(nudgeMinutes + 1, Math.min(1440, parseInt(experience.inactivity_close_minutes || 30, 10)));

    state.idleNudgeTimer = setTimeout(async () => {
      if (!state.shadow || !state.conversation_id) {
        return;
      }

      const typing = experience.typing_indicator === false ? null : addTyping(state.shadow);
      setPresence('NIVO está escribiendo…');
      await sleep(Math.max(500, Math.min(3000, parseInt(experience.typing_delay_ms || 900, 10))));
      typing?.remove();
      add(
        state.shadow,
        experience.inactivity_message || '¿Sigues por aquí? Si necesitas algo más, estoy pendiente para ayudarte.',
        'in',
        'NIVO'
      );
      setPresence('Esperando tu respuesta');
    }, nudgeMinutes * 60000);

    state.idleCloseTimer = setTimeout(async () => {
      if (!state.shadow || !state.conversation_id) {
        return;
      }

      try {
        const result = await call({ action: 'expire' });
        state.conversation_id = 0;
        setPresence('Sesión finalizada');
        add(
          state.shadow,
          result.message || experience.inactivity_close_message || 'Cerré esta sesión por inactividad. Cuando quieras, escribe y comenzamos una nueva conversación.',
          'in',
          'NIVO'
        );
        syncProfileUi();
      } catch (error) {
        console.warn('NIVO Web Chat:', error.message);
      }
    }, closeMinutes * 60000);
  };

  const boot = async () => {
    try {
      const data = await call({ action: 'bootstrap' });
      const experience = data.widget?.experience || {};
      const timeout = Math.max(15, Math.min(10080, parseInt(experience.session_timeout_minutes || 1440, 10))) * 60000;
      const last = parseInt(localStorage.getItem(`${storagePrefix}.last`) || '0', 10);

      if (!state.restarted && state.visitor_token && last && Date.now() - last > timeout) {
        state.restarted = true;
        state.visitor_token = '';
        state.conversation_id = 0;
        localStorage.removeItem(storagePrefix);
        localStorage.removeItem(`${storagePrefix}.open`);
        return boot();
      }

      if (experience.hide_on_mobile && matchMedia('(max-width: 600px)').matches) {
        return;
      }

      if (!pathAllowed(experience)) {
        return;
      }

      state.visitor_token = data.visitor_token;
      state.conversation_id = data.conversation_id;
      state.conversationClosed = Boolean(data.conversation_closed);
      state.surveyConversationId = data.survey?.conversation_id || 0;
      state.widget = data.widget;
      state.initialMessages = data.messages || [];
      state.profile = {
        name: data.visitor_profile?.name || localStorage.getItem(`${storagePrefix}.name`) || '',
        email: data.visitor_profile?.email || localStorage.getItem(`${storagePrefix}.email`) || ''
      };

      localStorage.setItem(storagePrefix, state.visitor_token);
      touchSession();
      mount(data);
      connect(data);
      renderMessages(state.initialMessages, false);
      scheduleInactivity();
    } catch (error) {
      console.warn('NIVO Web Chat:', error.message);
    }
  };

  function beep() {
    if (!state.widget?.sound_enabled) {
      return;
    }

    try {
      const AudioContextClass = window.AudioContext || window.webkitAudioContext;
      if (!AudioContextClass) {
        return;
      }

      const context = new AudioContextClass();
      const oscillator = context.createOscillator();
      const gain = context.createGain();
      oscillator.frequency.value = 620;
      gain.gain.value = 0.035;
      oscillator.connect(gain);
      gain.connect(context.destination);
      oscillator.start();
      oscillator.stop(context.currentTime + 0.09);
    } catch {
      // El sonido es un detalle opcional y nunca debe bloquear el chat.
    }
  }

  function mount(data) {
    const widget = data.widget;
    const position = widget.position || 'bottom-right';
    const vertical = position.startsWith('top') ? 'top' : 'bottom';
    const horizontal = position.endsWith('left') ? 'left' : 'right';
    const launcherText = brandedLauncher(widget);
    const launcherHtml = launcherMarkup(launcherText);
    const experience = widget.experience || {};
    const host = document.createElement('div');

    host.id = 'zynko-nivo-host';
    host.style.cssText = 'all:initial!important;position:static!important;display:block!important;width:0!important;height:0!important;margin:0!important;padding:0!important;border:0!important;font-size:16px!important;line-height:normal!important;color-scheme:light!important';
    document.body.appendChild(host);

    const shadow = host.attachShadow({ mode: 'open' });
    const privacy = widget.privacy_enabled
      ? `<label class="privacy"><input type="checkbox" class="privacy-ok"><span>${esc(widget.privacy_text || 'Al continuar aceptas nuestra política de privacidad.')}${widget.privacy_url ? ` <a href="${esc(widget.privacy_url)}" target="_blank" rel="noopener">Ver política</a>` : ''}</span></label>`
      : '';
    const ia = widget.nivo_ai_enabled
      ? '<span class="ia-state"><span></span>NIVO IA activo</span>'
      : '';

    shadow.innerHTML = `
      <style>
        :host{all:initial!important;contain:style layout!important;color-scheme:light!important}
        *,*:before,*:after{box-sizing:border-box!important;font-family:Inter,system-ui,-apple-system,Segoe UI,sans-serif!important;text-transform:none!important;letter-spacing:normal!important}
        button,input{font:inherit!important;appearance:none!important;-webkit-appearance:none!important}
        button{margin:0!important;text-decoration:none!important}
        input{margin:0!important;background-image:none!important}
        .wrap{font-size:16px!important;line-height:1.35!important;color:#172033!important;text-align:left!important;direction:ltr!important;position:fixed;z-index:2147483000;${vertical}:${widget.offset_y || 24}px;${horizontal}:${widget.offset_x || 24}px}
        .launcher-wrap{display:flex;align-items:center;gap:10px;justify-content:${horizontal === 'right' ? 'flex-end' : 'flex-start'}}
        .launch-label{border:1px solid #c9e8e1;background:#eef9f6;color:#17384e;padding:10px 13px;border-radius:13px;box-shadow:0 8px 24px #0f172a20;font-size:13px;font-weight:650;cursor:pointer;width:220px;max-width:min(220px,calc(100vw - 100px));white-space:normal;overflow:visible;text-overflow:clip;line-height:1.25;text-align:center;overflow-wrap:anywhere;transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease,background .2s ease}
        .launch-label strong{font-weight:900;color:#0b2d44}
        .launch-label em{font-style:italic}
        .launcher-wrap:hover .launch-label,.launch-label:hover{transform:translateY(-5px) scale(1.02);box-shadow:0 14px 32px #0f172a2b;border-color:#a7d9ce;background:#f5fcfa}
        .launch{position:relative;width:62px;height:62px;border:0;border-radius:50%;background:${widget.accent_color};color:#fff;box-shadow:0 14px 32px #0f172a35;cursor:pointer;display:grid;place-items:center;padding:0;line-height:1;transition:transform .18s ease,box-shadow .18s ease}
        .launch:hover{transform:translateY(-3px) scale(1.03);box-shadow:0 18px 38px #0f172a3d}
        .launch-logo{width:46px;height:46px;border-radius:50%;background:#fff;display:grid;place-items:center;overflow:hidden;box-shadow:inset 0 0 0 1px #ffffff99}
        .launch-logo img{width:43px;height:43px;object-fit:contain;object-position:center center;display:block;margin:auto}
        .badge{display:none;position:absolute;right:-3px;top:-4px;min-width:20px;height:20px;padding:0 5px;border-radius:999px;background:#dc2626;color:#fff;border:2px solid #fff;font-size:11px;font-weight:800;place-items:center}
        .badge.on{display:grid}
        .box{width:min(390px,calc(100vw - 28px));height:min(610px,calc(100dvh - 105px));background:#fff;border:1px solid #dce5eb;border-radius:21px;box-shadow:0 22px 60px #0f172a35;overflow:hidden;display:none;flex-direction:column;margin-${vertical === 'bottom' ? 'bottom' : 'top'}:12px}
        .box.open{display:flex}
        .head{padding:14px 15px;background:#0f172a;color:#fff;display:grid;grid-template-columns:48px minmax(0,1fr) auto;align-items:center;gap:11px}
        .bot{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;background:#fff;overflow:hidden;box-shadow:0 8px 18px #00000020}
        .bot img{width:47px;height:47px;display:block;object-fit:contain;object-position:center center;margin:auto}
        .head-copy{min-width:0}
        .head-copy b{display:block;font-size:15px;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .head-copy small{display:block;color:#cbd5e1;margin-top:3px;font-size:11px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .head-meta{display:flex;align-items:center;gap:6px;margin-top:7px;flex-wrap:wrap}
        .online{display:inline-flex;align-items:center;gap:6px;color:#bfeee3;font-size:10px;font-weight:800}
        .online:before{content:'';width:7px;height:7px;border-radius:50%;background:#1bc99d;box-shadow:0 0 0 4px #1bc99d22}
        .presence-text{color:#d8e4ec;font-size:9px;font-weight:700}
        .ia-state{display:inline-flex;align-items:center;gap:5px;padding:3px 7px;border-radius:999px;background:#ffffff17;border:1px solid #ffffff20;color:#dffaf4;font-size:9px;font-weight:800}
        .ia-state span{width:5px;height:5px;border-radius:50%;background:#64e2c9}
        .close{width:34px;height:34px;border-radius:10px;background:#ffffff12;color:#fff;border:1px solid #ffffff16;font-size:19px;cursor:pointer;display:grid;place-items:center}
        .close:hover{background:#ffffff22}
        .msgs{flex:1;min-height:0;overflow-y:auto;overflow-x:hidden;scrollbar-gutter:stable;padding:15px;background:#f8fafc;display:flex;flex-direction:column;gap:9px;overscroll-behavior:contain}
        .m{max-width:84%;padding:10px 12px;border-radius:14px;white-space:pre-wrap;font-size:14px;line-height:1.4}
        .in{align-self:flex-start;background:#fff;border:1px solid #dce5eb;border-bottom-left-radius:4px}
        .out{align-self:flex-end;background:${widget.accent_color};color:#fff;border-bottom-right-radius:4px}
        .who{font-size:10px;font-weight:700;opacity:.7;margin-bottom:3px}
        .profile{padding:12px 14px 9px;border-top:1px solid #e5e7eb;display:grid;gap:8px;background:#fff}
        .profile[hidden]{display:none!important}
        .profile input,.composer input{width:100%;border:1px solid #d5dee5;border-radius:11px;padding:10px 11px;outline:none;background:#fff;color:#172033}
        .profile input:focus,.composer input:focus{border-color:${widget.accent_color};box-shadow:0 0 0 3px #0f766e16}
        .profile-actions{display:flex;justify-content:flex-end;gap:8px}
        .profile-save,.profile-cancel{border:0;border-radius:10px;padding:8px 10px;font-size:11px;font-weight:800;cursor:pointer}
        .profile-save{background:${widget.accent_color};color:#fff}
        .profile-cancel{background:#edf2f7;color:#334155}
        .profile-chip{display:flex;align-items:center;gap:9px;padding:9px 13px;border-top:1px solid #eef2f7;background:#fff}
        .profile-chip[hidden]{display:none!important}
        .profile-chip strong{font-size:11px;color:#334155;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .profile-chip button{margin-left:auto!important;border:0;background:#eef9f6;color:#0f766e;border-radius:9px;padding:6px 9px;font-size:10px;font-weight:800;cursor:pointer}
        .privacy{display:flex;align-items:flex-start;gap:7px;padding:4px 14px 8px;font-size:10px;line-height:1.35;color:#64748b}
        .privacy input{margin-top:2px}
        .privacy a{color:${widget.accent_color};font-weight:700}
        .quick-replies{display:flex;gap:7px;flex-wrap:wrap;padding:8px 12px 2px;border-top:1px solid #eef2f7}
        .quick{border:1px solid #cfe7e1;background:#f2fbf8;color:#176b60;border-radius:999px;padding:7px 10px;font-size:11px;font-weight:800;cursor:pointer}
        .quick:hover{transform:translateY(-1px)}
        .stamp{display:block;margin-top:4px;font-size:9px;opacity:.62}
        .typing span:last-child{display:inline-flex;gap:4px;align-items:center}
        .typing span:last-child:after{content:'•••';letter-spacing:2px;animation:nivoPulse 1s infinite}
        .composer{padding:11px;border-top:1px solid #e5e7eb;display:flex;gap:8px;background:#fff}
        .send{width:44px;min-width:44px;border:0;border-radius:11px;background:${widget.accent_color};color:#fff;cursor:pointer}
        .send:disabled{opacity:.55;cursor:not-allowed}
        .msgs-wrap{position:relative;flex:1;min-height:0;display:flex;flex-direction:column;overflow:hidden;background:#f8fafc}
        .msgs-wrap .msgs{flex:1;min-height:0;overflow-y:auto}
        .history-nav{position:relative;z-index:2;flex:0 0 auto;display:flex;align-items:center;justify-content:flex-end;gap:5px;padding:7px 10px;background:#fff;border-bottom:1px solid #e6efed;box-shadow:0 5px 16px #0f172a0a}
        .history-nav-label{display:inline-flex;align-items:center;gap:4px;margin-right:auto;color:#64748b;font-size:9px;font-weight:850;white-space:nowrap}
        .history-nav button{height:30px;border:1px solid #d8e5e2;background:#f8fcfb;color:#0f766e;border-radius:9px;padding:0 9px;font-size:9px;font-weight:850;cursor:pointer;display:inline-flex;align-items:center;gap:5px;transition:.18s ease}
        .history-nav button:hover{background:#eaf8f4;border-color:#a8d9ce;transform:translateY(-1px)}
        .history-nav button:active{transform:translateY(0)}
        .session-actions{display:flex;align-items:center;justify-content:center;gap:7px;padding:10px 12px;border-top:1px solid #eef2f7;background:#fff}
        .session-actions[hidden]{display:none!important}
        .finish-chat,.new-chat{width:100%;border-radius:13px;padding:9px 11px;font-size:10px;font-weight:850;cursor:pointer;display:flex;align-items:center;gap:9px;text-align:left;transition:.18s ease}
        .finish-chat{background:linear-gradient(180deg,#fffaf8,#fff4f1);color:#8f352b;border:1px solid #efc8c1;box-shadow:0 5px 14px #7f1d1d0d}
        .finish-chat:hover{border-color:#e7aaa0;background:#fff0ec;transform:translateY(-1px)}
        .finish-icon,.new-chat-icon{width:28px;height:28px;min-width:28px;border-radius:9px;display:inline-flex;align-items:center;justify-content:center;font-size:13px}
        .finish-icon{background:#fde7e2;color:#a33b2f}.new-chat-icon{background:#ffffff22;color:#fff}
        .finish-copy,.new-chat-copy{display:grid;gap:1px;flex:1;min-width:0}
        .finish-copy b,.new-chat-copy b{font-size:10px;line-height:1.15}.finish-copy small,.new-chat-copy small{font-size:8.5px;line-height:1.25;font-weight:650;opacity:.72}
        .finish-arrow,.new-chat-arrow{font-size:18px;line-height:1;opacity:.7}
        .new-chat{background:${widget.accent_color};color:#fff;border:1px solid ${widget.accent_color};box-shadow:0 6px 16px #0f766e22}
        .new-chat:hover{filter:brightness(.97);transform:translateY(-1px)}
        .finish-confirm{margin:8px 12px;padding:10px;border:1px solid #f0d1ca;border-radius:12px;background:#fff8f6;display:grid;gap:8px;font-size:11px;color:#5f2d28}
        .finish-confirm[hidden]{display:none!important}.finish-confirm-actions{display:flex;justify-content:flex-end;gap:7px}.finish-confirm button{border:0;border-radius:9px;padding:7px 9px;font-size:10px;font-weight:800;cursor:pointer}.finish-confirm .cancel-finish{background:#edf2f7;color:#334155}.finish-confirm .confirm-finish{background:#b94a3c;color:#fff}
        .survey-card{margin:9px 12px;padding:12px;border:1px solid #cfe7e1;border-radius:14px;background:#f7fcfa;display:grid;gap:9px}.survey-card[hidden]{display:none!important}.survey-card b{font-size:12px;color:#173a35}.survey-card small{font-size:10px;color:#64748b;line-height:1.35}
        .survey-stars{display:flex;gap:6px}.survey-stars button{width:34px;height:34px;border:1px solid #d7e6e2;border-radius:10px;background:#fff;cursor:pointer;font-size:18px}.survey-stars button.active{background:#fff4c7;border-color:#e5b72f;transform:translateY(-1px)}
        .survey-comment{width:100%;min-height:58px;resize:vertical;border:1px solid #d5dee5;border-radius:10px;padding:9px 10px;font-size:11px;color:#172033;outline:none}.survey-actions{display:flex;gap:7px;justify-content:flex-end;flex-wrap:wrap}.survey-actions button{border:0;border-radius:9px;padding:7px 9px;font-size:10px;font-weight:800;cursor:pointer}.survey-submit{background:${widget.accent_color};color:#fff}.survey-skip{background:#edf2f7;color:#334155}
        .composer.is-closed{display:none!important}
        .brand{display:flex;align-items:center;justify-content:center;gap:6px;text-align:center;font-size:10px;color:#94a3b8;padding:0 9px 9px}
        .brand b{color:#64748b}
        .brand img{width:18px;height:18px;object-fit:contain;object-position:center center;display:block;margin:0}
        @keyframes nivoPulse{50%{opacity:.35}}
        @keyframes nivoLauncherPulse{0%,100%{box-shadow:0 14px 32px #0f172a35}50%{box-shadow:0 14px 34px #0f766e55;transform:translateY(-2px)}}
        .nivo-pulse{animation:nivoLauncherPulse 3.8s ease-in-out infinite}
        @media(max-width:480px){
          .wrap{left:12px!important;right:12px!important;${vertical}:12px!important}
          .box{width:100%;height:min(585px,calc(100dvh - 92px));border-radius:18px}
          .launch-label{width:190px;max-width:calc(100vw - 92px);font-size:12px;padding:9px 11px;white-space:normal;line-height:1.25}
          .launch{width:56px;height:56px}
          .launch-logo{width:42px;height:42px}
          .launch-logo img{width:39px;height:39px}
          .head{grid-template-columns:44px minmax(0,1fr) 34px}
          .history-nav{padding:6px 8px}
          .history-nav-label{display:none}
          .history-nav button{height:29px;padding:0 8px}
          .finish-chat,.new-chat{padding:8px 9px}
          .bot{width:44px;height:44px}
          .bot img{width:43px;height:43px}
        }
        @media(prefers-reduced-motion:reduce){
          .launch,.launch-label{transition:none!important;transform:none!important;animation:none!important}
        }
      </style>
      <div class="wrap">
        <div class="box">
          <div class="head">
            <span class="bot"><img src="${esc(mascotUrl)}" alt="NIVO"></span>
            <div class="head-copy">
              <b>${esc(widget.header_title || widget.welcome_title || 'NIVO')}</b>
              <small>${esc(widget.header_subtitle || widget.assistant_subtitle || `Asistente virtual de ${widget.company}`)}</small>
              <div class="head-meta">
                ${experience.show_online_status === false ? '' : '<span class="online">En línea</span>'}
                ${ia}
                <span class="presence-text">${state.conversation_id ? 'Esperando tu respuesta' : 'Listo para ayudarte'}</span>
              </div>
            </div>
            <button class="close" aria-label="Minimizar">×</button>
          </div>
          <div class="msgs-wrap">
            <div class="history-nav" aria-label="Navegar historial"><span class="history-nav-label">↕ Historial</span><button type="button" class="history-start"><span>↑</span><span>Inicio</span></button><button type="button" class="history-end"><span>↓</span><span>Último</span></button></div>
            <div class="msgs"></div>
          </div>
          <div class="profile" ${(widget.ask_name || widget.ask_email) && !state.conversation_id ? '' : 'hidden'}>
            ${widget.ask_name ? `<input class="name" placeholder="Tu nombre" value="${esc(state.profile.name)}">` : ''}
            ${widget.ask_email ? `<input class="email" type="email" placeholder="Tu correo" value="${esc(state.profile.email)}">` : ''}
            <div class="profile-actions">
              <button type="button" class="profile-cancel">Cancelar</button>
              <button type="button" class="profile-save">Guardar</button>
            </div>
          </div>
          <div class="profile-chip" ${experience.show_profile_chip === false || (!state.profile.name && !state.profile.email) ? 'hidden' : ''}>
            <strong class="profile-chip-text">${esc(state.profile.name || state.profile.email || 'Visitante')}</strong>
            <button type="button" class="profile-edit">Editar</button>
          </div>
          ${privacy}
          ${experience.quick_replies_enabled && Array.isArray(experience.quick_replies) && experience.quick_replies.length
            ? `<div class="quick-replies">${experience.quick_replies.map(value => `<button type="button" class="quick" data-q="${esc(value)}">${esc(value)}</button>`).join('')}</div>`
            : ''}
          <div class="session-actions" ${state.conversation_id && !state.conversationClosed ? '' : 'hidden'}><button type="button" class="finish-chat"><span class="finish-icon">✓</span><span class="finish-copy"><b>Finalizar chat</b><small>Cierra la conversación y permite calificar la atención</small></span><span class="finish-arrow">›</span></button></div>
          <div class="finish-confirm" hidden><b>¿Finalizar esta conversación?</b><span>El historial se conserva y podrás calificar la atención.</span><div class="finish-confirm-actions"><button type="button" class="cancel-finish">Cancelar</button><button type="button" class="confirm-finish">Finalizar</button></div></div>
          <div class="survey-card" hidden><b>¿Cómo fue tu atención?</b><small>Tu opinión nos ayuda a mejorar. Selecciona de 1 a 5 estrellas.</small><div class="survey-stars">${[1,2,3,4,5].map(v=>`<button type="button" data-rating="${v}" aria-label="${v} estrellas">★</button>`).join('')}</div><textarea class="survey-comment" maxlength="1000" placeholder="Comentario opcional"></textarea><div class="survey-actions"><button type="button" class="survey-skip">Ahora no</button><button type="button" class="survey-submit">Enviar opinión</button></div></div>
          <div class="session-actions new-chat-wrap" ${state.conversationClosed ? '' : 'hidden'}><button type="button" class="new-chat"><span class="new-chat-icon">＋</span><span class="new-chat-copy"><b>Iniciar nuevo chat</b><small>Comienza una conversación nueva desde cero</small></span><span class="new-chat-arrow">›</span></button></div>
          <form class="composer${state.conversationClosed ? ' is-closed' : ''}">
            <input class="text" autocomplete="off" placeholder="Escribe un mensaje…">
            <button class="send" aria-label="Enviar">➤</button>
          </form>
          ${experience.show_branding === false
            ? ''
            : `<div class="brand"><img src="${esc(mascotUrl)}" alt=""><span>${esc(widget.brand_footer || 'NIVO Web Chat · Tecnología ZYNKO by ES MULTISERVICIOS')}</span></div>`}
        </div>
        <div class="launcher-wrap">
          <button class="launch-label" title="${esc(launcherPlain(launcherText))}">${launcherHtml}</button>
          <button class="launch${experience.launcher_animation === false ? '' : ' nivo-pulse'}" aria-label="Abrir NIVO Web Chat">
            <span class="launch-logo"><img src="${esc(mascotUrl)}" alt="NIVO"></span>
            <span class="badge">0</span>
          </button>
        </div>
      </div>`;

    const box = shadow.querySelector('.box');
    const launch = shadow.querySelector('.launch');
    const badge = shadow.querySelector('.badge');

    const persistOpen = () => {
      if (experience.remember_open_state) {
        localStorage.setItem(`${storagePrefix}.open`, state.opened ? '1' : '0');
      }
    };

    const toggle = () => {
      box.classList.toggle('open');
      state.opened = box.classList.contains('open');
      persistOpen();

      if (state.opened) {
        badge.classList.remove('on');
        badge.textContent = '0';
        showInitialGreeting(state.initialMessages || []);
        requestAnimationFrame(() => {
          const messages = shadow.querySelector('.msgs');
          if (messages) {
            messages.scrollTop = messages.scrollHeight;
          }
        });
        shadow.querySelector('.text')?.focus();
      }
    };

    launch.onclick = toggle;
    shadow.querySelector('.launch-label')?.addEventListener('click', toggle);
    shadow.querySelector('.close').onclick = () => {
      box.classList.remove('open');
      state.opened = false;
      persistOpen();
    };

    shadow.querySelector('.history-start')?.addEventListener('click', () => {
      const messages = shadow.querySelector('.msgs');
      if (messages) messages.scrollTo({ top: 0, behavior: 'smooth' });
    });
    shadow.querySelector('.history-end')?.addEventListener('click', () => {
      const messages = shadow.querySelector('.msgs');
      if (messages) messages.scrollTo({ top: messages.scrollHeight, behavior: 'smooth' });
    });

    if (experience.close_on_escape) {
      document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && state.opened) {
          box.classList.remove('open');
          state.opened = false;
          persistOpen();
        }
      });
    }

    shadow.querySelectorAll('.quick').forEach(button => {
      button.addEventListener('click', () => {
        const input = shadow.querySelector('.text');
        if (input) {
          input.value = button.dataset.q || '';
          input.focus();
        }
      });
    });

    shadow.querySelector('.profile-edit')?.addEventListener('click', () => {
      const profile = shadow.querySelector('.profile');
      if (profile) {
        profile.hidden = false;
        profile.querySelector('.name')?.focus();
      }
    });

    shadow.querySelector('.profile-cancel')?.addEventListener('click', () => {
      syncProfileUi();
    });

    shadow.querySelector('.profile-save')?.addEventListener('click', saveProfile);

    const syncConversationStateUi = (survey = null) => {
      const composer = shadow.querySelector('.composer');
      const actions = shadow.querySelector('.session-actions:not(.new-chat-wrap)');
      const newWrap = shadow.querySelector('.new-chat-wrap');
      const surveyCard = shadow.querySelector('.survey-card');
      if (composer) composer.classList.toggle('is-closed', state.conversationClosed);
      if (actions) actions.hidden = !state.conversation_id || state.conversationClosed;
      if (newWrap) newWrap.hidden = !state.conversationClosed;
      const shouldSurvey = state.conversationClosed && survey?.requested && !survey?.answered;
      if (surveyCard) surveyCard.hidden = !shouldSurvey;
      if (state.conversationClosed) setPresence('Chat finalizado');
    };

    shadow.querySelector('.finish-chat')?.addEventListener('click', () => {
      const confirmBox = shadow.querySelector('.finish-confirm');
      if (confirmBox) confirmBox.hidden = false;
    });
    shadow.querySelector('.cancel-finish')?.addEventListener('click', () => {
      const confirmBox = shadow.querySelector('.finish-confirm');
      if (confirmBox) confirmBox.hidden = true;
    });
    shadow.querySelector('.confirm-finish')?.addEventListener('click', async () => {
      const button = shadow.querySelector('.confirm-finish');
      if (button) button.disabled = true;
      try {
        const result = await call({ action: 'close' });
        state.conversationClosed = true;
        state.surveyConversationId = result.survey?.conversation_id || state.conversation_id;
        const confirmBox = shadow.querySelector('.finish-confirm');
        if (confirmBox) confirmBox.hidden = true;
        await refresh();
        syncConversationStateUi(result.survey || { requested: true, answered: false });
      } catch (error) {
        add(shadow, error.message, 'in', 'Sistema');
      } finally {
        if (button) button.disabled = false;
      }
    });
    shadow.querySelectorAll('.survey-stars button').forEach(button => button.addEventListener('click', () => {
      state.selectedRating = parseInt(button.dataset.rating || '0', 10);
      shadow.querySelectorAll('.survey-stars button').forEach(star => star.classList.toggle('active', parseInt(star.dataset.rating || '0', 10) <= state.selectedRating));
    }));
    shadow.querySelector('.survey-submit')?.addEventListener('click', async () => {
      if (!state.selectedRating) {
        setPresence('Selecciona una calificación', true);
        return;
      }
      const button = shadow.querySelector('.survey-submit');
      if (button) button.disabled = true;
      try {
        await call({ action: 'survey', conversation_id: state.surveyConversationId || state.conversation_id, rating: state.selectedRating, comment: shadow.querySelector('.survey-comment')?.value || '' });
        const surveyCard = shadow.querySelector('.survey-card');
        if (surveyCard) surveyCard.innerHTML = '<b>¡Gracias por tu opinión! 💚</b><small>Tu calificación quedó registrada.</small>';
        setPresence('Opinión registrada', true);
      } catch (error) {
        add(shadow, error.message, 'in', 'Sistema');
      } finally {
        if (button) button.disabled = false;
      }
    });
    shadow.querySelector('.survey-skip')?.addEventListener('click', () => {
      const surveyCard = shadow.querySelector('.survey-card');
      if (surveyCard) surveyCard.hidden = true;
    });
    shadow.querySelector('.new-chat')?.addEventListener('click', async () => {
      const button = shadow.querySelector('.new-chat');
      if (button) button.disabled = true;
      try {
        await call({ action: 'new_chat' });
        state.conversation_id = 0;
        state.conversationClosed = false;
        state.surveyConversationId = 0;
        state.selectedRating = 0;
        state.lastCount = 0;
        state.initialGreetingShown = false;
        state.initialMessages = [];
        const messages = shadow.querySelector('.msgs');
        if (messages) messages.innerHTML = '';
        const surveyCard = shadow.querySelector('.survey-card');
        if (surveyCard) surveyCard.hidden = true;
        syncConversationStateUi(null);
        syncProfileUi();
        await showInitialGreeting([]);
        shadow.querySelector('.text')?.focus();
      } catch (error) {
        add(shadow, error.message, 'in', 'Sistema');
      } finally {
        if (button) button.disabled = false;
      }
    });

    syncConversationStateUi(data.survey || null);

    shadow.querySelector('.composer').onsubmit = async event => {
      event.preventDefault();

      if (state.conversationClosed) {
        setPresence('Inicia un nuevo chat para continuar', true);
        return;
      }

      if (state.sending && experience.prevent_double_submit) {
        return;
      }

      const input = shadow.querySelector('.text');
      const body = input.value.trim();
      if (!body) {
        return;
      }

      const maxLength = Math.max(120, Math.min(3000, parseInt(experience.max_message_length || 1000, 10)));
      if (body.length > maxLength) {
        showLocal(`Tu mensaje supera el máximo de ${maxLength} caracteres.`);
        return;
      }

      captureProfileInputs();

      if (widget.profile_required && widget.ask_name && !state.profile.name) {
        shadow.querySelector('.profile').hidden = false;
        shadow.querySelector('.name')?.focus();
        showLocal('Ingresa tu nombre para continuar.');
        return;
      }

      if (widget.profile_required && widget.ask_email && !/^\S+@\S+\.\S+$/.test(state.profile.email)) {
        shadow.querySelector('.profile').hidden = false;
        showLocal('Ingresa un correo válido para continuar.');
        return;
      }

      const privacyOk = shadow.querySelector('.privacy-ok');
      if (widget.privacy_enabled && !privacyOk?.checked) {
        showLocal('Acepta el aviso de privacidad para continuar.');
        return;
      }

      state.sending = true;
      const sendButton = shadow.querySelector('.send');
      if (sendButton) {
        sendButton.disabled = true;
      }

      add(shadow, body, 'out', state.profile.name || 'Tú');
      input.value = '';
      touchSession();
      setPresence('NIVO está escribiendo…');
      const typing = experience.typing_indicator === false ? null : addTyping(shadow);

      try {
        const result = await call({
          action: 'send',
          body,
          name: state.profile.name,
          email: state.profile.email,
          privacy_accepted: !widget.privacy_enabled || Boolean(privacyOk?.checked)
        });

        state.conversation_id = result.conversation_id;
        persistProfileLocal();
        const delay = Math.max(0, Math.min(2500, parseInt(experience.typing_delay_ms || 650, 10)));

        if (delay) {
          await sleep(delay);
        }

        typing?.remove();

        if (result.bot_reply) {
          add(shadow, result.bot_reply, 'in', 'NIVO');
        }

        syncProfileUi();
        setPresence(result.handoff ? 'Transferencia a atención humana' : 'Esperando tu respuesta');
        touchSession();

        setTimeout(async () => {
          await refresh();
          try {
            state.ws?.close();
            const next = await call({ action: 'bootstrap' });
            connect(next);
          } catch {
            // El polling mantiene la conversación incluso si el WebSocket no reconecta.
          }
        }, 180);
      } catch (error) {
        typing?.remove();
        showLocal(error.message);
        setPresence('No se pudo enviar', true);
      } finally {
        state.sending = false;
        if (sendButton) {
          sendButton.disabled = false;
        }
      }
    };

    const remembered = experience.remember_open_state && localStorage.getItem(`${storagePrefix}.open`) === '1';
    const autoDelay = Math.max(0, Math.min(30, parseInt(experience.auto_open_delay || 0, 10)));
    const proactiveSeen = localStorage.getItem(`${storagePrefix}.proactive`) === '1';
    const wantsAuto = (widget.display_mode || 'launcher') === 'open' || remembered || autoDelay > 0;

    if (wantsAuto && (!experience.proactive_once || !proactiveSeen || remembered)) {
      const openNow = () => {
        box.classList.add('open');
        state.opened = true;
        persistOpen();
        showInitialGreeting(state.initialMessages || []);
        requestAnimationFrame(() => {
          const messages = shadow.querySelector('.msgs');
          if (messages) {
            messages.scrollTop = messages.scrollHeight;
          }
        });
        if (experience.proactive_once) {
          localStorage.setItem(`${storagePrefix}.proactive`, '1');
        }
      };

      if (autoDelay > 0 && !remembered) {
        setTimeout(openNow, autoDelay * 1000);
      } else {
        openNow();
      }
    }

    state.shadow = shadow;
    syncProfileUi();

    function showLocal(message) {
      add(shadow, message, 'in', 'Sistema');
    }
  }

  function captureProfileInputs() {
    if (!state.shadow) {
      return;
    }

    const nameInput = state.shadow.querySelector('.name');
    const emailInput = state.shadow.querySelector('.email');

    if (nameInput) {
      state.profile.name = nameInput.value.trim();
    }

    if (emailInput) {
      state.profile.email = emailInput.value.trim();
    }
  }

  function persistProfileLocal() {
    const experience = state.widget?.experience || {};
    if (experience.persist_profile === false) {
      return;
    }

    if (state.profile.name) {
      localStorage.setItem(`${storagePrefix}.name`, state.profile.name);
    } else {
      localStorage.removeItem(`${storagePrefix}.name`);
    }

    if (state.profile.email) {
      localStorage.setItem(`${storagePrefix}.email`, state.profile.email);
    } else {
      localStorage.removeItem(`${storagePrefix}.email`);
    }
  }

  async function saveProfile() {
    if (!state.shadow) {
      return;
    }

    captureProfileInputs();

    if (state.widget?.ask_email && state.profile.email && !/^\S+@\S+\.\S+$/.test(state.profile.email)) {
      add(state.shadow, 'Ingresa un correo válido para guardar tu perfil.', 'in', 'Sistema');
      return;
    }

    try {
      const result = await call({
        action: 'profile',
        name: state.profile.name,
        email: state.profile.email
      });
      state.profile.name = result.name || '';
      state.profile.email = result.email || '';
      persistProfileLocal();
      syncProfileUi();
      setPresence('Perfil actualizado', true);
    } catch (error) {
      add(state.shadow, error.message, 'in', 'Sistema');
    }
  }

  function syncProfileUi() {
    if (!state.shadow) {
      return;
    }

    const experience = state.widget?.experience || {};
    const profile = state.shadow.querySelector('.profile');
    const chip = state.shadow.querySelector('.profile-chip');
    const chipText = state.shadow.querySelector('.profile-chip-text');
    const nameInput = state.shadow.querySelector('.name');
    const emailInput = state.shadow.querySelector('.email');

    if (nameInput) {
      nameInput.value = state.profile.name || '';
    }

    if (emailInput) {
      emailInput.value = state.profile.email || '';
    }

    const hasProfile = Boolean(state.profile.name || state.profile.email);
    const missingRequiredName = Boolean(state.widget?.profile_required && state.widget?.ask_name && !state.profile.name);
    const missingRequiredEmail = Boolean(state.widget?.profile_required && state.widget?.ask_email && !state.profile.email);

    if (profile) {
      profile.hidden = Boolean(state.conversation_id) || (hasProfile && !missingRequiredName && !missingRequiredEmail);
    }

    if (chip) {
      const canShowChip = Boolean(state.conversation_id || hasProfile);
      chip.hidden = experience.show_profile_chip === false || !canShowChip;
      if (chipText) {
        chipText.textContent = state.profile.name || state.profile.email || 'Agregar nombre';
      }
    }
  }

  async function showInitialGreeting(existingMessages) {
    if (!state.shadow || state.initialGreetingShown || existingMessages.length) {
      return;
    }

    state.initialGreetingShown = true;
    const experience = state.widget?.experience || {};
    const message = state.widget?.initial_greeting || state.widget?.welcome_message || '¿En qué puedo ayudarte hoy?';
    const delay = Math.max(350, Math.min(5000, parseInt(experience.initial_greeting_typing_ms || 1200, 10)));
    const typing = experience.typing_indicator === false ? null : addTyping(state.shadow);

    setPresence('NIVO está escribiendo…');
    await sleep(delay);
    typing?.remove();
    add(state.shadow, message, 'in', 'NIVO');
    setPresence('Listo para ayudarte');
  }

  function add(shadow, body, direction, who, autoScroll = true) {
    const message = document.createElement('div');
    const stamp = state.widget?.experience?.show_timestamps
      ? `<span class="stamp">${new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>`
      : '';

    message.className = `m ${direction}`;
    message.innerHTML = `<div class="who">${esc(who)}</div>${esc(body)}${stamp}`;
    const box = shadow.querySelector('.msgs');
    box.appendChild(message);
    if (autoScroll) {
      box.scrollTop = box.scrollHeight;
    }
  }

  function addTyping(shadow) {
    const message = document.createElement('div');
    message.className = 'm in typing';
    message.innerHTML = '<div class="who">NIVO</div><span>Escribiendo…</span>';
    shadow.querySelector('.msgs').appendChild(message);
    shadow.querySelector('.msgs').scrollTop = 999999;
    return message;
  }

  function renderMessages(messages, notify = true) {
    if (!state.shadow) {
      return;
    }

    const oldCount = state.lastCount;
    state.lastCount = messages.length;
    const box = state.shadow.querySelector('.msgs');
    const previousScrollTop = box.scrollTop;
    const distanceFromBottom = box.scrollHeight - box.clientHeight - box.scrollTop;
    const wasNearBottom = oldCount === 0 || distanceFromBottom < 56;

    if (messages.length) {
      box.innerHTML = '';
      messages.forEach(message => {
        add(
          state.shadow,
          message.body || '',
          message.direction === 'in' ? 'out' : 'in',
          message.sender_type === 'bot' ? 'NIVO' : (message.direction === 'in' ? (state.profile.name || 'Tú') : 'Agente'),
          false
        );
      });
      if (wasNearBottom) {
        box.scrollTop = box.scrollHeight;
      } else {
        box.scrollTop = Math.min(previousScrollTop, Math.max(0, box.scrollHeight - box.clientHeight));
      }
    }

    if (notify && messages.length > oldCount) {
      const incoming = messages.slice(oldCount).filter(message => message.direction === 'out').length;

      if (incoming) {
        beep();
        touchSession();
        setPresence('Nuevo mensaje', true);

        if (state.widget?.experience?.page_title_alert && !state.opened) {
          document.title = `(${incoming}) NIVO · ${state.originalTitle}`;
          setTimeout(() => {
            document.title = state.originalTitle;
          }, 5000);
        }

        if (!state.opened) {
          const badge = state.shadow.querySelector('.badge');
          badge.textContent = String(Math.min(99, (parseInt(badge.textContent, 10) || 0) + incoming));
          badge.classList.add('on');
        }
      }
    }
  }

  async function refresh() {
    try {
      const data = await call({ action: 'messages' });
      state.conversation_id = data.conversation_id;
      state.conversationClosed = Boolean(data.conversation_closed);
      state.surveyConversationId = data.survey?.conversation_id || state.surveyConversationId;
      renderMessages(data.messages || []);
      syncProfileUi();
      const composer = state.shadow?.querySelector('.composer');
      if (composer) composer.classList.toggle('is-closed', state.conversationClosed);
      const actions = state.shadow?.querySelector('.session-actions:not(.new-chat-wrap)');
      if (actions) actions.hidden = !state.conversation_id || state.conversationClosed;
      const newWrap = state.shadow?.querySelector('.new-chat-wrap');
      if (newWrap) newWrap.hidden = !state.conversationClosed;
      const surveyCard = state.shadow?.querySelector('.survey-card');
      if (surveyCard && state.conversationClosed && data.survey?.requested && !data.survey?.answered) surveyCard.hidden = false;
    } catch {
      // El refresco silencioso nunca debe bloquear el formulario principal.
    }
  }

  function connect(data) {
    if (state.poll) {
      clearInterval(state.poll);
    }

    const experience = state.widget?.experience || {};
    const poll = Math.max(3, Math.min(60, parseInt(experience.poll_interval_seconds || 5, 10))) * 1000;
    const reconnect = Math.max(1, Math.min(30, parseInt(experience.reconnect_seconds || 3, 10))) * 1000;
    state.poll = setInterval(refresh, poll);

    if (!data.ws_url || !data.ws_token) {
      return;
    }

    try {
      const socket = new WebSocket(`${data.ws_url}?token=${encodeURIComponent(data.ws_token)}`);
      state.ws = socket;

      socket.onmessage = event => {
        try {
          const message = JSON.parse(event.data);
          const eventConversation = message.data?.conversation_id || message.entity_id;

          if (message.type === 'event' && (!state.conversation_id || String(eventConversation) === String(state.conversation_id))) {
            refresh();
          }
        } catch {
          // Ignoramos frames inválidos y dejamos el polling como respaldo.
        }
      };

      socket.onclose = () => setTimeout(refresh, reconnect);
    } catch {
      // El polling mantiene disponible el Web Chat si WebSocket no está listo.
    }
  }

  boot();
})();
