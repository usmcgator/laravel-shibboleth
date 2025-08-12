<?php

use Illuminate\Support\Facades\Route;
use StudentAffairsUwm\Shibboleth\Controllers\ShibbolethController;

if (config("shibboleth.register_routes") !== false) {
    Route::middleware(['web'])->group(function () {
        Route::get('/shibboleth-login', [ShibbolethController::class, 'login'])->name('shibboleth-login');
        Route::get('/shibboleth-authenticate', [ShibbolethController::class, 'idpAuthenticate'])->name('shibboleth-authenticate');
        Route::get('/shibboleth-logout', [ShibbolethController::class, 'destroy'])->name('shibboleth-logout');

        if (config('shibboleth.sp_type') === "local_shib") {
            Route::get('/local-sp/Login', [ShibbolethController::class, 'localSPLogin'])->name('local-sp-login');
            Route::get('/local-sp/Logout', [ShibbolethController::class, 'localSPLogout'])->name('local-sp-logout');
            Route::post('/local-sp/ACS', [ShibbolethController::class, 'localSPACS'])->name('local-sp-acs');
            Route::get('/local-sp/Metadata', [ShibbolethController::class, 'localSPMetadata'])->name('local-sp-metadata');
        }
    });
}
