<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use LBHurtado\SMS\Facades\SMS;

class TestSMS extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:test {mobile} {message?} {--sender= : Sender ID to use}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test SMS sending via EngageSPARK';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $mobile = $this->argument('mobile');
        $message = $this->argument('message') ?? 'Test message from Text Commander!';
        $sender = $this->option('sender') ?? config('sms.default_sender_id', 'TXTCMDR');
        
        $this->info("Sending SMS to {$mobile} from {$sender}...");
        
        try {
            SMS::channel('engagespark')
                ->from($sender)
                ->to($mobile)
                ->content($message)
                ->send();
            
            $this->info('✅ SMS sent successfully!');
        } catch (\Exception $e) {
            $this->error('❌ Failed to send SMS: ' . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
}
