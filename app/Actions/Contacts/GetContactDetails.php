<?php

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Models\Group;
use App\Models\MessageLog;
use Lorisleiva\Actions\Concerns\AsAction;

class GetContactDetails
{
    use AsAction;

    /**
     * Get comprehensive contact details with message statistics and history
     */
    public function handle(int $contactId, ?int $userId = null, ?string $statusFilter = 'all'): array
    {
        $userId = $userId ?? auth()->id();

        // Get contact with groups
        $contact = Contact::where('id', $contactId)
            ->where('user_id', $userId)
            ->with('groups')
            ->firstOrFail();

        // Get message statistics
        $totalMessages = MessageLog::where('user_id', $userId)
            ->where('recipient', $contact->e164_mobile)
            ->count();

        $sentMessages = MessageLog::where('user_id', $userId)
            ->where('recipient', $contact->e164_mobile)
            ->where('status', 'sent')
            ->count();

        $failedMessages = MessageLog::where('user_id', $userId)
            ->where('recipient', $contact->e164_mobile)
            ->where('status', 'failed')
            ->count();

        $lastMessage = MessageLog::where('user_id', $userId)
            ->where('recipient', $contact->e164_mobile)
            ->latest('created_at')
            ->first();

        $successRate = $totalMessages > 0
            ? round(($sentMessages / $totalMessages) * 100, 1)
            : 0;

        // Get paginated messages with optional status filter
        $messagesQuery = MessageLog::where('user_id', $userId)
            ->where('recipient', $contact->e164_mobile);

        if ($statusFilter !== 'all') {
            $messagesQuery->where('status', $statusFilter);
        }

        $messages = $messagesQuery->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        // Get all groups for the user (for potential group assignment)
        $allGroups = Group::where('user_id', $userId)
            ->orderBy('name')
            ->get();

        return [
            'contact' => $contact,
            'stats' => [
                'total' => $totalMessages,
                'sent' => $sentMessages,
                'failed' => $failedMessages,
                'successRate' => $successRate,
                'lastMessageAt' => $lastMessage?->created_at?->toISOString(),
            ],
            'messages' => $messages,
            'allGroups' => $allGroups,
            'currentFilter' => $statusFilter,
        ];
    }
}
