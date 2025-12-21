<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Propaganistas\LaravelPhone\PhoneNumber;

class TestOtpApiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'otp:test-api {mobile? : Mobile number to send OTP to} {--user= : Email of user whose API token to use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test OTP API endpoints (/api/otp/request and /api/otp/verify)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔐 OTP API Test Command');
        $this->info('Testing HTTP endpoints: /api/otp/request and /api/otp/verify');
        $this->newLine();

        // Get user for API authentication
        $userEmail = $this->option('user') ?? 'admin@disburse.cash';
        $user = User::where('email', $userEmail)->first();
        
        if (! $user) {
            $this->error("User ({$userEmail}) not found!");
            return self::FAILURE;
        }

        // Create API token
        $token = $user->createToken('otp-api-test-'.now()->timestamp)->plainTextToken;
        $this->info("🔑 Authenticated as: {$user->name} ({$user->email})");
        $this->newLine();

        // Get mobile number
        $mobile = $this->argument('mobile');
        if (! $mobile) {
            $mobile = $this->ask('Enter mobile number (e.g., +639171234567 or 09171234567)');
        }

        // Normalize to E.164 format
        try {
            $phone = new PhoneNumber($mobile, 'PH');
            $e164Mobile = $phone->formatE164();
        } catch (\Exception $e) {
            $this->error("Invalid mobile number: {$mobile}");
            return self::FAILURE;
        }

        $this->info("📱 Testing with mobile: {$e164Mobile}");
        $this->newLine();

        // Test 1: POST /api/otp/request
        $this->info('📡 Testing POST /api/otp/request...');
        
        try {
            $response = Http::withToken($token)
                ->post(config('app.url').'/api/otp/request', [
                    'mobile' => $e164Mobile,
                    'purpose' => 'test',
                ]);

            $this->line("Status: {$response->status()}");
            
            if (! $response->successful()) {
                $this->error('❌ Request failed!');
                $this->line($response->body());
                
                // Clean up token
                $user->tokens()->where('name', 'like', 'otp-api-test-%')->delete();
                
                return self::FAILURE;
            }

            $data = $response->json();
            $verificationId = $data['verification_id'];
            $expiresIn = $data['expires_in'];
            $devCode = $data['dev_code'] ?? null;

            $this->info('✅ Request successful!');
            $this->line("Verification ID: {$verificationId}");
            $this->line("Expires in: {$expiresIn} seconds");
            
            if ($devCode) {
                $this->warn("[DEV] OTP Code: {$devCode}");
            }
            
            $this->newLine();
        } catch (\Exception $e) {
            $this->error("Request failed: {$e->getMessage()}");
            
            // Clean up token
            $user->tokens()->where('name', 'like', 'otp-api-test-%')->delete();
            
            return self::FAILURE;
        }

        $this->info('📱 Check your phone for the SMS...');
        $this->newLine();

        // Wait for user to enter OTP
        $enteredCode = trim($this->ask('Enter the OTP code you received'));

        if (! $enteredCode) {
            $this->error('No code entered. Exiting.');
            
            // Clean up token
            $user->tokens()->where('name', 'like', 'otp-api-test-%')->delete();
            
            return self::FAILURE;
        }

        // Debug output
        $this->line("Debug - Entered code: '{$enteredCode}' (length: ".strlen($enteredCode).')');
        if ($devCode) {
            $this->line("Debug - Dev code: '{$devCode}' (length: ".strlen($devCode).')');
            $this->line("Debug - Codes match: ".($enteredCode === $devCode ? 'YES' : 'NO'));
        }
        $this->newLine();

        // Test 2: POST /api/otp/verify
        $this->info('📡 Testing POST /api/otp/verify...');
        $this->newLine();

        try {
            $response = Http::withToken($token)
                ->post(config('app.url').'/api/otp/verify', [
                    'verification_id' => $verificationId,
                    'code' => $enteredCode,
                ]);

            $this->line("Status: {$response->status()}");

            if (! $response->successful()) {
                $this->error('❌ Verification failed!');
                $this->line($response->body());
                
                // Clean up token
                $user->tokens()->where('name', 'like', 'otp-api-test-%')->delete();
                
                return self::FAILURE;
            }

            $verifyResult = $response->json();
        } catch (\Exception $e) {
            $this->error("Verification failed: {$e->getMessage()}");
            
            // Clean up token
            $user->tokens()->where('name', 'like', 'otp-api-test-%')->delete();
            
            return self::FAILURE;
        }

        // Clean up the token
        $user->tokens()->where('name', 'like', 'otp-api-test-%')->delete();

        // Display result
        if ($verifyResult['ok']) {
            $this->info('✅ SUCCESS! API endpoints working correctly.');
            $this->newLine();
            $this->line("Verification ID: {$verificationId}");
            $this->line("Status: {$verifyResult['reason']}");
            $this->newLine();
            $this->info('🎉 Both /api/otp/request and /api/otp/verify are functioning properly!');

            return self::SUCCESS;
        } else {
            $this->error("❌ Verification FAILED: {$verifyResult['reason']}");
            $this->newLine();

            if (isset($verifyResult['attempts'])) {
                $this->warn("Attempts: {$verifyResult['attempts']}");
            }

            if (isset($verifyResult['status'])) {
                $this->warn("Status: {$verifyResult['status']}");
            }

            return self::FAILURE;
        }
    }
}
