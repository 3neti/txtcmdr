# SMS Settings UI Implementation Outline

## Overview
This document outlines the implementation plan for a user interface that allows users to configure their SMS credentials (EngageSPARK API Key, Organization ID, Sender ID, and available sender IDs) through the application's settings section.

**Pattern:** This follows the existing Laravel settings structure in this application:
- Route pattern: `/settings/sms` (alongside `/settings/profile`, `/settings/password`, `/settings/appearance`)
- Controller pattern: Settings namespace controllers (e.g., `ProfileController`, `PasswordController`)
- Layout pattern: Uses `SettingsLayout.vue` with left navigation menu
- Page pattern: Uses `AppLayout` → `SettingsLayout` → form with `HeadingSmall`

## Context
The backend infrastructure for user-specific SMS configuration already exists:
- `UserSmsConfig` model with encrypted credential storage
- `SmsConfigService` with hybrid fallback logic (user config → app config)
- Database migration with `user_sms_configs` table
- Integration with `SendSMSJob` and `SendToMultipleRecipients`
- Comprehensive test coverage

The admin user is already seeded with credentials from `.env` via `UserSeeder`.

## Goals
1. Follow existing settings page patterns (Profile, Password, Appearance)
2. Add "SMS" to the left navigation menu in settings
3. Use shadcn-vue components, Reka UI, and Tailwind (existing UI patterns)
4. Ensure secure handling of sensitive credentials (password inputs)
5. Support the hybrid fallback approach (user credentials override app defaults)

## Implementation Plan

### 1. Backend Components

#### A. Controller
**File:** `app/Http/Controllers/Settings/SmsConfigController.php`

**Pattern:** Follow `ProfileController` and `PasswordController` structure

**Methods:**
- `edit()` - Display the SMS settings form with current user config (if any)
  - Return `Inertia::render('settings/SmsConfig', [props])`
  - Props: `userConfig`, `usesAppDefaults`
- `update(UpdateSmsConfigRequest $request)` - Save/update user's SMS configuration
  - Validate via form request
  - Use `UserSmsConfig::updateOrCreate()` with authenticated user ID
  - Redirect back with success message
- `destroy()` - Optional: Delete user's custom SMS config (revert to app defaults)

**Example Structure:**
```php
namespace App\Http\Controllers\Settings;

class SmsConfigController extends Controller
{
    public function edit(Request $request)
    {
        $userConfig = $request->user()->smsConfig('engagespark');
        
        return Inertia::render('settings/SmsConfig', [
            'userConfig' => $userConfig ? [
                'api_key' => '', // Don't send actual values for security
                'org_id' => '',
                'default_sender_id' => $userConfig->default_sender_id,
                'sender_ids' => $userConfig->sender_ids ?? [],
                'is_active' => $userConfig->is_active,
                'has_credentials' => $userConfig->hasRequiredCredentials(),
            ] : null,
            'usesAppDefaults' => !$userConfig || !$userConfig->is_active,
        ]);
    }
    
    public function update(UpdateSmsConfigRequest $request)
    {
        $user = $request->user();
        
        UserSmsConfig::updateOrCreate(
            [
                'user_id' => $user->id,
                'driver' => 'engagespark',
            ],
            [
                'credentials' => [
                    'api_key' => $request->input('api_key'),
                    'org_id' => $request->input('org_id'),
                ],
                'default_sender_id' => $request->input('default_sender_id'),
                'sender_ids' => $request->input('sender_ids', []),
                'is_active' => $request->boolean('is_active', true),
            ]
        );
        
        return back()->with('status', 'sms-config-updated');
    }
    
    public function destroy(Request $request)
    {
        $request->user()->smsConfigs()->where('driver', 'engagespark')->delete();
        
        return back()->with('status', 'sms-config-deleted');
    }
}
```

#### B. Form Request Validation
**File:** `app/Http/Requests/Settings/UpdateSmsConfigRequest.php`

**Pattern:** Similar to other settings form requests

