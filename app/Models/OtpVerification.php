<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'otp_app_id',
        'mobile_e164',
        'purpose',
        'code_hash',
        'expires_at',
        'status',
        'attempts',
        'max_attempts',
        'send_count',
        'last_sent_at',
        'verified_at',
        'request_ip',
        'user_agent',
        'external_ref',
        'meta',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'verified_at' => 'datetime',
        'meta' => 'array',
    ];

    /**
     * Check if the OTP has expired
     */
    public function isExpired(): bool
    {
        return now()->greaterThan($this->expires_at);
    }

    /**
     * Check if the OTP is in a terminal state (cannot be verified)
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, ['verified', 'expired', 'locked'], true);
    }
}
