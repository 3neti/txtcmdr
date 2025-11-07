<?php

use App\Models\User;
use App\Models\UserSmsConfig;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('authenticated user can view SMS config page', function () {
    $response = $this->actingAs($this->user)->get(route('sms-config.edit'));

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('settings/SmsConfig')
        ->has('userConfig')
        ->has('usesAppDefaults')
    );
});

test('unauthenticated user is redirected to login', function () {
    $response = $this->get(route('sms-config.edit'));

    $response->assertRedirect(route('login'));
});

test('user can save new SMS configuration', function () {
    $configData = [
        'api_key' => 'test-api-key-123',
        'org_id' => 'test-org-id-456',
        'default_sender_id' => 'TestSender',
        'sender_ids' => 'sender1, sender2, sender3',
        'is_active' => true,
    ];

    $response = $this->actingAs($this->user)
        ->put(route('sms-config.update'), $configData);

    $response->assertRedirect();
    $response->assertSessionHas('status', 'sms-config-updated');

    $this->assertDatabaseHas('user_sms_configs', [
        'user_id' => $this->user->id,
        'driver' => 'engagespark',
        'default_sender_id' => 'TestSender',
        'is_active' => true,
    ]);

    // Verify credentials are stored
    $config = UserSmsConfig::where('user_id', $this->user->id)
        ->where('driver', 'engagespark')
        ->first();

    expect($config)->not->toBeNull();
    expect($config->getCredential('api_key'))->toBe('test-api-key-123');
    expect($config->getCredential('org_id'))->toBe('test-org-id-456');
    expect($config->sender_ids)->toBe(['sender1', 'sender2', 'sender3']);
});

test('user can update existing SMS configuration', function () {
    // Create initial config
    UserSmsConfig::create([
        'user_id' => $this->user->id,
        'driver' => 'engagespark',
        'credentials' => [
            'api_key' => 'old-api-key',
            'org_id' => 'old-org-id',
        ],
        'default_sender_id' => 'OldSender',
        'sender_ids' => ['old1', 'old2'],
        'is_active' => true,
    ]);

    $updatedData = [
        'api_key' => 'new-api-key-789',
        'org_id' => 'new-org-id-012',
        'default_sender_id' => 'NewSender',
        'sender_ids' => 'new1, new2',
        'is_active' => false,
    ];

    $response = $this->actingAs($this->user)
        ->put(route('sms-config.update'), $updatedData);

    $response->assertRedirect();
    $response->assertSessionHas('status', 'sms-config-updated');

    $config = UserSmsConfig::where('user_id', $this->user->id)
        ->where('driver', 'engagespark')
        ->first();

    expect($config->getCredential('api_key'))->toBe('new-api-key-789');
    expect($config->getCredential('org_id'))->toBe('new-org-id-012');
    expect($config->default_sender_id)->toBe('NewSender');
    expect($config->sender_ids)->toBe(['new1', 'new2']);
    expect($config->is_active)->toBe(false);
});

