<?php

use Illuminate\Support\Facades\Route;
use StudentAffairsUwm\Shibboleth\Controllers\ShibbolethController;

if (config("shibboleth.register_routes") === true) {
    Route::middleware(['web'])->group(function () {

        // Guest-only routes
        Route::middleware(['guest'])->group(function () {

            if (config('shibboleth.emulate_idp') === true) {
                Route::get('/emulated/login', [ShibbolethController::class, 'emulateLogin'])->name('emulateLogin');
                Route::get('/emulated/idp', [ShibbolethController::class, 'emulateIdp']);
                Route::post('/emulated/idp', [ShibbolethController::class, 'emulateIdp'])->name('emulateIdp');
            }

            if (config('shibboleth.sp_type') === 'local_shib') {
                Route::get('/local-sp/Login', [ShibbolethController::class, 'localSPLogin'])->name('local-sp-login');
                Route::post('/local-sp/ACS', [ShibbolethController::class, 'localSPACS'])->name('local-sp-acs');
            }
        });

        // Authenticated-only routes
        Route::middleware(['auth'])->group(function () {
            // Route::get('/shibboleth-logout', [ShibbolethController::class, 'destroy'])->name('shibboleth-logout');

            if (config('shibboleth.emulate_idp') === true) {
                Route::get('/emulated/logout', [ShibbolethController::class, 'emulateLogout'])->name('emulateLogout');
            }

            if (config('shibboleth.sp_type') === 'local_shib') {
                Route::get('/local-sp/Logout', [ShibbolethController::class, 'localSPLogout'])->name('local-sp-logout');
                Route::get('/local-sp/Metadata', [ShibbolethController::class, 'localSPMetadata'])->name('local-sp-metadata');
            }
        });
    });
}
