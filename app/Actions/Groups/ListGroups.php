<?php

namespace App\Actions\Groups;

use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

class ListGroups
{
    use AsAction;

    public function handle(?int $userId = null)
    {
        return Group::where('user_id', $userId ?? auth()->id())
            ->withCount('contacts')
            ->orderBy('name')
            ->get();
    }

    public function asController(): JsonResponse
    {
        $groups = $this->handle();

        return response()->json($groups, 200);
    }
}
