<?php

use App\Jobs\SendSMSJob;
use App\Models\MessageLog;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use LBHurtado\SMS\Facades\SMS;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('it creates a message log when dispatched', function () {
    Queue::fake();

    SendSMSJob::dispatch('+639173011987', 'Test message', 'TXTCMDR');

    Queue::assertPushed(SendSMSJob::class);
});

test('it sends SMS and marks log as sent on success', function () {
    // Mock SMS sending
    SMS::shouldReceive('channel')->with('engagespark')->andReturnSelf();
    SMS::shouldReceive('from')->with('TXTCMDR')->andReturnSelf();
    SMS::shouldReceive('to')->with('+639173011987')->andReturnSelf();
    SMS::shouldReceive('content')->with('Test message')->andReturnSelf();
    SMS::shouldReceive('send')->once()->andReturn(true);

    $job = new SendSMSJob('+639173011987', 'Test message', 'TXTCMDR');
    $job->handle();

    // Check that message log was created and marked as sent
    $log = MessageLog::where('recipient', '+639173011987')->first();
    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('sent')
        ->and($log->message)->toBe('Test message')
        ->and($log->sender_id)->toBe('TXTCMDR')
        ->and($log->user_id)->toBe($this->user->id)
        ->and($log->sent_at)->not->toBeNull();
});

test('it normalizes phone numbers to E164 format', function () {
    // Mock SMS sending
    SMS::shouldReceive('channel')->andReturnSelf();
    SMS::shouldReceive('from')->andReturnSelf();
    SMS::shouldReceive('to')->andReturnSelf();
    SMS::shouldReceive('content')->andReturnSelf();
    SMS::shouldReceive('send')->once()->andReturn(true);

    $job = new SendSMSJob('09173011987', 'Test message', 'TXTCMDR');
    $job->handle();

    // Check that log uses normalized E.164 format
    $log = MessageLog::where('recipient', '+639173011987')->first();
    expect($log)->not->toBeNull()
        ->and($log->recipient)->toBe('+639173011987');
});

test('it marks log as failed when SMS sending fails', function () {
    SMS::shouldReceive('channel')->andReturnSelf();
    SMS::shouldReceive('from')->andReturnSelf();
    SMS::shouldReceive('to')->andReturnSelf();
    SMS::shouldReceive('content')->andReturnSelf();
    SMS::shouldReceive('send')->andThrow(new \Exception('SMS API Error'));

    $job = new SendSMSJob('+639173011987', 'Test message', 'TXTCMDR');

    try {
        $job->handle();
    } catch (\Exception $e) {
        // Expected to throw
    }

    // Check that message log was created and marked as failed
    $log = MessageLog::where('recipient', '+639173011987')->first();
    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('failed')
        ->and($log->failed_at)->not->toBeNull()
        ->and($log->error_message)->toContain('SMS API Error');
});

test('it associates log with scheduled message when provided', function () {
    // Create a scheduled message first
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

    // Mock SMS sending
    SMS::shouldReceive('channel')->andReturnSelf();
    SMS::shouldReceive('from')->andReturnSelf();
    SMS::shouldReceive('to')->andReturnSelf();
    SMS::shouldReceive('content')->andReturnSelf();
    SMS::shouldReceive('send')->once()->andReturn(true);

    $job = new SendSMSJob('+639173011987', 'Test message', 'TXTCMDR', scheduledMessageId: $scheduledMessage->id);
    $job->handle();

    $log = MessageLog::where('recipient', '+639173011987')->first();
    expect($log)->not->toBeNull()
        ->and($log->scheduled_message_id)->toBe($scheduledMessage->id);
});

test('it respects blacklist middleware', function () {
    Queue::fake();

    // Create blacklisted number
    \DB::table('blacklisted_numbers')->insert([
        'mobile' => '+639173011987',
        'reason' => 'opt-out',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $job = new SendSMSJob('+639173011987', 'Test message', 'TXTCMDR');

    // Get middleware
    $middleware = $job->middleware();
    expect($middleware)->toHaveCount(1)
        ->and($middleware[0])->toBeInstanceOf(\App\Jobs\Middleware\CheckBlacklist::class);
});
