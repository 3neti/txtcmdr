<?php

namespace App\Actions\SMS;

use App\Jobs\SendSMSJob;
use App\Models\Contact;
use App\Services\FileParser;
use App\Services\MessagePersonalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Propaganistas\LaravelPhone\PhoneNumber;

class BulkSendPersonalized
{
    use AsAction;

    /**
     * Process personalized bulk SMS from CSV/XLSX
     *
     * Supports two formats:
     * - 2 columns: mobile, message
     * - 3 columns: mobile, name, message
     *
     * Messages can include variables: {{mobile}}, {{name}}
     */
    public function handle(
        UploadedFile $file,
        string $senderId,
        bool $importContacts = true
    ): array {
        $parser = new FileParser;
        $rows = $parser->parse($file);

        if (empty($rows)) {
            throw new \InvalidArgumentException('File is empty or could not be parsed');
        }

        // Get first row (may have numeric key starting at 1)
        $firstRow = reset($rows);

        // Detect structure from first row
        $structure = $this->detectStructure(array_keys($firstRow));

        $queued = 0;
        $invalid = 0;
        $imported = 0;
        $personalizer = new MessagePersonalizer;

        foreach ($rows as $row) {
            try {
                // Extract data based on structure
                $data = $this->extractRowData($row, $structure);

                if (! $data['mobile'] || ! $data['message']) {
                    $invalid++;

                    continue;
                }

                // Normalize phone number
                $phone = new PhoneNumber($data['mobile'], 'PH');
                $e164Mobile = $phone->formatE164();

                // Import contact if enabled
                if ($importContacts && $data['name']) {
                    $contact = Contact::createFromArray([
                        'mobile' => $e164Mobile,
                        'name' => $data['name'],
                    ]);
                    if ($contact) {
                        $imported++;
                    }
                }

                // Personalize message
                $personalizedMessage = $personalizer->personalize(
                    $data['message'],
                    [
                        'mobile' => $e164Mobile,
                        'name' => $data['name'] ?? '',
                    ]
                );

                // Queue SMS
                SendSMSJob::dispatch($e164Mobile, $personalizedMessage, $senderId);
                $queued++;

            } catch (\Exception $e) {
                $invalid++;
            }
        }

        return [
            'status' => 'queued',
            'queued' => $queued,
            'invalid' => $invalid,
            'format' => $structure,
            'contacts_imported' => $imported,
        ];
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,xlsx,xls|max:5120', // 5MB max
            'sender_id' => 'required|string',
            'import_contacts' => 'nullable|boolean',
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $result = $this->handle(
            $request->file('file'),
            $request->sender_id,
            $request->input('import_contacts', true)
        );

        return response()->json($result, 202);
    }

    /**
     * Detect CSV structure from column headers
     */
    private function detectStructure(array $columns): string
    {
        $count = count($columns);

        // 2-column format: mobile, message
        if ($count === 2 && $columns[0] === 'mobile' && $columns[1] === 'message') {
            return 'mobile_message';
        }

        // 3-column format: mobile, name, message
        if ($count === 3 &&
            $columns[0] === 'mobile' &&
            $columns[1] === 'name' &&
            $columns[2] === 'message') {
            return 'mobile_name_message';
        }

        throw new \InvalidArgumentException(
            'Invalid CSV structure. Expected columns: "mobile,message" OR "mobile,name,message"'
        );
    }

    /**
     * Extract row data based on detected structure
     */
    private function extractRowData(array $row, string $structure): array
    {
        if ($structure === 'mobile_message') {
            return [
                'mobile' => $row['mobile'] ?? null,
                'message' => $row['message'] ?? null,
                'name' => null,
            ];
        }

        // mobile_name_message format
        return [
            'mobile' => $row['mobile'] ?? null,
            'name' => $row['name'] ?? null,
            'message' => $row['message'] ?? null,
        ];
    }
}
