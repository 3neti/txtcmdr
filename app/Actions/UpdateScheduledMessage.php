<?php

namespace App\Actions;

use App\Models\ScheduledMessage;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateScheduledMessage
{
    use AsAction;

    public function handle(
        int $id,
        ?string $message = null,
        ?string $scheduledAt = null,
        ?string $senderId = null
    ): ScheduledMessage {
        $scheduledMessage = ScheduledMessage::findOrFail($id);

        // Only allow editing if status is 'pending' and scheduled_at is in the future
        if (! $scheduledMessage->isEditable()) {
            abort(422, 'Cannot edit this scheduled message');
        }

        $updates = [];

        if ($message !== null) {
            $updates['message'] = $message;
        }

        if ($scheduledAt !== null) {
            $updates['scheduled_at'] = Carbon::parse($scheduledAt);
        }

        if ($senderId !== null) {
            $updates['sender_id'] = $senderId;
        }

        $scheduledMessage->update($updates);

        return $scheduledMessage->fresh();
    }

    public function rules(): array
    {
        return [
            'message' => 'sometimes|string|max:1600',
            'scheduled_at' => 'sometimes|date|after:now',
            'sender_id' => 'sometimes|string|max:11',
        ];
    }

    public function asController(ActionRequest $request, int $id): JsonResponse
    {
        $scheduledMessage = $this->handle(
            $id,
            $request->message ?? null,
            $request->scheduled_at ?? null,
            $request->sender_id ?? null
        );

        return response()->json($scheduledMessage, 200);
    }
}
