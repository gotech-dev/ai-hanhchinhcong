<template>
    <div class="h-screen flex bg-gray-50">
        <!-- Left Sidebar - Chat History -->
        <div class="w-64 bg-gray-900 text-white flex flex-col flex-shrink-0">
            <!-- New Chat Button -->
            <div class="p-4 border-b border-gray-800">
                <button
                    @click="startNewChat"
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
                            currentSession?.id === session.id ? 'bg-gray-800 border-l-2 border-blue-500' : ''
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
        <div class="flex-1 flex flex-col bg-white">
            <!-- Chat Messages Area (when session exists) -->
        <div v-if="currentSession" class="flex-1 overflow-y-auto px-4 py-6 space-y-4 bg-gray-50" ref="messagesContainer">
            <!-- Document Management: Reminders (auto-show when opening chat) -->
            <DocumentReminderCard
                v-if="isDocumentManagement && reminders.total > 0"
                :reminders="reminders.reminders || []"
                :overdue="reminders.overdue || []"
                :due-today="reminders.due_today || []"
                :total="reminders.total || 0"
                @view-all="showDocumentList = true"
            />
            
            <!-- Document Management: Document List -->
            <DocumentList
                v-if="isDocumentManagement && showDocumentList"
                :session-id="currentSession.id"
                @view-document="handleViewDocument"
            />
            
            <!-- Document Management: Search Results -->
            <DocumentSearchResults
                v-if="isDocumentManagement && searchResults.length > 0"
                :documents="searchResults"
                @view-document="handleViewDocument"
            />
            
            <!-- Document Management: Classification Result -->
            <DocumentClassificationResult
                v-if="isDocumentManagement && classificationResult"
                :document="classificationResult"
            />
            <div
                v-for="(message, index) in sortedMessages"
                :key="`${message.id || 'msg'}-${index}-${message.created_at || Date.now()}`"
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
                    
                    <!-- Report Preview Component - Show for assistant messages with report -->
                    <div v-if="message.report && message.sender === 'assistant' && !message.metadata?.document" class="mt-2">
                        <ReportPreview 
                            :report-content="message.report.report_content || message.report.content"
                            :report-id="message.report.report_id || message.report.id || message.report_id"
                            :docx-url="message.report.report_file_path || message.report.docx_url || message.report.file_path"
                        />
                    </div>
                    
                    <!-- Regular message content - Only show if no report and no document -->
                    <div v-if="(!message.report && !message.metadata?.document) || message.sender === 'user'" class="markdown-content" v-html="renderMarkdown(message.content)"></div>
                    
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

        <!-- Initial State (no session, no messages) -->
        <div v-if="!currentSession" class="flex-1 flex items-center justify-center">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-semibold text-gray-800 mb-8">
                    Hãy chọn trợ lý và bắt đầu cuộc trò chuyện nhé.
                </h1>
                <div class="w-full max-w-3xl mx-auto px-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Chọn trợ lý</label>
                    <Combobox v-model="selectedAssistantId" @update:modelValue="handleAssistantSelect" nullable>
                        <div class="relative">
                            <div 
                                class="relative w-full cursor-default overflow-hidden rounded-lg bg-white text-left shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-opacity-75 focus-visible:ring-offset-2 focus-visible:ring-offset-blue-300 sm:text-sm"
                                @click="handleComboboxClick"
                            >
                                <ComboboxInput
                                    ref="comboboxInput"
                                    class="w-full border border-gray-300 rounded-lg py-3 pl-4 pr-10 text-sm leading-5 text-gray-900 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    :display-value="(assistantId) => {
                                        if (!assistantId) return '-- Chọn trợ lý --';
                                        const assistant = assistants.find(a => a.id === assistantId);
                                        return assistant ? assistant.name : '-- Chọn trợ lý --';
                                    }"
                                    @change="query = $event.target.value"
                                    @focus="handleInputFocus"
                                    @mousedown="handleInputMouseDown"
                                    placeholder="🔍 Tìm kiếm trợ lý..."
                                />
                                <ComboboxButton ref="comboboxButton" class="absolute inset-y-0 right-0 flex items-center pr-2">
                                    <ChevronUpDownIcon
                                        class="h-5 w-5 text-gray-400"
                                        aria-hidden="true"
                                    />
                                </ComboboxButton>
                            </div>
                            <Transition
                                leave-active-class="transition duration-100 ease-in"
                                leave-from-class="opacity-100"
                                leave-to-class="opacity-0"
                            >
                                <ComboboxOptions class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
                                    <div v-if="Object.keys(groupedAssistants).length === 0 && query !== ''" class="relative cursor-default select-none px-4 py-2 text-gray-700">
                                        Không tìm thấy trợ lý nào.
                                    </div>
                                    <template v-for="(group, type) in groupedAssistants" :key="type">
                                        <div v-if="group.length > 0" class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase bg-gray-50 sticky top-0">
                                            {{ getTypeLabel(type) }}
                                        </div>
                                        <ComboboxOption
                                            v-for="assistant in group"
                                            :key="assistant.id"
                                            v-slot="{ active, selected }"
                                            :value="assistant.id"
                                            as="template"
                                        >
                                            <li
                                                :class="[
                                                    'relative cursor-pointer select-none py-2 pl-10 pr-4',
                                                    active ? 'bg-blue-50 text-blue-900' : 'text-gray-900'
                                                ]"
                                            >
                                                <span
                                                    :class="[
                                                        'block truncate',
                                                        selected ? 'font-medium' : 'font-normal'
                                                    ]"
                                                >
                                                    {{ assistant.name }}
                                                </span>
                                                <span
                                                    v-if="assistant.description"
                                                    class="block text-xs text-gray-500 truncate mt-0.5"
                                                >
                                                    {{ assistant.description }}
                                                </span>
                                                <span
                                                    v-if="selected"
                                                    :class="[
                                                        'absolute inset-y-0 left-0 flex items-center pl-3',
                                                        active ? 'text-blue-600' : 'text-blue-600'
                                                    ]"
                                                >
                                                    <CheckIcon class="h-5 w-5" aria-hidden="true" />
                                                </span>
                                            </li>
                                        </ComboboxOption>
                                    </template>
                                </ComboboxOptions>
                            </Transition>
                        </div>
                    </Combobox>
                </div>
            </div>
        </div>

        <!-- Input Area (Fixed at bottom) - Only show when assistant is selected or session exists -->
        <div v-if="currentSession || selectedAssistantId" class="border-t border-gray-200 bg-white px-4 py-4">
            <div class="w-full max-w-3xl mx-auto">
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
                
                <!-- Document Type Selector (for document_management) -->
                <div
                    v-if="isDocumentManagement && attachedFiles.length > 0 && !showDocumentTypeSelector"
                    class="mb-2 p-3 bg-blue-50 border border-blue-200 rounded-lg"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-700 font-medium">Chọn loại văn bản trước khi gửi:</span>
                        <div class="flex gap-2">
                            <button
                                @click="handleClassifyDocument('van_ban_den')"
                                :class="[
                                    'px-3 py-1 rounded text-sm transition-colors',
                                    selectedDocumentType === 'van_ban_den' ? 'bg-blue-500 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
                                ]"
                            >
                                📥 Văn bản đến
                            </button>
                            <button
                                @click="handleClassifyDocument('van_ban_di')"
                                :class="[
                                    'px-3 py-1 rounded text-sm transition-colors',
                                    selectedDocumentType === 'van_ban_di' ? 'bg-green-500 text-white' : 'bg-white text-gray-700 border border-gray-300 hover:bg-gray-50'
                                ]"
                            >
                                📤 Văn bản đi
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="relative flex items-center bg-white border border-gray-300 rounded-2xl shadow-sm hover:shadow-md transition-shadow">
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
                        v-if="currentSession"
                        type="button"
                        @click="$refs.fileInput.click()"
                        :disabled="isLoading"
                        class="px-4 py-4 text-gray-600 hover:text-gray-900 transition-colors disabled:opacity-50"
                        title="Đính kèm file"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.414a2 2 0 00-2.828-2.828l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.414a2 2 0 00-2.828-2.828L9 10.172 7.586 8.757a2 2 0 112.828-2.828L15.172 7z" />
                        </svg>
                    </button>

                    <!-- Input Field -->
                    <input
                        v-model="messageInput"
                        @keydown.enter.prevent="handleSend"
                        type="text"
                        :placeholder="currentSession ? 'Nhập tin nhắn...' : 'Ask anything'"
                        class="flex-1 py-4 px-2 text-gray-900 placeholder-gray-400 focus:outline-none text-lg"
                        :disabled="!currentSession || isLoading"
                    />

                    <!-- Right Icons -->
                    <div class="flex items-center gap-2 px-4">
                        <!-- Microphone Icon -->
                        <button
                            class="p-2 text-gray-600 hover:text-gray-900 transition-colors"
                            title="Nhập bằng giọng nói"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z" />
                            </svg>
                        </button>

                        <!-- Equalizer Icon -->
                        <button
                            class="p-2 text-gray-600 hover:text-gray-900 transition-colors"
                            title="Cài đặt"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch, Transition } from 'vue';
