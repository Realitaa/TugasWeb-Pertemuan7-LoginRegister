<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Realitaa\PhpVite\Auth\AuthService;
use Realitaa\PhpVite\SpaceX\SpaceXService;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$auth = new AuthService();
$user = $auth->requireAuth('index.php');

$userName = $user['name'] ?? $user['username'] ?? 'Commander';
$userEmail = $user['email'] ?? '';
$gravatarUrl = $auth->gravatar($userEmail);

// Handle Profile Update
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $newName = (string)($_POST['name'] ?? '');
    $newEmail = (string)($_POST['email'] ?? '');
    $newPassword = (string)($_POST['password'] ?? '');

    $userId = (string)($user['id'] ?? $userEmail);
    $result = $auth->updateProfile($userId, $newName, $newEmail, $newPassword);

    if ($result['success']) {
        $auth->setFlashToast('success', 'Pengaturan Tersimpan', 'Data profil berhasil diperbarui.');
    } else {
        $auth->setFlashToast(
            'error',
            'Gagal Memperbarui Profil',
            reset($result['errors']) ?: 'Gagal memperbarui data profil.'
        );
    }

    header('Location: dashboard.php');
    exit;
}

$toast = $auth->getFlashToast();

require_once __DIR__ . '/src/components/ThemeSwitch.php';

$spaceXService = new SpaceXService();
$forceRefresh = isset($_GET['refresh']);
$stats = $spaceXService->getStats($forceRefresh);
if (!empty($stats['launches_per_year']['by_year'])) {
    usort($stats['launches_per_year']['by_year'], function ($a, $b) {
        return (int)$a['year'] <=> (int)$b['year'];
    });
}

$lc = $stats['launch_count'];
$lpy = $stats['launches_per_year'];
$launchSites = $stats['launch_sites'];
$landingSites = $stats['landing_sites'];
$turnarounds = $stats['turnarounds'];
$boosterReuse = $stats['booster_reuse'];
$capsuleReuse = $stats['capsule_reuse'];
$dragon = $stats['dragon'];
$payloads = $stats['payloads'];
$mars = $stats['mars'];
$moon = $stats['moon'];

