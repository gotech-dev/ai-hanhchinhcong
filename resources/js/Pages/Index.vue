<template>
    <div class="min-h-screen bg-gray-50">
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">
                    AI Hành Chính Công
                </h1>
                <p class="text-xl text-gray-600 mb-8">
                    Trợ lý AI thông minh cho công việc hành chính
                </p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl mx-auto">
                    <button
                        @click="handleChatClick"
                        class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow text-left w-full"
                    >
                        <div class="text-2xl mb-2">💬</div>
                        <h2 class="text-xl font-semibold mb-2">Chat với AI</h2>
                        <p class="text-gray-600">Tương tác với trợ lý AI thông minh</p>
                    </button>
                    
                    <Link
                        href="/admin/login"
                        class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow text-left w-full"
                    >
                        <div class="text-2xl mb-2">⚙️</div>
                        <h2 class="text-xl font-semibold mb-2">Quản lý</h2>
                        <p class="text-gray-600">Quản lý assistants và documents</p>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    auth: Object,
});

const handleChatClick = () => {
    // If user is logged in, go directly to /chat
    if (props.auth?.user) {
        router.visit('/chat');
    } else {
        // If not logged in, go to login page with intended redirect to /chat
        router.visit('/login?intended=/chat');
    }
};
</script>

