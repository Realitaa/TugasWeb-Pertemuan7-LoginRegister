<?php

/**
 * Reusable Preline UI Form Input Component
 *
 * @param array{
 *   id?: string,
 *   name: string,
 *   label: string,
 *   type?: string,
 *   value?: string,
 *   placeholder?: string,
 *   icon?: string,
 *   required?: bool,
 *   error?: string|null,
 *   togglePassword?: bool,
 *   autocomplete?: string
 * } $props
 */
function renderInput(array $props): string
{
    $name = htmlspecialchars($props['name'], ENT_QUOTES, 'UTF-8');
    $id = htmlspecialchars($props['id'] ?? ('input_' . $props['name']), ENT_QUOTES, 'UTF-8');
    $label = htmlspecialchars($props['label'], ENT_QUOTES, 'UTF-8');
    $type = htmlspecialchars($props['type'] ?? 'text', ENT_QUOTES, 'UTF-8');
    $value = htmlspecialchars($props['value'] ?? '', ENT_QUOTES, 'UTF-8');
    $placeholder = htmlspecialchars($props['placeholder'] ?? '', ENT_QUOTES, 'UTF-8');
    $icon = $props['icon'] ?? null;
    $required = !empty($props['required']) ? 'required' : '';
    $error = $props['error'] ?? null;
    $togglePassword = !empty($props['togglePassword']);
    $autocomplete = isset($props['autocomplete']) ? 'autocomplete="' . htmlspecialchars($props['autocomplete'], ENT_QUOTES, 'UTF-8') . '"' : '';

    $borderClass = $error
        ? 'border-red-500 focus:border-red-500 focus:ring-red-500 dark:border-red-600'
        : 'border-gray-200 focus:border-blue-600 focus:ring-blue-600 dark:border-neutral-700 dark:focus:border-blue-500 dark:focus:ring-blue-500';

    $paddingStart = $icon ? 'ps-10' : 'ps-4';
    $paddingEnd = $togglePassword ? 'pe-10' : 'pe-4';

    $iconMarkup = '';
    if ($icon) {
        $iconEsc = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');
        $iconMarkup = <<<HTML
        <div class="absolute inset-y-0 inset-s-0 flex items-center pointer-events-none ps-3.5 text-gray-400 dark:text-neutral-500">
          <iconify-icon icon="{$iconEsc}" class="text-base"></iconify-icon>
        </div>
HTML;
    }

    $toggleMarkup = '';
    if ($togglePassword) {
        $toggleMarkup = <<<HTML
        <button 
          type="button" 
          data-hs-toggle-password='{
            "target": "#{$id}"
          }' 
          class="absolute inset-y-0 inset-e-0 flex items-center z-20 px-3 cursor-pointer text-gray-400 dark:text-neutral-500 rounded-e-md focus:outline-hidden focus:text-blue-600 dark:focus:text-blue-500"
          aria-label="Toggle password visibility"
        >
          <iconify-icon icon="lucide:eye-off" class="text-base shrink-0 hs-password-active:hidden"></iconify-icon>
          <iconify-icon icon="lucide:eye" class="text-base shrink-0 hidden hs-password-active:block"></iconify-icon>
        </button>
HTML;
    }

    $errorMarkup = '';
    if ($error) {
        $errorEsc = htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
        $errorMarkup = <<<HTML
        <p class="text-xs text-red-600 dark:text-red-400 mt-1.5">{$errorEsc}</p>
HTML;
    }

    return <<<HTML
    <div class="w-full">
      <div class="flex justify-between items-center mb-1.5">
        <label for="{$id}" class="block text-sm font-medium text-gray-800 dark:text-neutral-200">{$label}</label>
      </div>
      <div class="relative">
        {$iconMarkup}
        <input 
          type="{$type}" 
          id="{$id}" 
          name="{$name}" 
          value="{$value}" 
          placeholder="{$placeholder}" 
          {$required}
          {$autocomplete}
          class="py-2.5 sm:py-3 {$paddingStart} {$paddingEnd} block w-full bg-white dark:bg-neutral-800 border-gray-200 dark:border-neutral-700 rounded-xl text-sm text-gray-800 dark:text-neutral-200 placeholder:text-gray-400 dark:placeholder:text-neutral-500 focus:outline-hidden focus:ring-2 disabled:opacity-50 disabled:pointer-events-none {$borderClass} transition-colors"
        />
        {$toggleMarkup}
      </div>
      {$errorMarkup}
    </div>
HTML;
}
