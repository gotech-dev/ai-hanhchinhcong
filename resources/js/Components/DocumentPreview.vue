<template>
    <div class="document-preview bg-white border border-gray-200 rounded-lg shadow-sm p-6 my-4">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900">📄 Văn Bản</h3>
            <div class="flex gap-2">
                <!-- ✅ MỚI: Edit HTML button -->
                <button
                    v-if="normalizedMessageId && docxPreviewHtml"
                    @click="toggleEditMode"
                    :disabled="isGenerating || isSaving"
                    class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 text-sm font-medium flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm hover:shadow-md"
                    :title="isEditMode ? 'Thoát chế độ chỉnh sửa' : 'Chỉnh sửa HTML trực tiếp trên web'"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    {{ isEditMode ? 'Thoát' : 'Sửa' }}
                </button>
                
                <!-- Save button (only show in edit mode) -->
                <button
                    v-if="isEditMode"
                    @click="saveEditedHtml"
                    :disabled="isSaving"
                    class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm font-medium flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm hover:shadow-md"
                    title="Lưu HTML đã chỉnh sửa"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    {{ isSaving ? 'Đang lưu...' : 'Lưu' }}
                </button>
                
                <!-- Download DOCX button -->
                <button
                    v-if="normalizedMessageId"
                    @click="downloadDocument('docx')"
                    :disabled="isGenerating"
                    class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 text-sm font-medium flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors shadow-sm hover:shadow-md"
                    title="Tải văn bản dạng DOCX"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Tải DOCX
                </button>
            </div>
        </div>
        
        <!-- ✅ Hint hiển thị bên trên khung preview HTML (luôn hiển thị khi có preview) -->
        <div v-if="docxPreviewHtml && !isGenerating" class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg flex items-center gap-2">
            <svg class="w-5 h-5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span class="text-sm text-blue-700">
                <strong>Hướng dẫn:</strong> Bấm nút <strong>"Sửa"</strong> để chỉnh sửa. Sau đó <strong>bôi đen đoạn văn</strong> bạn muốn sửa và <strong>bấm chuột phải</strong> để mở menu AI (Viết lại, Tóm tắt, Mở rộng, Sửa lỗi)
            </span>
        </div>
        
        <!-- ✅ FIX: Tách 2 div riêng để tránh v-html re-render khi edit -->
        <!-- View mode: Dùng v-html -->
        <div 
            v-if="!isEditMode && docxPreviewHtml && !isGenerating" 
            class="document-content docx-preview"
            v-html="docxPreviewHtml"
        ></div>
        
        <!-- Edit mode: Use contenteditable with AI Context Menu -->
        <div v-if="isEditMode" class="document-content relative">
            <div 
                ref="editorRef"
                class="docx-preview edit-mode min-h-[400px]"
                contenteditable="true"
                @contextmenu="handleContextMenu"
                @input="handleEditorInput"
            ></div>
            
            <!-- AI Context Menu Component -->
            <AiContextMenu 
                ref="contextMenuRef"
                @action-complete="handleActionComplete"
            />
        </div>
        
        <!-- Fallback: Hiển thị markdown với styling đẹp hơn nếu chưa có DOCX -->
        <div v-else-if="!isGenerating && documentContent" class="document-content markdown-fallback" v-html="formattedContent"></div>
        
        <!-- Loading state -->
        <div v-else-if="isGenerating" class="document-content loading-state">
            <p class="text-gray-500">Đang tạo văn bản...</p>
        </div>
        
        <div v-if="isGenerating" class="mt-4 text-center text-gray-500">
            <div class="flex items-center justify-center gap-2">
                <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Đang tạo file... Vui lòng đợi</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { marked } from 'marked';
import RichTextEditor from './RichTextEditor.vue';
import AiContextMenu from './AiContextMenu.vue';

const props = defineProps({
    documentContent: String,
    messageId: [Number, String], // Message ID containing document metadata
    documentData: Object, // Document data from message metadata
});

const docxPreviewHtml = ref('');
const isGenerating = ref(false);
const isEditMode = ref(false);
const isSaving = ref(false);
const editedHtml = ref(''); // HTML being edited in RichTextEditor
const originalHtml = ref(''); // Store original HTML before editing
const editorRef = ref(null); // Reference to contenteditable div
const contextMenuRef = ref(null); // Reference to AiContextMenu component

// Normalize messageId to ensure it's always available
const normalizedMessageId = computed(() => {
    if (props.messageId) {
        const numId = Number(props.messageId);
        return isNaN(numId) ? null : numId;
    }
    
    return null;
});

const formattedContent = computed(() => {
    if (!props.documentContent) return '';
    
    marked.use({
        breaks: true,
        gfm: true,
    });
    
    return marked.parse(props.documentContent);
});

/**
 * Load HTML preview from server (95%+ format preservation)
 * Server-side HTML generation with advanced DOCX converter
 */
