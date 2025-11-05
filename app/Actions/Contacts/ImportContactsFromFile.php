<?php

namespace App\Actions\Contacts;

use App\Jobs\ContactImportJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ImportContactsFromFile
{
    use AsAction;

    public function handle(
        UploadedFile $file,
        ?int $groupId = null,
        array $columnMapping = []
    ): array {
        // Store file temporarily
        $path = $file->store('imports', 'local');

        // Dispatch job for async processing
        ContactImportJob::dispatch($path, $groupId, $columnMapping);

        return [
            'status' => 'queued',
            'message' => 'Import queued for processing',
            'file_name' => $file->getClientOriginalName(),
        ];
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240', // 10MB max
            'group_id' => 'nullable|exists:groups,id',
            'column_mapping' => 'nullable|array',
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $result = $this->handle(
            $request->file('file'),
            $request->group_id,
            $request->column_mapping ?? []
        );

        return response()->json($result, 202);
    }
}
