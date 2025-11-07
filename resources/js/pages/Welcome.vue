<script setup lang="ts">
import { dashboard, login, register } from '@/routes';
import { Head, Link } from '@inertiajs/vue3';
import { BarChart3, Clock, MessageSquare, Users } from 'lucide-vue-next';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const features = [
    {
        icon: MessageSquare,
        title: 'Broadcast SMS',
        description:
            'Send messages to individuals, groups, or bulk recipients instantly',
    },
    {
        icon: Users,
        title: 'Manage Contacts',
        description: 'Organize contacts with groups and custom attributes',
    },
    {
        icon: Clock,
        title: 'Schedule Messages',
        description:
            'Schedule SMS for future delivery with flexible timing options',
    },
    {
        icon: BarChart3,
        title: 'Track Results',
        description:
            'Monitor message delivery with comprehensive analytics and history',
    },
];
</script>

<template>
    <Head title="Welcome to Text Commander" />
    <div
        class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 dark:from-slate-950 dark:to-slate-900"
    >
        <!-- Header -->
        <header
            class="border-b border-slate-200 bg-white/50 backdrop-blur-sm dark:border-slate-800 dark:bg-slate-950/50"
        >
            <div
                class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4"
            >
                <div class="flex items-center gap-2">
                    <MessageSquare
                        class="h-8 w-8 text-blue-600 dark:text-blue-400"
                        :stroke-width="2"
                    />
                    <span
                        class="text-xl font-bold text-slate-900 dark:text-slate-100"
                        >Text Commander</span
                    >
                </div>
                <nav class="flex items-center gap-3">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-lg bg-blue-600 px-6 py-2 text-sm font-medium text-white transition hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                    >
                        Dashboard
                    </Link>
                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-lg px-6 py-2 text-sm font-medium text-slate-700 transition hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800"
                        >
                            Log in
                        </Link>
                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="rounded-lg bg-blue-600 px-6 py-2 text-sm font-medium text-white transition hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
                        >
                            Sign Up
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Hero Section -->
        <main class="mx-auto max-w-7xl px-6 py-16 sm:py-24">
            <div class="text-center">
                <h1
                    class="text-4xl font-bold tracking-tight text-slate-900 sm:text-6xl dark:text-slate-100"
                >
                    SMS Broadcasting Made Simple
                </h1>
                <p
                    class="mt-6 text-lg leading-8 text-slate-600 dark:text-slate-400"
                >
                    Send targeted messages to groups of contacts with ease.
                    Manage contacts, schedule messages, and track delivery—all
                    in one powerful platform.
                </p>
                <div class="mt-10 flex items-center justify-center gap-x-6">
                    <Link
                        :href="$page.props.auth.user ? dashboard() : login()"
                        class="rounded-lg bg-blue-600 px-8 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600 dark:bg-blue-500 dark:hover:bg-blue-600"
                    >
                        {{
                            $page.props.auth.user
                                ? 'Go to Dashboard'
                                : 'Get Started'
                        }}
                    </Link>
                </div>
            </div>

            <!-- Features Grid -->
            <div class="mx-auto mt-24 max-w-5xl">
                <div
                    class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div
                        v-for="feature in features"
                        :key="feature.title"
                        class="relative rounded-2xl border border-slate-200 bg-white p-8 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600/10"
                        >
                            <component
                                :is="feature.icon"
                                class="h-6 w-6 text-blue-600 dark:text-blue-400"
                                :stroke-width="2"
                            />
                        </div>
                        <h3
                            class="mt-6 text-lg font-semibold text-slate-900 dark:text-slate-100"
                        >
                            {{ feature.title }}
                        </h3>
                        <p
                            class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-400"
                        >
                            {{ feature.description }}
                        </p>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer
            class="mt-auto border-t border-slate-200 py-6 dark:border-slate-800"
        >
            <div
                class="mx-auto max-w-7xl px-6 text-center text-sm text-slate-600 dark:text-slate-400"
            >
                <p>
                    © {{ new Date().getFullYear() }} Text Commander. Built with
                    Laravel & Vue.
                </p>
            </div>
        </footer>
    </div>
</template>
