<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    /**
     * Available abilities/permissions for API tokens
     */
    public static function availableAbilities(): array
    {
        return [
            [
                'value' => 'otp:request',
                'label' => 'Request OTP',
                'description' => 'Generate and send OTP codes via SMS',
            ],
            [
                'value' => 'otp:verify',
                'label' => 'Verify OTP',
                'description' => 'Verify OTP codes against verification IDs',
            ],
            [
                'value' => 'sms:send',
                'label' => 'Send SMS',
                'description' => 'Send immediate SMS messages to recipients',
            ],
            [
                'value' => 'sms:schedule',
                'label' => 'Schedule SMS',
                'description' => 'Schedule SMS messages for future delivery',
            ],
            [
                'value' => 'groups:read',
                'label' => 'Read Groups',
                'description' => 'View group information and members',
            ],
            [
                'value' => 'groups:write',
                'label' => 'Manage Groups',
                'description' => 'Create, update, and delete contact groups',
            ],
            [
                'value' => 'contacts:read',
                'label' => 'Read Contacts',
                'description' => 'View contact information',
            ],
            [
                'value' => 'contacts:write',
                'label' => 'Manage Contacts',
                'description' => 'Create, update, and delete contacts',
            ],
        ];
    }

    /**
     * Display API tokens management page
     */
    public function index(Request $request): Response
    {
        $tokens = $request->user()
            ->tokens()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities ?? [],
                'last_used_at' => $token->last_used_at?->toISOString(),
                'expires_at' => $token->expires_at?->toISOString(),
                'created_at' => $token->created_at->toISOString(),
            ]);

        return Inertia::render('settings/ApiTokens', [
            'tokens' => $tokens,
            'availableAbilities' => self::availableAbilities(),
        ]);
    }

    /**
     * Create a new API token
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['required', 'array', 'min:1'],
            'abilities.*' => ['required', 'string', 'in:' . implode(',', array_column(self::availableAbilities(), 'value'))],
            'expires_in_days' => ['nullable', 'integer', 'in:30,60,90,180,365'],
        ]);

        // Calculate expiration date
        $expiresAt = $validated['expires_in_days']
            ? Carbon::now()->addDays($validated['expires_in_days'])
            : null;

        // Create token
        $token = $request->user()->createToken(
            name: $validated['name'],
            abilities: $validated['abilities'],
            expiresAt: $expiresAt
        );

        // Get all tokens again to show the updated list
        $tokens = $request->user()
            ->tokens()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'abilities' => $t->abilities ?? [],
                'last_used_at' => $t->last_used_at?->toISOString(),
                'expires_at' => $t->expires_at?->toISOString(),
                'created_at' => $t->created_at->toISOString(),
            ]);

        // Return to index page with plain text token (shown only once)
        return Inertia::render('settings/ApiTokens', [
            'tokens' => $tokens,
            'availableAbilities' => self::availableAbilities(),
            'plainTextToken' => $token->plainTextToken,
        ]);
    }

    /**
     * Revoke an API token
     */
    public function destroy(Request $request, string $tokenId)
    {
        $token = $request->user()
            ->tokens()
            ->findOrFail($tokenId);

        $token->delete();

        return redirect()->route('settings.api-tokens.index')->with([
            'success' => 'API token has been revoked.',
        ]);
    }

    /**
     * Revoke all API tokens for the user
     */
    public function destroyAll(Request $request)
    {
        $count = $request->user()->tokens()->count();
        $request->user()->tokens()->delete();

        return redirect()->route('settings.api-tokens.index')->with([
            'success' => "All {$count} API tokens have been revoked.",
        ]);
    }
}
