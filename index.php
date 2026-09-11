<?php

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($_ENV['APP_NAME'] ?? 'Vite + Plain PHP', ENT_QUOTES, 'UTF-8') ?></title>
    <?= vite()->tags('src/main.js') ?>
  </head>
  <body>
    <div id="app"></div>
  </body>
</html>
