<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Propaganistas\LaravelPhone\PhoneNumber;

class BlacklistedNumber extends Model
{
    protected $fillable = [
        'mobile',
        'reason',
        'notes',
        'added_by',
    ];

    // Helper: Check if number is blacklisted
    public static function isBlacklisted(string $mobile): bool
    {
        // Normalize to E.164
        try {
            $phone = new PhoneNumber($mobile, 'PH');
            $e164 = $phone->formatE164();
            
            return self::where('mobile', $e164)->exists();
        } catch (\Exception $e) {
            return false;
        }
    }

    // Helper: Add to blacklist
    public static function addToBlacklist(
        string $mobile,
        string $reason = 'opt-out',
        ?string $notes = null,
        string $addedBy = 'system'
    ): self {
        $phone = new PhoneNumber($mobile, 'PH');
        
        return self::firstOrCreate(
            ['mobile' => $phone->formatE164()],
            [
                'reason' => $reason,
                'notes' => $notes,
                'added_by' => $addedBy,
            ]
        );
    }
}
