<?php

namespace App\Console\Commands;

use App\Models\User;
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

        // Get user whose SMS credentials to use
        $userEmail = $this->option('user') ?? 'admin@disburse.cash';
        $user = User::where('email', $userEmail)->first();
        
        if (! $user) {
            $this->error("User ({$userEmail}) not found!");
            return self::FAILURE;
        }

        $this->info("Using SMS credentials from: {$user->name} ({$user->email})");
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

        // Request OTP directly via service
        $this->info('🔄 Generating and sending OTP...');
        $this->newLine();

        try {
            $result = $otpService->requestOtp(
                mobileE164: $e164Mobile,
                purpose: 'test',
                userId: $user->id,
                requestIp: null,
                userAgent: 'otp:test command'
            );

            $verification = $result['verification'];
            $code = $result['code'];
            $expiresIn = $result['expires_in'];
        } catch (\Exception $e) {
            $this->error("Failed to request OTP: {$e->getMessage()}");
            return self::FAILURE;
        }

        // Show dev code in local/testing environments
        if (app()->environment(['local', 'testing'])) {
            $this->warn("[DEV] OTP Code: {$code}");
            $this->newLine();
        }

        $this->info("✅ OTP sent! Valid for {$expiresIn} seconds.");
        $this->info('📱 Check your phone for the SMS...');
        $this->newLine();

        // Wait for user to enter OTP
        $enteredCode = trim($this->ask('Enter the OTP code you received'));

        if (! $enteredCode) {
            $this->error('No code entered. Exiting.');
            return self::FAILURE;
        }

        $this->info('🔄 Verifying OTP...');
        $this->newLine();

        // Verify OTP directly via service
        try {
            $verifyResult = $otpService->verifyOtp($verification->id, $enteredCode);
        } catch (\Exception $e) {
            $this->error("Verification failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        // Display result
        if ($verifyResult['ok']) {
            $this->info('✅ SUCCESS! OTP verified correctly.');
            $this->newLine();
            $this->line("Verification ID: {$verification->id}");
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
