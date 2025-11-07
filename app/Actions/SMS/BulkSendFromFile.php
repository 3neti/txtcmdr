<?php

namespace App\Actions\SMS;

use App\Jobs\SendSMSJob;
use App\Services\FileParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Propaganistas\LaravelPhone\PhoneNumber;

class BulkSendFromFile
{
    use AsAction;

    public function handle(
        UploadedFile $file,
        string $message,
        string $senderId,
        int $userId,
        string $mobileColumn = 'mobile'
    ): array {
        $parser = new FileParser;
        $rows = $parser->parse($file);

        $queued = 0;
        $invalid = 0;
        $recipients = [];

        foreach ($rows as $row) {
            $mobile = $row[$mobileColumn] ?? null;

            if (! $mobile || ! $this->validatePhone($mobile)) {
                $invalid++;

                continue;
            }

            // Normalize to E.164
            try {
                $phone = new PhoneNumber($mobile, 'PH');
                $e164Mobile = $phone->formatE164();

                SendSMSJob::dispatch($e164Mobile, $message, $senderId, null, $userId);

                $recipients[] = $e164Mobile;
                $queued++;
            } catch (\Exception $e) {
                $invalid++;
            }
        }

        return [
            'status' => 'queued',
            'queued' => $queued,
            'invalid' => $invalid,
            'recipients' => $recipients,
        ];
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                // Allow common CSV and Excel extensions
                'mimes:csv,txt,xlsx,xls',
                // And their MIME types as reported by different browsers
                'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel.sheet.macroenabled.12',
                'max:5120', // 5MB
            ],
            'message' => 'required|string|max:1600',
            'sender_id' => 'required|string',
            'mobile_column' => 'nullable|string',
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $result = $this->handle(
            $request->file('file'),
            $request->message,
            $request->sender_id,
            $request->user()->id,
            $request->mobile_column ?? 'mobile'
        );

        return response()->json($result, 202);
    }

    private function validatePhone(string $mobile): bool
    {
        try {
            $phone = new PhoneNumber($mobile, 'PH');

            return $phone->isValid();
        } catch (\Exception $e) {
            return false;
        }
    }
}
