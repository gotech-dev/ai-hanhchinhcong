<template>
    <AppLayout :auth="auth">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-900">AI Assistants</h1>
                <p class="text-gray-600 mt-2">Chọn một assistant để bắt đầu chat</p>
            </div>

            <div v-if="assistants.length === 0" class="text-center py-12">
                <p class="text-gray-600">Chưa có assistant nào.</p>
            </div>

            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div
                    v-for="assistant in assistants"
                    :key="assistant.id"
                    class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow cursor-pointer"
                    @click="startChat(assistant.id)"
                >
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center text-white text-xl">
                            🤖
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">{{ assistant.name }}</h3>
                            <span
                                :class="[
                                    'text-xs px-2 py-1 rounded',
                                    assistant.assistant_type === 'document_drafting'
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-purple-100 text-purple-800'
                                ]"
                            >
                                {{ assistant.assistant_type === 'document_drafting' ? 'Soạn thảo văn bản' : assistant.assistant_type === 'qa_based_document' ? 'Q&A' : 'Assistant' }}
                            </span>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm">{{ assistant.description || 'Không có mô tả' }}</p>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    auth: Object,
    assistants: Array,
});

const startChat = async (assistantId) => {
    try {
        const response = await axios.post(`/api/chat/sessions/assistant/${assistantId}`);
        router.visit(`/chat/${response.data.session.id}`, {
            data: {
                assistant: response.data.session.ai_assistant,
                session: response.data.session,
            },
        });
    } catch (error) {
        console.error('Error starting chat:', error);
        alert('Không thể bắt đầu chat. Vui lòng thử lại.');
    }
};
</script>

