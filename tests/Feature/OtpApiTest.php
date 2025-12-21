<?php

use App\Jobs\SendSMSJob;
use App\Models\OtpVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// Disable SMS sending by default in tests to avoid foreign key issues
// Enable explicitly in SMS-specific tests
beforeEach(function () {
    config()->set('otp.send_sms', false);
    
    // Create authenticated user for all tests
    $this->user = \App\Models\User::factory()->create();
});

test('can request and verify OTP', function () {
    $resp = $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ])->assertOk();

    $id = $resp->json('verification_id');
    $code = $resp->json('dev_code');

    expect($id)->toBeString()
        ->and($code)->toBeString()
        ->and($resp->json('expires_in'))->toBe(300);

    $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/verify', [
        'verification_id' => $id,
        'code' => $code,
    ])->assertOk()
      ->assertJson(['ok' => true, 'reason' => 'verified']);

    expect(OtpVerification::find($id)->status)->toBe('verified');
});

test('locks OTP after max attempts', function () {
    config()->set('otp.max_attempts', 2);

    $resp = $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ]);

    $id = $resp->json('verification_id');

    // First wrong attempt
    $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/verify', [
        'verification_id' => $id,
        'code' => '000000',
    ])->assertJson(['ok' => false, 'reason' => 'invalid_code']);

    // Second wrong attempt
    $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/verify', [
        'verification_id' => $id,
        'code' => '111111',
    ])->assertJson(['ok' => false, 'reason' => 'invalid_code']);

    expect(OtpVerification::find($id)->status)->toBe('locked');

    // Third attempt should return locked
    $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/verify', [
        'verification_id' => $id,
        'code' => '222222',
    ])->assertJson(['ok' => false, 'reason' => 'locked']);
});

test('expires OTP after TTL', function () {
    config()->set('otp.ttl_seconds', 1);

    $resp = $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ]);

    $id = $resp->json('verification_id');
    $code = $resp->json('dev_code');

    $this->travel(2)->seconds();

    $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/verify', [
        'verification_id' => $id,
        'code' => $code,
    ])->assertJson(['ok' => false, 'reason' => 'expired']);

    expect(OtpVerification::find($id)->status)->toBe('expired');
});

test('returns not_found for invalid verification ID', function () {
    $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/verify', [
        'verification_id' => '00000000-0000-0000-0000-000000000000',
        'code' => '123456',
    ])->assertJson(['ok' => false, 'reason' => 'not_found']);
});

test('returns already_verified when trying to verify twice', function () {
    $resp = $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ]);

    $id = $resp->json('verification_id');
    $code = $resp->json('dev_code');

    // First verification
    $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/verify', [
        'verification_id' => $id,
        'code' => $code,
    ])->assertJson(['ok' => true]);

    // Second verification attempt
    $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/verify', [
        'verification_id' => $id,
        'code' => $code,
    ])->assertJson(['ok' => false, 'reason' => 'already_verified']);
});

test('validates required fields for OTP request', function () {
    $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/request', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['mobile']);
});

test('validates required fields for OTP verification', function () {
    $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/verify', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['verification_id', 'code']);
});

test('validates verification_id is UUID', function () {
    $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/verify', [
        'verification_id' => 'not-a-uuid',
        'code' => '123456',
    ])->assertStatus(422)
      ->assertJsonValidationErrors(['verification_id']);
});

test('validates code length constraints', function () {
    $resp = $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ]);

    $id = $resp->json('verification_id');

    // Too short
    $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/verify', [
        'verification_id' => $id,
        'code' => '123',
    ])->assertStatus(422)
      ->assertJsonValidationErrors(['code']);

    // Too long
    $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/verify', [
        'verification_id' => $id,
        'code' => '12345678901',
    ])->assertStatus(422)
      ->assertJsonValidationErrors(['code']);
});

test('stores request metadata correctly', function () {
    $resp = $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
        'purpose' => 'password_reset',
        'external_ref' => 'REF123',
        'meta' => ['source' => 'mobile_app'],
    ]);

    $id = $resp->json('verification_id');
    $verification = OtpVerification::find($id);

    expect($verification->mobile_e164)->toBe('+639171234567')
        ->and($verification->purpose)->toBe('password_reset')
        ->and($verification->external_ref)->toBe('REF123')
        ->and($verification->meta)->toBe(['source' => 'mobile_app'])
        ->and($verification->request_ip)->not->toBeNull();
});

