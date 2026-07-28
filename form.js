const form = document.querySelector('#lead-form');
const status = document.querySelector('#form-status');
const menuToggle = document.querySelector('.menu-toggle');
const primaryNav = document.querySelector('#primary-nav');
let turnstileWidget;

menuToggle?.addEventListener('click', () => {
  const open = menuToggle.getAttribute('aria-expanded') === 'true';
  menuToggle.setAttribute('aria-expanded', String(!open));
  primaryNav?.classList.toggle('is-open', !open);
});

primaryNav?.addEventListener('click', (event) => {
  if (!event.target.closest('a')) return;
  menuToggle?.setAttribute('aria-expanded', 'false');
  primaryNav.classList.remove('is-open');
});

window.addEventListener('load', () => {
  const container = document.querySelector('#turnstile-container');
  if (!container || typeof turnstile === 'undefined') {
    if (status) status.textContent = 'Verification could not load. Please refresh and try again.';
    return;
  }
  const isLocal = ['localhost', '127.0.0.1'].includes(location.hostname);
  turnstileWidget = turnstile.render(container, {
    sitekey: isLocal ? '1x00000000000000000000AA' : '0x4AAAAAAD9LvOBZY6EzQHiD',
    theme: 'light',
    size: window.innerWidth <= 360 ? 'compact' : 'flexible'
  });
});

form?.addEventListener('submit', async (event) => {
  event.preventDefault();
  const button = form.querySelector('button[type="submit"]');
  button.disabled = true;
  status.textContent = 'Sending\u2026';

  try {
    const response = await fetch('/api/contact', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(Object.fromEntries(new FormData(form)))
    });
    if (!response.ok) { const data = await response.json().catch(() => ({})); throw new Error(data.error || 'Unable to send'); }
    form.reset();
    status.textContent = 'Thanks \u2014 a CloudSys expert will be in touch shortly.';
    if (typeof turnstile !== 'undefined' && turnstileWidget !== undefined) turnstile.reset(turnstileWidget);
  } catch (error) {
    status.textContent = error.message || `We couldn't send your request just now. Please try again shortly.`;
    if (typeof turnstile !== 'undefined' && turnstileWidget !== undefined) turnstile.reset(turnstileWidget);
  } finally {
    button.disabled = false;
  }
});
