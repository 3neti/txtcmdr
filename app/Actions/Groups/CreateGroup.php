<?php

namespace App\Actions\Groups;

use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CreateGroup
{
    use AsAction;

    public function handle(string $name, ?string $description = null, ?int $userId = null): Group
    {
        return Group::create([
            'name' => $name,
            'description' => $description,
            'user_id' => $userId ?? auth()->id(),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:groups,name',
            'description' => 'nullable|string|max:500',
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $group = $this->handle(
            $request->name,
            $request->description ?? null
        );

        return response()->json($group, 201);
    }
}
