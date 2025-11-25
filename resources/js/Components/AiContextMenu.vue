<template>
    <!-- Context Menu -->
    <Teleport to="body">
        <div 
            v-if="showMenu" 
            :style="menuStyle"
            class="fixed bg-white border border-gray-200 rounded-lg shadow-lg py-1 z-[9999] min-w-[200px]"
            @click.stop
        >
            <button 
                @click="handleRewrite"
                class="w-full px-4 py-2 text-left hover:bg-blue-50 flex items-center gap-2 text-sm transition-colors"
            >
                <span>📝</span> Viết lại với AI
            </button>
            <button 
                @click="handleSummarize"
                class="w-full px-4 py-2 text-left hover:bg-blue-50 flex items-center gap-2 text-sm transition-colors"
            >
                <span>🔄</span> Tóm tắt
            </button>
            <button 
                @click="handleExpand"
                class="w-full px-4 py-2 text-left hover:bg-blue-50 flex items-center gap-2 text-sm transition-colors"
            >
                <span>📖</span> Mở rộng chi tiết
            </button>
            <button 
                @click="handleFixGrammar"
                class="w-full px-4 py-2 text-left hover:bg-blue-50 flex items-center gap-2 text-sm transition-colors"
            >
                <span>✏️</span> Sửa lỗi chính tả
            </button>
        </div>
    </Teleport>

    <!-- Rewrite Popup -->
    <Teleport to="body">
        <div 
            v-if="showRewritePopup" 
            class="fixed inset-0 bg-black/50 flex items-center justify-center z-[10000]"
            @click.self="closePopup"
        >
            <div class="bg-white rounded-lg shadow-xl w-[500px] max-w-[90vw] max-h-[90vh] overflow-y-auto">
                <div class="p-4 border-b border-gray-200">
                    <h3 class="font-semibold text-lg text-gray-900">{{ popupTitle }}</h3>
                </div>
                
                <div class="p-4 space-y-4">
                    <!-- Selected text preview -->
                    <div>
                        <label class="text-sm font-medium text-gray-700 mb-1 block">Đoạn văn đã chọn:</label>
                        <div class="bg-yellow-50 border border-yellow-200 rounded p-3 text-sm max-h-[100px] overflow-y-auto text-gray-800">
                            {{ selectedText }}
                        </div>
                    </div>
                    
                    <!-- Instruction input -->
                    <div>
                        <label class="text-sm font-medium text-gray-700 mb-1 block">Yêu cầu của bạn:</label>
                        <textarea
                            v-model="instruction"
                            :placeholder="instructionPlaceholder"
                            class="w-full border border-gray-300 rounded-lg p-3 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
                            rows="3"
                        ></textarea>
                    </div>
                    
                    <!-- Quick actions (only for rewrite) -->
                    <div v-if="actionType === 'rewrite'" class="flex flex-wrap gap-2">
                        <button 
                            @click="instruction = 'Viết lại ngắn gọn hơn'"
                            class="px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded-full transition-colors"
                        >Ngắn gọn hơn</button>
                        <button 
                            @click="instruction = 'Viết lại chi tiết hơn'"
                            class="px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded-full transition-colors"
                        >Chi tiết hơn</button>
                        <button 
                            @click="instruction = 'Viết lại với giọng văn trang trọng'"
                            class="px-3 py-1 text-xs bg-gray-100 hover:bg-gray-200 rounded-full transition-colors"
                        >Trang trọng</button>
                    </div>
                </div>
                
                <div class="p-4 border-t border-gray-200 flex justify-end gap-2">
                    <button 
                        @click="closePopup"
                        class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors text-sm font-medium"
                    >Hủy</button>
                    <button 
                        @click="submitAction"
                        :disabled="isLoading || !instruction.trim()"
                        class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 transition-colors text-sm font-medium"
                    >
                        <svg v-if="isLoading" class="animate-spin w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ isLoading ? 'Đang xử lý...' : actionButtonText }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';

const emit = defineEmits(['rewrite-complete', 'action-complete']);

const showMenu = ref(false);
const showRewritePopup = ref(false);
const menuPosition = ref({ x: 0, y: 0 });
const selectedText = ref('');
const selectedRange = ref(null);
const instruction = ref('');
const isLoading = ref(false);
const actionType = ref('rewrite'); // 'rewrite', 'summarize', 'expand', 'fix-grammar'

const menuStyle = computed(() => ({
    left: `${menuPosition.value.x}px`,
    top: `${menuPosition.value.y}px`,
}));

