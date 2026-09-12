/**
 * Theme Switch Utility
 * Manages 'default' (light), 'dark', and 'auto' (system) modes.
 */
export function updateThemeButtons(theme) {
  document.querySelectorAll('[data-hs-theme-click-value]').forEach((btn) => {
    const val = btn.getAttribute('data-hs-theme-click-value');
    if (val === theme) {
      btn.classList.add('bg-white', 'text-gray-800', 'shadow-xs', 'dark:bg-neutral-700', 'dark:text-white');
      btn.classList.remove('text-gray-500', 'dark:text-neutral-400');
    } else {
      btn.classList.remove('bg-white', 'text-gray-800', 'shadow-xs', 'dark:bg-neutral-700', 'dark:text-white');
      btn.classList.add('text-gray-500', 'dark:text-neutral-400');
    }
  });
}

export function applyTheme(theme) {
  const html = document.documentElement;
  const isDark =
    theme === 'dark' ||
    (theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);

  if (isDark) {
    html.classList.add('dark');
    html.classList.remove('light');
  } else {
    html.classList.remove('dark');
    html.classList.add('light');
  }

  localStorage.setItem('hs_theme', theme);
  updateThemeButtons(theme);
}

export function initThemeSwitch() {
  function getStoredTheme() {
    return localStorage.getItem('hs_theme') || 'auto';
  }

  // Ensure window.__setTheme is always available
  window.__setTheme = applyTheme;

  // Bind click handlers
  document.querySelectorAll('[data-hs-theme-click-value]').forEach((btn) => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const targetTheme = btn.getAttribute('data-hs-theme-click-value');
      if (targetTheme) {
        applyTheme(targetTheme);
      }
    });
  });

  // Observe OS theme changes when auto is active
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (getStoredTheme() === 'auto') {
      applyTheme('auto');
    }
  });

  // Also sync when Preline modals or overlays open
  document.addEventListener('open.hs.overlay', () => {
    updateThemeButtons(getStoredTheme());
  });

  // Initial sync
  applyTheme(getStoredTheme());
}

