<?php

use App\Models\Setting;
use App\Services\ChoraleIdentity;
use App\Services\PrivateFiles;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

try {
    require '/var/www/html/vendor/autoload.php';
    $app = require '/var/www/html/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    if (getenv('CI_STORAGE_TEST') !== '1' || DB::connection()->getDatabaseName() !== 'chorale_ci') {
        throw new RuntimeException('Disposable CI database only.');
    }
    app(TenantContext::class)->set(1);
    $path = 'tenants/1/chorale/ci-proof.png';
    if (($argv[1] ?? '') === 'fingerprint') {
        $response = app(PrivateFiles::class)->response($path, 'ci-proof.png', true);
        ob_start();
        $response->sendContent();
        $contents = ob_get_clean();
        echo hash('sha256', $contents)."\n";

        return;
    }
    if (($argv[1] ?? '') === 'write') {
        app(PrivateFiles::class)->persist($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='), 'image/png');
        Setting::set(ChoraleIdentity::KEY_LOGO_PATH, $path);
        if (! is_dir(storage_path('app/private'))) {
            mkdir(storage_path('app/private'), 0755, true);
        }
        file_put_contents(storage_path('app/private/ci-ephemeral-proof.txt'), 'temporary');
        if (! file_exists(storage_path('app/private/ci-ephemeral-proof.txt'))) {
            throw new RuntimeException('Ephemeral proof was not created.');
        }
    } else {
        if (! app(PrivateFiles::class)->exists($path) || file_exists(storage_path('app/private/ci-ephemeral-proof.txt'))) {
            throw new RuntimeException('Private storage persistence check failed.');
        }
    }
    echo "Private storage check passed.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Private storage check failed: '.$e->getMessage()."\n");
    exit(1);
}
