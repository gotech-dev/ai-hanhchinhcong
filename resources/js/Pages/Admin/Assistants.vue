<template>
    <AdminLayout :auth="auth">
        <div class="space-y-6">
            <!-- Header with Create Button -->
            <div class="flex justify-between items-center">
                <h2 class="text-2xl font-semibold text-gray-900">Assistants</h2>
                <Link
                    href="/admin/assistants/create"
                    class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"
                >
                    + Tạo Assistant Mới
                </Link>
            </div>

            <!-- Assistants List -->
            <div v-if="assistants.data.length === 0" class="text-center py-12 bg-white rounded-lg">
                <p class="text-gray-600">Chưa có assistant nào.</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="assistant in assistants.data"
                    :key="assistant.id"
                    class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow"
                >
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">{{ assistant.name }}</h3>
                        <span
                            :class="[
                                'text-xs px-2 py-1 rounded',
                                assistant.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                            ]"
                        >
                            {{ assistant.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p class="text-gray-600 text-sm mb-4">{{ assistant.description || 'Không có mô tả' }}</p>
                    <div class="flex space-x-2">
                        <Link
                            :href="`/admin/assistants/${assistant.id}/preview`"
                            class="px-3 py-1 text-sm bg-blue-100 text-blue-700 rounded hover:bg-blue-200"
                        >
                            Preview
                        </Link>
                        <button
                            @click="editAssistant(assistant)"
                            class="px-3 py-1 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200"
                        >
                            Sửa
                        </button>
                        <button
                            @click="deleteAssistant(assistant.id)"
                            class="px-3 py-1 text-sm bg-red-100 text-red-700 rounded hover:bg-red-200"
                        >
                            Xóa
                        </button>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="assistants.last_page > 1" class="flex justify-center items-center space-x-4 mt-6">
                <Link
                    v-if="assistants.current_page > 1"
                    :href="assistants.prev_page_url"
                    class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 text-sm font-medium text-gray-700"
                >
                    ← Previous
                </Link>
                
                <span class="text-gray-600 text-sm">
                    Page {{ assistants.current_page }} of {{ assistants.last_page }}
                </span>
                
                <Link
                    v-if="assistants.current_page < assistants.last_page"
                    :href="assistants.next_page_url"
                    class="px-4 py-2 bg-gray-200 rounded hover:bg-gray-300 text-sm font-medium text-gray-700"
                >
                    Next →
                </Link>
            </div>

        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import axios from 'axios';

const props = defineProps({
    auth: Object,
    assistants: Object,
});


const editAssistant = (assistant) => {
    router.visit(`/admin/assistants/${assistant.id}/edit`);
};

const deleteAssistant = async (assistantId) => {
    if (!confirm('Bạn có chắc chắn muốn xóa assistant này?')) return;
    
    try {
        await axios.delete(`/api/assistants/${assistantId}`);
        router.reload();
    } catch (error) {
        console.error('Error deleting assistant:', error);
        alert('Không thể xóa assistant. Vui lòng thử lại.');
    }
};
</script>

