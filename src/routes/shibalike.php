<?php

use Illuminate\Support\Facades\Route;
use StudentAffairsUwm\Shibboleth\Controllers\ShibbolethController;

if (config("shibboleth.register_routes") !== false && config("shibboleth.emulate_idp") === true) {
    Route::middleware(['web'])->group(function () {
        Route::get('emulated/idp', [ShibbolethController::class, 'emulateIdp']);
        Route::post('emulated/idp', [ShibbolethController::class, 'emulateIdp'])->name('emulateIdp');
        Route::get('emulated/login', [ShibbolethController::class, 'emulateLogin'])->name('emulateLogin');
        Route::get('emulated/logout', [ShibbolethController::class, 'emulateLogout'])->name('emulateLogout');
    });
}
