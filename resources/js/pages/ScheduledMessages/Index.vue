<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Calendar, Clock, X } from 'lucide-vue-next';
import { ref } from 'vue';

interface ScheduledMessage {
    id: number;
    message: string;
    sender_id: string;
    recipient_type: string;
    scheduled_at: string;
    status: string;
    total_recipients: number;
    sent_count: number | null;
    failed_count: number | null;
    created_at: string;
}

interface PaginatedMessages {
    data: ScheduledMessage[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    messages: PaginatedMessages;
    currentStatus: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Scheduled Messages', href: '/scheduled-messages' },
];

const showCancelDialog = ref(false);
const messageToCancel = ref<ScheduledMessage | null>(null);

const filterByStatus = (status: string) => {
    router.get('/scheduled-messages', { status }, { preserveState: true });
};

const confirmCancel = (message: ScheduledMessage) => {
    messageToCancel.value = message;
    showCancelDialog.value = true;
};

const cancelMessage = () => {
    if (!messageToCancel.value) return;

    router.post(
        `/scheduled-messages/${messageToCancel.value.id}/cancel`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                showCancelDialog.value = false;
                messageToCancel.value = null;
            },
        },
    );
};

const getStatusBadgeClass = (status: string) => {
    const classes = {
        pending:
            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
        processing:
            'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
        sent: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        cancelled:
            'bg-gray-100 text-gray-800 dark:bg-gray-900/30 dark:text-gray-400',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
    };
    return classes[status as keyof typeof classes] || classes.pending;
};

const formatDateTime = (date: string) => {
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const isCancellable = (message: ScheduledMessage) => {
    return message.status === 'pending' || message.status === 'processing';
};

const isPast = (date: string) => {
    return new Date(date) <= new Date();
};
</script>

<template>
    <Head title="Scheduled Messages" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Scheduled Messages</h1>
                    <p class="text-sm text-muted-foreground">
                        View and manage your scheduled SMS messages
                    </p>
                </div>
                <Select
                    :model-value="currentStatus"
                    @update:model-value="filterByStatus"
                >
                    <SelectTrigger class="w-[180px]">
                        <SelectValue placeholder="Filter by status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Messages</SelectItem>
                        <SelectItem value="pending">Pending</SelectItem>
                        <SelectItem value="processing">Processing</SelectItem>
                        <SelectItem value="sent">Sent</SelectItem>
                        <SelectItem value="cancelled">Cancelled</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Messages List -->
            <div v-if="messages.data.length > 0" class="space-y-3">
                <div
                    v-for="message in messages.data"
                    :key="message.id"
                    class="rounded-lg border bg-card p-6 shadow-sm"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex-1 space-y-3">
                            <!-- Header -->
                            <div class="flex items-center gap-2">
                                <span
                                    :class="getStatusBadgeClass(message.status)"
                                    class="rounded-full px-2.5 py-0.5 text-xs font-medium uppercase"
                                >
                                    {{ message.status }}
                                </span>
                                <span class="text-sm text-muted-foreground">
                                    From: {{ message.sender_id }}
                                </span>
                            </div>

                            <!-- Message Content -->
                            <p class="text-sm">{{ message.message }}</p>

                            <!-- Details -->
                            <div
                                class="flex flex-wrap gap-4 text-sm text-muted-foreground"
                            >
                                <div class="flex items-center gap-1">
                                    <Clock class="h-4 w-4" />
                                    <span>{{
                                        formatDateTime(message.scheduled_at)
                                    }}</span>
                                    <span
                                        v-if="
                                            message.status === 'pending' &&
                                            isPast(message.scheduled_at)
                                        "
                                        class="ml-1 text-yellow-600 dark:text-yellow-500"
                                    >
                                        (Processing soon)
                                    </span>
                                </div>
                                <div>
                                    {{ message.total_recipients }} recipient(s)
                                </div>
                                <div v-if="message.status === 'sent'">
                                    ✓ {{ message.sent_count }} sent
                                    <span
                                        v-if="
                                            message.failed_count &&
                                            message.failed_count > 0
                                        "
                                    >
                                        · ✗ {{ message.failed_count }} failed
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Cancel Button -->
                        <Button
                            v-if="isCancellable(message)"
                            variant="outline"
                            size="sm"
                            class="ml-4 shrink-0"
                            @click="confirmCancel(message)"
                        >
                            <X class="mr-2 h-4 w-4" />
                            Cancel
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed p-12 text-center"
            >
                <Calendar class="mb-4 h-12 w-12 text-muted-foreground" />
                <h3 class="mb-2 text-lg font-semibold">
                    {{
                        currentStatus === 'all'
                            ? 'No scheduled messages'
                            : `No ${currentStatus} messages`
                    }}
                </h3>
                <p class="mb-4 text-sm text-muted-foreground">
                    Schedule messages from the Send SMS page
                </p>
                <Button @click="router.visit('/send-sms')">
                    Schedule a Message
                </Button>
            </div>

            <!-- Pagination -->
            <div
                v-if="messages.last_page > 1"
                class="flex items-center justify-between rounded-lg border bg-card p-4"
            >
                <div class="text-sm text-muted-foreground">
                    Page {{ messages.current_page }} of {{ messages.last_page }}
                </div>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="messages.current_page === 1"
                        @click="
                            router.get('/scheduled-messages', {
                                status: currentStatus,
                                page: messages.current_page - 1,
                            })
                        "
                    >
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="messages.current_page === messages.last_page"
                        @click="
                            router.get('/scheduled-messages', {
                                status: currentStatus,
                                page: messages.current_page + 1,
                            })
                        "
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>

        <!-- Cancel Confirmation Dialog -->
        <AlertDialog v-model:open="showCancelDialog">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle
                        >Cancel Scheduled Message?</AlertDialogTitle
                    >
                    <AlertDialogDescription>
                        Are you sure you want to cancel this scheduled message?
                        This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>No, Keep It</AlertDialogCancel>
                    <AlertDialogAction @click="cancelMessage">
                        Yes, Cancel Message
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
