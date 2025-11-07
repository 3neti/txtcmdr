<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, Send, User } from 'lucide-vue-next';

interface Contact {
    id: number;
    mobile: string;
    name: string | null;
    email: string | null;
}

interface Group {
    id: number;
    name: string;
    description: string | null;
    contacts_count: number;
    contacts: Contact[];
    created_at: string;
}

const props = defineProps<{
    group: Group;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Groups', href: '/groups' },
    { title: props.group.name, href: `/groups/${props.group.id}` },
];

const sendToGroup = () => {
    // TODO: Navigate to send SMS page with group pre-selected
    router.visit('/send-sms');
};

const formatPhone = (mobile: string) => {
    // Format phone number for display
    if (mobile.startsWith('+63')) {
        return mobile.replace('+63', '0');
    }
    return mobile;
};
</script>

<template>
    <Head :title="group.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <Button
                        variant="ghost"
                        size="icon"
                        @click="router.visit('/groups')"
                    >
                        <ArrowLeft class="h-5 w-5" />
                    </Button>
                    <div>
                        <h1 class="text-2xl font-bold">{{ group.name }}</h1>
                        <p
                            v-if="group.description"
                            class="text-sm text-muted-foreground"
                        >
                            {{ group.description }}
                        </p>
                    </div>
                </div>
                <Button @click="sendToGroup">
                    <Send class="mr-2 h-4 w-4" />
                    Send SMS to Group
                </Button>
            </div>

            <!-- Stats -->
            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <div class="text-sm text-muted-foreground">Total Contacts</div>
                <div class="text-3xl font-bold">{{ group.contacts_count }}</div>
            </div>

            <!-- Contacts List -->
            <div class="rounded-xl border bg-card p-6 shadow-sm">
                <h2 class="mb-4 text-lg font-semibold">Contacts</h2>

                <div v-if="group.contacts.length > 0" class="space-y-2">
                    <div
                        v-for="contact in group.contacts"
                        :key="contact.id"
                        class="flex items-center gap-4 rounded-lg border p-4"
                    >
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary"
                        >
                            <User class="h-5 w-5" />
                        </div>
                        <div class="flex-1">
                            <div class="font-medium">
                                {{ contact.name || 'Unknown' }}
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{ formatPhone(contact.mobile) }}
                            </div>
                        </div>
                        <div
                            v-if="contact.email"
                            class="text-sm text-muted-foreground"
                        >
                            {{ contact.email }}
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="flex flex-col items-center justify-center py-12 text-center text-muted-foreground"
                >
                    <User class="mb-2 h-8 w-8" />
                    <p class="text-sm">No contacts in this group</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
