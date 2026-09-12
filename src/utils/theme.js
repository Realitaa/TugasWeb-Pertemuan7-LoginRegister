/**
 * Theme Switch Utility
 * Manages 'default' (light), 'dark', and 'auto' (system) modes.
 */
export function initThemeSwitch() {
  function getStoredTheme() {
    return localStorage.getItem('hs_theme') || 'auto';
  }

  function applyTheme(theme) {
    if (typeof window.__setTheme === 'function') {
      window.__setTheme(theme);
    } else {
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
    }
  }

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

  // Initial sync
  applyTheme(getStoredTheme());
}
