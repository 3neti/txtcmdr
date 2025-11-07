<?php

namespace App\Actions\Groups;

use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

class GetGroup
{
    use AsAction;

    public function handle(int $id, ?int $userId = null): Group
    {
        return Group::where('user_id', $userId ?? auth()->id())
            ->where('id', $id)
            ->with('contacts')
            ->withCount('contacts')
            ->firstOrFail();
    }

    public function asController(int $id): JsonResponse
    {
        $group = $this->handle($id);

        return response()->json($group, 200);
    }
}
