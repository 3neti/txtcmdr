<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import { type BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2, Upload, User, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

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
    created_at: string;
}

const props = defineProps<{
    contacts: Contact[];
    groups: Group[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Contacts', href: '/contacts' },
];

const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const showImportDialog = ref(false);
const showDeleteDialog = ref(false);
const contactToDelete = ref<Contact | null>(null);
const contactToEdit = ref<Contact | null>(null);
const searchQuery = ref('');

const form = useForm({
    mobile: '',
    name: '',
    email: '',
    group_ids: [] as number[],
});

const importForm = useForm({
    file: null as File | null,
    group_id: '',
});

const filteredContacts = computed(() => {
    if (!searchQuery.value) return props.contacts;

    const query = searchQuery.value.toLowerCase();
    return props.contacts.filter(
        (contact) =>
            contact.name?.toLowerCase().includes(query) ||
            contact.mobile.includes(query) ||
            contact.email?.toLowerCase().includes(query)
    );
});

const createContact = () => {
    form.post('/contacts', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showCreateDialog.value = false;
        },
    });
};

const openEditDialog = (contact: Contact) => {
    contactToEdit.value = contact;
    form.mobile = formatPhone(contact.mobile);
    form.name = contact.name || '';
    form.email = contact.email || '';
    form.group_ids = contact.groups.map(g => g.id);
    showEditDialog.value = true;
};

const updateContact = () => {
    if (!contactToEdit.value) return;
    
    form.put(`/contacts/${contactToEdit.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showEditDialog.value = false;
            contactToEdit.value = null;
        },
    });
};

const handleFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        importForm.file = target.files[0];
    }
};

const importContacts = () => {
    importForm.post('/contacts/import', {
        preserveScroll: true,
        onSuccess: () => {
            importForm.reset();
            showImportDialog.value = false;
        },
    });
};

const confirmDelete = (contact: Contact) => {
    contactToDelete.value = contact;
    showDeleteDialog.value = true;
};

const deleteContact = () => {
    if (!contactToDelete.value) return;

    router.delete(`/contacts/${contactToDelete.value.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteDialog.value = false;
            contactToDelete.value = null;
        },
    });
};

