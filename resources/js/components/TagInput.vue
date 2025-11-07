<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Props {
    modelValue?: string[];
    placeholder?: string;
    name?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: () => [],
    placeholder: 'Type and press Enter or comma',
});

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

const tags = ref<string[]>([...props.modelValue]);
const inputValue = ref('');

const tagsString = computed(() => tags.value.join(', '));

const addTag = () => {
    const value = inputValue.value.trim();
    if (value && !tags.value.includes(value)) {
        tags.value.push(value);
        emit('update:modelValue', tags.value);
    }
    inputValue.value = '';
};

const removeTag = (index: number) => {
    tags.value.splice(index, 1);
    emit('update:modelValue', tags.value);
};

const handleInput = (event: Event) => {
    const target = event.target as HTMLInputElement;
    const value = target.value;

    // Check if comma was entered
    if (value.includes(',')) {
        const parts = value
            .split(',')
            .map((s) => s.trim())
            .filter(Boolean);
        parts.forEach((part) => {
            if (part && !tags.value.includes(part)) {
                tags.value.push(part);
            }
        });
        emit('update:modelValue', tags.value);
        inputValue.value = '';
    } else {
        inputValue.value = value;
    }
};

const handleKeydown = (event: KeyboardEvent) => {
    if (event.key === 'Enter') {
        event.preventDefault();
        addTag();
    } else if (
        event.key === 'Backspace' &&
        !inputValue.value &&
        tags.value.length > 0
    ) {
        // Remove last tag on backspace when input is empty
        removeTag(tags.value.length - 1);
    }
};
</script>

<template>
    <div class="space-y-2">
        <!-- Tags Display -->
        <div v-if="tags.length > 0" class="flex flex-wrap gap-2">
            <Badge
                v-for="(tag, index) in tags"
                :key="index"
                variant="secondary"
                class="flex items-center gap-1 py-1 pr-1.5 pl-2.5"
            >
                <span>{{ tag }}</span>
                <button
                    type="button"
                    @click="removeTag(index)"
                    class="ml-1 rounded-sm opacity-70 ring-offset-background transition-opacity hover:opacity-100 focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                >
                    <X class="h-3 w-3" />
                    <span class="sr-only">Remove {{ tag }}</span>
                </button>
            </Badge>
        </div>

        <!-- Input -->
        <Input
            v-model="inputValue"
            :placeholder="placeholder"
            @input="handleInput"
            @keydown="handleKeydown"
            autocomplete="off"
        />

        <!-- Hidden input for form submission -->
        <input v-if="name" type="hidden" :name="name" :value="tagsString" />
    </div>
</template>
