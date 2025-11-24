<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

/**
 * Aspose.Words Cloud API Converter Service
 * 
 * Convert PDF → DOCX using Aspose.Words Cloud API
 * Reference: https://github.com/aspose-words-cloud/aspose-words-cloud-php
 */
class AsposeWordsConverter
{
    protected $clientId;
    protected $clientSecret;
    protected $baseUrl;
    
    public function __construct()
    {
        $this->clientId = config('services.aspose.client_id') ?? env('ASPOSE_CLIENT_ID');
        $this->clientSecret = config('services.aspose.client_secret') ?? env('ASPOSE_CLIENT_SECRET');
        $this->baseUrl = config('services.aspose.base_url', 'https://api.aspose.cloud');
    }
    
    /**
     * Check if Aspose.Words API is configured
     * 
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->clientId) && !empty($this->clientSecret);
    }
    
    /**
     * Convert PDF to DOCX using Aspose.Words Cloud API
     * 
     * @param string|UploadedFile $pdfFile Path to PDF file or UploadedFile instance
     * @return string|null Path to converted DOCX file, or null if conversion failed
     * @throws \Exception If conversion fails
     */
    public function convertPdfToDocx($pdfFile): ?string
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Aspose.Words API is not configured. Please set ASPOSE_CLIENT_ID and ASPOSE_CLIENT_SECRET in .env file.');
        }
        
        try {
            // Get file path
            $pdfPath = is_string($pdfFile) ? $pdfFile : $pdfFile->getRealPath();
            $pdfFileName = is_string($pdfFile) ? basename($pdfFile) : $pdfFile->getClientOriginalName();
            
            if (!file_exists($pdfPath)) {
                throw new \Exception("PDF file not found: {$pdfPath}");
            }
            
            Log::info('📄 [AsposeWordsConverter] Starting PDF → DOCX conversion', [
                'pdf_file' => $pdfFileName,
                'pdf_size' => filesize($pdfPath),
            ]);
            
            // Initialize Aspose.Words API
            $wordsApi = $this->getWordsApi();
            
            // ✅ Method: Direct conversion using file path
            // Create temp PDF file (Aspose API needs file path)
            $tempPdfPath = sys_get_temp_dir() . '/' . uniqid('temp_pdf_') . '.pdf';
            if (!copy($pdfPath, $tempPdfPath)) {
                throw new \Exception("Failed to copy PDF to temp location: {$pdfPath}");
            }
            
            // Convert PDF → DOCX directly
            $convertRequest = new \Aspose\Words\Model\Requests\ConvertDocumentRequest(
                $tempPdfPath,  // File path, not handle
                'docx'
            );
            
            $convertResult = $wordsApi->convertDocument($convertRequest);
            
            // Clean up temp PDF
            if (file_exists($tempPdfPath)) {
                unlink($tempPdfPath);
            }
            
            // ✅ FIX: convertDocument returns SplFileObject
            // Save DOCX to local storage
            $tempDocxPath = sys_get_temp_dir() . '/' . uniqid('aspose_docx_') . '.docx';
            
            if ($convertResult instanceof \SplFileObject) {
                // Read from SplFileObject
                $convertResult->rewind();
                $docxContent = '';
                while (!$convertResult->eof()) {
                    $docxContent .= $convertResult->fread(8192); // Read in chunks
                }
                file_put_contents($tempDocxPath, $docxContent);
            } elseif (is_resource($convertResult)) {
                // Read from resource
                rewind($convertResult);
                $docxContent = stream_get_contents($convertResult);
                file_put_contents($tempDocxPath, $docxContent);
            } else {
                throw new \Exception("Unexpected response type from Aspose API");
            }
            
            Log::info('✅ [AsposeWordsConverter] PDF converted to DOCX (direct method)', [
                'local_path' => $tempDocxPath,
                'file_size' => filesize($tempDocxPath),
            ]);
            
            return $tempDocxPath;
            
        } catch (\Exception $e) {
            Log::error('🔴 [AsposeWordsConverter] PDF → DOCX conversion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Convert PDF to HTML using Aspose.Words Cloud API
     * 
     * @param string|UploadedFile $pdfFile Path to PDF file or UploadedFile instance
     * @return string|null HTML content, or null if conversion failed
     * @throws \Exception If conversion fails
     */
    public function convertPdfToHtml($pdfFile): ?string
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Aspose.Words API is not configured. Please set ASPOSE_CLIENT_ID and ASPOSE_CLIENT_SECRET in .env file.');
        }
        
        try {
            // Get file path
            $pdfPath = is_string($pdfFile) ? $pdfFile : $pdfFile->getRealPath();
            $pdfFileName = is_string($pdfFile) ? basename($pdfFile) : $pdfFile->getClientOriginalName();
            
            if (!file_exists($pdfPath)) {
                throw new \Exception("PDF file not found: {$pdfPath}");
            }
            
            Log::info('📄 [AsposeWordsConverter] Starting PDF → HTML conversion', [
                'pdf_file' => $pdfFileName,
                'pdf_size' => filesize($pdfPath),
            ]);
            
            // Initialize Aspose.Words API
            $wordsApi = $this->getWordsApi();
            
            // ✅ Method: Direct conversion using file path
            // Create temp PDF file (Aspose API needs file path)
            $tempPdfPath = sys_get_temp_dir() . '/' . uniqid('temp_pdf_') . '.pdf';
            if (!copy($pdfPath, $tempPdfPath)) {
                throw new \Exception("Failed to copy PDF to temp location: {$pdfPath}");
            }
            
            // Convert PDF → HTML directly
            $convertRequest = new \Aspose\Words\Model\Requests\ConvertDocumentRequest(
                $tempPdfPath,  // File path, not handle
                'html'
            );
            
            $convertResult = $wordsApi->convertDocument($convertRequest);
            
            // Clean up temp PDF
            if (file_exists($tempPdfPath)) {
                unlink($tempPdfPath);
            }
            
            // ✅ FIX: convertDocument returns SplFileObject
            // Read HTML content
            $htmlContent = '';
            
            if ($convertResult instanceof \SplFileObject) {
                // Read from SplFileObject
                $convertResult->rewind();
                while (!$convertResult->eof()) {
                    $htmlContent .= $convertResult->fread(8192); // Read in chunks
                }
            } elseif (is_resource($convertResult)) {
                // Read from resource
                rewind($convertResult);
                $htmlContent = stream_get_contents($convertResult);
            } else {
                throw new \Exception("Unexpected response type from Aspose API");
            }
            
            Log::info('✅ [AsposeWordsConverter] PDF converted to HTML (direct method)', [
                'html_length' => strlen($htmlContent),
                'html_preview' => mb_substr(strip_tags($htmlContent), 0, 200),
            ]);
            
            return $htmlContent;
            
        } catch (\Exception $e) {
            Log::error('🔴 [AsposeWordsConverter] PDF → HTML conversion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Convert DOCX to HTML using Aspose.Words Cloud API
     * 
     * @param string|UploadedFile $docxFile Path to DOCX file or UploadedFile instance
     * @return string|null HTML content, or null if conversion failed
     * @throws \Exception If conversion fails
     */
    public function convertDocxToHtml($docxFile): ?string
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Aspose.Words API is not configured. Please set ASPOSE_CLIENT_ID and ASPOSE_CLIENT_SECRET in .env file.');
        }
        
        try {
            // Get file path
            $docxPath = is_string($docxFile) ? $docxFile : $docxFile->getRealPath();
            $docxFileName = is_string($docxFile) ? basename($docxFile) : $docxFile->getClientOriginalName();
            
            if (!file_exists($docxPath)) {
                throw new \Exception("DOCX file not found: {$docxPath}");
            }
            
            Log::info('📄 [AsposeWordsConverter] Starting DOCX → HTML conversion', [
                'docx_file' => $docxFileName,
                'docx_size' => filesize($docxPath),
            ]);
            
            // Initialize Aspose.Words API
            $wordsApi = $this->getWordsApi();
            
            // ✅ Method: Direct conversion using file path
            // Create temp DOCX file (Aspose API needs file path)
            $tempDocxPath = sys_get_temp_dir() . '/' . uniqid('temp_docx_') . '.docx';
            if (!copy($docxPath, $tempDocxPath)) {
                throw new \Exception("Failed to copy DOCX to temp location: {$docxPath}");
            }
            
            // Convert DOCX → HTML directly
            $convertRequest = new \Aspose\Words\Model\Requests\ConvertDocumentRequest(
                $tempDocxPath,  // File path, not handle
                'html'
            );
            
            $convertResult = $wordsApi->convertDocument($convertRequest);
            
            // Clean up temp DOCX
            if (file_exists($tempDocxPath)) {
                unlink($tempDocxPath);
            }
            
            // ✅ FIX: convertDocument returns SplFileObject
            // Read HTML content
            $htmlContent = '';
            
            if ($convertResult instanceof \SplFileObject) {
                // Read from SplFileObject
                $convertResult->rewind();
                while (!$convertResult->eof()) {
                    $htmlContent .= $convertResult->fread(8192); // Read in chunks
                }
            } elseif (is_resource($convertResult)) {
                // Read from resource
                rewind($convertResult);
                $htmlContent = stream_get_contents($convertResult);
            } else {
                throw new \Exception("Unexpected response type from Aspose API");
            }
            
            Log::info('✅ [AsposeWordsConverter] DOCX converted to HTML (direct method)', [
                'html_length' => strlen($htmlContent),
                'html_preview' => mb_substr(strip_tags($htmlContent), 0, 200),
            ]);
            
            return $htmlContent;
            
        } catch (\Exception $e) {
            Log::error('🔴 [AsposeWordsConverter] DOCX → HTML conversion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Convert HTML to DOCX using Aspose.Words Cloud API
     * 
     * @param string $htmlContent HTML content string
     * @return string|null Path to converted DOCX file, or null if conversion failed
     * @throws \Exception If conversion fails
     */
    public function convertHtmlToDocx(string $htmlContent): ?string
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Aspose.Words API is not configured. Please set ASPOSE_CLIENT_ID and ASPOSE_CLIENT_SECRET in .env file.');
        }
        
        try {
            Log::info('📄 [AsposeWordsConverter] Starting HTML → DOCX conversion', [
                'html_length' => strlen($htmlContent),
            ]);
            
            // Initialize Aspose.Words API
            $wordsApi = $this->getWordsApi();
            
            // ✅ Save HTML to temp file (Aspose API needs file path)
            $tempHtmlPath = sys_get_temp_dir() . '/' . uniqid('temp_html_') . '.html';
            file_put_contents($tempHtmlPath, $htmlContent);
            
            // Convert HTML → DOCX directly
            $convertRequest = new \Aspose\Words\Model\Requests\ConvertDocumentRequest(
                $tempHtmlPath,  // File path
                'docx'
            );
            
            $convertResult = $wordsApi->convertDocument($convertRequest);
            
            // Clean up temp HTML
            if (file_exists($tempHtmlPath)) {
                unlink($tempHtmlPath);
            }
            
            // ✅ Save DOCX to local storage
            $tempDocxPath = sys_get_temp_dir() . '/' . uniqid('aspose_html_docx_') . '.docx';
            
            if ($convertResult instanceof \SplFileObject) {
                // Read from SplFileObject
                $convertResult->rewind();
                $docxContent = '';
                while (!$convertResult->eof()) {
                    $docxContent .= $convertResult->fread(8192); // Read in chunks
                }
                file_put_contents($tempDocxPath, $docxContent);
            } elseif (is_resource($convertResult)) {
                // Read from resource
                rewind($convertResult);
                $docxContent = stream_get_contents($convertResult);
                file_put_contents($tempDocxPath, $docxContent);
            } else {
                throw new \Exception("Unexpected response type from Aspose API");
            }
            
            Log::info('✅ [AsposeWordsConverter] HTML converted to DOCX', [
                'local_path' => $tempDocxPath,
                'file_size' => filesize($tempDocxPath),
            ]);
            
            return $tempDocxPath;
            
        } catch (\Exception $e) {
            Log::error('🔴 [AsposeWordsConverter] HTML → DOCX conversion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Get Aspose.Words API instance
     * 
     * @return \Aspose\Words\WordsApi
     */
    protected function getWordsApi(): \Aspose\Words\WordsApi
    {
        if (!class_exists('\Aspose\Words\WordsApi')) {
            throw new \Exception('Aspose.Words Cloud PHP SDK is not installed. Please run: composer require aspose-cloud/aspose-words-cloud');
        }
        
        $wordsApi = new \Aspose\Words\WordsApi($this->clientId, $this->clientSecret);
        $wordsApi->getConfig()->setHost($this->baseUrl);
        
        return $wordsApi;
    }
}

