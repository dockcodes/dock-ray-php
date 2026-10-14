<?php

declare(strict_types=1);

use Dock\Ray\Inventory\ComposerInventory;
use Dock\Ray\Inventory\InventoryReporter;
use Dock\Ray\Options;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class InventoryTest extends TestCase
{
    private string $root;

    private string $state;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/dockray-inventory-test-'.bin2hex(random_bytes(8));
        $this->state = $this->root.'/state';
        mkdir($this->root.'/vendor/composer', 0700, true);
        file_put_contents($this->root.'/composer.json', json_encode(['name' => 'private/application']));
        $this->metadata('v7.3.3');
    }

    private function metadata(string $version): void
    {
        file_put_contents($this->root.'/composer.lock', json_encode([
            'packages' => [['name' => 'symfony/http-foundation'], ['name' => 'company/private'], ['name' => 'private/application']],
            'packages-dev' => [['name' => 'phpunit/phpunit']],
        ]));
        file_put_contents($this->root.'/vendor/composer/installed.json', json_encode(['packages' => [
            ['name' => 'symfony/http-foundation', 'version' => $version, 'install-path' => '/secret/path', 'dist' => ['url' => 'https://user:secret@example.com']],
            ['name' => 'company/private', 'version' => '1.0.0', 'description' => 'secret project'],
            ['name' => 'private/application', 'version' => '1.0.0'],
            ['name' => 'phpunit/phpunit', 'version' => '13.0.0'],
        ]]));
    }

    private function options(array $extra = []): Options
    {
        return new Options(array_merge([
            'token' => 'project-token', 'private_key' => 'top-secret-key',
            'inventory_enabled' => true, 'inventory_site_url' => 'https://app.example.com',
            'inventory_exclude_packages' => ['company/*'],
        ], $extra));
    }

    private function reporter(MockHttpClient $http, array $extra = []): InventoryReporter
    {
        return new InventoryReporter($this->options($extra), $this->root, $this->state, 'php', $http);
    }

    protected function tearDown(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    public function test_only_installed_production_names_and_versions_leave_the_application(): void
    {
        $payload = (new ComposerInventory($this->root, ['company/*']))->collect('https://app.example.com');
        $this->assertSame([['type' => 'package', 'slug' => 'symfony/http-foundation', 'version' => '7.3.3', 'active' => true]], $payload['components']);
        $json = json_encode($payload);
        foreach (['secret', 'private/', 'phpunit', 'install-path', 'dist', 'description'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json);
        }
        $this->assertSame(PHP_VERSION, $payload['php_version']);
    }

    public function test_drupal_uses_installed_core_version_and_keeps_production_packages_only(): void
    {
        file_put_contents($this->root.'/composer.lock', json_encode([
            'packages' => [['name' => 'drupal/core'], ['name' => 'drupal/token']],
            'packages-dev' => [['name' => 'drupal/devel']],
        ]));
        file_put_contents($this->root.'/vendor/composer/installed.json', json_encode(['packages' => [
            ['name' => 'drupal/core', 'version' => 'v10.4.0'],
            ['name' => 'drupal/token', 'version' => '1.14.0'],
            ['name' => 'drupal/devel', 'version' => '5.0.0'],
        ]]));
        $payload = (new ComposerInventory($this->root))->collect('https://app.example.com', 'drupal');
        $this->assertSame('drupal', $payload['platform']);
        $this->assertSame('10.4.0', $payload['platform_version']);
        $this->assertSame(['drupal/core', 'drupal/token'], array_column($payload['components'], 'slug'));
        $payload = (new ComposerInventory($this->root, ['drupal/core']))->collect('https://app.example.com', 'drupal');
        $this->assertNull($payload['platform_version']);
        $this->assertSame(['drupal/token'], array_column($payload['components'], 'slug'));
    }

    public function test_disabled_and_missing_credentials_do_not_send_or_create_state(): void
    {
        $http = new MockHttpClient(function () {
            $this->fail('Unexpected HTTP request.');
        });
        $this->assertSame('disabled', $this->reporter($http, ['inventory_enabled' => false])->report(true)['status']);
        $this->assertSame('not_configured', $this->reporter($http, ['private_key' => null])->report()['status']);
        $this->assertDirectoryDoesNotExist($this->state);
    }

    public function test_insecure_transport_and_sensitive_site_urls_are_rejected(): void
    {
        $http = new MockHttpClient(function () {
            $this->fail('Unexpected HTTP request.');
        });
        $this->assertSame('invalid_configuration', $this->reporter($http, ['url' => 'http://dockray.example.com'])->report()['status']);
        foreach (['https://app.example.com/?token=secret', 'https://user:secret@app.example.com', 'https://app.example.com/#secret'] as $url) {
            $this->assertSame('failed', $this->reporter($http, ['inventory_site_url' => $url])->report()['status']);
        }
    }

    public function test_https_bearer_without_redirects_and_periodic_or_changed_reports(): void
    {
        $calls = 0;
        $http = new MockHttpClient(function ($method, $url, $options) use (&$calls) {
            $calls++;
            $this->assertSame('POST', $method);
            $this->assertSame('https://dockray.io/api/v1/project-token/inventory', $url);
            $this->assertStringNotContainsString('top-secret-key', $url);
            $this->assertSame(0, $options['max_redirects']);
            $this->assertTrue($options['verify_peer']);
            $this->assertTrue($options['verify_host']);
            $this->assertSame('Authorization: Bearer top-secret-key', $options['normalized_headers']['authorization'][0]);

            return new MockResponse('{"success":true,"next_report_in":43200,"vulnerable":1}');
        });
        $reporter = $this->reporter($http);
        $this->assertSame('sent', $reporter->report()['status']);
        $this->assertSame('not_due', $reporter->report()['status']);
        $this->metadata('7.3.4');
        $this->assertSame('sent', $reporter->report()['status']);
        $this->assertSame(2, $calls);
        foreach (glob($this->state.'/*.json') as $file) {
            $this->assertStringNotContainsString('top-secret-key', file_get_contents($file));
            $this->assertStringNotContainsString('symfony', file_get_contents($file));
            $this->assertSame(0600, fileperms($file) & 0777);
        }
        $this->assertSame(0700, fileperms($this->state) & 0777);
    }

    public function test_rate_limit_is_respected_even_for_forced_and_changed_reports(): void
    {
        $calls = 0;
        $http = new MockHttpClient(function () use (&$calls) {
            $calls++;

            return new MockResponse('secret response body', ['http_code' => 429, 'response_headers' => ['Retry-After: 7200']]);
        });
        $reporter = $this->reporter($http);
        $this->assertSame(['status' => 'failed', 'http_status' => 429], $reporter->report());
        $this->metadata('7.3.4');
        $this->assertSame('backoff', $reporter->report(true)['status']);
        $this->assertSame(1, $calls);
    }

    public function test_parallel_reports_are_locked_and_site_environments_have_separate_state(): void
    {
        $http = new MockHttpClient(function () {
            return new MockResponse('{"success":true}');
        });
        $nested = $this->reporter($http);
        $outer = $this->reporter(new MockHttpClient(function () use ($nested) {
            $this->assertSame('busy', $nested->report(true)['status']);

            return new MockResponse('{"success":true}');
        }));
        $this->assertSame('sent', $outer->report()['status']);
        $this->assertSame('sent', $this->reporter($http, ['inventory_site_url' => 'https://stage.example.com'])->report()['status']);
        $this->assertCount(2, glob($this->state.'/*.json'));
    }

    public function test_missing_metadata_does_not_send_an_empty_snapshot(): void
    {
        unlink($this->root.'/composer.lock');
        $http = new MockHttpClient(function () {
            $this->fail('Unexpected HTTP request.');
        });
        $this->assertSame('failed', $this->reporter($http)->report()['status']);
    }

    public function test_an_oversized_response_is_rejected_and_backed_off(): void
    {
        $reporter = $this->reporter(new MockHttpClient(new MockResponse(str_repeat('x', 65537))));
        $this->assertSame('failed', $reporter->report()['status']);
        $this->assertSame('backoff', $reporter->report()['status']);
    }
}
