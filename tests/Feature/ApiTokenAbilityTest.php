<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    
    // Disable SMS sending in tests
    config(['otp.send_sms' => false]);
});

test('API request without required ability returns 403', function () {
    // Create token with only otp:request ability
    $token = $this->user->createToken('test-token', ['otp:request']);

    // Try to verify OTP (requires otp:verify ability)
    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/otp/verify', [
            'verification_id' => 'some-uuid',
            'code' => '123456',
        ]);

    $response->assertStatus(403);
});

test('API request with correct ability succeeds', function () {
    // Create token with otp:request ability
    $token = $this->user->createToken('test-token', ['otp:request']);

    // Request OTP (requires otp:request ability) - should work
    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/otp/request', [
            'mobile' => '+639173011987',
        ]);

    $response->assertStatus(200);
});

test('token with groups:read can list groups but not create', function () {
    // Create token with only read permission
    $token = $this->user->createToken('test-token', ['groups:read']);

    // List groups should work
    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/groups');

    $response->assertStatus(200);

    // Create group should fail
    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/groups', [
            'name' => 'Test Group',
        ]);

    $response->assertStatus(403);
});

test('token with groups:write can create groups', function () {
    // Create token with write permission
    $token = $this->user->createToken('test-token', ['groups:write']);

    // Create group should work
    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/groups', [
            'name' => 'Test Group',
        ]);

    $response->assertStatus(201);
});

test('token with wildcard ability has all permissions', function () {
    // Create token with wildcard
    $token = $this->user->createToken('test-token', ['*']);

    // Should be able to do anything
    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/otp/request', [
            'mobile' => '+639173011987',
        ]);

    $response->assertStatus(200);

    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/groups');

    $response->assertStatus(200);
});

test('token with multiple abilities can access multiple endpoints', function () {
    // Create token with multiple abilities
    $token = $this->user->createToken('test-token', [
        'otp:request',
        'otp:verify',
        'sms:send',
    ]);

    // All these should work
    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/otp/request', [
            'mobile' => '+639173011987',
        ]);
    $response->assertStatus(200);

    // But this should fail (no groups:read)
    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/groups');
    $response->assertStatus(403);
});