const loadHtmlPreview = async () => {
    if (!normalizedMessageId.value) {
        console.warn('[DocumentPreview] Cannot load preview: messageId is missing', {
            messageId: props.messageId,
            documentData: props.documentData,
        });
        return;
    }
    
    console.log('[DocumentPreview] Loading HTML preview (server-side)', {
        messageId: normalizedMessageId.value,
        documentData: props.documentData,
    });
    
    try {
        // ✅ Use server-side HTML generation (95%+ format preservation)
        // ✅ FIX: Add cache-busting to prevent browser cache
        const cacheBuster = Date.now();
        const previewUrl = `/api/documents/${normalizedMessageId.value}/preview-html?_=${cacheBuster}`;
        console.log('[DocumentPreview] Fetching HTML from server', { previewUrl });
        
        const response = await fetch(previewUrl, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache',
            },
            cache: 'no-store',
        });
        
        console.log('[DocumentPreview] Server response', {
            status: response.status,
            statusText: response.statusText,
            ok: response.ok,
            contentType: response.headers.get('content-type'),
        });
        
        if (!response.ok) {
            throw new Error(`Failed to fetch HTML preview: ${response.statusText}`);
        }
        
        const html = await response.text();
        // ✅ FIX: Extract CSS từ HTML và apply riêng (preserve CSS từ Pandoc)
        const styleMatch = html.match(/<style[^>]*>([\s\S]*?)<\/style>/i);
        if (styleMatch) {
            const styleContent = styleMatch[1];
            // ✅ FIX: Apply CSS vào component thay vì xóa
            const styleElement = document.createElement('style');
            styleElement.textContent = styleContent;
            styleElement.id = 'pandoc-styles';
            // ✅ FIX: Remove old style nếu có
            const oldStyle = document.getElementById('pandoc-styles');
            if (oldStyle) {
                oldStyle.remove();
            }
            document.head.appendChild(styleElement);
            console.log('[DocumentPreview] Applied CSS from Pandoc', {
                cssLength: styleContent.length,
                preview: styleContent.substring(0, 200),
            });
        }
        
        // ✅ FIX: Count <p> tags AFTER removing style tags to get accurate count
        const htmlWithoutStyle = html.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '');
        const actualPTagCount = (htmlWithoutStyle.match(/<p[^>]*>/gi) || []).length;
        console.log('[DocumentPreview] Received HTML', {
            size: html.length,
            preview: html.substring(0, 200),
            pTagCountInFullHtml: (html.match(/<p[^>]*>/gi) || []).length,
            pTagCountAfterRemovingStyle: actualPTagCount,
        });
        
        // ✅ FIX: Remove style tag và header tag từ HTML (CSS đã apply vào <head> rồi)
        // Extract content directly from HTML string (don't use DOMParser to avoid splitting <p> tags)
        let cleanedHtml = html;
        
        // ✅ FIX: Count style and header tags before removal
        const styleTagMatches = cleanedHtml.match(/<style[^>]*>[\s\S]*?<\/style>/gi);
        const headerTagMatches = cleanedHtml.match(/<header[^>]*>[\s\S]*?<\/header>/gi);
        const styleTagCount = styleTagMatches ? styleTagMatches.length : 0;
        const headerTagCount = headerTagMatches ? headerTagMatches.length : 0;
        const pTagCountBefore = (cleanedHtml.match(/<p[^>]*>/gi) || []).length;
        
        // ✅ FIX: Remove style tags using regex (CSS đã apply vào <head> rồi)
        cleanedHtml = cleanedHtml.replace(/<style[^>]*>[\s\S]*?<\/style>/gi, '');
        
        // ✅ FIX: Remove header tags using regex
        cleanedHtml = cleanedHtml.replace(/<header[^>]*>[\s\S]*?<\/header>/gi, '');
        
        // ✅ FIX: Clean up any remaining whitespace
        cleanedHtml = cleanedHtml.trim();
        
        const pTagCountAfter = (cleanedHtml.match(/<p[^>]*>/gi) || []).length;
        
        console.log('[DocumentPreview] Removed style and header tags', {
            removedStyleCount: styleTagCount,
            removedHeaderCount: headerTagCount,
            cleanedSize: cleanedHtml.length,
            pTagCountBefore: pTagCountBefore,
            pTagCountAfter: pTagCountAfter,
            sample: cleanedHtml.substring(0, 500),
        });
        
        // Set cleaned HTML (style tags and header tags removed to prevent CSS override)
        docxPreviewHtml.value = cleanedHtml;
        // Save original HTML for edit mode
        originalHtml.value = cleanedHtml;
        
        // ✅ LOG: After setting v-html, check actual DOM
        setTimeout(() => {
            const docxPreview = document.querySelector('.docx-preview');
            if (docxPreview) {
                const ps = docxPreview.querySelectorAll('p');
                const first10Ps = Array.from(ps).slice(0, 10).map((p, index) => {
                    const computedStyle = window.getComputedStyle(p);
                    return {
                        index: index + 1,
                        text: p.textContent.trim(),
                        length: p.textContent.trim().length,
                        html: p.outerHTML.substring(0, 150),
                        styles: {
                            fontFamily: computedStyle.fontFamily,
                            fontSize: computedStyle.fontSize,
                            textAlign: computedStyle.textAlign,
                            marginTop: computedStyle.marginTop,
                            marginBottom: computedStyle.marginBottom,
                            lineHeight: computedStyle.lineHeight,
                        },
                    };
                });
                
                console.log('🔵 [DocumentPreview] After v-html render - DETAILED', {
                    messageId: normalizedMessageId.value,
                    pTagCountInDOM: ps.length,
                    first10Paragraphs: first10Ps,
                    totalSpans: docxPreview.querySelectorAll('span').length,
                    totalSups: docxPreview.querySelectorAll('sup').length,
                    totalSubs: docxPreview.querySelectorAll('sub').length,
                });
            }
        }, 100);
        
        // ✅ LOG: Final summary
        console.log('✅ [DocumentPreview] HTML preview loaded successfully', {
            messageId: normalizedMessageId.value,
            htmlLength: html.length,
            cleanedHtmlLength: cleanedHtml.length,
            styleTagCount: styleTagCount,
            headerTagCount: headerTagCount,
            pTagCountBefore: pTagCountBefore,
            pTagCountAfter: pTagCountAfter,
        });
        
    } catch (error) {
        console.error('[DocumentPreview] Failed to load HTML preview:', error, {
            messageId: normalizedMessageId.value,
            documentData: props.documentData,
            errorMessage: error.message,
            errorStack: error.stack,
        });
        // Fallback to markdown
        docxPreviewHtml.value = '';
    }
};

