# 🔬 CÁC PHƯƠNG PHÁP KHÁC ĐỂ MODIFY DOCX

**Mục tiêu:** So sánh các phương pháp modify DOCX file để thêm placeholders, ngoài XML manipulation trực tiếp

---

## 📊 TỔNG QUAN CÁC PHƯƠNG PHÁP

| Phương Pháp | Format Preservation | Performance | Complexity | Cost | Recommendation |
|-------------|---------------------|-------------|------------|------|----------------|
| **XML Manipulation** | ✅ 100% | ✅ Fast | ⚠️ Medium | ✅ Free | ✅ **BEST** |
| **Python-docx** | ✅ 100% | ✅ Fast | ✅ Simple | ✅ Free | ✅ **GOOD** |
| **LibreOffice Headless** | ✅ 100% | ⚠️ Slow | ⚠️ Complex | ✅ Free | ⚠️ **OK** |
| **Pandoc** | ⚠️ 90-95% | ⚠️ Medium | ✅ Simple | ✅ Free | ⚠️ **OK** |
| **Microsoft Graph API** | ✅ 100% | ✅ Fast | ✅ Simple | ❌ Paid | ❌ **NO** |
| **Node.js (docx)** | ✅ 100% | ✅ Fast | ✅ Simple | ✅ Free | ✅ **GOOD** |

---

## 1. ✅ PYTHON-DOCX (KHUYẾN NGHỊ)

### Mô Tả

**python-docx** là thư viện Python mạnh mẽ để xử lý DOCX files. Có thể modify existing DOCX files với format preservation tốt.

### Ưu Điểm

- ✅ **Format preservation 100%** (giữ nguyên format)
- ✅ **API đơn giản** và dễ sử dụng
- ✅ **Performance tốt** (nhanh hơn LibreOffice)
- ✅ **Free và open source**
- ✅ **Cộng đồng lớn** và documentation tốt
- ✅ **Có thể replace text** trong existing document

### Nhược Điểm

- ⚠️ **Cần Python** (không phải PHP native)
- ⚠️ **Cần setup Python environment**
- ⚠️ **Cần call Python script từ PHP** (exec/system call)

### Implementation

```python
# modify_docx.py
from docx import Document
import sys

def add_placeholders(template_path, output_path, mappings):
    """
    Add placeholders to DOCX template
    
    Args:
        template_path: Path to template DOCX
        output_path: Path to output DOCX
        mappings: Dict of {original_text: placeholder_key}
    """
    doc = Document(template_path)
    
    # Replace text in all paragraphs
    for paragraph in doc.paragraphs:
        for original_text, placeholder_key in mappings.items():
            if original_text in paragraph.text:
                # Replace text while preserving format
                for run in paragraph.runs:
                    if original_text in run.text:
                        run.text = run.text.replace(original_text, f"${{{placeholder_key}}}")
    
    # Replace text in tables
    for table in doc.tables:
        for row in table.rows:
            for cell in row.cells:
                for paragraph in cell.paragraphs:
                    for original_text, placeholder_key in mappings.items():
                        if original_text in paragraph.text:
                            for run in paragraph.runs:
                                if original_text in run.text:
                                    run.text = run.text.replace(original_text, f"${{{placeholder_key}}}")
    
    doc.save(output_path)
    return True

if __name__ == "__main__":
    import json
    template_path = sys.argv[1]
    output_path = sys.argv[2]
    mappings_json = sys.argv[3]
    mappings = json.loads(mappings_json)
    
    add_placeholders(template_path, output_path, mappings)
```

**Call từ PHP:**

```php
// app/Services/PythonDocxModifier.php
class PythonDocxModifier
{
    public function addPlaceholders(string $templatePath, array $mappings): string
    {
        $outputPath = $this->getOutputPath($templatePath);
        $mappingsJson = json_encode($mappings);
        
        $command = sprintf(
            'python3 %s %s %s %s',
            escapeshellarg(__DIR__ . '/../../scripts/modify_docx.py'),
            escapeshellarg($templatePath),
            escapeshellarg($outputPath),
            escapeshellarg($mappingsJson)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \Exception("Python script failed: " . implode("\n", $output));
        }
        
        return $outputPath;
    }
}
```

