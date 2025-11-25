<template>
    <div class="rich-text-editor border border-gray-300 rounded-lg overflow-hidden bg-white flex flex-col h-full">
        <!-- Toolbar -->
        <div class="editor-toolbar bg-gray-50 border-b border-gray-200 p-2 flex flex-wrap gap-1 items-center sticky top-0 z-10">
            <!-- History -->
            <div class="flex gap-1 mr-2 border-r border-gray-300 pr-2">
                <button @click="execCommand('undo')" class="toolbar-btn" title="Hoàn tác">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                </button>
                <button @click="execCommand('redo')" class="toolbar-btn" title="Làm lại">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 10h-10a8 8 0 00-8 8v2M21 10l-6 6m6-6l-6-6"/></svg>
                </button>
            </div>

            <!-- Text Formatting -->
            <div class="flex gap-1 mr-2 border-r border-gray-300 pr-2">
                <button @click="execCommand('bold')" :class="{ 'is-active': isActive('bold') }" class="toolbar-btn font-bold" title="In đậm">B</button>
                <button @click="execCommand('italic')" :class="{ 'is-active': isActive('italic') }" class="toolbar-btn italic" title="In nghiêng">I</button>
                <button @click="execCommand('underline')" :class="{ 'is-active': isActive('underline') }" class="toolbar-btn underline" title="Gạch chân">U</button>
            </div>

            <!-- Headings -->
            <div class="flex gap-1 mr-2 border-r border-gray-300 pr-2">
                <button @click="execCommand('formatBlock', 'h1')" class="toolbar-btn font-bold" title="Tiêu đề 1">H1</button>
                <button @click="execCommand('formatBlock', 'h2')" class="toolbar-btn font-bold" title="Tiêu đề 2">H2</button>
                <button @click="execCommand('formatBlock', 'h3')" class="toolbar-btn font-bold" title="Tiêu đề 3">H3</button>
                <button @click="execCommand('formatBlock', 'p')" class="toolbar-btn" title="Đoạn văn">P</button>
            </div>

            <!-- Alignment -->
            <div class="flex gap-1 mr-2 border-r border-gray-300 pr-2">
                <button @click="execCommand('justifyLeft')" class="toolbar-btn" title="Căn trái">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h10M4 18h16"/></svg>
                </button>
                <button @click="execCommand('justifyCenter')" class="toolbar-btn" title="Căn giữa">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M7 12h10M4 18h16"/></svg>
                </button>
                <button @click="execCommand('justifyRight')" class="toolbar-btn" title="Căn phải">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M10 12h10M4 18h16"/></svg>
                </button>
                <button @click="execCommand('justifyFull')" class="toolbar-btn" title="Căn đều">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>

            <!-- Lists -->
            <div class="flex gap-1">
                <button @click="execCommand('insertUnorderedList')" class="toolbar-btn" title="Danh sách">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16M4 6h.01M4 12h.01M4 18h.01"/></svg>
                </button>
                <button @click="execCommand('insertOrderedList')" class="toolbar-btn" title="Danh sách số">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h12M7 12h12M7 17h12M3 7h.01M3 12h.01M3 17h.01"/></svg>
                </button>
            </div>
        </div>

        <!-- Editor Content -->
        <div 
            ref="editorRef"
            class="flex-1 overflow-y-auto p-4 docx-content focus:outline-none"
            contenteditable="true"
            @input="handleInput"
            @keydown="handleKeydown"
        ></div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['update:modelValue']);

const editorRef = ref(null);
const activeFormats = ref(new Set());

// Initialize content
onMounted(() => {
    if (editorRef.value && props.modelValue) {
        editorRef.value.innerHTML = props.modelValue;
    }
});

// Watch for external changes
watch(() => props.modelValue, (newValue) => {
    if (editorRef.value && editorRef.value.innerHTML !== newValue) {
        const selection = saveSelection();
        editorRef.value.innerHTML = newValue;
        nextTick(() => {
            restoreSelection(selection);
        });
    }
});

// Handle input
const handleInput = () => {
    if (editorRef.value) {
        emit('update:modelValue', editorRef.value.innerHTML);
    }
    updateActiveFormats();
};

// Handle keyboard shortcuts
const handleKeydown = (e) => {
    // Ctrl+B for bold
    if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
        e.preventDefault();
        execCommand('bold');
    }
    // Ctrl+I for italic
    if ((e.ctrlKey || e.metaKey) && e.key === 'i') {
        e.preventDefault();
        execCommand('italic');
    }
    // Ctrl+U for underline
    if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
        e.preventDefault();
        execCommand('underline');
    }
};

// Execute command
const execCommand = (command, value = null) => {
    document.execCommand(command, false, value);
    editorRef.value?.focus();
    updateActiveFormats();
    handleInput();
};

// Check if format is active
const isActive = (format) => {
    return activeFormats.value.has(format);
};

// Update active formats
const updateActiveFormats = () => {
    activeFormats.value = new Set();
    if (document.queryCommandState('bold')) activeFormats.value.add('bold');
    if (document.queryCommandState('italic')) activeFormats.value.add('italic');
    if (document.queryCommandState('underline')) activeFormats.value.add('underline');
};

// Save selection
const saveSelection = () => {
    const selection = window.getSelection();
    if (selection.rangeCount > 0) {
        return selection.getRangeAt(0);
    }
    return null;
};

// Restore selection
const restoreSelection = (range) => {
    if (range) {
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
    }
};
</script>

<style scoped>
.toolbar-btn {
    padding: 0.375rem;
    border-radius: 0.25rem;
    color: rgb(55 65 81);
    transition-property: color, background-color, border-color, text-decoration-color, fill, stroke;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 150ms;
}

.toolbar-btn:hover {
    background-color: rgb(229 231 235);
}

.toolbar-btn.is-active {
    background-color: rgb(219 234 254);
    color: rgb(37 99 235);
}

.toolbar-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.toolbar-btn:disabled:hover {
    background-color: transparent;
}

/* Custom styles for editor content to match document preview */
.docx-content {
    font-family: 'Times New Roman', serif;
    line-height: 1.6;
    color: #333;
    min-height: 400px;
}

.docx-content:focus {
    outline: 2px solid #3b82f6;
    outline-offset: -2px;
}
</style>
