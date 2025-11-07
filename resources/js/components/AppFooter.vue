<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();

const version = computed(() => page.props.version as {
    version: string;
    frameworks: {
        laravel: string;
        php: string;
        vue: string;
        inertia: string;
    };
});
</script>

<template>
    <footer class="mt-auto border-t bg-background py-4">
        <div class="container flex flex-col items-center justify-between gap-2 text-xs text-muted-foreground sm:flex-row">
            <div class="flex items-center gap-2">
                <span>Text Commander</span>
                <span class="font-mono">{{ version.version }}</span>
            </div>
            <div class="flex items-center gap-3">
                <span>Laravel {{ version.frameworks.laravel }}</span>
                <span>·</span>
                <span>Vue {{ version.frameworks.vue }}</span>
                <span>·</span>
                <span>Inertia {{ version.frameworks.inertia }}</span>
                <span>·</span>
                <span>PHP {{ version.frameworks.php }}</span>
            </div>
        </div>
    </footer>
</template>