const downloadDocument = async (format) => {
    // Check if messageId is available
    if (!normalizedMessageId.value) {
        alert('Không tìm thấy ID message. Vui lòng thử lại sau.');
        return;
    }
    
    isGenerating.value = true;
    
    try {
        // ✅ FIX: Add cache-busting to prevent downloading old file
        const cacheBuster = Date.now();
        
        // Call API để download file
        const response = await fetch(`/api/documents/${normalizedMessageId.value}/download?format=${format}&_=${cacheBuster}`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': format === 'docx' 
                    ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    : 'application/pdf',
                'Cache-Control': 'no-cache',
                'Pragma': 'no-cache',
            },
            cache: 'no-store',
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || `Failed to download ${format.toUpperCase()}`);
        }
        
        // Get filename from Content-Disposition header
        const contentDisposition = response.headers.get('Content-Disposition');
        let filename = contentDisposition
            ? contentDisposition.split('filename=')[1]?.replace(/"/g, '')
            : `document_${normalizedMessageId.value}.${format}`;
        
        // Ensure filename has correct extension
        if (!filename.endsWith(`.${format}`)) {
            filename = `document_${normalizedMessageId.value}.${format}`;
        }
        
        // Create blob and download
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
    } catch (error) {
        console.error('Failed to download:', error);
        alert(`Không thể tải file ${format.toUpperCase()}. ${error.message || 'Vui lòng thử lại.'}`);
    } finally {
        isGenerating.value = false;
    }
};

// ✅ MỚI: Toggle edit mode
const toggleEditMode = () => {
    if (isEditMode.value) {
        // Exit edit mode - restore original HTML
        if (confirm('Bạn có muốn hủy các thay đổi chưa lưu?')) {
            isEditMode.value = false;
            editedHtml.value = '';
        }
    } else {
        // Enter edit mode - save original HTML and initialize editor
        originalHtml.value = docxPreviewHtml.value;
        editedHtml.value = docxPreviewHtml.value;
        isEditMode.value = true;
        
        // ✅ FIX: Set innerHTML trực tiếp (không dùng v-html để tránh re-render)
        nextTick(() => {
            if (editorRef.value) {
                editorRef.value.innerHTML = editedHtml.value;
                editorRef.value.focus();
                
                // Set cursor to end of content
                const range = document.createRange();
                const selection = window.getSelection();
                range.selectNodeContents(editorRef.value);
                range.collapse(false); // false = collapse to end
                selection.removeAllRanges();
                selection.addRange(range);
            }
        });
    }
};



// ✅ MỚI: Handle context menu (right-click)
const handleContextMenu = (event) => {
    const selection = window.getSelection();
    const selectedText = selection.toString().trim();
    
    if (selectedText && selectedText.length > 0) {
        event.preventDefault();
        event.stopPropagation();
        
        // Get the range for later replacement
        const range = selection.rangeCount > 0 ? selection.getRangeAt(0).cloneRange() : null;
        
        // Show context menu
        contextMenuRef.value?.showContextMenu(event, selectedText, range);
    }
};

// ✅ MỚI: Handle editor input (contenteditable change)
// ✅ FIX: Không update editedHtml.value ngay lập tức để tránh v-html re-render
// Chỉ update khi cần (save, blur, etc.)
const handleEditorInput = () => {
    // ✅ FIX: Không update editedHtml.value ở đây để tránh v-html re-render
    // editedHtml.value chỉ dùng để lưu khi save
    // Content được lưu trực tiếp từ editorRef.value.innerHTML khi cần
};

