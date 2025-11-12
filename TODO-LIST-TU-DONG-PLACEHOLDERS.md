# 📋 TODO LIST: Tự Động Tạo Placeholders Từ Template

**Mục tiêu:** Implement tính năng tự động tạo placeholders từ template DOCX khi admin upload

**Phương pháp:** XML Manipulation (như `SmartDocxReplacer`)

---

## 🎯 TỔNG QUAN

### Phạm Vi
- ✅ **Backend Admin:** Tạo service và integrate vào AdminController
- ✅ **Frontend Admin:** Thông báo về auto-generate placeholders (optional)
- ✅ **Backend User:** Không cần sửa (đã có logic xử lý placeholders)
- ✅ **Frontend User:** Không cần sửa (đã có DocumentPreview component)

---

## 📦 BACKEND - ADMIN

### Phase 1: Tạo Service TemplatePlaceholderGenerator

#### ✅ Task 1.1: Tạo Service Class

**File:** `app/Services/TemplatePlaceholderGenerator.php` (NEW)

**Tasks:**
- [ ] Tạo class `TemplatePlaceholderGenerator`
- [ ] Inject `DocumentProcessor` vào constructor
- [ ] Implement method `generatePlaceholders(string $templatePath): array`
- [ ] Implement method `extractExistingPlaceholders(string $templatePath): array`
- [ ] Implement method `analyzeStructure(string $templatePath): array`
- [ ] Implement method `identifyFillablePositionsWithAI(string $text, array $structure): array`
- [ ] Implement method `buildAIPrompt(string $text): string`
- [ ] Implement method `modifyDocxWithPlaceholders(string $templatePath, array $mappings): string`
- [ ] Implement method `addPlaceholdersToXml(string $xml, array $mappings): string`
- [ ] Implement method `simpleReplaceInXml(string $xml, array $mappings): string` (fallback)
- [ ] Implement method `replaceTextInNodes(array &$nodeMap, int $position, int $length, string $replacement): void`
- [ ] Implement method `getModifiedPath(string $originalPath): string`
- [ ] Add comprehensive logging
- [ ] Add error handling và fallback

**Dependencies:**
- `DocumentProcessor` (đã có)
- `ZipArchive` (PHP built-in)
- `DOMDocument` (PHP built-in)
- `DOMXPath` (PHP built-in)
- `OpenAI` (đã có)

**Estimated Time:** 4-6 hours

---

#### ✅ Task 1.2: Implement AI Prompt

**File:** `app/Services/TemplatePlaceholderGenerator.php`

**Tasks:**
- [ ] Design AI prompt để nhận diện fillable positions
- [ ] Include examples trong prompt
- [ ] Specify placeholder format (`${key}`)
- [ ] Specify placeholder naming rules (lowercase, underscore, no special chars)
- [ ] Add instructions để ignore static text
- [ ] Test prompt với nhiều template khác nhau
- [ ] Refine prompt dựa trên test results

**Estimated Time:** 2-3 hours

---

#### ✅ Task 1.3: Implement XML Manipulation

**File:** `app/Services/TemplatePlaceholderGenerator.php`

**Tasks:**
- [ ] Study `SmartDocxReplacer` logic
- [ ] Implement XML parsing với `DOMDocument`
- [ ] Implement text node extraction với `DOMXPath`
- [ ] Implement text replacement trong XML
- [ ] Handle text split across nodes
- [ ] Preserve format (font, size, color, alignment)
- [ ] Test với complex templates (tables, nested structures)
- [ ] Add fallback cho simple string replace

**Reference:** `app/Services/SmartDocxReplacer.php`

**Estimated Time:** 3-4 hours

---

### Phase 2: Integrate Vào AdminController

#### ✅ Task 2.1: Modify processDocumentTemplates()

**File:** `app/Http/Controllers/AdminController.php`

**Location:** Method `processDocumentTemplates()` (line 227-317)

**Tasks:**
- [ ] Inject `TemplatePlaceholderGenerator` vào constructor hoặc resolve via service container
- [ ] Modify logic sau khi store file (line 265-266)
- [ ] Check xem template có placeholders không (line 276-285)
- [ ] Nếu KHÔNG có placeholders:
  - [ ] Call `TemplatePlaceholderGenerator::generatePlaceholders()`
  - [ ] Get full path của stored file
  - [ ] Pass full path vào service
  - [ ] Get generated placeholders
  - [ ] Update metadata với placeholders
  - [ ] Log kết quả
- [ ] Nếu CÓ placeholders:
  - [ ] Keep existing logic (extract và save)
