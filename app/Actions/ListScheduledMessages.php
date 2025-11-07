<?php

namespace App\Actions;

use App\Models\ScheduledMessage;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ListScheduledMessages
{
    use AsAction;

    public function handle(string $status = 'all', ?int $userId = null)
    {
        $query = ScheduledMessage::where('user_id', $userId ?? auth()->id())
            ->orderBy('scheduled_at', 'desc');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return $query->paginate(20);
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $status = $request->query('status', 'all');
        $messages = $this->handle($status);

        return response()->json($messages, 200);
    }
}