// ✅ MỚI: Handle AI action complete (rewrite, summarize, etc.)
const handleActionComplete = ({ originalText, newText, range }) => {
    console.log('🔵 [DEBUG] handleActionComplete START', {
        originalTextLength: originalText?.length,
        newTextLength: newText?.length,
        hasRange: !!range,
        hasEditorRef: !!editorRef.value,
    });
    
    if (!range || !editorRef.value) {
        console.warn('🔴 [DEBUG] Missing range or editorRef');
        alert('Không thể thay thế văn bản: Thiếu range hoặc editorRef');
        return;
    }
    
    try {
        // Clone range trước khi thao tác
        const workingRange = range.cloneRange();
        
        // ✅ FIX: Ưu tiên tìm <p> element (paragraph) vì CSS chỉ áp dụng cho p
        let styleElement = workingRange.commonAncestorContainer;
        let paragraphElement = null;
        const styleElementPath = [];
        
        // Walk up the DOM tree - ƯU TIÊN tìm <p> trước
        while (styleElement && styleElement !== editorRef.value) {
            if (styleElement.nodeType === Node.ELEMENT_NODE) {
                const tagName = styleElement.tagName;
                styleElementPath.push({
                    tag: tagName,
                    nodeType: styleElement.nodeType,
                    hasInlineStyle: !!styleElement.style && styleElement.style.length > 0,
                });
                
                // ✅ FIX: Ưu tiên tìm <p> element
                if (tagName === 'P') {
                    paragraphElement = styleElement;
                    break;
                }
            }
            styleElement = styleElement.parentElement;
        }
        
        // Nếu không tìm thấy <p>, tìm từ startContainer
        if (!paragraphElement) {
            let current = workingRange.startContainer;
            while (current && current !== editorRef.value) {
                if (current.nodeType === Node.TEXT_NODE && current.parentElement) {
                    current = current.parentElement;
                }
                if (current.nodeType === Node.ELEMENT_NODE && current.tagName === 'P') {
                    paragraphElement = current;
                    break;
                }
                if (current.parentElement) {
                    current = current.parentElement;
                } else {
                    break;
                }
            }
        }
        
        // ✅ FIX: Nếu vẫn không có <p>, tìm block element gần nhất
        if (!paragraphElement) {
            styleElement = workingRange.commonAncestorContainer;
            while (styleElement && styleElement !== editorRef.value) {
                if (styleElement.nodeType === Node.ELEMENT_NODE) {
                    const tagName = styleElement.tagName;
                    if (tagName === 'DIV' || tagName === 'H1' || tagName === 'H2' || 
                        tagName === 'H3' || tagName === 'H4' || tagName === 'H5' || tagName === 'H6') {
                        paragraphElement = styleElement;
                        break;
                    }
                }
                styleElement = styleElement.parentElement;
            }
        }
        
        // Fallback cuối cùng
        if (!paragraphElement) {
            const startContainer = workingRange.startContainer;
            paragraphElement = startContainer.nodeType === Node.TEXT_NODE 
                ? startContainer.parentElement 
                : (startContainer.nodeType === Node.ELEMENT_NODE ? startContainer : startContainer.parentElement);
        }
        
        if (!paragraphElement) {
            throw new Error('Cannot find paragraph or block element');
        }
        
        styleElement = paragraphElement; // Use paragraphElement as styleElement
        
        console.log('🔵 [DEBUG] Style element found', {
            tag: styleElement.tagName,
            className: styleElement.className,
            id: styleElement.id,
            path: styleElementPath,
        });
        
        // Get computed styles from the style element
        const computedStyle = window.getComputedStyle(styleElement);
        const inlineStyle = styleElement.style;
        
        // Log ALL style information
        const styleInfo = {
            // Inline styles (from DOCX template)
            inline: {
                fontFamily: inlineStyle.fontFamily || '(none)',
                fontSize: inlineStyle.fontSize || '(none)',
                fontStyle: inlineStyle.fontStyle || '(none)',
                fontWeight: inlineStyle.fontWeight || '(none)',
                color: inlineStyle.color || '(none)',
                lineHeight: inlineStyle.lineHeight || '(none)',
                textAlign: inlineStyle.textAlign || '(none)',
            },
            // Computed styles (after CSS applied)
            computed: {
                fontFamily: computedStyle.fontFamily,
                fontSize: computedStyle.fontSize,
                fontStyle: computedStyle.fontStyle,
                fontWeight: computedStyle.fontWeight,
                color: computedStyle.color,
                lineHeight: computedStyle.lineHeight,
                textAlign: computedStyle.textAlign,
                whiteSpace: computedStyle.whiteSpace,
                wordWrap: computedStyle.wordWrap,
                overflowWrap: computedStyle.overflowWrap,
                maxWidth: computedStyle.maxWidth,
                width: computedStyle.width,
            },
        };
        
        console.log('🔵 [DEBUG] Style information BEFORE insert', styleInfo);
        
        // ✅ FIX: Save original font-size from P element (from inline style or computed)
        // Ưu tiên inline style (từ DOCX template), fallback về computed style
        const originalFontSize = inlineStyle.fontSize || computedStyle.fontSize;
        const originalFontFamily = inlineStyle.fontFamily || computedStyle.fontFamily;
        const originalLineHeight = inlineStyle.lineHeight || computedStyle.lineHeight;
        const originalColor = inlineStyle.color || computedStyle.color;
        const originalFontWeight = inlineStyle.fontWeight || computedStyle.fontWeight;
        const originalFontStyle = inlineStyle.fontStyle || computedStyle.fontStyle;
        
        console.log('🔵 [DEBUG] Original styles to preserve', {
            fontSize: originalFontSize,
            fontFamily: originalFontFamily,
            lineHeight: originalLineHeight,
            color: originalColor,
            fontWeight: originalFontWeight,
            fontStyle: originalFontStyle,
        });
        
        // Save insertion point BEFORE delete
        const startContainer = workingRange.startContainer;
        const startOffset = workingRange.startOffset;
        
        console.log('🔵 [DEBUG] Before deleteContents', {
            startContainerType: startContainer.nodeType,
            startContainerText: startContainer.nodeType === Node.TEXT_NODE ? startContainer.textContent?.substring(0, 50) : startContainer.tagName,
            startOffset: startOffset,
            selectedText: workingRange.toString().substring(0, 100),
        });
        
        // ✅ FIX: Tìm P element TRƯỚC KHI deleteContents để giữ reference
        let targetP = null;
        if (styleElement && styleElement.tagName === 'P') {
            targetP = styleElement;
        } else {
            // Tìm P từ startContainer
            let current = startContainer;
            if (current.nodeType === Node.TEXT_NODE) {
                current = current.parentElement;
            }
            
            while (current && current !== editorRef.value) {
                if (current.tagName === 'P') {
                    targetP = current;
                    break;
                }
                current = current.parentElement;
            }
        }
        
        // ✅ FIX: Tìm text node trong P và vị trí insert TRƯỚC KHI delete
        let insertTextNode = null;
        let insertOffset = 0;
        
        if (targetP && targetP.tagName === 'P') {
            // Tìm text node chứa startContainer
            if (startContainer.nodeType === Node.TEXT_NODE && targetP.contains(startContainer)) {
                insertTextNode = startContainer;
                insertOffset = startOffset;
            } else {
                // Tìm text node đầu tiên trong P
                const textNodesInP = Array.from(targetP.childNodes).filter(node => node.nodeType === Node.TEXT_NODE);
                if (textNodesInP.length > 0) {
                    insertTextNode = textNodesInP[0];
                    insertOffset = 0;
                }
            }
        }
        
        console.log('🔵 [DEBUG] Target P and insert position BEFORE delete', {
            foundP: !!targetP,
            targetPTag: targetP?.tagName,
            insertTextNode: !!insertTextNode,
            insertOffset: insertOffset,
        });
        
        // Delete selected content
        workingRange.deleteContents();
        
        // ✅ FIX: Insert vào P element đã tìm được
        let finalInsertRange = document.createRange();
        
        if (targetP && targetP.tagName === 'P') {
            // Nếu text node vẫn còn (không bị xóa hết), insert vào đó
            if (insertTextNode && insertTextNode.parentElement === targetP) {
                // Text node còn tồn tại, insert vào vị trí đã tính
                finalInsertRange.setStart(insertTextNode, Math.min(insertOffset, insertTextNode.textContent.length));
                finalInsertRange.collapse(true);
            } else {
                // Text node đã bị xóa hoặc không tìm thấy, tìm text node mới trong P
                const textNodesInP = Array.from(targetP.childNodes).filter(node => node.nodeType === Node.TEXT_NODE);
                if (textNodesInP.length > 0) {
                    // Insert vào cuối text node cuối cùng
                    const lastTextNode = textNodesInP[textNodesInP.length - 1];
                    finalInsertRange.setStart(lastTextNode, lastTextNode.textContent.length);
                    finalInsertRange.collapse(true);
                } else {
                    // P không có text node, insert vào đầu P
                    finalInsertRange.setStart(targetP, 0);
                    finalInsertRange.collapse(true);
                }
            }
        } else {
            console.warn('🔴 [DEBUG] Cannot find P element, using fallback');
            // Fallback: dùng range sau deleteContents
            try {
                finalInsertRange.setStart(workingRange.startContainer, workingRange.startOffset);
                finalInsertRange.collapse(true);
            } catch (e) {
                if (startContainer && startContainer.parentElement) {
                    const parent = startContainer.nodeType === Node.TEXT_NODE 
                        ? startContainer.parentElement 
                        : startContainer;
                    finalInsertRange.setStart(parent, 0);
                    finalInsertRange.collapse(true);
                } else {
                    throw new Error('Cannot create insertion range');
                }
            }
        }
        
        console.log('🔵 [DEBUG] Final insert range', {
            startContainerType: finalInsertRange.startContainer.nodeType,
            startContainerTag: finalInsertRange.startContainer.nodeType === Node.ELEMENT_NODE 
                ? finalInsertRange.startContainer.tagName 
                : 'TEXT',
            startOffset: finalInsertRange.startOffset,
            parentElement: finalInsertRange.startContainer.nodeType === Node.TEXT_NODE 
                ? finalInsertRange.startContainer.parentElement?.tagName 
                : finalInsertRange.startContainer.tagName,
        });
        
        // ✅ FIX: Check if text contains newlines (\n) and convert to <br> tags
        const hasNewlines = newText.includes('\n');
        let insertedNode = null;
        
        console.log('🔵 [DEBUG] Inserting text', {
            hasNewlines,
            textLength: newText.length,
            textPreview: newText.substring(0, 100),
            newlineCount: (newText.match(/\n/g) || []).length,
        });
        
        if (hasNewlines) {
            // Text has line breaks → need to insert HTML with <br> tags
            // Create a temporary container to hold the HTML
            const tempContainer = document.createElement('span');
            
            // Split text by newlines and filter out empty lines
            const lines = newText.split('\n').filter(line => line.trim().length > 0);
            
            console.log('🔵 [DEBUG] Processing lines', {
                originalLineCount: newText.split('\n').length,
                filteredLineCount: lines.length,
                emptyLinesRemoved: newText.split('\n').length - lines.length,
            });
            
            lines.forEach((line, index) => {
                if (index > 0) {
                    // Add <br> before each line except the first
                    tempContainer.appendChild(document.createElement('br'));
                }
                // Line is already filtered, so it's guaranteed to have content
                tempContainer.appendChild(document.createTextNode(line));
            });
            
            // Insert all children of temp container
            const fragment = document.createDocumentFragment();
            while (tempContainer.firstChild) {
                fragment.appendChild(tempContainer.firstChild);
            }
            
            finalInsertRange.insertNode(fragment);
            insertedNode = finalInsertRange.startContainer; // Reference to insertion point
            
            console.log('🔵 [DEBUG] Inserted HTML with <br> tags', {
                lineCount: lines.length,
                brCount: lines.length - 1,
            });
        } else {
            // No newlines → insert as simple text node
            const textNode = document.createTextNode(newText);
            finalInsertRange.insertNode(textNode);
            insertedNode = textNode;
        }
        
        console.log('🔵 [DEBUG] Content inserted', {
            hasNewlines,
            parentElement: insertedNode?.parentElement?.tagName || (finalInsertRange.startContainer.nodeType === Node.ELEMENT_NODE ? finalInsertRange.startContainer.tagName : 'TEXT'),
            targetParagraphTag: styleElement.tagName,
        });
        
        // ✅ FIX: Ensure P has proper width constraint and word-wrap
        // Get parent element (from insertedNode or from range)
        let parentP = null;
        if (insertedNode && insertedNode.parentElement) {
            parentP = insertedNode.parentElement;
        } else if (finalInsertRange.startContainer.nodeType === Node.ELEMENT_NODE) {
            parentP = finalInsertRange.startContainer;
        } else if (finalInsertRange.startContainer.parentElement) {
            parentP = finalInsertRange.startContainer.parentElement;
        }
        
        // Walk up to find P element
        while (parentP && parentP.tagName !== 'P' && parentP !== editorRef.value) {
            parentP = parentP.parentElement;
        }
        if (parentP && parentP.tagName === 'P') {
            // Get computed styles to check current state
            const computed = window.getComputedStyle(parentP);
            
            // Check if P has width constraint
            const hasWidthConstraint = computed.maxWidth !== 'none' && computed.maxWidth !== '0px';
            const parentWidth = parentP.offsetWidth;
            const scrollWidth = parentP.scrollWidth;
            
            console.log('🔵 [DEBUG] P width check', {
                hasWidthConstraint,
                maxWidth: computed.maxWidth,
                width: computed.width,
                offsetWidth: parentWidth,
                scrollWidth: scrollWidth,
                isOverflowing: scrollWidth > parentWidth,
            });
            
            // If P doesn't have proper width constraint, ensure it inherits from parent
            // The CSS already has max-width: 100% !important, so this should work
            // But we can force it via inline style if needed (without !important)
            if (!hasWidthConstraint || scrollWidth > parentWidth) {
                // Ensure P inherits width from parent container
                const parentContainer = parentP.parentElement;
                if (parentContainer) {
                    const containerWidth = parentContainer.offsetWidth;
                    console.log('🔵 [DEBUG] Parent container width', {
                        containerTag: parentContainer.tagName,
                        containerWidth: containerWidth,
                    });
                }
            }
        }
        
        // Get computed styles AFTER insert to verify
        // Use parentP found earlier (works for both text node and fragment insert)
        const parentAfterInsert = parentP;
        if (parentAfterInsert && parentAfterInsert.tagName === 'P') {
            const computedAfterInsert = window.getComputedStyle(parentAfterInsert);
            console.log('🔵 [DEBUG] Parent styles AFTER insert', {
                parentTag: parentAfterInsert.tagName,
                fontFamily: computedAfterInsert.fontFamily,
                fontSize: computedAfterInsert.fontSize,
                fontStyle: computedAfterInsert.fontStyle,
                fontWeight: computedAfterInsert.fontWeight,
                color: computedAfterInsert.color,
                lineHeight: computedAfterInsert.lineHeight,
                whiteSpace: computedAfterInsert.whiteSpace,
                wordWrap: computedAfterInsert.wordWrap,
                overflowWrap: computedAfterInsert.overflowWrap,
                wordBreak: computedAfterInsert.wordBreak,
                maxWidth: computedAfterInsert.maxWidth,
                width: computedAfterInsert.width,
                display: computedAfterInsert.display,
                boxSizing: computedAfterInsert.boxSizing,
            });
            
            // ✅ DEBUG: Check if P has inline style that might override CSS
            const inlineStyleText = parentAfterInsert.getAttribute('style') || '';
            console.log('🔵 [DEBUG] P inline styles', {
                hasInlineStyle: parentAfterInsert.style.length > 0,
                inlineStyleText: inlineStyleText,
                // Check critical properties that might cause no-wrap
                inlineWhiteSpace: parentAfterInsert.style.whiteSpace || '(not set)',
                inlineWordWrap: parentAfterInsert.style.wordWrap || '(not set)',
                inlineOverflowWrap: parentAfterInsert.style.overflowWrap || '(not set)',
            });
            
            // ✅ FIX: Check if inline style contains white-space or word-wrap that prevents wrapping
            // And force override it
            if (inlineStyleText.includes('white-space') || 
                inlineStyleText.includes('word-wrap') || 
                inlineStyleText.includes('overflow-wrap') ||
                inlineStyleText.includes('nowrap')) {
                console.log('🔴 [DEBUG] Found problematic inline style, overriding...');
            }
            
            // ✅ FIX: Preserve original font styles from P element
            // Apply original font-size, font-family, line-height, color, etc.
            if (originalFontSize && originalFontSize !== '(none)') {
                parentAfterInsert.style.fontSize = originalFontSize;
            }
            if (originalFontFamily && originalFontFamily !== '(none)') {
                parentAfterInsert.style.fontFamily = originalFontFamily;
            }
            if (originalLineHeight && originalLineHeight !== '(none)') {
                parentAfterInsert.style.lineHeight = originalLineHeight;
            }
            if (originalColor && originalColor !== '(none)') {
                parentAfterInsert.style.color = originalColor;
            }
            if (originalFontWeight && originalFontWeight !== '(none)') {
                parentAfterInsert.style.fontWeight = originalFontWeight;
            }
            if (originalFontStyle && originalFontStyle !== '(none)') {
                parentAfterInsert.style.fontStyle = originalFontStyle;
            }
            
            // ✅ FIX: Force apply word-wrap to P via inline style (override any existing inline styles)
            parentAfterInsert.style.whiteSpace = 'normal';
            parentAfterInsert.style.wordWrap = 'break-word';
            parentAfterInsert.style.overflowWrap = 'break-word';
            parentAfterInsert.style.wordBreak = 'break-word';
            parentAfterInsert.style.width = '100%';
            parentAfterInsert.style.maxWidth = '100%';
            parentAfterInsert.style.boxSizing = 'border-box';
            
            console.log('🔵 [DEBUG] Applied inline styles to P', {
                preservedFontSize: parentAfterInsert.style.fontSize,
                preservedFontFamily: parentAfterInsert.style.fontFamily,
                preservedLineHeight: parentAfterInsert.style.lineHeight,
                newWhiteSpace: parentAfterInsert.style.whiteSpace,
                newWordWrap: parentAfterInsert.style.wordWrap,
                newWidth: parentAfterInsert.style.width,
            });
            
            // ✅ DEBUG: Check P's computed width and content
            console.log('🔵 [DEBUG] P dimensions and text', {
                pWidth: parentAfterInsert.offsetWidth,
                pScrollWidth: parentAfterInsert.scrollWidth,
                newTextLength: newText.length,
                newTextPreview: newText.substring(0, 100),
            });
            
            // ✅ DEBUG: Check DOM hierarchy to find width constraints
            let element = parentAfterInsert;
            const hierarchy = [];
            while (element && element !== document.body) {
                const computed = window.getComputedStyle(element);
                hierarchy.push({
                    tag: element.tagName,
                    className: element.className?.substring(0, 50),
                    offsetWidth: element.offsetWidth,
                    scrollWidth: element.scrollWidth,
                    computedWidth: computed.width,
                    computedMaxWidth: computed.maxWidth,
                    overflow: computed.overflow,
                    overflowX: computed.overflowX,
                });
                element = element.parentElement;
            }
            console.log('🔵 [DEBUG] DOM hierarchy (P → body)', hierarchy);
        }
        
        // Set cursor after inserted content
        try {
            if (insertedNode && insertedNode.nodeType === Node.TEXT_NODE) {
                finalInsertRange.setStartAfter(insertedNode);
            } else if (parentP) {
                // For fragment insert, set cursor at end of parent P
                finalInsertRange.selectNodeContents(parentP);
                finalInsertRange.collapse(false); // collapse to end
            }
            finalInsertRange.collapse(true);
            
            // Clear selection and restore cursor
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(finalInsertRange);
        } catch (cursorError) {
            console.warn('🔴 [DEBUG] Failed to set cursor, ignoring', cursorError);
        }
        
        console.log('✅ [DEBUG] Text replaced successfully', {
            originalLength: originalText.length,
            newLength: newText.length,
            styleElementTag: styleElement?.tagName,
        });
    } catch (error) {
        console.error('🔴 [DEBUG] Failed to replace text:', error, {
            errorMessage: error.message,
            errorStack: error.stack,
            hasRange: !!range,
            hasEditorRef: !!editorRef.value,
        });
        alert(`Không thể thay thế văn bản: ${error.message || 'Vui lòng thử lại.'}`);
    }
};

