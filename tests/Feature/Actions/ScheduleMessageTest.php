<?php

use App\Actions\ScheduleMessage;
use App\Models\Contact;
use App\Models\Group;
use App\Models\ScheduledMessage;
use App\Models\User;
use Carbon\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('it creates a scheduled message for phone numbers', function () {
    $scheduledAt = Carbon::now()->addHour();

    $scheduledMessage = ScheduleMessage::run(
        recipients: ['+639173011987', '+639178251991'],
        message: 'Scheduled test message',
        scheduledAt: $scheduledAt,
        senderId: 'TXTCMDR'
    );

    expect($scheduledMessage)->toBeInstanceOf(ScheduledMessage::class)
        ->and($scheduledMessage->message)->toBe('Scheduled test message')
        ->and($scheduledMessage->sender_id)->toBe('TXTCMDR')
        ->and($scheduledMessage->recipient_type)->toBe('numbers')
        ->and($scheduledMessage->total_recipients)->toBe(2)
        ->and($scheduledMessage->status)->toBe('pending')
        ->and($scheduledMessage->scheduled_at->timestamp)->toBe($scheduledAt->timestamp);

    // Check recipient data
    $data = $scheduledMessage->recipient_data;
    expect($data['numbers'])->toHaveCount(2)
        ->and($data['numbers'])->toContain('+639173011987')
        ->and($data['numbers'])->toContain('+639178251991');
});

test('it accepts comma-separated string of recipients', function () {
    $scheduledAt = Carbon::now()->addHour();

    $scheduledMessage = ScheduleMessage::run(
        recipients: '+639173011987,+639178251991',
        message: 'Test message',
        scheduledAt: $scheduledAt
    );

    expect($scheduledMessage->total_recipients)->toBe(2);
});

test('it accepts Carbon instance for scheduled_at', function () {
    $scheduledAt = Carbon::now()->addHours(2);

    $scheduledMessage = ScheduleMessage::run(
        recipients: ['+639173011987'],
        message: 'Test message',
        scheduledAt: $scheduledAt
    );

    expect($scheduledMessage->scheduled_at->timestamp)->toBe($scheduledAt->timestamp);
});

test('it accepts date string for scheduled_at', function () {
    $dateString = '2025-12-01 10:00:00';
    $expectedTime = Carbon::parse($dateString);

    $scheduledMessage = ScheduleMessage::run(
        recipients: ['+639173011987'],
        message: 'Test message',
        scheduledAt: $dateString
    );

    expect($scheduledMessage->scheduled_at->timestamp)->toBe($expectedTime->timestamp);
});

test('it schedules messages for groups', function () {
    $group = Group::create(['name' => 'Test Group', 'user_id' => $this->user->id]);
    $contact1 = Contact::create(['mobile' => '+639173011987', 'country' => 'PH']);
    $contact2 = Contact::create(['mobile' => '+639178251991', 'country' => 'PH']);
    $group->contacts()->attach([$contact1->id, $contact2->id]);

    $scheduledAt = Carbon::now()->addHour();

    $scheduledMessage = ScheduleMessage::run(
        recipients: ['Test Group'],
        message: 'Group message',
        scheduledAt: $scheduledAt
    );

    expect($scheduledMessage->recipient_type)->toBe('group')
        ->and($scheduledMessage->total_recipients)->toBe(2)
        ->and($scheduledMessage->recipient_data['group_name'])->toBe('Test Group')
        ->and($scheduledMessage->recipient_data['groups'])->toHaveCount(1);
});

test('it schedules messages for contacts by name', function () {
    Contact::create([
        'mobile' => '+639173011987',
        'country' => 'PH',
        'name' => 'John Doe',
    ]);

    $scheduledAt = Carbon::now()->addHour();

    $scheduledMessage = ScheduleMessage::run(
        recipients: ['John Doe'],
        message: 'Contact message',
        scheduledAt: $scheduledAt
    );

    expect($scheduledMessage->total_recipients)->toBe(1)
        ->and($scheduledMessage->recipient_data['numbers'])->toContain('+639173011987');
});

test('it handles mixed recipients (numbers, contacts, groups)', function () {
    // Create group with 2 contacts
    $group = Group::create(['name' => 'Test Group', 'user_id' => $this->user->id]);
    $contact1 = Contact::create(['mobile' => '+639173011987', 'country' => 'PH']);
    $contact2 = Contact::create(['mobile' => '+639178251991', 'country' => 'PH']);
    $group->contacts()->attach([$contact1->id, $contact2->id]);

    // Create named contact
    Contact::create([
        'mobile' => '+639171234567',
        'country' => 'PH',
        'name' => 'Jane Doe',
    ]);

    $scheduledAt = Carbon::now()->addHour();

    $scheduledMessage = ScheduleMessage::run(
        recipients: ['Test Group', 'Jane Doe', '+639179999999'],
        message: 'Mixed message',
        scheduledAt: $scheduledAt
    );

    expect($scheduledMessage->recipient_type)->toBe('mixed')
        ->and($scheduledMessage->total_recipients)->toBe(4); // 2 from group + 1 contact + 1 number
});

test('it uses default sender ID when not provided', function () {
    $scheduledAt = Carbon::now()->addHour();

    $scheduledMessage = ScheduleMessage::run(
        recipients: ['+639173011987'],
        message: 'Test message',
        scheduledAt: $scheduledAt
    );

    expect($scheduledMessage->sender_id)->toBe(config('sms.default_sender_id', 'TXTCMDR'));
});

test('it normalizes local phone numbers to E164', function () {
    $scheduledAt = Carbon::now()->addHour();

    $scheduledMessage = ScheduleMessage::run(
        recipients: ['09173011987'],
        message: 'Test message',
        scheduledAt: $scheduledAt
    );

    expect($scheduledMessage->recipient_data['numbers'])->toContain('+639173011987');
});

test('it validates required fields via API endpoint', function () {
    $response = $this->postJson('/api/send/schedule', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['recipients', 'message', 'scheduled_at']);
});

test('it validates scheduled_at is in the future via API endpoint', function () {
    $response = $this->postJson('/api/send/schedule', [
        'recipients' => ['+639173011987'],
        'message' => 'Test message',
        'scheduled_at' => Carbon::now()->subHour()->toIso8601String(), // Past time
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['scheduled_at']);
});

test('it successfully schedules via API endpoint', function () {
    $scheduledAt = Carbon::now()->addHours(2);

    $response = $this->postJson('/api/send/schedule', [
        'recipients' => ['+639173011987', '+639178251991'],
        'message' => 'API scheduled message',
        'scheduled_at' => $scheduledAt->toIso8601String(),
        'sender_id' => 'TXTCMDR',
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'status' => 'scheduled',
            'recipient_count' => 2,
        ])
        ->assertJsonStructure(['id', 'scheduled_at', 'message']);

    expect(ScheduledMessage::count())->toBe(1);
});
