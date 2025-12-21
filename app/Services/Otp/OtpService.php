<?php

namespace App\Services\Otp;

use App\Models\OtpVerification;
use Illuminate\Support\Str;

class OtpService
{
    /**
     * Request a new OTP verification
     */
    public function requestOtp(
        string $mobileE164,
        string $purpose = 'login',
        ?int $userId = null,
        ?string $externalRef = null,
        array $meta = [],
        ?string $requestIp = null,
        ?string $userAgent = null,
    ): array {
        $digits = (int) config('otp.digits');
        $ttl = (int) config('otp.ttl_seconds');
        $maxAttempts = (int) config('otp.max_attempts');

        $code = $this->generateCode($digits);

        $verification = OtpVerification::create([
            'id' => (string) Str::uuid(),
            'user_id' => $userId,
            'mobile_e164' => $mobileE164,
            'purpose' => $purpose,
            'code_hash' => $this->hashCode($code),
            'expires_at' => now()->addSeconds($ttl),
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => $maxAttempts,
            'request_ip' => $requestIp,
            'user_agent' => $userAgent,
            'external_ref' => $externalRef,
            'meta' => $meta,
        ]);

        return [
            'verification' => $verification,
            'code' => $code,
            'expires_in' => $ttl,
        ];
    }

    /**
     * Verify an OTP code
     */
    public function verifyOtp(string $verificationId, string $code): array
    {
        $v = OtpVerification::find($verificationId);

        if (! $v) {
            return ['ok' => false, 'reason' => 'not_found'];
        }

        if ($v->status === 'verified') {
            return ['ok' => false, 'reason' => 'already_verified'];
        }

        if ($v->status === 'locked') {
            return ['ok' => false, 'reason' => 'locked'];
        }

        if ($v->isExpired()) {
            $v->update(['status' => 'expired']);

            return ['ok' => false, 'reason' => 'expired'];
        }

        if ($v->attempts >= $v->max_attempts) {
            $v->update(['status' => 'locked']);

            return ['ok' => false, 'reason' => 'locked'];
        }

        if (hash_equals($v->code_hash, $this->hashCode($code))) {
            $v->update([
                'status' => 'verified',
                'verified_at' => now(),
            ]);

            return ['ok' => true, 'reason' => 'verified'];
        }

        $v->attempts++;
        if ($v->attempts >= $v->max_attempts) {
            $v->status = 'locked';
        }
        $v->save();

        return [
            'ok' => false,
            'reason' => 'invalid_code',
            'attempts' => $v->attempts,
            'status' => $v->status,
        ];
    }

    /**
     * Generate a random numeric code
     */
    private function generateCode(int $digits): string
    {
        return (string) random_int(10 ** ($digits - 1), (10 ** $digits) - 1);
    }

    /**
     * Hash an OTP code using HMAC-SHA256 with pepper
     */
    private function hashCode(string $code): string
    {
        return hash_hmac('sha256', $code, config('otp.pepper'));
    }
}
