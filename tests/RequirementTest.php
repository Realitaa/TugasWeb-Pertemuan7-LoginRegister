<?php

declare(strict_types=1);

namespace Realitaa\PhpVite\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Test suite verifying core authentication, registration, security,
 * session management, cookies, and profile management requirements.
 */
class RequirementTest extends TestCase
{
    private static $serverProcess = null;
    private static int $serverPort = 8097;
    private static string $baseUrl = '';
    private static string $usersFile = '';
    private static ?string $usersBackup = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $rootDir = realpath(__DIR__ . '/..');
        self::$usersFile = $rootDir . '/data/users.json';

        // Backup existing users.json if present
        if (file_exists(self::$usersFile)) {
            self::$usersBackup = (string)file_get_contents(self::$usersFile);
        } else {
            if (!is_dir(dirname(self::$usersFile))) {
                mkdir(dirname(self::$usersFile), 0755, true);
            }
            file_put_contents(self::$usersFile, json_encode([], JSON_PRETTY_PRINT));
        }

        // Start a dedicated test HTTP server on an ephemeral port
        self::$serverPort = 8090 + random_int(1, 9);
        self::$baseUrl = 'http://127.0.0.1:' . self::$serverPort;

        $cmd = [
            'php',
            '-S',
            '127.0.0.1:' . self::$serverPort,
            '-t',
            $rootDir,
        ];

        self::$serverProcess = proc_open($cmd, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        // Wait for server to start responding
        $started = false;
        for ($i = 0; $i < 40; $i++) {
            usleep(50000); // 50ms
            $fp = @fsockopen('127.0.0.1', self::$serverPort, $errno, $errstr, 0.2);
            if ($fp) {
                fclose($fp);
                $started = true;
                break;
            }
        }

        if (!$started) {
            self::tearDownAfterClass();
            throw new \RuntimeException('Failed to start test HTTP server on port ' . self::$serverPort);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$serverProcess !== null) {
            proc_terminate(self::$serverProcess);
            self::$serverProcess = null;
        }

        // Restore original users.json
        if (self::$usersBackup !== null) {
            file_put_contents(self::$usersFile, self::$usersBackup);
        }

        parent::tearDownAfterClass();
    }

