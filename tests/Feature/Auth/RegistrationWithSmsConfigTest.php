<?php

use App\Models\User;
use App\Models\UserSmsConfig;

beforeEach(function () {
    config(['registration.sms_config' => 'optional']); // Default
});

test('user can register without SMS config when mode is optional', function () {
    config(['registration.sms_config' => 'optional']);

    $response = $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    $this->assertDatabaseMissing('user_sms_configs', [
        'user_id' => User::where('email', 'test@example.com')->first()->id,
    ]);
});

test('user can register with SMS config when mode is optional', function () {
    config(['registration.sms_config' => 'optional']);

    $response = $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'sms_api_key' => 'test-api-key',
        'sms_org_id' => 'test-org-id',
        'sms_default_sender_id' => 'TestSender',
        'sms_sender_ids' => 'sender1, sender2',
        'sms_is_active' => true,
    ]);

    $response->assertRedirect(route('dashboard'));

    $user = User::where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();

    $config = UserSmsConfig::where('user_id', $user->id)->first();
    expect($config)->not->toBeNull();
    expect($config->getCredential('api_key'))->toBe('test-api-key');
    expect($config->getCredential('org_id'))->toBe('test-org-id');
    expect($config->default_sender_id)->toBe('TestSender');
    expect($config->sender_ids)->toBe(['sender1', 'sender2']);
    expect($config->is_active)->toBe(true);
});

test('user must provide SMS config when mode is required', function () {
    config(['registration.sms_config' => 'required']);

    $response = $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors(['sms_api_key', 'sms_org_id', 'sms_default_sender_id']);
    $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
});

test('user can register with SMS config when mode is required', function () {
    config(['registration.sms_config' => 'required']);

    $response = $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'sms_api_key' => 'required-api-key',
        'sms_org_id' => 'required-org-id',
        'sms_default_sender_id' => 'RequiredSender',
    ]);

    $response->assertRedirect(route('dashboard'));

    $user = User::where('email', 'test@example.com')->first();
    $config = UserSmsConfig::where('user_id', $user->id)->first();

    expect($config)->not->toBeNull();
    expect($config->getCredential('api_key'))->toBe('required-api-key');
    expect($config->is_active)->toBe(true);
});

test('SMS config is ignored when mode is disabled', function () {
    config(['registration.sms_config' => 'disabled']);

    $response = $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'sms_api_key' => 'should-be-ignored',
        'sms_org_id' => 'should-be-ignored',
        'sms_default_sender_id' => 'ShouldBeIgnored',
    ]);

    $response->assertRedirect(route('dashboard'));

    $user = User::where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();

    $config = UserSmsConfig::where('user_id', $user->id)->first();
    expect($config)->toBeNull(); // No config should be created
});

test('sender_ids are properly parsed during registration', function () {
    config(['registration.sms_config' => 'optional']);

    $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'sms_api_key' => 'test-key',
        'sms_org_id' => 'test-org',
        'sms_default_sender_id' => 'Default',
        'sms_sender_ids' => 'sender1, sender2,  sender3  , sender4',
    ]);

    $user = User::where('email', 'test@example.com')->first();
    $config = UserSmsConfig::where('user_id', $user->id)->first();

    expect($config->sender_ids)->toBe(['sender1', 'sender2', 'sender3', 'sender4']);
});

test('empty sender_ids are filtered during registration', function () {
    config(['registration.sms_config' => 'optional']);

    $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'sms_api_key' => 'test-key',
        'sms_org_id' => 'test-org',
        'sms_default_sender_id' => 'Default',
        'sms_sender_ids' => 'sender1, , sender2,  ,sender3',
    ]);

    $user = User::where('email', 'test@example.com')->first();
    $config = UserSmsConfig::where('user_id', $user->id)->first();

    expect($config->sender_ids)->toHaveCount(3);
    expect($config->sender_ids)->toContain('sender1');
    expect($config->sender_ids)->toContain('sender2');
    expect($config->sender_ids)->toContain('sender3');
});

test('SMS credentials are encrypted during registration', function () {
    config(['registration.sms_config' => 'optional']);

    $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'sms_api_key' => 'plain-api-key-123',
        'sms_org_id' => 'plain-org-id-456',
        'sms_default_sender_id' => 'TestSender',
    ]);

    $user = User::where('email', 'test@example.com')->first();

    // Check raw database value is encrypted
    $rawConfig = \DB::table('user_sms_configs')
        ->where('user_id', $user->id)
        ->first();

    $credentialsString = json_encode(json_decode($rawConfig->credentials, true));

    // Encrypted value should not contain plain text
    expect($credentialsString)->not->toContain('plain-api-key-123');
    expect($credentialsString)->not->toContain('plain-org-id-456');

    // But decrypted value should match
    $config = UserSmsConfig::find($rawConfig->id);
    expect($config->getCredential('api_key'))->toBe('plain-api-key-123');
    expect($config->getCredential('org_id'))->toBe('plain-org-id-456');
});

test('is_active defaults to true when not provided', function () {
    config(['registration.sms_config' => 'optional']);

    $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'sms_api_key' => 'test-key',
        'sms_org_id' => 'test-org',
        'sms_default_sender_id' => 'Default',
        // is_active not provided
    ]);

    $user = User::where('email', 'test@example.com')->first();
    $config = UserSmsConfig::where('user_id', $user->id)->first();

    expect($config->is_active)->toBe(true);
});

test('is_active can be set to false during registration', function () {
    config(['registration.sms_config' => 'optional']);

    $this->post(route('register'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'sms_api_key' => 'test-key',
        'sms_org_id' => 'test-org',
        'sms_default_sender_id' => 'Default',
        'sms_is_active' => false,
    ]);

    $user = User::where('email', 'test@example.com')->first();
    $config = UserSmsConfig::where('user_id', $user->id)->first();

    expect($config->is_active)->toBe(false);
});

test('register view receives correct smsConfigMode prop for optional', function () {
    config(['registration.sms_config' => 'optional']);

    $response = $this->get(route('register'));

    $response->assertInertia(fn ($page) => $page
        ->component('auth/Register')
        ->where('smsConfigMode', 'optional')
    );
});

test('register view receives correct smsConfigMode prop for required', function () {
    config(['registration.sms_config' => 'required']);

    $response = $this->get(route('register'));

    $response->assertInertia(fn ($page) => $page
        ->component('auth/Register')
        ->where('smsConfigMode', 'required')
    );
});

test('register view receives correct smsConfigMode prop for disabled', function () {
    config(['registration.sms_config' => 'disabled']);

    $response = $this->get(route('register'));

    $response->assertInertia(fn ($page) => $page
        ->component('auth/Register')
        ->where('smsConfigMode', 'disabled')
    );
});

test('standard validations still work with SMS config', function () {
    config(['registration.sms_config' => 'optional']);

    $response = $this->post(route('register'), [
        'name' => '', // Missing
        'email' => 'invalid-email', // Invalid format
        'password' => 'short', // Too short
        'password_confirmation' => 'different', // Doesn't match
        'sms_api_key' => 'test-key',
        'sms_org_id' => 'test-org',
        'sms_default_sender_id' => 'Default',
    ]);

    $response->assertSessionHasErrors(['name', 'email', 'password']);
});