// ✅ MỚI: Save edited HTML
const saveEditedHtml = async () => {
    if (!normalizedMessageId.value) {
        alert('Không tìm thấy ID message. Vui lòng thử lại sau.');
        return;
    }
    
    if (!editorRef.value) {
        alert('Không tìm thấy editor. Vui lòng thử lại sau.');
        return;
    }
    
    isSaving.value = true;
    
    try {
        // ✅ FIX: Get HTML trực tiếp từ editorRef (không dùng editedHtml.value)
        const htmlToSave = editorRef.value.innerHTML;
        
        // Call API to save edited HTML
        const response = await fetch(`/api/documents/${normalizedMessageId.value}/html-preview`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                html_preview: htmlToSave
            }),
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || 'Failed to save HTML preview');
        }
        
        // ✅ FIX: Reload HTML preview từ server để đảm bảo sync với DOCX mới
        console.log('🔵 [saveEditedHtml] Reloading HTML preview from server after save...');
        await loadHtmlPreview();
        
        // ✅ FIX: Update reactive values sau khi save thành công
        originalHtml.value = docxPreviewHtml.value;
        
        // Exit edit mode
        isEditMode.value = false;
        
        alert('Nội dung đã được lưu và file DOCX đã được cập nhật thành công!');
        
    } catch (error) {
        console.error('Failed to save HTML:', error);
        alert(`Không thể lưu HTML. ${error.message || 'Vui lòng thử lại.'}`);
    } finally {
        isSaving.value = false;
    }
};