    /**
     * Send an HTTP request to the test server.
     */
    private function request(
        string $method,
        string $path,
        array $postFields = [],
        array $cookies = [],
        bool $followRedirects = false
    ): array {
        $url = self::$baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $followRedirects);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
        }

        if (!empty($cookies)) {
            $cookiePairs = [];
            foreach ($cookies as $k => $v) {
                $cookiePairs[] = $k . '=' . urlencode((string)$v);
            }
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $cookiePairs));
        }

        $rawResponse = (string)curl_exec($ch);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $rawHeaders = substr($rawResponse, 0, $headerSize);
        $body = substr($rawResponse, $headerSize);

        // Parse response headers and Set-Cookie
        $headers = [];
        $setCookies = [];
        $location = null;

        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (stripos($line, 'Location:') === 0) {
                $location = trim(substr($line, 9));
            } elseif (stripos($line, 'Set-Cookie:') === 0) {
                $cookieStr = trim(substr($line, 11));
                $parts = explode(';', $cookieStr);
                $firstPart = explode('=', trim($parts[0]), 2);
                $cookieName = $firstPart[0];
                $cookieVal = $firstPart[1] ?? '';

                $isExpired = false;
                if (stripos($cookieStr, 'expires=') !== false || stripos($cookieStr, 'Max-Age=0') !== false || stripos($cookieStr, 'deleted') !== false) {
                    $isExpired = true;
                }

                $setCookies[$cookieName] = [
                    'value' => urldecode($cookieVal),
                    'raw' => $cookieStr,
                    'expired' => $isExpired,
                ];
            } elseif (strpos($line, ':') !== false) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
        }

        return [
            'status' => $httpCode,
            'headers' => $headers,
            'set_cookies' => $setCookies,
            'location' => $location,
            'body' => $body,
        ];
    }

    /**
     * Helper to register a test user.
     */
    private function registerUser(string $name, string $email, string $password): array
    {
        return $this->request('POST', 'register.php', [
            'action' => 'register',
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
        ]);
    }

    /**
     * Helper to log in a test user.
     */
    private function loginUser(string $email, string $password, bool $remember = false): array
    {
        $post = [
            'action' => 'login',
            'email' => $email,
            'password' => $password,
        ];
        if ($remember) {
            $post['remember'] = '1';
        }
        return $this->request('POST', 'index.php', $post);
    }

    /**
     * 1. Berhasil menampilkan halaman form registrasi, registrasi berhasil dilakukan
     *    menggunakan nama, email, password, dan konfirmasi password.
     */
    public function testRegistrationPageDisplaysAndSubmitsSuccessfully(): void
    {
        // GET Form Page
        $getRes = $this->request('GET', 'register.php');
        $this->assertSame(200, $getRes['status']);
        $this->assertStringContainsString('name="name"', $getRes['body']);
        $this->assertStringContainsString('name="email"', $getRes['body']);
        $this->assertStringContainsString('name="password"', $getRes['body']);
        $this->assertStringContainsString('name="password_confirmation"', $getRes['body']);

        // POST Valid Registration
        $email = 'req1_' . bin2hex(random_bytes(4)) . '@spacex.test';
        $postRes = $this->registerUser('Neil Armstrong', $email, 'Apollo1969!');

        $this->assertSame(302, $postRes['status']);
        $this->assertSame('index.php', $postRes['location']);

        // Verify user exists in users.json
        $users = json_decode((string)file_get_contents(self::$usersFile), true) ?: [];
        $registered = null;
        foreach ($users as $u) {
            if (($u['email'] ?? '') === $email) {
                $registered = $u;
                break;
            }
        }

        $this->assertNotNull($registered, 'Registered user should be saved in users.json');
        $this->assertSame('Neil Armstrong', $registered['name']);
        $this->assertSame($email, $registered['email']);
    }

    /**
     * 2. Pendaftaran memvalidasi email dengan filter_var() (trigger dengan format email yang salah).
     */
    public function testRegistrationValidatesEmailWithFilterVar(): void
    {
        $invalidEmails = [
            'plainaddress',
            'missing-at-sign.com',
            '@no-local-part.com',
            'user@.invalid.com',
        ];

        foreach ($invalidEmails as $badEmail) {
            $res = $this->request('POST', 'register.php', [
                'action' => 'register',
                'name' => 'Invalid Email Tester',
                'email' => $badEmail,
                'password' => 'ValidPass123!',
                'password_confirmation' => 'ValidPass123!',
            ]);

            // Must NOT redirect on validation error
            $this->assertSame(200, $res['status'], "Registration should fail for invalid email: $badEmail");
            $this->assertStringContainsString('valid email address', $res['body']);

            // Verify user was NOT saved to users.json
            $users = json_decode((string)file_get_contents(self::$usersFile), true) ?: [];
            foreach ($users as $u) {
                $this->assertNotSame($badEmail, $u['email'] ?? '');
            }
        }
    }

    /**
     * 3. Test apakah pengguna yang terdaftar passwordnya di hash.
     */
    public function testRegisteredUserPasswordIsHashed(): void
    {
        $email = 'req3_' . bin2hex(random_bytes(4)) . '@spacex.test';
        $plainPass = 'SuperSecretPass123!';
        $this->registerUser('Buzz Aldrin', $email, $plainPass);

        $users = json_decode((string)file_get_contents(self::$usersFile), true) ?: [];
        $user = null;
        foreach ($users as $u) {
            if (($u['email'] ?? '') === $email) {
                $user = $u;
                break;
            }
        }

        $this->assertNotNull($user);
        $hashed = $user['password'] ?? '';

        // Must never store plaintext password
        $this->assertNotSame($plainPass, $hashed);

        // Must verify against password_verify
        $this->assertTrue(password_verify($plainPass, $hashed));

        // Must be a valid PHP password hash algorithm
        $info = password_get_info($hashed);
        $this->assertNotNull($info['algo']);
        $this->assertNotSame(0, $info['algo']);
    }

    /**
     * 4. Test apakah data terdaftar disimpan ke JSON.
     */
    public function testRegisteredDataIsSavedToJson(): void
    {
        $email = 'req4_' . bin2hex(random_bytes(4)) . '@spacex.test';
        $this->registerUser('Michael Collins', $email, 'LunarOrbit1969!');

        $this->assertFileExists(self::$usersFile);
        $raw = file_get_contents(self::$usersFile);
        $this->assertIsString($raw);

        $users = json_decode($raw, true);
        $this->assertIsArray($users);

        $found = false;
        foreach ($users as $u) {
            if (($u['email'] ?? '') === $email) {
                $this->assertArrayHasKey('id', $u);
                $this->assertArrayHasKey('name', $u);
                $this->assertArrayHasKey('email', $u);
                $this->assertArrayHasKey('password', $u);
                $this->assertArrayHasKey('created_at', $u);
                $this->assertSame('Michael Collins', $u['name']);
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'User record must exist in data/users.json');
    }

    /**
     * 5. Test kegagalan pendaftaran akibat duplikasi email.
     */
    public function testRegistrationFailsOnDuplicateEmail(): void
    {
        $email = 'req5_' . bin2hex(random_bytes(4)) . '@spacex.test';
        $this->registerUser('Yuri Gagarin', $email, 'Vostok1961!');

        // Try registering again with the same email in uppercase
        $res = $this->request('POST', 'register.php', [
            'action' => 'register',
            'name' => 'Imposter Yuri',
            'email' => strtoupper($email),
            'password' => 'AnotherPassword123!',
            'password_confirmation' => 'AnotherPassword123!',
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('already exists', $res['body']);

        // Verify only 1 entry exists for that email in users.json
        $users = json_decode((string)file_get_contents(self::$usersFile), true) ?: [];
        $count = 0;
        foreach ($users as $u) {
            if (strtolower($u['email'] ?? '') === strtolower($email)) {
                $count++;
            }
        }

        $this->assertSame(1, $count, 'There must only be exactly one entry for duplicate email');
    }

    /**
     * 6. Test ketersediaan session login.
     */
    public function testLoginSessionAvailability(): void
    {
        $email = 'req6_' . bin2hex(random_bytes(4)) . '@spacex.test';
        $password = 'Redstone1961!';
        $this->registerUser('Alan Shepard', $email, $password);

        // POST to login
        $loginRes = $this->loginUser($email, $password);

        $this->assertSame(302, $loginRes['status']);
        $this->assertSame('dashboard.php', $loginRes['location']);
        $this->assertArrayHasKey('PHPSESSID', $loginRes['set_cookies']);

        $sessionId = $loginRes['set_cookies']['PHPSESSID']['value'];

        // Access dashboard using the authenticated session cookie
        $dashRes = $this->request('GET', 'dashboard.php', [], [
            'PHPSESSID' => $sessionId,
        ]);

        $this->assertSame(200, $dashRes['status']);
        $this->assertStringContainsString('Alan Shepard', $dashRes['body']);
        $this->assertStringContainsString('Signed in as', $dashRes['body']);
    }

    /**
     * 7. Test protected dashboard (redirect ke login).
     */
    public function testProtectedDashboardRedirectsToLogin(): void
    {
        // Unauthenticated request without session
        $res = $this->request('GET', 'dashboard.php');

        $this->assertSame(302, $res['status']);
        $this->assertSame('index.php', $res['location']);
    }

    /**
     * 8. Cek apakah fungsi dan cookie di hancurkan ketika logout.
     */
    public function testLogoutDestroysSessionAndCookies(): void
    {
        $email = 'req8_' . bin2hex(random_bytes(4)) . '@spacex.test';
        $password = 'Friendship7!';
        $this->registerUser('John Glenn', $email, $password);

        // 1. Login with remember-me
        $loginRes = $this->loginUser($email, $password, true);

        $sessionId = $loginRes['set_cookies']['PHPSESSID']['value'] ?? '';
        $rememberToken = $loginRes['set_cookies']['spacex_remember']['value'] ?? '';

        $this->assertNotEmpty($sessionId);
        $this->assertNotEmpty($rememberToken);

        // 2. Perform logout
        $logoutRes = $this->request('GET', 'logout.php', [], [
            'PHPSESSID' => $sessionId,
            'spacex_remember' => $rememberToken,
        ]);

        $this->assertSame(302, $logoutRes['status']);
        $this->assertSame('index.php', $logoutRes['location']);

        // Verify remember-me cookie is cleared/expired
        $this->assertArrayHasKey('spacex_remember', $logoutRes['set_cookies']);
        $this->assertTrue(
            $logoutRes['set_cookies']['spacex_remember']['expired'] ||
            empty($logoutRes['set_cookies']['spacex_remember']['value']),
            'spacex_remember cookie should be invalidated on logout'
        );

        // 3. Attempt to access dashboard with the old session ID
        $dashRes = $this->request('GET', 'dashboard.php', [], [
            'PHPSESSID' => $sessionId,
        ]);

        // Must be redirected to index.php because session was destroyed
        $this->assertSame(302, $dashRes['status']);
        $this->assertSame('index.php', $dashRes['location']);
    }

    /**
     * 9. Test apakah toast bekerja dengan benar ketika autentikasi.
     */
    public function testToastBehaviorOnAuthentication(): void
    {
        // Failed login trigger
        $badLoginRes = $this->request('POST', 'index.php', [
            'action' => 'login',
            'email' => 'nonexistent@spacex.test',
            'password' => 'WrongPassword!',
        ]);

        $this->assertSame(200, $badLoginRes['status']);
        $this->assertStringContainsString('id="flash-toast-data"', $badLoginRes['body']);
        $this->assertStringContainsString('Authentication Failed', $badLoginRes['body']);

        // Successful login flash toast check
        $email = 'req9_' . bin2hex(random_bytes(4)) . '@spacex.test';
        $password = 'Vostok1963!';
        $this->registerUser('Valentina Tereshkova', $email, $password);

        $goodLoginRes = $this->loginUser($email, $password);
        $sessId = $goodLoginRes['set_cookies']['PHPSESSID']['value'] ?? '';
        $dashRes = $this->request('GET', 'dashboard.php', [], [
            'PHPSESSID' => $sessId,
        ]);

        $this->assertSame(200, $dashRes['status']);
        $this->assertStringContainsString('id="flash-toast-data"', $dashRes['body']);
        $this->assertStringContainsString('Welcome Back!', $dashRes['body']);
    }

    /**
     * 10. Tes apakah remember me berperilaku dengan benar.
     */
    public function testRememberMeBehavior(): void
    {
        $email = 'req10_' . bin2hex(random_bytes(4)) . '@spacex.test';
        $password = 'Gemini1965!';
        $this->registerUser('Ed White', $email, $password);

        // Login with remember-me checkbox enabled
        $loginRes = $this->loginUser($email, $password, true);

        $this->assertArrayHasKey('spacex_remember', $loginRes['set_cookies']);
        $cookieToken = $loginRes['set_cookies']['spacex_remember']['value'];
        $this->assertNotEmpty($cookieToken);

        // Verify token saved in users.json
        $users = json_decode((string)file_get_contents(self::$usersFile), true) ?: [];
        $savedToken = null;
        foreach ($users as $u) {
            if (($u['email'] ?? '') === $email) {
                $savedToken = $u['remember_token'] ?? null;
                break;
            }
        }
        $this->assertSame($cookieToken, $savedToken);

        // Access dashboard without PHPSESSID, using ONLY spacex_remember cookie
        $dashRes = $this->request('GET', 'dashboard.php', [], [
            'spacex_remember' => $cookieToken,
        ]);

        $this->assertSame(200, $dashRes['status']);
        $this->assertStringContainsString('Ed White', $dashRes['body']);
    }

    /**
     * 11. Tes apakah edit profile berhasil dilakukan (yang dapat di edit adalah name, email
     *     dan password; jika password dikosongkan maka tidak diubah).
     */
    public function testEditProfileSuccessAndOptionalPassword(): void
    {
        $email = 'req11_' . bin2hex(random_bytes(4)) . '@spacex.test';
        $origPass = 'Challenger1983!';
        $this->registerUser('Sally Ride', $email, $origPass);

        // 1. Establish login session
        $loginRes = $this->loginUser($email, $origPass);
        $sessId = $loginRes['set_cookies']['PHPSESSID']['value'] ?? '';

        // 2. Case A: Update name and email, leave password empty
        $usersBefore = json_decode((string)file_get_contents(self::$usersFile), true) ?: [];
        $hashBefore = null;
        foreach ($usersBefore as $u) {
            if (($u['email'] ?? '') === $email) {
                $hashBefore = $u['password'];
                break;
            }
        }
        $this->assertNotNull($hashBefore);

        $newEmailA = 'sally.ride.updated@spacex.test';
        $updateResA = $this->request('POST', 'dashboard.php', [
            'action' => 'update_profile',
            'name' => 'Dr. Sally Ride',
            'email' => $newEmailA,
            'password' => '', // Empty password must NOT overwrite hash
        ], [
            'PHPSESSID' => $sessId,
        ]);

        $this->assertSame(302, $updateResA['status']);
        $this->assertSame('dashboard.php', $updateResA['location']);

        // Check updated fields in users.json
        $usersAfterA = json_decode((string)file_get_contents(self::$usersFile), true) ?: [];
        $userA = null;
        foreach ($usersAfterA as $u) {
            if (($u['email'] ?? '') === $newEmailA) {
                $userA = $u;
                break;
            }
        }
        $this->assertNotNull($userA);
        $this->assertSame('Dr. Sally Ride', $userA['name']);
        $this->assertSame($newEmailA, $userA['email']);
        $this->assertSame($hashBefore, $userA['password'], 'Password hash must remain untouched when left empty');
        $this->assertTrue(password_verify($origPass, $userA['password']));

        // 3. Case B: Update password with a new value
        $newPass = 'NewSTS7Mission2026!';
        $updateResB = $this->request('POST', 'dashboard.php', [
            'action' => 'update_profile',
            'name' => 'Dr. Sally Ride',
            'email' => $newEmailA,
            'password' => $newPass,
        ], [
            'PHPSESSID' => $sessId,
        ]);

        $this->assertSame(302, $updateResB['status']);
        $this->assertSame('dashboard.php', $updateResB['location']);

        $usersAfterB = json_decode((string)file_get_contents(self::$usersFile), true) ?: [];
        $userB = null;
        foreach ($usersAfterB as $u) {
            if (($u['email'] ?? '') === $newEmailA) {
                $userB = $u;
                break;
            }
        }
        $this->assertNotNull($userB);
        $this->assertNotSame($hashBefore, $userB['password'], 'Password hash must change when a new password is provided');
        $this->assertTrue(password_verify($newPass, $userB['password']));
        $this->assertFalse(password_verify($origPass, $userB['password']), 'Old password must no longer verify');
    }
}
