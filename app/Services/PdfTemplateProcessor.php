<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\ChatSession;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * PDF Template Processor Service
 * 
 * ✅ MỚI: Service riêng để xử lý PDF templates
 * - Convert PDF → DOCX bằng LibreOffice headless
 * - Extract placeholders từ converted DOCX
 * - Fill placeholders và generate DOCX mới
 * - Giữ nguyên 95-98% format của PDF template gốc
 * 
 * ⚠️ QUAN TRỌNG: Service này CHỈ xử lý PDF templates
 * - Không ảnh hưởng đến DOCX template processing
 * - Tách biệt hoàn toàn với logic DOCX
 */
class PdfTemplateProcessor
{
    /**
     * Generate DOCX from PDF template using PDF→DOCX conversion
     * 
     * Flow:
     * 1. Convert PDF → DOCX (LibreOffice headless) - preserves format
     * 2. Extract placeholders from converted DOCX
     * 3. Fill placeholders using TemplateProcessor
     * 4. Save new DOCX with filled data
     * 
     * @param DocumentTemplate $template PDF template
     * @param array $documentData Data to fill into template
     * @param ChatSession $session Chat session
     * @return string Public URL of generated DOCX file
     * @throws \Exception If conversion or processing fails
     */
    public function generateDocxFromPdfTemplate(
        DocumentTemplate $template,
        array $documentData,
        ChatSession $session
    ): string {
        try {
            $templatePath = $this->getTemplatePath($template->file_path);
            
            if (!file_exists($templatePath)) {
                throw new \Exception("PDF template file not found: {$templatePath}");
            }
            
            Log::info('📄 [PdfTemplateProcessor] Starting PDF template processing', [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'pdf_path' => $templatePath,
                'method' => 'PDF→DOCX conversion + TemplateProcessor',
                'expected_format_preservation' => '95-98%',
            ]);
            
            // Step 1: Convert PDF → DOCX
            $docxPath = $this->convertPdfToDocx($templatePath);
            
            Log::info('✅ [PdfTemplateProcessor] PDF converted to DOCX', [
                'template_id' => $template->id,
                'docx_path' => $docxPath,
                'docx_size' => file_exists($docxPath) ? filesize($docxPath) : 0,
            ]);
            
            // Step 2: Use TemplateProcessor (giống như DOCX template)
            $templateProcessor = new TemplateProcessor($docxPath);
            
            // Step 3: Extract placeholders
            $placeholders = $this->extractPlaceholders($template, $templateProcessor);
            
            Log::info('🔵 [PdfTemplateProcessor] Placeholders extracted from converted DOCX', [
                'template_id' => $template->id,
                'placeholders_count' => count($placeholders),
                'placeholders' => array_slice($placeholders, 0, 20),
            ]);
            
            // Step 4: Map and replace placeholders
            $mappedData = $this->mapDataToPlaceholders($documentData, $placeholders);
            
            $replacedCount = 0;
            $failedCount = 0;
            
            foreach ($mappedData as $key => $value) {
                try {
                    $templateProcessor->setValue($key, $value);
                    $replacedCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    Log::warning('⚠️ [PdfTemplateProcessor] Failed to replace placeholder', [
                        'template_id' => $template->id,
                        'placeholder' => $key,
                        'value_preview' => mb_substr($value, 0, 50),
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            Log::info('🔵 [PdfTemplateProcessor] Placeholder replacement completed', [
                'template_id' => $template->id,
                'replaced_count' => $replacedCount,
                'failed_count' => $failedCount,
                'total_placeholders' => count($mappedData),
            ]);
            
            // Step 5: Save new DOCX
            $fileName = $this->generateFileName(
                \App\Enums\DocumentType::from($template->document_type),
                $session
            );
            $filePath = storage_path("app/public/documents/{$fileName}");
            
            $directory = dirname($filePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            
            $templateProcessor->saveAs($filePath);
            
            Log::info('✅ [PdfTemplateProcessor] DOCX generated from PDF template', [
                'template_id' => $template->id,
                'file_path' => $filePath,
                'file_size' => filesize($filePath),
            ]);
            
            // Clean up temp converted DOCX file
            if (file_exists($docxPath)) {
                unlink($docxPath);
            }
            
            return Storage::disk('public')->url("documents/{$fileName}");
            
        } catch (\Exception $e) {
            Log::error('🔴 [PdfTemplateProcessor] Failed to generate DOCX from PDF template', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Convert PDF to DOCX using LibreOffice headless mode
     * 
     * @param string $pdfPath Path to PDF file
     * @return string Path to converted DOCX file
     * @throws \Exception If conversion fails
     */
    protected function convertPdfToDocx(string $pdfPath): string
    {
        if (!file_exists($pdfPath)) {
            throw new \Exception("PDF file not found: {$pdfPath}");
        }
        
        $outputDir = sys_get_temp_dir();
        $outputPath = $outputDir . '/' . uniqid('pdf_to_docx_') . '.docx';
        
        Log::info('📄 [PdfTemplateProcessor] Starting PDF to DOCX conversion', [
            'pdf_path' => $pdfPath,
            'output_path' => $outputPath,
        ]);
        
        // Method 1: Try LibreOffice (most reliable)
        if ($this->isLibreOfficeAvailable()) {
            try {
                $command = sprintf(
                    'libreoffice --headless --convert-to docx --outdir %s %s 2>&1',
                    escapeshellarg($outputDir),
                    escapeshellarg($pdfPath)
                );
                
                exec($command, $output, $returnCode);
                
                if ($returnCode === 0) {
                    // LibreOffice saves with original name
                    $convertedPath = $outputDir . '/' . pathinfo($pdfPath, PATHINFO_FILENAME) . '.docx';
                    if (file_exists($convertedPath)) {
                        rename($convertedPath, $outputPath);
                        
                        Log::info('✅ [PdfTemplateProcessor] PDF converted to DOCX using LibreOffice', [
                            'docx_path' => $outputPath,
                            'file_size' => filesize($outputPath),
                        ]);
                        
                        return $outputPath;
                    }
                }
                
                Log::warning('⚠️ [PdfTemplateProcessor] LibreOffice conversion failed', [
                    'return_code' => $returnCode,
                    'output' => implode("\n", $output),
                ]);
            } catch (\Exception $e) {
                Log::warning('⚠️ [PdfTemplateProcessor] LibreOffice conversion error', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Method 2: Try unoconv
        if ($this->isUnoconvAvailable()) {
            try {
                $command = sprintf(
                    'unoconv -f docx -o %s %s 2>&1',
                    escapeshellarg($outputPath),
                    escapeshellarg($pdfPath)
                );
                
                exec($command, $output, $returnCode);
                
                if ($returnCode === 0 && file_exists($outputPath)) {
                    Log::info('✅ [PdfTemplateProcessor] PDF converted to DOCX using unoconv', [
                        'docx_path' => $outputPath,
                        'file_size' => filesize($outputPath),
                    ]);
                    
                    return $outputPath;
                }
                
                Log::warning('⚠️ [PdfTemplateProcessor] unoconv conversion failed', [
                    'return_code' => $returnCode,
                    'output' => implode("\n", $output),
                ]);
            } catch (\Exception $e) {
                Log::warning('⚠️ [PdfTemplateProcessor] unoconv conversion error', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        throw new \Exception(
            'PDF to DOCX conversion failed. ' .
            'Please install LibreOffice (brew install libreoffice) or unoconv. ' .
            'LibreOffice available: ' . ($this->isLibreOfficeAvailable() ? 'yes' : 'no') . ', ' .
            'unoconv available: ' . ($this->isUnoconvAvailable() ? 'yes' : 'no')
        );
    }
    
    /**
     * Check if LibreOffice is available
     * 
     * @return bool
     */
    protected function isLibreOfficeAvailable(): bool
    {
        exec('which libreoffice 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }
    
    /**
     * Check if unoconv is available
     * 
     * @return bool
     */
    protected function isUnoconvAvailable(): bool
    {
        exec('which unoconv 2>&1', $output, $returnCode);
        return $returnCode === 0;
    }
    
    /**
     * Extract placeholders from template
     * 
     * @param DocumentTemplate $template
     * @param TemplateProcessor $templateProcessor
     * @return array List of placeholder names
     */
    protected function extractPlaceholders(DocumentTemplate $template, TemplateProcessor $templateProcessor): array
    {
        // Try to get from metadata first
        $placeholders = $template->metadata['placeholders'] ?? [];
        
        if (empty($placeholders)) {
            // Extract from converted DOCX
            $placeholders = $templateProcessor->getVariables();
        }
        
        return $placeholders;
    }
    
    /**
     * Map document data to template placeholders
     * 
     * @param array $documentData Document data
     * @param array $placeholders List of placeholder names
     * @return array Mapped data (placeholder => value)
     */
    protected function mapDataToPlaceholders(array $documentData, array $placeholders): array
    {
        $mapped = [];
        
        foreach ($placeholders as $placeholder) {
            // Remove {{ }} or ${ } or [ ] from placeholder
            $cleanKey = preg_replace('/[\[\]{}${}]/', '', $placeholder);
            $cleanKey = trim($cleanKey);
            
            // Try to find matching data
            if (isset($documentData[$cleanKey])) {
                $mapped[$placeholder] = $documentData[$cleanKey];
            } elseif (isset($documentData[$placeholder])) {
                $mapped[$placeholder] = $documentData[$placeholder];
            } else {
                // Try case-insensitive match
                foreach ($documentData as $key => $value) {
                    if (strtolower($key) === strtolower($cleanKey)) {
                        $mapped[$placeholder] = $value;
                        break;
                    }
                }
            }
        }
        
        return $mapped;
    }
    
    /**
     * Get template file path from URL
     * 
     * @param string $templateUrl Template file URL
     * @return string Absolute file path
     */
    protected function getTemplatePath(string $templateUrl): string
    {
        $parsedUrl = parse_url($templateUrl);
        $path = $parsedUrl['path'] ?? $templateUrl;
        $filePath = preg_replace('#^/storage/#', '', $path);
        $filePath = ltrim($filePath, '/');
        return Storage::disk('public')->path($filePath);
    }
    
    /**
     * Generate file name for output DOCX
     * 
     * @param \App\Enums\DocumentType $documentType
     * @param ChatSession $session
     * @return string File name
     */
    protected function generateFileName(\App\Enums\DocumentType $documentType, ChatSession $session): string
    {
        $timestamp = now()->format('YmdHis');
        $type = $documentType->value;
        $sessionId = $session->id;
        
        return "{$type}_{$sessionId}_{$timestamp}.docx";
    }
}

