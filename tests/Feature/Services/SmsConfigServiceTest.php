<?php

use App\Models\User;
use App\Models\UserSmsConfig;
use App\Services\SmsConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SmsConfigService();
});

it('returns app config when no user config exists', function () {
    $user = User::factory()->create();

    $config = $this->service->getEngageSparkConfig($user);

    expect($config['source'])->toBe('app')
        ->and($config['api_key'])->toBe(config('sms.drivers.engagespark.api_key'))
        ->and($config['org_id'])->toBe(config('sms.drivers.engagespark.org_id'));
});

it('returns user config when active config exists', function () {
    $user = User::factory()->create();
    
    UserSmsConfig::create([
        'user_id' => $user->id,
        'driver' => 'engagespark',
        'credentials' => [
            'api_key' => 'user-api-key-123',
            'org_id' => 'user-org-456',
        ],
        'default_sender_id' => 'UserID',
        'sender_ids' => ['UserID', 'TestID'],
        'is_active' => true,
    ]);

    $config = $this->service->getEngageSparkConfig($user);

    expect($config['source'])->toBe('user')
        ->and($config['api_key'])->toBe('user-api-key-123')
        ->and($config['org_id'])->toBe('user-org-456')
        ->and($config['default_sender_id'])->toBe('UserID')
        ->and($config['sender_ids'])->toBe(['UserID', 'TestID']);
});

it('returns app config when user config is inactive', function () {
    $user = User::factory()->create();
    
    UserSmsConfig::create([
        'user_id' => $user->id,
        'driver' => 'engagespark',
        'credentials' => [
            'api_key' => 'user-api-key-123',
            'org_id' => 'user-org-456',
        ],
        'is_active' => false,
    ]);

    $config = $this->service->getEngageSparkConfig($user);

    expect($config['source'])->toBe('app');
});

it('returns app config when user config missing required credentials', function () {
    $user = User::factory()->create();
    
    UserSmsConfig::create([
        'user_id' => $user->id,
        'driver' => 'engagespark',
        'credentials' => [
            'api_key' => 'user-api-key-123',
            // Missing org_id
        ],
        'is_active' => true,
    ]);

    $config = $this->service->getEngageSparkConfig($user);

    expect($config['source'])->toBe('app');
});

it('encrypts credentials in database', function () {
    $user = User::factory()->create();
    
    $smsConfig = UserSmsConfig::create([
        'user_id' => $user->id,
        'driver' => 'engagespark',
        'credentials' => [
            'api_key' => 'secret-key-123',
            'org_id' => 'secret-org-456',
        ],
        'is_active' => true,
    ]);

    // Check that raw database value is encrypted
    $rawValue = DB::table('user_sms_configs')
        ->where('id', $smsConfig->id)
        ->value('credentials');

    expect($rawValue)->not->toContain('secret-key-123')
        ->and($rawValue)->toContain('eyJ') // Base64 encrypted format
        // Check that model decrypts correctly
        ->and($smsConfig->credentials['api_key'])->toBe('secret-key-123')
        ->and($smsConfig->credentials['org_id'])->toBe('secret-org-456');
});

it('has working user relationship', function () {
    $user = User::factory()->create();
    
    UserSmsConfig::create([
        'user_id' => $user->id,
        'driver' => 'engagespark',
        'credentials' => ['api_key' => 'test', 'org_id' => 'test'],
        'is_active' => true,
    ]);

    expect($user->smsConfig('engagespark'))->toBeInstanceOf(UserSmsConfig::class)
        ->and($user->smsConfig('engagespark')->user_id)->toBe($user->id);
});

it('supports multiple drivers per user', function () {
    $user = User::factory()->create();
    
    // Create EngageSPARK config
    UserSmsConfig::create([
        'user_id' => $user->id,
        'driver' => 'engagespark',
        'credentials' => ['api_key' => 'es-key', 'org_id' => 'es-org'],
        'is_active' => true,
    ]);
    
    // Create Twilio config (future)
    UserSmsConfig::create([
        'user_id' => $user->id,
        'driver' => 'twilio',
        'credentials' => ['account_sid' => 'tw-sid', 'auth_token' => 'tw-token'],
        'is_active' => true,
    ]);

    expect($user->smsConfigs)->toHaveCount(2)
        ->and($user->smsConfig('engagespark')->driver)->toBe('engagespark')
        ->and($user->smsConfig('twilio')->driver)->toBe('twilio');
});
