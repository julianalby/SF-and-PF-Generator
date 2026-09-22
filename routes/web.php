<?php

use App\Http\Controllers\Admin\PfRecordController as AdminPfRecordController;
use App\Http\Controllers\Admin\SfRecordController as AdminSfRecordController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PfController;
use App\Http\Controllers\SfController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guests only
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

/*
|--------------------------------------------------------------------------
| Any authenticated user (User or Admin)
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    Route::redirect('/', '/dashboard');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Generate Number SF
    Route::get('/sf/create', [SfController::class, 'create'])->name('sf.create');
    Route::post('/sf', [SfController::class, 'store'])->name('sf.store');
    Route::get('/sf/{sfRecord}', [SfController::class, 'show'])->whereNumber('sfRecord')->name('sf.show');

    // Generate Number PF
    Route::get('/pf/create', [PfController::class, 'create'])->name('pf.create');
    Route::post('/pf', [PfController::class, 'store'])->name('pf.store');
    Route::get('/pf/{pfRecord}', [PfController::class, 'show'])->whereNumber('pfRecord')->name('pf.show');

    /*
    |----------------------------------------------------------------------
    | Admins only: enforced server-side by the "admin" middleware
    |----------------------------------------------------------------------
    */
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/sf-records', [AdminSfRecordController::class, 'index'])->name('sf.index');
        Route::get('/pf-records', [AdminPfRecordController::class, 'index'])->name('pf.index');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [AdminUserController::class, 'edit'])->whereNumber('user')->name('users.edit');
        Route::put('/users/{user}/password', [AdminUserController::class, 'updatePassword'])->whereNumber('user')->name('users.password');
    });
});
