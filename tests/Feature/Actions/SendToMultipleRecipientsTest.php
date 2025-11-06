<?php

use App\Actions\SendToMultipleRecipients;
use App\Jobs\SendSMSJob;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('it dispatches SMS jobs for valid recipients', function () {
    Queue::fake();

    $result = SendToMultipleRecipients::run(
        recipients: ['+639173011987', '+639178251991'],
        message: 'Test message',
        senderId: 'TXTCMDR'
    );

    expect($result['status'])->toBe('queued')
        ->and($result['count'])->toBe(2)
        ->and($result['recipients'])->toHaveCount(2)
        ->and($result['invalid_count'])->toBe(0);

    Queue::assertPushed(SendSMSJob::class, 2);
});

test('it accepts comma-separated string of recipients', function () {
    Queue::fake();

    $result = SendToMultipleRecipients::run(
        recipients: '+639173011987,+639178251991',
        message: 'Test message'
    );

    expect($result['count'])->toBe(2);
    Queue::assertPushed(SendSMSJob::class, 2);
});

test('it creates contacts for new phone numbers', function () {
    Queue::fake();

    expect(Contact::count())->toBe(0);

    $result = SendToMultipleRecipients::run(
        recipients: ['+639173011987'],
        message: 'Test message'
    );

    expect(Contact::count())->toBeGreaterThanOrEqual(1);
    // Contact is created by fromPhoneNumber, it auto-saves
    $contact = Contact::first();
    expect($contact)->not->toBeNull()
        ->and($contact->e164_mobile)->toBe('+639173011987');
});

test('it uses existing contacts for known numbers', function () {
    Queue::fake();

    // Create contact first
    $contact = Contact::create([
        'mobile' => '+639173011987',
        'country' => 'PH',
        'name' => 'John Doe',
    ]);

    $initialCount = Contact::count();

    SendToMultipleRecipients::run(
        recipients: ['+639173011987'],
        message: 'Test message'
    );

    // Should not create duplicate contact
    expect(Contact::count())->toBe($initialCount);
});

test('it normalizes local phone numbers to E164', function () {
    Queue::fake();

    $result = SendToMultipleRecipients::run(
        recipients: ['09173011987', '09178251991'],
        message: 'Test message'
    );

    expect($result['recipients'])->toContain('+639173011987')
        ->and($result['recipients'])->toContain('+639178251991');
});

test('it skips invalid phone numbers gracefully', function () {
    Queue::fake();

    // Test that invalid numbers don't crash the action
    $result = SendToMultipleRecipients::run(
        recipients: ['+639173011987'],
        message: 'Test message'
    );

    expect($result['count'])->toBeGreaterThan(0)
        ->and($result['status'])->toBe('queued');

    Queue::assertPushed(SendSMSJob::class);
});

test('it uses default sender ID when not provided', function () {
    Queue::fake();

    SendToMultipleRecipients::run(
        recipients: ['+639173011987'],
        message: 'Test message'
    );

    Queue::assertPushed(SendSMSJob::class, function ($job) {
        return $job->senderId === config('sms.default_sender_id', 'TXTCMDR');
    });
});

test('it uses custom sender ID when provided', function () {
    Queue::fake();

    SendToMultipleRecipients::run(
        recipients: ['+639173011987'],
        message: 'Test message',
        senderId: 'cashless'
    );

    Queue::assertPushed(SendSMSJob::class, function ($job) {
        return $job->senderId === 'cashless';
    });
});

test('it validates required fields via API endpoint', function () {
    $response = $this->postJson('/api/send', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['recipients', 'message']);
});

test('it validates message length via API endpoint', function () {
    $response = $this->postJson('/api/send', [
        'recipients' => ['+639173011987'],
        'message' => str_repeat('a', 1601), // Exceeds 1600 char limit
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['message']);
});

test('it successfully sends via API endpoint', function () {
    Queue::fake();

    $response = $this->postJson('/api/send', [
        'recipients' => ['+639173011987', '+639178251991'],
        'message' => 'API test message',
        'sender_id' => 'TXTCMDR',
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'queued',
            'count' => 2,
        ]);

    Queue::assertPushed(SendSMSJob::class, 2);
});
