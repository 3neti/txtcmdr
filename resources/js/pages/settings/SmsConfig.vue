<script setup lang="ts">
import SmsConfigController from '@/actions/App/Http/Controllers/Settings/SmsConfigController';
import InputError from '@/components/InputError.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Checkbox } from '@/components/ui/checkbox';
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
                        You are currently using the application's default SMS
                        configuration. Add your own credentials below to
                        override the defaults.
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
                        <Label for="org_id"
                            >EngageSPARK Organization ID *</Label
                        >
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
                        <Label for="default_sender_id"
                            >Default Sender ID *</Label
                        >
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
                        <Label for="sender_ids"
                            >Additional Sender IDs (comma-separated)</Label
                        >
                        <Input
                            id="sender_ids"
                            name="sender_ids"
                            class="mt-1 block w-full"
                            :default-value="
                                userConfig?.sender_ids?.join(', ') ?? ''
                            "
                            placeholder="sender1, sender2, sender3"
                        />
                        <InputError :message="errors.sender_ids" />
                        <p class="text-sm text-muted-foreground">
                            Optional. Provide a list of alternative sender IDs
                            you can use.
                        </p>
                    </div>

                    <!-- Active Checkbox -->
                    <div class="flex items-center space-x-2">
                        <Checkbox
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
                <div
                    v-if="userConfig?.has_credentials"
                    class="space-y-6 border-t pt-6"
                >
                    <HeadingSmall
                        title="Delete SMS Configuration"
                        description="Remove your custom SMS credentials and revert to application defaults"
                    />
                    <Form
                        v-bind="SmsConfigController.destroy.form()"
                        :options="{
                            preserveScroll: true,
                            onBefore: () =>
                                confirm(
                                    'Are you sure you want to delete your SMS configuration? You will use the application defaults.',
                                ),
                        }"
                        v-slot="{ processing }"
                    >
                        <Button
                            variant="destructive"
                            :disabled="processing"
                            data-test="delete-sms-config-button"
                        >
                            Delete Configuration
                        </Button>
                    </Form>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
