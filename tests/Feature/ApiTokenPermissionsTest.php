<?php

use App\Models\User;
use App\Models\Group;

beforeEach(function () {
    $this->user = User::factory()->create();
    
    // Disable SMS sending in tests
    config(['otp.send_sms' => false]);
});

// ========================================
// OTP Permissions
// ========================================

test('otp:request permission allows requesting OTP', function () {
    $token = $this->user->createToken('test', ['otp:request']);

    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/otp/request', [
            'mobile' => '+639173011987',
        ]);

    $response->assertStatus(200);
});

test('otp:request permission denies verifying OTP', function () {
    $token = $this->user->createToken('test', ['otp:request']);

    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/otp/verify', [
            'verification_id' => fake()->uuid(),
            'code' => '123456',
        ]);

    $response->assertStatus(403);
});

test('otp:verify permission allows verifying OTP', function () {
    $token = $this->user->createToken('test', ['otp:verify']);

    // Create an OTP first
    $otp = \App\Models\OtpVerification::create([
        'user_id' => $this->user->id,
        'mobile_e164' => '+639173011987',
        'purpose' => 'login',
        'code_hash' => hash_hmac('sha256', '123456', config('otp.pepper')),
        'expires_at' => now()->addMinutes(5),
        'status' => 'pending',
        'max_attempts' => 5,
        'attempts' => 0,
    ]);

    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/otp/verify', [
            'verification_id' => $otp->id,
            'code' => '123456',
        ]);

    // Should process (even if verification fails due to wrong code)
    $response->assertStatus(200);
});

test('otp:verify permission denies requesting OTP', function () {
    $token = $this->user->createToken('test', ['otp:verify']);

    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/otp/request', [
            'mobile' => '+639173011987',
        ]);

    $response->assertStatus(403);
});

// ========================================
// SMS Permissions
// ========================================

test('sms:send permission allows sending immediate SMS', function () {
    $token = $this->user->createToken('test', ['sms:send']);

    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/send', [
            'recipients' => ['+639173011987'],
            'message' => 'Test message',
        ]);

    $response->assertStatus(200);
});

test('sms:send permission denies scheduling SMS', function () {
    $token = $this->user->createToken('test', ['sms:send']);

    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/send/schedule', [
            'recipients' => ['+639173011987'],
            'message' => 'Scheduled message',
            'scheduled_at' => now()->addHour()->toISOString(),
        ]);

    $response->assertStatus(403);
});

test('sms:schedule permission allows scheduling SMS', function () {
    $token = $this->user->createToken('test', ['sms:schedule']);

    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/send/schedule', [
            'recipients' => ['+639173011987'],
            'message' => 'Scheduled message',
            'scheduled_at' => now()->addHour()->toISOString(),
        ]);

    $response->assertStatus(201);
});

test('sms:schedule permission denies sending immediate SMS', function () {
    $token = $this->user->createToken('test', ['sms:schedule']);

    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/send', [
            'recipients' => ['+639173011987'],
            'message' => 'Test message',
        ]);

    $response->assertStatus(403);
});

// ========================================
// Groups Permissions
// ========================================

test('groups:read permission allows listing groups', function () {
    $token = $this->user->createToken('test', ['groups:read']);

    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/groups');

    $response->assertStatus(200);
});

test('groups:read permission allows viewing single group', function () {
    $token = $this->user->createToken('test', ['groups:read']);
    $group = Group::factory()->create(['user_id' => $this->user->id]);

    $response = $this->withToken($token->plainTextToken)
        ->getJson("/api/groups/{$group->id}");

    $response->assertStatus(200);
});

test('groups:read permission denies creating groups', function () {
    $token = $this->user->createToken('test', ['groups:read']);

    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/groups', [
            'name' => 'Test Group',
        ]);

    $response->assertStatus(403);
});

test('groups:read permission denies deleting groups', function () {
    $token = $this->user->createToken('test', ['groups:read']);
    $group = Group::factory()->create(['user_id' => $this->user->id]);

    $response = $this->withToken($token->plainTextToken)
        ->deleteJson("/api/groups/{$group->id}");

    $response->assertStatus(403);
});

