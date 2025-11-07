<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Models\UserSmsConfig;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, array_merge([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ], $this->getSmsValidationRules()))->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);

        // Create SMS config if mode is not disabled and data provided
        if (config('registration.sms_config') !== 'disabled' && $this->hasSmsConfig($input)) {
            $this->createSmsConfig($user, $input);
        }

        return $user;
    }

    /**
     * Get SMS validation rules based on registration mode.
     *
     * @return array<string, array<int, string>>
     */
    protected function getSmsValidationRules(): array
    {
        $mode = config('registration.sms_config');

        if ($mode === 'disabled') {
            return [];
        }

        $rule = $mode === 'required' ? 'required' : 'nullable';

        return [
            'sms_api_key' => [$rule, 'string', 'max:255'],
            'sms_org_id' => [$rule, 'string', 'max:255'],
            'sms_default_sender_id' => [$rule, 'string', 'max:255'],
            'sms_sender_ids' => ['nullable', 'string'],
            'sms_is_active' => ['boolean'],
        ];
    }

    /**
     * Check if SMS configuration data is provided.
     */
    protected function hasSmsConfig(array $input): bool
    {
        return ! empty($input['sms_api_key']) && ! empty($input['sms_org_id']);
    }

    /**
     * Create SMS configuration for the user.
     */
    protected function createSmsConfig(User $user, array $input): void
    {
        // Parse sender_ids if provided as comma-separated string
        $senderIds = $input['sms_sender_ids'] ?? '';
        if (is_string($senderIds)) {
            $senderIds = array_filter(
                array_map('trim', explode(',', $senderIds)),
                fn ($id) => ! empty($id)
            );
        }

        UserSmsConfig::create([
            'user_id' => $user->id,
            'driver' => 'engagespark',
            'credentials' => [
                'api_key' => $input['sms_api_key'],
                'org_id' => $input['sms_org_id'],
            ],
            'default_sender_id' => $input['sms_default_sender_id'],
            'sender_ids' => $senderIds,
            'is_active' => $input['sms_is_active'] ?? true,
        ]);
    }
}
