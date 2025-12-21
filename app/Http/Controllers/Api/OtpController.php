<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Otp\RequestOtpRequest;
use App\Http\Requests\Otp\VerifyOtpRequest;
use App\Services\Otp\OtpService;

class OtpController extends Controller
{
    /**
     * Request a new OTP
     */
    public function request(RequestOtpRequest $request, OtpService $otp)
    {
        $res = $otp->requestOtp(
            mobileE164: $request->input('mobile'),
            purpose: $request->input('purpose', 'login'),
            userId: optional($request->user())->id,
            externalRef: $request->input('external_ref'),
            meta: (array) $request->input('meta', []),
            requestIp: $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->json([
            'verification_id' => $res['verification']->id,
            'expires_in' => $res['expires_in'],
            'dev_code' => (app()->isLocal() || app()->environment('testing')) ? $res['code'] : null,
        ]);
    }

    /**
     * Verify an OTP code
     */
    public function verify(VerifyOtpRequest $request, OtpService $otp)
    {
        return response()->json(
            $otp->verifyOtp(
                $request->input('verification_id'),
                $request->input('code'),
            )
        );
    }
}
