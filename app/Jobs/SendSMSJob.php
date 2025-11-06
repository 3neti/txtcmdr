<?php

namespace App\Jobs;

use App\Jobs\Middleware\CheckBlacklist;
use App\Models\MessageLog;
use App\Services\SmsConfigService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LBHurtado\SMS\Facades\SMS;
use Propaganistas\LaravelPhone\PhoneNumber;

class SendSMSJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $mobile,
        public string $message,
        public string $senderId = 'TXTCMDR',
        public ?int $scheduledMessageId = null,
        public ?int $userId = null
    ) {}

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [new CheckBlacklist($this->mobile)];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Normalize phone number to E.164
        try {
            $phone = new PhoneNumber($this->mobile, 'PH');
            $e164Mobile = $phone->formatE164();
        } catch (\Exception $e) {
            $e164Mobile = $this->mobile;
        }

        // Create message log
        $log = MessageLog::create([
            'user_id' => auth()->id() ?? 1, // Fallback to admin if no auth context
            'recipient' => $e164Mobile,
            'message' => $this->message,
            'status' => 'pending',
            'sender_id' => $this->senderId,
            'scheduled_message_id' => $this->scheduledMessageId,
        ]);

        try {
            // Get user-specific or app-wide SMS config
            $user = $this->userId ? \App\Models\User::find($this->userId) : null;
            $smsConfigService = app(SmsConfigService::class);
            $config = $smsConfigService->getEngageSparkConfig($user);

            // Set runtime config for EngageSPARK
            if ($config['api_key'] && $config['org_id']) {
                config([
                    'engagespark.api_key' => $config['api_key'],
                    'engagespark.org_id' => $config['org_id'],
                ]);
            }

            // Send SMS using resolved config
            SMS::channel('engagespark')
                ->from($this->senderId)
                ->to($this->mobile)
                ->content($this->message)
                ->send();

            // Mark as sent
            $log->markAsSent();
        } catch (\Exception $e) {
            // Mark as failed
            $log->markAsFailed($e->getMessage());

            // Re-throw to trigger job failure handling
            throw $e;
        }
    }
}
