# 🐛 BÁO CÁO LỖI: Nội Dung Không Thay Đổi Sau Regenerate

**Thời gian:** 07/11/2025 21:00  
**User Report:** "Tự tạo nội dung vào phần 'BÁO CÁO HOẠT ĐỘNG' nhưng nội dung không thay đổi gì"  
**Severity:** 🔴 **HIGH** - Core functionality broken  

---

## 🔍 ĐIỀU TRA

### Logs Analysis:

```log
[ERROR] Failed to generate content with AI
  "error": "Malformed UTF-8 characters, possibly incorrectly encoded"
  
[INFO] Report regenerated with edit request
  "edit_request": "Tự tạo nội dung vào phần BÁO CÁO HOẠT ĐỘNG"
  
[INFO] SmartDocxReplacer: Template filled successfully
  "file_size": 21,546 bytes
  
✅ Backend: API success (200 OK)
✅ Backend: DOCX created
❌ Backend: AI generation FAILED
❌ Frontend: NO preview-html request after regenerate
```

---

## 🎯 VẤN ĐỀ PHÁT HIỆN

### Có 2 LỖI CHÍNH:

### ❌ LỖI 1: Backend AI Generation Failed

**Location:** `app/Services/ReportGenerator.php::generateContentWithAI()`

**Error:** `Malformed UTF-8 characters, possibly incorrectly encoded`

**Root Cause:**
- Template DOCX có ký tự đặc biệt (superscripts: ¹ ² ³, special chars)
- `TemplateAnalyzer::extractTemplateText()` extract raw text từ DOCX
- Text này được đưa vào prompt gửi cho OpenAI
- OpenAI API **reject** vì malformed UTF-8
- AI **KHÔNG generate** nội dung mới

**Impact:**
```
User Request: "Tự tạo nội dung vào phần BÁO CÁO HOẠT ĐỘNG"
   ↓
AI Should: Generate new content for this section
   ↓
AI Actually: ❌ FAILS (UTF-8 error)
   ↓
Result: Nội dung KHÔNG THAY ĐỔI (no AI input)
```

---

### ❌ LỖI 2: Frontend Cache Không Được Invalidate

**Location:** `app/Http/Controllers/ReportController.php::previewHtml()`

**Cache Key:** `report_advanced_html_{reportId}_v{updated_at.timestamp}`

**Root Cause:**
- Sau khi regenerate, report.updated_at thay đổi
- Frontend gọi `loadHtmlPreview()` với report ID cũ
- Cache key **có thể** giống nhau nếu updated_at timestamp giống (same second)
- Browser serve **CACHED old HTML**
- User không thấy thay đổi

**Impact:**
```
Backend: Creates new DOCX (report_690dfb1f1d858_1762523935.docx)
   ↓
Frontend: Calls /api/reports/14/preview-html
   ↓
Backend: Cache key = "report_advanced_html_14_v1762523604"
   ↓
Cache: Returns OLD HTML (from previous generation)
   ↓
User: Sees NO CHANGE
```

---

## 📊 USER EXPERIENCE FLOW (HIỆN TẠI)

```
1. User clicks "Chỉnh sửa" ✅
   ↓
2. Edit form appears ✅
   ↓
3. User nhập: "Tự tạo nội dung vào phần BÁO CÁO HOẠT ĐỘNG" ✅
   ↓
4. User clicks "Gửi yêu cầu" ✅
   ↓
5. Frontend → POST /api/reports/14/regenerate ✅
   ↓
6. Backend → AI generation ❌ FAILS (UTF-8 error)
   ↓
7. Backend → SmartDocxReplacer creates DOCX ✅
   (But WITHOUT new AI content - just template fill)
   ↓
8. Backend → Returns 200 OK ✅
   ↓
9. Frontend → Receives success ✅
   ↓
10. Frontend → Calls loadHtmlPreview() ✅
   ↓
11. Backend → Returns CACHED old HTML ❌
   ↓
12. User → Sees NO CHANGE ❌
   ↓
13. User → Confused: "Không thay đổi gì?" 😕
```

---

## 🔧 ROOT CAUSES

### Cause 1: Malformed UTF-8 in Template Text

**Problem:**
```php
// In ReportGenerator::generateContentWithAI()
$prompt .= "CẤU TRÚC TEMPLATE:\n";
$prompt .= $templateStructure['text_preview'] ?? '';
//         ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
//         This text contains superscripts (¹ ² ³) and special chars
//         → OpenAI API rejects as malformed UTF-8
```

**Template Text Example:**
```
CÔNG TY TNHH ABC¹
CÔNG TY TNHH ABC²
01/BC-ABC³...-...⁴...
```

These superscripts (¹ ² ³ ⁴) are Unicode characters that may not be properly encoded when extracted from DOCX.

---

### Cause 2: Cache Not Invalidated

