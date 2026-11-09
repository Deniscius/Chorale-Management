<?php

// Adds fictional review data to the local browser fixture, never to a persistent service.
use App\Models\Account;
use App\Models\Activity;
use App\Models\Contribution;
use App\Models\Instrument;
use App\Models\Member;
use App\Models\Platform\Admin;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ChoraleIdentity;
use App\Services\Platform\Totp;
use App\Services\PrivateFiles;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;

$root = dirname(__DIR__, 2);
$directory = $root.'/storage/framework/testing/accessibility';
$database = $directory.'/review.sqlite';
if (! is_file($database) || ! is_file($directory.'/credentials.json')) {
    throw new RuntimeException('Run tests/browser/prepare.php first.');
}
foreach (['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => $directory.'/config.php', 'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => $database, 'DB_URL' => '', 'DATABASE_URL' => '', 'DATABASE_URL_UNPOOLED' => '',
    'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'MAIL_DELIVERY_ENABLED' => 'false', 'TENANCY_ENABLED' => 'true'] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== $database) {
    throw new RuntimeException('Review database isolation failed.');
}
Mail::fake();
Notification::fake();
$admin = User::withoutGlobalScopes()->where('username', 'review-admin')->firstOrFail();
app(TenantContext::class)->run($admin->tenant_id, function (): void {
    if (blank(Setting::get(ChoraleIdentity::KEY_LOGO_PATH))) {
        $path = app(TenantContext::class)->path('chorale/design-fixture.png');
        app(PrivateFiles::class)->storeContents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZrC8AAAAASUVORK5CYII='), 'image/png');
        Setting::set(ChoraleIdentity::KEY_LOGO_PATH, $path);
    }
    foreach (['Élise', 'Joseph', 'Anne', 'Paul', 'Marie', 'Emmanuel', 'Grâce', 'Michel'] as $index => $name) {
        if (! Member::where('email', 'design-'.$index.'@example.test')->exists()) {
            Member::factory()->create(['first_name' => $name, 'last_name' => 'Démonstration', 'email' => 'design-'.$index.'@example.test',
                'phone' => null, 'address' => null, 'profession' => null, 'voice' => ['soprano', 'alto', 'tenor', 'bass'][$index % 4],
                'status' => $index === 7 ? 'externe' : 'actif']);
        }
    }
    $activity = Activity::firstOrCreate(['title' => 'Concert de l’Avent'], ['type' => 'concert', 'description' => 'Répétition générale et accueil du public.',
        'starts_at' => now()->addDays(12)->setTime(18, 30), 'ends_at' => now()->addDays(12)->setTime(20, 0),
        'venue' => 'Église paroissiale', 'status' => 'planifiee', 'budget_allocated' => 75000, 'budget_spent' => 12500]);
    foreach (Member::all() as $member) {
        $activity->participantRecords()->firstOrCreate(['member_id' => $member->id], ['status' => 'invite']);
    }
    if (! Instrument::where('name', 'Piano de la salle de répétition')->exists()) {
        Instrument::factory()->create(['name' => 'Piano de la salle de répétition', 'category' => 'piano', 'condition' => 'bon', 'quantity' => 1,
            'notes' => null, 'location' => 'Salle de répétition']);
    }
    $account = Account::firstOrCreate(['code' => 'DESIGN-CAISSE'], ['name' => 'Caisse de la chorale', 'type' => 'tresorerie', 'is_active' => true]);
    if (! Transaction::where('description', 'Participations au concert de l’Avent')->exists()) {
        Transaction::factory()->create(['description' => 'Participations au concert de l’Avent', 'type' => 'recette', 'status' => 'validee',
            'transaction_date' => today(), 'amount' => 42000, 'account_id' => $account->id, 'activity_id' => $activity->id]);
    }
    $period = today()->subMonthNoOverflow()->startOfMonth();
    foreach (Member::active()->take(4)->get() as $member) {
        Contribution::firstOrCreate(['member_id' => $member->id, 'year' => $period->year, 'month' => $period->month, 'type_code' => 'mensuelle'],
            ['period_starts_on' => $period, 'due_on' => $period->copy()->endOfMonth(), 'due_amount' => 2000, 'paid_amount' => 0, 'status' => 'due']);
    }
});
$credentials = json_decode(file_get_contents($directory.'/credentials.json'), true, flags: JSON_THROW_ON_ERROR);
$secret = app(Totp::class)->secret();
Admin::updateOrCreate(['email' => 'design-platform@example.test'], ['name' => 'Propriétaire de démonstration', 'password' => $credentials['password'],
    'totp_secret' => $secret, 'enabled' => true, 'last_totp_counter' => 0]);
$credentials['platform'] = ['email' => 'design-platform@example.test', 'secret' => $secret];
file_put_contents($directory.'/credentials.json', json_encode($credentials, JSON_THROW_ON_ERROR));
chmod($directory.'/credentials.json', 0600);
echo "Local design fixtures ready. No mail sent.\n";
