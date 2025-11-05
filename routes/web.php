<?php

use App\Actions\ScheduleMessage;
use App\Actions\SendToMultipleRecipients;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('send-sms', function () {
    return Inertia::render('SendSMS');
})->middleware(['auth', 'verified'])->name('sendSMS');

// SMS Actions (for authenticated web interface)
Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('sms/send', function (Request $request) {
        $result = SendToMultipleRecipients::run(
            $request->input('recipients'),
            $request->input('message'),
            $request->input('sender_id')
        );
        return back()->with('success', 'Message sent successfully!');
    })->name('sms.send');
    
    Route::post('sms/schedule', function (Request $request) {
        $result = ScheduleMessage::run(
            $request->input('recipients'),
            $request->input('message'),
            $request->input('scheduled_at'),
            $request->input('sender_id')
        );
        return back()->with('success', 'Message scheduled successfully!');
    })->name('sms.schedule');
});

require __DIR__.'/settings.php';