- [ ] Add error handling (nếu generate fail, continue với original file)
- [ ] Add logging chi tiết

**Code Location:**
```php
// After line 266 (store file)
$fullPath = Storage::disk('public')->path($path);

// After line 276 (check if DOCX)
if ($finalExtension === 'docx') {
    try {
        // Check existing placeholders
        $placeholders = $this->extractPlaceholdersFromTemplate($fullPath);
        
        if (empty($placeholders)) {
            // ✅ NEW: Auto-generate placeholders
            $placeholderGenerator = app(\App\Services\TemplatePlaceholderGenerator::class);
            $generatedPlaceholders = $placeholderGenerator->generatePlaceholders($fullPath);
            
            if (!empty($generatedPlaceholders)) {
                $metadata['placeholders'] = array_keys($generatedPlaceholders);
                $metadata['placeholders_auto_generated'] = true;
            }
        } else {
            // Existing logic
            $metadata['placeholders'] = array_keys($placeholders);
        }
    } catch (\Exception $e) {
        // Error handling
    }
}
```

**Estimated Time:** 2-3 hours

---

#### ✅ Task 2.2: Add Logging

**File:** `app/Http/Controllers/AdminController.php`

**Tasks:**
- [ ] Add log khi bắt đầu generate placeholders
- [ ] Add log khi generate thành công
- [ ] Add log khi generate thất bại
- [ ] Add log số lượng placeholders được generate
- [ ] Add log thời gian xử lý
- [ ] Add log cho debugging (template path, mappings, etc.)

**Estimated Time:** 1 hour

---

#### ✅ Task 2.3: Add Error Handling

**File:** `app/Http/Controllers/AdminController.php`

**Tasks:**
- [ ] Handle exception khi generate placeholders fail
- [ ] Fallback về original file nếu generate fail
- [ ] Continue với other files nếu một file fail
- [ ] Log errors chi tiết
- [ ] Không throw exception (chỉ log warning)

**Estimated Time:** 1 hour

---

### Phase 3: Testing & Refinement

#### ✅ Task 3.1: Unit Tests

**File:** `tests/Unit/TemplatePlaceholderGeneratorTest.php` (NEW)

**Tasks:**
- [ ] Test `extractExistingPlaceholders()` với template có placeholders
- [ ] Test `extractExistingPlaceholders()` với template không có placeholders
- [ ] Test `identifyFillablePositionsWithAI()` với simple template
- [ ] Test `identifyFillablePositionsWithAI()` với complex template
- [ ] Test `modifyDocxWithPlaceholders()` với simple replacements
- [ ] Test `modifyDocxWithPlaceholders()` với complex replacements
- [ ] Test format preservation (font, size, color, alignment)
- [ ] Test error handling

**Estimated Time:** 3-4 hours

---

#### ✅ Task 3.2: Integration Tests

**File:** `tests/Feature/AdminTemplatePlaceholderTest.php` (NEW)

**Tasks:**
- [ ] Test upload template không có placeholders
- [ ] Test upload template có placeholders
- [ ] Test upload multiple templates
- [ ] Test error handling khi generate fail
- [ ] Test metadata được lưu đúng
- [ ] Test template file được modify đúng

**Estimated Time:** 2-3 hours

---

#### ✅ Task 3.3: Manual Testing

**Tasks:**
- [ ] Test với 10+ templates khác nhau:
  - [ ] Simple template (chỉ text)
  - [ ] Template với tables
  - [ ] Template với complex formatting
  - [ ] Template với images
  - [ ] Template với headers/footers
- [ ] Verify placeholders được tạo đúng
- [ ] Verify format preservation 100%
- [ ] Verify AI accuracy (placeholders có đúng không)
- [ ] Test performance (thời gian xử lý)
- [ ] Test với large templates (> 1MB)

**Estimated Time:** 4-6 hours

---

#### ✅ Task 3.4: Refine AI Prompt

**File:** `app/Services/TemplatePlaceholderGenerator.php`

**Tasks:**
- [ ] Analyze test results
- [ ] Identify common mistakes của AI
- [ ] Refine prompt để improve accuracy
- [ ] Add more examples vào prompt
- [ ] Add specific instructions cho edge cases
- [ ] Test lại với refined prompt

**Estimated Time:** 2-3 hours

---

## 🎨 FRONTEND - ADMIN

### Phase 4: UI/UX Improvements (Optional)

#### ✅ Task 4.1: Add Info Message

