<?php

use App\Actions\SendToMultipleRecipients;
use App\Actions\SendToMultipleGroups;
use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\ListGroups;
use App\Actions\Groups\GetGroup;
use App\Actions\Groups\DeleteGroup;
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
    
    // Contact Management (to be implemented)
    // Route::post('/groups/{id}/contacts', AddContactToGroup::class);
    
    // Blacklist Management (to be implemented)
    // Route::post('/blacklist', AddToBlacklist::class);
    // Route::delete('/blacklist/{id}', RemoveFromBlacklist::class);
    
    // Scheduled Messages (Phase 3)
    // Route::post('/send/schedule', ScheduleMessage::class);
    // Route::get('/scheduled-messages', ListScheduledMessages::class);
});
