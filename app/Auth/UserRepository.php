<?php

declare(strict_types=1);

namespace Realitaa\PhpVite\Auth;

class UserRepository
{
    private string $filePath;

    public function __construct(?string $filePath = null)
    {
        $this->filePath = $filePath ?? dirname(__DIR__, 2) . '/data/users.json';
        $this->ensureFileExists();
    }

    /**
     * Ensure storage directory and users.json file exist.
     */
    private function ensureFileExists(): void
    {
        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        if (!file_exists($this->filePath)) {
            file_put_contents($this->filePath, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    /**
     * Get all users.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        if (!file_exists($this->filePath)) {
            return [];
        }

        $content = file_get_contents($this->filePath);
        if ($content === false || trim($content) === '') {
            return [];
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Save all users to JSON file.
     *
     * @param array<int, array<string, mixed>> $users
     */
    public function saveAll(array $users): void
    {
        $this->ensureFileExists();
        file_put_contents($this->filePath, json_encode(array_values($users), JSON_PRETTY_PRINT));
    }

    /**
     * Find user by ID.
     */
    public function findById(string $id): ?array
    {
        foreach ($this->all() as $user) {
            if (($user['id'] ?? '') === $id) {
                return $user;
            }
        }
        return null;
    }

    /**
     * Find user by email address (case-insensitive).
     */
    public function findByEmail(string $email): ?array
    {
        $targetEmail = strtolower(trim($email));
        foreach ($this->all() as $user) {
            if (strtolower((string)($user['email'] ?? '')) === $targetEmail) {
                return $user;
            }
        }
        return null;
    }

    /**
     * Find user by remember token using timing-safe comparison.
     */
    public function findByRememberToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        foreach ($this->all() as $user) {
            $userToken = (string)($user['remember_token'] ?? '');
            if ($userToken !== '' && hash_equals($userToken, $token)) {
                return $user;
            }
        }
        return null;
    }

    /**
     * Check whether an email already exists (optionally excluding a user ID).
     */
    public function emailExists(string $email, ?string $excludeId = null): bool
    {
        $targetEmail = strtolower(trim($email));
        foreach ($this->all() as $user) {
            if ($excludeId !== null && ($user['id'] ?? '') === $excludeId) {
                continue;
            }
            if (strtolower((string)($user['email'] ?? '')) === $targetEmail) {
                return true;
            }
        }
        return false;
    }

    /**
     * Create and persist a new user record.
     */
    public function create(string $name, string $email, string $password): array
    {
        $users = $this->all();

        $newUser = [
            'id' => 'usr_' . bin2hex(random_bytes(8)),
            'name' => trim($name),
            'email' => strtolower(trim($email)),
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('c'),
            'remember_token' => null,
        ];

        $users[] = $newUser;
        $this->saveAll($users);

        return $newUser;
    }

    /**
     * Update user details.
     *
     * @param array{name?: string, email?: string, password?: string} $data
     */
    public function update(string $id, array $data): ?array
    {
        $users = $this->all();
        $updatedUser = null;

        foreach ($users as $index => $user) {
            if (($user['id'] ?? '') === $id) {
                if (isset($data['name'])) {
                    $user['name'] = trim((string)$data['name']);
                }
                if (isset($data['email'])) {
                    $user['email'] = strtolower(trim((string)$data['email']));
                }
                if (!empty($data['password'])) {
                    $user['password'] = password_hash((string)$data['password'], PASSWORD_DEFAULT);
                }

                $users[$index] = $user;
                $updatedUser = $user;
                break;
            }
        }

        if ($updatedUser !== null) {
            $this->saveAll($users);
        }

        return $updatedUser;
    }

    /**
     * Update or clear remember token for a user.
     */
    public function updateRememberToken(string $id, ?string $token): void
    {
        $users = $this->all();
        foreach ($users as $index => $user) {
            if (($user['id'] ?? '') === $id) {
                $users[$index]['remember_token'] = $token;
                $this->saveAll($users);
                return;
            }
        }
    }
}
