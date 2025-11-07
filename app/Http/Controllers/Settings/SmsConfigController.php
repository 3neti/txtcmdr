<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSmsConfigRequest;
use App\Models\UserSmsConfig;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SmsConfigController extends Controller
{
    /**
     * Display the SMS configuration settings form.
     */
    public function edit(Request $request): Response
    {
        $userConfig = $request->user()->smsConfig('engagespark');

        return Inertia::render('settings/SmsConfig', [
            'userConfig' => $userConfig ? [
                // Send masked versions as placeholders for visibility
                'api_key_masked' => $userConfig->hasRequiredCredentials() ? '••••••••'.substr($userConfig->getCredential('api_key'), -4) : null,
                'org_id_masked' => $userConfig->hasRequiredCredentials() ? '••••••••'.substr($userConfig->getCredential('org_id'), -4) : null,
                'default_sender_id' => $userConfig->default_sender_id,
                'sender_ids' => $userConfig->sender_ids ?? [],
                'is_active' => $userConfig->is_active,
                'has_credentials' => $userConfig->hasRequiredCredentials(),
            ] : null,
            'usesAppDefaults' => ! $userConfig || ! $userConfig->is_active,
        ]);
    }

    /**
     * Update the user's SMS configuration.
     */
    public function update(UpdateSmsConfigRequest $request)
    {
        $user = $request->user();

        // Parse sender_ids if provided as comma-separated string
        $senderIds = $request->input('sender_ids', []);
        if (is_string($senderIds)) {
            $senderIds = array_filter(
                array_map('trim', explode(',', $senderIds)),
                fn ($id) => ! empty($id)
            );
        }

        // Get existing config to preserve credentials if not provided
        $existingConfig = $user->smsConfig('engagespark');
        $credentials = [
            'api_key' => $request->filled('api_key') ? $request->input('api_key') : ($existingConfig?->getCredential('api_key') ?? null),
            'org_id' => $request->filled('org_id') ? $request->input('org_id') : ($existingConfig?->getCredential('org_id') ?? null),
        ];

        UserSmsConfig::updateOrCreate(
            [
                'user_id' => $user->id,
                'driver' => 'engagespark',
            ],
            [
                'credentials' => $credentials,
                'default_sender_id' => $request->input('default_sender_id'),
                'sender_ids' => $senderIds,
                'is_active' => $request->boolean('is_active', true),
            ]
        );

        return back()->with('status', 'sms-config-updated');
    }

    /**
     * Delete the user's SMS configuration.
     */
    public function destroy(Request $request)
    {
        $request->user()->smsConfigs()->where('driver', 'engagespark')->delete();

        return back()->with('status', 'sms-config-deleted');
    }
}
