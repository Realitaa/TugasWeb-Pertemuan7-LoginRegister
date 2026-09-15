<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Realitaa\PhpVite\Auth\AuthService;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$auth = new AuthService();
$auth->initSession();
$auth->requireGuest('dashboard.php');

require_once __DIR__ . '/src/components/ui/Input.php';
require_once __DIR__ . '/src/components/ui/Button.php';
require_once __DIR__ . '/src/components/ThemeSwitch.php';
require_once __DIR__ . '/src/components/AuthImage.php';

$appName = $_ENV['VITE_APP_NAME'] ?? $_ENV['APP_NAME'] ?? 'SpaceXStat';
$currentUser = $auth->user();
$toast = $auth->getFlashToast();

$errors = [];
$oldEmail = '';

// Handle Login Submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $remember = !empty($_POST['remember']);
    $oldEmail = $email;

    $result = $auth->login($email, $password, $remember);

    if ($result['success']) {
        $userName = (string)($result['user']['name'] ?? 'User');
        $auth->setFlashToast(
            'success',
            'Welcome Back!',
            'Signed in successfully as ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8')
        );

        $hasDashboard = file_exists(__DIR__ . '/dashboard.php');
        header('Location: ' . ($hasDashboard ? 'dashboard.php' : 'index.php'));
        exit;
    }

    $errors = $result['errors'];
    if (isset($errors['auth'])) {
        $toast = [
            'type' => 'error',
            'title' => 'Authentication Failed',
            'message' => 'Invalid email or password. Please check your credentials.',
        ];
    } else {
        $toast = [
            'type' => 'error',
            'title' => 'Validation Error',
            'message' => reset($errors) ?: 'Please fill out all required fields.',
        ];
    }
}
?>
<!doctype html>
<html lang="en" class="h-full">
  <head>
    <meta charset="UTF-8" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Sign In &bull; <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    
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

    <link rel="preload" href="/public/spacex-Ptd-iTdrCJM-unsplash.webp" as="image" type="image/webp" media="(min-width: 1024px)" />

    <?= vite()->tags('src/main.js') ?>
  </head>
  <body class="h-full bg-white dark:bg-neutral-900 transition-colors duration-200">
    
    <!-- Flash Toast Bridge -->
    <?php if ($toast): ?>
      <div id="flash-toast-data" class="hidden" data-toast="<?= htmlspecialchars(json_encode($toast), ENT_QUOTES, 'UTF-8') ?>"></div>
    <?php endif; ?>

    <main class="w-full min-h-screen grid grid-cols-1 lg:grid-cols-2">
      
      <?= renderAuthImage($appName) ?>

      <!-- Right Column: Authentication / Login -->
      <div class="flex flex-col justify-between p-6 sm:p-10 lg:p-14 min-h-screen bg-white dark:bg-neutral-900">
        
        <!-- Header: Light/Dark/System Theme Switch -->
        <header class="w-full flex items-center justify-end pb-4 border-b border-gray-100 dark:border-neutral-800">
          <?= renderThemeSwitch() ?>
        </header>

        <!-- Main Form Container -->
        <div class="w-full max-w-md mx-auto my-auto py-8">
          
          <?php if ($currentUser): ?>
            <!-- Signed In State (when dashboard is not present) -->
            <div class="p-6 bg-blue-50/70 border border-blue-200 rounded-2xl dark:bg-blue-900/20 dark:border-blue-800 text-center space-y-4">
              <div class="size-16 rounded-full bg-blue-600/10 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 mx-auto flex items-center justify-center">
                <iconify-icon icon="lucide:user-check" class="text-3xl"></iconify-icon>
              </div>
              <div>
                <h3 class="text-lg font-bold text-gray-800 dark:text-white">Already Signed In</h3>
                <p class="text-sm text-gray-600 dark:text-neutral-400 mt-1">
                  You are currently authenticated as <strong><?= htmlspecialchars($currentUser['name'], ENT_QUOTES, 'UTF-8') ?></strong> (<?= htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8') ?>).
                </p>
              </div>
              <div class="pt-2 flex flex-col sm:flex-row gap-2 justify-center">
                <?php if ($hasDashboard): ?>
                  <a href="dashboard.php" class="py-2.5 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-semibold rounded-xl bg-blue-600 text-white hover:bg-blue-700 transition-colors">
                    <iconify-icon icon="lucide:layout-dashboard"></iconify-icon> Go to Dashboard
                  </a>
                <?php endif; ?>
                <a href="logout.php" class="py-2.5 px-4 inline-flex justify-center items-center gap-x-2 text-sm font-medium rounded-xl border border-gray-200 bg-white text-gray-800 hover:bg-gray-50 dark:bg-neutral-800 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-700 transition-colors">
                  <iconify-icon icon="lucide:log-out"></iconify-icon> Sign Out
                </a>
              </div>
            </div>
          <?php else: ?>
            <!-- Login Form -->
            <div class="mb-8">
              <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                Sign in to SpaceXStat
              </h1>
              <p class="mt-2 text-sm text-gray-500 dark:text-neutral-400">
                Track real-time Falcon 9, Starship, and Dragon orbital missions.
              </p>
            </div>

            <?php if (!empty($errors['auth'])): ?>
              <div class="mb-5 p-4 rounded-xl bg-red-50 border border-red-200 text-sm text-red-700 dark:bg-red-900/20 dark:border-red-800/50 dark:text-red-400 flex items-start gap-3" role="alert">
                <iconify-icon icon="lucide:alert-triangle" class="text-lg shrink-0 mt-0.5"></iconify-icon>
                <span><?= htmlspecialchars($errors['auth'], ENT_QUOTES, 'UTF-8') ?></span>
              </div>
            <?php endif; ?>

            <form action="index.php" method="POST" class="space-y-4" novalidate>
              <input type="hidden" name="action" value="login" />

              <!-- Email Field -->
              <?= renderInput([
                  'name' => 'email',
                  'label' => 'Email address',
                  'type' => 'email',
                  'value' => $oldEmail,
                  'placeholder' => 'astronaut@spacex.com',
                  'icon' => 'lucide:mail',
                  'required' => true,
                  'autocomplete' => 'email',
                  'error' => $errors['email'] ?? null,
              ]) ?>

              <!-- Password Field with Preline Toggle -->
              <?= renderInput([
                  'name' => 'password',
                  'label' => 'Password',
                  'type' => 'password',
                  'placeholder' => '••••••••',
                  'icon' => 'lucide:lock',
                  'required' => true,
                  'togglePassword' => true,
                  'autocomplete' => 'current-password',
                  'error' => $errors['password'] ?? null,
              ]) ?>

              <!-- Remember Me -->
              <div class="flex items-center justify-between pt-1">
                <label class="flex items-center gap-x-2 text-sm text-gray-600 dark:text-neutral-400 cursor-pointer select-none">
                  <input 
                    type="checkbox" 
                    name="remember" 
                    value="1"
                    class="shrink-0 size-4 rounded-md border-gray-300 text-blue-600 focus:ring-blue-500 dark:bg-neutral-800 dark:border-neutral-700 dark:checked:bg-blue-500 dark:checked:border-blue-500"
                  />
                  <span>Remember this device</span>
                </label>
              </div>

              <!-- Submit Button -->
              <div class="pt-2">
                <?= renderButton([
                    'text' => 'Sign in to Dashboard',
                    'icon' => 'lucide:arrow-right',
                    'iconPosition' => 'end',
                ]) ?>
              </div>
            </form>

            <!-- Link to Register -->
            <div class="mt-6 text-center text-sm text-gray-500 dark:text-neutral-400">
              Don't have an account yet?
              <a href="register.php" class="font-semibold text-blue-600 hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300 hover:underline transition-colors">
                Create an account
              </a>
            </div>
          <?php endif; ?>

        </div>

        <!-- Footer -->
        <footer class="w-full pt-6 border-t border-gray-100 dark:border-neutral-800 text-center text-xs text-gray-400 dark:text-neutral-500">
          <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>. Built for telemetry exploration.</p>
        </footer>

      </div>

    </main>

  </body>
</html>