onMounted(async () => {
    // ✅ LOG: Component mounted
    console.log('[DocumentPreview] Component mounted', {
        messageId: props.messageId,
        normalizedMessageId: normalizedMessageId.value,
        documentData: props.documentData,
        documentContent: props.documentContent ? props.documentContent.substring(0, 100) : null,
    });
    
    // ✅ Always try to load HTML preview from server (95%+ format preservation)
    if (normalizedMessageId.value) {
        console.log('[DocumentPreview] Loading HTML preview from server');
        await loadHtmlPreview();
    } else {
        console.warn('[DocumentPreview] No messageId, cannot load preview');
    }
});
</script>

<style scoped>
/* ✅ FIX: Container giới hạn kích thước để không vỡ UI */
.document-preview {
    max-width: 100% !important;
    width: 100% !important;
    min-width: 0; /* ✅ FIX: Allow flex item to shrink below content size */
    overflow: hidden;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    /* ✅ FIX: Override Tailwind padding to reduce size */
    padding: 16px !important;
    /* ✅ FIX: Ensure container doesn't exceed parent width */
    position: relative;
}

.document-content {
    max-width: 100%;
    overflow-x: auto;
    overflow-y: visible;
}

/* ✅ FIX: Styling cho DOCX preview - Giới hạn kích thước và giữ format */
.docx-preview {
    font-family: 'Times New Roman', serif;
    line-height: 1.6;
    color: #333;
    max-width: 800px;
    width: 100%;
    margin: 0 auto;
    overflow-x: auto;
    overflow-y: visible;
    word-wrap: break-word;
    background: white;
    padding: 30px 40px;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    box-sizing: border-box;
}