**Validation Rules:**
```php
public function rules(): array
{
    return [
        'api_key' => 'required|string|max:255',
        'org_id' => 'required|string|max:255',
        'default_sender_id' => 'required|string|max:255',
        'sender_ids' => 'nullable|array',
        'sender_ids.*' => 'string|max:255',
        'is_active' => 'boolean',
    ];
}
```

#### C. Routes
**File:** `routes/settings.php` (add to existing settings routes)

**Pattern:** Follow existing route structure (GET for edit, PUT for update)

```php
use App\Http\Controllers\Settings\SmsConfigController;

// Add inside Route::middleware('auth')->group(function () {
Route::get('settings/sms', [SmsConfigController::class, 'edit'])->name('sms-config.edit');
Route::put('settings/sms', [SmsConfigController::class, 'update'])->name('sms-config.update');
Route::delete('settings/sms', [SmsConfigController::class, 'destroy'])->name('sms-config.destroy');
```

### 2. Frontend Components

#### A. Settings Page
**File:** `resources/js/pages/settings/SmsConfig.vue`

**Pattern:** Follow `Profile.vue` and `Password.vue` structure exactly:
- Use `AppLayout` with breadcrumbs
- Nest `SettingsLayout` inside
- Use `HeadingSmall` for title/description
- Use Wayfinder-generated actions for form submission
- Use `Form` component from Inertia with v-slot pattern
- Show "Saved." message on `recentlySuccessful`

**Structure:**
```vue
<script setup lang="ts">
import SmsConfigController from '@/actions/App/Http/Controllers/Settings/SmsConfigController';
import InputError from '@/components/InputError.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Switch } from '@/components/ui/switch';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/sms-config';
import { Form, Head } from '@inertiajs/vue3';
import { type BreadcrumbItem } from '@/types';

interface UserConfig {
    api_key: string;
    org_id: string;
    default_sender_id: string;
    sender_ids: string[];
    is_active: boolean;
    has_credentials: boolean;
}

interface Props {
    userConfig?: UserConfig;
    usesAppDefaults: boolean;
}

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'SMS settings',
        href: edit().url,
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="SMS settings" />

        <SettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="SMS Configuration"
                    description="Configure your EngageSPARK credentials for sending SMS messages"
                />

                <!-- Status Alert -->
                <Alert v-if="usesAppDefaults" variant="default">
                    <AlertDescription>
                        You are currently using the application's default SMS configuration.
                        Add your own credentials below to override the defaults.
                    </AlertDescription>
                </Alert>

                <Form
                    v-bind="SmsConfigController.update.form()"
                    :options="{
                        preserveScroll: true,
                    }"
                    class="space-y-6"
                    v-slot="{ errors, processing, recentlySuccessful }"
                >
                    <!-- API Key -->
                    <div class="grid gap-2">
                        <Label for="api_key">EngageSPARK API Key *</Label>
                        <Input
                            id="api_key"
                            name="api_key"
                            type="password"
                            class="mt-1 block w-full"
                            placeholder="Enter your EngageSPARK API Key"
                            required
                            autocomplete="off"
                        />
                        <InputError :message="errors.api_key" />
                    </div>

                    <!-- Organization ID -->
                    <div class="grid gap-2">
                        <Label for="org_id">EngageSPARK Organization ID *</Label>
                        <Input
                            id="org_id"
                            name="org_id"
                            type="password"
                            class="mt-1 block w-full"
                            placeholder="Enter your EngageSPARK Organization ID"
                            required
                            autocomplete="off"
                        />
                        <InputError :message="errors.org_id" />
                    </div>

                    <!-- Default Sender ID -->
                    <div class="grid gap-2">
                        <Label for="default_sender_id">Default Sender ID *</Label>
                        <Input
                            id="default_sender_id"
                            name="default_sender_id"
                            class="mt-1 block w-full"
                            :default-value="userConfig?.default_sender_id ?? ''"
                            placeholder="e.g., cashless, Quezon City"
                            required
                        />
                        <InputError :message="errors.default_sender_id" />
                    </div>

                    <!-- Additional Sender IDs -->
                    <div class="grid gap-2">
                        <Label for="sender_ids">Additional Sender IDs (comma-separated)</Label>
                        <Input
                            id="sender_ids"
                            name="sender_ids"
                            class="mt-1 block w-full"
                            :default-value="userConfig?.sender_ids?.join(', ') ?? ''"
                            placeholder="sender1, sender2, sender3"
                        />
                        <InputError :message="errors.sender_ids" />
                        <p class="text-sm text-muted-foreground">
                            Optional. Provide a list of alternative sender IDs you can use.
                        </p>
                    </div>

                    <!-- Active Switch -->
                    <div class="flex items-center space-x-2">
                        <Switch
                            id="is_active"
                            name="is_active"
                            :default-checked="userConfig?.is_active ?? true"
                        />
                        <Label for="is_active" class="cursor-pointer">
                            Use my custom SMS configuration
                        </Label>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-4">
                        <Button
                            :disabled="processing"
                            data-test="update-sms-config-button"
                        >
                            Save
                        </Button>

                        <Transition
                            enter-active-class="transition ease-in-out"
                            enter-from-class="opacity-0"
                            leave-active-class="transition ease-in-out"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-show="recentlySuccessful"
                                class="text-sm text-neutral-600"
                            >
                                Saved.
                            </p>
                        </Transition>
                    </div>
                </Form>

                <!-- Delete Configuration Section -->
                <div v-if="userConfig?.has_credentials" class="pt-6 border-t">
                    <HeadingSmall
                        title="Delete SMS Configuration"
                        description="Remove your custom SMS credentials and revert to application defaults"
                    />
                    <Form
                        v-bind="SmsConfigController.destroy.form()"
                        :options="{
                            preserveScroll: true,
                        }"
                        class="mt-4"
                        v-slot="{ processing }"
                    >
                        <Button
                            variant="destructive"
                            :disabled="processing"
                            @click="() => {
                                if (confirm('Are you sure you want to delete your SMS configuration? You will use the application defaults.')) {
                                    // Form will submit
                                }
                            }"
                        >
                            Delete Configuration
                        </Button>
                    </Form>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
```

