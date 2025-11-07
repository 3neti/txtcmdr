<?php

namespace App\Actions;

use App\Jobs\SendSMSJob;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Propaganistas\LaravelPhone\PhoneNumber;

class SendToMultipleRecipients
{
    use AsAction;

    public function handle(array|string $recipients, string $message, ?string $senderId = null, ?int $userId = null): array
    {
        $userId = $userId ?? auth()->id();
        $senderId = $senderId ?? config('sms.default_sender_id', 'TXTCMDR');

        // Normalize to array
        $recipientArray = is_string($recipients)
            ? explode(',', $recipients)
            : $recipients;

        $normalizedRecipients = [];
        $dispatchedCount = 0;

        foreach ($recipientArray as $mobile) {
            try {
                // Create PhoneNumber object
                $phone = new PhoneNumber(trim($mobile), 'PH');
                $e164Mobile = $phone->formatE164();

                // Create or get contact for this user
                $contact = Contact::firstOrCreate(
                    ['mobile' => $e164Mobile, 'user_id' => $userId],
                    ['country' => 'PH']
                );

                SendSMSJob::dispatch($e164Mobile, $message, $senderId, null, $userId);

                $normalizedRecipients[] = $e164Mobile;
                $dispatchedCount++;
            } catch (\Exception $e) {
                // Skip invalid numbers
                continue;
            }
        }

        return [
            'status' => 'queued',
            'count' => $dispatchedCount,
            'recipients' => $normalizedRecipients,
            'invalid_count' => count($recipientArray) - count($normalizedRecipients),
        ];
    }

    public function rules(): array
    {
        return [
            'recipients' => 'required',
            'message' => 'required|string|max:1600',
            'sender_id' => 'nullable|string|max:11',
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $result = $this->handle(
            $request->recipients,
            $request->message,
            $request->sender_id ?? null
        );

        return response()->json($result, 200);
    }
}