**File:** `resources/js/Pages/Admin/CreateAssistant.vue`

**Location:** Template upload section (line 110-146)

**Tasks:**
- [ ] Add info message về auto-generate placeholders
- [ ] Message: "Hệ thống sẽ tự động tạo placeholders nếu template chưa có"
- [ ] Style với blue info box
- [ ] Add icon (info icon)

**Code Location:**
```vue
<!-- After line 123 -->
<div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mt-2">
    <div class="flex items-start">
        <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        <div class="text-sm text-blue-700">
            <p class="font-medium">Tự động tạo placeholders</p>
            <p class="mt-1">Nếu template chưa có placeholders (${key}), hệ thống sẽ tự động phân tích và tạo placeholders phù hợp.</p>
        </div>
    </div>
</div>
```

**Estimated Time:** 1 hour

---

#### ✅ Task 4.2: Add Loading State

**File:** `resources/js/Pages/Admin/CreateAssistant.vue`

**Tasks:**
- [ ] Add loading state khi đang generate placeholders
- [ ] Show message: "Đang phân tích template và tạo placeholders..."
- [ ] Update upload status message
- [ ] Disable submit button khi đang process

**Estimated Time:** 1 hour

---

#### ✅ Task 4.3: Show Generated Placeholders (Optional)

**File:** `resources/js/Pages/Admin/PreviewAssistant.vue` (hoặc tạo component mới)

**Tasks:**
- [ ] Show list placeholders đã được generate
- [ ] Show indicator nếu placeholders được auto-generate
- [ ] Allow admin review và edit placeholders (future enhancement)

**Estimated Time:** 2-3 hours (optional)

---

## 🔧 BACKEND - USER

### Phase 5: User Side (No Changes Needed)

#### ✅ Task 5.1: Verify Existing Logic

**Files:**
- `app/Services/DocumentDraftingService.php`
- `app/Http/Controllers/DocumentController.php`
- `app/Services/SmartAssistantEngine.php`

**Tasks:**
- [ ] Verify `DocumentDraftingService::generateDocxFromTemplate()` hoạt động với auto-generated placeholders
- [ ] Verify `DocumentController::previewHtml()` hoạt động với modified templates
- [ ] Test end-to-end flow:
  - [ ] Admin upload template → Auto-generate placeholders
  - [ ] User request document → Use template với placeholders
  - [ ] Generate DOCX → Replace placeholders
  - [ ] Preview HTML → Show formatted document

**Estimated Time:** 1-2 hours

---

## 🎨 FRONTEND - USER

### Phase 6: User Side (No Changes Needed)

#### ✅ Task 6.1: Verify Existing Components

**Files:**
- `resources/js/Components/DocumentPreview.vue`
- `resources/js/Pages/Chat/Dashboard.vue`
- `resources/js/Pages/Chat/IndexNew.vue`

**Tasks:**
- [ ] Verify `DocumentPreview` component hoạt động với auto-generated placeholders
- [ ] Test HTML preview hiển thị đúng format
- [ ] Test download DOCX hoạt động đúng

**Estimated Time:** 1 hour

---

## 📊 TESTING CHECKLIST

### Functional Tests

- [ ] Upload template không có placeholders → Placeholders được auto-generate
- [ ] Upload template có placeholders → Giữ nguyên placeholders hiện có
- [ ] Upload multiple templates → Tất cả được xử lý đúng
- [ ] Template với tables → Placeholders được tạo đúng
- [ ] Template với complex formatting → Format được preserve 100%
- [ ] Template với images → Images được preserve
- [ ] Large template (> 1MB) → Xử lý thành công
- [ ] Error handling → Fallback về original file

### Performance Tests

- [ ] Small template (< 100KB) → < 5s
- [ ] Medium template (100KB - 500KB) → < 10s
- [ ] Large template (> 500KB) → < 15s
- [ ] Multiple templates → Parallel processing

### Accuracy Tests

- [ ] AI nhận diện đúng fillable positions
- [ ] Placeholders được tạo với naming đúng (lowercase, underscore)
- [ ] Placeholders không duplicate
- [ ] Placeholders không conflict với existing placeholders

---

## 📝 DOCUMENTATION

### Phase 7: Documentation

#### ✅ Task 7.1: Code Documentation

**Files:**
- `app/Services/TemplatePlaceholderGenerator.php`

**Tasks:**
- [ ] Add PHPDoc comments cho tất cả methods
- [ ] Add examples trong comments
- [ ] Document parameters và return types
- [ ] Document exceptions
- [ ] Document edge cases

