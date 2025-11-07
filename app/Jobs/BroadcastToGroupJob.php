<?php

namespace App\Jobs;

use App\Models\Group;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BroadcastToGroupJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $groupId,
        public string $message,
        public string $senderId = 'TXTCMDR',
        public ?int $userId = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $group = Group::with('contacts')->findOrFail($this->groupId);

        foreach ($group->contacts as $contact) {
            SendSMSJob::dispatch(
                $contact->e164_mobile,
                $this->message,
                $this->senderId,
                null,
                $this->userId ?? $group->user_id
            );
        }
    }
}