#### B. Navigation Menu
**File:** `resources/js/layouts/settings/Layout.vue`

**Action:** Add SMS to `sidebarNavItems` array

**Change:**
```typescript
import { edit as editSmsConfig } from '@/routes/sms-config';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: editProfile(),
    },
    {
        title: 'Password',
        href: editPassword(),
    },
    {
        title: 'Two-Factor Auth',
        href: show(),
    },
    {
        title: 'SMS',  // Add this
        href: editSmsConfig(),
    },
    {
        title: 'Appearance',
        href: editAppearance(),
    },
];
```

### 3. Security Considerations

1. **Input Type:** Use `type="password"` for API Key and Organization ID fields
2. **No Pre-fill:** Don't send actual credential values to frontend (security)
3. **Encrypted Storage:** Already handled via `encrypted:array` cast
4. **HTTPS:** Required in production
5. **CSRF Protection:** Automatic via Inertia
6. **Authorization:** Route protected by `auth` middleware
7. **User Isolation:** Controller uses `$request->user()` to ensure users only access their own config

### 4. User Experience Flow

**Following existing settings patterns:**

1. User navigates to Settings (clicks user dropdown → Settings)
2. Sees settings page with left navigation: Profile, Password, Two-Factor Auth, **SMS**, Appearance
3. Clicks "SMS" in left nav
4. Views SMS settings page:
   - If no custom config: Alert shows "Using app defaults", empty form
   - If has config: Form shows default_sender_id and sender_ids, password fields empty
5. User fills/updates credentials and clicks "Save"
6. "Saved." message appears briefly (like Profile and Password pages)
7. Optional: User can delete config to revert to app defaults

### 5. Testing Plan

#### A. Feature Tests
**File:** `tests/Feature/Controllers/Settings/SmsConfigControllerTest.php`

