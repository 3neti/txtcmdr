<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { ScrollArea } from '@/components/ui/scroll-area';
import { router } from '@inertiajs/vue3';
import { MessageSquare, RefreshCw } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

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

interface Contact {
    id: number;
    name: string | null;
    mobile: string;
}

interface MessageHistoryData {
    contact: Contact;
    messages: Message[];
    total: number;
}

const props = defineProps<{
    contactId: number | null;
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const isOpen = computed({
    get: () => props.open,
    set: (value) => emit('update:open', value),
});

const loading = ref(false);
const historyData = ref<MessageHistoryData | null>(null);

const loadMessages = async () => {
    if (!props.contactId) return;

    loading.value = true;
    try {
        const response = await fetch(`/contacts/${props.contactId}/messages`);
        if (!response.ok) throw new Error('Failed to fetch messages');
        historyData.value = await response.json();
    } catch (error) {
        console.error('Error loading messages:', error);
        historyData.value = null;
    } finally {
        loading.value = false;
    }
};

watch(
    () => props.open,
    (newValue) => {
        if (newValue && props.contactId) {
            loadMessages();
        }
    },
);

const formatRelativeTime = (dateString: string) => {
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

const retryMessage = (messageId: number) => {
    router.post(
        `/message-logs/${messageId}/retry`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                loadMessages(); // Reload messages after retry
            },
        },
    );
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogContent class="max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    <div class="flex items-center gap-2">
                        <MessageSquare class="h-5 w-5" />
                        <span>Message History</span>
                    </div>
                </DialogTitle>
                <DialogDescription v-if="historyData?.contact">
                    {{ historyData.contact.name || 'Unknown' }} •
                    {{ historyData.contact.mobile }}
                </DialogDescription>
            </DialogHeader>

            <!-- Loading State -->
            <div v-if="loading" class="flex items-center justify-center py-12">
                <div class="text-center">
                    <RefreshCw
                        class="mx-auto h-8 w-8 animate-spin text-muted-foreground"
                    />
                    <p class="mt-2 text-sm text-muted-foreground">
                        Loading messages...
                    </p>
                </div>
            </div>

            <!-- Messages List -->
            <ScrollArea
                v-else-if="historyData && historyData.messages.length > 0"
                class="h-[500px] pr-4"
            >
                <div class="space-y-4">
                    <div
                        v-for="message in historyData.messages"
                        :key="message.id"
                        class="rounded-lg border bg-card p-4"
                    >
                        <!-- Header: Status + Sender + Time -->
                        <div
                            class="mb-2 flex items-center justify-between gap-2"
                        >
                            <div class="flex items-center gap-2">
                                <Badge
                                    :variant="getStatusVariant(message.status)"
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

                        <!-- Error Message (if failed) -->
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

                        <!-- Footer: Timestamp + Actions -->
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
            </ScrollArea>

            <!-- Empty State -->
            <div
                v-else-if="historyData && historyData.messages.length === 0"
                class="py-12 text-center"
            >
                <MessageSquare
                    class="mx-auto h-12 w-12 text-muted-foreground/30"
                />
                <p class="mt-2 text-sm text-muted-foreground">
                    No messages sent to this contact yet
                </p>
            </div>

            <!-- Total Count -->
            <div
                v-if="historyData && historyData.messages.length > 0"
                class="border-t pt-4 text-center text-sm text-muted-foreground"
            >
                Showing {{ historyData.total }} message{{
                    historyData.total !== 1 ? 's' : ''
                }}
            </div>
        </DialogContent>
    </Dialog>
</template>
