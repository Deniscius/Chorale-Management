<?php

use App\Http\Controllers\Api\V1\MemberController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['throttle:api-integrations', 'integration.api:members:read'])->group(function () {
    Route::get('/members', [MemberController::class, 'index'])->name('api.v1.members.index');
    Route::get('/members/{member}', [MemberController::class, 'show'])->name('api.v1.members.show');
});
