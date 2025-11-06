<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MessageLog extends Model
{
    protected $fillable = [
        'user_id',
        'recipient',
        'message',
        'status',
        'sender_id',
        'scheduled_message_id',
        'sent_at',
        'failed_at',
        'error_message',
        'cost',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'cost' => 'decimal:4',
    ];

    protected $appends = ['contact_with_name'];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scheduledMessage(): BelongsTo
    {
        return $this->belongsTo(ScheduledMessage::class);
    }

    public function contact(): HasOne
    {
        // Try to match by recipient (could be in E.164 format like +639178251991)
        // But contacts might be stored in local format (09178251991) or E.164
        return $this->hasOne(Contact::class, 'mobile', 'recipient');
    }

    /**
     * Get contact with normalized phone lookup
     * Handles both E.164 (+639...) and local (09...) formats
     */
    public function getContactWithNameAttribute()
    {
        // Try direct match first (E.164 to E.164)
        $contact = Contact::where('mobile', $this->recipient)->first();

        if (! $contact) {
            // Try converting E.164 to local format (e.g., +639178251991 -> 09178251991)
            if (str_starts_with($this->recipient, '+63')) {
                $localFormat = '0'.substr($this->recipient, 3);
                $contact = Contact::where('mobile', $localFormat)->first();
            }
            // Try converting local to E.164 format (e.g., 09178251991 -> +639178251991)
            elseif (str_starts_with($this->recipient, '09')) {
                $e164Format = '+63'.substr($this->recipient, 1);
                $contact = Contact::where('mobile', $e164Format)->first();
            }
        }

        return $contact;
    }

    // Scopes
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    // Helper methods
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $error,
        ]);
    }
}
