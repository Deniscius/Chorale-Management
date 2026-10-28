<?php

use App\Enums\UserRole;
use App\Http\Controllers\ApiIntegrationController;
use App\Http\Controllers\AttendanceReportController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ChoraleLogoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\DocumentPreviewController;
use App\Http\Controllers\InstrumentImageController;
use App\Http\Controllers\InstrumentLabelsController;
use App\Http\Controllers\LetterheadFileController;
use App\Http\Controllers\LetterheadSampleController;
use App\Http\Controllers\LetterheadUploadController;
use App\Http\Controllers\MemberDossierController;
use App\Http\Controllers\MemberPhotoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantAddressController;
use App\Http\Controllers\TenantRegistrationController;
use App\Http\Controllers\VoiceResourceAudioController;
use App\Http\Controllers\VoiceSpaceController;
use App\Http\Middleware\TenantAddress;
use App\Livewire\Accounting\MemberOverview as AccountingMemberOverview;
use App\Livewire\Activities\Index as ActivitiesIndex;
use App\Livewire\Attendance\FinancialReport as AttendanceFinancialReport;
use App\Livewire\Attendance\Sheet as AttendanceSheet;
use App\Livewire\Audit\Index;
use App\Livewire\Budgets\Index as BudgetsIndex;
use App\Livewire\Contributions\Index as ContributionsIndex;
use App\Livewire\Correspondences\Index as CorrespondencesIndex;
use App\Livewire\Documents\Index as DocumentsIndex;
use App\Livewire\Donations\Index as DonationsIndex;
use App\Livewire\Instruments\Ensembles as InstrumentsEnsembles;
use App\Livewire\Instruments\Index as InstrumentsIndex;
use App\Livewire\Instruments\Maintenances as InstrumentsMaintenances;
use App\Livewire\Instruments\Movements as InstrumentsMovements;
use App\Livewire\Instruments\Units;
use App\Livewire\Members\Index as MembersIndex;
use App\Livewire\Performances\Index as PerformancesIndex;
use App\Livewire\Rehearsals\Index as RehearsalsIndex;
use App\Livewire\Reminders\History as RemindersHistory;
use App\Livewire\Seasons\Index as SeasonsIndex;
use App\Livewire\Settings\Chorale as SettingsChorale;
use App\Livewire\Settings\FinancialTypes;
use App\Livewire\Transactions\Index as TransactionsIndex;
use App\Livewire\Users\CommissionAssignments;
use App\Livewire\Users\Index as UsersIndex;
use App\Livewire\Works\Index as WorksIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

require __DIR__.'/platform.php';

Route::prefix('c/{slug}')->where(['slug' => '[a-z0-9]+(?:-[a-z0-9]+)*'])
    ->middleware([TenantAddress::class, 'chorale.session'])->group(function () {
        Route::get('/', [TenantAddressController::class, 'show'])->name('tenant.entry');
        Route::get('/logo', [TenantAddressController::class, 'logo'])->middleware('auth')->name('tenant.logo');
        Route::post('/connexion', [AuthenticatedSessionController::class, 'store'])->middleware(['guest', 'throttle:60,1,chorale-login:'])->name('tenant.login');
    });

Route::middleware('guest')->group(function () {
    Route::get('/creer-un-espace', [TenantRegistrationController::class, 'create'])->name('spaces.create');
    Route::post('/creer-un-espace', [TenantRegistrationController::class, 'store'])->middleware('throttle:3,60,space-registration:')->name('spaces.store');
});

// Logos réservés à la session de la chorale ; aucune exposition sur la connexion.
Route::get('/chorale/logo', ChoraleLogoController::class)
    ->middleware(['auth', 'chorale.session'])
    ->name('chorale.logo');

