<?php

namespace App\Actions;

use App\Models\MessageLog;
use Illuminate\Support\Facades\Response;
use League\Csv\Writer;
use Lorisleiva\Actions\Concerns\AsAction;
use SplTempFileObject;

class ExportMessageHistory
{
    use AsAction;

    public function handle(int $userId, ?string $status = null, ?string $search = null)
    {
        // Build query with same filters as Message History page
        $query = MessageLog::query()
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        // Search by recipient or message
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('recipient', 'like', "%{$search}%")
                    ->orWhere('message', 'like', "%{$search}%");
            });
        }

        // Get all matching logs
        $logs = $query->get();

        // Create CSV writer
        $csv = Writer::createFromFileObject(new SplTempFileObject);

        // Add CSV header
        $csv->insertOne([
            'Date/Time',
            'Status',
            'From (Sender)',
            'To (Recipient)',
            'Message',
            'Error',
            'Sent At',
            'Failed At',
        ]);

        // Add data rows
        foreach ($logs as $log) {
            // Get contact name if available
            $recipient = $log->recipient;
            if ($log->contact_with_name && $log->contact_with_name->name) {
                $recipient = $log->contact_with_name->name.' ('.$this->formatPhone($recipient).')';
            } else {
                $recipient = $this->formatPhone($recipient);
            }

            $csv->insertOne([
                $log->created_at->format('Y-m-d H:i:s'),
                ucfirst($log->status),
                $log->sender_id,
                $recipient,
                $log->message,
                $log->error_message ?? '',
                $log->sent_at ? $log->sent_at->format('Y-m-d H:i:s') : '',
                $log->failed_at ? $log->failed_at->format('Y-m-d H:i:s') : '',
            ]);
        }

        // Generate filename with timestamp
        $filename = 'message-history-'.now()->format('Y-m-d-His').'.csv';

        // Return CSV download response
        return Response::make($csv->toString(), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }

    private function formatPhone(string $mobile): string
    {
        if (str_starts_with($mobile, '+63')) {
            return '0'.substr($mobile, 3);
        }

        return $mobile;
    }
}