**Tests:**
- ✅ Authenticated user can view SMS config page
- ✅ Unauthenticated user redirected to login
- ✅ User can save new SMS configuration
- ✅ User can update existing SMS configuration
- ✅ User can delete SMS configuration
- ✅ Validation errors for missing required fields
- ✅ Credentials are encrypted in database
- ✅ User can only access their own config
- ✅ API key and org_id are not sent to frontend

#### B. Browser Tests (optional)
**File:** `tests/Browser/SmsConfigTest.php`

- SMS link appears in settings navigation
- Form validation works
- Delete confirmation works
- Switch toggle updates properly

### 6. Documentation Updates

#### A. WARP.md
**Section:** Add under "Frontend Structure (Phase 4)" → "Application Pages"

```markdown
**Settings Pages** (`pages/settings/`):
- **Profile**: Update name and email
- **Password**: Change password with current password verification
- **Two-Factor Auth**: Enable/disable 2FA
- **SMS**: Configure EngageSPARK credentials (API key, org ID, sender IDs)
- **Appearance**: Theme and appearance settings

All settings pages follow the same pattern:
- Route: `/settings/{page}`
- Layout: `AppLayout` → `SettingsLayout` (with left nav menu)
- Components: `HeadingSmall`, shadcn-vue form components
- Actions: Wayfinder-generated form actions
```

**Section:** Update "Web Routes" to include SMS config routes

```php
# Settings
GET    /settings/profile       → Profile settings
PATCH  /settings/profile       → Update profile
GET    /settings/password      → Password settings
PUT    /settings/password      → Update password
GET    /settings/appearance    → Appearance settings
GET    /settings/sms           → SMS configuration settings
PUT    /settings/sms           → Update SMS configuration
DELETE /settings/sms           → Delete SMS configuration
GET    /settings/two-factor    → Two-factor auth settings
```

#### B. README.md
Update features list to mention user-specific SMS configuration.

## Implementation Checklist

### Backend
- [ ] Create `app/Http/Controllers/Settings/SmsConfigController.php`
- [ ] Create `app/Http/Requests/Settings/UpdateSmsConfigRequest.php`
- [ ] Add routes to `routes/settings.php` (inside auth middleware group)
- [ ] Write feature tests in `tests/Feature/Controllers/Settings/SmsConfigControllerTest.php`

### Frontend
- [ ] Create `resources/js/pages/settings/SmsConfig.vue` (lowercase 's')
- [ ] Update `resources/js/layouts/settings/Layout.vue` to add SMS to nav menu
- [ ] Wait for Vite to generate route helpers (`@/routes/sms-config`)
- [ ] Wait for Vite to generate action helpers (`SmsConfigController`)
- [ ] Test UI manually

### Testing
- [ ] Write comprehensive feature tests
- [ ] Test form validation (missing fields)
- [ ] Test credential encryption
- [ ] Test user isolation (users can't access others' configs)
- [ ] Test hybrid fallback still works after UI implementation
- [ ] Manual browser testing

### Documentation
- [ ] Update WARP.md with SMS settings page documentation
- [ ] Update WARP.md with routes
- [ ] Update README.md feature list

## Additional Considerations

### Password Field Behavior
- API Key and Org ID use `type="password"` but don't pre-fill (for security)
- User must re-enter these values to update
- Alternative: Add "Change credentials" checkbox that reveals password inputs

### Sender IDs Input
- Current approach: Comma-separated string
- Backend should parse: `explode(',', $input)` and `array_map('trim', ...)`
- Alternative: Dynamic list with add/remove buttons (more complex)

### Future: Multi-Driver Support
When adding more SMS providers:
1. Add driver selection dropdown
2. Dynamic form fields based on selected driver
3. Backend already supports this via `driver` column in `user_sms_configs`

### Delete vs Deactivate
- Current approach: Delete removes config entirely
- Alternative: Just toggle `is_active` to false (keeps credentials)
- Trade-off: Storage vs convenience

---

**Status:** Ready for implementation  
**Dependencies:** None (backend infrastructure complete)  
**Estimated Effort:** 4-6 hours
**Pattern Reference:** Follow `Profile.vue` and `Password.vue` exactly