Route::middleware(['auth', 'chorale.session'])->group(function () {
    Route::get('/parametres/contributions', FinancialTypes::class)->middleware('can:manage-settings')->name('settings.financial-types');
    Route::get('/abonnement', [BillingController::class, 'show'])->name('billing.show');
    Route::post('/abonnement/activer', [BillingController::class, 'activate'])->middleware('throttle:5,1,billing-activation:')->name('billing.activate');
    Route::post('/abonnement/actualiser', [BillingController::class, 'refresh'])->middleware('throttle:5,1,billing-refresh:')->name('billing.refresh');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/commissions', App\Livewire\Commissions\Index::class)->middleware('module:'.UserRole::MODULE_MEMBERS)->name('commissions.index');

    // La chorale
    Route::get('/membres', MembersIndex::class)
        ->middleware('module:'.UserRole::MODULE_MEMBERS)
        ->name('members.index');
    Route::get('/membres/{member}/dossier', MemberDossierController::class)->name('members.show');

    // Commission liturgique
    Route::get('/oeuvres', WorksIndex::class)
        ->middleware('module:'.UserRole::MODULE_LITURGIQUE)
        ->name('works.index');

    Route::get('/mon-pupitre', [VoiceSpaceController::class, 'index'])
        ->middleware('module:'.UserRole::MODULE_LITURGIQUE)->name('voice-space.index');
    Route::post('/mon-pupitre', [VoiceSpaceController::class, 'store'])
        ->middleware(['module:'.UserRole::MODULE_LITURGIQUE, 'throttle:20,1'])->name('voice-space.store');
    Route::patch('/mon-pupitre/{resource}', [VoiceSpaceController::class, 'update'])
        ->middleware(['module:'.UserRole::MODULE_LITURGIQUE, 'throttle:30,1'])->name('voice-space.update');
    Route::delete('/mon-pupitre/{resource}', [VoiceSpaceController::class, 'destroy'])
        ->middleware(['module:'.UserRole::MODULE_LITURGIQUE, 'throttle:30,1'])->name('voice-space.destroy');
    Route::get('/mon-pupitre/{resource}/audio', VoiceResourceAudioController::class)
        ->middleware('module:'.UserRole::MODULE_LITURGIQUE)->name('voice-space.audio');

    Route::get('/repetitions', RehearsalsIndex::class)
        ->middleware('module:'.UserRole::MODULE_LITURGIQUE)
        ->name('rehearsals.index');

    Route::get('/celebrations', PerformancesIndex::class)
        ->middleware('module:'.UserRole::MODULE_LITURGIQUE)
        ->name('performances.index');

    Route::get('/temps-liturgiques', SeasonsIndex::class)
        ->middleware('module:'.UserRole::MODULE_LITURGIQUE)
        ->name('seasons.index');

    // Archives documentaires des commissions (accès filtré par rôle dans le composant).
    Route::get('/documents', DocumentsIndex::class)
        ->middleware('module:'.UserRole::MODULE_MEMBERS)
        ->name('documents.index');

    Route::get('/courriers/{correspondence}/pieces', DocumentsIndex::class)
        ->middleware('module:'.UserRole::MODULE_MEMBERS)->name('correspondences.documents');

    Route::get('/documents/{document}/telecharger', DocumentDownloadController::class)
        ->name('documents.download');

    Route::get('/documents/{document}/apercu', DocumentPreviewController::class)
        ->name('documents.preview');

    // Rappels envoyés aux choristes (journal, lecture seule).
    Route::get('/rappels', RemindersHistory::class)
        ->middleware('module:'.UserRole::MODULE_LITURGIQUE)
        ->name('reminders.index');

    Route::get('/instruments/articles', Units::class)->middleware('module:'.UserRole::MODULE_INSTRUMENTS)->name('instruments.units');
    Route::post('/instruments/etiquettes', InstrumentLabelsController::class)->middleware(['module:'.UserRole::MODULE_INSTRUMENTS, 'throttle:30,1'])->name('instruments.labels');
    Route::get('/instruments/{instrument}/image', InstrumentImageController::class)->middleware('module:'.UserRole::MODULE_INSTRUMENTS)->name('instruments.image');

    // Commission des instruments
    Route::get('/instruments', InstrumentsIndex::class)
        ->middleware('module:'.UserRole::MODULE_INSTRUMENTS)
        ->name('instruments.index');

    Route::get('/instruments/mouvements', InstrumentsMovements::class)
        ->middleware('module:'.UserRole::MODULE_INSTRUMENTS)
        ->name('instruments.movements');

    Route::get('/instruments/maintenance', InstrumentsMaintenances::class)
        ->middleware('module:'.UserRole::MODULE_INSTRUMENTS)
        ->name('instruments.maintenances');

    Route::get('/instruments/ensembles', InstrumentsEnsembles::class)
        ->middleware('module:'.UserRole::MODULE_INSTRUMENTS)
        ->name('instruments.ensembles');

    // Secrétariat
    Route::get('/courriers', CorrespondencesIndex::class)
        ->middleware('module:'.UserRole::MODULE_COURRIERS)
        ->name('correspondences.index');

    Route::get('/activites', ActivitiesIndex::class)
        ->middleware('module:'.UserRole::MODULE_ACTIVITES)
        ->name('activities.index');

    Route::get('/secretariat/presences', AttendanceSheet::class)
        ->middleware('can:view-attendance')
        ->name('attendance.sheet');

    // Comptabilité
    Route::get('/transactions', TransactionsIndex::class)
        ->middleware('module:'.UserRole::MODULE_COMPTABILITE)
        ->name('transactions.index');

    Route::get('/budgets', BudgetsIndex::class)
        ->middleware('module:'.UserRole::MODULE_COMPTABILITE)
        ->name('budgets.index');

    Route::get('/cotisations', ContributionsIndex::class)
        ->middleware('module:'.UserRole::MODULE_COMPTABILITE)
        ->name('contributions.index');

    Route::get('/dons', DonationsIndex::class)
        ->middleware('module:'.UserRole::MODULE_COMPTABILITE)
        ->name('donations.index');

    Route::get('/comptabilite/presences', AttendanceFinancialReport::class)
        ->middleware('can:view-attendance-finance')
        ->name('attendance.financial-report');
    Route::get('/comptabilite/choristes', AccountingMemberOverview::class)
        ->middleware('can:view-attendance-finance')
        ->name('accounting.members');

    Route::get('/presences/{eventType}/{event}/rapport', AttendanceReportController::class)
        ->where(['eventType' => 'repetition|activite', 'event' => '[0-9]+'])
        ->name('attendance.report');

    // Administration
    Route::get('/utilisateurs', UsersIndex::class)
        ->middleware('can:manage-users')
        ->name('users.index');

    Route::get('/parametres/chorale', SettingsChorale::class)
        ->middleware('can:manage-settings')
        ->name('settings.chorale');
    Route::get('/parametres/integrations-api', [ApiIntegrationController::class, 'index'])
        ->middleware('can:manage-settings')->name('settings.api-integrations');
    Route::post('/parametres/integrations-api', [ApiIntegrationController::class, 'store'])
        ->middleware(['can:manage-settings', 'throttle:5,1'])->name('settings.api-integrations.store');
    Route::delete('/parametres/integrations-api/{apiClient}', [ApiIntegrationController::class, 'destroy'])
        ->middleware(['can:manage-settings', 'throttle:10,1'])->name('settings.api-integrations.destroy');
    Route::get('/parametres/chorale/papier-entete/exemple', LetterheadSampleController::class)
        ->middleware('can:manage-settings')->name('settings.letterheads.sample');
    Route::post('/parametres/chorale/papier-entete', LetterheadUploadController::class)
        ->middleware(['can:manage-settings', 'throttle:10,1'])->name('settings.letterheads.upload');
    Route::get('/parametres/chorale/papier-entete/{template}/{kind}', LetterheadFileController::class)
        ->where('kind', 'original|preview|header')
        ->middleware('can:manage-settings')->name('settings.letterheads.file');

    Route::get('/administration/journal', Index::class)
        ->middleware('can:manage-users')->name('audit.index');

    // Profil
    Route::get('/utilisateurs/{user}/commissions', CommissionAssignments::class)->middleware('can:manage-users')->name('users.commissions');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [MemberPhotoController::class, 'store'])->middleware('throttle:10,1')->name('profile.photo.store');
    Route::get('/profile/photo', [MemberPhotoController::class, 'show'])->name('profile.photo.show');
    Route::delete('/profile/photo', [MemberPhotoController::class, 'destroy'])->middleware('throttle:10,1')->name('profile.photo.destroy');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
