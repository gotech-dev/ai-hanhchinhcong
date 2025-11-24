<template>
    <div class="h-screen flex bg-gray-50">
        <!-- Left Sidebar - Chat History -->
        <div class="w-64 bg-gray-900 text-white flex flex-col">
            <!-- New Chat Button -->
            <div class="p-4 border-b border-gray-800">
                <button
                    @click="showNewChatModal = true"
                    class="w-full px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg text-sm font-medium flex items-center justify-center gap-2"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Chat mới
                </button>
            </div>

            <!-- Chat History List -->
            <div class="flex-1 overflow-y-auto">
                <div v-if="chatSessions.length === 0" class="p-4 text-center text-gray-400 text-sm">
                    Chưa có cuộc trò chuyện nào
                </div>
                <div v-else class="py-2">
                    <div
                        v-for="session in chatSessions"
                        :key="session.id"
                        @click="selectSession(session.id)"
                        :class="[
                            'px-3 py-3 cursor-pointer hover:bg-gray-800 transition-colors group',
                            currentSessionId === session.id ? 'bg-gray-800 border-l-2 border-blue-500' : ''
                        ]"
                    >
                        <div class="flex items-start gap-3">
                            <!-- Avatar/Thumbnail -->
                            <div class="flex-shrink-0 w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-sm font-semibold">
                                <span v-if="session.ai_assistant?.name">
                                    {{ session.ai_assistant.name.charAt(0).toUpperCase() }}
                                </span>
                                <span v-else>🤖</span>
                            </div>
                            
                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-1">
                                    <div class="text-sm font-medium text-white truncate">
                                        {{ session.title || session.ai_assistant?.name || 'Chat mới' }}
                                    </div>
                                    <button
                                        @click.stop.prevent="() => deleteSession(session.id)"
                                        class="opacity-0 group-hover:opacity-100 text-gray-500 hover:text-red-400 ml-2 flex-shrink-0 transition-opacity cursor-pointer"
                                        title="Xóa cuộc trò chuyện"
                                        type="button"
                                        :data-session-id="session.id"
                                    >
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="text-xs text-gray-400 truncate mb-1">
                                    {{ getLatestMessageContent(session) }}
                                </div>
                                <div class="text-xs text-gray-500">
                                    {{ formatDate(session.updated_at) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Info -->
            <div class="p-4 border-t border-gray-800">
                <div class="text-sm font-medium">{{ auth?.user?.name || auth?.user?.email }}</div>
                <div class="text-xs text-gray-400">{{ auth?.user?.email }}</div>
            </div>
        </div>

        <!-- Main Chat Area -->
        <div class="flex-1 flex flex-col">
            <!-- Chat Window -->
            <div v-if="currentSession" class="flex-1 flex flex-col bg-white">
                <!-- Chat Header -->
                <div class="border-b border-gray-200 px-6 py-4 bg-gray-50">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-gray-900">
                                {{ currentAssistant?.name || 'Chat với AI' }}
                            </h2>
                            <p class="text-sm text-gray-600">
                                {{ currentAssistant?.description || 'Trợ lý AI thông minh' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                :class="[
                                    'text-xs px-3 py-1 rounded-full',
                                    currentAssistant?.assistant_type === 'document_drafting'
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-purple-100 text-purple-800'
                                ]"
                            >
                                {{ currentAssistant?.assistant_type === 'document_drafting' ? 'Soạn thảo văn bản' : currentAssistant?.assistant_type === 'qa_based_document' ? 'Q&A' : 'Assistant' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Messages Container -->
                <div class="flex-1 overflow-y-auto px-6 py-4 space-y-4 bg-gray-50" ref="messagesContainer">
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
                                    : 'bg-white text-gray-900 border border-gray-200'
                            ]"
                        >
                            <!-- File Attachments -->
                            <div v-if="message.attachments && message.attachments.length > 0" class="mb-2 space-y-2">
                                <div
                                    v-for="(attachment, index) in message.attachments"
                                    :key="index"
                                    :class="[
                                        'flex items-center gap-2 p-2 rounded',
                                        message.sender === 'user'
                                            ? 'bg-blue-600 text-white'
                                            : 'bg-gray-100 text-gray-700'
                                    ]"
                                >
                                    <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span class="text-sm truncate flex-1">{{ attachment.name || attachment.original_name }}</span>
                                    <a
                                        v-if="attachment.url || attachment.path"
                                        :href="attachment.url || attachment.path"
                                        target="_blank"
                                        :class="[
                                            'text-xs underline',
                                            message.sender === 'user' ? 'text-blue-100' : 'text-blue-600'
                                        ]"
                                    >
                                        Mở
                                    </a>
                                </div>
                            </div>
                            
                            <!-- ✅ MỚI: Hiển thị DocumentPreview nếu có document metadata -->
                            <DocumentPreview
                                v-if="message.sender === 'assistant' && message.metadata?.document"
                                :message-id="message.id"
                                :document-data="message.metadata.document"
                                :document-content="message.content"
                                class="mt-4"
                            />
                            
                            <!-- Hiển thị message content nếu không có document preview -->
                            <div v-if="!message.metadata?.document || message.sender === 'user'" class="markdown-content" v-html="renderMarkdown(message.content)"></div>
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
                        <div class="bg-white border border-gray-200 rounded-lg px-4 py-2">
                            <div class="flex space-x-1">
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Smart Suggestions (when no messages) -->
                <div v-if="messages.length === 0 && !isLoading" class="px-6 py-4 border-t border-gray-200 bg-white">
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
                <div class="border-t border-gray-200 px-6 py-4 bg-white">
                    <!-- File Attachments Preview -->
                    <div v-if="attachedFiles.length > 0" class="mb-3 flex flex-wrap gap-2">
                        <div
                            v-for="(file, index) in attachedFiles"
                            :key="index"
                            class="flex items-center gap-2 bg-gray-100 rounded-lg px-3 py-2 text-sm"
                        >
                            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <span class="text-gray-700 truncate max-w-xs">{{ file.name }}</span>
                            <button
                                @click="removeFile(index)"
                                class="text-gray-500 hover:text-red-500 ml-1"
                                title="Xóa file"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <form @submit.prevent="sendMessage" class="flex items-end gap-2">
                        <!-- File Input (hidden) -->
                        <input
                            ref="fileInput"
                            type="file"
                            multiple
                            accept=".pdf,.doc,.docx,.txt,.jpg,.jpeg,.png,.gif,.xlsx,.xls"
                            @change="handleFileSelect"
                            class="hidden"
                        />
                        
                        <!-- Attach File Button -->
                        <button
                            type="button"
                            @click="$refs.fileInput.click()"
                            :disabled="isLoading || !currentSession"
                            class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            title="Đính kèm file"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.414a2 2 0 00-2.828-2.828l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.414a2 2 0 00-2.828-2.828L9 10.172 7.586 8.757a2 2 0 112.828-2.828L15.172 7z" />
                            </svg>
                        </button>
                        
                        <div class="flex-1 rounded-lg border border-gray-300 bg-white focus-within:ring-2 focus-within:ring-blue-500 focus-within:border-blue-500">
                            <input
                                v-model="messageInput"
                                type="text"
                                placeholder="Nhập tin nhắn..."
                                class="w-full px-4 py-2 focus:outline-none"
                                :disabled="isLoading || !currentSession"
                            />
                        </div>
                        
                        <button
                            type="submit"
                            :disabled="(!messageInput.trim() && attachedFiles.length === 0) || isLoading || !currentSession"
                            class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
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

            <!-- Empty State - No Session Selected -->
            <div v-else class="flex-1 flex items-center justify-center bg-gray-50">
                <div class="text-center">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                    </svg>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Chọn một cuộc trò chuyện</h3>
                    <p class="text-gray-600 mb-4">Chọn một cuộc trò chuyện từ danh sách bên trái hoặc tạo chat mới</p>
                    <button
                        @click="showNewChatModal = true"
                        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600"
                    >
                        Tạo chat mới
                    </button>
                </div>
            </div>
        </div>

        <!-- New Chat Modal -->
        <div v-if="showNewChatModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" @click.self="showNewChatModal = false">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">Chọn trợ lý AI</h3>
                
                <div v-if="assistantsLoading" class="text-center py-8">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div>
                </div>

                <div v-else-if="assistants.length === 0" class="text-center py-8 text-gray-600">
                    Chưa có assistant nào
                </div>

                <div v-else>
                    <!-- Search Box -->
                    <input
                        v-model="assistantSearchQuery"
                        type="text"
                        placeholder="🔍 Tìm kiếm trợ lý..."
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg mb-3 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    />
                    
                    <!-- Results count -->
                    <div v-if="assistantSearchQuery" class="text-sm text-gray-600 mb-2">
                        Tìm thấy {{ filteredAssistants.length }} trợ lý
                    </div>
                    
                    <!-- Assistants List -->
                    <div class="space-y-2 max-h-96 overflow-y-auto">
                    <div
                        v-for="assistant in filteredAssistants"
                        :key="assistant.id"
                        @click="startNewChat(assistant.id)"
                        class="p-4 border border-gray-200 rounded-lg hover:border-blue-500 hover:bg-blue-50 cursor-pointer transition-colors"
                    >
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white text-xl flex-shrink-0">
                                🤖
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="font-semibold text-gray-900">{{ assistant.name }}</div>
                                <div class="text-sm text-gray-600 truncate">{{ assistant.description || 'Không có mô tả' }}</div>
                                <div class="flex gap-2 mt-1">
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
                        </div>
                    </div>
                    
                    <!-- No results -->
                    <div v-if="filteredAssistants.length === 0" class="text-center py-8 text-gray-500">
                        Không tìm thấy trợ lý phù hợp
                    </div>
                    </div>
                </div>

                <div class="mt-4 flex justify-end">
                    <button
                        @click="showNewChatModal = false"
                        class="px-4 py-2 text-gray-700 hover:bg-gray-100 rounded-lg"
                    >
                        Hủy
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { useChatStream } from '../../composables/useChatStream.js';
import axios from 'axios';
import { marked } from 'marked';
import DocumentPreview from '../../Components/DocumentPreview.vue';

const props = defineProps({
    auth: Object,
    assistants: Array,
    sessions: Array,
    currentSession: Object,
});

const showNewChatModal = ref(false);
const assistants = ref(props.assistants || []);
const assistantsLoading = ref(false);
const chatSessions = ref(props.sessions || []);
const currentSession = ref(props.currentSession || null);
const currentSessionId = ref(null);
const currentAssistant = ref(null);
const messages = ref([]);
const messageInput = ref('');
const isLoading = ref(false);
const messagesContainer = ref(null);
const useStreaming = ref(true);
const { streamResponse } = useChatStream();
const attachedFiles = ref([]);
const fileInput = ref(null);
const assistantSearchQuery = ref('');

if (currentSession.value) {
    currentSessionId.value = currentSession.value.id;
    currentAssistant.value = currentSession.value.ai_assistant;
    messages.value = currentSession.value.messages || [];
}

const filteredAssistants = computed(() => {
    if (!assistantSearchQuery.value) return assistants.value;
    
    const query = assistantSearchQuery.value.toLowerCase();
    return assistants.value.filter(a => 
        a.name.toLowerCase().includes(query) ||
        (a.description && a.description.toLowerCase().includes(query)) ||
        (a.assistant_type && a.assistant_type.toLowerCase().includes(query))
    );
});

const getLatestMessageContent = (session) => {
    if (!session) return 'Chưa có tin nhắn';
    
    // Handle different possible structures
    const latestMessage = session.latest_message || session.latestMessage;
    
    if (Array.isArray(latestMessage)) {
        return latestMessage[0]?.content || 'Chưa có tin nhắn';
    }
    
    return latestMessage?.content || 'Chưa có tin nhắn';
};

const formatDate = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 1) return 'Hôm nay';
    if (diffDays === 2) return 'Hôm qua';
    if (diffDays <= 7) return `${diffDays - 1} ngày trước`;
    
    return date.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
};

