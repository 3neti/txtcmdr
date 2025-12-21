<?php

use App\Http\Controllers\Settings\OtpConfigController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SmsConfigController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/Appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    Route::get('settings/sms', [SmsConfigController::class, 'edit'])->name('sms-config.edit');
    Route::put('settings/sms', [SmsConfigController::class, 'update'])->name('sms-config.update');
    Route::delete('settings/sms', [SmsConfigController::class, 'destroy'])->name('sms-config.destroy');

    Route::get('settings/otp', [OtpConfigController::class, 'edit'])->name('otp-config.edit');
    Route::put('settings/otp', [OtpConfigController::class, 'update'])->name('otp-config.update');
});
