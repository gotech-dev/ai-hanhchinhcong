<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Services\AdvancedDocxToHtmlConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    /**
     * Preview document as HTML with format preservation
     * API: GET /api/documents/{messageId}/preview-html
     */
    public function previewHtml(Request $request, $messageId)
    {
        Log::info('🔵 [DocumentController] HTML preview requested', [
            'message_id' => $messageId,
            'user_id' => Auth::id(),
        ]);
        
        $message = ChatMessage::findOrFail($messageId);
        
        // Authorization: Check if message belongs to user's session
        if ($message->chatSession->user_id !== Auth::id()) {
            Log::warning('⚠️ [DocumentController] Unauthorized HTML preview request', [
                'message_id' => $messageId,
                'message_user_id' => $message->chatSession->user_id,
                'request_user_id' => Auth::id(),
            ]);
            abort(403);
        }
        
        // Get document file path from metadata
        $documentData = $message->metadata['document'] ?? null;
        if (!$documentData || !isset($documentData['file_path'])) {
            Log::warning('⚠️ [DocumentController] No document file path in metadata', [
                'message_id' => $messageId,
                'metadata' => $message->metadata,
            ]);
            return response()->json([
                'error' => 'Document file not found in message metadata'
            ], 404);
        }
        
        $filePath = $documentData['file_path'];
        
        try {
            // ✅ FIX: Tạm thời bỏ hết cache để code cho đúng
            Log::info('🔵 [DocumentController] Generating HTML (cache disabled)', [
                'message_id' => $messageId,
            ]);
            
            // Get DOCX path
            $docxPath = $this->getDocxPath($filePath);
            
            if (!file_exists($docxPath)) {
                Log::error('🔴 [DocumentController] DOCX file not found', [
                    'message_id' => $messageId,
                    'file_path' => $filePath,
                    'docx_path' => $docxPath,
                ]);
                throw new \Exception("DOCX not found: {$docxPath}");
            }
            
            Log::info('🔵 [DocumentController] Converting DOCX to HTML', [
                'message_id' => $messageId,
                'docx_path' => $docxPath,
                'file_size' => filesize($docxPath),
                'converter' => 'AdvancedDocxToHtmlConverter (95%+ format, pure PHP)',
            ]);
            
            // ✅ Use AdvancedDocxToHtmlConverter (95%+ format preservation, pure PHP)
            // Native PHP solution - no external dependencies
            $converter = new AdvancedDocxToHtmlConverter();
            $html = $converter->convert($docxPath);
            
            // ✅ DEBUG: Extract first 10 paragraphs for logging
            $dom = new \DOMDocument();
            @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
            $xpath = new \DOMXPath($dom);
            $paragraphs = $xpath->query('//p');
            $first10Paragraphs = [];
            for ($i = 0; $i < min(10, $paragraphs->length); $i++) {
                $p = $paragraphs->item($i);
                $text = trim($p->textContent);
                $htmlContent = $dom->saveHTML($p);
                $styles = [];
                if ($p->hasAttribute('style')) {
                    $styleAttr = $p->getAttribute('style');
                    $stylePairs = explode(';', $styleAttr);
                    foreach ($stylePairs as $pair) {
                        $pair = trim($pair);
                        if (empty($pair)) continue;
                        $parts = explode(':', $pair, 2);
                        if (count($parts) === 2) {
                            $styles[trim($parts[0])] = trim($parts[1]);
                        }
                    }
                }
                $first10Paragraphs[] = [
                    'index' => $i + 1,
                    'text' => mb_substr($text, 0, 80),
                    'length' => mb_strlen($text),
                    'html' => mb_substr($htmlContent, 0, 200),
                    'styles' => $styles,
                ];
            }
            
            Log::info('🔵 [DocumentController] HTML generated', [
                'message_id' => $messageId,
                'html_length' => strlen($html),
                'p_tag_count' => substr_count($html, '<p'),
                'paragraph_count' => $paragraphs->length,
                'first_10_paragraphs' => $first10Paragraphs,
            ]);
            
            Log::info('🔵 [DocumentController] HTML returned (cache disabled)', [
                'message_id' => $messageId,
                'html_length' => strlen($html),
                'p_tag_count' => substr_count($html, '<p'),
            ]);
            
            // ✅ DEBUG: Compare with template gốc if available
            $templateComparison = null;
            try {
                $template = \App\Models\DocumentTemplate::where('document_type', 'bien_ban')
                    ->where('is_active', true)
                    ->first();
                
                if ($template) {
                    $templateUrl = $template->file_path;
                    $parsedUrl = parse_url($templateUrl);
                    $path = $parsedUrl['path'] ?? $templateUrl;
                    $filePath = preg_replace('#^/storage/#', '', $path);
                    $filePath = ltrim($filePath, '/');
                    $templatePath = Storage::disk('public')->path($filePath);
                    
                    if (file_exists($templatePath)) {
                        // Extract text from template
                        $phpWord = \PhpOffice\PhpWord\IOFactory::load($templatePath);
                        $templateText = [];
                        foreach ($phpWord->getSections() as $section) {
                            foreach ($section->getElements() as $element) {
                                if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                                    $line = '';
                                    foreach ($element->getElements() as $textElement) {
                                        if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
                                            $line .= $textElement->getText();
                                        }
                                    }
                                    $normalized = preg_replace('/\s+/', ' ', trim($line));
                                    if (!empty($normalized)) {
                                        $templateText[] = $normalized;
                                    }
                                }
                            }
                        }
                        
                        // Extract text from generated DOCX
                        $phpWord2 = \PhpOffice\PhpWord\IOFactory::load($docxPath);
                        $generatedText = [];
                        foreach ($phpWord2->getSections() as $section) {
                            foreach ($section->getElements() as $element) {
                                if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                                    $line = '';
                                    foreach ($element->getElements() as $textElement) {
                                        if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
                                            $line .= $textElement->getText();
                                        }
                                    }
                                    $normalized = preg_replace('/\s+/', ' ', trim($line));
                                    if (!empty($normalized)) {
                                        $generatedText[] = $normalized;
                                    }
                                }
                            }
                        }
                        
                        // Extract text from HTML
                        $htmlText = [];
                        foreach ($paragraphs as $p) {
                            $text = trim($p->textContent);
                            $normalized = preg_replace('/\s+/', ' ', $text);
                            if (!empty($normalized)) {
                                $htmlText[] = $normalized;
                            }
                        }
                        
                        $templateComparison = [
                            'template_lines' => count($templateText),
                            'generated_lines' => count($generatedText),
                            'html_lines' => count($htmlText),
                            'template_first_10' => array_slice($templateText, 0, 10),
                            'generated_first_10' => array_slice($generatedText, 0, 10),
                            'html_first_10' => array_slice($htmlText, 0, 10),
                        ];
                    }
                }
            } catch (\Exception $e) {
                Log::warning('⚠️ [DocumentController] Failed to compare with template', [
                    'error' => $e->getMessage(),
                ]);
            }
            
            Log::info('✅ [DocumentController] HTML preview generated successfully', [
                'message_id' => $messageId,
                'html_length' => strlen($html),
                'cache_disabled' => true,
                'template_comparison' => $templateComparison,
            ]);
            
            return response($html)
                ->header('Content-Type', 'text/html; charset=utf-8')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type');
                
        } catch (\Exception $e) {
            Log::error('🔴 [DocumentController] Failed to generate HTML preview', [
                'error' => $e->getMessage(),
                'message_id' => $messageId,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to generate HTML preview: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Download document as DOCX or PDF
     * API: GET /api/documents/{messageId}/download?format=docx|pdf
     */
    public function download(Request $request, $messageId)
    {
        Log::info('🔵 [DocumentController] Download requested', [
            'message_id' => $messageId,
            'user_id' => Auth::id(),
            'format' => $request->get('format', 'docx'),
        ]);
        
        $message = ChatMessage::findOrFail($messageId);
        
        // Authorization: Check if message belongs to user's session
        if ($message->chatSession->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
        
        // Get document file path from metadata
        $documentData = $message->metadata['document'] ?? null;
        if (!$documentData || !isset($documentData['file_path'])) {
            Log::warning('⚠️ [DocumentController] No document file path in metadata', [
                'message_id' => $messageId,
            ]);
            return response()->json([
                'error' => 'Document file not found'
            ], 404);
        }
        
        $format = $request->get('format', 'docx'); // docx or pdf
        
        try {
            $filePath = $documentData['file_path'];
            
            if ($format === 'docx') {
                // Get DOCX file path
                $docxPath = $this->getDocxPath($filePath);
                
                if (!file_exists($docxPath)) {
                    Log::error('🔴 [DocumentController] DOCX file not found', [
                        'message_id' => $messageId,
                        'file_path' => $filePath,
                        'docx_path' => $docxPath,
                    ]);
                    return response()->json([
                        'error' => 'DOCX file not found on server'
                    ], 404);
                }
                
                Log::info('✅ [DocumentController] Serving DOCX file for download', [
                    'message_id' => $messageId,
                    'file_path' => $docxPath,
                    'file_size' => filesize($docxPath),
                ]);
                
                // Generate filename
                $documentType = $documentData['document_type'] ?? 'document';
                $filename = "{$documentType}_{$messageId}.docx";
                
                return response()->download(
                    $docxPath,
                    $filename,
                    ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
                );
            } else {
                // PDF generation - can be implemented later
                return response()->json([
                    'error' => 'PDF generation not yet implemented. Please use DOCX format.'
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('🔴 [DocumentController] Failed to download document', [
                'error' => $e->getMessage(),
                'message_id' => $messageId,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to download document: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Compare DOCX with HTML preview
     * API: GET /api/documents/{messageId}/compare
     */
    public function compare(Request $request, $messageId)
    {
        Log::info('🔵 [DocumentController] Compare requested', [
            'message_id' => $messageId,
            'user_id' => Auth::id(),
        ]);
        
        $message = ChatMessage::findOrFail($messageId);
        
        // Authorization
        if ($message->chatSession->user_id !== Auth::id()) {
            abort(403);
        }
        
        // Get document file path
        $documentData = $message->metadata['document'] ?? null;
        if (!$documentData || !isset($documentData['file_path'])) {
            return response()->json(['error' => 'Document file not found'], 404);
        }
        
        $docxPath = $this->getDocxPath($documentData['file_path']);
        
        if (!file_exists($docxPath)) {
            return response()->json(['error' => 'DOCX file not found'], 404);
        }
        
        try {
            // Extract text from DOCX
            $docxText = $this->extractTextFromDocx($docxPath);
            
            // Convert to HTML
            $converter = new AdvancedDocxToHtmlConverter();
            $html = $converter->convert($docxPath);
            
            // Extract text from HTML
            $htmlText = $this->extractTextFromHtml($html);
            
            // Compare
            $differences = $this->compareTexts($docxText, $htmlText);
            
            return response()->json([
                'docx_lines' => count($docxText),
                'html_lines' => count($htmlText),
                'differences' => count($differences),
                'docx_text' => $docxText,
                'html_text' => $htmlText,
                'differences_detail' => $differences
            ]);
        } catch (\Exception $e) {
            Log::error('🔴 [DocumentController] Compare failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Compare failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Extract text from DOCX
     */
    protected function extractTextFromDocx(string $docxPath): array
    {
        $phpWord = \PhpOffice\PhpWord\IOFactory::load($docxPath);
        $text = [];
        
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                    $line = '';
                    foreach ($element->getElements() as $textElement) {
                        if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
                            $line .= $textElement->getText();
                        }
                    }
                    $normalized = $this->normalizeText($line);
                    if (!empty($normalized)) {
                        $text[] = $normalized;
                    }
                }
            }
        }
        
        return $text;
    }
    
    /**
     * Extract text from HTML
     */
    protected function extractTextFromHtml(string $html): array
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($dom);
        
        $text = [];
        $paragraphs = $xpath->query('//p');
        
        foreach ($paragraphs as $paragraph) {
            $normalized = $this->normalizeText($paragraph->textContent);
            if (!empty($normalized)) {
                $text[] = $normalized;
            }
        }
        
        return $text;
    }
    
    /**
     * Normalize text for comparison
     */
    protected function normalizeText(string $text): string
    {
        // Remove extra spaces
        $text = preg_replace('/\s+/', ' ', $text);
        // Trim
        $text = trim($text);
        return $text;
    }
    
    /**
     * Compare two text arrays
     */
    protected function compareTexts(array $docxText, array $htmlText): array
    {
        $differences = [];
        $maxLines = max(count($docxText), count($htmlText));
        
        for ($i = 0; $i < $maxLines; $i++) {
            $docxLine = $docxText[$i] ?? '';
            $htmlLine = $htmlText[$i] ?? '';
            
            if ($docxLine !== $htmlLine) {
                $differences[] = [
                    'line' => $i + 1,
                    'docx' => $docxLine,
                    'html' => $htmlLine,
                    'diff' => $this->computeDiff($docxLine, $htmlLine)
                ];
            }
        }
        
        return $differences;
    }
    
    /**
     * Compute character-by-character diff
     */
    protected function computeDiff(string $docx, string $html): array
    {
        $diff = [];
        $maxLen = max(mb_strlen($docx), mb_strlen($html));
        
        for ($i = 0; $i < $maxLen; $i++) {
            $docxChar = mb_substr($docx, $i, 1) ?: '';
            $htmlChar = mb_substr($html, $i, 1) ?: '';
            
            if ($docxChar !== $htmlChar) {
                $diff[] = [
                    'position' => $i,
                    'docx' => $docxChar === '' ? '[EMPTY]' : $docxChar,
                    'html' => $htmlChar === '' ? '[EMPTY]' : $htmlChar
                ];
            }
        }
        
        return $diff;
    }
    
    /**
     * Get DOCX file path from URL
     * 
     * @param string $url
     * @return string
     */
    protected function getDocxPath(string $url): string
    {
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? $url;
        $filePath = preg_replace('#^/storage/#', '', $path);
        $filePath = ltrim($filePath, '/');
        return Storage::disk('public')->path($filePath);
    }
}

