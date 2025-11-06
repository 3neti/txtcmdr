<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSmsConfig extends Model
{
    protected $fillable = [
        'user_id',
        'driver',
        'credentials',
        'default_sender_id',
        'sender_ids',
        'is_active',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'sender_ids' => 'array',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get a specific credential value
     */
    public function getCredential(string $key, mixed $default = null): mixed
    {
        return $this->credentials[$key] ?? $default;
    }

    /**
     * Check if all required credentials are present
     */
    public function hasRequiredCredentials(): bool
    {
        if (! $this->credentials) {
            return false;
        }

        // Driver-specific required fields
        $required = match ($this->driver) {
            'engagespark' => ['api_key', 'org_id'],
            default => [],
        };

        foreach ($required as $field) {
            if (empty($this->credentials[$field])) {
                return false;
            }
        }

        return true;
    }
}
