<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { FileUp, Info, Send, Upload, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Group {
    id: number;
    name: string;
}

const props = defineProps<{
    groups: Group[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Bulk Operations', href: '/bulk-operations' },
];

const senderIds = ['cashless', 'Quezon City', 'TXTCMDR'];

// Import Contacts Form
const importForm = useForm({
    file: null as File | null,
    group_id: '',
});

// Bulk Send Form
const bulkSendForm = useForm({
    file: null as File | null,
    message: '',
    sender_id: 'TXTCMDR',
    mobile_column: 'mobile',
});

// Personalized Send Form
const personalizedForm = useForm({
    file: null as File | null,
    sender_id: 'TXTCMDR',
    import_contacts: false,
});

const handleImportFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        importForm.file = target.files[0];
    }
};

const handleBulkSendFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        bulkSendForm.file = target.files[0];
    }
};

const handlePersonalizedFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        personalizedForm.file = target.files[0];
    }
};

const importContacts = () => {
    importForm.post('/contacts/import', {
        preserveScroll: true,
        onSuccess: () => {
            importForm.reset();
        },
    });
};

const bulkSend = () => {
    bulkSendForm.post('/bulk/send', {
        preserveScroll: true,
        onSuccess: () => {
            bulkSendForm.reset();
        },
    });
};

const sendPersonalized = () => {
    personalizedForm.post('/bulk/send-personalized', {
        preserveScroll: true,
        onSuccess: () => {
            personalizedForm.reset();
        },
    });
};

const messageCount = computed(() => {
    if (!bulkSendForm.message) return 0;
    return Math.ceil(bulkSendForm.message.length / 160);
});

const remainingChars = computed(() => {
    return 1600 - bulkSendForm.message.length;
});
</script>

<template>
    <Head title="Bulk Operations" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-4">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-bold">Bulk Operations</h1>
                <p class="text-sm text-muted-foreground">
                    Import contacts and send bulk SMS messages
                </p>
            </div>

            <!-- Import Contacts Card -->
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Users class="h-5 w-5 text-primary" />
                        <CardTitle>Import Contacts</CardTitle>
                    </div>
                    <CardDescription>
                        Upload a CSV or XLSX file to import contacts in bulk
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="importContacts" class="space-y-4">
                        <Alert>
                            <Info class="h-4 w-4" />
                            <AlertDescription>
                                CSV format: <code class="font-mono text-xs">mobile, name (optional), email (optional)</code>
                            </AlertDescription>
                        </Alert>

                        <div>
                            <Label for="import-file">CSV/XLSX File</Label>
                            <Input
                                id="import-file"
                                type="file"
                                accept=".csv,.xlsx,.xls"
                                @change="handleImportFileChange"
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
                            <Label for="import-group">Assign to Group (Optional)</Label>
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

                        <Button type="submit" :disabled="importForm.processing">
                            <Upload class="mr-2 h-4 w-4" />
                            {{ importForm.processing ? 'Importing...' : 'Import Contacts' }}
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <!-- Bulk Send from File Card -->
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <FileUp class="h-5 w-5 text-primary" />
                        <CardTitle>Bulk Send from File</CardTitle>
                    </div>
                    <CardDescription>
                        Send the same message to all phone numbers in a file
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="bulkSend" class="space-y-4">
                        <Alert>
                            <Info class="h-4 w-4" />
                            <AlertDescription>
                                Upload a file with phone numbers and send the same message to all. Column name: <code class="font-mono text-xs">mobile</code>
                            </AlertDescription>
                        </Alert>

                        <div>
                            <Label for="bulk-file">CSV/XLSX File</Label>
                            <Input
                                id="bulk-file"
                                type="file"
                                accept=".csv,.xlsx,.xls"
                                @change="handleBulkSendFileChange"
                                required
                            />
                            <p
                                v-if="bulkSendForm.errors.file"
                                class="mt-1 text-sm text-destructive"
                            >
                                {{ bulkSendForm.errors.file }}
                            </p>
                        </div>

                        <div>
                            <Label for="bulk-message">Message</Label>
                            <Textarea
                                id="bulk-message"
                                v-model="bulkSendForm.message"
                                placeholder="Enter your message here..."
                                rows="4"
                                maxlength="1600"
                                required
                            />
                            <div class="mt-1 flex justify-between text-xs text-muted-foreground">
                                <span>{{ messageCount }} SMS</span>
                                <span>{{ remainingChars }} characters remaining</span>
                            </div>
                            <p
                                v-if="bulkSendForm.errors.message"
                                class="mt-1 text-sm text-destructive"
                            >
                                {{ bulkSendForm.errors.message }}
                            </p>
                        </div>

                        <div>
                            <Label for="bulk-sender">Sender ID</Label>
                            <Select v-model="bulkSendForm.sender_id">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="sender in senderIds"
                                        :key="sender"
                                        :value="sender"
                                    >
                                        {{ sender }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <Button type="submit" :disabled="bulkSendForm.processing">
                            <Send class="mr-2 h-4 w-4" />
                            {{ bulkSendForm.processing ? 'Sending...' : 'Send to All' }}
                        </Button>
                    </form>
                </CardContent>
            </Card>

            <!-- Personalized Bulk Send Card -->
            <Card>
                <CardHeader>
                    <div class="flex items-center gap-2">
                        <Send class="h-5 w-5 text-primary" />
                        <CardTitle>Personalized Bulk Send</CardTitle>
                    </div>
                    <CardDescription>
                        Send personalized messages with variable substitution
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="sendPersonalized" class="space-y-4">
                        <Alert>
                            <Info class="h-4 w-4" />
                            <AlertDescription>
                                <div class="space-y-1 text-sm">
                                    <p><strong>Format 1 (2 columns):</strong> <code class="font-mono text-xs">mobile, message</code></p>
                                    <p><strong>Format 2 (3 columns):</strong> <code class="font-mono text-xs">mobile, name, message</code></p>
                                    <p class="mt-2">Use variables: <code class="font-mono text-xs" v-text="'{{name}}'"></code>, <code class="font-mono text-xs" v-text="'{{mobile}}'"></code></p>
                                    <p class="text-xs text-muted-foreground">Example: "Hi <span v-text="'{{name}}'"></span>! Your account <span v-text="'{{mobile}}'"></span> is active."</p>
                                </div>
                            </AlertDescription>
                        </Alert>

                        <div>
                            <Label for="personalized-file">CSV/XLSX File</Label>
                            <Input
                                id="personalized-file"
                                type="file"
                                accept=".csv,.xlsx,.xls"
                                @change="handlePersonalizedFileChange"
                                required
                            />
                            <p
                                v-if="personalizedForm.errors.file"
                                class="mt-1 text-sm text-destructive"
                            >
                                {{ personalizedForm.errors.file }}
                            </p>
                        </div>

                        <div>
                            <Label for="personalized-sender">Sender ID</Label>
                            <Select v-model="personalizedForm.sender_id">
                                <SelectTrigger>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="sender in senderIds"
                                        :key="sender"
                                        :value="sender"
                                    >
                                        {{ sender }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="flex items-center space-x-2">
                            <input
                                id="import-contacts-checkbox"
                                type="checkbox"
                                v-model="personalizedForm.import_contacts"
                                class="h-4 w-4"
                            />
                            <Label for="import-contacts-checkbox" class="cursor-pointer">
                                Also import contacts from file
                            </Label>
                        </div>

                        <Button type="submit" :disabled="personalizedForm.processing">
                            <Send class="mr-2 h-4 w-4" />
                            {{ personalizedForm.processing ? 'Sending...' : 'Send Personalized Messages' }}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
