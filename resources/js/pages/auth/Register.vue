<script setup lang="ts">
import { ref, computed } from 'vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Checkbox } from '@/components/ui/checkbox';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';
import { Form, Head } from '@inertiajs/vue3';

interface Props {
    smsConfigMode: 'optional' | 'required' | 'disabled';
}

const props = defineProps<Props>();

const step = ref(1);
const smsIsActive = ref(true);

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
                : smsRequired
                  ? 'Configure your SMS account to complete registration'
                  : 'Configure your SMS account now, or skip to use our shared account'
        "
    >
        <Head title="Register" />

        <!-- Step indicator -->
        <div v-if="showSmsStep" class="mb-4 flex items-center justify-center gap-2">
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
            <div v-if="step === 1" class="grid gap-6">
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
                    {{ smsRequired ? '(Required)' : '(Optional)' }}
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
            <div v-if="step === 2 && showSmsStep" class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="sms_api_key">
                        EngageSPARK API Key
                        <span v-if="smsRequired" class="text-destructive">*</span>
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
                        <span v-if="smsRequired" class="text-destructive">*</span>
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

                <div class="grid gap-2">
                    <Label for="sms_default_sender_id">
                        Default Sender ID
                        <span v-if="smsRequired" class="text-destructive">*</span>
                    </Label>
                    <Input
                        id="sms_default_sender_id"
                        name="sms_default_sender_id"
                        :required="smsRequired"
                        placeholder="e.g., cashless, Quezon City"
                    />
                    <InputError :message="errors.sms_default_sender_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="sms_sender_ids">
                        Additional Sender IDs (comma-separated)
                    </Label>
                    <Input
                        id="sms_sender_ids"
                        name="sms_sender_ids"
                        placeholder="sender1, sender2, sender3"
                    />
                    <InputError :message="errors.sms_sender_ids" />
                    <p class="text-sm text-muted-foreground">
                        Optional. List alternative sender IDs you can use.
                    </p>
                </div>

                <div class="flex items-center space-x-2">
                    <Checkbox
                        id="sms_is_active"
                        :checked="smsIsActive"
                        @update:checked="(value) => (smsIsActive = value)"
                    />
                    <input
                        type="hidden"
                        name="sms_is_active"
                        :value="smsIsActive ? '1' : '0'"
                    />
                    <Label for="sms_is_active" class="cursor-pointer">
                        Use my custom SMS configuration
                    </Label>
                </div>

                <div class="mt-2 flex gap-3">
                    <Button
                        type="submit"
                        class="flex-1"
                        :disabled="processing"
                        data-test="register-user-button"
                    >
                        <Spinner v-if="processing" />
                        Complete Registration
                    </Button>

                    <Button
                        v-if="canSkipSms"
                        type="submit"
                        variant="ghost"
                        class="flex-1"
                        :disabled="processing"
                    >
                        Skip - Use Shared Account
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
