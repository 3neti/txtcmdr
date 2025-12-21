<?php

namespace App\Console\Commands;

use App\Services\Otp\OtpService;
use Illuminate\Console\Command;
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

        // Get user if specified
        $userId = null;
        if ($userEmail = $this->option('user')) {
            $user = \App\Models\User::where('email', $userEmail)->first();
            if (! $user) {
                $this->error("User not found: {$userEmail}");

                return self::FAILURE;
            }
            $userId = $user->id;
            $this->info("Using SMS credentials from: {$user->name} ({$user->email})");
            $this->newLine();
        } else {
            $this->warn('No user specified. Using app-wide SMS credentials from .env');
            $this->newLine();
        }

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

        // Request OTP
        try {
            $result = $otpService->requestOtp(
                mobileE164: $e164Mobile,
                purpose: 'test',
                userId: $userId,
                requestIp: '127.0.0.1',
                userAgent: 'Artisan CLI',
            );
        } catch (\Exception $e) {
            $this->error("Failed to request OTP: {$e->getMessage()}");

            return self::FAILURE;
        }

        $verificationId = $result['verification']->id;
        $expiresIn = $result['expires_in'];

        // Show dev code in local/testing environments
        if (app()->isLocal() || app()->environment('testing')) {
            $this->warn("[DEV] OTP Code: {$result['code']}");
            $this->newLine();
        }

        $this->info("✅ OTP sent! Valid for {$expiresIn} seconds.");
        $this->newLine();

        // Wait for user to enter OTP
        $code = $this->ask('Enter the OTP code you received');

        if (! $code) {
            $this->error('No code entered. Exiting.');

            return self::FAILURE;
        }

        $this->info('🔄 Verifying OTP...');
        $this->newLine();

        // Verify OTP
        try {
            $verifyResult = $otpService->verifyOtp($verificationId, $code);
        } catch (\Exception $e) {
            $this->error("Verification failed: {$e->getMessage()}");

            return self::FAILURE;
        }

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
