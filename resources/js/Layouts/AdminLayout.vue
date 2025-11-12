<template>
    <AppLayout :auth="auth">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900">Admin Panel</h1>
                <p class="text-gray-600 mt-2">Quản lý assistants và documents</p>
            </div>
            
            <!-- Menu Bar -->
            <div class="mb-6 bg-white rounded-lg shadow-sm border border-gray-200">
                <div class="flex items-center space-x-1 p-2">
                    <Link
                        href="/admin/assistant-types/create"
                        class="px-4 py-2 text-sm font-medium rounded-md transition-colors"
                        :class="isCreatePage 
                            ? 'bg-blue-50 text-blue-700 border border-blue-200' 
                            : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900'"
                    >
                        ➕ Tạo loại trợ lý
                    </Link>
                    <Link
                        href="/admin/assistant-types"
                        class="px-4 py-2 text-sm font-medium rounded-md transition-colors"
                        :class="isListPage 
                            ? 'bg-blue-50 text-blue-700 border border-blue-200' 
                            : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900'"
                    >
                        📋 List loại trợ lý
                    </Link>
                </div>
            </div>
            
            <slot />
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from './AppLayout.vue';

defineProps({
    auth: Object,
});

const page = usePage();

const isCreatePage = computed(() => {
    return page.url === '/admin/assistant-types/create';
});

const isListPage = computed(() => {
    const url = page.url;
    return url === '/admin/assistant-types' || 
           (url.startsWith('/admin/assistant-types/') && url !== '/admin/assistant-types/create');
});
</script>







