<?php

use Illuminate\Support\Facades\Route;
use StudentAffairsUwm\Shibboleth\Controllers\ShibbolethController;

if (config('shibboleth.register_routes') === true) {

    $basePath = trim((string) config('shibboleth.mount_path'), '');

    Route::group([
        //  'prefix' => $basePath,
        'middleware' => ['web', 'guest'],
    ], function () {

        // ---------------------------
        // Guest-only routes
        // ---------------------------
        Route::middleware(['guest'])->group(function () {

            // Shibalike emulated IdP routes (GET/POST for the IdP endpoint)
            if (config('shibboleth.emulate_idp') === true) {

                Route::get('emulated/login', [ShibbolethController::class, 'emulateLogin'])
                    ->name('emulateLogin');

                Route::get('emulated/idp', [ShibbolethController::class, 'emulateIdp'])
                    ->name('emulateIdp.get');

                Route::post('emulated/idp', [ShibbolethController::class, 'emulateIdp'])
                    ->name('emulateIdp'); // keep the existing name for POST
            }

            // Local SP (SAML) endpoints
            if (config('shibboleth.sp_type') === 'local_shib') {
                // Use paths WITHOUT leading slash; the group prefix handles base
                Route::get('local-sp/Login', [ShibbolethController::class, 'localSPLogin'])
                    ->name('local-sp-login');

                Route::post('local-sp/ACS', [ShibbolethController::class, 'localSPACS'])
                    ->name('local-sp-acs');
            }
        });

        // ---------------------------
        // Authenticated-only routes
        // ---------------------------
        Route::middleware(['auth'])->group(function () {

            // If you wish to provide a logout route here later, keep as-is:
            // Route::get('shibboleth-logout', [ShibbolethController::class, 'destroy'])->name('shibboleth-logout');

            // Emulated logout
            if (config('shibboleth.emulate_idp') === true) {
                Route::get('emulated/logout', [ShibbolethController::class, 'emulateLogout'])
                    ->name('emulateLogout');
            }

            // Local SP metadata/logout endpoints
            if (config('shibboleth.sp_type') === 'local_shib') {
                Route::get('local-sp/Logout', [ShibbolethController::class, 'localSPLogout'])
                    ->name('local-sp-logout');

                Route::get('local-sp/Metadata', [ShibbolethController::class, 'localSPMetadata'])
                    ->name('local-sp-metadata');
            }
        });
    });
}
