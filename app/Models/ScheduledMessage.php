<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledMessage extends Model
{
    protected $fillable = [
        'message',
        'recipients',
        'group_ids',
        'sender_id',
        'scheduled_at',
        'status',
    ];

    protected $casts = [
        'recipients' => 'array',
        'group_ids' => 'array',
        'scheduled_at' => 'datetime',
    ];

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDue($query)
    {
        return $query->where('scheduled_at', '<=', now())
                     ->where('status', 'pending');
    }
}
