<?php

namespace App\Actions;

use App\Models\ScheduledMessage;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

class CancelScheduledMessage
{
    use AsAction;

    public function handle(int $id): ScheduledMessage
    {
        $scheduledMessage = ScheduledMessage::findOrFail($id);

        if (!$scheduledMessage->isCancellable()) {
            abort(422, 'Cannot cancel this message');
        }

        $scheduledMessage->update(['status' => 'cancelled']);

        return $scheduledMessage;
    }

    public function asController(int $id): JsonResponse
    {
        $scheduledMessage = $this->handle($id);

        return response()->json([
            'message' => 'Scheduled message cancelled',
            'scheduled_message' => $scheduledMessage,
        ], 200);
    }
}
