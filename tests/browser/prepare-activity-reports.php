<?php

use App\Enums\UserRole;
use App\Http\Controllers\ActivityFinancialReportController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\CommissionReportController;
use App\Http\Controllers\LetterheadSampleController;
use App\Http\Controllers\MemberReportController;
use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\ChoirCommission;
use App\Models\LetterheadTemplate;
use App\Models\Member;
use App\Models\Rehearsal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ChoraleIdentity;
use App\Services\Letterhead;
use App\Services\PrivateFiles;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ReportHeaderFixture;

// All data is fictitious. Refuse to use a persistent database or production configuration.
$root = dirname(__DIR__, 2);
foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DB_URL' => '',
    'DATABASE_URL' => '', 'DATABASE_URL_UNPOOLED' => '', 'TENANCY_ENABLED' => 'false', 'SESSION_DRIVER' => 'array',
    'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'MAIL_DELIVERY_ENABLED' => 'false',
    'APP_CONFIG_CACHE' => sys_get_temp_dir().'/report-preview-no-config.php'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'sqlite'
    || config('database.connections.sqlite.database') !== ':memory:' || config('database.connections.sqlite.url')) {
    throw new RuntimeException('Only an isolated in-memory SQLite database is permitted.');
}
config(['private-files.driver' => 'local', 'deployment.ephemeral_files' => false]);
Artisan::call('migrate:fresh', ['--force' => true]);
Mail::fake();
Notification::fake();
Storage::fake('local');
$directory = $argv[1] ?? throw new RuntimeException('Artifact directory required.');
if (! is_dir($directory)) {
    mkdir($directory, 0700, true);
}
$admin = User::factory()->role(UserRole::Admin)->create(['name' => 'Responsable de démonstration']);
Auth::setUser($admin);
$commission = ChoirCommission::query()->create(['name' => 'Commission de démonstration', 'name_key' => 'report-preview',
    'kind' => 'permanente', 'mission' => 'Préparation du concert', 'starts_on' => today()->subMonth(), 'module_key' => 'activites', 'leader_id' => $admin->id]);
$activity = Activity::factory()->create(['title' => 'Concert de démonstration', 'type' => 'concert', 'status' => 'terminee',
    'description' => 'Rapport fictif destiné à vérifier le rendu des informations, des présences et du bilan financier.',
    'report' => 'Les participants ont préparé le programme et contribué à la réussite du concert.',
    'venue' => 'Salle de démonstration', 'choir_commission_id' => $commission->id,
    'created_by' => $admin->id, 'budget_allocated' => 100000, 'budget_spent' => 25000,
    'attendance_finalized_at' => now(), 'attendance_finalized_by' => $admin->id]);
for ($i = 1; $i <= 45; $i++) {
    $member = Member::factory()->active()->create(['first_name' => 'Choriste', 'last_name' => sprintf('Démo %02d', $i)]);
    ActivityParticipant::query()->create(['activity_id' => $activity->id, 'member_id' => $member->id,
        'status' => $i % 5 === 0 ? 'absent' : 'present', 'role' => $i === 1 ? 'soliste' : 'choriste', 'hours' => 2.5,
        'notes' => $i === 1 ? 'Dernière vérification de la signature et du rôle.' : null]);
}
$commission->members()->attach(Member::query()->limit(3)->pluck('id'));
Transaction::factory()->recette()->create(['activity_id' => $activity->id, 'description' => 'Participation au concert', 'amount' => 35000]);
Transaction::factory()->depense()->create(['activity_id' => $activity->id, 'description' => 'Transport et logistique', 'amount' => 12000]);
$path = 'letterheads/browser-fixture/header.png';
app(PrivateFiles::class)->storeContents($path, ReportHeaderFixture::png(), 'image/png');
LetterheadTemplate::query()->create(['original_name' => 'papier-de-demonstration.pdf', 'header_path' => $path,
    'status' => 'active', 'header_height_mm' => 45, 'activated_at' => now(), 'uploaded_by' => $admin->id]);
$request = Request::create('/activites/'.$activity->id.'/rapport');
$request->setUserResolver(fn () => $admin);
$app->instance('request', $request);
$identity = app(ChoraleIdentity::class);
foreach (['landscape', 'portrait'] as $orientation) {
    $request->query->set('orientation', $orientation);
    $response = app(AttendanceReportController::class)->activityReport($request, $activity, app(Letterhead::class), $identity);
    file_put_contents($directory.'/activity-'.$orientation.'.html', $response->getContent());
}
$request->query->replace(['document' => 'fiche']);
file_put_contents($directory.'/attendance.html', app(AttendanceReportController::class)($request, 'activite', $activity->id, app(Letterhead::class), $identity)->getContent());
$rehearsal = Rehearsal::factory()->create(['date' => today()]);
file_put_contents($directory.'/rehearsal.html', app(AttendanceReportController::class)($request, 'repetition', $rehearsal->id, app(Letterhead::class), $identity)->getContent());
file_put_contents($directory.'/members.html', app(MemberReportController::class)(app(Letterhead::class), $identity)->getContent());
file_put_contents($directory.'/commission.html', app(CommissionReportController::class)($request, $commission, app(Letterhead::class), $identity)->getContent());
$request->query->replace(['orientation' => 'landscape']);
file_put_contents($directory.'/period.html', app(ActivityFinancialReportController::class)($request, app(Letterhead::class), $identity)->getContent());
file_put_contents($directory.'/sample.html', app(LetterheadSampleController::class)(app(Letterhead::class), $identity)->render());
echo "Prepared eight isolated report previews.\n";