test('user can delete SMS configuration', function () {
    UserSmsConfig::create([
        'user_id' => $this->user->id,
        'driver' => 'engagespark',
        'credentials' => [
            'api_key' => 'test-api-key',
            'org_id' => 'test-org-id',
        ],
        'default_sender_id' => 'TestSender',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->delete(route('sms-config.destroy'));

    $response->assertRedirect();
    $response->assertSessionHas('status', 'sms-config-deleted');

    $this->assertDatabaseMissing('user_sms_configs', [
        'user_id' => $this->user->id,
        'driver' => 'engagespark',
    ]);
});

test('validation fails when default_sender_id is missing', function () {
    $response = $this->actingAs($this->user)
        ->put(route('sms-config.update'), [
            'api_key' => 'test-api-key',
            'org_id' => 'test-org-id',
        ]);

    $response->assertSessionHasErrors('default_sender_id');
});

test('can update config without providing credentials', function () {
    // Create initial config
    UserSmsConfig::create([
        'user_id' => $this->user->id,
        'driver' => 'engagespark',
        'credentials' => [
            'api_key' => 'existing-api-key',
            'org_id' => 'existing-org-id',
        ],
        'default_sender_id' => 'OldSender',
        'sender_ids' => ['old1'],
        'is_active' => true,
    ]);

    // Update without providing api_key or org_id
    $response = $this->actingAs($this->user)
        ->put(route('sms-config.update'), [
            'default_sender_id' => 'NewSender',
            'sender_ids' => 'new1, new2',
            'is_active' => true,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('status', 'sms-config-updated');

    // Verify credentials were preserved
    $config = UserSmsConfig::where('user_id', $this->user->id)->first();
    expect($config->getCredential('api_key'))->toBe('existing-api-key');
    expect($config->getCredential('org_id'))->toBe('existing-org-id');
    expect($config->default_sender_id)->toBe('NewSender');
    expect($config->sender_ids)->toBe(['new1', 'new2']);
});

test('can update only api_key while preserving org_id', function () {
    UserSmsConfig::create([
        'user_id' => $this->user->id,
        'driver' => 'engagespark',
        'credentials' => [
            'api_key' => 'old-key',
            'org_id' => 'old-org',
        ],
        'default_sender_id' => 'Sender',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->put(route('sms-config.update'), [
            'api_key' => 'new-key',
            'default_sender_id' => 'Sender',
            'is_active' => true,
        ]);

    $response->assertRedirect();

    $config = UserSmsConfig::where('user_id', $this->user->id)->first();
    expect($config->getCredential('api_key'))->toBe('new-key');
    expect($config->getCredential('org_id'))->toBe('old-org');
});

test('credentials are encrypted in database', function () {
    $configData = [
        'api_key' => 'plain-api-key-123',
        'org_id' => 'plain-org-id-456',
        'default_sender_id' => 'TestSender',
        'is_active' => true,
    ];

    $this->actingAs($this->user)
        ->put(route('sms-config.update'), $configData);

    // Check raw database value is encrypted
    $rawConfig = \DB::table('user_sms_configs')
        ->where('user_id', $this->user->id)
        ->first();

    $rawCredentials = json_decode($rawConfig->credentials, true);

    // Encrypted value should be a serialized string, not plain text
    $credentialsString = json_encode($rawCredentials);
    expect($credentialsString)->not->toContain('plain-api-key-123');
    expect($credentialsString)->not->toContain('plain-org-id-456');

    // But decrypted value should match
    $config = UserSmsConfig::find($rawConfig->id);
    expect($config->getCredential('api_key'))->toBe('plain-api-key-123');
    expect($config->getCredential('org_id'))->toBe('plain-org-id-456');
});

test('user can only access their own config', function () {
    $otherUser = User::factory()->create();

    UserSmsConfig::create([
        'user_id' => $otherUser->id,
        'driver' => 'engagespark',
        'credentials' => [
            'api_key' => 'other-user-key',
            'org_id' => 'other-user-org',
        ],
        'default_sender_id' => 'OtherSender',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->get(route('sms-config.edit'));

    $response->assertInertia(fn ($page) => $page
        ->where('userConfig', null)
        ->where('usesAppDefaults', true)
    );
});

test('api key and org_id are masked in frontend', function () {
    UserSmsConfig::create([
        'user_id' => $this->user->id,
        'driver' => 'engagespark',
        'credentials' => [
            'api_key' => 'secret-api-key-1234',
            'org_id' => 'secret-org-id-5678',
        ],
        'default_sender_id' => 'TestSender',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->get(route('sms-config.edit'));

    $response->assertInertia(fn ($page) => $page
        ->where('userConfig.api_key_masked', '••••••••1234')
        ->where('userConfig.org_id_masked', '••••••••5678')
        ->where('userConfig.default_sender_id', 'TestSender')
        ->has('userConfig.has_credentials')
    );
});

test('sender_ids are properly parsed from comma-separated string', function () {
    $response = $this->actingAs($this->user)
        ->put(route('sms-config.update'), [
            'api_key' => 'test-key',
            'org_id' => 'test-org',
            'default_sender_id' => 'Default',
            'sender_ids' => 'sender1, sender2,  sender3  , sender4',
            'is_active' => true,
        ]);

    $response->assertRedirect();

    $config = UserSmsConfig::where('user_id', $this->user->id)->first();
    expect($config->sender_ids)->toBe(['sender1', 'sender2', 'sender3', 'sender4']);
});

test('empty sender_ids are filtered out', function () {
    $response = $this->actingAs($this->user)
        ->put(route('sms-config.update'), [
            'api_key' => 'test-key',
            'org_id' => 'test-org',
            'default_sender_id' => 'Default',
            'sender_ids' => 'sender1, , sender2,  ,sender3',
            'is_active' => true,
        ]);

    $response->assertRedirect();

    $config = UserSmsConfig::where('user_id', $this->user->id)->first();
    // Array keys might not be sequential after filtering
    expect($config->sender_ids)->toHaveCount(3);
    expect($config->sender_ids)->toContain('sender1');
    expect($config->sender_ids)->toContain('sender2');
    expect($config->sender_ids)->toContain('sender3');
});

test('uses app defaults is true when no user config exists', function () {
    $response = $this->actingAs($this->user)->get(route('sms-config.edit'));

    $response->assertInertia(fn ($page) => $page
        ->where('usesAppDefaults', true)
    );
});

test('uses app defaults is true when user config is inactive', function () {
    UserSmsConfig::create([
        'user_id' => $this->user->id,
        'driver' => 'engagespark',
        'credentials' => [
            'api_key' => 'test-key',
            'org_id' => 'test-org',
        ],
        'default_sender_id' => 'TestSender',
        'is_active' => false,
    ]);

    $response = $this->actingAs($this->user)->get(route('sms-config.edit'));

    $response->assertInertia(fn ($page) => $page
        ->where('usesAppDefaults', true)
    );
});

test('uses app defaults is false when user config is active', function () {
    UserSmsConfig::create([
        'user_id' => $this->user->id,
        'driver' => 'engagespark',
        'credentials' => [
            'api_key' => 'test-key',
            'org_id' => 'test-org',
        ],
        'default_sender_id' => 'TestSender',
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->get(route('sms-config.edit'));

    $response->assertInertia(fn ($page) => $page
        ->where('usesAppDefaults', false)
    );
});
