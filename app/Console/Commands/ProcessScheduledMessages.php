<?php

namespace App\Console\Commands;

use App\Jobs\ProcessScheduledMessage;
use App\Models\ScheduledMessage;
use Illuminate\Console\Command;

class ProcessScheduledMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'messages:process-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process scheduled messages that are ready to send';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $messages = ScheduledMessage::ready()->get();

        if ($messages->isEmpty()) {
            $this->info('No scheduled messages ready to send');
            return 0;
        }

        foreach ($messages as $message) {
            ProcessScheduledMessage::dispatch($message->id);
        }

        $this->info("Dispatched {$messages->count()} scheduled message(s)");

        return 0;
    }
}
