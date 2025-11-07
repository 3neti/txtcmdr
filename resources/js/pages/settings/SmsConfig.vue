<script setup lang="ts">
import SmsConfigController from '@/actions/App/Http/Controllers/Settings/SmsConfigController';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import TagInput from '@/components/TagInput.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { edit } from '@/routes/sms-config';
import { type BreadcrumbItem } from '@/types';
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';

interface UserConfig {
    api_key_masked: string | null;
    org_id_masked: string | null;
    default_sender_id: string;
    sender_ids: string[];
    is_active: boolean;
    has_credentials: boolean;
}

interface Props {
    userConfig?: UserConfig;
    usesAppDefaults: boolean;
}

const props = defineProps<Props>();

const senderIds = ref<string[]>(props.userConfig?.sender_ids ?? []);
const defaultSenderId = ref<string>(props.userConfig?.default_sender_id ?? '');

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
                <Alert v-if="!userConfig?.has_credentials" variant="default">
                    <AlertDescription>
                        Please configure your SMS credentials to start sending
                        messages.
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
                        <Label for="api_key">EngageSPARK API Key</Label>
                        <Input
                            id="api_key"
                            name="api_key"
                            type="password"
                            class="mt-1 block w-full"
                            :placeholder="
                                userConfig?.api_key_masked ||
                                'Enter your EngageSPARK API Key'
                            "
                            autocomplete="off"
                        />
                        <InputError :message="errors.api_key" />
                        <p
                            v-if="userConfig?.api_key_masked"
                            class="text-sm text-muted-foreground"
                        >
                            Current: {{ userConfig.api_key_masked }} (leave
                            blank to keep current)
                        </p>
                    </div>

                    <!-- Organization ID -->
                    <div class="grid gap-2">
                        <Label for="org_id">EngageSPARK Organization ID</Label>
                        <Input
                            id="org_id"
                            name="org_id"
                            type="password"
                            class="mt-1 block w-full"
                            :placeholder="
                                userConfig?.org_id_masked ||
                                'Enter your EngageSPARK Organization ID'
                            "
                            autocomplete="off"
                        />
                        <InputError :message="errors.org_id" />
                        <p
                            v-if="userConfig?.org_id_masked"
                            class="text-sm text-muted-foreground"
                        >
                            Current: {{ userConfig.org_id_masked }} (leave blank
                            to keep current)
                        </p>
                    </div>

                    <!-- Sender IDs (define all first) -->
                    <div class="grid gap-2">
                        <Label for="sender_ids">Sender IDs *</Label>
                        <TagInput
                            v-model="senderIds"
                            name="sender_ids"
                            placeholder="Type sender ID and press Enter or comma"
                        />
                        <InputError :message="errors.sender_ids" />
                        <p class="text-sm text-muted-foreground">
                            Add all your sender IDs (e.g., cashless, Quezon
                            City). Press Enter or comma to add each one.
                        </p>
                    </div>

                    <!-- Default Sender ID (pick from above) -->
                    <div class="grid gap-2">
                        <Label for="default_sender_id"
                            >Default Sender ID *</Label
                        >
                        <Select
                            v-model="defaultSenderId"
                            :disabled="senderIds.length === 0"
                        >
                            <SelectTrigger>
                                <SelectValue
                                    :placeholder="
                                        senderIds.length > 0
                                            ? 'Select default sender ID'
                                            : 'Add sender IDs above first'
                                    "
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="senderId in senderIds"
                                    :key="senderId"
                                    :value="senderId"
                                >
                                    {{ senderId }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <input
                            type="hidden"
                            name="default_sender_id"
                            :value="defaultSenderId"
                        />
                        <InputError :message="errors.default_sender_id" />
                        <p class="text-sm text-muted-foreground">
                            Choose which sender ID to use by default.
                        </p>
                    </div>

                    <!-- Hidden input to always submit active=true -->
                    <input type="hidden" name="is_active" value="1" />

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
                        description="Remove your SMS credentials. You will need to reconfigure to send messages."
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
