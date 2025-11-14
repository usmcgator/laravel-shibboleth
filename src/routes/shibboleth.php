<?php

use Illuminate\Support\Facades\Route;
use StudentAffairsUwm\Shibboleth\Controllers\ShibbolethController;

if (config("shibboleth.register_routes") === true) {
    Route::middleware(['web'])->group(function () {

        // Guest-only routes
        Route::middleware(['guest'])->group(function () {
            Route::get('/shibboleth-login', [ShibbolethController::class, 'login'])->name('shibboleth-login');
            Route::get('/shibboleth-authenticate', [ShibbolethController::class, 'idpAuthenticate'])->name('shibboleth-authenticate');
        });

        // Authenticated-only routes
        Route::middleware(['auth'])->group(function () {
            Route::get('/shibboleth-logout', [ShibbolethController::class, 'destroy'])->name('shibboleth-logout');
        });
    });
}