### Use Case

- ✅ **Tốt cho:** Production systems có Python available
- ✅ **Tốt cho:** Complex document manipulation
- ✅ **Tốt cho:** Batch processing nhiều files

---

## 2. ⚠️ LIBREOFFICE HEADLESS

### Mô Tả

LibreOffice có thể chạy headless (không có GUI) để convert và modify documents.

### Ưu Điểm

- ✅ **Format preservation 100%** (LibreOffice engine)
- ✅ **Free và open source**
- ✅ **Có thể convert** giữa nhiều formats
- ✅ **Powerful** (full LibreOffice features)

### Nhược Điểm

- ❌ **Rất chậm** (phải start LibreOffice process)
- ❌ **Resource intensive** (memory, CPU)
- ❌ **Phức tạp** để modify text (phải dùng macro/script)
- ❌ **Không có API trực tiếp** để replace text
- ❌ **Cần install LibreOffice** trên server

### Implementation

```bash
# Convert DOCX to ODT, modify, convert back
libreoffice --headless --convert-to odt template.docx
# Modify ODT (XML format)
# Convert back to DOCX
libreoffice --headless --convert-to docx template.odt
```

**Vấn đề:** Không có cách trực tiếp để replace text. Phải:
1. Convert DOCX → ODT
2. Modify ODT XML
3. Convert ODT → DOCX

**→ Phức tạp và chậm!**

### Use Case

- ⚠️ **Chỉ tốt cho:** Convert format (DOCX → PDF, etc.)
- ❌ **KHÔNG tốt cho:** Modify text trong DOCX

---

## 3. ⚠️ PANDOC

### Mô Tả

Pandoc là universal document converter. Có thể convert DOCX ↔ Markdown ↔ HTML, etc.

### Ưu Điểm

- ✅ **Simple command-line tool**
- ✅ **Free và open source**
- ✅ **Fast** (nhanh hơn LibreOffice)
- ✅ **Đã có trong codebase** (`PandocDocxToHtmlConverter`)

### Nhược Điểm

- ❌ **KHÔNG thể modify DOCX trực tiếp**
- ❌ **Chỉ convert** (DOCX → Markdown → DOCX)
- ❌ **Mất format** khi convert (90-95% preservation)
- ❌ **Không preserve** complex formatting

### Implementation

```bash
# Convert DOCX to Markdown
pandoc template.docx -o template.md

# Modify Markdown (add placeholders)
# ...

# Convert back to DOCX
pandoc template.md -o template_modified.docx
```

**Vấn đề:** 
- Mất format khi convert
- Không preserve complex structures
- **KHÔNG phù hợp** cho use case này

### Use Case

- ✅ **Tốt cho:** Convert format (DOCX → HTML, PDF, etc.)
- ❌ **KHÔNG tốt cho:** Modify DOCX với format preservation

---

## 4. ❌ MICROSOFT GRAPH API

### Mô Tả

Microsoft Graph API có thể access và modify Word documents trên OneDrive/SharePoint.

### Ưu Điểm

- ✅ **Format preservation 100%** (Microsoft engine)
- ✅ **API đơn giản** (REST API)
- ✅ **Fast** (cloud-based)
- ✅ **Official Microsoft solution**

### Nhược Điểm

- ❌ **Paid service** (Microsoft 365 subscription)
- ❌ **Cần authentication** (OAuth, Azure AD)
- ❌ **Cần upload file** lên OneDrive/SharePoint
- ❌ **Dependency** vào Microsoft services
- ❌ **Không phù hợp** cho on-premise systems

### Implementation

```php
// Microsoft Graph API
$client = new \GuzzleHttp\Client();
$response = $client->post('https://graph.microsoft.com/v1.0/me/drive/items/{item-id}/workbook/worksheets/{id}/range', [
    'headers' => [
        'Authorization' => 'Bearer ' . $accessToken,
    ],
    'json' => [
        'values' => [['${so_van_ban}']],
    ],
]);
```

**Vấn đề:** 
- Quá phức tạp cho use case đơn giản
- Cost cao
- Dependency vào cloud

