<?php

namespace App\Actions\Groups;

use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

class GetGroup
{
    use AsAction;

    public function handle(int $id): Group
    {
        return Group::with('contacts')
            ->withCount('contacts')
            ->findOrFail($id);
    }

    public function asController(int $id): JsonResponse
    {
        $group = $this->handle($id);

        return response()->json($group, 200);
    }
}
