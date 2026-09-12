import Toastify from 'toastify-js';

/**
 * Preline UI Toast Close Handler
 * Used by Preline toast close buttons with onclick="tostifyCustomClose(this)"
 */
if (typeof window !== 'undefined') {
  window.tostifyCustomClose = function(el) {
    const parent = el.closest('.toastify');
    const close = parent ? parent.querySelector('.toast-close') : null;
    if (close) {
      close.click();
    } else if (parent) {
      parent.remove();
    }
  };
}

/**
 * Show a Preline UI toast notification using Toastify-JS
 * Follows the official Preline UI toast theme & components:
 * https://preline.co/docs/components/toasts.html
 *
 * @param {Object} options
 * @param {'success'|'error'|'warning'|'info'} [options.type='info']
 * @param {'default'|'soft'|'solid'} [options.variant='default']
 * @param {string} [options.title='']
 * @param {string} [options.message='']
 * @param {number} [options.duration=3500]
 * @param {boolean} [options.dismissible=true]
 */
export function showToast({
  type = 'info',
  variant = 'default',
  title = '',
  message = '',
  duration = 3500,
  dismissible = true
} = {}) {
  // Preline UI Standard SVGs
  const icons = {
    success: `
      <svg class="shrink-0 size-4 text-teal-500 mt-0.5" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/>
      </svg>
    `,
    error: `
      <svg class="shrink-0 size-4 text-red-500 mt-0.5" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z"/>
      </svg>
    `,
    warning: `
      <svg class="shrink-0 size-4 text-yellow-500 mt-0.5" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
        <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/>
      </svg>
    `,
    info: `
      <svg class="shrink-0 size-4 text-blue-600 mt-0.5" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
        <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/>
      </svg>
    `
  };

  let iconMarkup = icons[type] || icons.info;

  // Preline UI Theme Variants: default | soft | solid
  let containerClasses = 'bg-white border border-gray-200 rounded-xl shadow-lg dark:bg-neutral-800 dark:border-neutral-700';
  let titleClasses = 'text-gray-800 font-semibold text-sm dark:text-white';
  let messageClasses = 'text-sm text-gray-700 dark:text-neutral-400';
  let closeBtnClasses = 'text-gray-800 opacity-50 hover:opacity-100 focus:outline-hidden focus:opacity-100 dark:text-neutral-200';

  if (variant === 'soft') {
    if (type === 'success') {
      containerClasses = 'bg-teal-100 border border-teal-200 rounded-xl shadow-lg dark:bg-teal-500/20 dark:border-teal-900';
      titleClasses = 'text-teal-900 font-semibold text-sm dark:text-teal-300';
      messageClasses = 'text-sm text-teal-800 dark:text-teal-400';
      closeBtnClasses = 'text-teal-800 opacity-50 hover:opacity-100 focus:outline-hidden focus:opacity-100 dark:text-teal-400';
      iconMarkup = `<svg class="shrink-0 size-4 text-teal-800 dark:text-teal-400 mt-0.5" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>`;
    } else if (type === 'error') {
      containerClasses = 'bg-red-100 border border-red-200 rounded-xl shadow-lg dark:bg-red-500/20 dark:border-red-900';
      titleClasses = 'text-red-900 font-semibold text-sm dark:text-red-300';
      messageClasses = 'text-sm text-red-800 dark:text-red-400';
      closeBtnClasses = 'text-red-800 opacity-50 hover:opacity-100 focus:outline-hidden focus:opacity-100 dark:text-red-400';
      iconMarkup = `<svg class="shrink-0 size-4 text-red-800 dark:text-red-400 mt-0.5" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z"/></svg>`;
    } else if (type === 'warning') {
      containerClasses = 'bg-yellow-100 border border-yellow-200 rounded-xl shadow-lg dark:bg-yellow-500/20 dark:border-yellow-900';
      titleClasses = 'text-yellow-900 font-semibold text-sm dark:text-yellow-300';
      messageClasses = 'text-sm text-yellow-800 dark:text-yellow-400';
      closeBtnClasses = 'text-yellow-800 opacity-50 hover:opacity-100 focus:outline-hidden focus:opacity-100 dark:text-yellow-400';
      iconMarkup = `<svg class="shrink-0 size-4 text-yellow-800 dark:text-yellow-400 mt-0.5" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/></svg>`;
    } else {
      containerClasses = 'bg-blue-100 border border-blue-200 rounded-xl shadow-lg dark:bg-blue-500/20 dark:border-blue-900';
      titleClasses = 'text-blue-900 font-semibold text-sm dark:text-blue-300';
      messageClasses = 'text-sm text-blue-800 dark:text-blue-400';
      closeBtnClasses = 'text-blue-800 opacity-50 hover:opacity-100 focus:outline-hidden focus:opacity-100 dark:text-blue-400';
      iconMarkup = `<svg class="shrink-0 size-4 text-blue-800 dark:text-blue-400 mt-0.5" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></svg>`;
    }
  } else if (variant === 'solid') {
    closeBtnClasses = 'text-white opacity-50 hover:opacity-100 focus:outline-hidden focus:opacity-100';
    titleClasses = 'text-white font-semibold text-sm';
    messageClasses = 'text-sm text-white/90';
    if (type === 'success') {
      containerClasses = 'bg-teal-500 rounded-xl shadow-lg text-white';
      iconMarkup = `<svg class="shrink-0 size-4 text-white mt-0.5" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z"/></svg>`;
    } else if (type === 'error') {
      containerClasses = 'bg-red-500 rounded-xl shadow-lg text-white';
      iconMarkup = `<svg class="shrink-0 size-4 text-white mt-0.5" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM5.354 4.646a.5.5 0 1 0-.708.708L7.293 8l-2.647 2.646a.5.5 0 0 0 .708.708L8 8.707l2.646 2.647a.5.5 0 0 0 .708-.708L8.707 8l2.647-2.646a.5.5 0 0 0-.708-.708L8 7.293 5.354 4.646z"/></svg>`;
    } else if (type === 'warning') {
      containerClasses = 'bg-yellow-500 rounded-xl shadow-lg text-white';
      iconMarkup = `<svg class="shrink-0 size-4 text-white mt-0.5" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zM8 4a.905.905 0 0 0-.9.995l.35 3.507a.552.552 0 0 0 1.1 0l.35-3.507A.905.905 0 0 0 8 4zm.002 6a1 1 0 1 0 0 2 1 1 0 0 0 0-2z"/></svg>`;
    } else {
      containerClasses = 'bg-blue-600 rounded-xl shadow-lg text-white';
      iconMarkup = `<svg class="shrink-0 size-4 text-white mt-0.5" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z"/></svg>`;
    }
  }

  const closeMarkup = dismissible ? `
    <div class="ms-auto ps-3">
      <button onclick="tostifyCustomClose(this)" type="button" class="inline-flex shrink-0 justify-center items-center size-5 rounded-lg ${closeBtnClasses} cursor-pointer" aria-label="Close">
        <span class="sr-only">Close</span>
        <svg class="shrink-0 size-4" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 6 6 18"/>
          <path d="m6 6 12 12"/>
        </svg>
      </button>
    </div>
  ` : '';

  const contentMarkup = title && message
    ? `
      <div class="ms-3 grow">
        <h3 class="${titleClasses}">${title}</h3>
        <p class="${messageClasses} mt-1">${message}</p>
      </div>
    `
    : title
    ? `
      <div class="ms-3 grow">
        <h3 class="${titleClasses}">${title}</h3>
      </div>
    `
    : `
      <div class="ms-3 grow">
        <p class="${messageClasses}">${message}</p>
      </div>
    `;

  const toastMarkup = `
    <div class="max-w-xs w-full ${containerClasses}" role="alert" tabindex="-1">
      <div class="flex p-4">
        <div class="shrink-0">
          ${iconMarkup}
        </div>
        ${contentMarkup}
        ${closeMarkup}
      </div>
    </div>
  `;

  Toastify({
    text: toastMarkup,
    className: "hs-toastify-on:opacity-100 opacity-0 fixed bottom-4 inset-x-4 sm:inset-x-auto sm:end-6 sm:bottom-6 z-90 transition-all duration-300 w-auto max-w-xs mx-auto sm:mx-0 [&>.toast-close]:hidden",
    duration: duration,
    close: true,
    escapeMarkup: false,
    gravity: "bottom",
    position: "right",
    style: {
      background: 'transparent',
      boxShadow: 'none',
      padding: '0'
    }
  }).showToast();
}

// Make globally accessible
window.showToast = showToast;

/**
 * Auto-trigger toast if server rendered flash toast data in DOM
 */
export function initFlashToast() {
  const el = document.getElementById('flash-toast-data');
  if (!el) return;

  try {
    const data = JSON.parse(el.getAttribute('data-toast') || '{}');
    if (data && (data.title || data.message)) {
      setTimeout(() => {
        showToast(data);
      }, 100);
    }
  } catch (err) {
    console.error('Failed to parse flash toast:', err);
  }
}
