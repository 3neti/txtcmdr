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
}
