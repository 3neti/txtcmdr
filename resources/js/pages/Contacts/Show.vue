<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Calendar,
    CheckCircle,
    MessageSquare,
    Pencil,
    Phone,
    RefreshCw,
    Send,
    User,
    Users,
    XCircle,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Group {
    id: number;
    name: string;
}

interface Contact {
    id: number;
    mobile: string;
    name: string | null;
    email: string | null;
    groups: Group[];
}

interface Stats {
    total: number;
    sent: number;
    failed: number;
    successRate: number;
    lastMessageAt: string | null;
}

interface Message {
    id: number;
    message: string;
    status: 'sent' | 'failed' | 'pending';
    sender_id: string;
    sent_at: string | null;
    failed_at: string | null;
    created_at: string;
    error_message: string | null;
    scheduled_message_id: number | null;
}

interface PaginatedMessages {
    data: Message[];
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    contact: Contact;
    stats: Stats;
    messages: PaginatedMessages;
    allGroups: Group[];
    currentFilter: string;
    senderIds: string[];
    defaultSenderId: string | null;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Contacts', href: '/contacts' },
    { title: props.contact.name || props.contact.mobile, href: '' },
];

const sendForm = useForm({
    recipients: [props.contact.mobile],
    message: '',
    sender_id: props.defaultSenderId || '',
});

const formatPhone = (mobile: string) => {
    if (mobile.startsWith('+63')) {
        return mobile.replace('+63', '0');
    }
    return mobile;
};

