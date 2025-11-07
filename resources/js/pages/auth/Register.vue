<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TagInput from '@/components/TagInput.vue';
import TextLink from '@/components/TextLink.vue';
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
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Props {
    smsConfigMode: 'optional' | 'required' | 'disabled';
}

const props = defineProps<Props>();

const step = ref(1);
const senderIds = ref<string[]>([]);
const defaultSenderId = ref<string>('');

// Computed properties based on mode
const showSmsStep = computed(() => props.smsConfigMode !== 'disabled');
const smsRequired = computed(() => props.smsConfigMode === 'required');
const canSkipSms = computed(() => props.smsConfigMode === 'optional');

const goToSmsStep = () => {
    if (showSmsStep.value) {
        step.value = 2;
    }
};

const goBackToBasicInfo = () => {
    step.value = 1;
};
</script>

<template>
    <AuthBase
        :title="step === 1 ? 'Create an account' : 'SMS Configuration'"
        :description="
            step === 1
                ? 'Enter your details below to create your account'
                : 'Configure your SMS account to complete registration'
        "
    >
        <Head title="Register" />

        <!-- Step indicator -->
        <div
            v-if="showSmsStep"
            class="mb-4 flex items-center justify-center gap-2"
        >
            <div
                class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium"
                :class="
                    step === 1
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-muted text-muted-foreground'
                "
            >
                1
            </div>
            <div class="h-0.5 w-8 bg-border"></div>
            <div
                class="flex h-8 w-8 items-center justify-center rounded-full text-sm font-medium"
                :class="
                    step === 2
                        ? 'bg-primary text-primary-foreground'
                        : 'bg-muted text-muted-foreground'
                "
            >
                2
            </div>
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <!-- Step 1: Basic Info -->
            <div v-show="step === 1" class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="name"
                        name="name"
                        placeholder="Full name"
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">Email address</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        :tabindex="2"
                        autocomplete="email"
                        name="email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="3"
                        autocomplete="new-password"
                        name="password"
                        placeholder="Password"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        :tabindex="4"
                        autocomplete="new-password"
                        name="password_confirmation"
                        placeholder="Confirm password"
                    />
                    <InputError :message="errors.password_confirmation" />
                </div>

                <Button
                    v-if="showSmsStep"
                    type="button"
                    class="mt-2 w-full"
                    :tabindex="5"
                    @click="goToSmsStep"
                >
                    Next: SMS Configuration
                </Button>

                <Button
                    v-else
                    type="submit"
                    class="mt-2 w-full"
                    :tabindex="5"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    Create Account
                </Button>
            </div>

            <!-- Step 2: SMS Config (if not disabled) -->
            <div v-show="step === 2 && showSmsStep" class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="sms_api_key">
                        EngageSPARK API Key
                        <span v-if="smsRequired" class="text-destructive"
                            >*</span
                        >
                    </Label>
                    <Input
                        id="sms_api_key"
                        name="sms_api_key"
                        type="password"
                        :required="smsRequired"
                        autocomplete="off"
                        placeholder="Enter your EngageSPARK API Key"
                    />
                    <InputError :message="errors.sms_api_key" />
                </div>

                <div class="grid gap-2">
                    <Label for="sms_org_id">
                        EngageSPARK Organization ID
                        <span v-if="smsRequired" class="text-destructive"
                            >*</span
                        >
                    </Label>
                    <Input
                        id="sms_org_id"
                        name="sms_org_id"
                        type="password"
                        :required="smsRequired"
                        autocomplete="off"
                        placeholder="Enter your Organization ID"
                    />
                    <InputError :message="errors.sms_org_id" />
                </div>

                <!-- Sender IDs (define all first) -->
                <div class="grid gap-2">
                    <Label for="sms_sender_ids">
                        Sender IDs
                        <span v-if="smsRequired" class="text-destructive"
                            >*</span
                        >
                    </Label>
                    <TagInput
                        v-model="senderIds"
                        name="sms_sender_ids"
                        placeholder="Type sender ID and press Enter or comma"
                    />
                    <InputError :message="errors.sms_sender_ids" />
                    <p class="text-sm text-muted-foreground">
                        Add all your sender IDs (e.g., cashless, Quezon City).
                        Press Enter or comma to add each one.
                    </p>
                </div>

                <!-- Default Sender ID (pick from above) -->
                <div class="grid gap-2">
                    <Label for="sms_default_sender_id">
                        Default Sender ID
                        <span v-if="smsRequired" class="text-destructive"
                            >*</span
                        >
                    </Label>
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
                        name="sms_default_sender_id"
                        :value="defaultSenderId"
                    />
                    <InputError :message="errors.sms_default_sender_id" />
                    <p class="text-sm text-muted-foreground">
                        Choose which sender ID to use by default.
                    </p>
                </div>

                <!-- Hidden input to always submit active=true -->
                <input type="hidden" name="sms_is_active" value="1" />

                <div class="mt-2 flex gap-3">
                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="processing"
                        data-test="register-user-button"
                    >
                        <Spinner v-if="processing" />
                        Complete Registration
                    </Button>
                </div>

                <Button
                    type="button"
                    variant="outline"
                    class="w-full"
                    @click="goBackToBasicInfo"
                >
                    ← Back to Account Details
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="6"
                    >Log in</TextLink
                >
            </div>
        </Form>
    </AuthBase>
</template>
