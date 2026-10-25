<?php

use App\Enums\UserRole;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\ChoraleLogoController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TenantRegistrationController;
use App\Livewire\Activities\Index as ActivitiesIndex;
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
use App\Livewire\Members\Index as MembersIndex;
use App\Livewire\Performances\Index as PerformancesIndex;
use App\Livewire\Rehearsals\Index as RehearsalsIndex;
use App\Livewire\Reminders\History as RemindersHistory;
use App\Livewire\Seasons\Index as SeasonsIndex;
use App\Livewire\Settings\Chorale as SettingsChorale;
use App\Livewire\Transactions\Index as TransactionsIndex;
use App\Livewire\Users\Index as UsersIndex;
use App\Livewire\Works\Index as WorksIndex;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

require __DIR__.'/platform.php';

Route::middleware('guest')->group(function () {
    Route::get('/creer-un-espace', [TenantRegistrationController::class, 'create'])->name('spaces.create');
    Route::post('/creer-un-espace', [TenantRegistrationController::class, 'store'])->middleware('throttle:3,60,space-registration:')->name('spaces.store');
});

// Logo de la chorale : affiché sur les pages publiques (connexion).
Route::get('/chorale/logo', ChoraleLogoController::class)
    ->middleware(['web'])
    ->name('chorale.logo');

Route::middleware(['auth', 'chorale.session'])->group(function () {
    Route::get('/abonnement', [BillingController::class, 'show'])->name('billing.show');
    Route::post('/abonnement/activer', [BillingController::class, 'activate'])->middleware('throttle:5,1,billing-activation:')->name('billing.activate');
    Route::post('/abonnement/actualiser', [BillingController::class, 'refresh'])->middleware('throttle:5,1,billing-refresh:')->name('billing.refresh');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // La chorale
    Route::get('/membres', MembersIndex::class)
        ->middleware('module:'.UserRole::MODULE_MEMBERS)
        ->name('members.index');

    // Commission liturgique
    Route::get('/oeuvres', WorksIndex::class)
        ->middleware('module:'.UserRole::MODULE_LITURGIQUE)
        ->name('works.index');

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

    // Rappels envoyés aux choristes (journal, lecture seule).
    Route::get('/rappels', RemindersHistory::class)
        ->middleware('module:'.UserRole::MODULE_LITURGIQUE)
        ->name('reminders.index');

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

    // Administration
    Route::get('/utilisateurs', UsersIndex::class)
        ->middleware('can:manage-users')
        ->name('users.index');

    Route::get('/parametres/chorale', SettingsChorale::class)
        ->middleware('can:manage-settings')
        ->name('settings.chorale');

    Route::get('/administration/journal', Index::class)
        ->middleware('can:manage-users')->name('audit.index');

    // Profil
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
