<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LBHurtado\Contact\Models\Contact as BaseContact;
use Propaganistas\LaravelPhone\PhoneNumber;

class Contact extends BaseContact
{
    // Package already provides:
    // - mobile, country, bank_account (fillable columns)
    // - name, email (schemaless via HasAdditionalAttributes trait)
    // - meta (JSON column for schemaless attributes)

    // Relationships
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)
            ->withTimestamps();
    }

    // Accessor: Get E.164 formatted mobile
    public function getE164MobileAttribute(): string
    {
        return (new PhoneNumber($this->mobile, 'PH'))->formatE164();
    }

    // Note: fromPhoneNumber() is already provided by HasMobile trait
    // from the lbhurtado/contact package

    /**
     * Create a contact from array (for bulk import)
     * Uses firstOrCreate to avoid duplicates
     */
    public static function createFromArray(array $data): ?self
    {
        if (empty($data['mobile'])) {
            return null;
        }

        // Normalize phone number
        try {
            $phone = new PhoneNumber($data['mobile'], 'PH');
            $e164Mobile = $phone->formatE164();
        } catch (\Exception $e) {
            return null;
        }

        // Use firstOrCreate to avoid duplicates
        $contact = self::firstOrCreate(
            ['mobile' => $e164Mobile],
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
