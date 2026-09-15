<?php

declare(strict_types=1);

namespace Realitaa\PhpVite\Tests;

use PHPUnit\Framework\TestCase;
use Realitaa\PhpVite\Auth\AuthService;
use Realitaa\PhpVite\Auth\UserRepository;

class AuthServiceTest extends TestCase
{
    private string $tempUsersFile;
    private UserRepository $userRepository;
    private AuthService $authService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempUsersFile = sys_get_temp_dir() . '/test_users_' . bin2hex(random_bytes(6)) . '.json';
        file_put_contents($this->tempUsersFile, json_encode([]));

        $this->userRepository = new UserRepository($this->tempUsersFile);
        $this->authService = new AuthService($this->userRepository);

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempUsersFile)) {
            @unlink($this->tempUsersFile);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }

        parent::tearDown();
    }

    public function testUserRepositoryCreateAndFind(): void
    {
        $user = $this->userRepository->create('Elon Musk', 'elon@spacex.com', 'starship123');

        $this->assertNotEmpty($user['id']);
        $this->assertSame('Elon Musk', $user['name']);
        $this->assertSame('elon@spacex.com', $user['email']);
        $this->assertTrue(password_verify('starship123', $user['password']));

        $foundById = $this->userRepository->findById($user['id']);
        $this->assertNotNull($foundById);
        $this->assertSame($user['id'], $foundById['id']);

        $foundByEmail = $this->userRepository->findByEmail('ELON@SPACEX.COM');
        $this->assertNotNull($foundByEmail);
        $this->assertSame($user['id'], $foundByEmail['id']);
    }

    public function testRegisterValidationAndSuccess(): void
    {
        // 1. Missing name
        $res = $this->authService->register('', 'user@example.com', 'secret123', 'secret123');
        $this->assertFalse($res['success']);
        $this->assertArrayHasKey('name', $res['errors']);

        // 2. Invalid email format
        $res = $this->authService->register('Test User', 'invalid-email', 'secret123', 'secret123');
        $this->assertFalse($res['success']);
        $this->assertArrayHasKey('email', $res['errors']);

        // 3. Password mismatch
        $res = $this->authService->register('Test User', 'user@example.com', 'secret123', 'wrongmatch');
        $this->assertFalse($res['success']);
        $this->assertArrayHasKey('password_confirmation', $res['errors']);

        // 4. Valid registration
        $res = $this->authService->register('Test User', 'user@example.com', 'secret123', 'secret123');
        $this->assertTrue($res['success']);
        $this->assertNotNull($res['user']);
        $this->assertSame('Test User', $res['user']['name']);

        // 5. Duplicate email check
        $resDup = $this->authService->register('Another User', 'USER@example.com', 'secret123', 'secret123');
        $this->assertFalse($resDup['success']);
        $this->assertArrayHasKey('email', $resDup['errors']);
    }

    public function testLoginSuccessAndFailure(): void
    {
        $this->authService->register('Gwynne Shotwell', 'gwynne@spacex.com', 'falconheavy123', 'falconheavy123');

        // Invalid password
        $fail = $this->authService->login('gwynne@spacex.com', 'wrongpassword');
        $this->assertFalse($fail['success']);
        $this->assertArrayHasKey('auth', $fail['errors']);

        // Valid login
        $success = $this->authService->login('gwynne@spacex.com', 'falconheavy123');
        $this->assertTrue($success['success']);
        $this->assertNotNull($success['user']);
        $this->assertSame('Gwynne Shotwell', $success['user']['name']);
        $this->assertSame('gwynne@spacex.com', $success['user']['email']);

        // Check user is authenticated in session
        $this->assertTrue($this->authService->check());
        $currentUser = $this->authService->user();
        $this->assertSame('Gwynne Shotwell', $currentUser['name']);
    }

    public function testRememberTokenLifecycle(): void
    {
        $user = $this->userRepository->create('Astronaut', 'astro@spacex.com', 'dragonpass123');
        $this->assertNull($this->userRepository->findByRememberToken('non-existent-token'));

        $token = bin2hex(random_bytes(32));
        $this->userRepository->updateRememberToken($user['id'], $token);

        $found = $this->userRepository->findByRememberToken($token);
        $this->assertNotNull($found);
        $this->assertSame($user['id'], $found['id']);
    }

    public function testUpdateProfile(): void
    {
        $reg = $this->authService->register('Original Name', 'orig@spacex.com', 'password123', 'password123');
        $user = $reg['user'];

        // Login to populate session
        $this->authService->login('orig@spacex.com', 'password123');

        // Update name and email without password change
        $updateRes = $this->authService->updateProfile($user['id'], 'New Name', 'newemail@spacex.com', '');
        $this->assertTrue($updateRes['success']);
        $this->assertSame('New Name', $updateRes['user']['name']);
        $this->assertSame('newemail@spacex.com', $updateRes['user']['email']);

        // Password remains valid
        $reLogin = $this->authService->login('newemail@spacex.com', 'password123');
        $this->assertTrue($reLogin['success']);

        // Update password
        $passUpdate = $this->authService->updateProfile($user['id'], 'New Name', 'newemail@spacex.com', 'newsecretpass');
        $this->assertTrue($passUpdate['success']);

        $loginNewPass = $this->authService->login('newemail@spacex.com', 'newsecretpass');
        $this->assertTrue($loginNewPass['success']);
    }

    public function testGravatarUrl(): void
    {
        $url = $this->authService->gravatar('Test.User@example.com', 128);
        $expectedHash = md5('test.user@example.com');
        $this->assertSame("https://www.gravatar.com/avatar/{$expectedHash}?s=128&d=404", $url);
    }

    public function testFlashToastLifecycle(): void
    {
        $this->authService->setFlashToast('success', 'Test Title', 'Test Message');

        $toast = $this->authService->getFlashToast();
        $this->assertNotNull($toast);
        $this->assertSame('success', $toast['type']);
        $this->assertSame('Test Title', $toast['title']);
        $this->assertSame('Test Message', $toast['message']);

        // Second call should return null (flash consumed)
        $this->assertNull($this->authService->getFlashToast());
    }
}
