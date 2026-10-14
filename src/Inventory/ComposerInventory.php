<?php

declare(strict_types=1);

namespace Dock\Ray\Inventory;

use Dock\Ray\Client;
use RuntimeException;

/** Reads metadata only. It never executes Composer, scans source files or reads environment files. */
final class ComposerInventory
{
    private string $projectDirectory;

    private array $excluded;

    public function __construct(string $projectDirectory, array $excluded = [])
    {
        $this->projectDirectory = rtrim($projectDirectory, '/\\');
        $this->excluded = $excluded;
    }

    public function collect(string $siteUrl, string $platform = 'php'): array
    {
        if (! in_array($platform, ['php', 'laravel', 'drupal'], true)) {
            throw new RuntimeException('Unsupported inventory platform.');
        }
        $parts = parse_url($siteUrl);
        if (! is_array($parts) || ! isset($parts['host'], $parts['scheme'])
            || ! in_array($parts['scheme'], ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment']) || strlen($siteUrl) > 2048
            || preg_match('/[\x00-\x20\x7f]/', $siteUrl)) {
            throw new RuntimeException('Inventory requires a configured site URL without credentials, query or fragment.');
        }
        $installed = $this->json($this->projectDirectory.'/vendor/composer/installed.json');
        $lock = $this->json($this->projectDirectory.'/composer.lock');
        $manifest = $this->json($this->projectDirectory.'/composer.json');
        $production = [];
        foreach ($lock['packages'] ?? [] as $package) {
            if (isset($package['name'])) {
                $production[$package['name']] = true;
            }
        }
        if (! isset($lock['packages']) || ! is_array($lock['packages'])) {
            throw new RuntimeException('Production package metadata is unavailable.');
        }
        $components = [];
        $frameworkVersion = null;
        foreach ($installed['packages'] ?? $installed as $package) {
            if (! is_array($package)) {
                throw new RuntimeException('Installed package metadata is invalid.');
            }
            $name = $package['name'] ?? '';
            $version = $package['version'] ?? '';
            if (! is_string($name) || ! isset($production[$name]) || $name === ($manifest['name'] ?? null)
                || ! preg_match('#^[a-z0-9_.-]+/[a-z0-9_.-]+$#D', $name) || strlen($name) > 191) {
                continue;
            }
            foreach ($this->excluded as $pattern) {
                if (fnmatch($pattern, $name)) {
                    continue 2;
                }
            }
            if (! is_string($version) || strlen($version) > 64 || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._+\-]*$/D', $version)) {
                throw new RuntimeException('Installed package version metadata is invalid.');
            }
            $version = preg_replace('/^v(?=\d)/', '', $version);
            $components[$name] = ['type' => 'package', 'slug' => $name, 'version' => $version, 'active' => true];
            if (($platform === 'laravel' && $name === 'laravel/framework')
                || ($platform === 'drupal' && in_array($name, ['drupal/core', 'drupal/drupal'], true))) {
                $frameworkVersion = $version;
            }
        }
        if (count($components) > 1000) {
            // A partial snapshot would incorrectly remove omitted packages on the server.
            throw new RuntimeException('Inventory exceeds the component limit.');
        }
        ksort($components);

        return [
            'schema' => 1, 'platform' => $platform,
            'platform_version' => $platform === 'php' ? PHP_VERSION : $frameworkVersion,
            'php_version' => PHP_VERSION, 'agent_version' => Client::SDK_VERSION,
            'site_url' => $siteUrl, 'components' => array_values($components),
        ];
    }

    private function json(string $file): array
    {
        if (! is_file($file) || ! is_readable($file) || filesize($file) > 8 * 1024 * 1024) {
            throw new RuntimeException('Composer metadata is unavailable or too large.');
        }
        $contents = file_get_contents($file, false, null, 0, 8 * 1024 * 1024 + 1);
        if ($contents === false || strlen($contents) > 8 * 1024 * 1024) {
            throw new RuntimeException('Composer metadata could not be read.');
        }
        $data = json_decode($contents, true, 128);
        if (! is_array($data)) {
            throw new RuntimeException('Composer metadata is invalid.');
        }

        return $data;
    }
}
