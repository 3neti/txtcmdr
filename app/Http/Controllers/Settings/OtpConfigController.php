<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\Otp\OtpService;
use App\Services\SmsConfigService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OtpConfigController extends Controller
{
    public function edit(Request $request, SmsConfigService $smsConfigService)
    {
        $user = $request->user();
        
        // Get current sender ID info
        $smsConfig = $smsConfigService->getEngageSparkConfig($user);
        $currentSenderId = $smsConfig['default_sender_id'] ?? config('sms.default_sender_id', 'TXTCMDR');
        
        // Determine source
        $source = match ($smsConfig['source']) {
            'user' => 'user',
            'app' => 'sms_default',
            default => 'fallback',
        };
        
        // If using user config but no default_sender_id, it might be using OTP config
        if ($source === 'user' && ! $smsConfig['default_sender_id']) {
            $source = 'otp_config';
            $currentSenderId = config('otp.sender_id', $currentSenderId);
        }

        return Inertia::render('settings/OtpConfig', [
            'config' => [
                'digits' => config('otp.digits', 6),
                'ttl_minutes' => config('otp.ttl_seconds', 300) / 60,
                'max_attempts' => config('otp.max_attempts', 5),
                'send_sms' => config('otp.send_sms', true),
                'sender_id_source' => $source,
                'current_sender_id' => $currentSenderId,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'digits' => 'required|integer|min:4|max:10',
            'ttl_minutes' => 'required|integer|min:1|max:60',
            'max_attempts' => 'required|integer|min:3|max:10',
            'send_sms' => 'required|boolean',
        ]);

        // Convert TTL from minutes to seconds
        $ttlSeconds = $validated['ttl_minutes'] * 60;

        // Update .env file or use a config cache
        // For now, we'll update via config facade (runtime only)
        // In production, you'd want to persist these to .env or database
        
        config([
            'otp.digits' => $validated['digits'],
            'otp.ttl_seconds' => $ttlSeconds,
            'otp.max_attempts' => $validated['max_attempts'],
            'otp.send_sms' => $validated['send_sms'],
        ]);

        return back()->with('success', 'OTP configuration updated successfully');
    }
}