/* ✅ MỚI: Edit mode styling */
.docx-preview.edit-mode {
    outline: 2px solid #3b82f6;
    outline-offset: 2px;
    background: #f8fafc;
    min-height: 200px;
    /* ✅ FIX: Đảm bảo content không overflow trong edit mode */
    overflow-x: hidden !important;
    overflow-y: auto !important;
}

.docx-preview.edit-mode:focus {
    outline: 2px solid #2563eb;
    background: white;
}

/* ✅ FIX: Force word-wrap cho P trong edit mode (override inline styles từ DOCX) */
.docx-preview.edit-mode p {
    white-space: normal !important;
    word-wrap: break-word !important;
    overflow-wrap: break-word !important;
    word-break: break-word !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
}

/* ✅ FIX: Force word-wrap cho DIV trong edit mode */
.docx-preview.edit-mode div {
    white-space: normal !important;
    word-wrap: break-word !important;
    overflow-wrap: break-word !important;
    word-break: break-word !important;
    width: 100% !important;
    max-width: 100% !important;
    box-sizing: border-box !important;
}

/* ✅ FIX: Preserve superscript/subscript formatting */
.docx-preview :deep(sup) {
    font-size: 0.7em;
    vertical-align: super;
    line-height: 0;
    position: relative;
    top: -0.4em;
}

.docx-preview :deep(sub) {
    font-size: 0.7em;
    vertical-align: sub;
    line-height: 0;
    position: relative;
    bottom: -0.25em;
}

