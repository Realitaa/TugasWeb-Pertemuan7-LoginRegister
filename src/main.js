import './style.css';
import 'iconify-icon';
import 'preline';

import { initFlashToast } from './components/ui/Toast.js';
import { initThemeSwitch } from './utils/theme.js';

function boot() {
  initThemeSwitch();
  initFlashToast();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}
