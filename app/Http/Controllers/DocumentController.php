<?php

namespace App\Http\Controllers;

use App\Models\AiAssistant;
use App\Models\ChatMessage;
use App\Models\DocumentTemplate;
use App\Services\AdvancedDocxToHtmlConverter;
use App\Services\AsposeWordsConverter;
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
        
        // ✅ DEBUG: Log document metadata để kiểm tra
        Log::info('🔵 [DocumentController] Document metadata', [
            'message_id' => $messageId,
            'file_path' => $documentData['file_path'] ?? null,
            'document_type' => $documentData['document_type'] ?? null,
            'template_id' => $documentData['template_id'] ?? null,
            'template_used' => $documentData['template_used'] ?? null,
            'assistant_id' => $message->chatSession->assistant_id ?? null,
        ]);
        
        $filePath = $documentData['file_path'];
        
        try {
            // ✅ FIX: Tạm thời bỏ hết cache để code cho đúng
            Log::info('🔵 [DocumentController] Generating HTML (cache disabled)', [
                'message_id' => $messageId,
                'document_file_path' => $filePath,
            ]);
            
            // Get DOCX path
            $docxPath = $this->getDocxPath($filePath);
            
            // ✅ DEBUG: Log DOCX path để verify
            Log::info('🔵 [DocumentController] DOCX path resolved', [
                'message_id' => $messageId,
                'file_path' => $filePath,
                'docx_path' => $docxPath,
                'file_exists' => file_exists($docxPath),
            ]);
            
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
                'file_basename' => basename($docxPath),
            ]);
            
            // ✅ DEBUG: Verify đây là DOCX file đã generate, không phải template
            $docxBasename = basename($docxPath);
            Log::info('🔵 [DocumentController] DOCX file info', [
                'message_id' => $messageId,
                'docx_basename' => $docxBasename,
                'is_template_file' => strpos($docxBasename, 'template') !== false || strpos($docxPath, 'document-templates') !== false,
                'is_document_file' => strpos($docxPath, 'documents') !== false,
            ]);
            
            // ✅ Use Aspose.Words API for 100% format preservation (fallback to AdvancedDocxToHtmlConverter)
            $html = null;
            $converterUsed = 'unknown';
            
            try {
                // Try Aspose API first (100% format preservation)
                $asposeConverter = app(AsposeWordsConverter::class);
                
                if ($asposeConverter->isConfigured()) {
                    Log::info('🔵 [DocumentController] Using Aspose.Words API for DOCX → HTML conversion', [
                        'message_id' => $messageId,
                        'docx_path' => $docxPath,
                    ]);
                    
                    $html = $asposeConverter->convertDocxToHtml($docxPath);
                    $converterUsed = 'Aspose.Words API (100% format)';
                    
                    // ✅ DEBUG: Log HTML sample để verify
                    $htmlSample = mb_substr(strip_tags($html), 0, 300);
                    Log::info('✅ [DocumentController] Aspose conversion successful', [
                        'message_id' => $messageId,
                        'html_length' => strlen($html),
                        'html_sample' => $htmlSample,
                    ]);
                } else {
                    throw new \Exception('Aspose API not configured');
                }
            } catch (\Exception $e) {
                Log::warning('⚠️ [DocumentController] Aspose conversion failed, falling back to AdvancedDocxToHtmlConverter', [
                    'message_id' => $messageId,
                    'error' => $e->getMessage(),
                ]);
                
                // Fallback to AdvancedDocxToHtmlConverter (95%+ format preservation, pure PHP)
                $converter = new AdvancedDocxToHtmlConverter();
                $html = $converter->convert($docxPath);
                $converterUsed = 'AdvancedDocxToHtmlConverter (95%+ format, fallback)';
                
                // ✅ DEBUG: Log HTML sample
                $htmlSample = mb_substr(strip_tags($html), 0, 300);
                Log::info('✅ [DocumentController] Fallback conversion successful', [
                    'message_id' => $messageId,
                    'html_length' => strlen($html),
                    'html_sample' => $htmlSample,
                ]);
            }
            
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
                'converter_used' => $converterUsed,
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
                'converter_used' => $converterUsed,
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
     * Preview template as HTML from saved HTML in metadata (with caching)
     * API: GET /api/templates/{templateId}/preview-html
     */
    public function previewTemplateHtml(Request $request, $templateId)
    {
        Log::info('🔵 [DocumentController] Template HTML preview requested', [
            'template_id' => $templateId,
            'user_id' => Auth::id(),
            'request_url' => $request->fullUrl(),
        ]);
        
        $template = DocumentTemplate::findOrFail($templateId);
        
        // ✅ DEBUG: Log template info chi tiết
        Log::info('🔵 [DocumentController] Template info', [
            'template_id' => $template->id,
            'template_name' => $template->name,
            'document_type' => $template->document_type,
            'subtype' => $template->template_subtype,
            'file_name' => $template->file_name,
            'file_type' => $template->file_type,
            'file_path' => $template->file_path,
            'assistant_id' => $template->ai_assistant_id,
            'has_html_preview_in_metadata' => isset($template->metadata['html_preview']),
            'html_preview_length' => isset($template->metadata['html_preview']) ? strlen($template->metadata['html_preview']) : 0,
        ]);
        
        // Authorization: Check if template belongs to user's assistant (if needed)
        // For now, allow preview for all authenticated users
        
        // ✅ CACHE: Get HTML preview from metadata (cached)
        $htmlPreview = $template->metadata['html_preview'] ?? null;
        
        // ✅ MỚI: Nếu chưa có cache, convert và cache lại
        if (!$htmlPreview) {
            Log::info('⚠️ [DocumentController] No HTML preview cache, generating now', [
                'template_id' => $templateId,
                'template_name' => $template->name,
                'file_type' => $template->file_type,
                'file_path' => $template->file_path,
            ]);
            
            try {
                // ✅ FIX: Get template file path (tương tự như DocumentDraftingService)
                $templateUrl = $template->file_path;
                $parsedUrl = parse_url($templateUrl);
                $path = $parsedUrl['path'] ?? $templateUrl;
                $filePath = preg_replace('#^/storage/#', '', $path);
                $filePath = ltrim($filePath, '/');
                $templatePath = Storage::disk('public')->path($filePath);
                
                // ✅ DEBUG: Log path resolution
                Log::info('🔵 [DocumentController] Template path resolution', [
                    'template_id' => $templateId,
                    'original_file_path' => $template->file_path,
                    'parsed_url' => $parsedUrl,
                    'parsed_path' => $path,
                    'cleaned_file_path' => $filePath,
                    'resolved_template_path' => $templatePath,
                    'file_exists' => file_exists($templatePath),
                ]);
                
                if (!file_exists($templatePath)) {
                    Log::error('🔴 [DocumentController] Template file not found', [
                        'template_id' => $templateId,
                        'template_name' => $template->name,
                        'file_path' => $template->file_path,
                        'template_path' => $templatePath,
                        'parsed_path' => $filePath,
                    ]);
                    
                    return response()->json([
                        'error' => 'Template file not found. Please re-upload the template.'
                    ], 404);
                }
                
                // Convert template to HTML
                $asposeConverter = app(AsposeWordsConverter::class);
                
                if ($asposeConverter->isConfigured()) {
                    Log::info('🔵 [DocumentController] Converting template to HTML (first time)', [
                        'template_id' => $templateId,
                        'template_name' => $template->name,
                        'file_type' => $template->file_type,
                        'template_path' => $templatePath,
                        'file_size' => filesize($templatePath),
                    ]);
                    
                    // Convert based on file type
                    if ($template->file_type === 'pdf') {
                        $htmlPreview = $asposeConverter->convertPdfToHtml($templatePath);
                        Log::info('✅ [DocumentController] PDF → HTML conversion completed', [
                            'template_id' => $templateId,
                            'html_length' => strlen($htmlPreview),
                        ]);
                    } elseif ($template->file_type === 'docx') {
                        $htmlPreview = $asposeConverter->convertDocxToHtml($templatePath);
                        Log::info('✅ [DocumentController] DOCX → HTML conversion completed', [
                            'template_id' => $templateId,
                            'html_length' => strlen($htmlPreview),
                        ]);
                    } else {
                        throw new \Exception("Unsupported file type: {$template->file_type}");
                    }
                    
                    // ✅ DEBUG: Log HTML preview sample
                    $htmlSample = mb_substr(strip_tags($htmlPreview), 0, 200);
                    Log::info('🔵 [DocumentController] HTML preview sample', [
                        'template_id' => $templateId,
                        'html_sample' => $htmlSample,
                    ]);
                    
                    // ✅ CACHE: Save HTML to metadata for next time
                    $metadata = $template->metadata ?? [];
                    $metadata['html_preview'] = $htmlPreview;
                    $metadata['html_preview_cached_at'] = now()->toISOString();
                    
                    $template->metadata = $metadata;
                    $template->save();
                    
                    Log::info('✅ [DocumentController] Template HTML generated and cached', [
                        'template_id' => $templateId,
                        'template_name' => $template->name,
                        'html_length' => strlen($htmlPreview),
                    ]);
                } else {
                    throw new \Exception('Aspose API not configured');
                }
            } catch (\Exception $e) {
                Log::error('🔴 [DocumentController] Failed to generate HTML preview', [
                    'template_id' => $templateId,
                    'template_name' => $template->name,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                
                return response()->json([
                    'error' => 'HTML preview not available. Please ensure Aspose API is configured and re-upload the template.'
                ], 500);
            }
        } else {
            // ✅ DEBUG: Log cached HTML info
            $htmlSample = mb_substr(strip_tags($htmlPreview), 0, 200);
            Log::info('✅ [DocumentController] Template HTML preview returned from cache', [
                'template_id' => $templateId,
                'template_name' => $template->name,
                'html_length' => strlen($htmlPreview),
                'cached_at' => $template->metadata['html_preview_cached_at'] ?? 'unknown',
                'html_sample' => $htmlSample,
            ]);
        }
        
        // ✅ CACHE: Enable browser cache (1 hour) for faster loading
        $lastModified = $template->updated_at ?? $template->created_at;
        $etag = md5($htmlPreview . $template->id);
        
        // Check if client has cached version
        $ifNoneMatch = $request->header('If-None-Match');
        if ($ifNoneMatch === $etag) {
            return response('', 304) // Not Modified
                ->header('ETag', $etag)
                ->header('Cache-Control', 'public, max-age=3600') // 1 hour
                ->header('Last-Modified', $lastModified->toRfc7231String());
        }
        
        return response($htmlPreview)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600') // ✅ CACHE: 1 hour browser cache
            ->header('ETag', $etag) // ✅ CACHE: ETag for conditional requests
            ->header('Last-Modified', $lastModified->toRfc7231String()) // ✅ CACHE: Last modified
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type');
    }
    
    /**
     * Update template HTML preview
     * API: PUT /api/templates/{templateId}/html-preview
     */
    public function updateHtmlPreview(Request $request, $templateId)
    {
        Log::info('🔵 [DocumentController] Update HTML preview requested', [
            'template_id' => $templateId,
            'user_id' => Auth::id(),
        ]);
        
        $request->validate([
            'html_preview' => 'required|string',
        ]);
        
        $template = DocumentTemplate::findOrFail($templateId);
        
        // Authorization: Check if user is admin or owns the assistant
        $assistant = $template->assistant;
        if ($assistant->admin_id !== Auth::id()) {
            Log::warning('⚠️ [DocumentController] Unauthorized HTML preview update', [
                'template_id' => $templateId,
                'assistant_admin_id' => $assistant->admin_id,
                'request_user_id' => Auth::id(),
            ]);
            abort(403, 'Unauthorized');
        }
        
        // Update metadata with new HTML preview
        $metadata = $template->metadata ?? [];
        $metadata['html_preview'] = $request->input('html_preview');
        $metadata['html_preview_edited'] = true;
        $metadata['html_preview_edited_at'] = now()->toISOString();
        
        $template->metadata = $metadata;
        $template->save();
        
        Log::info('✅ [DocumentController] HTML preview updated', [
            'template_id' => $templateId,
            'html_length' => strlen($request->input('html_preview')),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'HTML preview đã được cập nhật thành công',
            'template' => $template->fresh(),
        ]);
    }
    
    /**
     * Update document HTML preview (for editing)
     * API: PUT /api/documents/{messageId}/html-preview
     */
    public function updateDocumentHtmlPreview(Request $request, $messageId)
    {
        Log::info('🔵 [DocumentController] Update document HTML preview requested', [
            'message_id' => $messageId,
            'user_id' => Auth::id(),
        ]);
        
        $request->validate([
            'html_preview' => 'required|string',
        ]);
        
        $message = ChatMessage::findOrFail($messageId);
        
        // Authorization: Check if message belongs to user's session
        if ($message->chatSession->user_id !== Auth::id()) {
            Log::warning('⚠️ [DocumentController] Unauthorized HTML preview update', [
                'message_id' => $messageId,
                'message_user_id' => $message->chatSession->user_id,
                'request_user_id' => Auth::id(),
            ]);
            abort(403, 'Unauthorized');
        }
        
        // Get document file path from metadata
        $documentData = $message->metadata['document'] ?? null;
        if (!$documentData || !isset($documentData['file_path'])) {
            return response()->json([
                'error' => 'Document file not found in message metadata'
            ], 404);
        }
        
        $htmlPreview = $request->input('html_preview');
        
        // ✅ MỚI: Convert HTML → DOCX và lưu file mới
        $newDocxPath = null;
        try {
            $asposeConverter = app(AsposeWordsConverter::class);
            
            if ($asposeConverter->isConfigured()) {
                Log::info('🔵 [DocumentController] Converting edited HTML → DOCX', [
                    'message_id' => $messageId,
                ]);
                
                // Convert HTML → DOCX
                $tempDocxPath = $asposeConverter->convertHtmlToDocx($htmlPreview);
                
                if ($tempDocxPath && file_exists($tempDocxPath)) {
                    // Get original DOCX path
                    $originalDocxPath = $this->getDocxPath($documentData['file_path']);
                    
                    // Generate new file name (keep same name or add timestamp)
                    $originalFileName = basename($originalDocxPath);
                    $fileInfo = pathinfo($originalFileName);
                    $newFileName = $fileInfo['filename'] . '_edited_' . time() . '.docx';
                    
                    // Save new DOCX to storage
                    $newPath = 'documents/' . $newFileName;
                    Storage::disk('public')->put($newPath, file_get_contents($tempDocxPath));
                    
                    // Get new file URL
                    $newUrl = Storage::disk('public')->url($newPath);
                    $newDocxPath = Storage::disk('public')->path($newPath);
                    
                    // Clean up temp file
                    if (file_exists($tempDocxPath)) {
                        unlink($tempDocxPath);
                    }
                    
                    Log::info('✅ [DocumentController] New DOCX file created from edited HTML', [
                        'message_id' => $messageId,
                        'new_file_path' => $newPath,
                        'new_file_url' => $newUrl,
                        'file_size' => filesize($newDocxPath),
                    ]);
                } else {
                    throw new \Exception('Converted DOCX file not found');
                }
            } else {
                Log::warning('⚠️ [DocumentController] Aspose API not configured, skipping DOCX conversion', [
                    'message_id' => $messageId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('🔴 [DocumentController] Failed to convert HTML → DOCX', [
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Continue without DOCX conversion - still save HTML preview
        }
        
        // Update metadata with new HTML preview and new DOCX path
        $metadata = $message->metadata ?? [];
        if (!isset($metadata['document'])) {
            $metadata['document'] = [];
        }
        $metadata['document']['html_preview'] = $htmlPreview;
        $metadata['document']['html_preview_edited'] = true;
        $metadata['document']['html_preview_edited_at'] = now()->toISOString();
        
        // ✅ MỚI: Update file_path nếu có DOCX mới
        if ($newDocxPath && isset($newUrl)) {
            $metadata['document']['file_path'] = $newUrl;
            $metadata['document']['file_path_updated'] = true;
            $metadata['document']['file_path_updated_at'] = now()->toISOString();
            
            Log::info('✅ [DocumentController] Document file_path updated', [
                'message_id' => $messageId,
                'old_path' => $documentData['file_path'],
                'new_path' => $newUrl,
            ]);
        }
        
        $message->metadata = $metadata;
        $message->save();
        
        Log::info('✅ [DocumentController] Document HTML preview and DOCX updated', [
            'message_id' => $messageId,
            'html_length' => strlen($htmlPreview),
            'docx_updated' => !empty($newDocxPath),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'HTML preview và file DOCX đã được cập nhật thành công',
            'docx_updated' => !empty($newDocxPath),
        ]);
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
    
    /**
     * Preview assistant template as HTML (for user)
     * API: GET /api/assistants/{assistantId}/template-preview
     */
    public function previewAssistantTemplate(Request $request, $assistantId)
    {
        Log::info('🔵 [DocumentController] Assistant template preview requested', [
            'assistant_id' => $assistantId,
            'user_id' => Auth::id(),
        ]);
        
        $assistant = AiAssistant::findOrFail($assistantId);
        
        // Find first active template for this assistant
        $template = DocumentTemplate::where('ai_assistant_id', $assistantId)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->first();
        
        if (!$template) {
            Log::warning('⚠️ [DocumentController] No template found for assistant', [
                'assistant_id' => $assistantId,
            ]);
            
            return response()->json([
                'error' => 'Không tìm thấy template. Vui lòng liên hệ admin.'
            ], 404);
        }
        
        // Get HTML preview from metadata
        $htmlPreview = $template->metadata['html_preview'] ?? null;
        
        // If no HTML preview, generate it
        if (!$htmlPreview) {
            Log::info('⚠️ [DocumentController] No HTML preview cache, generating now', [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'file_type' => $template->file_type,
            ]);
            
            try {
                // Get template file path
                $templateUrl = $template->file_path;
                $parsedUrl = parse_url($templateUrl);
                $path = $parsedUrl['path'] ?? $templateUrl;
                $filePath = preg_replace('#^/storage/#', '', $path);
                $filePath = ltrim($filePath, '/');
                $templatePath = Storage::disk('public')->path($filePath);
                
                if (!file_exists($templatePath)) {
                    Log::error('🔴 [DocumentController] Template file not found', [
                        'template_id' => $template->id,
                        'template_path' => $templatePath,
                    ]);
                    
                    return response()->json([
                        'error' => 'Template file not found. Please re-upload the template.'
                    ], 404);
                }
                
                // Convert template to HTML
                $asposeConverter = app(AsposeWordsConverter::class);
                
                if ($asposeConverter->isConfigured()) {
                    // Convert based on file type
                    if ($template->file_type === 'pdf') {
                        $htmlPreview = $asposeConverter->convertPdfToHtml($templatePath);
                    } elseif ($template->file_type === 'docx') {
                        $htmlPreview = $asposeConverter->convertDocxToHtml($templatePath);
                    } else {
                        throw new \Exception("Unsupported file type: {$template->file_type}");
                    }
                    
                    // Cache HTML to metadata
                    $metadata = $template->metadata ?? [];
                    $metadata['html_preview'] = $htmlPreview;
                    $metadata['html_preview_cached_at'] = now()->toISOString();
                    
                    $template->metadata = $metadata;
                    $template->save();
                    
                    Log::info('✅ [DocumentController] Template HTML generated and cached', [
                        'template_id' => $template->id,
                        'html_length' => strlen($htmlPreview),
                    ]);
                } else {
                    throw new \Exception('Aspose API not configured');
                }
            } catch (\Exception $e) {
                Log::error('🔴 [DocumentController] Failed to generate HTML preview', [
                    'template_id' => $template->id,
                    'error' => $e->getMessage(),
                ]);
                
                return response()->json([
                    'error' => 'HTML preview not available. Please ensure Aspose API is configured.'
                ], 500);
            }
        }
        
        Log::info('✅ [DocumentController] Assistant template HTML preview returned', [
            'assistant_id' => $assistantId,
            'template_id' => $template->id,
            'html_length' => strlen($htmlPreview),
        ]);
        
        // Return HTML with proper headers
        return response($htmlPreview)
            ->header('Content-Type', 'text/html; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=3600'); // Cache 1 hour
    }
}

