(() => {
  'use strict';
  const profile = document.querySelector('.admin-profile');
  if (!profile) return;
  document.addEventListener('click', (event) => {
    if (profile.open && !profile.contains(event.target)) profile.open = false;
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && profile.open) {
      profile.open = false;
      profile.querySelector('summary')?.focus();
    }
  });
})();