const popupTitle = computed(() => {
    const titles = {
        'rewrite': '📝 Viết lại với AI',
        'summarize': '🔄 Tóm tắt với AI',
        'expand': '📖 Mở rộng chi tiết',
        'fix-grammar': '✏️ Sửa lỗi chính tả',
    };
    return titles[actionType.value] || '📝 Viết lại với AI';
});

const instructionPlaceholder = computed(() => {
    const placeholders = {
        'rewrite': 'Ví dụ: viết lại đoạn văn này với mục tiêu 2026, thêm số liệu cụ thể...',
        'summarize': 'Ví dụ: tóm tắt ngắn gọn trong 2-3 câu...',
        'expand': 'Ví dụ: mở rộng thêm về phần tài chính, thêm số liệu cụ thể...',
        'fix-grammar': 'Sửa lỗi chính tả và ngữ pháp (không cần nhập yêu cầu)',
    };
    return placeholders[actionType.value] || 'Nhập yêu cầu của bạn...';
});

const actionButtonText = computed(() => {
    const texts = {
        'rewrite': 'Viết lại',
        'summarize': 'Tóm tắt',
        'expand': 'Mở rộng',
        'fix-grammar': 'Sửa lỗi',
    };
    return texts[actionType.value] || 'Xử lý';
});

// Show context menu
const showContextMenu = (event, text, range) => {
    if (!text || text.trim().length === 0) return;
    
    event.preventDefault();
    event.stopPropagation();
    
    selectedText.value = text;
    selectedRange.value = range;
    
    // Calculate menu position (adjust if near screen edges)
    const x = event.clientX;
    const y = event.clientY;
    const menuWidth = 200;
    const menuHeight = 200;
    
    let menuX = x;
    let menuY = y;
    
    // Adjust if too close to right edge
    if (x + menuWidth > window.innerWidth) {
        menuX = window.innerWidth - menuWidth - 10;
    }
    
    // Adjust if too close to bottom edge
    if (y + menuHeight > window.innerHeight) {
        menuY = window.innerHeight - menuHeight - 10;
    }
    
    menuPosition.value = { x: menuX, y: menuY };
    showMenu.value = true;
    
    // Close menu when clicking elsewhere
    setTimeout(() => {
        document.addEventListener('click', closeMenu, { once: true });
    }, 100);
};

const closeMenu = () => {
    showMenu.value = false;
};

const handleRewrite = () => {
    closeMenu();
    actionType.value = 'rewrite';
    instruction.value = '';
    showRewritePopup.value = true;
};

const handleSummarize = () => {
    closeMenu();
    actionType.value = 'summarize';
    instruction.value = 'Tóm tắt ngắn gọn trong 2-3 câu';
    showRewritePopup.value = true;
};

const handleExpand = () => {
    closeMenu();
    actionType.value = 'expand';
    instruction.value = 'Mở rộng thêm chi tiết';
    showRewritePopup.value = true;
};

const handleFixGrammar = () => {
    closeMenu();
    actionType.value = 'fix-grammar';
    instruction.value = 'Sửa lỗi chính tả và ngữ pháp';
    showRewritePopup.value = true;
};

const closePopup = () => {
    showRewritePopup.value = false;
    instruction.value = '';
    actionType.value = 'rewrite';
};

// Call AI API
const submitAction = async () => {
    if (!instruction.value.trim() || !selectedText.value) return;
    
    isLoading.value = true;
    
    try {
        // Determine API endpoint based on action type
        const endpoint = `/api/ai/${actionType.value}`;
        
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({
                selected_text: selectedText.value,
                instruction: instruction.value,
            }),
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || `Failed to ${actionType.value}`);
        }
        
        const data = await response.json();
        
        if (!data.success || !data.result_text) {
            throw new Error('Invalid response from server');
        }
        
        // Emit event với text mới và range để replace
        emit('action-complete', {
            actionType: actionType.value,
            originalText: selectedText.value,
            newText: data.result_text,
            range: selectedRange.value,
        });
        
        closePopup();
    } catch (error) {
        console.error(`${actionType.value} failed:`, error);
        alert(`Không thể xử lý. ${error.message || 'Vui lòng thử lại.'}`);
    } finally {
        isLoading.value = false;
    }
};

// Cleanup on unmount
onUnmounted(() => {
    document.removeEventListener('click', closeMenu);
});

// Expose method for parent component
defineExpose({ showContextMenu });
</script>

<style scoped>
/* Additional styles if needed */
</style>

