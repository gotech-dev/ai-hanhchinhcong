<template>
    <AdminLayout :auth="auth">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-900">Danh Sách Loại Trợ Lý</h2>
                    <p class="text-gray-600 mt-1">Quản lý các loại trợ lý có sẵn trong hệ thống</p>
                </div>
                <Link
                    href="/admin/assistant-types/create"
                    class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors"
                >
                    ➕ Tạo Loại Trợ Lý Mới
                </Link>
            </div>

            <!-- Success Message -->
            <div v-if="$page.props.flash?.success" class="bg-green-50 border border-green-200 rounded-lg p-4">
                <p class="text-sm text-green-600">{{ $page.props.flash.success }}</p>
            </div>

            <!-- Error Message -->
            <div v-if="errorMessage || $page.props.flash?.error" class="bg-red-50 border border-red-200 rounded-lg p-4">
                <p class="text-sm text-red-600">{{ errorMessage || $page.props.flash?.error }}</p>
            </div>

            <!-- Assistant Types List -->
            <div v-if="assistantTypes.length === 0" class="text-center py-12 bg-white rounded-lg shadow-sm">
                <p class="text-gray-600 mb-4">Chưa có loại trợ lý nào.</p>
                <Link
                    href="/admin/assistant-types/create"
                    class="inline-block px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"
                >
                    Tạo Loại Trợ Lý Đầu Tiên
                </Link>
            </div>

            <!-- Table View -->
            <div v-else class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                    Mã
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                    Tên
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">
                                    Mô tả
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap hidden lg:table-cell">
                                    Icon
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap hidden lg:table-cell">
                                    Màu
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap hidden sm:table-cell">
                                    Thứ tự
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap">
                                    Trạng thái
                                </th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap sticky right-0 bg-gray-50 z-20" style="box-shadow: 2px 0 5px -2px rgba(0,0,0,0.1);">
                                    Thao tác
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <tr
                                v-for="type in assistantTypes"
                                :key="type.id"
                                class="group hover:bg-gray-50 transition-colors"
                            >
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <code class="text-sm font-mono text-gray-900 bg-gray-100 px-2 py-1 rounded">
                                        {{ type.code }}
                                    </code>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="text-sm font-medium text-gray-900">{{ type.name }}</div>
                                </td>
                                <td class="px-4 py-4 hidden md:table-cell">
                                    <div class="text-sm text-gray-500 max-w-xs truncate">
                                        {{ type.description || 'Không có mô tả' }}
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap hidden lg:table-cell">
                                    <span class="text-2xl">{{ type.icon || '—' }}</span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap hidden lg:table-cell">
                                    <div v-if="type.color" class="flex items-center gap-2">
                                        <div
                                            class="w-6 h-6 rounded border border-gray-300"
                                            :style="{ backgroundColor: type.color }"
                                        ></div>
                                        <span class="text-sm text-gray-600 hidden xl:inline">{{ type.color }}</span>
                                    </div>
                                    <span v-else class="text-gray-400">—</span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-500 hidden sm:table-cell">
                                    {{ type.sort_order }}
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span
                                        :class="[
                                            'text-xs px-2 py-1 rounded font-medium',
                                            type.is_active
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-gray-100 text-gray-800'
                                        ]"
                                    >
                                        {{ type.is_active ? 'Hoạt động' : 'Tạm dừng' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-right text-sm font-medium sticky right-0 bg-white z-20 group-hover:bg-gray-50" style="box-shadow: 2px 0 5px -2px rgba(0,0,0,0.1);">
                                    <div class="flex justify-end gap-2 flex-nowrap">
                                        <button
                                            type="button"
                                            @click="editAssistantType(type.id)"
                                            class="inline-flex items-center px-2 py-1.5 sm:px-3 sm:py-2 text-xs sm:text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 hover:text-blue-900 transition-colors focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 whitespace-nowrap"
                                            title="Sửa loại trợ lý"
                                        >
                                            <svg class="w-3 h-3 sm:w-4 sm:h-4 sm:mr-1.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            <span class="hidden sm:inline">Sửa</span>
                                        </button>
                                        <button
                                            type="button"
                                            @click="deleteAssistantType(type.id, type.name)"
                                            class="inline-flex items-center px-2 py-1.5 sm:px-3 sm:py-2 text-xs sm:text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100 hover:text-red-900 transition-colors focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-1 whitespace-nowrap"
                                            title="Xóa loại trợ lý"
                                        >
                                            <svg class="w-3 h-3 sm:w-4 sm:h-4 sm:mr-1.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            <span class="hidden sm:inline">Xóa</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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
    assistantTypes: Array,
});

const errorMessage = ref('');

const editAssistantType = (typeId) => {
    console.log('Edit assistant type:', typeId);
    if (!typeId) {
        console.error('Type ID is missing');
        return;
    }
    router.visit(`/admin/assistant-types/${typeId}/edit`);
};

const deleteAssistantType = async (typeId, typeName) => {
    console.log('Delete assistant type:', typeId, typeName);
    if (!typeId) {
        console.error('Type ID is missing');
        return;
    }
    
    if (!confirm(`Bạn có chắc chắn muốn xóa loại trợ lý "${typeName}"?\n\nLưu ý: Không thể xóa nếu đang có Assistant nào sử dụng loại này.`)) {
        return;
    }
    
    errorMessage.value = '';
    
    try {
        await axios.delete(`/admin/assistant-types/${typeId}`);
        router.reload();
    } catch (error) {
        console.error('Error deleting assistant type:', error);
        const message = error.response?.data?.error || error.response?.data?.message || 'Không thể xóa loại trợ lý. Vui lòng thử lại.';
        errorMessage.value = message;
    }
};
</script>

