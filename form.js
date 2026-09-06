const form = document.querySelector('#lead-form');
const status = document.querySelector('#form-status');
const menuToggle = document.querySelector('.menu-toggle');
const primaryNav = document.querySelector('#primary-nav');
const submitButton = form?.querySelector('button[type="submit"]');
const thankYouPanel = document.querySelector('#form-thank-you');
const submissionStorageKey = 'cloudsys-contact-submitted-at';
const submissionCooldownMs = 60 * 60 * 1000;
let turnstileWidget;
let turnstileVerified = false;
let preserveStatus = false;

if (submitButton) submitButton.disabled = true;

function setFormStatus(message, state = '') {
  if (!status) return;
  status.textContent = message;
  if (state) status.dataset.state = state;
  else delete status.dataset.state;
}

function storedSubmissionIsRecent() {
  try {
    return Number(localStorage.getItem(submissionStorageKey) || 0) > Date.now() - submissionCooldownMs;
  } catch {
    return false;
  }
}

function rememberSubmission() {
  try {
    localStorage.setItem(submissionStorageKey, String(Date.now()));
  } catch {
    // The server-side IP limit still applies when browser storage is unavailable.
  }
}

function showThankYou() {
  if (!form || !thankYouPanel) return;
  form.hidden = true;
  thankYouPanel.hidden = false;
  if (typeof turnstile !== 'undefined' && turnstileWidget !== undefined) {
    turnstile.remove(turnstileWidget);
    turnstileWidget = undefined;
  }
  thankYouPanel.focus();
}

function setMenuState(open, returnFocus = false) {
  if (!menuToggle || !primaryNav) return;
  menuToggle.setAttribute('aria-expanded', String(open));
  menuToggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
  primaryNav.classList.toggle('is-open', open);
  document.body.classList.toggle('nav-open', open);
  if (!open && returnFocus) menuToggle.focus();
}

menuToggle?.addEventListener('click', () => {
  setMenuState(menuToggle.getAttribute('aria-expanded') !== 'true');
});

primaryNav?.addEventListener('click', (event) => {
  if (event.target.closest('a')) setMenuState(false);
});

document.addEventListener('click', (event) => {
  if (menuToggle?.getAttribute('aria-expanded') !== 'true') return;
  if (primaryNav?.contains(event.target) || menuToggle?.contains(event.target)) return;
  setMenuState(false);
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'Tab' && menuToggle?.getAttribute('aria-expanded') === 'true') {
    const links = [...primaryNav.querySelectorAll('a[href]')];
    const last = links.at(-1);
    if (event.shiftKey && document.activeElement === menuToggle && last) {
      event.preventDefault(); last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault(); menuToggle.focus();
    }
  }
  if (event.key === 'Escape' && menuToggle?.getAttribute('aria-expanded') === 'true') {
    setMenuState(false, true);
  }
});

window.addEventListener('resize', () => {
  if (window.innerWidth > 1280) setMenuState(false);
});

function requireVerification(message = '') {
  turnstileVerified = false;
  if (submitButton) submitButton.disabled = true;
  if (status && message && !preserveStatus) {
    setFormStatus(message, 'verification');
    status.dataset.source = 'verification';
  }
}

window.addEventListener('load', () => {
  if (storedSubmissionIsRecent()) {
    showThankYou();
    return;
  }

  const container = document.querySelector('#turnstile-container');
  if (!container || typeof turnstile === 'undefined') {
    requireVerification('Verification could not load. Please refresh and try again.');
    return;
  }
  const isLocal = ['localhost', '127.0.0.1'].includes(location.hostname);
  turnstileWidget = turnstile.render(container, {
    sitekey: isLocal ? '1x00000000000000000000AA' : '0x4AAAAAAD9LvOBZY6EzQHiD',
    theme: 'light',
    size: window.innerWidth <= 360 ? 'compact' : 'flexible',
    callback: () => {
      turnstileVerified = true;
      if (submitButton) submitButton.disabled = false;
      if (!preserveStatus && status?.dataset.source === 'verification') {
        setFormStatus('');
        delete status.dataset.source;
      }
    },
    'expired-callback': () => requireVerification('Verification expired. Please verify again.'),
    'error-callback': () => requireVerification('Verification failed to load. Please refresh and try again.')
  });
});

form?.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!turnstileVerified) {
    setFormStatus('Your request was not sent. Please complete the security verification first.', 'error');
    status.focus();
    return;
  }

  const button = form.querySelector('button[type="submit"]');
  button.disabled = true;
  preserveStatus = false;
  delete status.dataset.source;
  setFormStatus('Sending your request\u2026', 'sending');
  let submissionSucceeded = false;

  try {
    const response = await fetch('/api/contact.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.fromEntries(new FormData(form)))
    });
    if (!response.ok) {
      const data = await response.json().catch(() => ({}));
      throw new Error(data.error || 'Unable to send');
    }
    submissionSucceeded = true;
    rememberSubmission();
    showThankYou();
  } catch (error) {
    preserveStatus = true;
    const detail = error?.message || '';
    let message;
    if (error instanceof TypeError) {
      message = 'Your request was not sent because the contact service could not be reached. Check your connection and try again.';
    } else if (!detail || detail === 'Unable to send message.' || detail === 'Unable to send') {
      message = 'Your request was not sent because the contact service is temporarily unavailable. Please try again shortly or email support@cloudsysllc.com.';
    } else {
      message = `Your request was not sent. ${detail}`;
    }
    setFormStatus(message, 'error');
    status.focus();
  } finally {
    if (!submissionSucceeded) {
      requireVerification();
      if (typeof turnstile !== 'undefined' && turnstileWidget !== undefined) turnstile.reset(turnstileWidget);
    }
  }
});
