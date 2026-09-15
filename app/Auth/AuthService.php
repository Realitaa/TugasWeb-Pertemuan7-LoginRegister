<?php

declare(strict_types=1);

namespace Realitaa\PhpVite\Auth;

class AuthService
{
    private UserRepository $userRepository;

    public function __construct(?UserRepository $userRepository = null)
    {
        $this->userRepository = $userRepository ?? new UserRepository();
    }

    /**
     * Get underlying user repository.
     */
    public function getUserRepository(): UserRepository
    {
        return $this->userRepository;
    }

    /**
     * Initialize PHP session if not already active.
     */
    public function initSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Get currently logged-in user from session.
     *
     * @return array{id: string, name: string, email: string}|null
     */
    public function user(): ?array
    {
        $this->initSession();
        return $_SESSION['user'] ?? null;
    }

    /**
     * Determine if current visitor is authenticated.
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Check remember-me cookie and auto-authenticate if valid.
     *
     * @return array<string, mixed>|null
     */
    public function checkRememberMe(): ?array
    {
        $this->initSession();

        if (empty($_SESSION['user']) && !empty($_COOKIE['spacex_remember'])) {
            $token = (string)$_COOKIE['spacex_remember'];
            $matchedUser = $this->userRepository->findByRememberToken($token);

            if ($matchedUser !== null) {
                $_SESSION['user'] = [
                    'id' => $matchedUser['id'],
                    'name' => $matchedUser['name'],
                    'email' => $matchedUser['email'],
                ];
                return $matchedUser;
            }
        }

        return null;
    }

    /**
     * Authenticate user credentials and establish session.
     *
     * @return array{success: bool, errors: array<string, string>, user: array<string, mixed>|null}
     */
    public function login(string $email, string $password, bool $remember = false): array
    {
        $this->initSession();

        $email = strtolower(trim($email));
        $errors = [];

        if (empty($email)) {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        if (empty($password)) {
            $errors['password'] = 'Password is required.';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors,
                'user' => null,
            ];
        }

        $user = $this->userRepository->findByEmail($email);

        if ($user !== null && password_verify($password, (string)($user['password'] ?? ''))) {
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
            ];

            if ($remember) {
                $rememberToken = bin2hex(random_bytes(32));
                $this->userRepository->updateRememberToken($user['id'], $rememberToken);

                setcookie('spacex_remember', $rememberToken, [
                    'expires' => time() + (86400 * 30),
                    'path' => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }

            return [
                'success' => true,
                'errors' => [],
                'user' => $user,
            ];
        }

        return [
            'success' => false,
            'errors' => [
                'auth' => 'Invalid email or password combination.',
            ],
            'user' => null,
        ];
    }

    /**
     * Validate and register a new user account.
     *
     * @return array{success: bool, errors: array<string, string>, user: array<string, mixed>|null}
     */
    public function register(
        string $name,
        string $email,
        string $password,
        string $passwordConfirmation
    ): array {
        $this->initSession();

        $name = trim($name);
        $email = strtolower(trim($email));
        $errors = [];

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

        if (empty($errors['email']) && $this->userRepository->emailExists($email)) {
            $errors['email'] = 'An account with this email address already exists.';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors,
                'user' => null,
            ];
        }

        $user = $this->userRepository->create($name, $email, $password);

        return [
            'success' => true,
            'errors' => [],
            'user' => $user,
        ];
    }

    /**
     * Terminate the user session and clear cookies.
     */
    public function logout(): void
    {
        $this->initSession();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();

        // Clear remember-me cookie
        setcookie('spacex_remember', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Update user profile credentials and update current session.
     *
     * @return array{success: bool, errors: array<int, string>, user: array<string, mixed>|null}
     */
    public function updateProfile(
        string $userIdOrEmail,
        string $name,
        string $email,
        string $password = ''
    ): array {
        $this->initSession();

        $name = trim($name);
        $email = strtolower(trim($email));
        $errors = [];

        $user = $this->userRepository->findById($userIdOrEmail)
            ?? $this->userRepository->findByEmail($userIdOrEmail);

        if ($user === null) {
            return [
                'success' => false,
                'errors' => ['Akun pengguna tidak ditemukan.'],
                'user' => null,
            ];
        }

        if ($name === '') {
            $errors[] = 'Nama tampilan tidak boleh kosong.';
        }

        if ($email === '') {
            $errors[] = 'Alamat email tidak boleh kosong.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        } elseif ($this->userRepository->emailExists($email, $user['id'])) {
            $errors[] = 'Email ini sudah digunakan oleh akun lain.';
        }

        if ($password !== '' && strlen($password) < 6) {
            $errors[] = 'Password minimal harus 6 karakter.';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors,
                'user' => null,
            ];
        }

        $fields = [
            'name' => $name,
            'email' => $email,
        ];
        if ($password !== '') {
            $fields['password'] = $password;
        }

        $updatedUser = $this->userRepository->update($user['id'], $fields);

        if ($updatedUser !== null) {
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['username'] = $name;
            $_SESSION['user']['email'] = $email;
        }

        return [
            'success' => true,
            'errors' => [],
            'user' => $updatedUser,
        ];
    }

    /**
     * Guard protected pages: require authentication or redirect to login.
     *
     * @return array{id: string, name: string, email: string}
     */
    public function requireAuth(string $redirect = 'index.php'): array
    {
        $this->initSession();
        $this->checkRememberMe();

        $user = $this->user();
        if ($user === null) {
            $this->setFlashToast(
                'warning',
                'Authentication Required',
                'Silakan masuk terlebih dahulu untuk mengakses telemetry dashboard.'
            );
            header("Location: {$redirect}");
            exit;
        }

        return $user;
    }

    /**
     * Guard guest-only pages: redirect logged-in users to dashboard.
     */
    public function requireGuest(string $redirect = 'dashboard.php'): void
    {
        $this->initSession();
        $this->checkRememberMe();

        if ($this->check()) {
            header("Location: {$redirect}");
            exit;
        }
    }

    /**
     * Generate Gravatar avatar URL.
     */
    public function gravatar(string $email, int $size = 96): string
    {
        $cleanEmail = strtolower(trim($email));
        $hash = md5($cleanEmail);
        return "https://www.gravatar.com/avatar/{$hash}?s={$size}&d=404";
    }

    /**
     * Flash toast helper to set a temporary message in session.
     */
    public function setFlashToast(string $type, string $title, string $message): void
    {
        $this->initSession();
        $_SESSION['flash_toast'] = [
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ];
    }

    /**
     * Flash toast helper to consume a temporary message from session.
     *
     * @return array{type: string, title: string, message: string}|null
     */
    public function getFlashToast(): ?array
    {
        $this->initSession();
        $toast = $_SESSION['flash_toast'] ?? null;
        unset($_SESSION['flash_toast']);
        return $toast;
    }
}
