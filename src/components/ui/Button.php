<?php

/**
 * Reusable Preline UI Button Component
 *
 * @param array{
 *   text: string,
 *   type?: string,
 *   icon?: string,
 *   iconPosition?: 'start'|'end',
 *   class?: string,
 *   id?: string
 * } $props
 */
function renderButton(array $props): string
{
    $text = htmlspecialchars($props['text'], ENT_QUOTES, 'UTF-8');
    $type = htmlspecialchars($props['type'] ?? 'submit', ENT_QUOTES, 'UTF-8');
    $id = isset($props['id']) ? 'id="' . htmlspecialchars($props['id'], ENT_QUOTES, 'UTF-8') . '"' : '';
    $icon = $props['icon'] ?? null;
    $iconPosition = $props['iconPosition'] ?? 'end';
    $customClass = $props['class'] ?? '';

    $iconMarkup = '';
    if ($icon) {
        $iconEsc = htmlspecialchars($icon, ENT_QUOTES, 'UTF-8');
        $iconMarkup = "<iconify-icon icon=\"{$iconEsc}\" class=\"text-base shrink-0\"></iconify-icon>";
    }

    $content = $iconPosition === 'start'
        ? "{$iconMarkup}<span>{$text}</span>"
        : "<span>{$text}</span>{$iconMarkup}";

    return <<<HTML
    <button 
      type="{$type}" 
      {$id}
      class="w-full py-3 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-xl border border-transparent bg-blue-600 text-white hover:bg-blue-700 focus:outline-hidden focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:pointer-events-none dark:focus:ring-offset-neutral-900 transition-all cursor-pointer shadow-md hover:shadow-lg shadow-blue-500/20 {$customClass}"
    >
      {$content}
    </button>
HTML;
}