const formatRelativeTime = (dateString: string | null) => {
    if (!dateString) return 'Never';

    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} min${diffMins > 1 ? 's' : ''} ago`;
    if (diffHours < 24)
        return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
    if (diffDays === 1) return 'Yesterday';
    if (diffDays < 7) return `${diffDays} days ago`;

    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined,
    });
};

const formatTime = (dateString: string) => {
    return new Date(dateString).toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
    });
};

const getStatusVariant = (
    status: string,
): 'default' | 'secondary' | 'destructive' => {
    switch (status) {
        case 'sent':
            return 'default';
        case 'failed':
            return 'destructive';
        case 'pending':
            return 'secondary';
        default:
            return 'secondary';
    }
};

const filterByStatus = (status: string) => {
    router.visit(`/contacts/${props.contact.id}?status=${status}`, {
        preserveState: true,
        preserveScroll: true,
    });
};

const retryMessage = (messageId: number) => {
    router.post(
        `/message-logs/${messageId}/retry`,
        {},
        {
            preserveScroll: true,
        },
    );
};

const sendMessage = () => {
    sendForm.post('/sms/send', {
        preserveScroll: true,
        onSuccess: () => {
            sendForm.reset('message');
            // Reload the page to show the new message
            router.reload();
        },
    });
};

const characterCount = computed(() => sendForm.message.length);
const smsCount = computed(() => Math.ceil(characterCount.value / 160) || 1);
</script>

<template>
    <Head :title="contact.name || contact.mobile" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
            <!-- Back Button & Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Link href="/contacts">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft class="h-5 w-5" />
                        </Button>
                    </Link>
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 text-primary"
                        >
                            <User class="h-8 w-8" />
                        </div>
                        <div>
                            <h1 class="text-3xl font-bold">
                                {{ contact.name || 'Unknown' }}
                            </h1>
                            <div
                                class="flex items-center gap-2 text-muted-foreground"
                            >
                                <Phone class="h-4 w-4" />
                                <span>{{ formatPhone(contact.mobile) }}</span>
                            </div>
                            <div
                                v-if="contact.groups.length > 0"
                                class="mt-1 flex gap-1"
                            >
                                <Badge
                                    v-for="group in contact.groups"
                                    :key="group.id"
                                    variant="outline"
                                >
                                    <Users class="mr-1 h-3 w-3" />
                                    {{ group.name }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </div>
                <Link :href="`/contacts`">
                    <Button variant="outline">
                        <Pencil class="mr-2 h-4 w-4" />
                        Edit Contact
                    </Button>
                </Link>
            </div>

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-4">
                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Total Messages
                        </CardTitle>
                        <MessageSquare class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.total }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Success Rate
                        </CardTitle>
                        <CheckCircle class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">
                            {{ stats.successRate }}%
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{ stats.sent }} sent
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Failed Messages
                        </CardTitle>
                        <XCircle class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold">{{ stats.failed }}</div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 pb-2"
                    >
                        <CardTitle class="text-sm font-medium">
                            Last Message
                        </CardTitle>
                        <Calendar class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-sm font-medium">
                            {{ formatRelativeTime(stats.lastMessageAt) }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Quick Send Form -->
            <Card>
                <CardHeader>
                    <CardTitle>
                        <div class="flex items-center gap-2">
                            <Send class="h-5 w-5" />
                            <span
                                >Send Message to
                                {{ contact.name || 'Contact' }}</span
                            >
                        </div>
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="sendMessage" class="space-y-4">
                        <div>
                            <Label for="message">Message</Label>
                            <Textarea
                                id="message"
                                v-model="sendForm.message"
                                placeholder="Type your message here..."
                                rows="3"
                                :maxlength="1600"
                                required
                            />
                            <div
                                class="mt-1 flex justify-between text-xs text-muted-foreground"
                            >
                                <span
                                    >{{ characterCount }} / 1600
                                    characters</span
                                >
                                <span>≈ {{ smsCount }} SMS</span>
                            </div>
                        </div>

                        <div>
                            <Label for="sender_id">Sender ID</Label>
                            <Select v-model="sendForm.sender_id" required>
                                <SelectTrigger>
                                    <SelectValue
                                        :placeholder="
                                            senderIds.length > 0
                                                ? 'Select sender ID'
                                                : 'No sender IDs configured'
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
                            <p
                                v-if="senderIds.length === 0"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                Configure sender IDs in Settings → SMS
                            </p>
                        </div>

                        <Button
                            type="submit"
                            :disabled="sendForm.processing || !sendForm.message"
                        >
                            <Send class="mr-2 h-4 w-4" />
                            {{
                                sendForm.processing
                                    ? 'Sending...'
                                    : 'Send Message'
                            }}
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <!-- Message History -->
            <Card>
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle>Message History</CardTitle>
                        <Select
                            :model-value="currentFilter"
                            @update:model-value="filterByStatus"
                        >
                            <SelectTrigger class="w-32">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All</SelectItem>
                                <SelectItem value="sent">Sent</SelectItem>
                                <SelectItem value="failed">Failed</SelectItem>
                                <SelectItem value="pending">Pending</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </CardHeader>
                <CardContent>
                    <!-- Messages List -->
                    <div v-if="messages.data.length > 0" class="space-y-4">
                        <div
                            v-for="message in messages.data"
                            :key="message.id"
                            class="rounded-lg border bg-card p-4"
                        >
                            <!-- Header -->
                            <div
                                class="mb-2 flex items-center justify-between gap-2"
                            >
                                <div class="flex items-center gap-2">
                                    <Badge
                                        :variant="
                                            getStatusVariant(message.status)
                                        "
                                    >
                                        {{ message.status }}
                                    </Badge>
                                    <span class="text-sm text-muted-foreground">
                                        Sender: {{ message.sender_id }}
                                    </span>
                                </div>
                                <span class="text-xs text-muted-foreground">
                                    {{ formatRelativeTime(message.created_at) }}
                                </span>
                            </div>

                            <!-- Message Text -->
                            <p class="text-sm">{{ message.message }}</p>

                            <!-- Error Message -->
                            <div
                                v-if="
                                    message.status === 'failed' &&
                                    message.error_message
                                "
                                class="mt-2 rounded bg-destructive/10 p-2"
                            >
                                <p class="text-xs text-destructive">
                                    Error: {{ message.error_message }}
                                </p>
                            </div>

                            <!-- Footer -->
                            <div
                                class="mt-2 flex items-center justify-between border-t pt-2"
                            >
                                <span class="text-xs text-muted-foreground">
                                    {{
                                        message.sent_at
                                            ? `Sent at ${formatTime(message.sent_at)}`
                                            : message.failed_at
                                              ? `Failed at ${formatTime(message.failed_at)}`
                                              : `Created ${formatTime(message.created_at)}`
                                    }}
                                </span>
                                <Button
                                    v-if="message.status === 'failed'"
                                    size="sm"
                                    variant="ghost"
                                    @click="retryMessage(message.id)"
                                >
                                    <RefreshCw class="mr-1 h-3 w-3" />
                                    Retry
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="py-12 text-center">
                        <MessageSquare
                            class="mx-auto h-12 w-12 text-muted-foreground/30"
                        />
                        <p class="mt-2 text-sm text-muted-foreground">
                            No messages sent to this contact yet
                        </p>
                    </div>

                    <!-- Pagination -->
                    <div
                        v-if="messages.links.length > 3"
                        class="mt-6 flex justify-center gap-1"
                    >
                        <Link
                            v-for="(link, index) in messages.links"
                            :key="index"
                            :href="link.url || ''"
                            :class="[
                                'rounded px-3 py-1 text-sm',
                                link.active
                                    ? 'bg-primary text-primary-foreground'
                                    : 'hover:bg-accent',
                                !link.url && 'cursor-not-allowed opacity-50',
                            ]"
                            :preserve-scroll="true"
                            v-html="link.label"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
