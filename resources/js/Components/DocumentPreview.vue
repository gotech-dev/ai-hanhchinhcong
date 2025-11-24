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
        
        <!-- ✅ FIX: Tách 2 div riêng để tránh v-html re-render khi edit -->
        <!-- View mode: Dùng v-html -->
        <div 
            v-if="!isEditMode && docxPreviewHtml && !isGenerating" 
            class="document-content docx-preview"
            v-html="docxPreviewHtml"
        ></div>
        
        <!-- Edit mode: Không dùng v-html, chỉ set innerHTML một lần -->
        <div 
            v-if="isEditMode"
            ref="editableContent"
            class="document-content docx-preview edit-mode"
            contenteditable="true"
            @input="onHtmlEdit"
        ></div>
        
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
import { ref, computed, onMounted, nextTick } from 'vue';
import { marked } from 'marked';

const props = defineProps({
    documentContent: String,
    messageId: [Number, String], // Message ID containing document metadata
    documentData: Object, // Document data from message metadata
});

const docxPreviewHtml = ref('');
const isGenerating = ref(false);
const isEditMode = ref(false);
const isSaving = ref(false);
const editableContent = ref(null);
const originalHtml = ref(''); // Store original HTML before editing

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
        // Call API để download file
        const response = await fetch(`/api/documents/${normalizedMessageId.value}/download?format=${format}`, {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                'Accept': format === 'docx' 
                    ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    : 'application/pdf',
            },
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
        }
    } else {
        // Enter edit mode - save original HTML
        originalHtml.value = docxPreviewHtml.value;
        isEditMode.value = true;
        
        // ✅ FIX: Set innerHTML trực tiếp (không dùng v-html để tránh re-render)
        nextTick(() => {
            if (editableContent.value) {
                editableContent.value.innerHTML = originalHtml.value;
                editableContent.value.focus();
                
                // Set cursor to end of content
                const range = document.createRange();
                const selection = window.getSelection();
                range.selectNodeContents(editableContent.value);
                range.collapse(false); // false = collapse to end
                selection.removeAllRanges();
                selection.addRange(range);
            }
        });
    }
};

// ✅ MỚI: Handle HTML edit input
// ✅ FIX: Không update docxPreviewHtml.value để tránh Vue re-render và mất cursor position
const onHtmlEdit = (event) => {
    // Chỉ log để debug, không update reactive value
    // HTML sẽ được lấy từ element khi save
    console.log('[DocumentPreview] HTML edited', {
        length: event.target.innerHTML.length,
    });
};

// ✅ MỚI: Save edited HTML
const saveEditedHtml = async () => {
    if (!normalizedMessageId.value) {
        alert('Không tìm thấy ID message. Vui lòng thử lại sau.');
        return;
    }
    
    if (!editableContent.value) {
        alert('Không tìm thấy nội dung để lưu.');
        return;
    }
    
    isSaving.value = true;
    
    try {
        // ✅ FIX: Lấy HTML từ element (không từ reactive value)
        const editedHtml = editableContent.value.innerHTML;
        
        // Call API to save edited HTML
        const response = await fetch(`/api/documents/${normalizedMessageId.value}/html-preview`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                html_preview: editedHtml
            }),
        });
        
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || 'Failed to save HTML preview');
        }
        
        // ✅ FIX: Update reactive values sau khi save thành công
        originalHtml.value = editedHtml;
        docxPreviewHtml.value = editedHtml;
        
        // Exit edit mode
        isEditMode.value = false;
        
        alert('HTML đã được lưu thành công!');
        
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
}

.docx-preview.edit-mode:focus {
    outline: 2px solid #2563eb;
    background: white;
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
    /* ✅ FIX: Preserve font và spacing từ template */
    font-family: 'Times New Roman', serif !important;
    font-size: 13pt !important;
    line-height: 1.5 !important;
}

/* ✅ FIX: Preserve paragraph spacing từ template */
.docx-preview :deep(p) {
    margin: 0.5em 0 !important;
    /* ✅ FIX: KHÔNG force text-align, preserve từ inline style của DOCX */
    font-family: 'Times New Roman', serif !important;
    font-size: 13pt !important;
    line-height: 1.5 !important;
    white-space: normal !important; /* ✅ FIX: Normal whitespace, each <p> is a new line */
    word-wrap: break-word !important; /* ✅ FIX: Break long words */
    overflow-wrap: break-word !important; /* ✅ FIX: Break long words */
    display: block !important; /* ✅ FIX: Ensure each paragraph is on a new line */
    page-break-inside: avoid !important; /* ✅ FIX: Avoid breaking paragraphs */
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