const formatPhone = (mobile: string) => {
    if (mobile.startsWith('+63')) {
        return mobile.replace('+63', '0');
    }
    return mobile;
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
    <Head title="Contacts" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold">Contacts</h1>
                    <p class="text-sm text-muted-foreground">
                        Manage your contact list
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" @click="showImportDialog = true">
                        <Upload class="mr-2 h-4 w-4" />
                        Import CSV
                    </Button>
                    <Button @click="showCreateDialog = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Contact
                    </Button>
                </div>
            </div>

            <!-- Search -->
            <div class="w-full max-w-sm">
                <Input
                    v-model="searchQuery"
                    placeholder="Search contacts..."
                    class="w-full"
                />
            </div>

            <!-- Contacts List -->
            <div v-if="filteredContacts.length > 0" class="space-y-2">
                <div
                    v-for="contact in filteredContacts"
                    :key="contact.id"
                    class="group flex cursor-pointer items-center justify-between rounded-lg border bg-card p-4 shadow-sm hover:bg-accent"
                    @dblclick="openEditDialog(contact)"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10 text-primary"
                        >
                            <User class="h-5 w-5" />
                        </div>
                        <div class="flex flex-col">
                            <span class="font-medium">{{
                                contact.name || 'Unknown'
                            }}</span>
                            <span class="text-sm text-muted-foreground">{{
                                formatPhone(contact.mobile)
                            }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="flex flex-col items-end gap-1">
                            <div
                                v-if="contact.email"
                                class="text-sm text-muted-foreground"
                            >
                                {{ contact.email }}
                            </div>
                            <div
                                v-if="contact.groups.length > 0"
                                class="flex gap-1"
                            >
                                <div
                                    v-for="group in contact.groups"
                                    :key="group.id"
                                    class="rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary"
                                >
                                    {{ group.name }}
                                </div>
                            </div>
                        </div>
                        <div class="flex gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="opacity-0 transition-opacity group-hover:opacity-100"
                                @click.stop="openEditDialog(contact)"
                            >
                                <Pencil class="h-4 w-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="opacity-0 transition-opacity group-hover:opacity-100"
                                @click.stop="confirmDelete(contact)"
                            >
                                <Trash2 class="h-4 w-4 text-destructive" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty State -->
            <div
                v-else-if="!searchQuery && contacts.length === 0"
                class="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed p-12 text-center"
            >
                <Users class="mb-4 h-12 w-12 text-muted-foreground" />
                <h3 class="mb-2 text-lg font-semibold">No contacts yet</h3>
                <p class="mb-4 text-sm text-muted-foreground">
                    Add your first contact or import from CSV
                </p>
                <div class="flex gap-2">
                    <Button variant="outline" @click="showImportDialog = true">
                        <Upload class="mr-2 h-4 w-4" />
                        Import CSV
                    </Button>
                    <Button @click="showCreateDialog = true">
                        <Plus class="mr-2 h-4 w-4" />
                        Add Contact
                    </Button>
                </div>
            </div>

            <!-- No Search Results -->
            <div
                v-else-if="searchQuery && filteredContacts.length === 0"
                class="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed p-12 text-center"
            >
                <Users class="mb-4 h-8 w-8 text-muted-foreground" />
                <p class="text-sm text-muted-foreground">
                    No contacts found for "{{ searchQuery }}"
                </p>
            </div>
        </div>

        <!-- Create Contact Dialog -->
        <Dialog v-model:open="showCreateDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add New Contact</DialogTitle>
                    <DialogDescription>
                        Add a new contact to your list
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="createContact" class="space-y-4">
                    <div>
                        <Label for="mobile">Mobile Number</Label>
                        <Input
                            id="mobile"
                            v-model="form.mobile"
                            placeholder="09171234567 or +639171234567"
                            required
                        />
                        <p
                            v-if="form.errors.mobile"
                            class="mt-1 text-sm text-destructive"
                        >
                            {{ form.errors.mobile }}
                        </p>
                    </div>

                    <div>
                        <Label for="name">Name (Optional)</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="John Doe"
                        />
                    </div>

                    <div>
                        <Label for="email">Email (Optional)</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            placeholder="john@example.com"
                        />
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
                            {{ form.processing ? 'Adding...' : 'Add Contact' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Edit Contact Dialog -->
        <Dialog v-model:open="showEditDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Edit Contact</DialogTitle>
                    <DialogDescription>
                        Update contact information
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="updateContact" class="space-y-4">
                    <div>
                        <Label for="edit-mobile">Mobile Number</Label>
                        <Input
                            id="edit-mobile"
                            v-model="form.mobile"
                            placeholder="09171234567 or +639171234567"
                            required
                        />
                        <p
                            v-if="form.errors.mobile"
                            class="mt-1 text-sm text-destructive"
                        >
                            {{ form.errors.mobile }}
                        </p>
                    </div>

                    <div>
                        <Label for="edit-name">Name (Optional)</Label>
                        <Input
                            id="edit-name"
                            v-model="form.name"
                            placeholder="John Doe"
                        />
                    </div>

                    <div>
                        <Label for="edit-email">Email (Optional)</Label>
                        <Input
                            id="edit-email"
                            v-model="form.email"
                            type="email"
                            placeholder="john@example.com"
                        />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showEditDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Updating...' : 'Update Contact' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Import Dialog -->
        <Dialog v-model:open="showImportDialog">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Import Contacts from CSV</DialogTitle>
                    <DialogDescription>
                        Upload a CSV file with columns: mobile, name (optional), email (optional)
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="importContacts" class="space-y-4">
                    <div>
                        <Label for="file">CSV File</Label>
                        <Input
                            id="file"
                            type="file"
                            accept=".csv,.xlsx,.xls"
                            @change="handleFileChange"
                            required
                        />
                        <p
                            v-if="importForm.errors.file"
                            class="mt-1 text-sm text-destructive"
                        >
                            {{ importForm.errors.file }}
                        </p>
                    </div>

                    <div v-if="groups.length > 0">
                        <Label for="group">Assign to Group (Optional)</Label>
                        <Select v-model="importForm.group_id">
                            <SelectTrigger>
                                <SelectValue placeholder="Select a group" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="group in groups"
                                    :key="group.id"
                                    :value="group.id.toString()"
                                >
                                    {{ group.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showImportDialog = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="importForm.processing">
                            {{
                                importForm.processing
                                    ? 'Importing...'
                                    : 'Import'
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
                    <AlertDialogTitle>Delete Contact?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Are you sure you want to delete "{{
                            contactToDelete?.name || contactToDelete?.mobile
                        }}"? This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction @click="deleteContact">
                        Delete
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </AppLayout>
</template>
