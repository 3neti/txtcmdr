<?php

use App\Actions\CancelScheduledMessage;
use App\Actions\Contacts\ImportContactsFromFile;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\DeleteGroup;
use App\Actions\Groups\GetGroup;
use App\Actions\Groups\ListGroups;
use App\Actions\ListScheduledMessages;
use App\Actions\ScheduleMessage;
use App\Actions\SendToMultipleGroups;
use App\Actions\SendToMultipleRecipients;
use App\Actions\SMS\BulkSendFromFile;
use App\Actions\SMS\BulkSendPersonalized;
use App\Actions\UpdateScheduledMessage;
use App\Http\Controllers\Api\OtpController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// User endpoint
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// OTP endpoints - Require authentication and specific abilities
Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    Route::post('/otp/request', [OtpController::class, 'request'])->middleware('abilities:otp:request');
    Route::post('/otp/verify', [OtpController::class, 'verify'])->middleware('abilities:otp:verify');
});

// SMS Broadcasting - Protected by auth and abilities
Route::middleware('auth:sanctum')->group(function () {
    // Send to multiple recipients - requires sms:send
    Route::post('/send', SendToMultipleRecipients::class)->middleware('abilities:sms:send');

    // Send to multiple groups - requires sms:send
    Route::post('/groups/send', SendToMultipleGroups::class)->middleware('abilities:sms:send');

    // Group Management - separate read/write permissions
    Route::get('/groups', ListGroups::class)->middleware('abilities:groups:read');
    Route::post('/groups', CreateGroup::class)->middleware('abilities:groups:write');
    Route::get('/groups/{id}', GetGroup::class)->middleware('abilities:groups:read');
    Route::delete('/groups/{id}', DeleteGroup::class)->middleware('abilities:groups:write');

    // Scheduled Messages - requires sms:schedule
    Route::post('/send/schedule', ScheduleMessage::class)->middleware('abilities:sms:schedule');
    Route::get('/scheduled-messages', ListScheduledMessages::class)->middleware('abilities:sms:schedule');
    Route::put('/scheduled-messages/{id}', UpdateScheduledMessage::class)->middleware('abilities:sms:schedule');
    Route::post('/scheduled-messages/{id}/cancel', CancelScheduledMessage::class)->middleware('abilities:sms:schedule');

    // Bulk Import - requires appropriate permissions
    Route::post('/contacts/import', ImportContactsFromFile::class)->middleware('abilities:contacts:write');
    Route::post('/sms/bulk-send', BulkSendFromFile::class)->middleware('abilities:sms:send');
    Route::post('/sms/bulk-send-personalized', BulkSendPersonalized::class)->middleware('abilities:sms:send');
});
