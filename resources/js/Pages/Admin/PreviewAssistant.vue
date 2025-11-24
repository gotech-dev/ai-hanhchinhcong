<template>
    <AdminLayout :auth="auth">
        <div class="max-w-7xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-2xl font-semibold text-gray-900">{{ assistant.name }}</h2>
                    <p class="text-gray-600 mt-1">{{ assistant.description || 'Không có mô tả' }}</p>
                </div>
                <div class="flex space-x-2">
                    <Link
                        :href="`/admin/assistants`"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                    >
                        Quay lại
                    </Link>
                    <button
                        @click="publishAssistant"
                        :disabled="assistant.is_active"
                        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        {{ assistant.is_active ? 'Đã xuất bản' : 'Xuất bản' }}
                    </button>
                    <button
                        @click="showInfo = !showInfo"
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200"
                    >
                        {{ showInfo ? 'Ẩn thông tin' : 'Xem thông tin' }}
                    </button>
                </div>
            </div>

            <!-- Loading State -->
            <div v-if="loading" class="text-center py-12">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                <p class="text-gray-600 mt-2">Đang tải...</p>
            </div>

            <!-- Content -->
            <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left: Assistant Info & Config (Collapsible) -->
                <div v-if="showInfo" class="lg:col-span-1 space-y-6">
                    <!-- Assistant Info -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Thông tin Assistant</h3>
                        <div class="space-y-3">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Loại Assistant</label>
                                <p class="text-gray-900">
                                    {{ assistant.assistant_type === 'document_drafting' ? 'Soạn thảo Văn bản Hành chính' : assistant.assistant_type === 'qa_based_document' ? 'Trả lời Q&A từ tài liệu' : 'Assistant' }}
                                </p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Trạng thái</label>
                                <span
                                    :class="[
                                        'text-xs px-2 py-1 rounded',
                                        assistant.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                                    ]"
                                >
                                    {{ assistant.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                            <div v-if="assistant.template_file_path">
                                <label class="text-sm font-medium text-gray-700">Template File</label>
                                <p class="text-gray-900">
                                    <a :href="assistant.template_file_path" target="_blank" class="text-blue-600 hover:underline">
                                        Xem template
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Workflow Config -->
                    <div v-if="assistant.config?.workflow_config" class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Workflow Config</h3>
                        <div class="space-y-2">
                            <div>
                                <label class="text-sm font-medium text-gray-700">Loại Workflow</label>
                                <p class="text-gray-900">{{ assistant.config.workflow_config.workflow_type }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-700">Tổng số Steps</label>
                                <p class="text-gray-900">{{ assistant.config.workflow_config.total_steps }}</p>
                            </div>
                            <div v-if="assistant.config.workflow_config.steps">
                                <label class="text-sm font-medium text-gray-700 mb-2 block">Các Steps</label>
                                <div class="space-y-2">
                                    <div
                                        v-for="(step, index) in assistant.config.workflow_config.steps"
                                        :key="index"
                                        class="bg-gray-50 p-3 rounded"
                                    >
                                        <div class="flex items-center justify-between">
                                            <span class="text-sm font-medium text-gray-900">
                                                Step {{ step.step_id }}: {{ step.type }}
                                            </span>
                                        </div>
                                        <p v-if="step.question" class="text-sm text-gray-600 mt-1">{{ step.question }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Documents (for Q&A) -->
                    <div v-if="assistant.assistant_type === 'qa_based_document' && documents && documents.length > 0" class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Documents ({{ documents ? documents.length : 0 }})</h3>
                        <div class="space-y-2">
                            <div
                                v-for="document in documents"
                                :key="document.id"
                                class="flex items-center justify-between bg-gray-50 p-3 rounded"
                            >
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ document.file_name }}</p>
                                    <p class="text-xs text-gray-600">
                                        {{ document.chunks_count || 0 }} chunks
                                        <span v-if="document.status" class="ml-2">
                                            <span
                                                :class="[
                                                    'text-xs px-2 py-1 rounded',
                                                    document.status === 'indexed' ? 'bg-green-100 text-green-800' :
                                                    document.status === 'processing' ? 'bg-yellow-100 text-yellow-800' :
                                                    document.status === 'error' ? 'bg-red-100 text-red-800' :
                                                    'bg-gray-100 text-gray-800'
                                                ]"
                                            >
                                                {{ document.status }}
                                            </span>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ✅ Task 4.3: Document Templates with Placeholders (for document_drafting) -->
                    <div v-if="assistant.assistant_type === 'document_drafting' && documentTemplates && documentTemplates.length > 0" class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Templates ({{ documentTemplates ? documentTemplates.length : 0 }})</h3>
                        <div class="space-y-4">
                            <div
                                v-for="template in documentTemplates"
                                :key="template.id"
                                class="bg-gray-50 p-4 rounded-lg border border-gray-200"
                            >
                                <div class="flex items-start justify-between mb-2">
                                    <div class="flex-1">
                                        <p class="text-sm font-medium text-gray-900">{{ template.name }}</p>
                                        <p class="text-xs text-gray-600 mt-1">{{ template.file_name }}</p>
                                    </div>
                                    <!-- Auto-generated indicator -->
                                    <span
                                        v-if="template.metadata && template.metadata.placeholders_auto_generated"
                                        class="ml-2 text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded"
                                        title="Placeholders được tự động tạo"
                                    >
                                        🤖 Auto
                                    </span>
                                </div>
                                
                                <!-- Placeholders list -->
                                <div v-if="template.metadata && template.metadata.placeholders && template.metadata.placeholders.length > 0" class="mt-3">
                                    <p class="text-xs font-medium text-gray-700 mb-2">
                                        Placeholders ({{ template.metadata.placeholders.length }}):
                                    </p>
                                    <div class="flex flex-wrap gap-1">
                                        <span
                                            v-for="(placeholder, index) in template.metadata.placeholders"
                                            :key="index"
                                            class="text-xs px-2 py-1 bg-blue-50 text-blue-700 rounded border border-blue-200 font-mono"
                                        >
                                            {{ placeholder }}
                                        </span>
                                    </div>
                                </div>
                                <div v-else class="mt-3">
                                    <p class="text-xs text-gray-500 italic">Không có placeholders</p>
                                </div>
                                
                                <!-- ✅ MỚI: Edit HTML Preview Button -->
                                <div class="mt-3 flex gap-2">
                                    <button
                                        @click="openEditHtmlModal(template)"
                                        class="px-3 py-1.5 text-xs bg-green-500 text-white rounded hover:bg-green-600 transition-colors flex items-center gap-1"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                        Edit HTML
                                    </button>
                                    <button
                                        @click="previewTemplateHtml(template)"
                                        class="px-3 py-1.5 text-xs bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors flex items-center gap-1"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                        Preview
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- ✅ MỚI: Edit HTML Modal -->
                <div
                    v-if="showEditHtmlModal"
                    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
                    @click.self="closeEditHtmlModal"
                >
                    <div class="bg-white rounded-lg shadow-xl w-full max-w-6xl max-h-[90vh] flex flex-col">
                        <!-- Modal Header -->
                        <div class="flex items-center justify-between p-4 border-b border-gray-200">
                            <h3 class="text-lg font-semibold text-gray-900">
                                Edit HTML Preview - {{ editingTemplate?.name }}
                            </h3>
                            <button
                                @click="closeEditHtmlModal"
                                class="text-gray-400 hover:text-gray-600 transition-colors"
                            >
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Modal Content -->
                        <div class="flex-1 overflow-hidden flex">
                            <!-- Left: HTML Editor -->
                            <div class="flex-1 flex flex-col border-r border-gray-200">
                                <div class="p-2 bg-gray-50 border-b border-gray-200">
                                    <span class="text-sm font-medium text-gray-700">HTML Code</span>
                                </div>
                                <textarea
                                    v-model="editingHtml"
                                    class="flex-1 p-4 font-mono text-sm border-0 resize-none focus:outline-none focus:ring-0"
                                    placeholder="HTML content will appear here..."
                                    spellcheck="false"
                                ></textarea>
                            </div>
                            
                            <!-- Right: Preview -->
                            <div class="flex-1 flex flex-col">
                                <div class="p-2 bg-gray-50 border-b border-gray-200">
                                    <span class="text-sm font-medium text-gray-700">Preview</span>
                                </div>
                                <div class="flex-1 overflow-auto p-4 bg-gray-50">
                                    <div
                                        v-if="editingHtml"
                                        class="bg-white p-4 rounded shadow-sm min-h-full"
                                        v-html="editingHtml"
                                    ></div>
                                    <div v-else class="text-gray-400 text-center py-12">
                                        HTML preview will appear here...
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Modal Footer -->
                        <div class="flex items-center justify-between p-4 border-t border-gray-200 bg-gray-50">
                            <div class="text-sm text-gray-600">
                                <span v-if="editingHtml">Length: {{ editingHtml.length }} characters</span>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    @click="closeEditHtmlModal"
                                    class="px-4 py-2 text-sm bg-gray-100 text-gray-700 rounded hover:bg-gray-200 transition-colors"
                                >
                                    Cancel
                                </button>
                                <button
                                    @click="saveHtmlPreview"
                                    :disabled="!editingHtml || isSavingHtml"
                                    class="px-4 py-2 text-sm bg-blue-500 text-white rounded hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors flex items-center gap-2"
                                >
                                    <span v-if="isSavingHtml" class="inline-block animate-spin">⏳</span>
                                    <span v-else>💾</span>
                                    {{ isSavingHtml ? 'Saving...' : 'Save HTML' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Full Chat Interface (like user interface) -->
                <div :class="['bg-white rounded-lg shadow-lg flex flex-col', showInfo ? 'lg:col-span-2' : 'lg:col-span-3']" style="height: calc(100vh - 12rem);">
                    <!-- Chat Header -->
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-xl font-semibold text-gray-900">
                            {{ assistant.name }}
                        </h3>
                        <p class="text-sm text-gray-600">
                            {{ assistant.description || 'Trợ lý AI thông minh' }}
                        </p>
                    </div>

                    <!-- Messages Container -->
                    <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4" ref="messagesContainer">
                        <div v-if="!testMessages || testMessages.length === 0" class="text-center text-gray-500 py-12">
                            <p class="mb-4">Chưa có tin nhắn nào. Bắt đầu chat với trợ lý!</p>
                            <!-- Smart Suggestions -->
                            <div v-if="suggestions && suggestions.length > 0" class="flex flex-wrap gap-2 justify-center">
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
                        <div
                            v-for="message in testMessages"
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

                    <!-- Input Area -->
                    <div class="border-t border-gray-200 px-6 py-4">
                        <form @submit.prevent="sendTestMessage" class="flex space-x-4">
                            <input
                                v-model="testMessageInput"
                                type="text"
                                placeholder="Nhập tin nhắn..."
                                class="flex-1 rounded-lg border border-gray-300 px-4 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                :disabled="isLoading"
                            />
                            <button
                                type="submit"
                                :disabled="!testMessageInput.trim() || isLoading"
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
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, onMounted, nextTick, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../Layouts/AdminLayout.vue';
import { useChatStream } from '../../composables/useChatStream.js';
import { marked } from 'marked';

const props = defineProps({
    auth: Object,
    assistant: {
        type: Object,
        required: true,
    },
    documents: {
        type: Array,
        default: () => [],
    },
    documentTemplates: {
        type: Array,
        default: () => [],
    },
});

const loading = ref(false);
const showInfo = ref(true);
const testMessages = ref([]);
const testMessageInput = ref('');
const isLoading = ref(false);
const messagesContainer = ref(null);
const testSessionId = ref(null);
const useStreaming = ref(true); // Enable streaming by default
const { streamResponse } = useChatStream();

// ✅ MỚI: HTML Editing state
const showEditHtmlModal = ref(false);
const editingTemplate = ref(null);
const editingHtml = ref('');
const isSavingHtml = ref(false);

// Smart suggestions based on assistant type
const getSuggestions = () => {
    if (!props.assistant || !props.assistant.assistant_type) {
        return [];
    }
    return [
        props.assistant.assistant_type === 'document_drafting'
            ? 'Tôi cần tạo quyết định bổ nhiệm'
            : props.assistant.assistant_type === 'qa_based_document'
            ? 'Thủ tục xin giấy phép kinh doanh như thế nào?'
            : 'Tôi cần hỗ trợ',
        props.assistant.assistant_type === 'document_drafting'
            ? 'Tạo công văn gửi Sở Tài chính'
            : props.assistant.assistant_type === 'qa_based_document'
            ? 'Cần những giấy tờ gì để đăng ký doanh nghiệp?'
            : 'Tôi cần hỗ trợ',
        props.assistant.assistant_type === 'document_drafting'
            ? 'Soạn thảo tờ trình xin phê duyệt'
            : 'Quy trình xử lý hồ sơ hành chính',
    ];
};
const suggestions = ref(getSuggestions());

const formatTime = (dateString) => {
    if (!dateString) return '';
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

const publishAssistant = async () => {
    if (!confirm('Bạn có chắc chắn muốn xuất bản assistant này?')) return;
    
    try {
        await window.axios.put(`/api/assistants/${props.assistant.id}`, {
            is_active: true,
        });
        router.reload();
    } catch (error) {
        console.error('Error publishing assistant:', error);
        alert('Không thể xuất bản assistant. Vui lòng thử lại.');
    }
};

const useSuggestion = (suggestion) => {
    testMessageInput.value = suggestion;
    sendTestMessage(suggestion);
};

const sendTestMessage = async (messageText = null) => {
    const message = messageText || testMessageInput.value.trim();
    if (!message || isLoading.value) return;
    
    // Add user message
    testMessages.value.push({
        id: Date.now(),
        sender: 'user',
        content: message,
        created_at: new Date().toISOString(),
    });
    
    testMessageInput.value = '';
    isLoading.value = true;
    scrollToBottom();
    
    // Create placeholder for assistant response
    const assistantMessageId = Date.now() + 1;
    let assistantMessage = {
        id: assistantMessageId,
        sender: 'assistant',
        content: '',
        created_at: new Date().toISOString(),
    };
    testMessages.value.push(assistantMessage);
    
    try {
        // Create or get test session if not exists
        if (!testSessionId.value) {
            const sessionResponse = await window.axios.post(`/api/chat/sessions/assistant/${props.assistant.id}`);
            testSessionId.value = sessionResponse.data.session.id;
        }
        
        if (useStreaming.value) {
            // Use streaming mode
            let fullContent = '';
            
            streamResponse(
                testSessionId.value,
                message,
                (chunk) => {
                    // Append chunk to assistant message
                    fullContent += chunk;
                    assistantMessage.content = fullContent;
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
            const response = await window.axios.post(`/api/chat/sessions/${testSessionId.value}/message`, {
                message: message,
            });
            
            // Update assistant message
            assistantMessage.content = response.data.assistant_message.content;
            assistantMessage.id = response.data.assistant_message.id;
            isLoading.value = false;
            scrollToBottom();
        }
    } catch (error) {
        console.error('Error sending test message:', error);
        assistantMessage.content = 'Xin lỗi, đã có lỗi xảy ra. Vui lòng thử lại sau.';
        isLoading.value = false;
        scrollToBottom();
    }
};

onMounted(async () => {
    // Load greeting message from session if exists
    try {
        const sessionResponse = await window.axios.post(`/api/chat/sessions/assistant/${props.assistant.id}`);
        if (sessionResponse.data.session && sessionResponse.data.session.messages) {
            const sessionMessages = sessionResponse.data.session.messages;
            // Only add greeting if no messages exist yet
            if (sessionMessages.length > 0 && testMessages.value.length === 0) {
                // Sort messages by created_at to ensure greeting is first
                const sortedMessages = sessionMessages.sort((a, b) => 
                    new Date(a.created_at) - new Date(b.created_at)
                );
                
                // Map all messages to display format
                testMessages.value = sortedMessages.map(msg => ({
                    id: msg.id,
                    sender: msg.sender,
                    content: msg.content,
                    created_at: msg.created_at,
                }));
                testSessionId.value = sessionResponse.data.session.id;
            }
        }
    } catch (error) {
        console.error('Error loading session messages:', error);
    }
    scrollToBottom();
});

// ✅ MỚI: HTML Editing methods
const openEditHtmlModal = async (template) => {
    editingTemplate.value = template;
    showEditHtmlModal.value = true;
    
    // Load HTML preview from API or metadata
    try {
        if (template.metadata?.html_preview) {
            // Use HTML from metadata if available
            editingHtml.value = template.metadata.html_preview;
        } else {
            // Try to load from API
            const response = await window.axios.get(`/api/templates/${template.id}/preview-html`, {
                responseType: 'text'
            });
            // response.data is already a string when responseType is 'text'
            editingHtml.value = response.data;
        }
    } catch (error) {
        console.error('Error loading HTML preview:', error);
        if (error.response?.status === 404) {
            alert('HTML preview chưa được tạo. Vui lòng upload lại template để tự động generate HTML.');
        } else {
            alert('Không thể load HTML preview. Vui lòng thử lại.');
        }
        editingHtml.value = '';
    }
};

const closeEditHtmlModal = () => {
    showEditHtmlModal.value = false;
    editingTemplate.value = null;
    editingHtml.value = '';
};

const previewTemplateHtml = async (template) => {
    try {
        const response = await window.axios.get(`/api/templates/${template.id}/preview-html`, {
            responseType: 'text'
        });
        
        // Open preview in new window
        const newWindow = window.open('', '_blank');
        // response.data is already a string when responseType is 'text'
        newWindow.document.write(response.data);
        newWindow.document.close();
    } catch (error) {
        console.error('Error previewing HTML:', error);
        if (error.response?.status === 404) {
            alert('HTML preview chưa được tạo. Vui lòng upload lại template để tự động generate HTML.');
        } else {
            alert('Không thể preview HTML. Vui lòng thử lại.');
        }
    }
};

const saveHtmlPreview = async () => {
    if (!editingTemplate.value || !editingHtml.value.trim()) {
        alert('HTML không được để trống.');
        return;
    }
    
    isSavingHtml.value = true;
    
    try {
        await window.axios.put(`/api/templates/${editingTemplate.value.id}/html-preview`, {
            html_preview: editingHtml.value
        });
        
        alert('HTML preview đã được lưu thành công!');
        closeEditHtmlModal();
        
        // Reload page to reflect changes
        router.reload();
    } catch (error) {
        console.error('Error saving HTML preview:', error);
        alert('Không thể lưu HTML preview. Vui lòng thử lại.');
    } finally {
        isSavingHtml.value = false;
    }
};
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