**Estimated Time:** 1-2 hours

---

#### ✅ Task 7.2: API Documentation

**File:** `docs/API_TEMPLATE_PLACEHOLDER_GENERATOR.md` (NEW)

**Tasks:**
- [ ] Document service API
- [ ] Document usage examples
- [ ] Document configuration options
- [ ] Document error handling

**Estimated Time:** 1 hour

---

#### ✅ Task 7.3: User Guide

**File:** `docs/ADMIN_TEMPLATE_UPLOAD_GUIDE.md` (NEW)

**Tasks:**
- [ ] Hướng dẫn admin upload template
- [ ] Giải thích auto-generate placeholders
- [ ] Best practices
- [ ] Troubleshooting

**Estimated Time:** 1-2 hours

---

## 🚀 DEPLOYMENT

### Phase 8: Deployment

#### ✅ Task 8.1: Pre-deployment Checklist

**Tasks:**
- [ ] All tests passed
- [ ] Code reviewed
- [ ] Documentation complete
- [ ] Logging configured
- [ ] Error handling tested
- [ ] Performance tested
- [ ] Backup strategy (backup original templates)

**Estimated Time:** 1 hour

---

#### ✅ Task 8.2: Deployment Steps

**Tasks:**
- [ ] Deploy to staging
- [ ] Test on staging
- [ ] Deploy to production
- [ ] Monitor logs
- [ ] Monitor performance
- [ ] Monitor errors

**Estimated Time:** 2-3 hours

---

## 📊 ESTIMATED TIME SUMMARY

| Phase | Tasks | Estimated Time |
|-------|-------|----------------|
| **Phase 1:** Create Service | 3 tasks | 9-13 hours |
| **Phase 2:** Integrate AdminController | 3 tasks | 4-5 hours |
| **Phase 3:** Testing & Refinement | 4 tasks | 11-16 hours |
| **Phase 4:** Frontend Admin (Optional) | 3 tasks | 4-5 hours |
| **Phase 5:** Backend User | 1 task | 1-2 hours |
| **Phase 6:** Frontend User | 1 task | 1 hour |
| **Phase 7:** Documentation | 3 tasks | 3-5 hours |
| **Phase 8:** Deployment | 2 tasks | 3-4 hours |
| **TOTAL** | **20 tasks** | **36-51 hours** |

---

## 🎯 PRIORITY

### High Priority (Must Have)
1. ✅ Task 1.1: Tạo Service Class
2. ✅ Task 1.2: Implement AI Prompt
3. ✅ Task 1.3: Implement XML Manipulation
4. ✅ Task 2.1: Integrate vào AdminController
5. ✅ Task 3.1: Unit Tests
6. ✅ Task 3.2: Integration Tests
7. ✅ Task 3.3: Manual Testing

### Medium Priority (Should Have)
8. ✅ Task 2.2: Add Logging
9. ✅ Task 2.3: Add Error Handling
10. ✅ Task 3.4: Refine AI Prompt
11. ✅ Task 5.1: Verify Existing Logic
12. ✅ Task 6.1: Verify Existing Components

### Low Priority (Nice to Have)
13. ✅ Task 4.1: Add Info Message
14. ✅ Task 4.2: Add Loading State
15. ✅ Task 4.3: Show Generated Placeholders
16. ✅ Task 7.1: Code Documentation
17. ✅ Task 7.2: API Documentation
18. ✅ Task 7.3: User Guide

---

## 🔄 ITERATION PLAN

### Sprint 1 (Week 1): Core Implementation
- Task 1.1, 1.2, 1.3: Create Service
- Task 2.1: Integrate
- Task 2.2, 2.3: Logging & Error Handling

### Sprint 2 (Week 2): Testing & Refinement
- Task 3.1, 3.2, 3.3: Testing
- Task 3.4: Refine AI Prompt
- Task 5.1, 6.1: Verify Existing Logic

### Sprint 3 (Week 3): Polish & Deploy
- Task 4.1, 4.2: UI Improvements
- Task 7.1, 7.2, 7.3: Documentation
- Task 8.1, 8.2: Deployment

---

## 📝 NOTES

- **Backup Strategy:** Luôn backup original template trước khi modify
- **Error Handling:** Nếu generate fail, continue với original file (không throw exception)
- **Performance:** Cache AI responses nếu có thể
- **Monitoring:** Monitor AI API costs và performance
- **Future Enhancement:** Allow admin review và edit auto-generated placeholders

---

**Last Updated:** 2025-11-09  
**Status:** 📋 Planning



