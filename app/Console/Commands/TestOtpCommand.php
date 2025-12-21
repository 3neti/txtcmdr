<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Otp\OtpService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Propaganistas\LaravelPhone\PhoneNumber;

class TestOtpCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'otp:test {mobile? : Mobile number to send OTP to} {--user= : Email of user whose SMS credentials to use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test OTP generation and verification flow via SMS';

    /**
     * Execute the console command.
     */
    public function handle(OtpService $otpService): int
    {
        $this->info('🔐 OTP Test Command');
        $this->newLine();

        // Get admin user and create token
        $user = User::where('email', 'admin@disburse.cash')->first();
        if (! $user) {
            $this->error('Admin user (admin@disburse.cash) not found!');

            return self::FAILURE;
        }

        // Create API token for admin
        $token = $user->createToken('otp-test-'.now()->timestamp)->plainTextToken;
        $this->info("Authenticated as: {$user->name} ({$user->email})");
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

        $this->info("📱 Sending OTP to: {$e164Mobile}");
        $this->newLine();

        // Request OTP via API
        $this->info('📡 Requesting OTP via API...');
        $this->newLine();

        try {
            $response = Http::withToken($token)
                ->post(config('app.url').'/api/otp/request', [
                    'mobile' => $e164Mobile,
                    'purpose' => 'test',
                ]);

            if (! $response->successful()) {
                $this->error('API request failed: '.$response->status());
                $this->line($response->body());

                return self::FAILURE;
            }

            $data = $response->json();
            $verificationId = $data['verification_id'];
            $expiresIn = $data['expires_in'];
            $devCode = $data['dev_code'] ?? null;
        } catch (\Exception $e) {
            $this->error("Failed to request OTP: {$e->getMessage()}");

            return self::FAILURE;
        }

        // Show dev code in local/testing environments
        if ($devCode) {
            $this->warn("[DEV] OTP Code: {$devCode}");
            $this->newLine();
        }

        $this->info("✅ OTP request sent! Valid for {$expiresIn} seconds.");
        $this->info('📱 Check your phone for the SMS...');
        $this->newLine();

        // Wait for user to enter OTP
        $code = $this->ask('Enter the OTP code you received');

        if (! $code) {
            $this->error('No code entered. Exiting.');

            return self::FAILURE;
        }

        $this->info('🔄 Verifying OTP...');
        $this->newLine();

        // Verify OTP via API
        try {
            $response = Http::withToken($token)
                ->post(config('app.url').'/api/otp/verify', [
                    'verification_id' => $verificationId,
                    'code' => $code,
                ]);

            if (! $response->successful()) {
                $this->error('API verification failed: '.$response->status());
                $this->line($response->body());

                return self::FAILURE;
            }

            $verifyResult = $response->json();
        } catch (\Exception $e) {
            $this->error("Verification failed: {$e->getMessage()}");

            return self::FAILURE;
        }

        // Clean up the token
        $user->tokens()->where('name', 'like', 'otp-test-%')->delete();

        // Display result
        if ($verifyResult['ok']) {
            $this->info('✅ SUCCESS! OTP verified correctly.');
            $this->newLine();
            $this->line("Verification ID: {$verificationId}");
            $this->line("Status: {$verifyResult['reason']}");

            return self::SUCCESS;
        } else {
            $this->error("❌ FAILED: {$verifyResult['reason']}");
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
