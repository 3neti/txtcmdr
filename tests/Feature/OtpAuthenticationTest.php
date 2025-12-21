<?php

use App\Models\User;
use App\Models\UserSmsConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('otp.send_sms', false);
});

test('OTP request requires authentication', function () {
    $this->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ])->assertStatus(401);
});

test('OTP verify requires authentication', function () {
    $this->postJson('/api/otp/verify', [
        'verification_id' => '019b4043-ea61-7287-9793-96512a5cfc18',
        'code' => '123456',
    ])->assertStatus(401);
});

test('authenticated user can request OTP', function () {
    $user = User::factory()->create();

    $resp = $this->actingAs($user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ])->assertOk();

    expect($resp->json('verification_id'))->toBeString()
        ->and($resp->json('dev_code'))->toBeString()
        ->and($resp->json('expires_in'))->toBe(300);
});

test('authenticated user can verify OTP', function () {
    $user = User::factory()->create();

    $resp = $this->actingAs($user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ]);

    $id = $resp->json('verification_id');
    $code = $resp->json('dev_code');

    $this->actingAs($user, 'sanctum')->postJson('/api/otp/verify', [
        'verification_id' => $id,
        'code' => $code,
    ])->assertOk()
      ->assertJson(['ok' => true, 'reason' => 'verified']);
});

test('OTP request associates user_id correctly', function () {
    $user = User::factory()->create();

    $resp = $this->actingAs($user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ]);

    $id = $resp->json('verification_id');
    $verification = \App\Models\OtpVerification::find($id);

    expect($verification->user_id)->toBe($user->id);
});

test('different users can request OTP for same mobile', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $resp1 = $this->actingAs($user1, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ])->assertOk();

    $resp2 = $this->actingAs($user2, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ])->assertOk();

    $id1 = $resp1->json('verification_id');
    $id2 = $resp2->json('verification_id');

    $v1 = \App\Models\OtpVerification::find($id1);
    $v2 = \App\Models\OtpVerification::find($id2);

    expect($v1->user_id)->toBe($user1->id)
        ->and($v2->user_id)->toBe($user2->id)
        ->and($id1)->not->toBe($id2);
});

test('uses user SMS credentials when available', function () {
    $user = User::factory()->create();
    
    // Create user SMS config
    UserSmsConfig::create([
        'user_id' => $user->id,
        'driver' => 'engagespark',
        'credentials' => [
            'api_key' => 'user_test_key',
            'org_id' => '12345',
        ],
        'sender_ids' => ['USER_SENDER'],
        'default_sender_id' => 'USER_SENDER',
        'is_active' => true,
    ]);

    config()->set('otp.send_sms', true);
    \Illuminate\Support\Facades\Queue::fake();

    $this->actingAs($user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ]);

    \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\SendSMSJob::class, function ($job) use ($user) {
        return $job->userId === $user->id
            && $job->senderId === 'USER_SENDER';
    });
});

test('rate limiting applies to OTP endpoints', function () {
    $user = User::factory()->create();
    
    // Make 31 requests (exceeds 30/min limit)
    for ($i = 0; $i < 31; $i++) {
        $response = $this->actingAs($user, 'sanctum')->postJson('/api/otp/request', [
            'mobile' => '+639171234567',
        ]);
    }

    // The 31st request should be rate limited
    expect($response->status())->toBe(429);
});

test('invalid token returns 401', function () {
    $this->withHeaders([
        'Authorization' => 'Bearer invalid-token',
    ])->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ])->assertStatus(401);
});

test('expired token returns 401', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token')->plainTextToken;
    
    // Delete the token to simulate expiration
    $user->tokens()->delete();

    $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ])->assertStatus(401);
});

test('user can verify OTP from another user request', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // User1 requests OTP
    $resp = $this->actingAs($user1, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
    ]);

    $id = $resp->json('verification_id');
    $code = $resp->json('dev_code');

    // User2 tries to verify (should work - no user isolation on verification)
    $this->actingAs($user2, 'sanctum')->postJson('/api/otp/verify', [
        'verification_id' => $id,
        'code' => $code,
    ])->assertOk()
      ->assertJson(['ok' => true, 'reason' => 'verified']);
});

test('OTP request captures user context in metadata', function () {
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $resp = $this->actingAs($user, 'sanctum')->postJson('/api/otp/request', [
        'mobile' => '+639171234567',
        'purpose' => 'login',
        'external_ref' => 'app-login-123',
    ]);

    $id = $resp->json('verification_id');
    $verification = \App\Models\OtpVerification::find($id);

    expect($verification->user_id)->toBe($user->id)
        ->and($verification->mobile_e164)->toBe('+639171234567')
        ->and($verification->purpose)->toBe('login')
        ->and($verification->external_ref)->toBe('app-login-123')
        ->and($verification->request_ip)->not->toBeNull()
        ->and($verification->user_agent)->not->toBeNull();
});
