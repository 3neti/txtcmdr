<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { AlertCircle, Calendar, MessageSquare, Send, Users, UsersRound } from 'lucide-vue-next';

interface Stats {
    totalGroups: number;
    totalContacts: number;
    scheduledMessages: number;
    sentMessages: number;
    totalMessages: number;
    failedMessages: number;
    todayMessages: number;
    thisWeekMessages: number;
    successRate: number;
}

interface ChartData {
    date: string;
    count: number;
}

interface Group {
    id: number;
    name: string;
    description: string | null;
    contacts_count: number;
    created_at: string;
}

interface ScheduledMessage {
    id: number;
    message: string;
    recipient_type: string;
    scheduled_at: string;
    status: string;
    total_recipients: number;
}

interface Contact {
    mobile: string;
    name?: string;
    email?: string;
    meta?: {
        name?: string;
    };
}

interface FailedMessage {
    id: number;
    recipient: string;
    message: string;
    error_message: string | null;
    failed_at: string;
    sender_id: string;
    contact_with_name?: Contact | null;
}

defineProps<{
    stats: Stats;
    chartData: ChartData[];
    recentGroups: Group[];
    recentScheduled: ScheduledMessage[];
    recentFailures: FailedMessage[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const quickActions = [
    {
        title: 'Send SMS',
        description: 'Send messages to individuals or groups',
        icon: Send,
        action: () => router.visit('/send-sms'),
    },
    {
        title: 'Manage Groups',
        description: 'Create and organize contact groups',
        icon: UsersRound,
        action: () => router.visit('/groups'),
    },
    {
        title: 'Manage Contacts',
        description: 'Add and manage your contacts',
        icon: Users,
        action: () => router.visit('/contacts'),
    },
];

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatPhone = (mobile: string) => {
    if (mobile.startsWith('+63')) {
        return mobile.replace('+63', '0');
    }
    return mobile;
};

const formatRecipient = (recipient: string, contact?: Contact | null) => {
    const phone = formatPhone(recipient);
    // Name is appended directly on the contact object (from meta JSON column)
    const contactName = contact?.name;
    
    return contactName ? `${contactName} (${phone})` : phone;
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-4">
                <div
                    class="flex flex-col gap-2 rounded-xl border bg-card p-6 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-muted-foreground"
                            >Total Groups</span
                        >
                        <UsersRound class="h-4 w-4 text-muted-foreground" />
                    </div>
                    <div class="text-3xl font-bold">{{ stats.totalGroups }}</div>
                </div>

                <div
                    class="flex flex-col gap-2 rounded-xl border bg-card p-6 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-muted-foreground"
                            >Total Contacts</span
                        >
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </div>
                    <div class="text-3xl font-bold">{{ stats.totalContacts }}</div>
                </div>

                <div
                    class="flex flex-col gap-2 rounded-xl border bg-card p-6 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-muted-foreground"
                            >Scheduled</span
                        >
                        <Calendar class="h-4 w-4 text-muted-foreground" />
                    </div>
                    <div class="text-3xl font-bold">
                        {{ stats.scheduledMessages }}
                    </div>
                </div>

                <div
                    class="flex flex-col gap-2 rounded-xl border bg-card p-6 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-muted-foreground"
                            >Sent Messages</span
                        >
                        <MessageSquare class="h-4 w-4 text-muted-foreground" />
                    </div>
                    <div class="text-3xl font-bold">{{ stats.sentMessages }}</div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div>
                <h2 class="mb-4 text-lg font-semibold">Quick Actions</h2>
                <div class="grid gap-4 md:grid-cols-3">
                    <button
                        v-for="action in quickActions"
                        :key="action.title"
                        @click="action.action"
                        class="flex items-start gap-4 rounded-xl border bg-card p-6 text-left shadow-sm transition-colors hover:bg-accent"
                    >
                        <div
                            class="rounded-lg bg-primary/10 p-3 text-primary"
                        >
                            <component :is="action.icon" class="h-6 w-6" />
                        </div>
                        <div class="flex flex-col gap-1">
                            <h3 class="font-semibold">{{ action.title }}</h3>
                            <p class="text-sm text-muted-foreground">
                                {{ action.description }}
                            </p>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Message Analytics -->
            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold">Message Analytics</h2>
                
                <!-- Stats Grid -->
                <div class="mb-6 grid gap-4 md:grid-cols-4">
                    <div class="flex flex-col">
                        <span class="text-sm text-muted-foreground">Today</span>
                        <span class="text-2xl font-bold">{{ stats.todayMessages }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm text-muted-foreground">This Week</span>
                        <span class="text-2xl font-bold">{{ stats.thisWeekMessages }}</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm text-muted-foreground">Success Rate</span>
                        <span class="text-2xl font-bold text-green-600">{{ stats.successRate }}%</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-sm text-muted-foreground">Failed</span>
                        <span class="text-2xl font-bold text-red-600">{{ stats.failedMessages }}</span>
                    </div>
                </div>

                <!-- Simple Bar Chart -->
                <div>
                    <h3 class="mb-3 text-sm font-medium text-muted-foreground">Last 7 Days</h3>
                    <div class="flex items-end gap-2" style="height: 120px">
                        <div
                            v-for="(day, index) in chartData"
                            :key="index"
                            class="flex flex-1 flex-col items-center gap-2"
                        >
                            <div class="relative w-full">
                                <div
                                    class="w-full rounded-t-md bg-primary transition-all hover:bg-primary/80"
                                    :style="{
                                        height: day.count > 0 ? `${Math.max((day.count / Math.max(...chartData.map(d => d.count))) * 100, 10)}px` : '2px',
                                        minHeight: '2px'
                                    }"
                                    :title="`${day.date}: ${day.count} messages`"
                                >
                                    <span v-if="day.count > 0" class="absolute -top-5 left-1/2 -translate-x-1/2 text-xs font-medium">
                                        {{ day.count }}
                                    </span>
                                </div>
                            </div>
                            <span class="text-xs text-muted-foreground">{{ day.date }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Failed Messages Summary -->
            <div v-if="recentFailures.length > 0" class="rounded-xl border border-red-200 bg-red-50 p-6 shadow-sm dark:border-red-900/50 dark:bg-red-950/20">
                <div class="mb-4 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <AlertCircle class="h-5 w-5 text-red-600 dark:text-red-400" />
                        <h2 class="text-lg font-semibold text-red-900 dark:text-red-100">
                            Recent Failed Messages
                        </h2>
                    </div>
                    <button
                        @click="router.visit('/message-history?status=failed')"
                        class="text-sm text-red-600 hover:underline dark:text-red-400"
                    >
                        View All
                    </button>
                </div>
                <div class="space-y-3">
                    <div
                        v-for="failure in recentFailures"
                        :key="failure.id"
                        class="rounded-lg border border-red-200 bg-white p-3 dark:border-red-900/30 dark:bg-red-950/10"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 space-y-1">
                                <div class="flex items-center gap-2 text-sm">
                                    <span class="font-medium text-red-900 dark:text-red-100">
                                        To: {{ formatRecipient(failure.recipient, failure.contact_with_name) }}
                                    </span>
                                    <span class="text-xs text-red-600/70 dark:text-red-400/70">
                                        From: {{ failure.sender_id }}
                                    </span>
                                </div>
                                <p class="line-clamp-1 text-sm text-red-800 dark:text-red-200">
                                    {{ failure.message }}
                                </p>
                                <p v-if="failure.error_message" class="text-xs text-red-600 dark:text-red-400">
                                    Error: {{ failure.error_message }}
                                </p>
                            </div>
                            <span class="shrink-0 text-xs text-red-600/70 dark:text-red-400/70">
                                {{ formatDate(failure.failed_at) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="grid gap-6 md:grid-cols-2">
                <!-- Recent Groups -->
                <div class="rounded-xl border bg-card p-6 shadow-sm">
                    <h2 class="mb-4 text-lg font-semibold">Recent Groups</h2>
                    <div v-if="recentGroups.length > 0" class="space-y-3">
                        <div
                            v-for="group in recentGroups"
                            :key="group.id"
                            class="flex items-center justify-between rounded-lg border p-3 hover:bg-accent"
                        >
                            <div class="flex flex-col">
                                <span class="font-medium">{{ group.name }}</span>
                                <span class="text-sm text-muted-foreground">
                                    {{ group.contacts_count }} contact(s)
                                </span>
                            </div>
                            <span class="text-xs text-muted-foreground">
                                {{ formatDate(group.created_at) }}
                            </span>
                        </div>
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center justify-center py-8 text-center text-muted-foreground"
                    >
                        <UsersRound class="mb-2 h-8 w-8" />
                        <p class="text-sm">No groups yet</p>
                    </div>
                </div>

                <!-- Upcoming Scheduled Messages -->
                <div class="rounded-xl border bg-card p-6 shadow-sm">
                    <h2 class="mb-4 text-lg font-semibold">
                        Upcoming Scheduled Messages
                    </h2>
                    <div v-if="recentScheduled.length > 0" class="space-y-3">
                        <div
                            v-for="msg in recentScheduled"
                            :key="msg.id"
                            class="flex flex-col gap-1 rounded-lg border p-3 hover:bg-accent"
                        >
                            <div class="flex items-start justify-between">
                                <span class="line-clamp-1 text-sm font-medium">
                                    {{ msg.message }}
                                </span>
                                <span
                                    class="ml-2 shrink-0 text-xs text-muted-foreground"
                                >
                                    {{ formatDate(msg.scheduled_at) }}
                                </span>
                            </div>
                            <span class="text-xs text-muted-foreground">
                                {{ msg.total_recipients }} recipient(s)
                            </span>
                        </div>
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center justify-center py-8 text-center text-muted-foreground"
                    >
                        <Calendar class="mb-2 h-8 w-8" />
                        <p class="text-sm">No scheduled messages</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
