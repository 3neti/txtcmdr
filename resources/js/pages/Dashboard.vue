<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Calendar, MessageSquare, Send, Users, UsersRound } from 'lucide-vue-next';

interface Stats {
    totalGroups: number;
    totalContacts: number;
    scheduledMessages: number;
    sentMessages: number;
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

defineProps<{
    stats: Stats;
    recentGroups: Group[];
    recentScheduled: ScheduledMessage[];
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
