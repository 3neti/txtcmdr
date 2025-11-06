<?php

namespace App\Actions;

use App\Models\Contact;
use App\Models\Group;
use App\Models\ScheduledMessage;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Propaganistas\LaravelPhone\PhoneNumber;

class ScheduleMessage
{
    use AsAction;

    /**
     * Schedule a message for future delivery
     *
     * @param  array|string  $recipients  Phone numbers, contact names, or group names
     * @param  string  $message  Message content
     * @param  string|Carbon  $scheduledAt  When to send
     * @param  string|null  $senderId  Sender ID
     */
    public function handle(
        array|string $recipients,
        string $message,
        string|Carbon $scheduledAt,
        ?string $senderId = null
    ): ScheduledMessage {
        $senderId = $senderId ?? config('sms.default_sender_id', 'TXTCMDR');
        $scheduledAt = $scheduledAt instanceof Carbon
            ? $scheduledAt
            : Carbon::parse($scheduledAt);

        // Normalize recipients
        $recipientArray = is_string($recipients)
            ? array_map('trim', explode(',', $recipients))
            : $recipients;

        // Parse recipients (can be phone numbers, contacts, or groups)
        $parsedRecipients = $this->parseRecipients($recipientArray);

        return ScheduledMessage::create([
            'message' => $message,
            'sender_id' => $senderId,
            'recipient_type' => $parsedRecipients['type'],
            'recipient_data' => $parsedRecipients['data'],
            'scheduled_at' => $scheduledAt,
            'total_recipients' => $parsedRecipients['count'],
            'status' => 'pending',
        ]);
    }

    public function rules(): array
    {
        return [
            'recipients' => 'required',
            'message' => 'required|string|max:1600',
            'scheduled_at' => 'required|date|after:now',
            'sender_id' => 'nullable|string|max:11',
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $scheduledMessage = $this->handle(
            $request->recipients,
            $request->message,
            $request->scheduled_at,
            $request->sender_id ?? null
        );

        return response()->json([
            'id' => $scheduledMessage->id,
            'status' => 'scheduled',
            'scheduled_at' => $scheduledMessage->scheduled_at->toIso8601String(),
            'recipient_count' => $scheduledMessage->total_recipients,
            'message' => 'Message scheduled successfully',
        ], 201);
    }

    /**
     * Parse recipients into phone numbers and groups
     */
    protected function parseRecipients(array $recipients): array
    {
        $numbers = [];
        $groups = [];
        $totalCount = 0;

        foreach ($recipients as $recipient) {
            // Try to parse as phone number
            try {
                $phone = new PhoneNumber($recipient, 'PH');
                $e164 = $phone->formatE164();

                // Create/get contact and ensure it has country set
                $contact = Contact::firstOrCreate(
                    ['mobile' => $e164],
                    ['country' => 'PH']
                );

                $numbers[] = $e164;
                $totalCount++;

                continue;
            } catch (\Exception $e) {
                // Not a valid phone number
            }

            // Try to find as contact by name
            $contact = Contact::where('meta->name', $recipient)->first();
            if ($contact) {
                // Safely get E.164 format - use our custom accessor
                try {
                    $e164 = (new \Propaganistas\LaravelPhone\PhoneNumber($contact->mobile, $contact->country ?? 'PH'))->formatE164();
                    $numbers[] = $e164;
                    $totalCount++;
                } catch (\Exception $e) {
                    // Skip invalid contact
                }

                continue;
            }

            // Try to find as group
            $group = Group::where('name', $recipient)->first();
            if ($group) {
                $groupContactCount = $group->contacts()->count();
                $groups[] = [
                    'id' => $group->id,
                    'name' => $group->name,
                    'count' => $groupContactCount,
                ];
                $totalCount += $groupContactCount;
            }
        }

        // Determine recipient type
        $type = 'mixed';
        if (empty($groups)) {
            $type = 'numbers';
        } elseif (empty($numbers) && count($groups) === 1) {
            $type = 'group';
        }

        return [
            'type' => $type,
            'data' => [
                'numbers' => $numbers,
                'groups' => $groups,
                'group_name' => $groups[0]['name'] ?? null,
            ],
            'count' => $totalCount,
        ];
    }
}
