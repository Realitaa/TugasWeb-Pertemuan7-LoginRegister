<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Realitaa\PhpVite\Auth\AuthService;

$auth = new AuthService();
$auth->logout();

// Start fresh session to pass signout toast message
$auth->initSession();
$auth->setFlashToast('info', 'Signed Out', 'You have been safely signed out.');

header('Location: index.php');
exit;