### Use Case

- ✅ **Tốt cho:** Enterprise systems với Microsoft 365
- ❌ **KHÔNG tốt cho:** On-premise systems hoặc cost-sensitive projects

---

## 5. ✅ NODE.JS (DOCX LIBRARY)

### Mô Tả

Node.js có thư viện `docx` và `docx-templates` để xử lý DOCX files.

### Ưu Điểm

- ✅ **Format preservation 100%**
- ✅ **API đơn giản**
- ✅ **Fast** (Node.js performance)
- ✅ **Free và open source**
- ✅ **Có thể modify** existing documents

### Nhược Điểm

- ⚠️ **Cần Node.js** (không phải PHP native)
- ⚠️ **Cần call Node.js script** từ PHP (exec/system call)
- ⚠️ **Setup phức tạp hơn** Python

### Implementation

```javascript
// modify_docx.js
const { Document, Packer, Paragraph, TextRun } = require('docx');
const fs = require('fs');

async function addPlaceholders(templatePath, outputPath, mappings) {
    // Load DOCX
    const doc = await Document.load(fs.readFileSync(templatePath));
    
    // Modify paragraphs
    doc.sections.forEach(section => {
        section.children.forEach(child => {
            if (child instanceof Paragraph) {
                child.children.forEach(run => {
                    if (run instanceof TextRun) {
                        for (const [original, placeholder] of Object.entries(mappings)) {
                            if (run.text.includes(original)) {
                                run.text = run.text.replace(original, `\${${placeholder}}`);
                            }
                        }
                    }
                });
            }
        });
    });
    
    // Save
    const buffer = await Packer.toBuffer(doc);
    fs.writeFileSync(outputPath, buffer);
}

// Call from command line
const [templatePath, outputPath, mappingsJson] = process.argv.slice(2);
const mappings = JSON.parse(mappingsJson);
addPlaceholders(templatePath, outputPath, mappings);
```

**Call từ PHP:**

```php
// app/Services/NodeDocxModifier.php
class NodeDocxModifier
{
    public function addPlaceholders(string $templatePath, array $mappings): string
    {
        $outputPath = $this->getOutputPath($templatePath);
        $mappingsJson = json_encode($mappings);
        
        $command = sprintf(
            'node %s %s %s %s',
            escapeshellarg(__DIR__ . '/../../scripts/modify_docx.js'),
            escapeshellarg($templatePath),
            escapeshellarg($outputPath),
            escapeshellarg($mappingsJson)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new \Exception("Node script failed: " . implode("\n", $output));
        }
        
        return $outputPath;
    }
}
```

### Use Case

- ✅ **Tốt cho:** Systems đã có Node.js
- ✅ **Tốt cho:** Full-stack JavaScript applications
- ⚠️ **OK cho:** PHP applications (cần exec call)

---

## 6. ✅ XML MANIPULATION (HIỆN TẠI)

### Mô Tả

Modify DOCX XML trực tiếp bằng PHP (ZipArchive + DOMDocument).

### Ưu Điểm

- ✅ **Format preservation 100%**
- ✅ **Native PHP** (không cần external tools)
- ✅ **Fast** (chỉ modify XML)
- ✅ **Free** (built-in PHP extensions)
- ✅ **Đã được chứng minh** trong codebase (`SmartDocxReplacer`)
- ✅ **Full control** over DOCX structure

### Nhược Điểm

- ⚠️ **Complexity medium** (phải hiểu DOCX XML structure)
- ⚠️ **Cần handle edge cases** (text split across nodes)

### Implementation

Đã có trong `SmartDocxReplacer.php` - **Proven approach!**

### Use Case

- ✅ **Tốt cho:** PHP applications
- ✅ **Tốt cho:** On-premise systems
- ✅ **Tốt cho:** Production systems (đã proven)

---

## 🎯 SO SÁNH CHI TIẾT

### Format Preservation

| Method | Format Preservation | Notes |
|--------|---------------------|-------|
| XML Manipulation | ✅ 100% | Direct XML modification |
| Python-docx | ✅ 100% | Preserves all formatting |
| Node.js docx | ✅ 100% | Preserves all formatting |
| LibreOffice | ✅ 100% | Full LibreOffice engine |
| Pandoc | ⚠️ 90-95% | Loses some formatting |
| Microsoft Graph | ✅ 100% | Microsoft engine |

