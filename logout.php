<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Delete remember-me cookie
setcookie('spacex_remember', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);

// Start fresh session to pass signout toast message
session_start();
$_SESSION['flash_toast'] = [
    'type' => 'info',
    'title' => 'Signed Out',
    'message' => 'You have been safely signed out.',
];

header('Location: index.php');
exit;