import { useChatStream } from '../../composables/useChatStream.js';
import axios from 'axios';
import { marked } from 'marked';
import { Combobox, ComboboxInput, ComboboxButton, ComboboxOptions, ComboboxOption } from '@headlessui/vue';
import { CheckIcon, ChevronUpDownIcon } from '@heroicons/vue/20/solid';
import ReportPreview from '../../Components/ReportPreview.vue';
import DocumentPreview from '../../Components/DocumentPreview.vue';
import DocumentReminderCard from '../../Components/DocumentReminderCard.vue';
import DocumentList from '../../Components/DocumentList.vue';
import DocumentSearchResults from '../../Components/DocumentSearchResults.vue';
import DocumentClassificationResult from '../../Components/DocumentClassificationResult.vue';

const props = defineProps({
    auth: Object,
    assistants: Array,
    sessions: Array,
});

const messageInput = ref('');
const assistants = ref(props.assistants || []);
const selectedAssistantId = ref(null);
const query = ref('');
const currentSession = ref(null);
const currentAssistant = ref(null);
const messages = ref([]);
const isLoading = ref(false);
const messagesContainer = ref(null);
const showAssistantInfo = ref(false);
const useStreaming = ref(true);
const { streamResponse } = useChatStream();
const attachedFiles = ref([]);
const fileInput = ref(null);
const chatSessions = ref(props.sessions || []);
const comboboxInput = ref(null);
const comboboxButton = ref(null);

