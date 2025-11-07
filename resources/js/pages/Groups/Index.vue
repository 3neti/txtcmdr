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
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Plus, Trash2, Users } from 'lucide-vue-next';
import { ref } from 'vue';

interface Group {
    id: number;
    name: string;
    description: string | null;
    contacts_count: number;
    created_at: string;
}

const props = defineProps<{
    groups: Group[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Groups', href: '/groups' },
];

const showCreateDialog = ref(false);
const showDeleteDialog = ref(false);
const groupToDelete = ref<Group | null>(null);

const form = useForm({
    name: '',
    description: '',
});

const createGroup = () => {
    form.post('/groups', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showCreateDialog.value = false;
        },
    });
};

const confirmDelete = (group: Group) => {
    groupToDelete.value = group;
    showDeleteDialog.value = true;
};

const deleteGroup = () => {
    if (!groupToDelete.value) return;

    router.delete(`/groups/${groupToDelete.value.id}`, {
        onSuccess: () => {
            showDeleteDialog.value = false;
            groupToDelete.value = null;
        },
    });
};

const viewGroup = (id: number) => {
    router.visit(`/groups/${id}`);
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <Head title="Groups" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Groups</h1>
                    <p class="text-sm text-muted-foreground">
                        Organize your contacts into groups for easy messaging
                    </p>
                </div>
                <Button @click="showCreateDialog = true">
                    <Plus class="mr-2 h-4 w-4" />
                    New Group
                </Button>
            </div>

            <!-- Groups Grid -->
            <div
                v-if="groups.length > 0"
                class="grid gap-4 md:grid-cols-2 lg:grid-cols-3"
            >
                <div
                    v-for="group in groups"
                    :key="group.id"
                    class="group relative flex cursor-pointer flex-col gap-3 rounded-xl border bg-card p-6 shadow-sm transition-colors hover:bg-accent"
                    @click="viewGroup(group.id)"
                >
                    <div class="flex items-start justify-between">
                        <div class="flex items-center gap-3">
                            <div
                                class="rounded-lg bg-primary/10 p-2 text-primary"
                            >
                                <Users class="h-5 w-5" />
                            </div>
                            <div>
                                <h3 class="font-semibold">{{ group.name }}</h3>
                                <p class="text-sm text-muted-foreground">
                                    {{ group.contacts_count }} contact(s)
                                </p>
                            </div>
                        </div>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="opacity-0 transition-opacity group-hover:opacity-100"
                            @click.stop="confirmDelete(group)"
                        >
                            <Trash2 class="h-4 w-4 text-destructive" />
                        </Button>
                    </div>

                    <p
                        v-if="group.description"
                        class="line-clamp-2 text-sm text-muted-foreground"
                    >
                        {{ group.description }}
                    </p>

                    <div class="mt-auto pt-2 text-xs text-muted-foreground">
                        Created {{ formatDate(group.created_at) }}
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else
                class="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed p-12 text-center"
            >
                <Users class="mb-4 h-12 w-12 text-muted-foreground" />
                <h3 class="mb-2 text-lg font-semibold">No groups yet</h3>
                <p class="mb-4 text-sm text-muted-foreground">
                    Create your first group to organize your contacts
                </p>
                <Button @click="showCreateDialog = true">
                    <Plus class="mr-2 h-4 w-4" />
                    Create Group
                </Button>
            </div>
        </div>

        <!-- Create Group Dialog -->
        <Dialog v-model:open="showCreateDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create New Group</DialogTitle>
                    <DialogDescription>
                        Add a new group to organize your contacts
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="createGroup" class="space-y-4">
                    <div>
                        <label
                            for="name"
                            class="mb-1 block text-sm font-medium"
                        >
                            Group Name
                        </label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="e.g., VIP Customers, Team Members"
                            required
                        />
                        <p
                            v-if="form.errors.name"
                            class="mt-1 text-sm text-destructive"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div>
                        <label
                            for="description"
                            class="mb-1 block text-sm font-medium"
                        >
                            Description (Optional)
                        </label>
                        <Textarea
                            id="description"
                            v-model="form.description"
                            placeholder="Brief description of this group"
                            rows="3"
                        />
                        <p
                            v-if="form.errors.description"
                            class="mt-1 text-sm text-destructive"
                        >
                            {{ form.errors.description }}
                        </p>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showCreateDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{
                                form.processing ? 'Creating...' : 'Create Group'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Delete Confirmation Dialog -->
        <AlertDialog v-model:open="showDeleteDialog">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete Group?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to delete "{{
                            groupToDelete?.name
                        }}"? This action cannot be undone. Contacts in this
                        group will not be deleted.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction @click="deleteGroup">
                        Delete
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
