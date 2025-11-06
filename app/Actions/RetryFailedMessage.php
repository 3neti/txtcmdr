<?php

namespace App\Actions;

use App\Jobs\SendSMSJob;
use App\Models\MessageLog;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RetryFailedMessage
{
    use AsAction;

    /**
     * Retry sending a failed message
     */
    public function handle(int $messageLogId): array
    {
        $log = MessageLog::findOrFail($messageLogId);

        // Verify message belongs to authenticated user
        if ($log->user_id !== auth()->id()) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Unauthorized to retry this message.');
        }

        // Verify message is in failed state
        if ($log->status !== 'failed') {
            throw new \InvalidArgumentException('Only failed messages can be retried.');
        }

        // Reset status to pending
        $log->update([
            'status' => 'pending',
            'failed_at' => null,
            'error_message' => null,
        ]);

        // Dispatch new job to send SMS
        SendSMSJob::dispatch(
            $log->recipient,
            $log->message,
            $log->sender_id,
            $log->scheduled_message_id
        );

        return [
            'success' => true,
            'message' => 'Message queued for retry',
            'log_id' => $log->id,
        ];
    }

    public function asController(ActionRequest $request, int $id): JsonResponse
    {
        try {
            $result = $this->handle($id);

            return response()->json($result, 200);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
