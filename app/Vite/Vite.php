<?php

declare(strict_types=1);

namespace Realitaa\PhpVite\Vite;

use JsonException;
use RuntimeException;

class Vite
{
    private string $rootPath;
    private string $buildDirectory;
    private string $buildUrl;
    private string $manifestPath;
    private string $hotFile;
    private ?array $manifest = null;

    public function __construct(
        ?string $rootPath = null,
        ?string $buildDirectory = null,
        ?string $buildUrl = null,
        ?string $manifestPath = null,
        ?string $hotFile = null,
    ) {
        $this->rootPath = $rootPath ?? dirname(__DIR__, 2);
        $this->buildDirectory = $buildDirectory ?? ($this->rootPath . '/dist');
        $this->buildUrl = $buildUrl ?? '/dist';
        $this->manifestPath = $manifestPath ?? ($this->buildDirectory . '/.vite/manifest.json');
        $this->hotFile = $hotFile ?? ($this->rootPath . '/storage/vite.hot');
    }

    public function getRootPath(): string
    {
        return $this->rootPath;
    }

    public function getBuildDirectory(): string
    {
        return $this->buildDirectory;
    }

    public function getBuildUrl(): string
    {
        return $this->buildUrl;
    }

    public function getManifestPath(): string
    {
        return $this->manifestPath;
    }

    public function getHotFile(): string
    {
        return $this->hotFile;
    }

    public function isRunning(): bool
    {
        return is_file($this->hotFile);
    }

    public function tags(string $entry): string
    {
        return $this->isRunning()
            ? $this->devTags($entry)
            : $this->productionTags($entry);
    }

    public function asset(string $entry): string
    {
        return $this->isRunning()
            ? $this->devAsset($entry)
            : $this->productionAsset($entry);
    }

    public function clearManifestCache(): void
    {
        $this->manifest = null;
    }

    private function devServerUrl(): string
    {
        if (!$this->isRunning()) {
            throw new RuntimeException('Vite development server is not running.');
        }

        $content = file_get_contents($this->hotFile);
        if ($content === false) {
            throw new RuntimeException("Unable to read Vite hot file [{$this->hotFile}].");
        }

        $url = rtrim(trim($content), '/');
        if ($url === '') {
            throw new RuntimeException("Vite hot file [{$this->hotFile}] is empty.");
        }

        if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
            throw new RuntimeException("Vite hot file [{$this->hotFile}] contains an invalid URL: [{$url}].");
        }

        return $url;
    }

    private function manifest(): array
    {
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        if (!is_file($this->manifestPath)) {
            throw new RuntimeException(
                "Vite production manifest not found at [{$this->manifestPath}]. " .
                'Run "pnpm exec vite build" first.'
            );
        }

        $content = file_get_contents($this->manifestPath);
        if ($content === false) {
            throw new RuntimeException(
                "Unable to read Vite production manifest at [{$this->manifestPath}]."
            );
        }

        try {
            $manifest = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException(
                'Vite production manifest contains invalid JSON: ' . $e->getMessage(),
                0,
                $e
            );
        }

        if (!is_array($manifest)) {
            throw new RuntimeException(
                'Vite production manifest is invalid: expected JSON object/array.'
            );
        }

        $this->manifest = $manifest;

        return $this->manifest;
    }

    private function resolve(string $entry): array
    {
        $manifest = $this->manifest();

        if (!isset($manifest[$entry])) {
            throw new RuntimeException(
                "Vite entry [{$entry}] not found in manifest [{$this->manifestPath}]."
            );
        }

        $asset = $manifest[$entry];

        if (!is_array($asset)) {
            throw new RuntimeException(
                "Vite manifest entry [{$entry}] is malformed: expected array/object."
            );
        }

        if (!isset($asset['file']) || !is_string($asset['file']) || trim($asset['file']) === '') {
            throw new RuntimeException(
                "Vite manifest entry [{$entry}] does not specify a valid 'file'."
            );
        }

        if (isset($asset['css'])) {
            if (!is_array($asset['css'])) {
                throw new RuntimeException(
                    "Vite manifest entry [{$entry}] has a malformed 'css' field: expected array."
                );
            }

            foreach ($asset['css'] as $css) {
                if (!is_string($css) || trim($css) === '') {
                    throw new RuntimeException(
                        "Vite manifest entry [{$entry}] contains an invalid CSS entry."
                    );
                }
            }
        }

        return $asset;
    }

    private function assetUrl(string $path): string
    {
        return rtrim($this->buildUrl, '/') . '/' . ltrim($path, '/');
    }

    private function devAsset(string $entry): string
    {
        return $this->devServerUrl() . '/' . ltrim($entry, '/');
    }

    private function productionAsset(string $entry): string
    {
        $asset = $this->resolve($entry);

        return $this->assetUrl($asset['file']);
    }

    private function devTags(string $entry): string
    {
        $url = $this->devServerUrl();

        $client = htmlspecialchars($url . '/@vite/client', ENT_QUOTES, 'UTF-8');
        $entryUrl = htmlspecialchars($this->devAsset($entry), ENT_QUOTES, 'UTF-8');

        if ($this->isCss($entry)) {
            return <<<HTML
<script type="module" src="{$client}"></script>
<link rel="stylesheet" href="{$entryUrl}">
HTML;
        }

        return <<<HTML
<script type="module" src="{$client}"></script>
<script type="module" src="{$entryUrl}"></script>
HTML;
    }

    private function productionTags(string $entry): string
    {
        $asset = $this->resolve($entry);

        $tags = [];

        foreach ($asset['css'] ?? [] as $css) {
            $tags[] = sprintf(
                '<link rel="stylesheet" href="%s">',
                htmlspecialchars(
                    $this->assetUrl($css),
                    ENT_QUOTES,
                    'UTF-8'
                )
            );
        }

        if ($this->isCss($entry) || $this->isCss($asset['file'])) {
            $tags[] = sprintf(
                '<link rel="stylesheet" href="%s">',
                htmlspecialchars(
                    $this->productionAsset($entry),
                    ENT_QUOTES,
                    'UTF-8'
                )
            );
        } else {
            $tags[] = sprintf(
                '<script type="module" src="%s"></script>',
                htmlspecialchars(
                    $this->productionAsset($entry),
                    ENT_QUOTES,
                    'UTF-8'
                )
            );
        }

        return implode("\n", $tags);
    }

    private function isCss(string $path): bool
    {
        $cleanPath = parse_url($path, PHP_URL_PATH) ?? $path;

        return str_ends_with(strtolower($cleanPath), '.css');
    }
}
