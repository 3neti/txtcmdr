<?php

use App\Actions\RetryFailedMessage;
use App\Jobs\SendSMSJob;
use App\Models\MessageLog;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('it retries a failed message', function () {
    Queue::fake();

    // Create a failed message log
    $log = MessageLog::create([
        'user_id' => $this->user->id,
        'recipient' => '+639173011987',
        'message' => 'Test message',
        'status' => 'failed',
        'sender_id' => 'TXTCMDR',
        'failed_at' => now(),
        'error_message' => 'Original error',
    ]);

    $result = RetryFailedMessage::run($log->id);

    expect($result['success'])->toBeTrue()
        ->and($result['message'])->toBe('Message queued for retry');

    // Check that log was updated
    $log->refresh();
    expect($log->status)->toBe('pending')
        ->and($log->failed_at)->toBeNull()
        ->and($log->error_message)->toBeNull();

    // Check that job was dispatched
    Queue::assertPushed(SendSMSJob::class, function ($job) use ($log) {
        return $job->mobile === $log->recipient
            && $job->message === $log->message
            && $job->senderId === $log->sender_id;
    });
});

test('it only allows retrying failed messages', function () {
    // Create a sent message
    $log = MessageLog::create([
        'user_id' => $this->user->id,
        'recipient' => '+639173011987',
        'message' => 'Test message',
        'status' => 'sent',
        'sender_id' => 'TXTCMDR',
        'sent_at' => now(),
    ]);

    RetryFailedMessage::run($log->id);
})->throws(\InvalidArgumentException::class, 'Only failed messages can be retried.');

test('it only allows user to retry their own messages', function () {
    $otherUser = User::factory()->create();

    // Create a failed message for another user
    $log = MessageLog::create([
        'user_id' => $otherUser->id,
        'recipient' => '+639173011987',
        'message' => 'Test message',
        'status' => 'failed',
        'sender_id' => 'TXTCMDR',
        'failed_at' => now(),
        'error_message' => 'Original error',
    ]);

    RetryFailedMessage::run($log->id);
})->throws(\Illuminate\Auth\Access\AuthorizationException::class);

test('it preserves scheduled message association when retrying', function () {
    Queue::fake();

    $scheduledMessage = \App\Models\ScheduledMessage::create([
        'user_id' => $this->user->id,
        'message' => 'Test message',
        'sender_id' => 'TXTCMDR',
        'recipient_type' => 'numbers',
        'recipient_data' => ['numbers' => ['+639173011987']],
        'scheduled_at' => now()->addHour(),
        'total_recipients' => 1,
        'status' => 'pending',
    ]);

    $log = MessageLog::create([
        'user_id' => $this->user->id,
        'recipient' => '+639173011987',
        'message' => 'Test message',
        'status' => 'failed',
        'sender_id' => 'TXTCMDR',
        'scheduled_message_id' => $scheduledMessage->id,
        'failed_at' => now(),
        'error_message' => 'Original error',
    ]);

    RetryFailedMessage::run($log->id);

    // Check that job was dispatched with scheduled message ID
    Queue::assertPushed(SendSMSJob::class, function ($job) use ($scheduledMessage) {
        return $job->scheduledMessageId === $scheduledMessage->id;
    });
});

test('it handles retry via web route', function () {
    Queue::fake();

    $log = MessageLog::create([
        'user_id' => $this->user->id,
        'recipient' => '+639173011987',
        'message' => 'Test message',
        'status' => 'failed',
        'sender_id' => 'TXTCMDR',
        'failed_at' => now(),
        'error_message' => 'Original error',
    ]);

    $response = $this->post("/message-logs/{$log->id}/retry");

    $response->assertRedirect()
        ->assertSessionHas('success', 'Message queued for retry!');

    Queue::assertPushed(SendSMSJob::class);
});

test('it returns error for unauthorized retry via web route', function () {
    $otherUser = User::factory()->create();

    $log = MessageLog::create([
        'user_id' => $otherUser->id,
        'recipient' => '+639173011987',
        'message' => 'Test message',
        'status' => 'failed',
        'sender_id' => 'TXTCMDR',
        'failed_at' => now(),
        'error_message' => 'Original error',
    ]);

    $response = $this->post("/message-logs/{$log->id}/retry");

    $response->assertRedirect()
        ->assertSessionHasErrors();
});
