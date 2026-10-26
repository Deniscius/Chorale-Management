<?php

namespace Tests\Unit;

use Illuminate\Database\ConfigurationUrlParser;
use Illuminate\Database\Connectors\PostgresConnector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class RenderNeonConfigurationTest extends TestCase
{
    public function test_laravel_uses_neon_cli_variables_without_overriding_an_explicit_render_url(): void
    {
        $root = dirname(__DIR__, 2);
        $code = 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $config = require "config/database.php"; echo $config["connections"]["pgsql"]["url"] ?? "";';
        $pooled = 'postgresql://ep-test-pooler.neon.tech/test';
        $direct = 'postgresql://ep-test.neon.tech/test';
        $explicit = 'postgresql://explicit-host.test/test';
        foreach ([
            [$explicit, $direct, $pooled, $explicit],
            ['', $direct, $pooled, $direct],
            ['', '', $pooled, $pooled],
            ['', '', '', ''],
        ] as [$render, $unpooled, $url, $expected]) {
            $process = new Process([PHP_BINARY, '-r', $code], $root, [
                'DB_URL' => $render, 'DATABASE_URL_UNPOOLED' => $unpooled, 'DATABASE_URL' => $url,
            ]);
            $process->mustRun();
            $this->assertSame($expected, $process->getOutput());
        }
    }

    public function test_blueprints_retain_laravel_services_without_managing_a_database(): void
    {
        foreach (['render.yaml' => ['chorale-management'], 'deploy/render.production.yaml' => ['chorale-web', 'chorale-worker', 'chorale-scheduler']] as $path => $names) {
            $contents = file_get_contents(dirname(__DIR__, 2).'/'.$path);
            $blueprint = Yaml::parse($contents);
            $this->assertArrayNotHasKey('databases', $blueprint);
            $this->assertStringNotContainsString('fromDatabase', $contents);
            $this->assertSame($names, array_column($blueprint['services'], 'name'));
            $web = array_column($blueprint['services'][0]['envVars'], null, 'key');
            $this->assertSame(['key' => 'DB_URL', 'sync' => false], $web['DB_URL']);
            foreach (array_slice($blueprint['services'], 1) as $service) {
                $variables = array_column($service['envVars'], null, 'key');
                foreach (['DB_URL', 'APP_KEY'] as $key) {
                    $this->assertSame(['type' => 'web', 'name' => 'chorale-web', 'envVarKey' => $key], $variables[$key]['fromService']);
                }
            }
            $variables = isset($blueprint['envVarGroups'])
                ? array_column($blueprint['envVarGroups'][0]['envVars'], null, 'key') : $web;
            $this->assertSame('pgsql', $variables['DB_CONNECTION']['value']);
            $this->assertSame('verify-full', $variables['DB_SSLMODE']['value']);
            $this->assertSame('/etc/ssl/certs/ca-certificates.crt', $variables['DB_SSLROOTCERT']['value']);
            $this->assertArrayNotHasKey('generateValue', $web['APP_KEY']);
            $this->assertArrayNotHasKey('APP_KEY', array_column($blueprint['envVarGroups'][0]['envVars'] ?? [], null, 'key'));
        }
    }

    public function test_neon_url_decodes_credentials_and_preserves_certificate_verification(): void
    {
        $config = (new ConfigurationUrlParser)->parseConfiguration([
            'driver' => 'pgsql', 'host' => 'obsolete-render-host', 'database' => 'obsolete',
            'sslmode' => 'prefer', 'sslrootcert' => '/etc/ssl/certs/ca-certificates.crt',
            'url' => 'postgresql://test_user:test%40only%3Avalue@ep-test.eu-central-1.aws.neon.tech:5432/choirs?sslmode=verify-full',
        ]);
        $this->assertSame('ep-test.eu-central-1.aws.neon.tech', $config['host']);
        $this->assertSame('choirs', $config['database']);
        $this->assertSame('test@only:value', $config['password']);
        $connector = new class extends PostgresConnector
        {
            public function dsn(array $config): string
            {
                return $this->getDsn($config);
            }
        };
        $dsn = $connector->dsn($config);
        $this->assertStringContainsString('sslmode=verify-full', $dsn);
        $this->assertStringContainsString('sslrootcert=/etc/ssl/certs/ca-certificates.crt', $dsn);
        $this->assertStringNotContainsString($config['password'], $dsn);
    }

    public function test_release_migrates_without_seeding_and_can_skip_all_database_writes(): void
    {
        $directory = sys_get_temp_dir().'/chorale-release-'.bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        try {
            // Substitute only the container working directory; execute the actual release shell.
            $script = str_replace('cd /var/www/html', 'cd '.escapeshellarg($directory), file_get_contents(dirname(__DIR__, 2).'/docker/release.sh'));
            file_put_contents($directory.'/release.sh', $script);
            file_put_contents($directory.'/php', "#!/bin/sh\nprintf '%s\\n' \"\$*\" >> \"\$RELEASE_TRACE\"\nprintf '%s' \"\$APP_KEY\" | sha256sum > \"\$RELEASE_KEY_HASH\"\n");
            chmod($directory.'/php', 0700);
            $key = 'base64:'.base64_encode(str_repeat('k', 32));
            $environment = ['PATH' => $directory.':'.getenv('PATH'), 'APP_KEY' => $key,
                'RELEASE_TRACE' => $directory.'/calls', 'RELEASE_KEY_HASH' => $directory.'/key'];
            $process = new Process(['sh', $directory.'/release.sh'], $directory, $environment + ['RUN_MIGRATIONS_ON_RELEASE' => 'true']);
            $process->mustRun();
            $this->assertSame("artisan migrate --force --no-interaction\n", file_get_contents($directory.'/calls'));
            $this->assertStringStartsWith(hash('sha256', $key), file_get_contents($directory.'/key'));
            unlink($directory.'/calls');
            foreach (['false' => 0, 'invalid' => 1] as $flag => $exit) {
                $process = new Process(['sh', $directory.'/release.sh'], $directory, $environment + ['RUN_MIGRATIONS_ON_RELEASE' => $flag]);
                $this->assertSame($exit, $process->run());
                $this->assertFileDoesNotExist($directory.'/calls');
            }
            $process = new Process(['sh', $directory.'/release.sh'], $directory, array_replace($environment, ['APP_KEY' => false]));
            $this->assertSame(1, $process->run());
            $this->assertFileDoesNotExist($directory.'/calls');
        } finally {
            foreach (glob($directory.'/*') as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
