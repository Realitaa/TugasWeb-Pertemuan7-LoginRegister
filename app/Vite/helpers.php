<?php

declare(strict_types=1);

use Realitaa\PhpVite\Vite\Vite;

function vite(?Vite $instance = null): Vite
{
    static $vite = null;

    if ($instance !== null) {
        $vite = $instance;
    }

    if ($vite === null) {
        $vite = new Vite();
    }

    return $vite;
}
