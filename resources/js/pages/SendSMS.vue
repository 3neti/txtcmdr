<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { sendSMS } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Send SMS', href: sendSMS().url },
];

// Form state
const form = ref({
    recipients: '',
    message: '',
    sender_id: 'cashless',
});

const errors = ref<Record<string, string>>({});
const sending = ref(false);
const success = ref(false);
const scheduleMode = ref(false);
const scheduledAt = ref('');

// Computed
const characterCount = computed(() => form.value.message.length);
const smsCount = computed(() => Math.ceil(characterCount.value / 160) || 1);
const recipientCount = computed(() => {
    if (!form.value.recipients.trim()) return 0;
    return form.value.recipients.split(',').filter(r => r.trim()).length;
});

const canSend = computed(() => {
    return form.value.recipients.trim().length > 0 && 
           form.value.message.trim().length > 0 &&
           (!scheduleMode.value || scheduledAt.value.length > 0);
});

// Methods
const sendNow = () => {
    if (!canSend.value || sending.value) return;
    
    sending.value = true;
    errors.value = {};
    success.value = false;
    
    // Safety timeout to re-enable button after 10 seconds
    const timeout = setTimeout(() => {
        if (sending.value) {
            sending.value = false;
            console.warn('Request timed out - re-enabling button');
        }
    }, 10000);
    
    router.post('/sms/send', {
        recipients: form.value.recipients,
        message: form.value.message,
        sender_id: form.value.sender_id,
    }, {
        onSuccess: () => {
            clearTimeout(timeout);
            success.value = true;
            form.value.recipients = '';
            form.value.message = '';
            sending.value = false;
        },
        onError: (err) => {
            clearTimeout(timeout);
            errors.value = err as Record<string, string>;
            sending.value = false;
        },
        onFinish: () => {
            clearTimeout(timeout);
            sending.value = false;
        },
    });
};

const scheduleMessage = () => {
    if (!canSend.value || !scheduledAt.value || sending.value) return;
    
    sending.value = true;
    errors.value = {};
    success.value = false;
    
    // Safety timeout to re-enable button after 10 seconds
    const timeout = setTimeout(() => {
        if (sending.value) {
            sending.value = false;
            console.warn('Request timed out - re-enabling button');
        }
    }, 10000);
    
    router.post('/sms/schedule', {
        recipients: form.value.recipients.split(',').map(r => r.trim()),
        message: form.value.message,
        sender_id: form.value.sender_id,
        scheduled_at: scheduledAt.value,
    }, {
        onSuccess: () => {
            clearTimeout(timeout);
            success.value = true;
            form.value.recipients = '';
            form.value.message = '';
            scheduledAt.value = '';
            scheduleMode.value = false;
            sending.value = false;
        },
        onError: (err) => {
            clearTimeout(timeout);
            errors.value = err as Record<string, string>;
            sending.value = false;
        },
        onFinish: () => {
            clearTimeout(timeout);
            sending.value = false;
        },
    });
};

const handleSubmit = () => {
    if (scheduleMode.value) {
        scheduleMessage();
    } else {
        sendNow();
    }
};
</script>

<template>
    <Head title="Send SMS" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="max-w-3xl mx-auto w-full">
                <!-- Success Alert -->
                <Alert v-if="success" class="mb-4 bg-green-50 border-green-200">
                    <AlertDescription class="text-green-800">
                        ✓ {{ scheduleMode ? 'Message scheduled successfully!' : 'Message sent successfully!' }}
                    </AlertDescription>
                </Alert>

                <Card>
                    <CardHeader>
                        <CardTitle>📤 Send SMS</CardTitle>
                        <CardDescription>
                            Send SMS messages to individual numbers or groups
                        </CardDescription>
                    </CardHeader>
                    
                    <CardContent>
                        <form @submit.prevent="handleSubmit" class="space-y-6">
                            <!-- Recipients -->
                            <div class="space-y-2">
                                <Label for="recipients">
                                    Recipients
                                    <span class="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="recipients"
                                    v-model="form.recipients"
                                    placeholder="09173011987, 09178251991 or group names"
                                    :class="{ 'border-red-500': errors.recipients }"
                                />
                                <p class="text-sm text-muted-foreground">
                                    Enter phone numbers (comma-separated) or group names
                                </p>
                                <p v-if="errors.recipients" class="text-sm text-red-500">
                                    {{ errors.recipients }}
                                </p>
                                <div v-if="recipientCount > 0" class="flex gap-2">
                                    <Badge variant="secondary">
                                        {{ recipientCount }} recipient{{ recipientCount !== 1 ? 's' : '' }}
                                    </Badge>
                                </div>
                            </div>

                            <!-- Message -->
                            <div class="space-y-2">
                                <Label for="message">
                                    Message
                                    <span class="text-red-500">*</span>
                                </Label>
                                <textarea
                                    id="message"
                                    v-model="form.message"
                                    rows="6"
                                    maxlength="1600"
                                    class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                    :class="{ 'border-red-500': errors.message }"
                                    placeholder="Your message here..."
                                />
                                <div class="flex items-center justify-between">
                                    <p class="text-sm text-muted-foreground">
                                        {{ characterCount }} / 1600 characters
                                    </p>
                                    <Badge variant="outline">
                                        {{ smsCount }} SMS
                                    </Badge>
                                </div>
                                <p v-if="errors.message" class="text-sm text-red-500">
                                    {{ errors.message }}
                                </p>
                            </div>

                            <!-- Sender ID -->
                            <div class="space-y-2">
                                <Label for="sender_id">Sender ID</Label>
                                <select
                                    id="sender_id"
                                    v-model="form.sender_id"
                                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="cashless">cashless</option>
                                    <option value="Quezon City">Quezon City</option>
                                    <option value="TXTCMDR">TXTCMDR</option>
                                </select>
                            </div>

                            <!-- Schedule Option -->
                            <div class="space-y-4">
                                <div class="flex items-center space-x-2">
                                    <input
                                        id="schedule"
                                        v-model="scheduleMode"
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-gray-300"
                                    />
                                    <Label for="schedule" class="cursor-pointer">
                                        Schedule for later
                                    </Label>
                                </div>

                                <div v-if="scheduleMode" class="space-y-2">
                                    <Label for="scheduled_at">Schedule Date & Time</Label>
                                    <Input
                                        id="scheduled_at"
                                        v-model="scheduledAt"
                                        type="datetime-local"
                                        :min="new Date().toISOString().slice(0, 16)"
                                    />
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex gap-3">
                                <Button
                                    type="submit"
                                    :disabled="!canSend || sending"
                                    class="flex-1"
                                >
                                    {{ sending ? 'Sending...' : scheduleMode ? 'Schedule Message' : 'Send Now' }}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    @click="() => {
                                        form.recipients = '';
                                        form.message = '';
                                        scheduledAt = '';
                                        scheduleMode = false;
                                        errors = {};
                                    }"
                                >
                                    Clear
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Tips Card -->
                <Card class="mt-4">
                    <CardHeader>
                        <CardTitle class="text-base">💡 Tips</CardTitle>
                    </CardHeader>
                    <CardContent class="text-sm text-muted-foreground space-y-2">
                        <p>• Use comma-separated phone numbers: <code class="text-xs bg-muted px-1 py-0.5 rounded">09173011987, 09178251991</code></p>
                        <p>• Phone numbers can be in any format (09XX, +639XX, 639XX)</p>
                        <p>• Each SMS can contain up to 160 characters (longer messages split automatically)</p>
                        <p>• Blacklisted numbers will be automatically filtered</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
