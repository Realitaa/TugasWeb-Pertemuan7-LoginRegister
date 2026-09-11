<?php

declare(strict_types=1);

namespace Realitaa\PhpVite\Vite;

use Spatie\Fork\Fork;

class DevServer extends Fork
{
    private string $hotFile;

    public function __construct(?string $hotFile = null)
    {
        parent::__construct();

        $root = dirname(__DIR__, 2);
        $this->hotFile = $hotFile ?? ($root . '/storage/vite.hot');
    }

    public static function start(?string $hotFile = null): void
    {
        $server = new self($hotFile);
        $server->serve();
    }

    public function serve(): void
    {
        $root = dirname(__DIR__, 2);
        $this->cleanup();

        $packageManager = $this->detectPackageManager($root);

        echo "\033[1;32m[PHP+Vite]\033[0m Starting Vite and PHP dev servers concurrently...\n";
        echo "\033[1;34m[PHP]\033[0m http://localhost:8000\n";
        echo "\033[1;35m[Vite]\033[0m http://localhost:5173\n\n";

        try {
            $this->run(
                function () use ($packageManager) {
                    passthru("{$packageManager} run dev");
                },
                function () {
                    passthru('php -S localhost:8000');
                }
            );
        } finally {
            $this->cleanup();
        }
    }

    protected function finishTask(\Spatie\Fork\Task $task): mixed
    {
        $output = parent::finishTask($task);

        if ($this->isRunning()) {
            $this->exit();
        }

        return $output;
    }

    protected function exit(): void
    {
        $this->cleanup();

        if (extension_loaded('posix')) {
            foreach ($this->runningTasks as $task) {
                @posix_kill($task->pid(), SIGTERM);
            }
        }

        exit(0);
    }

    private function cleanup(): void
    {
        if (file_exists($this->hotFile)) {
            @unlink($this->hotFile);
        }
    }

    private function detectPackageManager(string $root): string
    {
        if (file_exists($root . '/pnpm-lock.yaml') || (exec('which pnpm 2>/dev/null') !== '')) {
            return 'pnpm';
        }

        if (file_exists($root . '/yarn.lock') || (exec('which yarn 2>/dev/null') !== '')) {
            return 'yarn';
        }

        if (file_exists($root . '/bun.lockb') || file_exists($root . '/bun.lock') || (exec('which bun 2>/dev/null') !== '')) {
            return 'bun';
        }

        return 'npm';
    }
}
