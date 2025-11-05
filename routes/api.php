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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// User endpoint
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// SMS Broadcasting - Protected by auth
Route::middleware('auth:sanctum')->group(function () {
    // Send to multiple recipients
    Route::post('/send', SendToMultipleRecipients::class);
    
    // Send to multiple groups
    Route::post('/groups/send', SendToMultipleGroups::class);
    
    // Group Management
    Route::get('/groups', ListGroups::class);
    Route::post('/groups', CreateGroup::class);
    Route::get('/groups/{id}', GetGroup::class);
    Route::delete('/groups/{id}', DeleteGroup::class);
    
    // Scheduled Messages (Phase 3)
    Route::post('/send/schedule', ScheduleMessage::class);
    Route::get('/scheduled-messages', ListScheduledMessages::class);
    Route::put('/scheduled-messages/{id}', UpdateScheduledMessage::class);
    Route::post('/scheduled-messages/{id}/cancel', CancelScheduledMessage::class);
    
    // Bulk Import (Phase 3)
    Route::post('/contacts/import', ImportContactsFromFile::class);
    Route::post('/sms/bulk-send', BulkSendFromFile::class);
    Route::post('/sms/bulk-send-personalized', BulkSendPersonalized::class);
});
