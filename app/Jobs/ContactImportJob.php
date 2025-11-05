<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Services\FileParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ContactImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $filePath,
        public ?int $groupId = null,
        public array $columnMapping = []
    ) {}

    public function handle()
    {
        $parser = new FileParser;
        $fullPath = Storage::path($this->filePath);
        $rows = $parser->parseFromPath($fullPath);

        $imported = 0;
        $failed = 0;

        foreach ($rows as $row) {
            try {
                $mobile = $row['mobile'] ?? $row['phone'] ?? null;

                if (! $mobile) {
                    $failed++;

                    continue;
                }

                $contact = Contact::createFromArray([
                    'mobile' => $mobile,
                    'name' => $row['name'] ?? null,
                    'email' => $row['email'] ?? null,
                ]);

                if ($contact && $this->groupId) {
                    $contact->groups()->syncWithoutDetaching([$this->groupId]);
                }

                $imported++;
            } catch (\Exception $e) {
                Log::warning('Failed to import contact', [
                    'row' => $row,
                    'error' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        Log::info('Contact import completed', [
            'imported' => $imported,
            'failed' => $failed,
            'group_id' => $this->groupId,
        ]);

        // Clean up temp file
        Storage::delete($this->filePath);
    }
}
