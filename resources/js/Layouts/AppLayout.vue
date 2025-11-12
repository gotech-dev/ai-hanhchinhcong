<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Navigation -->
        <nav class="bg-white shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex">
                        <Link href="/" class="flex items-center px-4 text-xl font-semibold text-gray-900">
                            🤖 AI Hành Chính Công
                        </Link>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <Link
                            v-if="auth.user"
                            href="/assistants"
                            class="px-4 py-2 text-sm text-gray-700 hover:text-gray-900"
                        >
                            Assistants
                        </Link>
                        <Link
                            v-if="auth.user"
                            href="/chat"
                            class="px-4 py-2 text-sm text-gray-700 hover:text-gray-900"
                        >
                            Chat
                        </Link>
                        <Link
                            v-if="auth.user && auth.user.is_admin"
                            href="/admin/assistants"
                            class="px-4 py-2 text-sm text-gray-700 hover:text-gray-900"
                        >
                            Admin
                        </Link>
                        <Link
                            v-if="auth.user"
                            href="/logout"
                            method="post"
                            class="px-4 py-2 text-sm text-gray-700 hover:text-gray-900"
                        >
                            Logout
                        </Link>
                        <Link
                            v-else
                            href="/login"
                            class="px-4 py-2 text-sm text-gray-700 hover:text-gray-900"
                        >
                            Login
                        </Link>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main>
            <slot />
        </main>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    auth: Object,
});
</script>

