<?php

namespace App\Actions;

use App\Jobs\BroadcastToGroupJob;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class SendToMultipleGroups
{
    use AsAction;

    public function handle(array|string $groups, string $message, ?string $senderId = null, ?int $userId = null): array
    {
        $userId = $userId ?? auth()->id();
        $senderId = $senderId ?? config('sms.default_sender_id', 'TXTCMDR');

        // Normalize to array
        $groupArray = is_string($groups)
            ? explode(',', $groups)
            : $groups;

        // Trim and filter
        $groupArray = array_filter(
            array_map('trim', $groupArray)
        );

        $dispatchedGroups = [];
        $totalContacts = 0;

        foreach ($groupArray as $groupIdentifier) {
            $group = Group::where('name', $groupIdentifier)
                ->orWhere('id', $groupIdentifier)
                ->first();

            if ($group) {
                BroadcastToGroupJob::dispatch(
                    $group->id,
                    $message,
                    $senderId,
                    $userId
                );

                $contactCount = $group->contacts()->count();
                $totalContacts += $contactCount;

                $dispatchedGroups[] = [
                    'id' => $group->id,
                    'name' => $group->name,
                    'contacts' => $contactCount,
                ];
            }
        }

        return [
            'status' => 'queued',
            'groups' => $dispatchedGroups,
            'total_contacts' => $totalContacts,
        ];
    }

    public function rules(): array
    {
        return [
            'groups' => 'required',
            'message' => 'required|string|max:1600',
            'sender_id' => 'nullable|string|max:11',
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $result = $this->handle(
            $request->groups,
            $request->message,
            $request->sender_id ?? null
        );

        return response()->json($result, 200);
    }
}
