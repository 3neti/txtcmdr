<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LBHurtado\Contact\Models\Contact as BaseContact;
use Propaganistas\LaravelPhone\PhoneNumber;

class Contact extends BaseContact
{
    // Package already provides:
    // - mobile, country, bank_account (fillable columns)
    // - name, email (schemaless via HasAdditionalAttributes trait)
    // - meta (JSON column for schemaless attributes)

    // Ensure schemaless attributes are included when serializing to JSON
    protected $appends = ['name', 'email'];

    // Add user_id to fillable
    protected $fillable = [
        'user_id',
        'mobile',
        'country',
        'bank_account',
        'meta',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)
            ->withTimestamps();
    }

    /**
     * Override base package's mobile attribute to use E.164 format
     * The base package uses formatForMobileDialingInCountry() which returns local format (0917...)
     * We need E.164 format (+63917...) for consistency and unique constraints
     */
    protected function mobile(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: function ($value, $attributes) {
                $country = $attributes['country'] ?? 'PH';
                try {
                    return phone($value, $country)->formatE164();
                } catch (\Exception $e) {
                    return $value;
                }
            },
            set: function ($value) {
                $country = $this->country ?? 'PH';
                try {
                    return phone($value, $country)->formatE164();
                } catch (\Exception $e) {
                    return $value;
                }
            }
        );
    }

    // Accessor: Get E.164 formatted mobile (kept for backward compatibility)
    public function getE164MobileAttribute(): string
    {
        return $this->mobile; // Now mobile itself is E.164
    }

    // Note: fromPhoneNumber() is already provided by HasMobile trait
    // from the lbhurtado/contact package

    /**
     * Create a contact from array (for bulk import)
     * Uses firstOrCreate to avoid duplicates per user
     */
    public static function createFromArray(array $data): ?self
    {
        if (empty($data['mobile'])) {
            return null;
        }

        // Ensure user_id is present
        $userId = $data['user_id'] ?? auth()->id();
        if (! $userId) {
            return null;
        }

        // Normalize phone number
        try {
            $phone = new PhoneNumber($data['mobile'], 'PH');
            $e164Mobile = $phone->formatE164();
        } catch (\Exception $e) {
            return null;
        }

        // Use firstOrCreate to avoid duplicates per user
        $contact = self::firstOrCreate(
            ['mobile' => $e164Mobile, 'user_id' => $userId],
            ['country' => 'PH']
        );

        // Update schemaless attributes if provided
        if (isset($data['name'])) {
            $contact->name = $data['name'];
        }
        if (isset($data['email'])) {
            $contact->email = $data['email'];
        }

        $contact->save();

        return $contact;
    }
}
