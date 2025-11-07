<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Clock,
    Download,
    MessageSquare,
    RefreshCw,
    Search,
} from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface Contact {
    mobile: string;
    name?: string;
    email?: string;
    meta?: {
        name?: string;
    };
}

interface MessageLog {
    id: number;
    recipient: string;
    message: string;
    status: string;
    sender_id: string;
    sent_at: string | null;
    failed_at: string | null;
    error_message: string | null;
    created_at: string;
    contact_with_name?: Contact | null;
}

interface PaginatedLogs {
    data: MessageLog[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    logs: PaginatedLogs;
    currentStatus: string;
    searchQuery: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Message History', href: '/message-history' },
];

const search = ref(props.searchQuery);
const statusFilter = ref(props.currentStatus);

// Debounced search
let searchTimeout: NodeJS.Timeout;
watch(search, (newValue) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 500);
});

watch(statusFilter, () => {
    applyFilters();
});

const applyFilters = () => {
    router.get(
        '/message-history',
        {
            status: statusFilter.value,
            search: search.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
};

const getStatusBadgeClass = (status: string) => {
    const classes = {
        sent: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
        pending:
            'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    };
    return classes[status as keyof typeof classes] || classes.pending;
};

const formatPhone = (mobile: string) => {
    if (mobile.startsWith('+63')) {
        return mobile.replace('+63', '0');
    }
    return mobile;
};

const formatRecipient = (log: MessageLog) => {
    const phone = formatPhone(log.recipient);
    // Name is appended directly on the contact object (from meta JSON column)
    const contactName = log.contact_with_name?.name;

    return contactName ? `${contactName} (${phone})` : phone;
};

const formatDateTime = (date: string | null) => {
    if (!date) return '-';
    return new Date(date).toLocaleString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatRelativeTime = (date: string) => {
    const now = new Date();
    const then = new Date(date);
    const diffMs = now.getTime() - then.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffHours < 24) return `${diffHours}h ago`;
    if (diffDays < 7) return `${diffDays}d ago`;
    return formatDateTime(date);
};

const retryMessage = (logId: number) => {
    const form = useForm({});
    form.post(`/message-logs/${logId}/retry`, {
        preserveScroll: true,
        onSuccess: () => {
            // Message will be reloaded from server
        },
    });
};

const exportToCSV = () => {
    // Build export URL with current filters
    const params = new URLSearchParams();
    if (statusFilter.value !== 'all') {
        params.append('status', statusFilter.value);
    }
    if (search.value) {
        params.append('search', search.value);
    }

    const url = `/message-history/export${params.toString() ? '?' + params.toString() : ''}`;
    window.location.href = url;
};
</script>

<template>
    <Head title="Message History" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Message History</h1>
                    <p class="text-sm text-muted-foreground">
                        View all sent SMS messages
                    </p>
                </div>
                <div class="flex items-center gap-4">
                    <Button variant="outline" size="sm" @click="exportToCSV">
                        <Download class="mr-2 h-4 w-4" />
                        Export to CSV
                    </Button>
                    <div class="text-sm text-muted-foreground">
                        {{ logs.total }} total message(s)
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="flex flex-col gap-4 sm:flex-row">
                <div class="relative flex-1">
                    <Search
                        class="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <Input
                        v-model="search"
                        placeholder="Search by recipient or message..."
                        class="pl-9"
                    />
                </div>
                <Select v-model="statusFilter">
                    <SelectTrigger class="w-full sm:w-[180px]">
                        <SelectValue placeholder="Filter by status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">All Messages</SelectItem>
                        <SelectItem value="sent">Sent</SelectItem>
                        <SelectItem value="failed">Failed</SelectItem>
                        <SelectItem value="pending">Pending</SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <!-- Messages List -->
            <div v-if="logs.data.length > 0" class="space-y-3">
                <div
                    v-for="log in logs.data"
                    :key="log.id"
                    class="rounded-lg border bg-card p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 space-y-2">
                            <!-- Status & Sender -->
                            <div class="flex items-center gap-2">
                                <span
                                    :class="getStatusBadgeClass(log.status)"
                                    class="rounded-full px-2.5 py-0.5 text-xs font-medium uppercase"
                                >
                                    {{ log.status }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    From: {{ log.sender_id }}
                                </span>
                                <span class="text-xs text-muted-foreground">
                                    To: {{ formatRecipient(log) }}
                                </span>
                            </div>

                            <!-- Message Content -->
                            <p class="text-sm">{{ log.message }}</p>

                            <!-- Error Message (if failed) -->
                            <div
                                v-if="
                                    log.status === 'failed' && log.error_message
                                "
                                class="rounded-md bg-destructive/10 p-2 text-xs text-destructive"
                            >
                                <strong>Error:</strong> {{ log.error_message }}
                            </div>

                            <!-- Timestamps -->
                            <div
                                class="flex gap-4 text-xs text-muted-foreground"
                            >
                                <div class="flex items-center gap-1">
                                    <Clock class="h-3 w-3" />
                                    <span>{{
                                        formatRelativeTime(log.created_at)
                                    }}</span>
                                </div>
                                <div v-if="log.sent_at">
                                    Sent: {{ formatDateTime(log.sent_at) }}
                                </div>
                                <div v-if="log.failed_at">
                                    Failed: {{ formatDateTime(log.failed_at) }}
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <!-- Retry button for failed messages -->
                            <Button
                                v-if="log.status === 'failed'"
                                variant="outline"
                                size="sm"
                                @click="retryMessage(log.id)"
                            >
                                <RefreshCw class="mr-2 h-4 w-4" />
                                Retry
                            </Button>
                            <MessageSquare
                                class="h-5 w-5 text-muted-foreground"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed p-12 text-center"
            >
                <MessageSquare class="mb-4 h-12 w-12 text-muted-foreground" />
                <h3 class="mb-2 text-lg font-semibold">
                    {{
                        search || currentStatus !== 'all'
                            ? 'No messages found'
                            : 'No messages yet'
                    }}
                </h3>
                <p class="mb-4 text-sm text-muted-foreground">
                    {{
                        search || currentStatus !== 'all'
                            ? 'Try adjusting your filters'
                            : 'Send your first SMS to see it here'
                    }}
                </p>
                <Button @click="router.visit('/send-sms')"> Send SMS </Button>
            </div>

            <!-- Pagination -->
            <div
                v-if="logs.last_page > 1"
                class="flex items-center justify-between rounded-lg border bg-card p-4"
            >
                <div class="text-sm text-muted-foreground">
                    Page {{ logs.current_page }} of {{ logs.last_page }}
                </div>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="logs.current_page === 1"
                        @click="
                            router.get('/message-history', {
                                status: currentStatus,
                                search: searchQuery,
                                page: logs.current_page - 1,
                            })
                        "
                    >
                        Previous
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="logs.current_page === logs.last_page"
                        @click="
                            router.get('/message-history', {
                                status: currentStatus,
                                search: searchQuery,
                                page: logs.current_page + 1,
                            })
                        "
                    >
                        Next
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
