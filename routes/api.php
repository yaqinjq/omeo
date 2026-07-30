<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\DashboardApiController;
use App\Http\Controllers\Api\AppraisalApiController;
use App\Http\Controllers\Api\OutletApiController;
use App\Http\Controllers\Api\OutletAuthController;

Route::get('/dashboard/summary', [DashboardApiController::class, 'summary']);
Route::post('/appraisals/submit', [AppraisalApiController::class, 'submit']);
Route::post('/login', [OutletAuthController::class, 'login'])->name('api.login');

Route::middleware('api.token')->group(function () {
    Route::get('/outlets', [OutletApiController::class, 'index'])->name('api.outlets.index');
    Route::get('/outlets/{outlet}', [OutletApiController::class, 'show'])->name('api.outlets.show');
    Route::get('/branches', [OutletApiController::class, 'branches'])->name('api.branches.index');
});