// Handle combobox container click to open dropdown
const handleComboboxClick = (event) => {
    // Don't trigger if clicking directly on the button
    if (event.target.closest('button[type="button"]')) {
        return;
    }
    
    // Find and click the combobox button to open dropdown
    nextTick(() => {
        // Try multiple ways to find the button
        let button = null;
        
        // Method 1: Use ref
        if (comboboxButton.value) {
            button = comboboxButton.value.$el || comboboxButton.value;
        }
        
        // Method 2: Use querySelector as fallback
        if (!button) {
            const input = comboboxInput.value?.$el || comboboxInput.value;
            if (input) {
                const container = input.closest('.relative');
                if (container) {
                    button = container.querySelector('button[type="button"]');
                }
            }
        }
        
        // Method 3: Find by aria attributes
        if (!button) {
            button = document.querySelector('[aria-controls*="headlessui-combobox-options"]');
        }
        
        if (button) {
            const isExpanded = button.getAttribute('aria-expanded') === 'true';
            if (!isExpanded) {
                button.click();
            }
        }
    });
};

// Handle combobox input mousedown to open dropdown
const handleInputMouseDown = () => {
    // Trigger button click to open after a short delay to ensure input is focused
    setTimeout(() => {
        let button = null;
        
        // Try to find button using ref first
        if (comboboxButton.value) {
            button = comboboxButton.value.$el || comboboxButton.value;
        }
        
        // Fallback: find button using querySelector
        if (!button && comboboxInput.value) {
            const input = comboboxInput.value.$el || comboboxInput.value;
            if (input) {
                const container = input.closest('.relative');
                if (container) {
                    button = container.querySelector('button[type="button"]');
                }
            }
        }
        
        // Last resort: find by aria attributes
        if (!button) {
            button = document.querySelector('[aria-controls*="headlessui-combobox-options"]');
        }
        
        if (button) {
            const isExpanded = button.getAttribute('aria-expanded') === 'true';
            if (!isExpanded) {
                button.click();
            }
        }
    }, 10);
};

// Handle combobox input focus to ensure dropdown opens
const handleInputFocus = () => {
    // Ensure combobox opens on focus
    nextTick(() => {
        let button = null;
        
        if (comboboxButton.value) {
            button = comboboxButton.value.$el || comboboxButton.value;
        }
        
        if (!button && comboboxInput.value) {
            const input = comboboxInput.value.$el || comboboxInput.value;
            if (input) {
                const container = input.closest('.relative');
                if (container) {
                    button = container.querySelector('button[type="button"]');
                }
            }
        }
        
        if (button) {
            const isExpanded = button.getAttribute('aria-expanded') === 'true';
            if (!isExpanded) {
                button.click();
            }
        }
    });
};

// Document Management state
const reminders = ref({ reminders: [], overdue: [], due_today: [], total: 0 });
const showDocumentList = ref(false);
const searchResults = ref([]);
const classificationResult = ref(null);
const showDocumentTypeSelector = ref(false);
const selectedDocumentType = ref('van_ban_den');

// Computed: Check if current assistant is document_management
const isDocumentManagement = computed(() => {
    return currentAssistant.value?.assistant_type === 'document_management';
});

// Computed để sắp xếp messages theo created_at
const sortedMessages = computed(() => {
    return [...messages.value].sort((a, b) => {
        const timeA = new Date(a.created_at || 0).getTime();
        const timeB = new Date(b.created_at || 0).getTime();
        return timeA - timeB;
    });
});

// Computed để filter assistants theo query search
const filteredAssistants = computed(() => {
    if (!query.value) {
        return assistants.value || [];
    }
    
    const searchTerm = query.value.toLowerCase();
    return (assistants.value || []).filter(assistant => {
        return (
            assistant.name.toLowerCase().includes(searchTerm) ||
            (assistant.description && assistant.description.toLowerCase().includes(searchTerm)) ||
            (assistant.assistant_type && assistant.assistant_type.toLowerCase().includes(searchTerm))
        );
    });
});