### Performance

| Method | Speed | Memory | Notes |
|--------|-------|--------|-------|
| XML Manipulation | ✅ Fast | ✅ Low | Only modify XML |
| Python-docx | ✅ Fast | ✅ Medium | Efficient library |
| Node.js docx | ✅ Fast | ✅ Medium | Efficient library |
| LibreOffice | ❌ Slow | ❌ High | Heavy process |
| Pandoc | ⚠️ Medium | ✅ Low | Fast converter |
| Microsoft Graph | ✅ Fast | ✅ Low | Cloud-based |

### Complexity

| Method | Setup | Code | Maintenance |
|--------|-------|------|-------------|
| XML Manipulation | ✅ Easy | ⚠️ Medium | ⚠️ Medium |
| Python-docx | ⚠️ Medium | ✅ Simple | ✅ Easy |
| Node.js docx | ⚠️ Medium | ✅ Simple | ✅ Easy |
| LibreOffice | ❌ Complex | ❌ Complex | ❌ Hard |
| Pandoc | ✅ Easy | ✅ Simple | ✅ Easy |
| Microsoft Graph | ❌ Complex | ✅ Simple | ⚠️ Medium |

### Cost

| Method | Cost | Notes |
|--------|------|-------|
| XML Manipulation | ✅ Free | Built-in PHP |
| Python-docx | ✅ Free | Open source |
| Node.js docx | ✅ Free | Open source |
| LibreOffice | ✅ Free | Open source |
| Pandoc | ✅ Free | Open source |
| Microsoft Graph | ❌ Paid | Microsoft 365 |

---

## 💡 RECOMMENDATION

### Cho PHP Application (Hiện Tại):

**✅ BEST: XML Manipulation** (như `SmartDocxReplacer`)

**Lý do:**
- ✅ Native PHP (không cần external tools)
- ✅ Format preservation 100%
- ✅ Performance tốt
- ✅ Đã được proven trong codebase
- ✅ Không có dependency

**✅ ALTERNATIVE: Python-docx** (nếu có Python available)

**Lý do:**
- ✅ API đơn giản hơn
- ✅ Format preservation 100%
- ✅ Cộng đồng lớn
- ⚠️ Cần Python environment

### Cho New Projects:

**✅ BEST: Python-docx** (nếu có thể chọn stack)

**Lý do:**
- ✅ API đơn giản nhất
- ✅ Documentation tốt nhất
- ✅ Format preservation 100%
- ✅ Performance tốt

**✅ ALTERNATIVE: Node.js docx** (nếu full-stack JS)

**Lý do:**
- ✅ API đơn giản
- ✅ Format preservation 100%
- ✅ Performance tốt
- ✅ Native JavaScript

---

## 📝 KẾT LUẬN

### Cho Use Case Hiện Tại (PHP Laravel):

**✅ RECOMMENDATION: Tiếp tục dùng XML Manipulation**

**Lý do:**
1. ✅ **Đã proven** trong codebase (`SmartDocxReplacer`)
2. ✅ **Native PHP** - không cần external dependencies
3. ✅ **Format preservation 100%**
4. ✅ **Performance tốt**
5. ✅ **Full control** over DOCX structure

### Nếu Muốn Đơn Giản Hóa:

**✅ CONSIDER: Python-docx** (nếu có thể setup Python)

**Lý do:**
1. ✅ **API đơn giản hơn** XML manipulation
2. ✅ **Documentation tốt hơn**
3. ✅ **Dễ maintain hơn**
4. ⚠️ **Cần Python environment**

---

## 🚀 NEXT STEPS

1. **Nếu tiếp tục PHP:** ✅ Dùng XML Manipulation (hiện tại)
2. **Nếu muốn đơn giản hóa:** ✅ Consider Python-docx
3. **Nếu full-stack JS:** ✅ Consider Node.js docx

**Recommendation:** ✅ **Tiếp tục XML Manipulation** vì đã proven và không cần external dependencies.



