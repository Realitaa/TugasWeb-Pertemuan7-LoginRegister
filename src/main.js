import './style.css';
import 'iconify-icon';
import 'preline';

import { initFlashToast } from './components/ui/Toast.js';
import { initThemeSwitch } from './utils/theme.js';
import { initLaunchesPerYearChart, initLaunchSitesChart } from './charts.js';

function initSpaceXCharts() {
  const dataEl = document.getElementById('spacex-stats-data');
  if (!dataEl) return;

  try {
    const stats = JSON.parse(dataEl.textContent || '{}');
    if (stats.launches_per_year && stats.launches_per_year.by_year) {
      initLaunchesPerYearChart('launches-per-year-chart', stats.launches_per_year.by_year);
    }
    if (stats.launch_sites) {
      initLaunchSitesChart('launch-sites-chart', stats.launch_sites);
    }
  } catch (err) {
    console.error('Failed to initialize SpaceX dashboard charts:', err);
  }
}

function boot() {
  initThemeSwitch();
  initFlashToast();
  initSpaceXCharts();

  if (window.HSStaticMethods && typeof window.HSStaticMethods.autoInit === 'function') {
    window.HSStaticMethods.autoInit();
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot);
} else {
  boot();
}

window.initSpaceXCharts = (yearData, siteData) => {
  if (yearData) initLaunchesPerYearChart('launches-per-year-chart', yearData);
  if (siteData) initLaunchSitesChart('launch-sites-chart', siteData);
};
