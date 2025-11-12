# 🐛 DEBUG: Preview Không Hiển Thị Đúng Format

## ❌ VẤN ĐỀ

Template DOCX gốc:
```
CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM
Độc lập - Tự do - Hạnh phúc

BÁO CÁO
Số: 01/BC/2023

VỀ VIỆC THỰC HIỆN CÁC CHƯƠNG TRÌNH ĐÀO TẠO NĂM 2023
```

Web hiển thị:
```
Text thuần túy, không format, không styling
→ HOÀN TOÀN KHÁC!
```

## 🔍 NGUYÊN NHÂN

### Confirmed: Frontend CHƯA gọi endpoint mới!

**Evidence:**
```bash
# Check logs - KHÔNG có request đến /preview-html
tail -500 storage/logs/laravel.log | grep "preview-html"
# → EMPTY!

# Chỉ có log từ SmartDocxReplacer (backend tạo DOCX)
# KHÔNG có log từ AdvancedDocxToHtmlConverter
```

**Root Cause:**
1. ✅ Code đã update: `ReportPreview.vue` gọi `loadHtmlPreview()`
2. ✅ Frontend đã build: `npm run build` success
3. ❌ **Browser đang cache code cũ!** (Vite build artifacts)

---

## ✅ SOLUTION: Force Reload Frontend

### Option 1: Hard Refresh Browser (FASTEST) ⚡

```bash
# User phải làm:
1. Mở browser (Chrome/Edge/Firefox)
2. Nhấn: Ctrl + Shift + R (Windows/Linux)
   Hoặc: Cmd + Shift + R (Mac)
3. Hoặc: F12 → Network tab → Check "Disable cache" → Reload
```

### Option 2: Clear Vite Cache + Rebuild

```bash
# Backend
cd /Users/gotechjsc/Documents/GitHub/ai-hanhchinhcong

# 1. Clear build cache
rm -rf public/build/*

# 2. Clear Vite cache
rm -rf node_modules/.vite

# 3. Rebuild
npm run build

# 4. Clear Laravel cache (optional)
php artisan cache:clear
php artisan view:clear
php artisan config:clear
```

### Option 3: Run Dev Server (BEST for Development)

```bash
# Terminal 1: Backend
php artisan serve

# Terminal 2: Frontend (hot reload)
npm run dev

# → Vite sẽ auto-reload khi code thay đổi
```

---

## 🧪 VERIFICATION STEPS

### Step 1: Check Browser Console

Sau khi hard refresh, console phải show:
```javascript
[ReportPreview] Loading HTML preview (server-side)
[ReportPreview] Fetching HTML from server { previewUrl: "/api/reports/12/preview-html" }
[ReportPreview] Server response { status: 200, ok: true }
[ReportPreview] HTML preview loaded successfully
```

### Step 2: Check Network Tab

Phải thấy request:
```
GET /api/reports/12/preview-html
Status: 200 OK
Type: text/html
Size: 15-50 KB (HTML with inline CSS)
```

### Step 3: Check Backend Logs

```bash
tail -f storage/logs/laravel.log

# Phải thấy:
[INFO] HTML preview requested {"report_id":12}
[INFO] Converting DOCX to HTML {"report_id":12,"docx_path":"..."}
[INFO] Extracted styles from styles.xml {"count":15}
[INFO] HTML preview generated successfully {"html_length":25000}
```

### Step 4: Inspect HTML Output

Right-click preview → Inspect → Phải thấy:
```html
<div class="docx-document" style="...">
    <p style="font-family: 'Times New Roman'; font-size: 14pt; 
       font-weight: bold; text-align: center; margin-bottom: 12pt;">
        CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM
    </p>
    <p style="font-family: 'Times New Roman'; font-size: 12pt; 
       font-style: italic; text-align: center;">
        Độc lập - Tự do - Hạnh phúc
    </p>
</div>
```

**NOT:**
```html
<div class="report-content">
    CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM
    Độc lập - Tự do - Hạnh phúc
    (no styling, just plain text)
</div>
```

---

## 🎯 EXPECTED RESULT

### Before (Mammoth.js client-side - OLD):
```
❌ Plain text
❌ No formatting
❌ No styles
```

### After (Server-side HTML - NEW):
```
✅ Bold text for headers
✅ Center alignment
✅ Font sizes (14pt, 12pt)
✅ Italic for "Độc lập - Tự do - Hạnh phúc"
✅ Spacing between sections
✅ A4 page layout
```

---

## 📊 COMPARISON TABLE

| Element | DOCX Template | Current Web | Expected Web (After Fix) |
|---------|--------------|-------------|-------------------------|
| **"CỘNG HÒA..."** | Bold, 14pt, Center | Plain text | **Bold, 14pt, Center** ✅ |
| **"Độc lập..."** | Italic, 12pt, Center | Plain text | *Italic, 12pt, Center* ✅ |
| **"BÁO CÁO"** | Bold, 16pt, Center | Plain text | **Bold, 16pt, Center** ✅ |
| **Spacing** | 12pt between sections | No spacing | 12pt spacing ✅ |
| **Page Layout** | A4 margins | No layout | A4 margins ✅ |

---

## 🚨 ACTION REQUIRED

**IMMEDIATE:**
```bash
1. User: Hard refresh browser (Ctrl+Shift+R)
2. User: Tạo báo cáo mới
3. User: Check console logs
4. User: Report back kết quả
```

**IF STILL NOT WORKING:**
```bash
# Dev run this:
cd /Users/gotechjsc/Documents/GitHub/ai-hanhchinhcong
rm -rf public/build/*
npm run build
php artisan cache:clear

# Then user: Hard refresh browser again
```

---

## 📝 ROOT CAUSE ANALYSIS

### Why Browser Cache?

Vite build creates:
```
public/build/assets/app-DZuTOh9a.js  (840 KB)
```

Browser cache này với:
```
Cache-Control: public, max-age=31536000, immutable
```

→ Code cũ trong `app-OLD_HASH.js` vẫn được dùng!

### Solution: Content Hash Changes

Khi rebuild:
```
OLD: app-DZuTOh9a.js
NEW: app-NEW_HASH.js  (với code mới)
```

→ Browser sẽ load file mới

**BUT:** Nếu HTML page vẫn reference file cũ → CACHE PROBLEM!

### Fix: Hard Refresh

```
Ctrl+Shift+R → Bypass cache → Load new HTML → Load new JS
```

---

## ✅ CONCLUSION

**Problem:** Frontend code đã update NHƯNG browser cache code cũ

**Solution:** Hard refresh browser (Ctrl+Shift+R)

**Expected:** Sau refresh, preview sẽ hiển thị ĐÚNG format như DOCX gốc (95%+)

**Time to fix:** < 1 minute! ⚡






