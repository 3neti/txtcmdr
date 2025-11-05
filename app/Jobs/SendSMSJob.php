<?php

namespace App\Jobs;

use App\Jobs\Middleware\CheckBlacklist;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use LBHurtado\SMS\Facades\SMS;

class SendSMSJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $mobile,
        public string $message,
        public string $senderId = 'TXTCMDR'
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
        SMS::channel('engagespark')
            ->from($this->senderId)
            ->to($this->mobile)
            ->content($this->message)
            ->send();
    }
}
