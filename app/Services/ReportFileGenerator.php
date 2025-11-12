<?php

namespace App\Services;

use App\Models\AiAssistant;
use App\Models\UserReport;
use PhpOffice\PhpWord\TemplateProcessor;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ReportFileGenerator
{
    /**
     * Generate DOCX from template by replacing placeholders
     * 
     * ✅ QUAN TRỌNG: Chỉ xử lý cho assistant_type = 'report_generator'
     * 
     * Công nghệ: PhpOffice\PhpWord\TemplateProcessor
     * - Load template DOCX gốc
     * - Replace placeholders ({{field_name}}, ${field_name}, [field_name]) với collected data
     * - Giữ nguyên TẤT CẢ format: font, size, color, bold, italic, alignment, table, etc.
     * 
     * @param UserReport $report
     * @param AiAssistant $assistant
     * @param array $collectedData Collected data trực tiếp từ conversation
     */
    public function generateDocxFromTemplate(
        UserReport $report, 
        AiAssistant $assistant, 
        array $collectedData
    ): string {
        try {
            // ✅ QUAN TRỌNG: Verify assistant type
            if ($assistant->assistant_type !== 'report_generator') {
                throw new \Exception('ReportFileGenerator chỉ dùng cho assistant_type = report_generator');
            }
            
            // 1. Load template DOCX gốc
            $templatePath = $this->getTemplatePath($assistant->template_file_path);
            
            if (!file_exists($templatePath)) {
                throw new \Exception("Template file not found: {$templatePath}");
            }
            
            // 2. Extract placeholders từ template (hỗ trợ {{key}}, ${key}, [key])
            $templatePlaceholders = $this->extractPlaceholdersFromTemplate($templatePath);
            Log::debug('Extracted placeholders from template', [
                'report_id' => $report->id,
                'placeholders' => array_keys($templatePlaceholders),
                'count' => count($templatePlaceholders),
            ]);
            
            // ✅ FIX: Check if template has placeholders
            // If NO placeholders → Use SmartDocxReplacer (text replacement)
            // If HAS placeholders → Use TemplateProcessor (placeholder replacement)
            $hasPlaceholders = !empty($templatePlaceholders);
            
            if (!$hasPlaceholders) {
                // ✅ Use SmartDocxReplacer for templates without placeholders
                Log::info('Template has no placeholders, using SmartDocxReplacer', [
                    'report_id' => $report->id,
                    'collected_data_count' => count($collectedData),
                ]);
                
                // Parse collected data into content structure for SmartDocxReplacer
                $parsedContent = [
                    'data' => $collectedData,
                    'sections' => [],
                ];
                
                return $this->generateWithSmartReplacer(
                    $report,
                    $assistant,
                    $templatePath,
                    $parsedContent,
                    $collectedData
                );
            }
            
            // 3. Sử dụng TemplateProcessor để replace placeholders
            $templateProcessor = new TemplateProcessor($templatePath);
            
            // 4. Map collected data vào placeholders
            $data = $this->prepareDataForTemplate($collectedData);
            
            // 5. Map data với placeholders thực tế trong template
            $mappedData = $this->mapDataToTemplatePlaceholders($data, $templatePlaceholders);
            
            // 6. Replace placeholders (giữ nguyên format)
            // Support multiple formats: {{field_name}}, ${field_name}, [field_name]
            $replacedCount = 0;
            $failedCount = 0;
            $squareBracketPlaceholders = []; // Track [key] format placeholders
            
            // Use mapped data if available, otherwise use original data
            $dataToReplace = !empty($mappedData) ? $mappedData : $data;
            
            foreach ($dataToReplace as $key => $value) {
                try {
                    // TemplateProcessor sẽ giữ nguyên format của placeholder
                    // Clean value: remove markdown formatting, extra whitespace
                    $cleanValue = $this->cleanValue($value);
                    
                    // Skip empty values (but allow 0 and false)
                    if ($cleanValue === '' && $value !== '' && $value !== 0 && $value !== false) {
                        continue;
                    }
                    
                    // Check if placeholder is [key] format
                    if (preg_match('/^\[.+\]$/', $key)) {
                        // TemplateProcessor might not support [key] format
                        // Store for direct XML replacement
                        $squareBracketPlaceholders[$key] = $cleanValue;
                    } else {
                        // Try to replace placeholder using TemplateProcessor
                        $templateProcessor->setValue($key, $cleanValue);
                        $replacedCount++;
                    }
                } catch (\PhpOffice\PhpWord\Exception\Exception $e) {
                    // PhpWord specific exception - placeholder not found
                    // If it's [key] format, store for direct XML replacement
                    if (preg_match('/^\[.+\]$/', $key)) {
                        $squareBracketPlaceholders[$key] = $cleanValue;
                    } else {
                        $failedCount++;
                        Log::debug("Placeholder not found in template", [
                            'placeholder' => $key,
                            'report_id' => $report->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } catch (\Exception $e) {
                    // Other exceptions
                    $failedCount++;
                    Log::warning("Error replacing placeholder", [
                        'placeholder' => $key,
                        'report_id' => $report->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // 7. Save file temporarily first
            $tempFilePath = storage_path('app/temp/report_' . $report->id . '_' . time() . '.docx');
            $tempDirectory = dirname($tempFilePath);
            if (!is_dir($tempDirectory)) {
                mkdir($tempDirectory, 0755, true);
            }
            
            $templateProcessor->saveAs($tempFilePath);
            
            // 8. If there are [key] format placeholders, replace them directly in XML
            if (!empty($squareBracketPlaceholders)) {
                $this->replaceSquareBracketPlaceholders($tempFilePath, $squareBracketPlaceholders);
                $replacedCount += count($squareBracketPlaceholders);
            }
            
            // 9. Move to final location
            $fileName = 'reports/report_' . $report->id . '_' . time() . '.docx';
            $filePath = storage_path('app/public/' . $fileName);
            
            // Ensure directory exists
            $directory = dirname($filePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            
            // Copy from temp to final location
            copy($tempFilePath, $filePath);
            
            // Clean up temp file
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
            
            Log::info('Template placeholders replaced', [
                'report_id' => $report->id,
                'replaced' => $replacedCount,
                'failed' => $failedCount,
                'square_bracket_count' => count($squareBracketPlaceholders),
                'total' => count($data),
            ]);
            
            // 10. Update report
            $report->update([
                'report_file_path' => Storage::disk('public')->url($fileName),
                'file_format' => 'docx',
            ]);
            
            Log::info('DOCX generated from template', [
                'report_id' => $report->id,
                'file_path' => $fileName,
            ]);
            
            return Storage::disk('public')->url($fileName);
            
        } catch (\Exception $e) {
            Log::error('Failed to generate DOCX from template', [
                'error' => $e->getMessage(),
                'report_id' => $report->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Prepare data for template replacement
     * Convert structured data to template placeholder format
     * Supports multiple placeholder formats and variations
     */
    protected function prepareDataForTemplate(array $structuredData): array
    {
        $data = [];
        
        foreach ($structuredData as $key => $value) {
            // Clean value before mapping
            $cleanValue = $this->cleanValue($value);
            
            // Generate all possible placeholder variations
            $variations = [
                // Original key
                $key,
                // With underscores replaced by spaces
                str_replace('_', ' ', $key),
                str_replace('_', '-', $key),
                // Case variations
                strtolower($key),
                strtoupper($key),
                ucfirst($key),
                ucwords(str_replace('_', ' ', $key)),
                // Vietnamese common variations
                $this->getVietnameseKey($key),
            ];
            
            // Remove duplicates
            $variations = array_unique($variations);
            
            // Map to all placeholder formats: {{key}}, ${key}, {key}, [key]
            foreach ($variations as $variation) {
                // Double curly braces
                $data['{{' . $variation . '}}'] = $cleanValue;
                // Dollar sign curly braces
                $data['${' . $variation . '}'] = $cleanValue;
                // Single curly braces
                $data['{' . $variation . '}'] = $cleanValue;
                // Square brackets
                $data['[' . $variation . ']'] = $cleanValue;
                // Double square brackets
                $data['[[' . $variation . ']]'] = $cleanValue;
            }
        }
        
        Log::debug('Prepared data for template', [
            'fields_count' => count($structuredData),
            'placeholder_variations' => count($data),
        ]);
        
        return $data;
    }
    
    /**
     * Get Vietnamese key variation for common fields
     */
    protected function getVietnameseKey(string $key): string
    {
        $vietnameseMappings = [
            'ten' => 'Tên',
            'ho_ten' => 'Họ tên',
            'dia_chi' => 'Địa chỉ',
            'so_dien_thoai' => 'Số điện thoại',
            'email' => 'Email',
            'ngay_sinh' => 'Ngày sinh',
            'noi_sinh' => 'Nơi sinh',
            'quoc_tich' => 'Quốc tịch',
            'cmnd' => 'CMND',
            'cccd' => 'CCCD',
        ];
        
        $normalizedKey = strtolower(str_replace(['_', '-'], '', $key));
        
        if (isset($vietnameseMappings[$normalizedKey])) {
            return $vietnameseMappings[$normalizedKey];
        }
        
        return $key;
    }
    
    /**
     * Parse AI-generated report content to extract structured data
     * This helps map AI-generated content back to template placeholders
     * 
     * @param string $reportContent AI-generated formatted text
     * @param array $structuredData Existing structured data (for reference)
     * @return array Parsed data mapped to placeholders
     */
    protected function parseReportContent(string $reportContent, array $structuredData): array
    {
        $parsedData = [];
        
        if (empty($reportContent) || empty($structuredData)) {
            return $parsedData;
        }
        
        // Strategy 1: Extract from structured patterns in reportContent
        // Example: "Tên: Nguyễn Văn A" or "Tên công ty: ABC Corp"
        foreach ($structuredData as $key => $expectedValue) {
            // Normalize key for matching (remove underscores, convert to lowercase)
            $normalizedKey = strtolower(str_replace(['_', '-'], ' ', $key));
            
            // Try to find the value in reportContent using various patterns
            $patterns = [
                // Pattern: "Key: Value" (exact match)
                '/' . preg_quote($key, '/') . '\s*[:：]\s*(.+?)(?:\n|$|\.)/i',
                // Pattern: "Key Label: Value" (with space variations)
                '/' . preg_quote(str_replace('_', ' ', $key), '/') . '\s*[:：]\s*(.+?)(?:\n|$|\.)/i',
                // Pattern with Vietnamese common labels
                $this->buildVietnamesePattern($key),
            ];
            
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $reportContent, $matches)) {
                    $extractedValue = trim($matches[1]);
                    // Clean extracted value
                    $extractedValue = $this->cleanValue($extractedValue);
                    
                    if (!empty($extractedValue) && strlen($extractedValue) > 0) {
                        // Map to all placeholder formats
                        $parsedData['{{' . $key . '}}'] = $extractedValue;
                        $parsedData['${' . $key . '}'] = $extractedValue;
                        $parsedData['{{' . str_replace('_', ' ', $key) . '}}'] = $extractedValue;
                        $parsedData['{{' . strtoupper($key) . '}}'] = $extractedValue;
                        $parsedData['{{' . ucfirst($key) . '}}'] = $extractedValue;
                        break;
                    }
                }
            }
        }
        
        // Strategy 2: Extract from markdown-style sections
        // Example: "## Tên: Nguyễn Văn A" or "**Tên:** Nguyễn Văn A"
        $markdownPatterns = [
            '/##+\s*([^:：\n]+)[:：]\s*(.+?)(?:\n|$|(?=\n##))/i', // Headers
            '/\*\*([^:：\*\n]+)[:：]\*\*\s*(.+?)(?:\n|$)/i', // Bold labels
            '/\*\s*([^:：\*\n]+)[:：]\s*(.+?)(?:\n|$)/i', // List items
            '/-\s*([^:：\-\n]+)[:：]\s*(.+?)(?:\n|$)/i', // Dash list
        ];
        
        foreach ($markdownPatterns as $pattern) {
            if (preg_match_all($pattern, $reportContent, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $label = trim($match[1]);
                    $value = trim($match[2]);
                    
                    // Clean label and value
                    $label = $this->cleanValue($label);
                    $value = $this->cleanValue($value);
                    
                    if (empty($value)) {
                        continue;
                    }
                    
                    // Try to match label with structured data keys
                    foreach ($structuredData as $key => $expectedValue) {
                        $normalizedKey = strtolower(str_replace(['_', '-', ' '], '', $key));
                        $normalizedLabel = strtolower(str_replace(['_', '-', ' '], '', $label));
                        
                        // Check if label matches key (fuzzy matching)
                        if (
                            stripos($label, $key) !== false || 
                            stripos($key, $label) !== false ||
                            $normalizedKey === $normalizedLabel ||
                            $this->isSimilarLabel($label, $key)
                        ) {
                            $parsedData['{{' . $key . '}}'] = $value;
                            $parsedData['${' . $key . '}'] = $value;
                            break;
                        }
                    }
                }
            }
        }
        
        // Strategy 3: Extract JSON-like structure if embedded in content
        // Some AI models might include structured data in the response
        if (preg_match('/"structured_data"\s*:\s*\{([^}]+)\}/', $reportContent, $matches)) {
            // Try to extract and parse JSON structure
            $jsonStr = '{' . $matches[1] . '}';
            $jsonData = json_decode($jsonStr, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                foreach ($jsonData as $jsonKey => $jsonValue) {
                    if (isset($structuredData[$jsonKey])) {
                        $parsedData['{{' . $jsonKey . '}}'] = $this->cleanValue($jsonValue);
                        $parsedData['${' . $jsonKey . '}'] = $this->cleanValue($jsonValue);
                    }
                }
            }
        }
        
        // Strategy 4: Try to extract from table-like structures
        // Example: "| Field | Value |" or "Field | Value"
        if (preg_match_all('/\|?\s*([^|]+)\s*\|\s*([^|\n]+)\s*\|?/i', $reportContent, $tableMatches, PREG_SET_ORDER)) {
            foreach ($tableMatches as $tableMatch) {
                $label = trim($tableMatch[1]);
                $value = trim($tableMatch[2]);
                
                if (empty($value) || stripos($label, 'field') !== false || stripos($label, 'trường') !== false) {
                    continue; // Skip header rows
                }
                
                $label = $this->cleanValue($label);
                $value = $this->cleanValue($value);
                
                foreach ($structuredData as $key => $expectedValue) {
                    if ($this->isSimilarLabel($label, $key)) {
                        $parsedData['{{' . $key . '}}'] = $value;
                        $parsedData['${' . $key . '}'] = $value;
                        break;
                    }
                }
            }
        }
        
        Log::debug('Parsed report content', [
            'extracted_fields' => count($parsedData),
            'structured_fields' => count($structuredData),
        ]);
        
        return $parsedData;
    }
    
    /**
     * Build Vietnamese pattern for common field labels
     */
    protected function buildVietnamesePattern(string $key): string
    {
        $vietnameseMappings = [
            'ten' => '(?:Tên|Họ tên|Tên người|Tên đơn vị|Tên công ty|Tên tổ chức)',
            'dia_chi' => '(?:Địa chỉ|Địa điểm|Nơi ở)',
            'so_dien_thoai' => '(?:Số điện thoại|Điện thoại|SĐT|Phone)',
            'email' => '(?:Email|E-mail|Thư điện tử)',
            'ngay_sinh' => '(?:Ngày sinh|Sinh ngày|Ngày tháng năm sinh)',
            'cmnd' => '(?:CMND|Chứng minh nhân dân|CCCD|Căn cước)',
            'noi_sinh' => '(?:Nơi sinh|Quê quán)',
            'quoc_tich' => '(?:Quốc tịch|Quốc gia)',
        ];
        
        $normalizedKey = strtolower(str_replace(['_', '-'], '', $key));
        
        if (isset($vietnameseMappings[$normalizedKey])) {
            return '/' . $vietnameseMappings[$normalizedKey] . '\s*[:：]\s*(.+?)(?:\n|$|\.)/i';
        }
        
        // Fallback: try to match key with common Vietnamese patterns
        return '/' . preg_quote($key, '/') . '\s*[:：]\s*(.+?)(?:\n|$|\.)/i';
    }
    
    /**
     * Check if two labels are similar (fuzzy matching)
     */
    protected function isSimilarLabel(string $label1, string $label2): bool
    {
        $normalized1 = strtolower(str_replace(['_', '-', ' ', ':', '：'], '', $label1));
        $normalized2 = strtolower(str_replace(['_', '-', ' ', ':', '：'], '', $label2));
        
        // Exact match
        if ($normalized1 === $normalized2) {
            return true;
        }
        
        // Check if one contains the other
        if (strlen($normalized1) > 3 && strlen($normalized2) > 3) {
            if (stripos($normalized1, $normalized2) !== false || stripos($normalized2, $normalized1) !== false) {
                return true;
            }
        }
        
        // Check similarity using Levenshtein distance (for short strings)
        if (strlen($normalized1) <= 20 && strlen($normalized2) <= 20) {
            $distance = levenshtein($normalized1, $normalized2);
            $maxLen = max(strlen($normalized1), strlen($normalized2));
            $similarity = 1 - ($distance / $maxLen);
            
            // Consider similar if similarity > 70%
            return $similarity > 0.7;
        }
        
        return false;
    }
    
    /**
     * Clean value before inserting into template
     * Remove markdown formatting, extra whitespace, etc.
     * 
     * @param mixed $value
     * @return string
     */
    protected function cleanValue($value): string
    {
        if (!is_string($value)) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            } else {
                $value = (string) $value;
            }
        }
        
        // Remove markdown formatting (multiple passes for nested formatting)
        $value = preg_replace('/\*\*\*([^*]+)\*\*\*/', '$1', $value); // Bold + Italic
        $value = preg_replace('/\*\*([^*]+)\*\*/', '$1', $value); // Bold
        $value = preg_replace('/\*([^*]+)\*/', '$1', $value); // Italic
        $value = preg_replace('/`([^`]+)`/', '$1', $value); // Inline code
        $value = preg_replace('/```[\s\S]*?```/', '', $value); // Code blocks
        $value = preg_replace('/#+\s*/', '', $value); // Headers
        $value = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $value); // Links
        $value = preg_replace('/!\[([^\]]*)\]\([^\)]+\)/', '$1', $value); // Images
        $value = preg_replace('/~~([^~]+)~~/', '$1', $value); // Strikethrough
        $value = preg_replace('/==([^=]+)==/', '$1', $value); // Highlight
        
        // Remove HTML tags (but preserve content)
        $value = strip_tags($value);
        
        // Remove special markdown characters
        $value = preg_replace('/^[-*+]\s+/m', '', $value); // List markers
        $value = preg_replace('/^\d+\.\s+/m', '', $value); // Numbered list markers
        $value = preg_replace('/^>\s+/m', '', $value); // Blockquotes
        
        // Clean up whitespace (preserve line breaks for multi-line content)
        $value = preg_replace('/[ \t]+/', ' ', $value); // Multiple spaces/tabs to single space
        $value = preg_replace('/\n{3,}/', "\n\n", $value); // Multiple newlines to double newline
        $value = trim($value);
        
        // Remove leading/trailing punctuation that might be from markdown
        $value = preg_replace('/^[:\-•]\s*/', '', $value);
        $value = preg_replace('/\s*[:\-•]$/', '', $value);
        
        return $value;
    }

    /**
     * Extract placeholders from template DOCX file
     * Supports multiple formats: {{key}}, ${key}, [key], {key}
     * 
     * @param string $templatePath
     * @return array Array of placeholder => normalized_key
     */
    protected function extractPlaceholdersFromTemplate(string $templatePath): array
    {
        $placeholders = [];
        
        try {
            // Use TemplateProcessor to get variables
            $templateProcessor = new TemplateProcessor($templatePath);
            
            // Get all variables from template
            $variables = $templateProcessor->getVariables();
            
            foreach ($variables as $variable) {
                // Normalize variable name (remove ${} wrapper if present)
                $normalized = preg_replace('/^\$\{?|\}?$/', '', $variable);
                $placeholders[$variable] = $normalized;
            }
            
            // Also try to extract from document XML directly for [key] format
            // PhpWord TemplateProcessor might not recognize [key] format
            $zip = new \ZipArchive();
            if ($zip->open($templatePath) === true) {
                // Read document.xml
                $documentXml = $zip->getFromName('word/document.xml');
                if ($documentXml) {
                    // Extract [key] format placeholders
                    if (preg_match_all('/\[([^\]]+)\]/', $documentXml, $matches)) {
                        foreach ($matches[1] as $match) {
                            $placeholder = '[' . $match . ']';
                            $normalized = trim($match);
                            if (!isset($placeholders[$placeholder])) {
                                $placeholders[$placeholder] = $normalized;
                            }
                        }
                    }
                    
                    // Extract {{key}} format placeholders
                    if (preg_match_all('/\{\{([^}]+)\}\}/', $documentXml, $matches)) {
                        foreach ($matches[1] as $match) {
                            $placeholder = '{{' . trim($match) . '}}';
                            $normalized = trim($match);
                            if (!isset($placeholders[$placeholder])) {
                                $placeholders[$placeholder] = $normalized;
                            }
                        }
                    }
                }
                $zip->close();
            }
        } catch (\Exception $e) {
            Log::warning('Failed to extract placeholders from template', [
                'template_path' => $templatePath,
                'error' => $e->getMessage(),
            ]);
        }
        
        return $placeholders;
    }
    
    /**
     * Map data to actual template placeholders
     * This ensures data matches the exact placeholder format in template
     * 
     * @param array $data Prepared data with various placeholder formats
     * @param array $templatePlaceholders Actual placeholders found in template
     * @return array Mapped data with exact template placeholder keys
     */
    protected function mapDataToTemplatePlaceholders(array $data, array $templatePlaceholders): array
    {
        $mappedData = [];
        
        if (empty($templatePlaceholders)) {
            // If no placeholders found, return original data
            return $data;
        }
        
        // Create reverse mapping: normalized_key => [placeholder1, placeholder2, ...]
        $normalizedToPlaceholders = [];
        foreach ($templatePlaceholders as $placeholder => $normalized) {
            $normalizedLower = strtolower(str_replace(['_', '-', ' '], '', $normalized));
            if (!isset($normalizedToPlaceholders[$normalizedLower])) {
                $normalizedToPlaceholders[$normalizedLower] = [];
            }
            $normalizedToPlaceholders[$normalizedLower][] = $placeholder;
        }
        
        // Map data to template placeholders
        foreach ($data as $dataKey => $value) {
            // Extract key from placeholder format
            $key = preg_replace('/^[\[\{\$]*|[\}\]]*$/', '', $dataKey);
            $key = trim($key);
            $normalizedKey = strtolower(str_replace(['_', '-', ' '], '', $key));
            
            // Try to find matching placeholder in template
            if (isset($normalizedToPlaceholders[$normalizedKey])) {
                // Map to all matching placeholders
                foreach ($normalizedToPlaceholders[$normalizedKey] as $templatePlaceholder) {
                    $mappedData[$templatePlaceholder] = $value;
                }
            } else {
                // Try fuzzy matching
                foreach ($normalizedToPlaceholders as $normalizedTemplate => $placeholders) {
                    if ($this->isSimilarLabel($normalizedKey, $normalizedTemplate)) {
                        foreach ($placeholders as $templatePlaceholder) {
                            $mappedData[$templatePlaceholder] = $value;
                        }
                        break;
                    }
                }
            }
        }
        
        Log::debug('Mapped data to template placeholders', [
            'original_count' => count($data),
            'mapped_count' => count($mappedData),
            'template_placeholders' => count($templatePlaceholders),
        ]);
        
        return $mappedData;
    }
    
    /**
     * Replace [key] format placeholders directly in DOCX XML
     * This is needed because TemplateProcessor might not support [key] format
     * 
     * @param string $docxPath Path to DOCX file
     * @param array $placeholders Array of [key] => value
     */
    protected function replaceSquareBracketPlaceholders(string $docxPath, array $placeholders): void
    {
        try {
            $zip = new \ZipArchive();
            if ($zip->open($docxPath) !== true) {
                Log::warning('Failed to open DOCX for square bracket replacement', [
                    'docx_path' => $docxPath,
                ]);
                return;
            }
            
            // Read document.xml
            $documentXml = $zip->getFromName('word/document.xml');
            if (!$documentXml) {
                $zip->close();
                return;
            }
            
            // Replace each [key] placeholder
            foreach ($placeholders as $placeholder => $value) {
                // Escape special XML characters
                $escapedValue = htmlspecialchars($value, ENT_XML1, 'UTF-8');
                
                // Replace placeholder in XML
                // Match [key] in XML text nodes
                $pattern = '/' . preg_quote($placeholder, '/') . '/';
                $documentXml = preg_replace($pattern, $escapedValue, $documentXml);
            }
            
            // Write back to DOCX
            $zip->deleteName('word/document.xml');
            $zip->addFromString('word/document.xml', $documentXml);
            $zip->close();
            
            Log::debug('Replaced square bracket placeholders in XML', [
                'count' => count($placeholders),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to replace square bracket placeholders', [
                'docx_path' => $docxPath,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Generate DOCX from template with AI-generated content
     * Map AI content into template structure while preserving format
     *
     * @param UserReport $report
     * @param AiAssistant $assistant
     * @param string $aiContent AI-generated content
     * @param array $parsedContent Parsed content {sections: array, data: array}
     * @param array $collectedData Collected data from conversation (for mapping to placeholders)
     * @return string DOCX URL
     */
    public function generateDocxWithAIContent(
        UserReport $report,
        AiAssistant $assistant,
        string $aiContent,
        array $parsedContent,
        array $collectedData = []
    ): string {
        try {
            // ✅ QUAN TRỌNG: Verify assistant type
            if ($assistant->assistant_type !== 'report_generator') {
                throw new \Exception('ReportFileGenerator chỉ dùng cho assistant_type = report_generator');
            }
            
            // 1. Load template DOCX gốc
            $templatePath = $this->getTemplatePath($assistant->template_file_path);
            
            if (!file_exists($templatePath)) {
                throw new \Exception("Template file not found: {$templatePath}");
            }
            
            // 2. Extract placeholders từ template
            $templatePlaceholders = $this->extractPlaceholdersFromTemplate($templatePath);
            
            Log::debug('Generating DOCX with AI content', [
                'report_id' => $report->id,
                'placeholders_count' => count($templatePlaceholders),
                'sections_count' => count($parsedContent['sections'] ?? []),
                'data_points_count' => count($parsedContent['data'] ?? []),
            ]);
            
            // ✅ NEW: Check if template has placeholders
            // If NO placeholders → Use SmartDocxReplacer (text replacement)
            // If HAS placeholders → Use TemplateProcessor (placeholder replacement)
            $hasPlaceholders = !empty($templatePlaceholders);
            
            Log::info('Choosing replacement strategy', [
                'report_id' => $report->id,
                'has_placeholders' => $hasPlaceholders,
                'strategy' => $hasPlaceholders ? 'TemplateProcessor' : 'SmartDocxReplacer',
            ]);
            
            if (!$hasPlaceholders) {
                // ✅ Use SmartDocxReplacer for templates without placeholders
                return $this->generateWithSmartReplacer(
                    $report,
                    $assistant,
                    $templatePath,
                    $parsedContent,
                    $collectedData
                );
            }
            
            // 3. Sử dụng TemplateProcessor để replace placeholders (old method)
            $templateProcessor = new TemplateProcessor($templatePath);
            
            // 4. ✅ FIX: Map collectedData trực tiếp vào placeholders (không phải parse từ AI content)
            // AI content chỉ dùng để hiển thị, nhưng để fill vào template thì phải dùng collectedData
            // Sử dụng collectedData được pass vào (nếu có), nếu không lấy từ session
            if (empty($collectedData)) {
                $session = $report->chatSession;
                $collectedData = $session->collected_data ?? [];
            }
            
            // Merge parsed data với collectedData (collectedData có priority cao hơn)
            $parsedData = $parsedContent['data'] ?? [];
            $dataToMap = array_merge($parsedData, $collectedData); // collectedData overwrite parsedData
            
            // Map data vào placeholders trong template
            $mappedData = $this->mapDataToTemplatePlaceholders($dataToMap, $templatePlaceholders);
            
            Log::debug('Mapping data to template', [
                'report_id' => $report->id,
                'collected_data_count' => count($collectedData),
                'parsed_data_count' => count($parsedData),
                'mapped_data_count' => count($mappedData),
                'template_placeholders_count' => count($templatePlaceholders),
            ]);
            
            // 5. Replace placeholders (giữ nguyên format)
            $replacedCount = 0;
            $failedCount = 0;
            $squareBracketPlaceholders = [];
            
            $dataToReplace = !empty($mappedData) ? $mappedData : $dataToMap;
            
            foreach ($dataToReplace as $key => $value) {
                try {
                    $cleanValue = $this->cleanValue($value);
                    
                    if ($cleanValue === '' && $value !== '' && $value !== 0 && $value !== false) {
                        continue;
                    }
                    
                    // Check if placeholder is [key] format
                    if (preg_match('/^\[.+\]$/', $key)) {
                        $squareBracketPlaceholders[$key] = $cleanValue;
                    } else {
                        $templateProcessor->setValue($key, $cleanValue);
                        $replacedCount++;
                    }
                } catch (\PhpOffice\PhpWord\Exception\Exception $e) {
                    if (preg_match('/^\[.+\]$/', $key)) {
                        $squareBracketPlaceholders[$key] = $cleanValue;
                    } else {
                        $failedCount++;
                        Log::debug("Placeholder not found in template", [
                            'placeholder' => $key,
                            'report_id' => $report->id,
                        ]);
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::warning("Error replacing placeholder", [
                        'placeholder' => $key,
                        'report_id' => $report->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // 6. Replace sections if needed
            // TODO: Implement section replacement if template has sections
            
            // 7. Save file temporarily first
            $tempFilePath = storage_path('app/temp/report_' . $report->id . '_' . time() . '.docx');
            $tempDirectory = dirname($tempFilePath);
            if (!is_dir($tempDirectory)) {
                mkdir($tempDirectory, 0755, true);
            }
            
            $templateProcessor->saveAs($tempFilePath);
            
            // 8. If there are [key] format placeholders, replace them directly in XML
            if (!empty($squareBracketPlaceholders)) {
                $this->replaceSquareBracketPlaceholders($tempFilePath, $squareBracketPlaceholders);
                $replacedCount += count($squareBracketPlaceholders);
            }
            
            // 9. Move to final location
            $fileName = 'reports/report_' . $report->id . '_' . time() . '.docx';
            $filePath = storage_path('app/public/' . $fileName);
            
            $directory = dirname($filePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            
            copy($tempFilePath, $filePath);
            
            if (file_exists($tempFilePath)) {
                unlink($tempFilePath);
            }
            
            Log::info('DOCX generated with AI content', [
                'report_id' => $report->id,
                'replaced' => $replacedCount,
                'failed' => $failedCount,
                'file_path' => $fileName,
            ]);
            
            // 10. Update report với DOCX URL
            $docxUrl = Storage::disk('public')->url($fileName);
            
            // ✅ LOG: Before update
            Log::info('Updating report file path in ReportFileGenerator', [
                'report_id' => $report->id,
                'old_report_file_path' => $report->report_file_path,
                'new_docx_url' => $docxUrl,
                'file_path' => $fileName,
                'file_exists' => file_exists($filePath),
                'file_size' => file_exists($filePath) ? filesize($filePath) : 0,
            ]);
            
            $report->update([
                'report_file_path' => $docxUrl,
                'file_format' => 'docx',
            ]);
            
            // ✅ LOG: After update - Reload to verify
            $report->refresh();
            Log::info('Report file path updated in ReportFileGenerator', [
                'report_id' => $report->id,
                'report_file_path' => $report->report_file_path,
                'file_format' => $report->file_format,
                'updated_at' => $report->updated_at,
                'docx_url' => $docxUrl,
                'file_path' => $fileName,
            ]);
            
            return $docxUrl;
            
        } catch (\Exception $e) {
            Log::error('Failed to generate DOCX with AI content', [
                'error' => $e->getMessage(),
                'report_id' => $report->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Get template file path from URL
     */
    protected function getTemplatePath(string $templateUrl): string
    {
        // Parse URL to get path
        $parsedUrl = parse_url($templateUrl);
        $path = $parsedUrl['path'] ?? $templateUrl;
        
        // Remove /storage prefix if present (use str_replace instead of ltrim)
        // ltrim() removes characters, not prefix!
        $filePath = preg_replace('#^/storage/#', '', $path);
        
        // If path still starts with /, remove it
        $filePath = ltrim($filePath, '/');
        
        // Get full path using Storage
        $fullPath = Storage::disk('public')->path($filePath);
        
        Log::debug('Template path resolution', [
            'template_url' => $templateUrl,
            'parsed_path' => $path,
            'file_path' => $filePath,
            'full_path' => $fullPath,
            'exists' => file_exists($fullPath),
        ]);
        
        return $fullPath;
    }
    
    /**
     * ✅ NEW: Generate DOCX using SmartDocxReplacer (for templates without placeholders)
     *
     * @param UserReport $report
     * @param AiAssistant $assistant
     * @param string $templatePath
     * @param array $parsedContent
     * @param array $collectedData
     * @return string DOCX URL
     */
    protected function generateWithSmartReplacer(
        UserReport $report,
        AiAssistant $assistant,
        string $templatePath,
        array $parsedContent,
        array $collectedData
    ): string {
        Log::info('Using SmartDocxReplacer for template without placeholders', [
            'report_id' => $report->id,
            'assistant_id' => $assistant->id,
        ]);
        
        // Get collected data
        if (empty($collectedData)) {
            $session = $report->chatSession;
            $collectedData = $session->collected_data ?? [];
        }
        
        // Merge parsed data with collected data
        $parsedData = $parsedContent['data'] ?? [];
        $allData = array_merge($parsedData, $collectedData);
        
        Log::debug('Preparing replacements for SmartDocxReplacer', [
            'report_id' => $report->id,
            'collected_data_count' => count($collectedData),
            'parsed_data_count' => count($parsedData),
            'total_data_count' => count($allData),
        ]);
        
        // Extract template text for dots detection
        $documentProcessor = app(DocumentProcessor::class);
        $templateText = $documentProcessor->extractText($templatePath);
        
        // Build replacements array (including dots replacements)
        $replacements = $this->buildReplacementsFromData($allData, $templateText);
        
        Log::info('Built replacements for SmartDocxReplacer', [
            'report_id' => $report->id,
            'replacements_count' => count($replacements),
            'replacements_preview' => array_slice($replacements, 0, 5, true),
        ]);
        
        // Use SmartDocxReplacer
        $smartReplacer = app(SmartDocxReplacer::class);
        $newDocxPath = $smartReplacer->fillTemplate($templatePath, $replacements);
        
        // Get URL for the new file
        $fileName = basename($newDocxPath);
        $docxUrl = Storage::disk('public')->url('reports/' . $fileName);
        
        Log::info('SmartDocxReplacer generated DOCX', [
            'report_id' => $report->id,
            'file_path' => $newDocxPath,
            'file_exists' => file_exists($newDocxPath),
            'file_size' => file_exists($newDocxPath) ? filesize($newDocxPath) : 0,
            'docx_url' => $docxUrl,
        ]);
        
        // Update report with DOCX URL
        $report->update([
            'report_file_path' => $docxUrl,
            'file_format' => 'docx',
        ]);
        
        $report->refresh();
        Log::info('Report updated with SmartDocxReplacer DOCX URL', [
            'report_id' => $report->id,
            'report_file_path' => $report->report_file_path,
            'file_format' => $report->file_format,
        ]);
        
        return $docxUrl;
    }
    
    /**
     * Build replacements array from data
     * Maps common Vietnamese document patterns to data fields
     * Also handles dots placeholders (..............) with Hybrid Approach
     *
     * @param array $data
     * @param string $templateText Optional template text for dots detection
     * @return array
     */
    protected function buildReplacementsFromData(array $data, string $templateText = ''): array
    {
        $replacements = [];
        
        // 1. Build replacements từ data (existing logic)
        $dataReplacements = $this->buildDataReplacements($data);
        $replacements = array_merge($replacements, $dataReplacements);
        
        // 2. Detect và generate content cho dots placeholders (Hybrid Approach)
        if (!empty($templateText)) {
            $dotsReplacements = $this->buildDotsReplacements($templateText, $data);
            $replacements = array_merge($replacements, $dotsReplacements);
        }
        
        return $replacements;
    }
    
    /**
     * Build replacements from data fields (existing logic)
     *
     * @param array $data
     * @return array
     */
    protected function buildDataReplacements(array $data): array
    {
        $replacements = [];
        
        // Common Vietnamese document patterns
        $patterns = [
            // Organization/Company names
            'TÊN CƠ QUAN, TỔ CHỨC' => ['ten_co_quan', 'ten_cong_ty', 'ten_to_chuc'],
            'TÊN CQ, TC CHỦ QUẢN' => ['ten_co_quan', 'ten_cong_ty'],
            'TÊN CƠ QUAN' => ['ten_co_quan', 'ten_cong_ty'],
            
            // Document type
            'TÊN LOẠI VĂN BẢN' => ['loai_van_ban', 'ten_loai_van_ban'],
            
            // Dates
            'ngày... tháng... năm...' => ['ngay_thang_nam', 'ngay'],
            
            // Document number
            'Số:' => ['so_van_ban', 'so'],
            '/...' => ['so_van_ban', 'so'],
            
            // Address
            'Địa chỉ:' => ['dia_chi', 'address'],
            
            // Representative
            'Người đại diện:' => ['nguoi_dai_dien', 'representative'],
            
            // Tax code
            'Mã số thuế:' => ['ma_so_thue', 'tax_code'],
            
            // Phone
            'Số điện thoại:' => ['so_dien_thoai', 'phone', 'dien_thoai'],
        ];
        
        // Try to match data to patterns
        foreach ($patterns as $pattern => $possibleFields) {
            foreach ($possibleFields as $field) {
                if (isset($data[$field]) && !empty($data[$field])) {
                    $replacements[$pattern] = $data[$field];
                    break;
                }
            }
        }
        
        // Add direct field mapping (for custom fields)
        foreach ($data as $key => $value) {
            if (!empty($value) && is_string($value)) {
                // Convert snake_case to Title Case for matching
                $displayKey = ucwords(str_replace('_', ' ', $key));
                $replacements[$displayKey] = $value;
            }
        }
        
        return $replacements;
    }
    
    /**
     * Build replacements for dots placeholders (..............)
     * Uses Hybrid Approach: Pattern-based mapping + AI generation
     *
     * @param string $templateText
     * @param array $data
     * @return array
     */
    protected function buildDotsReplacements(string $templateText, array $data): array
    {
        $replacements = [];
        
        // 1. Detect dots placeholders
        $dotsPlaceholders = $this->detectDotsPlaceholders($templateText);
        
        if (empty($dotsPlaceholders)) {
            return $replacements;
        }
        
        Log::debug('Detected dots placeholders', [
            'count' => count($dotsPlaceholders),
            'placeholders' => array_map(function($p) {
                return [
                    'text' => substr($p['text'], 0, 20) . '...',
                    'context_type' => $p['context_type'],
                    'length' => $p['length'],
                ];
            }, $dotsPlaceholders),
        ]);
        
        // 2. Get pattern mappings
        $patternMappings = $this->getPatternMappings();
        
        // 3. Try pattern matching first (fast path)
        $matchedDots = [];
        $unmatchedDots = [];
        
        foreach ($dotsPlaceholders as $dots) {
            $matched = false;
            
            foreach ($patternMappings as $pattern => $mapping) {
                if (preg_match($pattern, $dots['context'], $matches)) {
                    $content = $this->generateContentByMapping($mapping, $data, $dots);
                    if (!empty($content)) {
                        $replacements[$dots['text']] = $content;
                        $matchedDots[] = $dots;
                        $matched = true;
                        break;
                    }
                }
            }
            
            if (!$matched) {
                $unmatchedDots[] = $dots;
            }
        }
        
        Log::info('Dots placeholders matched with patterns', [
            'matched_count' => count($matchedDots),
            'unmatched_count' => count($unmatchedDots),
        ]);
        
        // 4. Use AI for unmatched dots (fallback)
        if (!empty($unmatchedDots)) {
            $aiGeneratedContent = $this->generateContentWithAI($unmatchedDots, $data);
            
            // ✅ FIX: Convert AI-generated content format to simple replacements
            // AI-generated content has format: ['search' => ..., 'replace' => ..., 'position' => ...]
            // But we need simple format: ['text' => 'content'] for SmartDocxReplacer
            // However, we need to handle duplicate text by using position-based replacement
            foreach ($aiGeneratedContent as $key => $replacement) {
                if (is_array($replacement) && isset($replacement['search']) && isset($replacement['replace'])) {
                    // Use search text as key, but SmartDocxReplacer will replace all occurrences
                    // For now, we'll use the first occurrence's content
                    // TODO: Enhance SmartDocxReplacer to support position-based replacement
                    $searchText = $replacement['search'];
                    if (!isset($replacements[$searchText])) {
                        $replacements[$searchText] = $replacement['replace'];
                    }
                } else {
                    // Fallback: use key as search text
                    $replacements[$key] = $replacement;
                }
            }
        }
        
        return $replacements;
    }
    
    /**
     * Detect dots placeholders in template text
     *
     * @param string $templateText
     * @return array
     */
    protected function detectDotsPlaceholders(string $templateText): array
    {
        $placeholders = [];
        
        // Pattern: 3+ dots (minimum 3 dots to avoid matching ellipsis)
        $pattern = '/\.{3,}/';
        preg_match_all($pattern, $templateText, $matches, PREG_OFFSET_CAPTURE);
        
        foreach ($matches[0] as $match) {
            $dots = $match[0];
            $position = $match[1];
            
            // Extract context (100 chars before and after)
            $contextStart = max(0, $position - 100);
            $contextEnd = min(strlen($templateText), $position + strlen($dots) + 100);
            $context = substr($templateText, $contextStart, $contextEnd - $contextStart);
            
            // Detect context type
            $contextType = $this->detectContextType($context, $position);
            
            // Extract heading/label if available
            $heading = $this->extractHeading($context);
            $label = $this->extractLabel($context);
            
            $placeholders[] = [
                'text' => $dots,
                'position' => $position,
                'context' => $context,
                'context_type' => $contextType,
                'heading' => $heading,
                'label' => $label,
                'length' => strlen($dots),
            ];
        }
        
        return $placeholders;
    }
    
    /**
     * Detect context type for dots placeholder
     *
     * @param string $context
     * @param int $position
     * @return string
     */
    protected function detectContextType(string $context, int $position): string
    {
        // Check for heading (ALL CAPS, 5+ chars)
        if (preg_match('/^[A-ZÁÀẢÃẠĂẮẰẲẴẶÂẤẦẨẪẬÉÈẺẼẸÊẾỀỂỄỆÍÌỈĨỊÓÒỎÕỌÔỐỒỔỖỘƠỚỜỞỠỢÚÙỦŨỤƯỨỪỬỮỰÝỲỶỸỴĐ\s]{5,}$/m', $context)) {
            return 'heading';
        }
        
        // Check for label (text ending with colon)
        if (preg_match('/([^:]+):\s*\.{3,}/', $context)) {
            return 'label';
        }
        
        // Check for table (contains pipe or table structure)
        if (preg_match('/\|.*\.{3,}.*\|/', $context) || preg_match('/\b(cell|row|table)\b/i', $context)) {
            return 'table';
        }
        
        // Check for list (starts with dash or asterisk)
        if (preg_match('/^[-*]\s*\.{3,}/m', $context)) {
            return 'list';
        }
        
        // Default: paragraph
        return 'paragraph';
    }
    
    /**
     * Extract heading from context
     *
     * @param string $context
     * @return string|null
     */
    protected function extractHeading(string $context): ?string
    {
        // Look for ALL CAPS heading before dots
        if (preg_match('/([A-ZÁÀẢÃẠĂẮẰẲẴẶÂẤẦẨẪẬÉÈẺẼẸÊẾỀỂỄỆÍÌỈĨỊÓÒỎÕỌÔỐỒỔỖỘƠỚỜỞỠỢÚÙỦŨỤƯỨỪỬỮỰÝỲỶỸỴĐ\s]{5,})\s*\.{3,}/', $context, $matches)) {
            return trim($matches[1]);
        }
        
        return null;
    }
    
    /**
     * Extract label from context
     *
     * @param string $context
     * @return string|null
     */
    protected function extractLabel(string $context): ?string
    {
        // Look for label ending with colon before dots
        if (preg_match('/([^:]+):\s*\.{3,}/', $context, $matches)) {
            return trim($matches[1]);
        }
        
        return null;
    }
    
    /**
     * Get pattern mappings for common dots patterns
     *
     * @return array
     */
    protected function getPatternMappings(): array
    {
        return [
            // Heading patterns
            '/BÁO CÁO HOẠT ĐỘNG.*\.{3,}/i' => [
                'type' => 'ai_generate',
                'prompt_template' => 'Tạo nội dung báo cáo hoạt động cho {ten_co_quan} trong {ky_bao_cao}. Nội dung cần chi tiết, chuyên nghiệp, khoảng 200-300 từ.',
                'data_fields' => ['ten_co_quan', 'ky_bao_cao', 'thoi_gian'],
            ],
            '/TÓM TẮT.*\.{3,}/i' => [
                'type' => 'ai_generate',
                'prompt_template' => 'Tạo tóm tắt báo cáo dựa trên dữ liệu có sẵn. Tóm tắt cần ngắn gọn, súc tích, khoảng 100-150 từ.',
                'data_fields' => [],
            ],
            '/KẾT LUẬN.*\.{3,}/i' => [
                'type' => 'ai_generate',
                'prompt_template' => 'Tạo kết luận báo cáo dựa trên dữ liệu có sẵn. Kết luận cần rõ ràng, khách quan, khoảng 100-150 từ.',
                'data_fields' => [],
            ],
            
            // Label patterns
            '/Nơi nhận:.*\.{3,}/i' => [
                'type' => 'data_field',
                'field' => 'noi_nhan',
                'fallback' => 'ai_generate',
                'fallback_prompt' => 'Tạo danh sách nơi nhận báo cáo. Format: - [Tên cơ quan/tổ chức]; - [Tên cơ quan/tổ chức]; - Lưu: VT',
            ],
            '/Họ và tên:.*\.{3,}/i' => [
                'type' => 'data_field',
                'field' => 'ho_ten',
                'fallback' => 'ai_generate',
                'fallback_prompt' => 'Tạo tên người ký báo cáo. Format: [Họ và tên]',
            ],
            '/Chức vụ:.*\.{3,}/i' => [
                'type' => 'data_field',
                'field' => 'chuc_vu',
                'fallback' => 'ai_generate',
                'fallback_prompt' => 'Tạo chức vụ người ký báo cáo. Format: [Chức vụ]',
            ],
            '/QUYỀN HẠN.*CHỨC VỤ.*NGƯỜI KÝ.*\.{3,}/i' => [
                'type' => 'data_field',
                'field' => 'quyen_han_chuc_vu',
                'fallback' => 'ai_generate',
                'fallback_prompt' => 'Tạo thông tin quyền hạn, chức vụ của người ký báo cáo.',
            ],
        ];
    }
    
    /**
     * Generate content by mapping (pattern-based)
     *
     * @param array $mapping
     * @param array $data
     * @param array $dots
     * @return string|null
     */
    protected function generateContentByMapping(array $mapping, array $data, array $dots): ?string
    {
        if ($mapping['type'] === 'data_field') {
            // Try to get from data first
            $field = $mapping['field'] ?? null;
            if ($field && isset($data[$field]) && !empty($data[$field])) {
                return $data[$field];
            }
            
            // Fallback to AI if configured
            if (isset($mapping['fallback']) && $mapping['fallback'] === 'ai_generate') {
                $prompt = $mapping['fallback_prompt'] ?? 'Tạo nội dung phù hợp.';
                return $this->generateContentWithAISingle($prompt, $data, $dots);
            }
            
            return null;
        } elseif ($mapping['type'] === 'ai_generate') {
            // Build prompt from template
            $prompt = $mapping['prompt_template'] ?? 'Tạo nội dung phù hợp.';
            
            // Replace placeholders in prompt
            foreach ($mapping['data_fields'] ?? [] as $field) {
                $value = $data[$field] ?? '';
                $prompt = str_replace('{' . $field . '}', $value, $prompt);
            }
            
            return $this->generateContentWithAISingle($prompt, $data, $dots);
        }
        
        return null;
    }
    
    /**
     * Generate content with AI for unmatched dots
     *
     * @param array $unmatchedDots
     * @param array $data
     * @return array
     */
    protected function generateContentWithAI(array $unmatchedDots, array $data): array
    {
        $replacements = [];
        
        // ✅ FIX: Generate content individually for each dots placeholder
        // Each dots placeholder may need different content based on its specific context
        foreach ($unmatchedDots as $dots) {
            $content = $this->generateContentForContext($dots['context_type'], [$dots], $data);
            
            // ✅ FIX: Use position as unique key to handle duplicate text
            // Multiple dots may have same text but different positions/contexts
            $uniqueKey = $dots['text'] . '_' . $dots['position'];
            $replacements[$uniqueKey] = [
                'search' => $dots['text'],
                'replace' => $content,
                'position' => $dots['position'],
            ];
        }
        
        Log::info('AI generated content for unmatched dots', [
            'dots_count' => count($unmatchedDots),
            'replacements_count' => count($replacements),
        ]);
        
        return $replacements;
    }
    
    /**
     * Generate content for specific context type
     *
     * @param string $contextType
     * @param array $dots
     * @param array $data
     * @return string
     */
    protected function generateContentForContext(string $contextType, array $dots, array $data): string
    {
        $firstDot = $dots[0] ?? null;
        if (!$firstDot) {
            return '';
        }
        
        $heading = $firstDot['heading'] ?? null;
        $label = $firstDot['label'] ?? null;
        
        // Build prompt based on context type
        $prompt = '';
        
        switch ($contextType) {
            case 'heading':
                $prompt = $heading 
                    ? "Tạo nội dung cho phần '{$heading}' trong báo cáo. Nội dung cần chi tiết, chuyên nghiệp, khoảng 200-300 từ."
                    : "Tạo nội dung báo cáo dựa trên dữ liệu có sẵn. Nội dung cần chi tiết, chuyên nghiệp, khoảng 200-300 từ.";
                break;
                
            case 'label':
                $prompt = $label
                    ? "Tạo nội dung cho '{$label}' trong báo cáo."
                    : "Tạo nội dung phù hợp với ngữ cảnh.";
                break;
                
            case 'table':
                $prompt = "Tạo nội dung cho ô trong bảng. Nội dung cần ngắn gọn, phù hợp với định dạng bảng.";
                break;
                
            case 'list':
                $prompt = "Tạo nội dung cho mục trong danh sách. Nội dung cần ngắn gọn, rõ ràng.";
                break;
                
            default:
                $prompt = "Tạo nội dung báo cáo dựa trên dữ liệu có sẵn. Nội dung cần phù hợp với ngữ cảnh.";
        }
        
        // Add data context
        $dataSummary = "Dữ liệu có sẵn: " . json_encode($data, JSON_UNESCAPED_UNICODE);
        $prompt .= "\n\n" . $dataSummary;
        
        return $this->generateContentWithAISingle($prompt, $data, $firstDot);
    }
    
    /**
     * Generate content with AI (single call)
     *
     * @param string $prompt
     * @param array $data
     * @param array $dots
     * @return string
     */
    protected function generateContentWithAISingle(string $prompt, array $data, array $dots): string
    {
        try {
            // Use OpenAI to generate content
            $response = \OpenAI\Laravel\Facades\OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Bạn là chuyên gia tạo nội dung báo cáo chuyên nghiệp. Bạn tạo nội dung dựa trên yêu cầu và dữ liệu có sẵn, giữ nguyên phong cách văn bản hành chính.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens' => 500,
            ]);
            
            $content = trim($response->choices[0]->message->content);
            
            Log::debug('AI generated content for dots placeholder', [
                'context_type' => $dots['context_type'] ?? 'unknown',
                'content_length' => strlen($content),
                'content_preview' => substr($content, 0, 100),
            ]);
            
            return $content;
        } catch (\Exception $e) {
            Log::error('Failed to generate content with AI for dots placeholder', [
                'error' => $e->getMessage(),
                'context_type' => $dots['context_type'] ?? 'unknown',
            ]);
            
            // Fallback: return empty or default content
            return '';
        }
    }
}