// Computed để group assistants theo type
const groupedAssistants = computed(() => {
    const groups = {};
    filteredAssistants.value.forEach(assistant => {
        const type = assistant.assistant_type || 'other';
        if (!groups[type]) {
            groups[type] = [];
        }
        groups[type].push(assistant);
    });
    
    // Sort groups by predefined order
    const typeOrder = ['document_drafting', 'qa_based_document', 'document_management', 'other'];
    const sortedGroups = {};
    typeOrder.forEach(type => {
        if (groups[type]) {
            sortedGroups[type] = groups[type];
        }
    });
    // Add any remaining types
    Object.keys(groups).forEach(type => {
        if (!sortedGroups[type]) {
            sortedGroups[type] = groups[type];
        }
    });
    
    return sortedGroups;
});

// Helper function để lấy label cho type
const getTypeLabel = (type) => {
    const labels = {
        'document_drafting': '📄 Soạn thảo văn bản',
        'qa_based_document': '❓ Q&A từ tài liệu',
        'document_management': '📁 Quản lý văn bản',
        'other': 'Trợ lý khác'
    };
    return labels[type] || 'Trợ lý khác';
};

const loadAssistants = async () => {
    try {
        const response = await axios.get('/api/assistants');
        assistants.value = response.data.assistants.data || response.data.assistants || [];
    } catch (error) {
        console.error('Error loading assistants:', error);
    }
};

const handleAssistantSelect = async (assistantId) => {
    // Reset query when assistant is selected
    query.value = '';
    selectedAssistantId.value = assistantId;
    await onAssistantChange();
};

const onAssistantChange = async () => {
    // If no assistant selected, reset everything
    if (!selectedAssistantId.value) {
        currentSession.value = null;
        currentAssistant.value = null;
        messages.value = [];
        messageInput.value = '';
        isLoading.value = false;
        return;
    }
    
    // If assistant changed, reset and create new session
    if (currentSession.value && currentSession.value.ai_assistant_id !== selectedAssistantId.value) {
        currentSession.value = null;
        currentAssistant.value = null;
        messages.value = [];
        messageInput.value = '';
        isLoading.value = false;
    }
    
    // If no session exists and assistant is selected, create a new session
    if (!currentSession.value && selectedAssistantId.value) {
        try {
            isLoading.value = true;
            const sessionResponse = await axios.post(`/api/chat/sessions/assistant/${selectedAssistantId.value}`, {
                new: true  // Force tạo session mới
            });
            currentSession.value = sessionResponse.data.session;
            currentAssistant.value = currentSession.value.ai_assistant;
            
            // Load messages from session
            const sessionMessages = currentSession.value.messages || [];
            messages.value = sessionMessages.sort((a, b) => {
                const timeA = new Date(a.created_at || 0).getTime();
                const timeB = new Date(b.created_at || 0).getTime();
                return timeA - timeB;
            });
            
            // Load reminders if document_management assistant
            if (currentAssistant.value?.assistant_type === 'document_management') {
                await loadReminders();
            }
            
            // Add session to chat sessions list if not already there
            const existingSession = chatSessions.value.find(s => s.id === currentSession.value.id);
            if (!existingSession) {
                await loadChatSessions();
            }
            
            scrollToBottom();
        } catch (error) {
            console.error('Error creating session:', error);
            const errorMessage = error.response?.data?.message 
                || error.response?.data?.error 
                || error.message 
                || 'Không thể tạo cuộc trò chuyện. Vui lòng thử lại.';
            alert(errorMessage);
            selectedAssistantId.value = null;
        } finally {
            isLoading.value = false;
        }
    }
};

