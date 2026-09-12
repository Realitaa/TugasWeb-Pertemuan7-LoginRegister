<?php

/**
 * Render reusable Hero Image component for Auth pages
 *
 * @param string $appName Application name from config
 */
function renderAuthImage(string $appName): string
{
    $appNameEsc = htmlspecialchars($appName, ENT_QUOTES, 'UTF-8');

    return <<<HTML
      <div class="relative hidden lg:flex flex-col justify-between w-full h-full min-h-screen p-8 lg:p-12 overflow-hidden select-none bg-neutral-950">
        <img
          src="/public/spacex-Ptd-iTdrCJM-unsplash.webp"
          alt="SpaceX Rocket Launch"
          class="absolute inset-0 w-full h-full object-cover hero-crop-position transition-transform duration-1000 ease-out hover:scale-105"
          style="object-position: 25% 50%;"
        />

        <div class="absolute inset-0 bg-linear-to-b from-black/80 via-black/20 to-transparent pointer-events-none"></div>
        <div class="absolute inset-0 bg-linear-to-t from-black/90 via-black/40 to-transparent pointer-events-none"></div>
        <div class="absolute inset-0 bg-linear-to-r from-black/30 via-transparent to-black/10 pointer-events-none"></div>
        <div class="relative z-10">
          <a href="index.php" class="inline-flex items-center gap-x-2.5 text-white group focus:outline-hidden">
            <div class="size-9 rounded-xl bg-blue-600/90 backdrop-blur-md flex items-center justify-center shadow-lg shadow-blue-500/30 ring-1 ring-white/20 group-hover:bg-blue-500 transition-all">
              <iconify-icon icon="lucide:rocket" class="text-lg text-white"></iconify-icon>
            </div>
            <div class="flex flex-col">
              <span class="text-lg font-bold tracking-tight text-white drop-shadow-md">{$appNameEsc}</span>
              <span class="text-[10px] tracking-wider uppercase text-blue-300 font-medium drop-shadow-sm">Orbital Telemetry</span>
            </div>
          </a>
        </div>

        <div class="relative z-10 max-w-md mt-auto space-y-1.5 pb-2">
          <p class="text-xs sm:text-sm font-normal leading-relaxed text-white/90 drop-shadow-sm">
            &ldquo;Any civilization that loses faith in the future will die.<br />
            Exploring the stars is an exciting future that you can believe in.&rdquo;
          </p>
          <div class="flex items-center gap-2 text-[11px] text-white/60">
            <span class="font-medium text-white/80">&mdash; Elon Musk</span>
            <span>&bull;</span>
            <a 
              href="https://x.com/elonmusk/status/2081154036338692413" 
              target="_blank" 
              rel="noopener noreferrer"
              class="inline-flex items-center gap-1 text-blue-300 hover:text-blue-200 underline decoration-blue-400/40 hover:decoration-blue-300 transition-colors"
            >
              <iconify-icon icon="ri:twitter-x-fill" class="text-[10px]"></iconify-icon>
              <span>Source on X</span>
            </a>
          </div>
        </div>
      </div>
HTML;
}
