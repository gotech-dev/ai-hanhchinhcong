<?php

namespace App\Http\Controllers;

use App\Models\UserReport;
use App\Services\ReportFileGenerator;
use App\Services\ReportGenerator;
use App\Services\AdvancedDocxToHtmlConverter;
use App\Services\PandocDocxToHtmlConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ReportController extends Controller
{
    public function __construct(
        protected ReportFileGenerator $reportFileGenerator,
        protected ReportGenerator $reportGenerator
    ) {}

    /**
     * Generate DOCX from template (first time)
     * API: POST /api/reports/{reportId}/generate-docx
     */
    public function generateDocx(Request $request, $reportId)
    {
        $report = UserReport::findOrFail($reportId);
        
        // Check permission
        if ($report->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
        
        $assistant = $report->chatSession->aiAssistant;
        $collectedData = $report->chatSession->collected_data ?? [];
        
        if (!$assistant) {
            return response()->json([
                'error' => 'Assistant not found for this report'
            ], 404);
        }
        
        if (!$assistant->template_file_path) {
            return response()->json([
                'error' => 'Template file not found for this assistant'
            ], 404);
        }
        
        try {
            // Generate DOCX từ template (giữ format)
            // ✅ Dùng trực tiếp collectedData - không cần AI-generated content
            $docxUrl = $this->reportFileGenerator->generateDocxFromTemplate(
                $report,
                $assistant,
                $collectedData // ✅ Dùng trực tiếp collected data
            );
            
            return response()->json([
                'success' => true,
                'docx_url' => $docxUrl,
                'report_id' => $report->id,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate DOCX: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download report as DOCX or PDF
     * API: GET /api/reports/{reportId}/download?format=docx|pdf
     */
    public function download(Request $request, $reportId)
    {
        $report = UserReport::findOrFail($reportId);
        
        // Check permission
        if ($report->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
        
        $format = $request->get('format', 'docx'); // docx or pdf
        
        try {
            $assistant = $report->chatSession->aiAssistant;
            $collectedData = $report->chatSession->collected_data ?? [];
            
            if (!$assistant || !$assistant->template_file_path) {
                return response()->json([
                    'error' => 'Template not found'
                ], 404);
            }
            
            if ($format === 'docx') {
                // Generate DOCX từ template (giữ format)
                // Check if already generated
                if ($report->report_file_path && $report->file_format === 'docx') {
                    // ✅ Fix: Use preg_replace instead of ltrim (ltrim removes characters, not prefix!)
                    $url = parse_url($report->report_file_path);
                    $path = $url['path'] ?? $report->report_file_path;
                    $filePath = preg_replace('#^/storage/#', '', $path);
                    $filePath = ltrim($filePath, '/');
                    $fullPath = Storage::disk('public')->path($filePath);
                    
                    if (file_exists($fullPath)) {
                        return response()->download(
                            $fullPath,
                            'report_' . $report->id . '.docx',
                            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
                        );
                    }
                }
                
                // Generate new DOCX
                // ✅ Dùng trực tiếp collectedData - không cần AI-generated content
                $fileUrl = $this->reportFileGenerator->generateDocxFromTemplate(
                    $report,
                    $assistant,
                    $collectedData // ✅ Dùng trực tiếp collected data
                );
                
                // Convert URL to file path
                // ✅ Fix: Use preg_replace instead of ltrim (ltrim removes characters, not prefix!)
                $url = parse_url($fileUrl);
                $path = $url['path'] ?? $fileUrl;
                $filePath = preg_replace('#^/storage/#', '', $path);
                $filePath = ltrim($filePath, '/');
                $fullPath = Storage::disk('public')->path($filePath);
                
                return response()->download(
                    $fullPath,
                    'report_' . $report->id . '.docx',
                    ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
                );
            } else {
                // PDF generation - can be implemented later
                return response()->json([
                    'error' => 'PDF generation not yet implemented. Please use DOCX format.'
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Regenerate report with edit request from user
     * API: POST /api/reports/{reportId}/regenerate
     * 
     * @param Request $request
     * @param int $reportId
     * @return \Illuminate\Http\JsonResponse
     */
    public function regenerate(Request $request, $reportId)
    {
        $request->validate([
            'edit_request' => 'required|string|max:2000',
        ]);
        
        $report = UserReport::findOrFail($reportId);
        
        // Check permission
        if ($report->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }
        
        $assistant = $report->chatSession->aiAssistant;
        $collectedData = $report->chatSession->collected_data ?? [];
        $editRequest = $request->input('edit_request');
        
        if (!$assistant) {
            return response()->json([
                'error' => 'Assistant not found for this report'
            ], 404);
        }
        
        if ($assistant->assistant_type !== 'report_generator') {
            return response()->json([
                'error' => 'This endpoint is only for report_generator assistants'
            ], 400);
        }
        
        if (!$assistant->template_file_path) {
            return response()->json([
                'error' => 'Template file not found for this assistant'
            ], 404);
        }
        
        try {
            // Combine original user request with edit request
            $userRequest = "Yêu cầu chỉnh sửa: {$editRequest}\n\n";
            $userRequest .= "Báo cáo gốc:\n" . substr($report->report_content ?? '', 0, 1000);
            
            // Regenerate report with edit request
            $reportResult = $this->reportGenerator->generateReport(
                $assistant,
                $report->chatSession,
                $collectedData,
                $userRequest // ✅ Pass edit request as user request
            );
            
            // Update existing report
            $report->update([
                'report_content' => $reportResult['report_content'],
                'report_file_path' => $reportResult['report_file_path'],
                'file_format' => 'docx',
            ]);
            
            Log::info('Report regenerated with edit request', [
                'report_id' => $report->id,
                'edit_request' => $editRequest,
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Báo cáo đã được cập nhật theo yêu cầu của bạn!',
                'report' => [
                    'report_id' => $report->id,
                    'report_content' => $reportResult['report_content'],
                    'report_file_path' => $reportResult['report_file_path'],
                ],
                'docx_url' => $reportResult['report_file_path'],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to regenerate report', [
                'error' => $e->getMessage(),
                'report_id' => $report->id,
                'edit_request' => $editRequest,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to regenerate report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview report as HTML with 95%+ format preservation
     * API: GET /api/reports/{reportId}/preview-html
     * 
     * @param Request $request
     * @param int $reportId
     * @return \Illuminate\Http\Response
     */
    public function previewHtml(Request $request, $reportId)
    {
        Log::info('HTML preview requested', [
            'report_id' => $reportId,
            'user_id' => Auth::id(),
        ]);
        
        $report = UserReport::findOrFail($reportId);
        
        // Authorization
        if ($report->user_id !== Auth::id()) {
            Log::warning('Unauthorized HTML preview request', [
                'report_id' => $reportId,
                'report_user_id' => $report->user_id,
                'request_user_id' => Auth::id(),
            ]);
            abort(403);
        }
        
        // Cache key includes report update timestamp
        $cacheKey = "report_advanced_html_{$reportId}_v{$report->updated_at->timestamp}";
        
        try {
            // Cache for 24 hours
            $html = Cache::remember($cacheKey, now()->addHours(24), function () use ($report) {
                // Get DOCX path
                $docxPath = $this->getDocxPath($report->report_file_path);
                
                if (!file_exists($docxPath)) {
                    throw new \Exception("DOCX not found: {$docxPath}");
                }
                
                Log::info('Converting DOCX to HTML', [
                    'report_id' => $report->id,
                    'docx_path' => $docxPath,
                    'file_size' => filesize($docxPath),
                    'converter' => 'Pandoc (95-98% format)',
                ]);
                
                // ✅ Use Pandoc converter (95-98% format preservation)
                // Falls back to AdvancedDocxToHtmlConverter if Pandoc not available
                try {
                    $converter = new PandocDocxToHtmlConverter();
                    return $converter->convert($docxPath);
                } catch (\Exception $e) {
                    Log::warning('Pandoc conversion failed, falling back to PhpWord', [
                        'error' => $e->getMessage(),
                        'report_id' => $report->id,
                    ]);
                    
                    // Fallback to PhpWord (85-90% format)
                    $converter = new AdvancedDocxToHtmlConverter();
                    return $converter->convert($docxPath);
                }
            });
            
            Log::info('HTML preview generated successfully', [
                'report_id' => $report->id,
                'html_length' => strlen($html),
                'cache_key' => $cacheKey,
            ]);
            
            return response($html)
                ->header('Content-Type', 'text/html; charset=utf-8')
                ->header('Cache-Control', 'private, max-age=86400')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type');
                
        } catch (\Exception $e) {
            Log::error('Failed to generate HTML preview', [
                'error' => $e->getMessage(),
                'report_id' => $report->id,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Failed to generate HTML preview: ' . $e->getMessage()
            ], 500);
        }
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
     * Preview DOCX file (with CORS headers for frontend)
     * API: GET /api/reports/{reportId}/preview
     * 
     * @param Request $request
     * @param int $reportId
     * @return \Illuminate\Http\Response
     */
    public function preview(Request $request, $reportId)
    {
        // ✅ LOG: Request preview
        Log::info('Report preview requested', [
            'report_id' => $reportId,
            'user_id' => Auth::id(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        $report = UserReport::findOrFail($reportId);
        
        // ✅ LOG: Report found
        Log::info('Report found for preview', [
            'report_id' => $report->id,
            'user_id' => $report->user_id,
            'session_id' => $report->chat_session_id,
            'report_file_path' => $report->report_file_path,
            'file_format' => $report->file_format,
            'report_content_length' => strlen($report->report_content ?? ''),
            'created_at' => $report->created_at,
            'updated_at' => $report->updated_at,
        ]);
        
        // Check permission
        if ($report->user_id !== Auth::id()) {
            Log::warning('Unauthorized preview request', [
                'report_id' => $reportId,
                'report_user_id' => $report->user_id,
                'request_user_id' => Auth::id(),
            ]);
            abort(403, 'Unauthorized');
        }
        
        if (!$report->report_file_path || $report->file_format !== 'docx') {
            Log::warning('Report file path missing or wrong format', [
                'report_id' => $report->id,
                'report_file_path' => $report->report_file_path,
                'file_format' => $report->file_format,
            ]);
            return response()->json([
                'error' => 'DOCX file not found for this report'
            ], 404);
        }
        
        try {
            // Parse URL to get file path
            $url = parse_url($report->report_file_path);
            $path = $url['path'] ?? $report->report_file_path;
            $filePath = preg_replace('#^/storage/#', '', $path);
            $filePath = ltrim($filePath, '/');
            $fullPath = Storage::disk('public')->path($filePath);
            
            // ✅ LOG: File path resolution
            Log::info('Resolving DOCX file path', [
                'report_id' => $report->id,
                'report_file_path' => $report->report_file_path,
                'parsed_path' => $path,
                'file_path' => $filePath,
                'full_path' => $fullPath,
                'file_exists' => file_exists($fullPath),
                'file_size' => file_exists($fullPath) ? filesize($fullPath) : 0,
            ]);
            
            if (!file_exists($fullPath)) {
                Log::error('DOCX file not found on server', [
                    'report_id' => $report->id,
                    'report_file_path' => $report->report_file_path,
                    'full_path' => $fullPath,
                ]);
                return response()->json([
                    'error' => 'DOCX file not found on server'
                ], 404);
            }
            
            // ✅ LOG: Serving file
            Log::info('Serving DOCX file for preview', [
                'report_id' => $report->id,
                'file_path' => $filePath,
                'file_size' => filesize($fullPath),
                'file_modified' => date('Y-m-d H:i:s', filemtime($fullPath)),
            ]);
            
            // Return file with CORS headers for preview
            // Use response()->file() to serve file without forcing download
            $response = response()->file($fullPath, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ]);
            
            // Add CORS headers
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
            
            return $response;
        } catch (\Exception $e) {
            Log::error('Failed to preview DOCX', [
                'error' => $e->getMessage(),
                'report_id' => $report->id,
            ]);
            
            return response()->json([
                'error' => 'Failed to preview DOCX: ' . $e->getMessage()
            ], 500);
        }
    }
}



