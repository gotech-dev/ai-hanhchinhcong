<template>
    <AppLayout :auth="auth">
        <div class="max-w-4xl mx-auto px-4 py-8">
            <div class="bg-white rounded-lg shadow-lg h-[calc(100vh-12rem)] flex flex-col">
                <!-- Chat Header -->
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-xl font-semibold text-gray-900">
                        {{ assistant?.name || 'Chat với AI' }}
                    </h2>
                    <p class="text-sm text-gray-600">
                        {{ assistant?.description || 'Trợ lý AI thông minh' }}
                    </p>
                </div>

                <!-- Messages Container -->
                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4" ref="messagesContainer">
                    <div
                        v-for="message in messages"
                        :key="message.id"
                        :class="[
                            'flex',
                            message.sender === 'user' ? 'justify-end' : 'justify-start'
                        ]"
                    >
                        <div
                            :class="[
                                'max-w-[80%] rounded-lg px-4 py-2',
                                message.sender === 'user'
                                    ? 'bg-blue-500 text-white'
                                    : 'bg-gray-100 text-gray-900'
                            ]"
                        >
                            <div class="markdown-content" v-html="renderMarkdown(message.content)"></div>
                            <div
                                :class="[
                                    'text-xs mt-1',
                                    message.sender === 'user' ? 'text-blue-100' : 'text-gray-500'
                                ]"
                            >
                                {{ formatTime(message.created_at) }}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loading indicator -->
                    <div v-if="isLoading" class="flex justify-start">
                        <div class="bg-gray-100 rounded-lg px-4 py-2">
                            <div class="flex space-x-1">
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Smart Suggestions (when no messages) -->
                <div v-if="messages.length === 0" class="px-6 py-4 border-t border-gray-200">
                    <p class="text-sm text-gray-600 mb-3">Bắt đầu với một trong những gợi ý:</p>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="(suggestion, index) in suggestions"
                            :key="index"
                            @click="useSuggestion(suggestion)"
                            class="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
                        >
                            💬 {{ suggestion }}
                        </button>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="border-t border-gray-200 px-6 py-4">
                    <form @submit.prevent="sendMessage" class="flex space-x-4">
                        <input
                            v-model="messageInput"
                            type="text"
                            placeholder="Nhập tin nhắn..."
                            class="flex-1 rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            :disabled="isLoading"
                        />
                        <button
                            type="submit"
                            :disabled="!messageInput.trim() || isLoading"
                            class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span v-if="!isLoading">Gửi</span>
                            <span v-else class="flex items-center">
                                <svg class="animate-spin h-4 w-4 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Đang xử lý...
                            </span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted, nextTick, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useChatStream } from '../../composables/useChatStream.js';
import axios from 'axios';
import { marked } from 'marked';

const props = defineProps({
    auth: Object,
    assistant: Object,
    session: Object,
    messages: Array,
});

const messageInput = ref('');
const isLoading = ref(false);
const messagesContainer = ref(null);
const messages = ref(props.messages || []);
const useStreaming = ref(true); // Toggle streaming mode
const { streamResponse } = useChatStream();

// Smart suggestions based on assistant type
const suggestions = ref([
    props.assistant?.assistant_type === 'document_drafting'
        ? 'Tôi cần tạo quyết định bổ nhiệm'
        : props.assistant?.assistant_type === 'qa_based_document'
        ? 'Thủ tục xin giấy phép kinh doanh như thế nào?'
        : 'Tôi cần hỗ trợ',
    props.assistant?.assistant_type === 'document_drafting'
        ? 'Tạo công văn gửi Sở Tài chính'
        : props.assistant?.assistant_type === 'qa_based_document'
        ? 'Cần những giấy tờ gì để đăng ký doanh nghiệp?'
        : 'Tôi cần hỗ trợ',
    props.assistant?.assistant_type === 'document_drafting'
        ? 'Soạn thảo tờ trình xin phê duyệt'
        : 'Quy trình xử lý hồ sơ hành chính',
]);

const formatTime = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
};

const renderMarkdown = (content) => {
    if (!content) return '';
    try {
        // Configure marked options - use marked.use() for proper configuration
        marked.use({
            breaks: true,
            gfm: true,
            headerIds: false,
            mangle: false,
        });
        
        // Parse markdown to HTML
        const html = marked.parse(content);
        
        return html;
    } catch (e) {
        console.error('Error rendering markdown:', e);
        // Fallback: escape HTML and preserve line breaks
        return content
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/\n/g, '<br>');
    }
};

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
};

