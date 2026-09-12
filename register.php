<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/src/components/ui/Input.php';
require_once __DIR__ . '/src/components/ui/Button.php';
require_once __DIR__ . '/src/components/ThemeSwitch.php';
require_once __DIR__ . '/src/components/AuthImage.php';

$appName = $_ENV['VITE_APP_NAME'] ?? $_ENV['APP_NAME'] ?? 'SpaceXStat';
$usersFile = __DIR__ . '/data/users.json';

if (!file_exists($usersFile)) {
    if (!is_dir(dirname($usersFile))) {
        mkdir(dirname($usersFile), 0755, true);
    }
    file_put_contents($usersFile, json_encode([], JSON_PRETTY_PRINT));
}

// If already logged in and dashboard exists, redirect
$hasDashboard = file_exists(__DIR__ . '/dashboard.php');
if (!empty($_SESSION['user']) && $hasDashboard) {
    header('Location: dashboard.php');
    exit;
}

$toast = $_SESSION['flash_toast'] ?? null;
unset($_SESSION['flash_toast']);

$errors = [];
$old = [
    'name' => '',
    'email' => '',
];

// Handle Registration Submission (In-file Backend Logic)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $name = trim((string)($_POST['name'] ?? ''));
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirmation = (string)($_POST['password_confirmation'] ?? '');

    $old['name'] = $name;
    $old['email'] = $email;

    if (empty($name)) {
        $errors['name'] = 'Full name is required.';
    } elseif (mb_strlen($name) < 2) {
        $errors['name'] = 'Full name must be at least 2 characters.';
    }

    if (empty($email)) {
        $errors['email'] = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please provide a valid email address.';
    }

    if (empty($password)) {
        $errors['password'] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'Password must be at least 6 characters.';
    }

    if ($password !== $passwordConfirmation) {
        $errors['password_confirmation'] = 'Passwords do not match.';
    }

    $users = json_decode((string)file_get_contents($usersFile), true) ?: [];

    // Check duplicate email
    if (empty($errors['email'])) {
        foreach ($users as $u) {
            if (strtolower($u['email'] ?? '') === $email) {
                $errors['email'] = 'An account with this email address already exists.';
                break;
            }
        }
    }

    if (empty($errors)) {
        $newUser = [
            'id' => 'usr_' . bin2hex(random_bytes(8)),
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('c'),
            'remember_token' => null,
        ];

        $users[] = $newUser;
        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));

        $_SESSION['flash_toast'] = [
            'type' => 'success',
            'title' => 'Account Created!',
            'message' => 'Registration complete. You can now sign in with your credentials.',
        ];

        header('Location: index.php');
        exit;
    } else {
        $toast = [
            'type' => 'error',
            'title' => 'Registration Failed',
            'message' => reset($errors) ?: 'Please resolve the highlighted errors.',
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
    <title>Create Account &bull; <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    
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

      <!-- Right Column: Registration Form -->
      <div class="flex flex-col justify-between p-6 sm:p-10 lg:p-14 min-h-screen bg-white dark:bg-neutral-900">
        
        <!-- Header: Light/Dark/System Theme Switch -->
        <header class="w-full flex items-center justify-end pb-4 border-b border-gray-100 dark:border-neutral-800">
          <?= renderThemeSwitch() ?>
        </header>

        <!-- Main Form Container -->
        <div class="w-full max-w-md mx-auto my-auto py-8">
          
          <div class="mb-8">
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
              Create an account
            </h1>
            <p class="mt-2 text-sm text-gray-500 dark:text-neutral-400">
              Join <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> to explore flight telemetry and mission stats.
            </p>
          </div>

          <form action="register.php" method="POST" class="space-y-4" novalidate>
            <input type="hidden" name="action" value="register" />

            <!-- Full Name Field -->
            <?= renderInput([
                'name' => 'name',
                'label' => 'Full name',
                'type' => 'text',
                'value' => $old['name'],
                'placeholder' => 'Gwynne Shotwell',
                'icon' => 'lucide:user',
                'required' => true,
                'autocomplete' => 'name',
                'error' => $errors['name'] ?? null,
            ]) ?>

            <!-- Email Field -->
            <?= renderInput([
                'name' => 'email',
                'label' => 'Email address',
                'type' => 'email',
                'value' => $old['email'],
                'placeholder' => 'astronaut@spacex.com',
                'icon' => 'lucide:mail',
                'required' => true,
                'autocomplete' => 'email',
                'error' => $errors['email'] ?? null,
            ]) ?>

            <!-- Password Field -->
            <?= renderInput([
                'name' => 'password',
                'label' => 'Password',
                'type' => 'password',
                'placeholder' => 'At least 6 characters',
                'icon' => 'lucide:lock',
                'required' => true,
                'togglePassword' => true,
                'autocomplete' => 'new-password',
                'error' => $errors['password'] ?? null,
            ]) ?>

            <!-- Confirm Password Field -->
            <?= renderInput([
                'name' => 'password_confirmation',
                'label' => 'Confirm password',
                'type' => 'password',
                'placeholder' => 'Re-enter your password',
                'icon' => 'lucide:lock-keyhole',
                'required' => true,
                'togglePassword' => true,
                'autocomplete' => 'new-password',
                'error' => $errors['password_confirmation'] ?? null,
            ]) ?>

            <!-- Submit Button -->
            <div class="pt-2">
              <?= renderButton([
                  'text' => 'Create My Account',
                  'icon' => 'lucide:user-plus',
                  'iconPosition' => 'end',
              ]) ?>
            </div>
          </form>

          <!-- Link to Sign In -->
          <div class="mt-6 text-center text-sm text-gray-500 dark:text-neutral-400">
            Already have an account?
            <a href="index.php" class="font-semibold text-blue-600 hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300 hover:underline transition-colors">
              Sign in instead
            </a>
          </div>

        </div>

        <!-- Footer -->
        <footer class="w-full pt-6 border-t border-gray-100 dark:border-neutral-800 text-center text-xs text-gray-400 dark:text-neutral-500">
          <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>. Built for telemetry exploration.</p>
        </footer>

      </div>

    </main>

  </body>
</html>