test('groups:write permission allows creating groups', function () {
    $token = $this->user->createToken('test', ['groups:write']);

    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/groups', [
            'name' => 'Test Group',
        ]);

    $response->assertStatus(201);
});

test('groups:write permission allows deleting groups', function () {
    $token = $this->user->createToken('test', ['groups:write']);
    $group = Group::factory()->create(['user_id' => $this->user->id]);

    $response = $this->withToken($token->plainTextToken)
        ->deleteJson("/api/groups/{$group->id}");

    $response->assertStatus(200);
});

test('groups:write permission denies listing groups', function () {
    $token = $this->user->createToken('test', ['groups:write']);

    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/groups');

    $response->assertStatus(403);
});

// ========================================
// Contacts Permissions
// ========================================

test('contacts:write permission allows importing contacts', function () {
    $token = $this->user->createToken('test', ['contacts:write']);

    // Create a simple CSV file
    $csv = "mobile,name\n+639173011987,John Doe\n+639178251991,Jane Smith";
    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('contacts.csv', $csv);

    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/contacts/import', [
            'file' => $file,
        ]);

    $response->assertStatus(202); // Queued job returns 202
});

test('contacts:read permission would deny importing contacts', function () {
    $token = $this->user->createToken('test', ['contacts:read']);

    $csv = "mobile,name\n+639173011987,John Doe";
    $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('contacts.csv', $csv);

    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/contacts/import', [
            'file' => $file,
        ]);

    $response->assertStatus(403);
});

// ========================================
// Combined Permissions Tests
// ========================================

test('token with multiple permissions can access all granted endpoints', function () {
    $token = $this->user->createToken('test', [
        'otp:request',
        'otp:verify',
        'sms:send',
        'groups:read',
    ]);

    // Should work
    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/otp/request', ['mobile' => '+639173011987']);
    $response->assertStatus(200);

    // Should work
    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/groups');
    $response->assertStatus(200);

    // Should work
    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/send', [
            'recipients' => ['+639173011987'],
            'message' => 'Test',
        ]);
    $response->assertStatus(200);

    // Should fail (no groups:write)
    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/groups', ['name' => 'Test Group']);
    $response->assertStatus(403);

    // Should fail (no sms:schedule)
    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/send/schedule', [
            'recipients' => ['+639173011987'],
            'message' => 'Test',
            'scheduled_at' => now()->addHour()->toISOString(),
        ]);
    $response->assertStatus(403);
});

test('wildcard permission grants access to all endpoints', function () {
    $token = $this->user->createToken('test', ['*']);

    // All should work
    $endpoints = [
        ['method' => 'post', 'url' => '/api/otp/request', 'data' => ['mobile' => '+639173011987']],
        ['method' => 'get', 'url' => '/api/groups', 'data' => []],
        ['method' => 'post', 'url' => '/api/groups', 'data' => ['name' => 'Test']],
        ['method' => 'post', 'url' => '/api/send', 'data' => ['recipients' => ['+639173011987'], 'message' => 'Test']],
        ['method' => 'post', 'url' => '/api/send/schedule', 'data' => ['recipients' => ['+639173011987'], 'message' => 'Test', 'scheduled_at' => now()->addHour()->toISOString()]],
    ];

    foreach ($endpoints as $endpoint) {
        $response = $this->withToken($token->plainTextToken)
            ->{$endpoint['method'] . 'Json'}($endpoint['url'], $endpoint['data']);
        
        expect($response->status())->toBeIn([200, 201]);
    }
});

test('token without any permissions cannot access any endpoint', function () {
    // Note: Creating a token with empty abilities array
    $token = $this->user->createToken('test', []);

    $response = $this->withToken($token->plainTextToken)
        ->postJson('/api/otp/request', ['mobile' => '+639173011987']);
    $response->assertStatus(403);

    $response = $this->withToken($token->plainTextToken)
        ->getJson('/api/groups');
    $response->assertStatus(403);
});