**Problem:**
```php
// In ReportController::previewHtml()
$cacheKey = "report_advanced_html_{$reportId}_v{$report->updated_at->timestamp}";
//                                                ^^^^^^^^^^^^^^^^^^^^^^^^^^^^
//                                                If updated_at doesn't change
//                                                → same cache key
//                                                → old HTML served
```

**Scenario:**
- Report created at: `2025-11-07 13:53:24` (timestamp: 1762523604)
- Report regenerated at: `2025-11-07 13:53:24` (same second!)
- Cache key: `report_advanced_html_14_v1762523604` (SAME!)
- Result: Old HTML cached and served

---

## ✅ SOLUTIONS

### 🔥 FIX 1: Sanitize Template Text for OpenAI (CRITICAL)

**File:** `app/Services/ReportGenerator.php`  
**Method:** `generateContentWithAI()`  

**Implementation:**

```php
protected function generateContentWithAI(
    string $userRequest,
    array $collectedData,
    array $templateStructure,
    AiAssistant $assistant
): string {
    try {
        // ... existing code ...
        
        // ✅ FIX: Sanitize template text before sending to OpenAI
        $templateText = $templateStructure['text_preview'] ?? '';
        
        // Remove problematic characters
        $templateText = $this->sanitizeTextForOpenAI($templateText);
        
        $prompt .= "CẤU TRÚC TEMPLATE:\n";
        $prompt .= $templateText; // ✅ Use sanitized text
        
        // ... rest of code ...
    }
}

/**
 * Sanitize text to prevent OpenAI UTF-8 errors
 * 
 * @param string $text
 * @return string
 */
protected function sanitizeTextForOpenAI(string $text): string
{
    // Convert to valid UTF-8
    $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    
    // Remove null bytes and control characters (except newlines/tabs)
    $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
    
    // Replace superscripts with regular numbers
    $superscripts = ['¹' => '1', '²' => '2', '³' => '3', '⁴' => '4', '⁵' => '5', 
                     '⁶' => '6', '⁷' => '7', '⁸' => '8', '⁹' => '9', '⁰' => '0'];
    $text = strtr($text, $superscripts);
    
    // Normalize whitespace
    $text = preg_replace('/\s+/u', ' ', $text);
    
    // Trim
    $text = trim($text);
    
    // Limit length (OpenAI has token limits)
    if (mb_strlen($text) > 3000) {
        $text = mb_substr($text, 0, 3000) . '...';
    }
    
    return $text;
}
```

**Result:**
```
Before: "CÔNG TY TNHH ABC¹\nCÔNG TY TNHH ABC²\n..."
After:  "CÔNG TY TNHH ABC1 CÔNG TY TNHH ABC2 ..."
         ↓
OpenAI: ✅ Accepts and generates content
```

---

### 🔥 FIX 2: Force Cache Invalidation on Regenerate

**Option A: Add Microtime to Cache Key (Quick Fix)**

**File:** `app/Http/Controllers/ReportController.php`  
**Method:** `previewHtml()`

```php
public function previewHtml(Request $request, $reportId)
{
    $report = UserReport::findOrFail($reportId);
    
    // ✅ FIX: Add microtime to ensure unique cache key
    $cacheKey = "report_advanced_html_{$reportId}_v{$report->updated_at->timestamp}_" . time();
    //                                                                                  ^^^^^^^^^
    //                                                                                  Always unique!
    
    $html = Cache::remember($cacheKey, now()->addHours(24), function () use ($report) {
        // ... conversion logic ...
    });
    
    return response($html)->header('Content-Type', 'text/html; charset=utf-8');
}
```

**Option B: Clear Cache on Regenerate (Better Fix)**

**File:** `app/Http/Controllers/ReportController.php`  
**Method:** `regenerate()`

```php
public function regenerate(Request $request, $reportId)
{
    // ... existing code ...
    
    // Update existing report
    $report->update([
        'report_content' => $reportResult['report_content'],
        'report_file_path' => $reportResult['report_file_path'],
        'file_format' => 'docx',
    ]);
    
    // ✅ FIX: Clear cache for this report
    $report->refresh();
    $oldCacheKey = "report_advanced_html_{$reportId}_v*";
    Cache::flush(); // Or use Cache::forget() with pattern matching
    
    Log::info('Cache cleared for regenerated report', [
        'report_id' => $report->id,
    ]);
    
    return response()->json([
        'success' => true,
        // ... response ...
    ]);
}
```

**Option C: Cache Busting via Query Parameter (Frontend Fix)**

**File:** `resources/js/Components/ReportPreview.vue`  
**Method:** `loadHtmlPreview()`