test('dev_code only visible in local environment', function () {
    app()->bind('env', fn () => 'production');

    $resp = $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ]);

    expect($resp->json('dev_code'))->toBeNull();
});

test('increments attempts on invalid code', function () {
    $resp = $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ]);

    $id = $resp->json('verification_id');

    $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/verify', [
        'verification_id' => $id,
        'code' => '000000',
    ])->assertJson(['ok' => false, 'reason' => 'invalid_code', 'attempts' => 1]);

    expect(OtpVerification::find($id)->attempts)->toBe(1);
});

test('generates different codes for each request', function () {
    $resp1 = $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/request', ['mobile' => '+639171234567']);
    $resp2 = $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/request', ['mobile' => '+639171234567']);

    $code1 = $resp1->json('dev_code');
    $code2 = $resp2->json('dev_code');

    expect($code1)->not->toBe($code2);
});

test('supports optional user association', function () {
    $user = \App\Models\User::factory()->create();

    $resp = $this->actingAs($user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ]);

    $id = $resp->json('verification_id');
    $verification = OtpVerification::find($id);

    expect($verification->user_id)->toBe($user->id);
});

test('authenticated user has user_id associated', function () {
    $resp = $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ])->assertOk();

    $id = $resp->json('verification_id');
    $verification = OtpVerification::find($id);

    expect($verification->user_id)->toBe($this->user->id);
});

test('dispatches SMS job when OTP is requested', function () {
    config()->set('otp.send_sms', true); // Enable for this test
    Queue::fake();

    $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
        'purpose' => 'login',
    ])->assertOk();

    Queue::assertPushed(SendSMSJob::class, function ($job) {
        return $job->mobile === '+639171234567'
            && str_contains($job->message, 'login code is:')
            && str_contains($job->message, 'Valid for 5 minutes');
    });
});

test('tracks SMS send count and timestamp', function () {
    config()->set('otp.send_sms', true); // Enable for this test
    Queue::fake();

    $resp = $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ]);

    $id = $resp->json('verification_id');
    $verification = OtpVerification::find($id);

    expect($verification->send_count)->toBe(1)
        ->and($verification->last_sent_at)->not->toBeNull();
});

test('dispatches SMS job with sender ID from user config or fallback', function () {
    config()->set('otp.send_sms', true); // Enable for this test
    Queue::fake();
    config()->set('otp.sender_id', 'OTP_SENDER');

    $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ]);

    Queue::assertPushed(SendSMSJob::class, function ($job) {
        // Should push job with some sender ID (user's config or fallback)
        return !empty($job->senderId);
    });
});

test('builds OTP message with custom template', function () {
    config()->set('otp.send_sms', true); // Enable for this test
    Queue::fake();
    config()->set('otp.message_template', 'Code: {code} for {purpose}. Expires in {minutes}min.');

    $resp = $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
        'purpose' => 'password_reset',
    ]);

    $code = $resp->json('dev_code');

    Queue::assertPushed(SendSMSJob::class, function ($job) use ($code) {
        return $job->message === "Code: {$code} for password_reset. Expires in 5min.";
    });
});

test('can disable SMS sending via config', function () {
    Queue::fake();
    config()->set('otp.send_sms', false);

    $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ])->assertOk();

    Queue::assertNotPushed(SendSMSJob::class);
});

test('passes user_id to SMS job for authenticated requests', function () {
    config()->set('otp.send_sms', true); // Enable for this test
    Queue::fake();
    $user = \App\Models\User::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ]);

    Queue::assertPushed(SendSMSJob::class, function ($job) use ($user) {
        return $job->userId === $user->id;
    });
});

test('authenticated OTP passes user_id to SMS job', function () {
    config()->set('otp.send_sms', true); // Enable for this test
    Queue::fake();

    $this->actingAs($this->user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ]);

    Queue::assertPushed(SendSMSJob::class, function ($job) {
        return $job->userId === $this->user->id;
    });
});
