<?php

namespace App\Actions\Groups;

use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteGroup
{
    use AsAction;

    public function handle(int $id): bool
    {
        $group = Group::findOrFail($id);
        
        return $group->delete();
    }

    public function asController(int $id): JsonResponse
    {
        $this->handle($id);

        return response()->json([
            'message' => 'Group deleted successfully',
        ], 200);
    }
}