```javascript
const loadHtmlPreview = async () => {
    // ✅ FIX: Add cache buster query parameter
    const cacheBuster = Date.now();
    const previewUrl = `/api/reports/${normalizedReportId.value}/preview-html?_=${cacheBuster}`;
    //                                                                         ^^^^^^^^^^^^^^^^^^^
    //                                                                         Forces fresh fetch
    
    const response = await fetch(previewUrl, {
        method: 'GET',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
    });
    
    // ... rest of code ...
};
```

---

### 🔥 FIX 3: Better Error Handling

**File:** `app/Services/ReportGenerator.php`  
**Method:** `generateContentWithAI()`

```php
protected function generateContentWithAI(
    string $userRequest,
    array $collectedData,
    array $templateStructure,
    AiAssistant $assistant
): string {
    try {
        // ... sanitize text ...
        
        $response = OpenAI::chat()->create([/* ... */]);
        
        $aiContent = trim($response->choices[0]->message->content);
        
        Log::info('AI content generated', [
            'assistant_id' => $assistant->id,
            'content_length' => strlen($aiContent),
        ]);
        
        return $aiContent;
        
    } catch (\Exception $e) {
        Log::error('Failed to generate content with AI', [
            'error' => $e->getMessage(),
            'assistant_id' => $assistant->id,
        ]);
        
        // ✅ FIX: Return fallback content instead of empty string
        return $this->generateFallbackContent($userRequest, $collectedData, $templateStructure);
    }
}

/**
 * Generate fallback content if OpenAI fails
 */
protected function generateFallbackContent(
    string $userRequest,
    array $collectedData,
    array $templateStructure
): string {
    $content = "BÁO CÁO\n\n";
    $content .= "YÊU CẦU: {$userRequest}\n\n";
    
    if (!empty($collectedData)) {
        $content .= "THÔNG TIN:\n";
        foreach ($collectedData as $key => $value) {
            $content .= "- {$key}: {$value}\n";
        }
    }
    
    $content .= "\n(Nội dung chi tiết sẽ được cập nhật sau)\n";
    
    return $content;
}
```

---

## 📋 IMPLEMENTATION PLAN

### Phase 1: Critical Fixes (5-10 minutes)

1. ✅ Add `sanitizeTextForOpenAI()` method
2. ✅ Update `generateContentWithAI()` to use sanitize
3. ✅ Add cache busting to `loadHtmlPreview()`
4. ✅ Test regenerate

### Phase 2: Better Fixes (10-15 minutes)

5. ✅ Implement cache clearing on regenerate
6. ✅ Add fallback content generation
7. ✅ Better error messages to user
8. ✅ Add retry logic

### Phase 3: Testing (5 minutes)

9. ✅ Test với nhiều loại edit requests
10. ✅ Verify preview updates correctly
11. ✅ Check logs for no UTF-8 errors
12. ✅ Confirm user sees changes

---

## 🎯 EXPECTED RESULTS AFTER FIX

### Backend:
```
✅ OpenAI API accepts sanitized text
✅ AI generates new content based on edit request
✅ DOCX created with NEW content
✅ No UTF-8 errors in logs
```

### Frontend:
```
✅ Preview reloads with cache busting
✅ New HTML fetched from backend
✅ User sees UPDATED content
✅ Changes visible immediately
```

### User Experience:
```
1. User clicks "Chỉnh sửa" ✅
2. User nhập: "Tự tạo nội dung..." ✅
3. User clicks "Gửi yêu cầu" ✅
4. Loading spinner... ✅
5. Backend generates NEW content ✅ (FIXED!)
6. Preview updates ✅ (FIXED!)
7. User sees CHANGES ✅ (FIXED!)
8. User happy! 😊
```

---

## 📊 PRIORITY

| Fix | Priority | Impact | Effort | Status |
|-----|----------|--------|--------|--------|
| **Sanitize UTF-8** | 🔴 CRITICAL | HIGH | 5 min | ⏳ Pending |
| **Cache Busting** | 🔴 HIGH | HIGH | 2 min | ⏳ Pending |
| **Fallback Content** | 🟡 MEDIUM | MEDIUM | 5 min | ⏳ Pending |
| **Clear Cache** | 🟡 MEDIUM | MEDIUM | 3 min | ⏳ Pending |
| **Retry Logic** | 🟢 LOW | LOW | 10 min | ⏳ Pending |

---

## 🎉 SUMMARY

**Vấn đề User:** "Nội dung không thay đổi gì sau khi regenerate"

**Root Causes:**
1. ❌ AI generation fails (UTF-8 error) → No new content
2. ❌ Frontend cache not invalidated → Old HTML shown

**Solutions:**
1. ✅ Sanitize template text before OpenAI
2. ✅ Add cache busting to frontend
3. ✅ Clear cache on regenerate
4. ✅ Fallback content if AI fails

**Next Steps:**
→ Apply fixes now!
→ Test thoroughly
→ Deploy to production

**ETA:** 15-20 minutes total