/* ✅ FIX: Override tất cả CSS từ Pandoc - Giới hạn kích thước nhưng preserve format */
.docx-preview :deep(*) {
    max-width: 100% !important;
    box-sizing: border-box;
}

/* ✅ FIX: Override article/body từ Pandoc nếu có (chỉ override size, preserve format) */
.docx-preview :deep(article),
.docx-preview :deep(body) {
    max-width: 100% !important;
    min-height: auto !important;
    padding: 0 !important;
    margin: 0 !important;
    box-shadow: none !important;
    background: transparent !important;
    /* Default font - KHÔNG dùng !important để inline style được preserve */
    font-family: 'Times New Roman', serif;
    font-size: 13pt;
    line-height: 1.5;
}

/* ✅ FIX: Preserve paragraph spacing từ template */
/* KHÔNG dùng !important cho font-size/font-family để inline style từ DOCX được preserve */
.docx-preview :deep(p) {
    margin: 0.5em 0 !important;
    /* Default font - sẽ bị override bởi inline style từ DOCX nếu có */
    font-family: 'Times New Roman', serif;
    font-size: 13pt;
    line-height: 1.5;
    /* ✅ FIX: Đảm bảo text tự động xuống dòng - dùng break-word để break cả long words */
    white-space: normal !important;
    word-wrap: break-word !important;
    overflow-wrap: break-word !important;
    word-break: break-word !important; /* ✅ FIX: break-word thay vì normal để break long words */
    display: block !important;
    page-break-inside: avoid !important;
    max-width: 100% !important;
    width: 100% !important; /* ✅ FIX: Đảm bảo P có width constraint */
    box-sizing: border-box !important;
}

/* ✅ FIX: CSS cho DIV (khi DOCX convert ra DIV thay vì P) */
.docx-preview :deep(div) {
    /* Default font - sẽ bị override bởi inline style từ DOCX nếu có */
    font-family: 'Times New Roman', serif;
    font-size: 13pt;
    line-height: 1.5;
    /* ✅ FIX: Đảm bảo text tự động xuống dòng - dùng break-word để break cả long words */
    white-space: normal !important;
    word-wrap: break-word !important;
    overflow-wrap: break-word !important;
    word-break: break-word !important; /* ✅ FIX: break-word thay vì normal để break long words */
    max-width: 100% !important;
    width: 100% !important; /* ✅ FIX: Đảm bảo DIV có width constraint */
    box-sizing: border-box !important;
}

/* ✅ FIX: Preserve inline styles từ DOCX (alignment, etc.) */
.docx-preview :deep(p[style*="text-align"]) {
    /* Preserve alignment từ inline style */
}

/* ✅ FIX: Responsive cho mobile */
@media (max-width: 768px) {
    .docx-preview {
        padding: 20px !important;
        max-width: 100%;
    }
}

/* Preserve table formatting */
.docx-preview :deep(table) {
    width: 100%;
    max-width: 100%;
    border-collapse: collapse;
    margin: 15px 0;
    display: block;
    overflow-x: auto;
}

.docx-preview :deep(table th),
.docx-preview :deep(table td) {
    border: 1px solid #ddd;
    padding: 8px;
    text-align: left;
    min-width: 80px;
}

/* Preserve heading styles */
.docx-preview :deep(h1) {
    font-size: 18pt;
    font-weight: bold;
    margin: 20px 0;
    text-align: center;
    text-transform: uppercase;
}

.docx-preview :deep(h2) {
    font-size: 16pt;
    font-weight: bold;
    margin: 15px 0;
    text-align: center;
}

.docx-preview :deep(h3) {
    font-size: 14pt;
    font-weight: bold;
    margin: 12px 0;
}

.docx-preview :deep(h4),
.docx-preview :deep(h5),
.docx-preview :deep(h6) {
    font-size: 13pt;
    font-weight: bold;
    margin: 10px 0 5px 0;
}

/* ✅ FIX: Preserve paragraph formatting - KHÔNG override alignment từ inline style */
/* Note: Alignment được preserve từ inline style của DOCX, không cần force justify */

/* ✅ FIX: Style cho span được tạo khi rewrite - đảm bảo word-wrap và preserve style */
.docx-preview.edit-mode span {
    word-wrap: break-word !important;
    overflow-wrap: break-word !important;
    white-space: normal !important;
    display: inline !important;
    max-width: 100% !important;
}

/* Preserve list formatting */
.docx-preview :deep(ul),
.docx-preview :deep(ol) {
    margin: 10px 0;
    padding-left: 2em;
}

.docx-preview :deep(li) {
    margin: 5px 0;
    line-height: 1.5;
}

/* Preserve image formatting */
.docx-preview :deep(img) {
    max-width: 100%;
    height: auto;
    display: block;
    margin: 15px auto;
}

/* Fallback markdown styling */
.markdown-fallback {
    font-family: 'Times New Roman', serif;
    line-height: 1.8;
    color: #1a1a1a;
    font-size: 14px;
    padding: 20px;
    background: #fafafa;
    border-radius: 8px;
    max-width: 100%;
    overflow-x: auto;
}

.markdown-fallback :deep(h1) {
    font-size: 20px;
    font-weight: bold;
    text-align: center;
    text-transform: uppercase;
    margin: 20px 0 15px 0;
    color: #000;
    letter-spacing: 1px;
}

.markdown-fallback :deep(h2) {
    font-size: 16px;
    font-weight: bold;
    margin: 15px 0 10px 0;
    color: #000;
    text-transform: uppercase;
}

.markdown-fallback :deep(p) {
    margin: 10px 0;
    text-align: justify;
    line-height: 1.8;
}

/* Loading state */
.loading-state {
    padding: 20px;
    text-align: center;
    color: #666;
}
</style>

