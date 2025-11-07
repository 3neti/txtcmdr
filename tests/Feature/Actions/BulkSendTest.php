<?php

use App\Actions\SMS\BulkSendFromFile;
use App\Actions\SMS\BulkSendPersonalized;
use App\Jobs\SendSMSJob;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    Storage::fake('local');
});

describe('BulkSendFromFile', function () {
    test('it sends SMS to all valid numbers in CSV file', function () {
        Queue::fake();

        // Create CSV file with phone numbers
        $csv = "mobile\n+639173011987\n+639178251991\n09171234567";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

        $result = BulkSendFromFile::run(
            file: $file,
            message: 'Bulk test message',
            senderId: 'TXTCMDR',
            userId: $this->user->id,
            mobileColumn: 'mobile'
        );

        expect($result['status'])->toBe('queued')
            ->and($result['queued'])->toBe(3)
            ->and($result['invalid'])->toBe(0)
            ->and($result['recipients'])->toHaveCount(3);

        Queue::assertPushed(SendSMSJob::class, 3);
    });

    test('it normalizes local phone numbers', function () {
        Queue::fake();

        $csv = "mobile\n09173011987\n09178251991";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

        $result = BulkSendFromFile::run(
            file: $file,
            message: 'Test message',
            senderId: 'TXTCMDR',
            userId: $this->user->id
        );

        expect($result['recipients'])->toContain('+639173011987')
            ->and($result['recipients'])->toContain('+639178251991');
    });

    test('it skips invalid phone numbers', function () {
        Queue::fake();

        $csv = "mobile\n+639173011987\ninvalid\n1234\n+639178251991";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

        $result = BulkSendFromFile::run(
            file: $file,
            message: 'Test message',
            senderId: 'TXTCMDR',
            userId: $this->user->id
        );

        expect($result['queued'])->toBe(2)
            ->and($result['invalid'])->toBe(2);

        Queue::assertPushed(SendSMSJob::class, 2);
    });

    test('it uses custom mobile column name', function () {
        Queue::fake();

        $csv = "phone_number\n+639173011987\n+639178251991";
        $file = UploadedFile::fake()->createWithContent('contacts.csv', $csv);

        $result = BulkSendFromFile::run(
            file: $file,
            message: 'Test message',
            senderId: 'TXTCMDR',
            userId: $this->user->id,
            mobileColumn: 'phone_number'
        );

        expect($result['queued'])->toBe(2);
    });

    test('it validates file upload via API endpoint', function () {
        $response = $this->postJson('/api/sms/bulk-send', [
            'message' => 'Test message',
            'sender_id' => 'TXTCMDR',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    });

    test('it validates file type via API endpoint', function () {
        $file = UploadedFile::fake()->create('document.pdf', 100);

        $response = $this->postJson('/api/sms/bulk-send', [
            'file' => $file,
            'message' => 'Test message',
            'sender_id' => 'TXTCMDR',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    });
});

describe('BulkSendPersonalized', function () {
    test('it sends personalized messages from 2-column CSV (mobile, message)', function () {
        Queue::fake();

        $csv = "mobile,message\n+639173011987,Your OTP is 123456\n+639178251991,Your payment was received";
        $file = UploadedFile::fake()->createWithContent('messages.csv', $csv);

        $result = BulkSendPersonalized::run(
            file: $file,
            senderId: 'TXTCMDR',
            userId: $this->user->id,
            importContacts: false
        );

        expect($result['status'])->toBe('queued')
            ->and($result['queued'])->toBe(2)
            ->and($result['invalid'])->toBe(0)
            ->and($result['format'])->toBe('mobile_message');

        Queue::assertPushed(SendSMSJob::class, 2);

        // Verify different messages were queued
        Queue::assertPushed(SendSMSJob::class, function ($job) {
            return $job->message === 'Your OTP is 123456';
        });
        Queue::assertPushed(SendSMSJob::class, function ($job) {
            return $job->message === 'Your payment was received';
        });
    });

    test('it sends personalized messages from 3-column CSV with variable substitution', function () {
        Queue::fake();

        $csv = "mobile,name,message\n+639173011987,Juan,\"Hi {{name}}! Your account {{mobile}} is activated.\"\n+639178251991,Maria,\"Hello {{name}}, contact {{mobile}} for support.\"";
        $file = UploadedFile::fake()->createWithContent('messages.csv', $csv);

        $result = BulkSendPersonalized::run(
            file: $file,
            senderId: 'TXTCMDR',
            userId: $this->user->id,
            importContacts: false
        );

        expect($result['format'])->toBe('mobile_name_message')
            ->and($result['queued'])->toBe(2);

        // Verify variable substitution
        Queue::assertPushed(SendSMSJob::class, function ($job) {
            return $job->message === 'Hi Juan! Your account +639173011987 is activated.';
        });
        Queue::assertPushed(SendSMSJob::class, function ($job) {
            return $job->message === 'Hello Maria, contact +639178251991 for support.';
        });
    });

    test('it imports contacts when enabled', function () {
        Queue::fake();

        expect(Contact::count())->toBe(0);

        $csv = "mobile,name,message\n+639173011987,Juan,Test message\n+639178251991,Maria,Test message";
        $file = UploadedFile::fake()->createWithContent('messages.csv', $csv);

        $result = BulkSendPersonalized::run(
            file: $file,
            senderId: 'TXTCMDR',
            userId: $this->user->id,
            importContacts: true
        );

        expect($result['contacts_imported'])->toBe(2);
        expect(Contact::count())->toBe(2);

        $juan = Contact::where('meta->name', 'Juan')->first();
        expect($juan)->not->toBeNull()
            ->and($juan->e164_mobile)->toBe('+639173011987');
    });

    test('it does not import contacts when disabled', function () {
        Queue::fake();

        $csv = "mobile,name,message\n+639173011987,Juan,Test message";
        $file = UploadedFile::fake()->createWithContent('messages.csv', $csv);

        $result = BulkSendPersonalized::run(
            file: $file,
            senderId: 'TXTCMDR',
            userId: $this->user->id,
            importContacts: false
        );

        expect($result['contacts_imported'])->toBe(0);
        expect(Contact::count())->toBe(0);
    });

    test('it skips invalid rows', function () {
        Queue::fake();

        $csv = "mobile,message\n+639173011987,Valid message\ninvalid,Should skip\n,Empty mobile";
        $file = UploadedFile::fake()->createWithContent('messages.csv', $csv);

        $result = BulkSendPersonalized::run(
            file: $file,
            senderId: 'TXTCMDR',
            userId: $this->user->id,
            importContacts: false
        );

        expect($result['queued'])->toBe(1)
            ->and($result['invalid'])->toBe(2);

        Queue::assertPushed(SendSMSJob::class, 1);
    });

    test('it throws exception for invalid CSV structure', function () {
        $csv = "mobile,name,email,message\n+639173011987,Juan,juan@example.com,Test";
        $file = UploadedFile::fake()->createWithContent('invalid.csv', $csv);

        BulkSendPersonalized::run(
            file: $file,
            senderId: 'TXTCMDR',
            userId: $this->user->id,
            importContacts: false
        );
    })->throws(\InvalidArgumentException::class, 'Invalid CSV structure');

    test('it throws exception for empty file', function () {
        $csv = "mobile,message\n";
        $file = UploadedFile::fake()->createWithContent('empty.csv', $csv);

        BulkSendPersonalized::run(
            file: $file,
            senderId: 'TXTCMDR',
            userId: $this->user->id,
            importContacts: false
        );
    })->throws(\InvalidArgumentException::class, 'File is empty');

    test('it validates required fields via API endpoint', function () {
        $response = $this->postJson('/api/sms/bulk-send-personalized', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file', 'sender_id']);
    });
});
