(() => {
  const launcher = document.querySelector('#chat-launcher');
  const entry = document.querySelector('#chat-entry');
  const panel = document.querySelector('#chat-panel');
  const closeButton = document.querySelector('#chat-close');
  const body = document.querySelector('#chat-body');
  const form = document.querySelector('#chat-form');
  const input = document.querySelector('#chat-input');
  const sendButton = document.querySelector('#chat-send');
  const note = document.querySelector('#chat-note');
  const suggestions = document.querySelector('#chat-suggestions');
  const security = document.querySelector('#chat-security');
  const turnstileContainer = document.querySelector('#chat-turnstile');
  if (!launcher || !panel || !form || !input || !body) return;

  const sitekey = ['localhost', '127.0.0.1'].includes(location.hostname) ? '1x00000000000000000000AA' : '0x4AAAAAAD9LvOBZY6EzQHiD';
  let widgetId;
  let verificationToken = '';
  let chatSession = '';
  let history = [];
  let busy = false;
  let verified = false;

  async function refreshChatAccess() {
    try {
      const response = await fetch('/api/chat-access.php', { credentials: 'same-origin', cache: 'no-store' });
      const data = await response.json().catch(() => ({}));
      const allowed = response.ok && data.allowed === true;
      entry.hidden = !allowed;
      if (!allowed) {
        panel.hidden = true;
        launcher.setAttribute('aria-expanded', 'false');
      }
      return allowed;
    } catch {
      entry.hidden = true;
      panel.hidden = true;
      return false;
    }
  }

  function setOpen(open) {
    panel.hidden = !open;
    entry?.classList.toggle('is-open', open);
    launcher.setAttribute('aria-expanded', String(open));
    if (open) {
      renderTurnstile();
      window.setTimeout(() => (verified ? input : closeButton)?.focus(), 0);
    } else launcher.focus();
  }

  function addMessage(role, text, options = {}) {
    const message = document.createElement('div');
    message.className = `chat-message ${role}${options.error ? ' error' : ''}`;
    message.textContent = text;
    body.appendChild(message);
    if (options.handoff) {
      const link = document.createElement('a');
      link.className = 'chat-handoff';
      link.href = '#contact';
      link.textContent = 'Click here to reach out →';
      link.addEventListener('click', (event) => {
        event.preventDefault();
        setOpen(false);
        const contact = document.querySelector('#contact');
        if (!contact) return;
        window.history.pushState(null, '', '#contact');
        contact.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
      });
      body.appendChild(link);
    }
    body.scrollTop = body.scrollHeight;
    return message;
  }

  function setComposerEnabled(enabled) {
    verified = enabled;
    input.disabled = !enabled;
    sendButton.disabled = !enabled || busy;
    if (enabled) {
      security.hidden = true;
      note.textContent = 'Verified · 15 messages per chat · refresh starts a new conversation';
    }
  }

  function renderTurnstile() {
    if (chatSession || widgetId !== undefined || typeof window.turnstile === 'undefined') return;
    widgetId = window.turnstile.render(turnstileContainer, {
      sitekey,
      theme: 'light',
      size: window.innerWidth <= 360 ? 'compact' : 'flexible',
      callback: (token) => {
        if (chatSession) return;
        verificationToken = token;
        setComposerEnabled(true);
        input.focus();
      },
      'expired-callback': () => resetVerification('Verification expired. Please verify again.'),
      'error-callback': () => resetVerification('Verification could not load. Refresh and try again.')
    });
  }

  function resetVerification(message, sessionExpired = false) {
    // Widget tokens expire independently of the server's verified chat session.
    if (chatSession && !sessionExpired) return;
    verificationToken = '';
    if (sessionExpired && chatSession) {
      history = [];
      body.replaceChildren();
      addMessage('assistant', 'Your previous chat session has expired. Verify again to start a new conversation.');
    }
    chatSession = '';
    setComposerEnabled(false);
    security.hidden = false;
    note.textContent = message;
    if (typeof window.turnstile !== 'undefined') {
      if (widgetId !== undefined) window.turnstile.reset(widgetId);
      else renderTurnstile();
    }
  }

  async function sendMessage(text) {
    const clean = text.trim();
    if (!clean || busy || !verified) return;
    if (clean.length > 600) {
      note.textContent = 'Please keep your question under 600 characters.';
      return;
    }

    busy = true;
    input.value = '';
    sendButton.disabled = true;
    suggestions.hidden = true;
    addMessage('user', clean);
    const typing = document.createElement('div');
    typing.className = 'chat-message assistant';
    typing.setAttribute('aria-label', 'CloudSys guide is responding');
    typing.innerHTML = '<span class="chat-typing" aria-hidden="true"><i></i><i></i><i></i></span>';
    body.appendChild(typing);
    body.scrollTop = body.scrollHeight;

    try {
      const response = await fetch('/api/chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          message: clean,
          chat_session: chatSession,
          'cf-turnstile-response': chatSession ? '' : verificationToken
        })
      });
      const data = await response.json().catch(() => ({}));
      typing.remove();
      if (!response.ok) {
        if (data.access_denied) {
          entry.hidden = true;
          panel.hidden = true;
        }
        if (data.verification_required) resetVerification(data.error || 'Please verify again.', true);
        throw new Error(data.error || 'The guide could not respond.');
      }
      chatSession = data.chat_session || chatSession;
      // The server now owns verification/context; retire the single-use widget.
      if (chatSession && widgetId !== undefined && typeof window.turnstile !== 'undefined') {
        const completedWidget = widgetId;
        widgetId = undefined;
        window.turnstile.remove(completedWidget);
      }
      verificationToken = '';
      setComposerEnabled(true);
      history.push({ role: 'user', content: clean }, { role: 'assistant', content: data.reply });
      history = history.slice(-6);
      addMessage('assistant', data.reply, { handoff: Boolean(data.handoff) });
      note.textContent = 'Verified · 15 messages per chat · refresh starts a new conversation';
    } catch (error) {
      typing.remove();
      addMessage('assistant', error?.message || 'The guide is temporarily unavailable. Please try again.', { error: true });
    } finally {
      busy = false;
      sendButton.disabled = !verified;
      if (verified) input.focus();
    }
  }

  launcher.addEventListener('click', () => setOpen(true));
  closeButton?.addEventListener('click', () => setOpen(false));
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    sendMessage(input.value);
  });
  input.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      form.requestSubmit();
    }
  });
  suggestions?.addEventListener('click', (event) => {
    const button = event.target.closest('button[data-question]');
    if (button && verified) sendMessage(button.dataset.question || '');
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && !panel.hidden) setOpen(false);
  });
  window.addEventListener('load', () => {
    refreshChatAccess().then((allowed) => {
      if (allowed && !panel.hidden) renderTurnstile();
    });
  });
  window.addEventListener('pageshow', refreshChatAccess);
})();
