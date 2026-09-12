<?php

/**
 * Reusable Theme Switch Component (Light, Dark, Auto/System)
 */
function renderThemeSwitch(): string
{
    return <<<HTML
    <div class="inline-flex items-center p-1 bg-gray-100 rounded-xl dark:bg-neutral-800 border border-gray-200 dark:border-neutral-700">
      <button 
        type="button" 
        data-hs-theme-click-value="default"
        onclick="window.__setTheme && window.__setTheme('default')"
        class="hs-theme-switch-btn py-1.5 px-2.5 inline-flex items-center gap-x-1.5 text-xs font-medium rounded-lg text-gray-500 hover:text-gray-900 focus:outline-hidden dark:text-neutral-400 dark:hover:text-white transition-all cursor-pointer"
        title="Light Mode"
        aria-label="Light Mode"
      >
        <iconify-icon icon="lucide:sun" class="text-sm shrink-0"></iconify-icon>
        <span class="hidden sm:inline">Light</span>
      </button>
      <button 
        type="button" 
        data-hs-theme-click-value="dark"
        onclick="window.__setTheme && window.__setTheme('dark')"
        class="hs-theme-switch-btn py-1.5 px-2.5 inline-flex items-center gap-x-1.5 text-xs font-medium rounded-lg text-gray-500 hover:text-gray-900 focus:outline-hidden dark:text-neutral-400 dark:hover:text-white transition-all cursor-pointer"
        title="Dark Mode"
        aria-label="Dark Mode"
      >
        <iconify-icon icon="lucide:moon" class="text-sm shrink-0"></iconify-icon>
        <span class="hidden sm:inline">Dark</span>
      </button>
      <button 
        type="button" 
        data-hs-theme-click-value="auto"
        onclick="window.__setTheme && window.__setTheme('auto')"
        class="hs-theme-switch-btn py-1.5 px-2.5 inline-flex items-center gap-x-1.5 text-xs font-medium rounded-lg text-gray-500 hover:text-gray-900 focus:outline-hidden dark:text-neutral-400 dark:hover:text-white transition-all cursor-pointer"
        title="System Preference"
        aria-label="System Preference"
      >
        <iconify-icon icon="lucide:laptop" class="text-sm shrink-0"></iconify-icon>
        <span class="hidden sm:inline">Auto</span>
      </button>
    </div>
HTML;
}