const formatTime = (dateString) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
};

const renderMarkdown = (content) => {
    if (!content) return '';
    try {
        marked.use({
            breaks: true,
            gfm: true,
            headerIds: false,
            mangle: false,
        });
        
        const html = marked.parse(content);
        return html;
    } catch (e) {
        console.error('Error rendering markdown:', e);
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

const loadAssistants = async () => {
    assistantsLoading.value = true;
    try {
        // Load all assistants for better UX in modal (with backend safety limit)
        const response = await axios.get('/api/assistants?all=true');
        
        // Handle both array (all) and paginated response
        if (Array.isArray(response.data.assistants)) {
            assistants.value = response.data.assistants;
        } else {
            assistants.value = response.data.assistants.data || response.data.assistants || [];
        }
        
        // Log strategy used
        if (response.data.strategy === 'paginated') {
            console.warn('[loadAssistants] Too many assistants, using pagination');
        }
    } catch (error) {
        console.error('Error loading assistants:', error);
    } finally {
        assistantsLoading.value = false;
    }
};

const loadChatSessions = async () => {
    try {
        console.log('[loadChatSessions] Loading sessions...');
        const response = await axios.get('/api/chat/sessions');
        const sessions = response.data.sessions.data || response.data.sessions || [];
        console.log('[loadChatSessions] Loaded sessions:', sessions.map(s => ({ id: s.id, title: s.title })));
        chatSessions.value = sessions;
        console.log('[loadChatSessions] Updated chatSessions, count:', chatSessions.value.length);
    } catch (error) {
        console.error('[loadChatSessions] Error:', error);
    }
};

const selectSession = async (sessionId) => {
    if (currentSessionId.value === sessionId) return;
    
    try {
        router.visit(`/chat/${sessionId}`, {
            preserveState: true,
            preserveScroll: true,
        });
    } catch (error) {
        console.error('Error selecting session:', error);
    }
};

const startNewChat = async (assistantId) => {
    showNewChatModal.value = false;
    
    try {
        const response = await axios.post(`/api/chat/sessions/assistant/${assistantId}`);
        const session = response.data.session;
        
        router.visit(`/chat/${session.id}`, {
            preserveState: true,
        });
    } catch (error) {
        console.error('Error starting chat:', error);
        alert('Không thể bắt đầu chat. Vui lòng thử lại.');
    }
};

const deleteSession = async (sessionId) => {
    console.log('[deleteSession] Called with sessionId:', sessionId);
    console.log('[deleteSession] Current chatSessions:', chatSessions.value.map(s => ({ id: s.id, title: s.title })));
    
    if (!sessionId) {
        console.error('[deleteSession] No sessionId provided');
        return;
    }
    
    // Verify session exists in list
    const sessionExists = chatSessions.value.some(s => s.id === sessionId);
    if (!sessionExists) {
        console.warn('[deleteSession] Session not found in list:', sessionId);
        // Still try to delete from server
    }
    
    if (!confirm('Bạn có chắc chắn muốn xóa cuộc trò chuyện này?')) {
        return;
    }
    
    // Store original list in case of error
    const originalSessions = [...chatSessions.value];
    
    try {
        console.log('[deleteSession] Starting deletion for session:', sessionId);
        
        // Delete from server first
        const response = await axios.delete(`/api/chat/sessions/${sessionId}`);
        console.log('[deleteSession] Server response:', response.data);
        
        // Verify deletion was successful
        if (response.status === 200 || response.data?.deleted !== false) {
            // Remove from UI after successful server deletion
            const sessionIndex = chatSessions.value.findIndex(s => s.id === sessionId);
            if (sessionIndex !== -1) {
                chatSessions.value.splice(sessionIndex, 1);
                console.log('[deleteSession] Removed from UI, new count:', chatSessions.value.length);
            } else {
                console.warn('[deleteSession] Session not found in UI list, reloading...');
                await loadChatSessions();
            }
            
            // Clear current session if it's the one being deleted
            if (currentSessionId.value === sessionId) {
                currentSession.value = null;
                currentSessionId.value = null;
                currentAssistant.value = null;
                messages.value = [];
                messageInput.value = '';
            }
            
            // Reload to ensure consistency (but only if we didn't already remove it)
            if (sessionIndex === -1) {
                await loadChatSessions();
            }
            
            console.log('[deleteSession] Session deleted successfully');
        } else {
            throw new Error('Delete operation returned unsuccessful');
        }
    } catch (error) {
        console.error('[deleteSession] Error:', error);
        console.error('[deleteSession] Error response:', error.response?.data);
        
        // Restore original list on error
        chatSessions.value = originalSessions;
        
        // Reload to ensure we have the correct state
        await loadChatSessions();
        
        const errorMessage = error.response?.data?.message 
            || error.response?.data?.error 
            || error.message 
            || 'Không thể xóa cuộc trò chuyện. Vui lòng thử lại.';
        alert(errorMessage);
    }
};

const handleFileSelect = (event) => {
    const files = Array.from(event.target.files);
    files.forEach(file => {
        // Validate file size (max 10MB)
        if (file.size > 10 * 1024 * 1024) {
            alert(`File "${file.name}" quá lớn. Kích thước tối đa là 10MB.`);
            return;
        }
        
        attachedFiles.value.push(file);
    });
    
    // Reset input
    event.target.value = '';
};

const removeFile = (index) => {
    attachedFiles.value.splice(index, 1);
};

const sendMessage = async (messageText = null) => {
    const userMessage = messageText || messageInput.value.trim();
    
    // Require either message or files
    if (!userMessage && attachedFiles.value.length === 0) return;
    if (!currentSession.value?.id) return;
    
    messageInput.value = '';
    isLoading.value = true;
    
    // Upload files first if any
    let uploadedFiles = [];
    if (attachedFiles.value.length > 0) {
        try {
            const formData = new FormData();
            attachedFiles.value.forEach(file => {
                formData.append('files[]', file);
            });
            
            const uploadResponse = await axios.post(
                `/api/chat/sessions/${currentSession.value.id}/upload`,
                formData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                }
            );
            
            uploadedFiles = uploadResponse.data.files || [];
        } catch (error) {
            console.error('Error uploading files:', error);
            alert('Không thể upload file. Vui lòng thử lại.');
            isLoading.value = false;
            return;
        }
    }
    
    // Build message content
    let messageContent = userMessage;
    if (uploadedFiles.length > 0) {
        const fileNames = uploadedFiles.map(f => f.name).join(', ');
        messageContent = messageContent 
            ? `${messageContent}\n\n[Đã đính kèm: ${fileNames}]`
            : `[Đã đính kèm: ${fileNames}]`;
    }
    
    messages.value.push({
        id: Date.now(),
        sender: 'user',
        content: messageContent,
        created_at: new Date().toISOString(),
        attachments: uploadedFiles,
    });
    
    // Clear attached files
    attachedFiles.value = [];
    
    scrollToBottom();
    
    const assistantMessageId = Date.now() + 1;
    let assistantMessage = {
        id: assistantMessageId,
        sender: 'assistant',
        content: '',
        created_at: new Date().toISOString(),
    };
    messages.value.push(assistantMessage);
    
    if (useStreaming.value) {
        let fullContent = '';
        
        // NEW: Separate callback for document data (immediate)
        const onDocumentCallback = (document, messageId) => {
            console.log('[Dashboard] onDocument callback called', {
                document,
                messageId,
                assistantMessageId: assistantMessage.id,
            });
            
            // Update message ID
            if (messageId) {
                assistantMessage.id = messageId;
            }
            
            // Set document metadata directly
            if (!assistantMessage.metadata) {
                assistantMessage.metadata = {};
            }
            
            assistantMessage.metadata.document = {
                file_path: document.file_path,
                document_type: document.document_type,
                document_type_display: document.document_type_display,
                template_used: document.template_used,
                template_id: document.template_id,
            };
            
            console.log('[Dashboard] Document metadata set', {
                messageId: assistantMessage.id,
                metadata: assistantMessage.metadata,
            });
            
            // Force reactivity
            messages.value = [...messages.value];
            scrollToBottom();
        };
        
        // Simplified onComplete callback
        const onCompleteCallback = async (data) => {
            console.log('[Dashboard] onComplete callback called', {
                hasData: !!data,
                messageId: data?.message_id,
                hasDocument: !!data?.document,
            });
            
            isLoading.value = false;
            
            // Update message ID if not already set
            if (data?.message_id && assistantMessage.id !== data.message_id) {
                assistantMessage.id = data.message_id;
            }
            
            // ✅ FIX: If document data exists but onDocumentCallback wasn't called, set it here
            if (data?.document && !assistantMessage.metadata?.document) {
                console.log('[Dashboard] Setting document metadata in onComplete (fallback)', {
                    document: data.document,
                    messageId: assistantMessage.id,
                });
                
                if (!assistantMessage.metadata) {
                    assistantMessage.metadata = {};
                }
                
                assistantMessage.metadata.document = {
                    file_path: data.document.file_path,
                    document_type: data.document.document_type,
                    document_type_display: data.document.document_type_display,
                    template_used: data.document.template_used,
                    template_id: data.document.template_id,
                };
                
                // Force reactivity
                messages.value = [...messages.value];
            }
            
            await loadChatSessions();
            
            // ✅ FIX: Update assistantMessage with metadata from database
            const updatedMessage = messages.value.find(m => m.id === assistantMessage.id);
            if (updatedMessage && updatedMessage.metadata?.document && !assistantMessage.metadata?.document) {
                console.log('[Dashboard] Updating assistantMessage with metadata from database', {
                    messageId: assistantMessage.id,
                    metadata: updatedMessage.metadata,
                });
                
                if (!assistantMessage.metadata) {
                    assistantMessage.metadata = {};
                }
                assistantMessage.metadata.document = updatedMessage.metadata.document;
                
                // Force reactivity
                messages.value = [...messages.value];
            }
            
            scrollToBottom();
        };
        
        // ✅ FIX: Ensure uploadedFiles is always an array
        const attachmentsArray = Array.isArray(uploadedFiles) ? uploadedFiles : [];
        
        // ✅ FIX: Log before streamResponse to debug
        console.log('[Dashboard] Before streamResponse', {
            hasOnDocumentCallback: !!onDocumentCallback,
            onDocumentCallbackType: typeof onDocumentCallback,
            onDocumentCallback: onDocumentCallback,
            uploadedFilesCount: attachmentsArray.length,
            useStreaming: useStreaming.value,
        });
        
        console.log('[Dashboard] Setting up streamResponse', {
            hasOnDocumentCallback: !!onDocumentCallback,
            onDocumentCallbackType: typeof onDocumentCallback,
            uploadedFilesCount: attachmentsArray.length,
        });
        
        // ✅ FIX: Log each parameter separately before calling streamResponse
        console.log('[Dashboard] Calling streamResponse - param0_sessionId:', currentSession.value.id);
        console.log('[Dashboard] Calling streamResponse - param1_message:', userMessage || null);
        console.log('[Dashboard] Calling streamResponse - param2_onChunk:', typeof ((chunk) => { fullContent += chunk; assistantMessage.content = fullContent; scrollToBottom(); }));
        console.log('[Dashboard] Calling streamResponse - param3_onComplete:', typeof onCompleteCallback);
        console.log('[Dashboard] Calling streamResponse - param4_onError:', typeof ((error) => { isLoading.value = false; assistantMessage.content = error || 'Xin lỗi, đã có lỗi xảy ra. Vui lòng thử lại sau.'; scrollToBottom(); }));
        console.log('[Dashboard] Calling streamResponse - param5_attachments:', attachmentsArray.length);
        console.log('[Dashboard] Calling streamResponse - param6_onReport:', null);
        console.log('[Dashboard] Calling streamResponse - param7_onDocument:', typeof onDocumentCallback);
        console.log('[Dashboard] Calling streamResponse - param7_onDocument isFunction:', typeof onDocumentCallback === 'function');
        console.log('[Dashboard] Calling streamResponse - param7_onDocument isNull:', onDocumentCallback === null);
        console.log('[Dashboard] Calling streamResponse - param7_onDocument isUndefined:', onDocumentCallback === undefined);
        console.log('[Dashboard] Calling streamResponse - param7_onDocumentValue:', onDocumentCallback);
        
        // ✅ FIX: Store onDocumentCallback in a variable to ensure it's passed correctly
        const documentCallback = onDocumentCallback;
        console.log('[Dashboard] documentCallback stored:', typeof documentCallback, documentCallback === null, documentCallback === undefined);
        
        streamResponse(
            currentSession.value.id,
            userMessage || null,
            // onChunk
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
            // onComplete
            onCompleteCallback,
            // onError
            (error) => {
                isLoading.value = false;
                assistantMessage.content = error || 'Xin lỗi, đã có lỗi xảy ra. Vui lòng thử lại sau.';
                scrollToBottom();
            },
            // attachments (must be array)
            attachmentsArray,
            // onReport (for report assistant)
            null,
            // onDocument (NEW - for document drafting assistant)
            documentCallback  // ✅ FIX: Use stored variable
        );
    } else {
        try {
            const response = await axios.post(`/api/chat/sessions/${currentSession.value.id}/message`, {
                message: userMessage || 'Xem file đính kèm',
                attachments: uploadedFiles,
            });
            
            assistantMessage.content = response.data.assistant_message.content;
            assistantMessage.id = response.data.assistant_message.id;
            await loadChatSessions();
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

const getSuggestions = () => {
    if (!currentAssistant.value) {
        return [
            'Thủ tục xin giấy phép kinh doanh như thế nào?',
            'Cần những giấy tờ gì để đăng ký doanh nghiệp?',
            'Quy trình xử lý hồ sơ hành chính',
        ];
    }
    
    if (currentAssistant.value.assistant_type === 'document_drafting') {
        return [
            'Tôi cần tạo báo cáo hoạt động tháng này',
            'Tạo báo cáo kết quả dự án',
            'Làm báo cáo tổng kết năm',
        ];
    }
    
    return [
        'Thủ tục xin giấy phép kinh doanh như thế nào?',
        'Cần những giấy tờ gì để đăng ký doanh nghiệp?',
        'Quy trình xử lý hồ sơ hành chính',
    ];
};

const suggestions = computed(() => getSuggestions());

watch(() => props.currentSession, (newSession) => {
    if (newSession) {
        currentSession.value = newSession;
        currentSessionId.value = newSession.id;
        currentAssistant.value = newSession.ai_assistant;
        const sessionMessages = newSession.messages || [];
        
        console.log('[Dashboard] Loading session messages', {
            sessionId: newSession.id,
            messageCount: sessionMessages.length,
            messagesWithMetadata: sessionMessages.filter(m => m.metadata).length,
            messagesWithDocument: sessionMessages.filter(m => m.metadata?.document).length,
        });
        
        // Load attachments from metadata if available
        sessionMessages.forEach(msg => {
            if (msg.metadata?.attachments) {
                msg.attachments = msg.metadata.attachments;
            }
            // ✅ LOG: Check document metadata
            if (msg.metadata?.document) {
                console.log('[Dashboard] Message has document metadata', {
                    messageId: msg.id,
                    document: msg.metadata.document,
                });
            }
        });
        
        messages.value = sessionMessages;
        scrollToBottom();
    }
}, { immediate: true });

watch(() => props.sessions, (newSessions) => {
    chatSessions.value = newSessions || [];
}, { immediate: true });

onMounted(() => {
    if (!assistants.value || assistants.value.length === 0) {
        loadAssistants();
    }
    if (!chatSessions.value || chatSessions.value.length === 0) {
        loadChatSessions();
    }
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

.markdown-content :deep(strong) {
    font-weight: 600;
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
</style>

