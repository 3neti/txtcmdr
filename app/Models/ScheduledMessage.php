<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class ScheduledMessage extends Model
{
    protected $fillable = [
        'message',
        'sender_id',
        'recipient_type',
        'recipient_data',
        'scheduled_at',
        'sent_at',
        'status',
        'total_recipients',
        'sent_count',
        'failed_count',
        'errors',
    ];

    protected $casts = [
        'recipient_data' => 'array',
        'errors' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'pending')
            ->where('scheduled_at', '<=', now());
    }

    // Accessors
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['pending', 'processing']);
    }

    public function isEditable(): bool
    {
        return $this->status === 'pending'
            && $this->scheduled_at->isFuture();
    }

    // Helper to get recipient summary
    public function recipientSummary(): Attribute
    {
        return Attribute::make(
            get: function () {
                return match ($this->recipient_type) {
                    'numbers' => count($this->recipient_data['numbers'] ?? []).' number(s)',
                    'group' => ($this->recipient_data['group_name'] ?? 'Group').' ('.$this->total_recipients.')',
                    'mixed' => $this->total_recipients.' recipient(s)',
                    default => 'Unknown'
                };
            }
        );
    }
}
