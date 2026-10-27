<?php

use App\Enums\UserRole;
use App\Models\Rehearsal;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Work;
use App\Services\TenantRegistration;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;

// Create a new SQLite fixture only; never reuse or reset an existing database.
$root = dirname(__DIR__, 2);
$directory = $root.'/storage/framework/testing/accessibility';
if (! is_dir($directory) && ! mkdir($directory, 0700, true)) {
    throw new RuntimeException('Cannot create the local fixture directory.');
}
$database = $directory.'/review.sqlite';
$handle = fopen($database, 'x');
if ($handle === false) {
    throw new RuntimeException('Fixture already exists. Choose a new isolated checkout instead of resetting it.');
}
fclose($handle);
chmod($database, 0600);

$environment = [
    'APP_ENV' => 'testing',
    'APP_CONFIG_CACHE' => $directory.'/config.php',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $database,
    'DB_URL' => '',
    'DATABASE_URL' => '',
    'DATABASE_URL_UNPOOLED' => '',
    'CACHE_STORE' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'MAIL_MAILER' => 'array',
    'TENANCY_ENABLED' => 'true',
    'BCRYPT_ROUNDS' => '4',
];
foreach ($environment as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database) {
    throw new RuntimeException('Local database isolation check failed.');
}
if (Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) !== 0) {
    throw new RuntimeException('Fixture migrations failed.');
}
$password = bin2hex(random_bytes(20));
$admin = app(TenantRegistration::class)->create([
    'organization' => 'Chorale de validation', 'name' => 'Responsable de validation',
    'username' => 'review-admin', 'email' => 'review-admin@example.test', 'password' => $password,
]);
Tenant::findOrFail($admin->tenant_id)->forceFill(['paid_perpetual' => true])->save();
app(TenantContext::class)->run($admin->tenant_id, function () use ($password): void {
    User::factory()->role(UserRole::Choriste)->create([
        'username' => 'review-choriste', 'email' => 'review-choriste@example.test', 'password' => $password,
    ]);
    Work::factory()->create(['title' => 'Chant de validation', 'arranger' => null, 'notes' => null]);
    Rehearsal::factory()->upcoming()->create(['venue' => 'Salle de validation', 'notes' => null]);
});
$credentials = $directory.'/credentials.json';
file_put_contents($credentials, json_encode(['password' => $password, 'chorister' => 'review-choriste', 'administrator' => 'review-admin'], JSON_THROW_ON_ERROR));
chmod($credentials, 0600);
echo 'Isolated SQLite fixture ready. Credentials file: '.$credentials.PHP_EOL;