// Helper function để generate unique ID
const generateMessageId = () => {
    return `msg-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
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

const handleSend = async () => {
    const message = messageInput.value.trim();
    
    // Require either message or files
    if (!message && attachedFiles.value.length === 0) return;
    
    // If no assistant selected and no session, show error
    if ((!selectedAssistantId.value || selectedAssistantId.value === null || selectedAssistantId.value === '') && !currentSession.value) {
        alert('Vui lòng chọn trợ lý trước khi gửi tin nhắn');
        return;
    }
    
    // If no session yet, create one
    if (!currentSession.value) {
        const assistantId = selectedAssistantId.value;
        
        if (!assistantId || assistantId === null || assistantId === '') {
            alert('Vui lòng chọn trợ lý trước khi gửi tin nhắn');
            return;
        }
        
        try {
            // Reset messages hoàn toàn trước khi tạo session mới
            messages.value = [];
            
            // Force tạo session mới (không reuse session cũ)
            const sessionResponse = await axios.post(`/api/chat/sessions/assistant/${assistantId}`, {
                new: true  // Force tạo session mới
            });
            currentSession.value = sessionResponse.data.session;
            currentAssistant.value = currentSession.value.ai_assistant;
            
            // Chỉ set messages từ session mới, đảm bảo không có messages cũ
            const sessionMessages = currentSession.value.messages || [];
            
            // Load reminders if document_management assistant
            if (currentAssistant.value?.assistant_type === 'document_management') {
                await loadReminders();
            }
            // Sắp xếp messages theo created_at để đảm bảo thứ tự
            messages.value = sessionMessages.sort((a, b) => {
                const timeA = new Date(a.created_at || 0).getTime();
                const timeB = new Date(b.created_at || 0).getTime();
                return timeA - timeB;
            });
            
            scrollToBottom();
        } catch (error) {
            console.error('Error creating session:', error);
            console.error('Error details:', error.response?.data);
            const errorMessage = error.response?.data?.message 
                || error.response?.data?.error 
                || error.message 
                || 'Không thể tạo cuộc trò chuyện. Vui lòng thử lại.';
            alert(errorMessage);
            return;
        }
    }
    
    // Upload files first if any
    let uploadedFiles = [];
    if (attachedFiles.value.length > 0) {
        // For document_management assistant, classify documents instead of regular upload
        if (isDocumentManagement.value) {
            try {
                // Process each file for classification
                for (const file of attachedFiles.value) {
                    // Only process PDF/DOCX for classification
                    const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                    if (!allowedTypes.includes(file.type) && !file.name.match(/\.(pdf|doc|docx)$/i)) {
                        // For other file types, use regular upload
                        const formData = new FormData();
                        formData.append('files[]', file);
                        
                        const uploadResponse = await axios.post(
                            `/api/chat/sessions/${currentSession.value.id}/upload`,
                            formData,
                            {
                                headers: {
                                    'Content-Type': 'multipart/form-data',
                                },
                            }
                        );
                        
                        uploadedFiles.push(...(uploadResponse.data.files || []));
                        continue;
                    }
                    
                    // Classify document
                    const classifyFormData = new FormData();
                    classifyFormData.append('file', file);
                    classifyFormData.append('loai_van_ban', selectedDocumentType.value);
                    
                    const classifyResponse = await axios.post(
                        `/api/chat/sessions/${currentSession.value.id}/documents/classify`,
                        classifyFormData,
                        {
                            headers: {
                                'Content-Type': 'multipart/form-data',
                            },
                        }
                    );
                    
                    // Show classification result
                    if (classifyResponse.data.document) {
                        classificationResult.value = classifyResponse.data.document;
                        
                        // Add user message about classification
                        const userMessage = {
                            id: generateMessageId(),
                            sender: 'user',
                            content: `Đã upload và phân loại văn bản: ${file.name}`,
                            created_at: new Date().toISOString(),
                        };
                        messages.value.push(userMessage);
                        
                        // Add assistant response with classification result
                        const assistantMessage = {
                            id: generateMessageId(),
                            sender: 'assistant',
                            content: `Văn bản "${file.name}" đã được phân loại thành công. Xem kết quả bên dưới.`,
                            created_at: new Date().toISOString(),
                        };
                        messages.value.push(assistantMessage);
                        
                        // Reload reminders after classification
                        await loadReminders();
                        
                        scrollToBottom();
                    }
                }
                
                // Clear attached files after processing
                attachedFiles.value = [];
                showDocumentTypeSelector.value = false;
                
                // If no message, just return (classification is done)
                if (!message) {
                    isLoading.value = false;
                    return;
                }
            } catch (error) {
                console.error('Error classifying document:', error);
                const errorMessage = error.response?.data?.error || error.message || 'Không thể phân loại văn bản. Vui lòng thử lại.';
                alert(errorMessage);
                isLoading.value = false;
                return;
            }
        } else {
            // Regular upload for other assistant types
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
    }
    
    // Build message content
    let messageContent = message;
    if (uploadedFiles.length > 0) {
        const fileNames = uploadedFiles.map(f => f.name).join(', ');
        messageContent = messageContent 
            ? `${messageContent}\n\n[Đã đính kèm: ${fileNames}]`
            : `[Đã đính kèm: ${fileNames}]`;
    }
    
    // Add user message to UI
    messageInput.value = '';
    isLoading.value = true;
    
    const userMessage = {
        id: generateMessageId(),
        sender: 'user',
        content: messageContent,
        created_at: new Date().toISOString(),
        attachments: uploadedFiles,
    };
    messages.value.push(userMessage);
    
    // Clear attached files
    attachedFiles.value = [];
    
    scrollToBottom();
    
    // Create placeholder for assistant response
    const assistantMessageId = generateMessageId();
    let assistantMessage = {
        id: assistantMessageId,
        sender: 'assistant',
        content: '',
        created_at: new Date().toISOString(),
    };
    messages.value.push(assistantMessage);
    
    if (useStreaming.value) {
        let fullContent = '';
        
        // Define onReport callback BEFORE calling streamResponse
        const onReportCallback = (reportData, messageId) => {
            // ✅ LOG: Report callback called
            console.log('[IndexNew] onReportCallback called', {
                hasReportData: !!reportData,
                reportData: reportData,
                messageId: messageId,
            });
            
            if (reportData) {
                // Normalize report data structure
                assistantMessage.report = {
                    report_id: reportData.report_id || reportData.id,
                    report_content: reportData.report_content || reportData.content,
                    report_file_path: reportData.report_file_path || reportData.docx_url || reportData.file_path,
                };
                assistantMessage.id = messageId || assistantMessage.id;
                
                // ✅ LOG: Report data normalized
                console.log('[IndexNew] Report data normalized', {
                    report: assistantMessage.report,
                    messageId: assistantMessage.id,
                });
                
                // Force Vue reactivity update
                messages.value = [...messages.value];
                
                // ✅ LOG: Messages updated
                console.log('[IndexNew] Messages updated with report', {
                    messageIndex: messages.value.findIndex(m => m.id === assistantMessage.id),
                    hasReport: !!messages.value.find(m => m.id === assistantMessage.id)?.report,
                });
            } else {
                console.warn('[IndexNew] onReportCallback called but no reportData', {
                    messageId: messageId,
                });
            }
        };
        
        // ✅ FIX: Define onDocument callback for document_drafting assistant
        const onDocumentCallback = (document, messageId) => {
            console.log('[IndexNew] onDocument callback called', {
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
            
            console.log('[IndexNew] Document metadata set', {
                messageId: assistantMessage.id,
                metadata: assistantMessage.metadata,
            });
            
            // Force reactivity
            messages.value = [...messages.value];
            scrollToBottom();
        };
        
        streamResponse(
            currentSession.value.id,
            message || null,
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
            async (data) => {
                isLoading.value = false;
                
                // Update message ID if provided
                if (data?.message_id) {
                    assistantMessage.id = data.message_id;
                }
                
                // ✅ FIX: If document data exists but onDocumentCallback wasn't called, set it here
                if (data?.document && !assistantMessage.metadata?.document) {
                    console.log('[IndexNew] Setting document metadata in onComplete (fallback)', {
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
                
                // Check if report data is in onComplete data (fallback)
                if (data?.report && !assistantMessage.report) {
                    assistantMessage.report = {
                        report_id: data.report.report_id || data.report.id,
                        report_content: data.report.report_content || data.report.content,
                        report_file_path: data.report.report_file_path || data.report.docx_url || data.report.file_path,
                    };
                    // Force Vue reactivity update
                    messages.value = [...messages.value];
                }
                
                // Check for document search results (document_management)
                if (isDocumentManagement.value && data?.message_id) {
                    try {
                        const response = await axios.get(`/api/chat/sessions/${currentSession.value.id}/history`);
                        const serverMessages = response.data.messages || [];
                        const serverMessage = serverMessages.find(m => m.id === data.message_id);
                        
                        if (serverMessage?.metadata?.search_results) {
                            searchResults.value = serverMessage.metadata.search_results;
                        }
                    } catch (error) {
                        console.error('Error loading search results:', error);
                    }
                }
                
                if (data?.message_id && !assistantMessage.report) {
                    // If no report in SSE, try to load from message metadata
                    try {
                        const response = await axios.get(`/api/chat/sessions/${currentSession.value.id}/history`);
                        const serverMessages = response.data.messages || [];
                        const serverMessage = serverMessages.find(m => m.id === data.message_id);
                        
                        if (serverMessage?.metadata?.report) {
                            assistantMessage.report = {
                                report_id: serverMessage.metadata.report.report_id || serverMessage.metadata.report.id,
                                report_content: serverMessage.metadata.report.report_content || serverMessage.metadata.report.content,
                                report_file_path: serverMessage.metadata.report.report_file_path || serverMessage.metadata.report.docx_url || serverMessage.metadata.report.file_path,
                            };
                            // Force Vue reactivity update
                            messages.value = [...messages.value];
                        }
                    } catch (error) {
                        console.error('Failed to load message metadata:', error);
                    }
                }
                
                // ✅ FIX: Update assistantMessage with metadata from database
                if (data?.message_id) {
                    try {
                        const response = await axios.get(`/api/chat/sessions/${currentSession.value.id}/history`);
                        const serverMessages = response.data.messages || [];
                        const serverMessage = serverMessages.find(m => m.id === data.message_id);
                        
                        if (serverMessage?.metadata?.document && !assistantMessage.metadata?.document) {
                            console.log('[IndexNew] Updating assistantMessage with metadata from database', {
                                messageId: assistantMessage.id,
                                metadata: serverMessage.metadata,
                            });
                            
                            if (!assistantMessage.metadata) {
                                assistantMessage.metadata = {};
                            }
                            assistantMessage.metadata.document = serverMessage.metadata.document;
                            
                            // Force reactivity
                            messages.value = [...messages.value];
                        }
                    } catch (error) {
                        console.error('Failed to load message metadata:', error);
                    }
                }
                
                // Message đã được stream đầy đủ, không cần reload
                // Chỉ update ID từ server nếu cần thiết
                scrollToBottom();
            },
            (error) => {
                isLoading.value = false;
                assistantMessage.content = error || 'Xin lỗi, đã có lỗi xảy ra. Vui lòng thử lại sau.';
                scrollToBottom();
            },
            uploadedFiles,
            // Handle report data - Pass the callback function
            onReportCallback,
            // ✅ FIX: Handle document data - Pass the callback function for document_drafting assistant
            onDocumentCallback
        );
    } else {
        try {
            const response = await axios.post(`/api/chat/sessions/${currentSession.value.id}/message`, {
                message: message || 'Xem file đính kèm',
                attachments: uploadedFiles,
            });
            
            assistantMessage.content = response.data.assistant_message.content;
            assistantMessage.id = response.data.assistant_message.id;
            // Update user message ID từ server nếu có
            if (response.data.user_message?.id) {
                userMessage.id = response.data.user_message.id;
            }
            // Không reload messages, chỉ merge nếu cần
        } catch (error) {
            console.error('Error sending message:', error);
            assistantMessage.content = 'Xin lỗi, đã có lỗi xảy ra. Vui lòng thử lại sau.';
        } finally {
            isLoading.value = false;
            scrollToBottom();
        }
    }
};

const loadMessages = async () => {
    if (!currentSession.value) return;
    
    try {
        const response = await axios.get(`/api/chat/sessions/${currentSession.value.id}/history`);
        const serverMessages = response.data.messages || [];
        
        // Merge với messages hiện tại thay vì thay thế
        const messageMap = new Map();
        
        // Thêm messages hiện tại vào map (ưu tiên messages đang stream)
        messages.value.forEach(msg => {
            if (msg.id && !messageMap.has(msg.id)) {
                messageMap.set(msg.id, msg);
            }
        });
        
        // Merge với messages từ server (không ghi đè messages đang stream)
        serverMessages.forEach(msg => {
            // Chỉ thêm message từ server nếu chưa có trong map hoặc không phải message đang stream
            if (!messageMap.has(msg.id)) {
                // Add attachments from metadata if available
                if (msg.metadata?.attachments) {
                    msg.attachments = msg.metadata.attachments;
                }
                // Add report from metadata if available and normalize structure
                if (msg.metadata?.report) {
                    msg.report = {
                        report_id: msg.metadata.report.report_id || msg.metadata.report.id,
                        report_content: msg.metadata.report.report_content || msg.metadata.report.content,
                        report_file_path: msg.metadata.report.report_file_path || msg.metadata.report.docx_url || msg.metadata.report.file_path,
                    };
                }
                messageMap.set(msg.id, msg);
            }
        });
        
        // Chuyển về array và sắp xếp theo created_at
        messages.value = Array.from(messageMap.values()).sort((a, b) => {
            const timeA = new Date(a.created_at || 0).getTime();
            const timeB = new Date(b.created_at || 0).getTime();
            return timeA - timeB;
        });
    } catch (error) {
        console.error('Error loading messages:', error);
    }
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

const getLatestMessageContent = (session) => {
    if (!session) return 'Chưa có tin nhắn';
    
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
    if (currentSession.value?.id === sessionId) return;
    
    try {
        const response = await axios.get(`/api/chat/sessions/${sessionId}/history`);
        const session = response.data.session;
        
        currentSession.value = session;
        currentAssistant.value = session.ai_assistant;
        const sessionMessages = session.messages || [];
        // Load attachments, report, and document from metadata if available
        sessionMessages.forEach(msg => {
            if (msg.metadata?.attachments) {
                msg.attachments = msg.metadata.attachments;
            }
            if (msg.metadata?.report) {
                // ✅ LOG: Normalizing report from metadata
                console.log('[IndexNew] Normalizing report from metadata', {
                    messageId: msg.id,
                    metadataReport: msg.metadata.report,
                });
                
                // Normalize report data structure
                msg.report = {
                    report_id: msg.metadata.report.report_id || msg.metadata.report.id,
                    report_content: msg.metadata.report.report_content || msg.metadata.report.content,
                    report_file_path: msg.metadata.report.report_file_path || msg.metadata.report.docx_url || msg.metadata.report.file_path,
                };
                
                // ✅ LOG: Report normalized
                console.log('[IndexNew] Report normalized from metadata', {
                    messageId: msg.id,
                    report: msg.report,
                });
            }
            // ✅ FIX: Check document metadata
            if (msg.metadata?.document) {
                console.log('[IndexNew] Message has document metadata', {
                    messageId: msg.id,
                    document: msg.metadata.document,
                });
            }
        });
        messages.value = sessionMessages;
        scrollToBottom();
    } catch (error) {
        console.error('Error selecting session:', error);
    }
};

const startNewChat = () => {
    currentSession.value = null;
    currentAssistant.value = null;
    messages.value = [];
    messageInput.value = '';
    selectedAssistantId.value = null;
    isLoading.value = false;
    attachedFiles.value = [];
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
            if (currentSession.value?.id === sessionId) {
                currentSession.value = null;
                currentAssistant.value = null;
                messages.value = [];
                messageInput.value = '';
                selectedAssistantId.value = null;
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

// Watch props.sessions only on initial load
// After that, we manage chatSessions ourselves via loadChatSessions()
const isInitialized = ref(false);
watch(() => props.sessions, (newSessions) => {
    if (!isInitialized.value && newSessions) {
        chatSessions.value = newSessions || [];
        isInitialized.value = true;
    }
}, { immediate: true });

// Load reminders for document_management assistant
const loadReminders = async () => {
    if (!currentSession.value || !isDocumentManagement.value) return;
    
    try {
        const response = await axios.get(`/api/chat/sessions/${currentSession.value.id}/reminders`);
        reminders.value = {
            reminders: response.data.reminders || [],
            overdue: response.data.overdue || [],
            due_today: response.data.due_today || [],
            total: response.data.total || 0,
        };
    } catch (error) {
        console.error('Error loading reminders:', error);
        reminders.value = { reminders: [], overdue: [], due_today: [], total: 0 };
    }
};

// Handle view document
const handleViewDocument = (document) => {
    // Show document details in a modal or navigate to detail page
    console.log('View document:', document);
    // TODO: Implement document detail view
    alert(`Xem chi tiết văn bản: ${document.so_van_ban || 'N/A'}\n${document.trich_yeu || ''}`);
};

// Handle classify document (for document_management)
const handleClassifyDocument = async (loaiVanBan) => {
    if (!currentSession.value || attachedFiles.value.length === 0) return;
    
    selectedDocumentType.value = loaiVanBan;
    isLoading.value = true;
    
    try {
        // Process each file for classification
        for (const file of attachedFiles.value) {
            // Only process PDF/DOCX for classification
            const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            if (!allowedTypes.includes(file.type) && !file.name.match(/\.(pdf|doc|docx)$/i)) {
                // For other file types, use regular upload
                const formData = new FormData();
                formData.append('files[]', file);
                
                const uploadResponse = await axios.post(
                    `/api/chat/sessions/${currentSession.value.id}/upload`,
                    formData,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data',
                        },
                    }
                );
                
                // Add user message about upload
                const userMessage = {
                    id: generateMessageId(),
                    sender: 'user',
                    content: `Đã upload file: ${file.name}`,
                    created_at: new Date().toISOString(),
                };
                messages.value.push(userMessage);
                continue;
            }
            
            // Classify document
            const classifyFormData = new FormData();
            classifyFormData.append('file', file);
            classifyFormData.append('loai_van_ban', loaiVanBan);
            
            const classifyResponse = await axios.post(
                `/api/chat/sessions/${currentSession.value.id}/documents/classify`,
                classifyFormData,
                {
                    headers: {
                        'Content-Type': 'multipart/form-data',
                    },
                }
            );
            
            // Show classification result
            if (classifyResponse.data.document) {
                classificationResult.value = classifyResponse.data.document;
                
                // Add user message about classification
                const userMessage = {
                    id: generateMessageId(),
                    sender: 'user',
                    content: `Đã upload và phân loại văn bản: ${file.name} (${loaiVanBan === 'van_ban_den' ? 'Văn bản đến' : 'Văn bản đi'})`,
                    created_at: new Date().toISOString(),
                };
                messages.value.push(userMessage);
                
                // Add assistant response with classification result
                const assistantMessage = {
                    id: generateMessageId(),
                    sender: 'assistant',
                    content: `Văn bản "${file.name}" đã được phân loại thành công. Xem kết quả bên dưới.`,
                    created_at: new Date().toISOString(),
                };
                messages.value.push(assistantMessage);
                
                // Reload reminders after classification
                await loadReminders();
                
                scrollToBottom();
            }
        }
        
        // Clear attached files after processing
        attachedFiles.value = [];
        showDocumentTypeSelector.value = false;
    } catch (error) {
        console.error('Error classifying document:', error);
        const errorMessage = error.response?.data?.error || error.message || 'Không thể phân loại văn bản. Vui lòng thử lại.';
        alert(errorMessage);
    } finally {
        isLoading.value = false;
    }
};

// Watch for session changes to load reminders
watch(() => currentSession.value?.id, async (newSessionId) => {
    if (newSessionId && isDocumentManagement.value) {
        await loadReminders();
    }
});

// Watch for assistant type changes
watch(() => currentAssistant.value?.assistant_type, async (newType) => {
    if (newType === 'document_management' && currentSession.value) {
        await loadReminders();
    } else {
        reminders.value = { reminders: [], overdue: [], due_today: [], total: 0 };
        showDocumentList.value = false;
        searchResults.value = [];
        classificationResult.value = null;
        showDocumentTypeSelector.value = false;
    }
});

onMounted(() => {
    if (!assistants.value || assistants.value.length === 0) {
        loadAssistants();
    }
    if (!chatSessions.value || chatSessions.value.length === 0) {
        loadChatSessions();
    }
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