const sendMessage = async (messageText = null) => {
    const userMessage = messageText || messageInput.value.trim();
    if (!userMessage || !props.session?.id) return;
    
    messageInput.value = '';
    isLoading.value = true;
    
    // Add user message to UI
    messages.value.push({
        id: Date.now(),
        sender: 'user',
        content: userMessage,
        created_at: new Date().toISOString(),
    });
    
    scrollToBottom();
    
    // Create placeholder for assistant response
    const assistantMessageId = Date.now() + 1;
    let assistantMessage = {
        id: assistantMessageId,
        sender: 'assistant',
        content: '',
        created_at: new Date().toISOString(),
    };
    messages.value.push(assistantMessage);
    
    if (useStreaming.value) {
        // Use streaming mode
        let fullContent = '';
        
        streamResponse(
            props.session.id,
            userMessage,
            (chunk) => {
                // ✅ FIX STREAMING: Xử lý loading message và clear signal
                if (chunk === '__CLEAR_LOADING__') {
                    // Clear loading message - xóa các loading message đã biết
                    fullContent = fullContent
                        .replace(/Đang xử lý yêu cầu của bạn\.\.\.\n\n/g, '')
                        .replace(/Đang soạn thảo văn bản\.\.\.\n\n/g, '');
                    assistantMessage.content = fullContent;
                    scrollToBottom();
                    return;
                }
                
                // Xử lý loading message với prefix
                if (chunk.startsWith('__LOADING__')) {
                    // Remove prefix và thêm vào content
                    const displayChunk = chunk.replace('__LOADING__', '');
                    // Nếu đã có loading message, thay thế nó
                    if (fullContent.includes('Đang xử lý') || fullContent.includes('Đang soạn thảo')) {
                        fullContent = fullContent
                            .replace(/Đang xử lý yêu cầu của bạn\.\.\.\n\n/g, '')
                            .replace(/Đang soạn thảo văn bản\.\.\.\n\n/g, '');
                    }
                    fullContent += displayChunk;
                    assistantMessage.content = fullContent;
                    scrollToBottom();
                    return;
                }
                
                // Append chunk to assistant message
                // Nếu đây là content thực sự, xóa loading message nếu có
                if (fullContent.includes('Đang xử lý') || fullContent.includes('Đang soạn thảo')) {
                    fullContent = fullContent
                        .replace(/Đang xử lý yêu cầu của bạn\.\.\.\n\n/g, '')
                        .replace(/Đang soạn thảo văn bản\.\.\.\n\n/g, '');
                }
                fullContent += chunk;
                assistantMessage.content = fullContent;
                // ✅ FIX: Force Vue reactivity để đảm bảo UI update ngay lập tức
                messages.value = [...messages.value];
                scrollToBottom();
            },
            async () => {
                // Stream complete - message already saved by streamChat endpoint
                isLoading.value = false;
                scrollToBottom();
            },
            (error) => {
                // Error handling
                isLoading.value = false;
                assistantMessage.content = error || 'Xin lỗi, đã có lỗi xảy ra. Vui lòng thử lại sau.';
                scrollToBottom();
            }
        );
    } else {
        // Use regular mode
        try {
            const response = await axios.post(`/api/chat/sessions/${props.session.id}/message`, {
                message: userMessage,
            });
            
            // Update assistant message
            assistantMessage.content = response.data.assistant_message.content;
            assistantMessage.id = response.data.assistant_message.id;
        } catch (error) {
            console.error('Error sending message:', error);
            assistantMessage.content = 'Xin lỗi, đã có lỗi xảy ra. Vui lòng thử lại sau.';
        } finally {
            isLoading.value = false;
            scrollToBottom();
        }
    }
};

const useSuggestion = (suggestion) => {
    sendMessage(suggestion);
};

watch(() => props.messages, (newMessages) => {
    messages.value = newMessages || [];
    scrollToBottom();
}, { immediate: true });

onMounted(() => {
    scrollToBottom();
});
</script>

<style scoped>
.markdown-content :deep(h1),
.markdown-content :deep(h2),
.markdown-content :deep(h3),
.markdown-content :deep(h4),
.markdown-content :deep(h5),
.markdown-content :deep(h6) {
    font-weight: 600;
    margin-top: 0.5em;
    margin-bottom: 0.5em;
}

.markdown-content :deep(p) {
    margin-bottom: 0.75em;
    line-height: 1.6;
    white-space: pre-wrap;
}

.markdown-content :deep(ul),
.markdown-content :deep(ol) {
    margin-left: 1.5em;
    margin-bottom: 0.75em;
    padding-left: 1em;
    line-height: 1.6;
}

.markdown-content :deep(li) {
    margin-bottom: 0.5em;
    line-height: 1.6;
}

.markdown-content :deep(li p) {
    margin-bottom: 0.25em;
}

.markdown-content :deep(li + li) {
    margin-top: 0.25em;
}

.markdown-content :deep(strong) {
    font-weight: 600;
}

.markdown-content :deep(em) {
    font-style: italic;
}

.markdown-content :deep(code) {
    background-color: rgba(0, 0, 0, 0.1);
    padding: 0.125em 0.25em;
    border-radius: 0.25em;
    font-family: monospace;
    font-size: 0.9em;
}

.markdown-content :deep(pre) {
    background-color: rgba(0, 0, 0, 0.05);
    padding: 0.75em;
    border-radius: 0.5em;
    overflow-x: auto;
    margin-bottom: 0.5em;
}

.markdown-content :deep(pre code) {
    background-color: transparent;
    padding: 0;
}

.markdown-content :deep(a) {
    color: #2563eb;
    text-decoration: underline;
}

.markdown-content :deep(a:hover) {
    color: #1d4ed8;
}

.markdown-content :deep(h1 + p),
.markdown-content :deep(h2 + p),
.markdown-content :deep(h3 + p),
.markdown-content :deep(h4 + p),
.markdown-content :deep(h5 + p),
.markdown-content :deep(h6 + p) {
    margin-top: 0.5em;
}

/* Ensure proper spacing for numbered lists */
.markdown-content :deep(ol) {
    list-style-type: decimal;
}

.markdown-content :deep(ul) {
    list-style-type: disc;
}

/* Fix spacing for list items with sub-content */
.markdown-content :deep(li > p) {
    display: inline;
    margin: 0;
}

.markdown-content :deep(li > p:first-child) {
    margin-right: 0.5em;
}
</style>

