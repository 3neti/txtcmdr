<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\Group;
use App\Models\ScheduledMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessScheduledMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $scheduledMessageId
    ) {}

    public function handle(): void
    {
        $scheduledMessage = ScheduledMessage::find($this->scheduledMessageId);

        if (!$scheduledMessage || $scheduledMessage->status !== 'pending') {
            return;
        }

        // Update status to processing
        $scheduledMessage->update(['status' => 'processing']);

        $recipientData = $scheduledMessage->recipient_data;
        $numbers = [];

        // Collect all numbers
        if (!empty($recipientData['numbers'])) {
            $numbers = array_merge($numbers, $recipientData['numbers']);
        }

        // Collect numbers from groups
        if (!empty($recipientData['groups'])) {
            foreach ($recipientData['groups'] as $groupData) {
                $group = Group::find($groupData['id']);
                if ($group) {
                    $groupNumbers = $group->contacts()
                        ->pluck('mobile')
                        ->map(function ($mobile) {
                            $contact = Contact::where('mobile', $mobile)->first();
                            return $contact?->e164_mobile;
                        })
                        ->filter()
                        ->toArray();
                    
                    $numbers = array_merge($numbers, $groupNumbers);
                }
            }
        }

        // Remove duplicates
        $numbers = array_unique($numbers);

        // Dispatch individual SMS jobs
        $sentCount = 0;
        $failedCount = 0;
        $errors = [];

        foreach ($numbers as $number) {
            try {
                SendSMSJob::dispatch(
                    $number,
                    $scheduledMessage->message,
                    $scheduledMessage->sender_id
                );
                $sentCount++;
            } catch (\Exception $e) {
                $failedCount++;
                $errors[] = [
                    'number' => $number,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Update scheduled message
        $scheduledMessage->update([
            'status' => 'sent',
            'sent_at' => now(),
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'errors' => $errors,
        ]);
    }
}
