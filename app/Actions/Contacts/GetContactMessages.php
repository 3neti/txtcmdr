<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Models\MessageLog;
use Lorisleiva\Actions\Concerns\AsAction;

class GetContactMessages
{
    use AsAction;

    /**
     * Get recent message history for a specific contact
     *
     * @param  int  $contactId  The contact ID
     * @param  int|null  $userId  The user ID (defaults to authenticated user)
     * @param  int  $limit  Maximum number of messages to return
     */
    public function handle(int $contactId, ?int $userId = null, int $limit = 50): array
    {
        $userId = $userId ?? auth()->id();

        // Verify contact belongs to user
        $contact = Contact::where('id', $contactId)
            ->where('user_id', $userId)
            ->firstOrFail();

        // Get messages sent to this contact's mobile number (use E.164 format)
        $messages = MessageLog::where('user_id', $userId)
            ->where('recipient', $contact->e164_mobile)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'message' => $log->message,
                    'status' => $log->status,
                    'sender_id' => $log->sender_id,
                    'sent_at' => $log->sent_at?->toISOString(),
                    'failed_at' => $log->failed_at?->toISOString(),
                    'created_at' => $log->created_at->toISOString(),
                    'error_message' => $log->error_message,
                    'scheduled_message_id' => $log->scheduled_message_id,
                ];
            });

        return [
            'contact' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'mobile' => $contact->mobile,
            ],
            'messages' => $messages,
            'total' => $messages->count(),
        ];
    }
}