// Max landing site total for progress bars
$maxLandingTotal = 1;
foreach ($landingSites as $ls) {
    if (!empty($ls['total']) && $ls['total'] > $maxLandingTotal) {
        $maxLandingTotal = (int)$ls['total'];
    }
}
?>
<!doctype html>
<html lang="en" class="h-full">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
  <title>SpaceXStat - Telemetry & Mission Analytics</title>

  <!-- Theme Controller & Anti-Flicker Script -->
  <script>
    (function () {
      window.__setTheme = function(theme) {
        const html = document.documentElement;
        const isDark = theme === 'dark' || (theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        if (isDark) {
          html.classList.add('dark');
          html.classList.remove('light');
        } else {
          html.classList.remove('dark');
          html.classList.add('light');
        }
        localStorage.setItem('hs_theme', theme);

        document.querySelectorAll('[data-hs-theme-click-value]').forEach(function(btn) {
          const val = btn.getAttribute('data-hs-theme-click-value');
          if (val === theme) {
            btn.classList.add('bg-white', 'text-gray-800', 'shadow-xs', 'dark:bg-neutral-700', 'dark:text-white');
            btn.classList.remove('text-gray-500', 'dark:text-neutral-400');
          } else {
            btn.classList.remove('bg-white', 'text-gray-800', 'shadow-xs', 'dark:bg-neutral-700', 'dark:text-white');
            btn.classList.add('text-gray-500', 'dark:text-neutral-400');
          }
        });
      };

      const initialTheme = localStorage.getItem('hs_theme') || 'auto';
      const isDark = initialTheme === 'dark' || (initialTheme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);
      if (isDark) {
        document.documentElement.classList.add('dark');
      } else {
        document.documentElement.classList.remove('dark');
      }

      document.addEventListener('DOMContentLoaded', function() {
        window.__setTheme(initialTheme);
      });
    })();
  </script>

  <?= vite()->tags('src/main.js') ?>
</head>
<body class="bg-gray-50 text-gray-800 dark:bg-neutral-900 dark:text-neutral-200 antialiased min-h-screen flex flex-col font-sans">
  
  <?php if (!empty($toast)): ?>
    <div id="flash-toast-data" data-toast="<?= htmlspecialchars(json_encode($toast), ENT_QUOTES, 'UTF-8') ?>" class="hidden"></div>
  <?php endif; ?>

  <div id="spacex-dashboard" class="flex-grow">
    
    <!-- NAVBAR -->
    <header class="sticky top-0 z-40 w-full bg-white/95 backdrop-blur-md border-b border-gray-200 shadow-2xs dark:bg-neutral-900/95 dark:border-neutral-800">
      <nav class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-3 flex flex-wrap sm:flex-nowrap items-center justify-between" aria-label="Global">
        
        <!-- Left: Brand Logo -->
        <a class="flex items-center gap-x-2.5 font-bold text-lg sm:text-xl text-gray-900 dark:text-white tracking-tight hover:opacity-90 transition" href="dashboard.php">
          <span class="flex items-center justify-center size-9 rounded-xl bg-gradient-to-tr from-sky-600 to-indigo-600 text-white shadow-sm">
            <iconify-icon icon="lucide:rocket" class="size-5"></iconify-icon>
          </span>
          <span>SpaceXStat</span>
        </a>

        <!-- Desktop Right: User Dropdown (Avatar + Name + Caret trigger) -->
        <div class="hidden sm:inline-flex items-center">
          <div class="hs-dropdown relative inline-flex">
            <button 
              id="hs-dropdown-user-menu" 
              type="button" 
              class="hs-dropdown-toggle inline-flex items-center gap-x-2.5 py-1 ps-1.5 pe-3.5 rounded-full text-sm font-medium bg-gray-50 text-gray-800 border border-gray-200 shadow-2xs hover:bg-gray-100 focus:outline-hidden dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-700 transition cursor-pointer" 
              aria-haspopup="menu" 
              aria-expanded="false" 
              aria-label="User menu"
            >
              <img 
                class="size-7 rounded-full object-cover border border-gray-200 dark:border-neutral-700 shrink-0" 
                src="<?= htmlspecialchars($gravatarUrl, ENT_QUOTES, 'UTF-8') ?>" 
                onerror="this.onerror=null;this.src='/public/avatar.png';" 
                alt="<?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>"
              >
              <span class="text-gray-700 font-semibold text-xs tracking-tight truncate max-w-[9rem] dark:text-neutral-200">
                <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>
              </span>
              <iconify-icon icon="lucide:chevron-down" class="hs-dropdown-open:rotate-180 size-3.5 text-gray-500 transition-transform duration-200 dark:text-neutral-400"></iconify-icon>
            </button>

            <!-- Dropdown Content -->
            <div 
              class="hs-dropdown-menu transition-[opacity,margin] duration hs-dropdown-open:opacity-100 opacity-0 hidden min-w-56 bg-white shadow-lg rounded-xl p-1.5 mt-2 border border-gray-200 dark:bg-neutral-800 dark:border-neutral-700 divide-y divide-gray-100 dark:divide-neutral-700 z-50" 
              role="menu" 
              aria-orientation="vertical" 
              aria-labelledby="hs-dropdown-user-menu"
            >
              <!-- 1. Signed in User Header -->
              <div class="py-2.5 px-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-neutral-500">Signed in as</p>
                <p class="text-sm font-semibold text-gray-900 truncate dark:text-white mt-0.5"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></p>
                <p class="text-xs text-gray-500 truncate dark:text-neutral-400"><?= htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') ?></p>
              </div>

              <!-- 2. Settings ("Pengaturan") -->
              <div class="py-1">
                <button 
                  type="button" 
                  data-hs-overlay="#settings-modal" 
                  class="w-full flex items-center gap-x-2.5 py-2 px-3 rounded-lg text-xs font-medium text-gray-700 hover:bg-gray-100 dark:text-neutral-300 dark:hover:bg-neutral-700/60 transition text-start cursor-pointer"
                >
                  <iconify-icon icon="lucide:settings" class="size-4 text-gray-500 dark:text-neutral-400"></iconify-icon>
                  <span>Pengaturan</span>
                </button>
              </div>

              <!-- 3. Logout -->
              <div class="pt-1">
                <a 
                  href="logout.php" 
                  class="flex items-center gap-x-2.5 py-2 px-3 rounded-lg text-xs font-semibold text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-950/30 transition"
                >
                  <iconify-icon icon="lucide:log-out" class="size-4"></iconify-icon>
                  <span>Logout</span>
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Mobile: Hamburger Toggle Button -->
        <div class="sm:hidden">
          <button 
            type="button" 
            class="hs-collapse-toggle p-2 inline-flex justify-center items-center gap-2 rounded-lg border border-gray-200 bg-white text-gray-800 shadow-2xs hover:bg-gray-50 focus:outline-hidden dark:bg-neutral-800 dark:border-neutral-700 dark:text-white dark:hover:bg-neutral-700 cursor-pointer" 
            id="hs-navbar-collapse" 
            data-hs-collapse="#hs-navbar-collapse-menu" 
            aria-controls="hs-navbar-collapse-menu" 
            aria-label="Toggle navigation"
          >
            <iconify-icon icon="lucide:menu" class="hs-collapse-open:hidden size-5"></iconify-icon>
            <iconify-icon icon="lucide:x" class="hs-collapse-open:block hidden size-5"></iconify-icon>
          </button>
        </div>

        <!-- Mobile: Hamburger Menu Collapse Container (Consists of 3 things: Pengaturan, User avatar + name & Logout with justify-between) -->
        <div 
          id="hs-navbar-collapse-menu" 
          class="hs-collapse hidden overflow-hidden transition-all duration-300 basis-full grow sm:hidden" 
          aria-labelledby="hs-navbar-collapse"
        >
          <div class="pt-3 pb-2 space-y-2 border-t border-gray-200 dark:border-neutral-800 mt-3">
            
            <!-- 1. Pengaturan -->
            <button 
              type="button" 
              data-hs-overlay="#settings-modal" 
              class="w-full flex items-center gap-x-2.5 py-2 px-3 rounded-xl text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-neutral-300 dark:hover:bg-neutral-800 transition cursor-pointer text-start"
            >
              <iconify-icon icon="lucide:settings" class="size-4.5 text-gray-500 dark:text-neutral-400"></iconify-icon>
              <span>Pengaturan</span>
            </button>

            <!-- 2 & 3. User Avatar + Name & Logout with justify-between -->
            <div class="flex items-center justify-between pt-2 px-2 border-t border-gray-100 dark:border-neutral-800">
              <div class="flex items-center gap-x-3 min-w-0">
                <img 
                  class="size-8 rounded-full object-cover border border-gray-200 dark:border-neutral-700 shrink-0" 
                  src="<?= htmlspecialchars($gravatarUrl, ENT_QUOTES, 'UTF-8') ?>" 
                  onerror="this.onerror=null;this.src='/public/avatar.png';" 
                  alt="<?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>"
                >
                <div class="truncate">
                  <span class="block text-sm font-semibold text-gray-900 dark:text-neutral-200 truncate"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span>
                  <span class="block text-xs text-gray-500 dark:text-neutral-400 truncate"><?= htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') ?></span>
                </div>
              </div>
              <a 
                href="logout.php" 
                class="inline-flex items-center gap-x-1.5 py-1.5 px-3 rounded-lg text-xs font-semibold bg-red-50 text-red-600 hover:bg-red-100 dark:bg-red-950/50 dark:text-red-400 dark:hover:bg-red-900/50 transition shrink-0"
              >
                <iconify-icon icon="lucide:log-out" class="size-3.5"></iconify-icon>
                <span>Logout</span>
              </a>
            </div>

          </div>
        </div>

      </nav>
    </header>

    <!-- SETTINGS MODAL ("PENGATURAN") -->
    <div 
      id="settings-modal" 
      class="hs-overlay hidden size-full fixed top-0 inset-s-0 z-80 overflow-x-hidden overflow-y-auto pointer-events-none" 
      role="dialog" 
      tabindex="-1" 
      aria-labelledby="settings-modal-label"
    >
      <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-lg sm:w-full m-3 sm:mx-auto min-h-[calc(100%-3.5rem)] flex items-center">
        <div class="w-full flex flex-col bg-white border border-gray-200 shadow-xl rounded-2xl pointer-events-auto dark:bg-neutral-800 dark:border-neutral-700">
          
          <!-- Modal Header -->
          <div class="flex justify-between items-center py-3.5 px-5 border-b border-gray-200 dark:border-neutral-700">
            <div class="flex items-center gap-x-2.5">
              <span class="flex items-center justify-center size-8 rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-950/50 dark:text-sky-400">
                <iconify-icon icon="lucide:settings" class="size-4.5"></iconify-icon>
              </span>
              <h3 id="settings-modal-label" class="font-bold text-base text-gray-900 dark:text-white">
                Pengaturan
              </h3>
            </div>
            <button 
              type="button" 
              class="size-8 inline-flex justify-center items-center gap-x-2 rounded-full border border-transparent bg-gray-100 text-gray-800 hover:bg-gray-200 focus:outline-hidden dark:bg-neutral-700 dark:hover:bg-neutral-600 dark:text-neutral-300 cursor-pointer" 
              aria-label="Close" 
              data-hs-overlay="#settings-modal"
            >
              <span class="sr-only">Close</span>
              <iconify-icon icon="lucide:x" class="size-4"></iconify-icon>
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-5 space-y-6">
            
            <!-- 1. Edit Profile Section -->
            <div>
              <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-1">Edit Profil</h4>
              <p class="text-xs text-gray-500 dark:text-neutral-400 mb-3">Perbarui nama tampilan, email, atau kata sandi akun Anda.</p>
              
              <form method="POST" action="dashboard.php" class="space-y-3">
                <input type="hidden" name="action" value="update_profile">
                <div>
                  <label for="settings-username" class="block text-xs font-semibold text-gray-700 dark:text-neutral-300 mb-1">Nama Tampilan</label>
                  <input 
                    type="text" 
                    id="settings-username" 
                    name="name" 
                    value="<?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>" 
                    required 
                    placeholder="Masukkan nama Anda" 
                    class="py-2.5 px-3.5 block w-full border-gray-200 rounded-xl text-sm focus:border-sky-500 focus:ring-sky-500 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-200 dark:placeholder-neutral-500 shadow-2xs"
                  >
                </div>
                <div>
                  <label for="settings-email" class="block text-xs font-semibold text-gray-700 dark:text-neutral-300 mb-1">Email</label>
                  <input 
                    type="email" 
                    id="settings-email" 
                    name="email" 
                    value="<?= htmlspecialchars($userEmail, ENT_QUOTES, 'UTF-8') ?>" 
                    required 
                    placeholder="nama@domain.com" 
                    class="py-2.5 px-3.5 block w-full border-gray-200 rounded-xl text-sm focus:border-sky-500 focus:ring-sky-500 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-200 dark:placeholder-neutral-500 shadow-2xs"
                  >
                </div>
                <div>
                  <label for="settings-password" class="block text-xs font-semibold text-gray-700 dark:text-neutral-300 mb-1">Kata Sandi Baru</label>
                  <input 
                    type="password" 
                    id="settings-password" 
                    name="password" 
                    placeholder="Kosongkan jika tidak ingin mengubah password" 
                    class="py-2.5 px-3.5 block w-full border-gray-200 rounded-xl text-sm focus:border-sky-500 focus:ring-sky-500 dark:bg-neutral-900 dark:border-neutral-700 dark:text-neutral-200 dark:placeholder-neutral-500 shadow-2xs"
                  >
                  <p class="text-xs text-gray-400 dark:text-neutral-500 mt-1">Kosongkan jika tidak ingin mengubah password.</p>
                </div>
                <div class="flex justify-end pt-1">
                  <button 
                    type="submit" 
                    class="py-2 px-4 inline-flex items-center gap-x-2 text-xs font-semibold rounded-lg border border-transparent bg-sky-600 text-white hover:bg-sky-700 focus:outline-hidden focus:bg-sky-700 transition cursor-pointer shadow-2xs"
                  >
                    <iconify-icon icon="lucide:check" class="size-3.5"></iconify-icon>
                    Simpan Perubahan
                  </button>
                </div>
              </form>
            </div>

            <!-- Divider -->
            <div class="border-t border-gray-200 dark:border-neutral-700 pt-5">
              <!-- 2. Theme Changing Section -->
              <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-1">Pilihan Tema</h4>
              <p class="text-xs text-gray-500 dark:text-neutral-400 mb-3">Sesuaikan tema tampilan antarmuka sesuai preferensi Anda.</p>
              <div>
                <?= renderThemeSwitch() ?>
              </div>
            </div>

          </div>

          <!-- Modal Footer -->
          <div class="flex justify-end items-center py-3 px-5 border-t border-gray-200 dark:border-neutral-700 bg-gray-50/70 dark:bg-neutral-900/50 rounded-b-2xl">
            <button 
              type="button" 
              class="py-2 px-3.5 inline-flex items-center gap-x-2 text-xs font-medium rounded-lg border border-gray-200 bg-white text-gray-700 shadow-2xs hover:bg-gray-50 focus:outline-hidden dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-700 cursor-pointer" 
              data-hs-overlay="#settings-modal"
            >
              Tutup
            </button>
          </div>

        </div>
      </div>
    </div>

    <!-- MAIN DASHBOARD CONTENT -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-8">
      
      <!-- HERO / JUMBOTRON -->
      <section class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white p-6 sm:p-8 lg:p-10 shadow-md border border-slate-800">
        <div class="absolute -right-16 -top-16 size-72 rounded-full bg-sky-500/10 blur-3xl pointer-events-none"></div>
        <div class="absolute right-1/3 -bottom-20 size-80 rounded-full bg-indigo-500/10 blur-3xl pointer-events-none"></div>

        <div class="relative z-10 max-w-3xl space-y-3">
          <div class="inline-flex items-center gap-x-2 py-1 px-3 rounded-full text-xs font-medium bg-white/10 text-sky-300 border border-white/10">
            <iconify-icon icon="lucide:orbit" class="size-3.5"></iconify-icon>
            SpaceX Telemetry
          </div>
          <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight text-white leading-tight">
            Selamat Datang, <?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?>
          </h1>
          <p class="text-sm sm:text-base text-slate-300 leading-relaxed max-w-2xl">
            Eksplorasi data aktivitas peluncuran, reliabilitas kendaraan, statistik pendaratan booster, dan misi eksplorasi luar angkasa SpaceX secara realtime.
          </p>

          <!-- Source & Sync Bar -->
          <div class="pt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-400 border-t border-slate-800/80">
            <span class="inline-flex items-center gap-1.5">
              <iconify-icon icon="lucide:database" class="size-3.5 text-sky-400"></iconify-icon>
              Sumber: <a href="https://spacexnow.com/stats" target="_blank" rel="noopener noreferrer" class="text-sky-300 hover:text-sky-200 underline underline-offset-2 transition font-medium">SpaceXNow /stats</a>
            </span>
            <?php if (!empty($stats['last_updated'])): ?>
              <span class="text-slate-600">•</span>
              <span class="text-slate-400">
                Sinkronisasi terakhir: <time datetime="<?= htmlspecialchars((string)$stats['last_updated'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$stats['last_updated'], ENT_QUOTES, 'UTF-8') ?></time>
              </span>
            <?php endif; ?>
            <a href="?refresh=1" class="inline-flex items-center gap-1.5 text-xs font-semibold px-2.5 py-1 rounded-md bg-white/10 hover:bg-white/20 text-slate-200 transition" title="Sinkronkan data terbaru">
              <iconify-icon icon="lucide:refresh-cw" class="size-3 text-sky-300"></iconify-icon>
              Sinkronkan Data
            </a>
          </div>
        </div>
      </section>

      <!-- 1. LAUNCH COUNT OVERVIEW CARDS -->
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg sm:text-xl font-bold tracking-tight text-gray-900 dark:text-white flex items-center gap-2">
            <iconify-icon icon="lucide:rocket" class="size-5 text-sky-600 dark:text-sky-400"></iconify-icon>
            Ringkasan Peluncuran
          </h2>
          <span class="text-xs text-gray-500 dark:text-neutral-400">Total misi yang pernah diluncurkan</span>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
          
          <!-- Card: Total Launches -->
          <div class="p-5 rounded-2xl bg-white border border-gray-200/80 shadow-2xs dark:bg-neutral-800 dark:border-neutral-700/80 flex flex-col justify-between">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">Total Launches</span>
              <span class="size-8 rounded-lg bg-sky-50 text-sky-600 dark:bg-sky-950/50 dark:text-sky-400 flex items-center justify-center">
                <iconify-icon icon="lucide:rocket" class="size-4"></iconify-icon>
              </span>
            </div>
            <div class="mt-4">
              <p class="text-2xl sm:text-3xl font-extrabold tracking-tight text-gray-900 dark:text-white"><?= htmlspecialchars((string)($lc['total_launches'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
              <p class="text-xs text-gray-500 dark:text-neutral-400 mt-1">Misi keseluruhan</p>
            </div>
          </div>

          <!-- Card: Successful Launches -->
          <div class="p-5 rounded-2xl bg-white border border-gray-200/80 shadow-2xs dark:bg-neutral-800 dark:border-neutral-700/80 flex flex-col justify-between">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold uppercase tracking-wider text-teal-600 dark:text-teal-400">Successful</span>
              <span class="size-8 rounded-lg bg-teal-50 text-teal-600 dark:bg-teal-950/50 dark:text-teal-400 flex items-center justify-center">
                <iconify-icon icon="lucide:check-circle-2" class="size-4"></iconify-icon>
              </span>
            </div>
            <div class="mt-4">
              <p class="text-2xl sm:text-3xl font-extrabold tracking-tight text-teal-600 dark:text-teal-400"><?= htmlspecialchars((string)($lc['successful_launches'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
              <p class="text-xs text-gray-500 dark:text-neutral-400 mt-1">Misi sukses tercatat</p>
            </div>
          </div>

          <!-- Card: Success Rate -->
          <div class="p-5 rounded-2xl bg-white border border-gray-200/80 shadow-2xs dark:bg-neutral-800 dark:border-neutral-700/80 flex flex-col justify-between">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Success Rate</span>
              <span class="size-8 rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-950/50 dark:text-indigo-400 flex items-center justify-center">
                <iconify-icon icon="lucide:percent" class="size-4"></iconify-icon>
              </span>
            </div>
            <div class="mt-4">
              <p class="text-2xl sm:text-3xl font-extrabold tracking-tight text-indigo-600 dark:text-indigo-400"><?= htmlspecialchars((string)($lc['success_rate'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
              <p class="text-xs text-gray-500 dark:text-neutral-400 mt-1">Tingkat keberhasilan</p>
            </div>
          </div>

          <!-- Card: Successive Streak -->
          <div class="p-5 rounded-2xl bg-white border border-gray-200/80 shadow-2xs dark:bg-neutral-800 dark:border-neutral-700/80 flex flex-col justify-between">
            <div class="flex items-center justify-between">
              <span class="text-xs font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">Current Streak</span>
              <span class="size-8 rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400 flex items-center justify-center">
                <iconify-icon icon="lucide:flame" class="size-4"></iconify-icon>
              </span>
            </div>
            <div class="mt-4">
              <p class="text-2xl sm:text-3xl font-extrabold tracking-tight text-amber-600 dark:text-amber-400"><?= htmlspecialchars((string)($lc['successive'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
              <p class="text-xs text-gray-500 dark:text-neutral-400 mt-1">Rekor terbaik: <?= htmlspecialchars((string)($lc['most_successive'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
            </div>
          </div>

        </div>
      </section>

      <!-- 2. VEHICLES PERFORMANCE -->
      <?php if (!empty($lc['vehicles'])): ?>
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg sm:text-xl font-bold tracking-tight text-gray-900 dark:text-white flex items-center gap-2">
            <iconify-icon icon="lucide:shield" class="size-5 text-indigo-600 dark:text-indigo-400"></iconify-icon>
            Performa Kendaraan Peluncur
          </h2>
          <span class="text-xs text-gray-500 dark:text-neutral-400">Statistik per tipe roket</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          <?php foreach ($lc['vehicles'] as $veh): ?>
            <div class="p-5 rounded-2xl bg-white border border-gray-200/80 shadow-2xs dark:bg-neutral-800 dark:border-neutral-700/80">
              <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($veh['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                <span class="text-xs font-semibold py-0.5 px-2 rounded-full bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300 border border-sky-100 dark:border-sky-900/50">
                  <?= htmlspecialchars($veh['rate'], ENT_QUOTES, 'UTF-8') ?>
                </span>
              </div>
              <p class="text-xl font-extrabold text-gray-900 dark:text-white">
                <?= (int)$veh['success'] ?> <span class="text-xs font-normal text-gray-400 dark:text-neutral-500">/ <?= (int)$veh['total'] ?> sukses</span>
              </p>
              <!-- Mini Progress Bar -->
              <?php 
                $pct = $veh['total'] > 0 ? round(($veh['success'] / $veh['total']) * 100, 1) : 0;
              ?>
              <div class="w-full bg-gray-100 rounded-full h-1.5 mt-3 dark:bg-neutral-700 overflow-hidden">
                <div class="bg-sky-600 h-1.5 rounded-full" style="width: <?= $pct ?>%"></div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
      <?php endif; ?>

      <!-- 3. CHARTS: LAUNCHES PER YEAR & LAUNCH SITES -->
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg sm:text-xl font-bold tracking-tight text-gray-900 dark:text-white flex items-center gap-2">
            <iconify-icon icon="lucide:bar-chart-3" class="size-5 text-sky-600 dark:text-sky-400"></iconify-icon>
            Aktivitas & Riwayat Peluncuran
          </h2>
          <span class="text-xs text-gray-500 dark:text-neutral-400">Visualisasi data tahunan & lokasi</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          
          <!-- Chart 1: Launches Per Year -->
          <div class="p-6 rounded-2xl bg-white border border-gray-200/80 shadow-2xs dark:bg-neutral-800 dark:border-neutral-700/80 flex flex-col">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Peluncuran Per Tahun</h3>
                <p class="text-xs text-gray-500 dark:text-neutral-400">Rekor per tahun: <?= htmlspecialchars((string)($lpy['most_in_year'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
              </div>
              <span class="text-xs font-semibold py-1 px-2.5 rounded-full bg-sky-50 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300 border border-sky-100 dark:border-sky-900/40">
                2006 – Sekarang
              </span>
            </div>
            <div class="relative w-full h-72">
              <canvas id="launches-per-year-chart"></canvas>
            </div>
          </div>

          <!-- Chart 2: Launch Sites -->
          <div class="p-6 rounded-2xl bg-white border border-gray-200/80 shadow-2xs dark:bg-neutral-800 dark:border-neutral-700/80 flex flex-col">
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Aktivitas Lokasi Peluncuran</h3>
                <p class="text-xs text-gray-500 dark:text-neutral-400">Distribusi misi di berbagai spaceport</p>
              </div>
              <span class="text-xs font-semibold py-1 px-2.5 rounded-full bg-indigo-50 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300 border border-indigo-100 dark:border-indigo-900/40">
                Spaceports
              </span>
            </div>
            <div class="relative w-full h-72">
              <canvas id="launch-sites-chart"></canvas>
            </div>
          </div>

        </div>
      </section>

      <!-- 4. RECOVERY & BOOSTER LANDING SITES -->
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg sm:text-xl font-bold tracking-tight text-gray-900 dark:text-white flex items-center gap-2">
            <iconify-icon icon="lucide:anchor" class="size-5 text-teal-600 dark:text-teal-400"></iconify-icon>
            Pendaratan & Pemulihan Booster
          </h2>
          <span class="text-xs text-gray-500 dark:text-neutral-400">Droneship, Landing Zone & Catch Arms</span>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
          
          <!-- Landing Sites List (Span 2) -->
          <div class="lg:col-span-2 p-6 rounded-2xl bg-white border border-gray-200/80 shadow-2xs dark:bg-neutral-800 dark:border-neutral-700/80">
            <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">Lokasi Pendaratan Utama</h3>
            <p class="text-xs text-gray-500 dark:text-neutral-400 mb-5">Statistik sukses pemulihan first-stage booster</p>
            
            <div class="space-y-4">
              <?php foreach ($landingSites as $site): ?>
                <?php 
                  $siteTotal = (int)($site['total'] ?? 0);
                  $siteSuccess = (int)($site['success'] ?? 0);
                  $barPct = $maxLandingTotal > 0 ? round(($siteTotal / $maxLandingTotal) * 100, 1) : 0;
                ?>
                <div class="space-y-1.5">
                  <div class="flex items-center justify-between text-xs">
                    <span class="font-semibold text-gray-900 dark:text-white flex items-center gap-1.5">
                      <?= htmlspecialchars($site['name'], ENT_QUOTES, 'UTF-8') ?>
                      <?php if (!empty($site['extra'])): ?>
                        <span class="text-xs font-normal text-gray-500 dark:text-neutral-400">(<?= htmlspecialchars($site['extra'], ENT_QUOTES, 'UTF-8') ?>)</span>
                      <?php endif; ?>
                    </span>
                    <span class="font-bold text-gray-800 dark:text-neutral-200">
                      <?= $siteSuccess ?> / <?= $siteTotal ?> <span class="text-teal-600 dark:text-teal-400 font-semibold">(<?= htmlspecialchars($site['rate'], ENT_QUOTES, 'UTF-8') ?>)</span>
                    </span>
                  </div>
                  <div class="w-full bg-gray-100 dark:bg-neutral-700 rounded-full h-2 overflow-hidden">
                    <div class="bg-gradient-to-r from-sky-500 to-teal-500 h-2 rounded-full" style="width: <?= $barPct ?>%"></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Turnarounds & Records (Span 1) -->
          <div class="p-6 rounded-2xl bg-white border border-gray-200/80 shadow-2xs dark:bg-neutral-800 dark:border-neutral-700/80 flex flex-col justify-between space-y-4">
            <div>
              <h3 class="text-base font-bold text-gray-900 dark:text-white mb-1">Rekor Turnaround</h3>
              <p class="text-xs text-gray-500 dark:text-neutral-400 mb-4">Waktu tersingkat antar peluncuran</p>

              <div class="space-y-4">
                <!-- Fastest Overall -->
                <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-neutral-900/60 border border-gray-100 dark:border-neutral-800">
                  <p class="text-xs font-semibold uppercase tracking-wider text-sky-600 dark:text-sky-400">Fastest Turnaround</p>
                  <p class="text-lg font-bold text-gray-900 dark:text-white mt-1"><?= htmlspecialchars((string)($turnarounds['fastest']['value'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
                  <?php if (!empty($turnarounds['fastest']['details'])): ?>
                    <p class="text-xs text-gray-500 dark:text-neutral-400 mt-1"><?= htmlspecialchars((string)$turnarounds['fastest']['details'], ENT_QUOTES, 'UTF-8') ?></p>
                  <?php endif; ?>
                </div>

                <!-- Fastest Booster -->
                <div class="p-3.5 rounded-xl bg-gray-50 dark:bg-neutral-900/60 border border-gray-100 dark:border-neutral-800">
                  <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Fastest Booster Re-flight</p>
                  <p class="text-lg font-bold text-gray-900 dark:text-white mt-1"><?= htmlspecialchars((string)($turnarounds['fastest_booster']['value'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
                  <?php if (!empty($turnarounds['fastest_booster']['details'])): ?>
                    <p class="text-xs text-gray-500 dark:text-neutral-400 mt-1"><?= htmlspecialchars((string)$turnarounds['fastest_booster']['details'], ENT_QUOTES, 'UTF-8') ?></p>
                  <?php endif; ?>
                </div>
              </div>
            </div>

            <!-- Turnaround by Site mini list -->
            <?php if (!empty($turnarounds['sites'])): ?>
              <div class="pt-3 border-t border-gray-100 dark:border-neutral-800">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-neutral-500 mb-2">Spaceport Records</p>
                <div class="space-y-1.5 text-xs">
                  <?php foreach (array_slice($turnarounds['sites'], 0, 3) as $ts): ?>
                    <div class="flex items-center justify-between">
                      <span class="text-gray-600 dark:text-neutral-400"><?= htmlspecialchars($ts['site'], ENT_QUOTES, 'UTF-8') ?></span>
                      <span class="font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($ts['time'], ENT_QUOTES, 'UTF-8') ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

          </div>

        </div>
      </section>

      <!-- 5. REUSE & DRAGON CAPSULES -->
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg sm:text-xl font-bold tracking-tight text-gray-900 dark:text-white flex items-center gap-2">
            <iconify-icon icon="lucide:repeat" class="size-5 text-indigo-600 dark:text-indigo-400"></iconify-icon>
            Penggunaan Ulang (Reuse) & Armada Dragon
          </h2>
          <span class="text-xs text-gray-500 dark:text-neutral-400">Reliabilitas roket & wahana antariksa berawak</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          
          <!-- Booster Reuse Card -->
          <div class="p-6 rounded-2xl bg-white border border-gray-200/80 shadow-2xs dark:bg-neutral-800 dark:border-neutral-700/80 space-y-4">
            <div class="flex items-center gap-3">
              <span class="size-10 rounded-xl bg-sky-50 text-sky-600 dark:bg-sky-950/50 dark:text-sky-400 flex items-center justify-center">
                <iconify-icon icon="lucide:rocket" class="size-5"></iconify-icon>
              </span>
              <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Booster Reuse</h3>
                <p class="text-xs text-gray-500 dark:text-neutral-400">First-stage booster reusability</p>
              </div>
            </div>

            <div class="space-y-3 pt-2">
              <div class="flex items-center justify-between text-xs">
                <span class="text-gray-500 dark:text-neutral-400">Paling Sering Terbang:</span>
                <span class="font-bold text-gray-900 dark:text-white"><?= htmlspecialchars((string)($boosterReuse['most_flights'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <div class="flex items-center justify-between text-xs">
                <span class="text-gray-500 dark:text-neutral-400">Booster Berhasil Mendarat:</span>
                <span class="font-bold text-teal-600 dark:text-teal-400"><?= htmlspecialchars((string)($boosterReuse['landed'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <div class="flex items-center justify-between text-xs">
                <span class="text-gray-500 dark:text-neutral-400">Total Penggunaan Ulang:</span>
                <span class="font-bold text-sky-600 dark:text-sky-400"><?= htmlspecialchars((string)($boosterReuse['reflown'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <div class="flex items-center justify-between text-xs">
                <span class="text-gray-500 dark:text-neutral-400">Block 5 Mendarat:</span>
                <span class="font-bold text-indigo-600 dark:text-indigo-400"><?= htmlspecialchars((string)($boosterReuse['block_5_landed'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
            </div>
          </div>

          <!-- Capsule Reuse Card -->
          <div class="p-6 rounded-2xl bg-white border border-gray-200/80 shadow-2xs dark:bg-neutral-800 dark:border-neutral-700/80 space-y-4">
            <div class="flex items-center gap-3">
              <span class="size-10 rounded-xl bg-teal-50 text-teal-600 dark:bg-teal-950/50 dark:text-teal-400 flex items-center justify-center">
                <iconify-icon icon="lucide:box" class="size-5"></iconify-icon>
              </span>
              <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Capsule Reuse</h3>
                <p class="text-xs text-gray-500 dark:text-neutral-400">Dragon capsule recovery</p>
              </div>
            </div>

            <div class="space-y-3 pt-2">
              <div class="flex items-center justify-between text-xs">
                <span class="text-gray-500 dark:text-neutral-400">Kapsul Berhasil Mendarat:</span>
                <span class="font-bold text-teal-600 dark:text-teal-400"><?= htmlspecialchars((string)($capsuleReuse['landed'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <div class="flex items-center justify-between text-xs">
                <span class="text-gray-500 dark:text-neutral-400">Total Kapsul Diterbangkan Ulang:</span>
                <span class="font-bold text-sky-600 dark:text-sky-400"><?= htmlspecialchars((string)($capsuleReuse['reflown'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <div class="flex items-center justify-between text-xs">
                <span class="text-gray-500 dark:text-neutral-400">Misi Penerbangan Ulang:</span>
                <span class="font-bold text-indigo-600 dark:text-indigo-400"><?= htmlspecialchars((string)($dragon['reflights'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
            </div>
          </div>

          <!-- Dragon Program Card -->
          <div class="p-6 rounded-2xl bg-white border border-gray-200/80 shadow-2xs dark:bg-neutral-800 dark:border-neutral-700/80 space-y-4">
            <div class="flex items-center gap-3">
              <span class="size-10 rounded-xl bg-amber-50 text-amber-600 dark:bg-amber-950/50 dark:text-amber-400 flex items-center justify-center">
                <iconify-icon icon="lucide:users" class="size-5"></iconify-icon>
              </span>
              <div>
                <h3 class="text-base font-bold text-gray-900 dark:text-white">Dragon Spacecraft</h3>
                <p class="text-xs text-gray-500 dark:text-neutral-400">Awak & kargo stasiun ISS</p>
              </div>
            </div>

            <div class="space-y-3 pt-2">
              <div class="flex items-center justify-between text-xs">
                <span class="text-gray-500 dark:text-neutral-400">Total Misi Dragon:</span>
                <span class="font-bold text-gray-900 dark:text-white"><?= htmlspecialchars((string)($dragon['missions'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <div class="flex items-center justify-between text-xs">
                <span class="text-gray-500 dark:text-neutral-400">Kargo ISS:</span>
                <span class="font-bold text-gray-900 dark:text-white"><?= htmlspecialchars((string)($dragon['iss_cargo'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
              <div class="flex items-center justify-between text-xs">
                <span class="text-gray-500 dark:text-neutral-400">Total Awak yang Diterbangkan:</span>
                <span class="font-bold text-amber-600 dark:text-amber-400"><?= htmlspecialchars((string)($dragon['crew_flown_total'] ?? '—'), ENT_QUOTES, 'UTF-8') ?> astronaut</span>
              </div>
              <div class="flex items-center justify-between text-xs">
                <span class="text-gray-500 dark:text-neutral-400">Awak Sedang di Orbit:</span>
                <span class="font-bold text-teal-600 dark:text-teal-400"><?= htmlspecialchars((string)($dragon['crew_in_orbit'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></span>
              </div>
            </div>
          </div>

        </div>
      </section>

      <!-- 6. PAYLOADS & BEYOND EARTH (MARS & MOON) -->
      <section class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-lg sm:text-xl font-bold tracking-tight text-gray-900 dark:text-white flex items-center gap-2">
            <iconify-icon icon="lucide:sparkles" class="size-5 text-amber-500"></iconify-icon>
            Muatan & Program Luar Angkasa
          </h2>
          <span class="text-xs text-gray-500 dark:text-neutral-400">Muatan rekor, Mars, dan Bulan</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          
          <!-- Heaviest LEO -->
          <div class="p-5 rounded-2xl bg-white border border-gray-200/80 shadow-2xs dark:bg-neutral-800 dark:border-neutral-700/80">
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-neutral-400">Muatan Terberat (LEO)</p>
            <p class="text-xl font-extrabold text-gray-900 dark:text-white mt-1"><?= htmlspecialchars((string)($payloads['heaviest_leo']['value'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
            <?php if (!empty($payloads['heaviest_leo']['extra'])): ?>
              <p class="text-xs text-gray-500 dark:text-neutral-400 mt-1"><?= htmlspecialchars((string)$payloads['heaviest_leo']['extra'], ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
          </div>

          <!-- Starlinks in Orbit -->
          <div class="p-5 rounded-2xl bg-white border border-gray-200/80 shadow-2xs dark:bg-neutral-800 dark:border-neutral-700/80">
            <p class="text-xs font-semibold uppercase tracking-wider text-sky-600 dark:text-sky-400">Starlinks di Orbit</p>
            <p class="text-xl font-extrabold text-sky-600 dark:text-sky-400 mt-1"><?= htmlspecialchars((string)($payloads['starlinks_in_orbit']['value'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></p>
            <p class="text-xs text-gray-500 dark:text-neutral-400 mt-1">Satelit konstelasi aktif</p>
          </div>

          <!-- Mars Program -->
          <div class="p-5 rounded-2xl bg-white border border-gray-200/80 shadow-2xs dark:bg-neutral-800 dark:border-neutral-700/80">
            <div class="flex items-center justify-between">
              <p class="text-xs font-semibold uppercase tracking-wider text-rose-600 dark:text-rose-400">Misi Planet Mars</p>
              <span class="text-xs font-semibold py-0.5 px-2 rounded-full bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300">Target NET 2030</span>
            </div>
            <div class="flex items-baseline gap-2 mt-2">
              <span class="text-xl font-extrabold text-gray-900 dark:text-white"><?= htmlspecialchars((string)($mars['landings'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></span>
              <span class="text-xs text-gray-500 dark:text-neutral-400">Pendaratan berhasil</span>
            </div>
            <p class="text-xs text-gray-500 dark:text-neutral-400 mt-1">Populasi: <?= htmlspecialchars((string)($mars['population'] ?? '0 (Untuk sekarang...)'), ENT_QUOTES, 'UTF-8') ?></p>
          </div>

          <!-- Moon Program -->
          <div class="p-5 rounded-2xl bg-white border border-gray-200/80 shadow-2xs dark:bg-neutral-800 dark:border-neutral-700/80">
            <div class="flex items-center justify-between">
              <p class="text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-400">Program Bulan</p>
              <span class="text-xs font-semibold py-0.5 px-2 rounded-full bg-slate-100 text-slate-700 dark:bg-neutral-700 dark:text-neutral-300">Artemis III/IV</span>
            </div>
            <div class="flex items-baseline gap-2 mt-2">
              <span class="text-xl font-extrabold text-gray-900 dark:text-white"><?= htmlspecialchars((string)($moon['landings'] ?? '0'), ENT_QUOTES, 'UTF-8') ?></span>
              <span class="text-xs text-gray-500 dark:text-neutral-400">Pendaratan HLS</span>
            </div>
            <p class="text-xs text-gray-500 dark:text-neutral-400 mt-1">Pangkalan bulan direncanakan</p>
          </div>

        </div>
      </section>

    </main>

    <!-- FOOTER -->
    <footer class="mt-12 border-t border-gray-200 dark:border-neutral-800 bg-white dark:bg-neutral-900 py-6 text-xs text-gray-500 dark:text-neutral-400">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <iconify-icon icon="lucide:rocket" class="size-4 text-sky-600 dark:text-sky-400"></iconify-icon>
          <span class="font-semibold text-gray-800 dark:text-neutral-200">SpaceXStat</span>
        </div>
        <p>
          Data disediakan oleh <a href="https://spacexnow.com/stats" target="_blank" rel="noopener noreferrer" class="underline hover:text-sky-600 transition">SpaceXNow</a>. Dirancang untuk kejelasan telemetry peluncuran.
        </p>
      </div>
    </footer>

  </div>

  <!-- JSON Telemetry Data for Chart.js in main.js -->
  <script id="spacex-stats-data" type="application/json">
    <?= json_encode($stats, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
  </script>
</body>
</html>
