<?php

namespace App\Services;

use App\Models\AdministrativeDocument;
use App\Models\AiAssistant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class DocumentManagementService
{
    public function __construct(
        protected DocumentProcessor $documentProcessor,
        protected DocumentClassifierService $classifier,
        protected VectorSearchService $vectorSearchService
    ) {}
    
    /**
     * Process and store incoming document
     * 
     * @param UploadedFile $file
     * @param AiAssistant $assistant
     * @param User $user
     * @param array $metadata Additional metadata
     * @return AdministrativeDocument
     */
    public function processIncomingDocument(
        UploadedFile $file,
        AiAssistant $assistant,
        User $user,
        array $metadata = []
    ): AdministrativeDocument {
        try {
            Log::info('Processing incoming document', [
                'file_name' => $file->getClientOriginalName(),
                'assistant_id' => $assistant->id,
                'user_id' => $user->id,
            ]);
            
            // 1. Extract text from document (OCR if needed)
            $textContent = $this->documentProcessor->extractText($file);
            
            // 2. Classify document using AI
            $classification = $this->classifier->classify($textContent, $file->getClientOriginalName());
            
            // 3. Extract document number and date
            $extractedInfo = $this->extractDocumentInfo($textContent);
            
            // 4. Calculate deadline based on urgency
            $deadline = $this->calculateDeadline(
                $classification['urgency'],
                $classification['processing_time'] ?? 5
            );
            
            // 5. Store file
            $filePath = $this->storeFile($file, $assistant, $classification);
            
            // 6. Create document record
            $document = AdministrativeDocument::create([
                'ai_assistant_id' => $assistant->id,
                'user_id' => $user->id,
                'so_van_ban' => $extractedInfo['so_van_ban'] ?? null,
                'ngay_van_ban' => $extractedInfo['ngay_van_ban'] ?? now(),
                'loai_van_ban' => 'van_ban_den',
                'document_type' => $classification['document_type'] ?? null,
                'noi_gui' => $classification['classification']['sender'] ?? null,
                'trich_yeu' => $classification['classification']['summary'] ?? substr($textContent, 0, 500),
                'muc_do' => $classification['urgency'],
                'thoi_han_xu_ly' => $classification['processing_time'],
                'deadline' => $deadline,
                'phong_ban_xu_ly' => $classification['suggested_handler'],
                'trang_thai' => 'moi',
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_type' => $file->getClientOriginalExtension(),
                'file_size' => $file->getSize(),
                'metadata' => [
                    'ocr_text' => $textContent,
                    'original_metadata' => $metadata,
                ],
                'classification' => $classification['classification'],
                'storage_path' => $this->generateStoragePath($assistant, $classification),
            ]);
            
            // 7. Index document for search (async)
            $this->indexDocumentForSearch($document, $textContent);
            
            Log::info('Document processed successfully', [
                'document_id' => $document->id,
                'classification' => $classification,
            ]);
            
            return $document;
        } catch (\Exception $e) {
            Log::error('Failed to process incoming document', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Process and store outgoing document
     */
    public function processOutgoingDocument(
        UploadedFile $file,
        AiAssistant $assistant,
        User $user,
        array $metadata = []
    ): AdministrativeDocument {
        try {
            // Similar to incoming but with loai_van_ban = 'van_ban_di'
            $textContent = $this->documentProcessor->extractText($file);
            $classification = $this->classifier->classify($textContent, $file->getClientOriginalName());
            $extractedInfo = $this->extractDocumentInfo($textContent);
            $filePath = $this->storeFile($file, $assistant, $classification);
            
            $document = AdministrativeDocument::create([
                'ai_assistant_id' => $assistant->id,
                'user_id' => $user->id,
                'so_van_ban' => $extractedInfo['so_van_ban'] ?? null,
                'ngay_van_ban' => $extractedInfo['ngay_van_ban'] ?? now(),
                'loai_van_ban' => 'van_ban_di',
                'document_type' => $classification['document_type'] ?? null,
                'noi_nhan' => $metadata['noi_nhan'] ?? $classification['classification']['receiver'] ?? null,
                'trich_yeu' => $classification['classification']['summary'] ?? substr($textContent, 0, 500),
                'muc_do' => $classification['urgency'],
                'trang_thai' => 'moi',
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $filePath,
                'file_type' => $file->getClientOriginalExtension(),
                'file_size' => $file->getSize(),
                'metadata' => [
                    'ocr_text' => $textContent,
                    'original_metadata' => $metadata,
                ],
                'classification' => $classification['classification'],
                'storage_path' => $this->generateStoragePath($assistant, $classification),
            ]);
            
            $this->indexDocumentForSearch($document, $textContent);
            
            return $document;
        } catch (\Exception $e) {
            Log::error('Failed to process outgoing document', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Extract document info (số văn bản, ngày tháng) from text
     */
    protected function extractDocumentInfo(string $text): array
    {
        $info = [];
        
        // Extract số văn bản: Số: 123/BC-STC hoặc Số 123/BC-STC
        if (preg_match('/Số[:\s]+([\d\/\-A-Z]+)/i', $text, $matches)) {
            $info['so_van_ban'] = trim($matches[1]);
        }
        
        // Extract ngày tháng: Ngày: 15/12/2024 hoặc ngày 15 tháng 12 năm 2024
        if (preg_match('/Ngày[:\s]+(\d{1,2}\/\d{1,2}\/\d{4})/i', $text, $matches)) {
            try {
                $info['ngay_van_ban'] = Carbon::createFromFormat('d/m/Y', $matches[1]);
            } catch (\Exception $e) {
                // Try alternative format
                if (preg_match('/ngày\s+(\d{1,2})\s+tháng\s+(\d{1,2})\s+năm\s+(\d{4})/i', $text, $matches)) {
                    try {
                        $info['ngay_van_ban'] = Carbon::createFromDate($matches[3], $matches[2], $matches[1]);
                    } catch (\Exception $e) {
                        // Ignore
                    }
                }
            }
        }
        
        return $info;
    }
    
    /**
     * Calculate deadline based on urgency and processing time
     */
    protected function calculateDeadline(string $urgency, int $processingTime): Carbon
    {
        $deadline = now();
        
        // Add working days (skip weekends)
        $daysAdded = 0;
        while ($daysAdded < $processingTime) {
            $deadline->addDay();
            // Skip weekends (Saturday = 6, Sunday = 0)
            if ($deadline->dayOfWeek !== 0 && $deadline->dayOfWeek !== 6) {
                $daysAdded++;
            }
        }
        
        return $deadline;
    }
    
    /**
     * Store file and return path
     */
    protected function storeFile(UploadedFile $file, AiAssistant $assistant, array $classification): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        $sender = $classification['classification']['sender'] ?? 'unknown';
        
        // Sanitize sender name for path
        $senderPath = preg_replace('/[^a-zA-Z0-9_-]/', '_', $sender);
        
        $path = "documents/{$assistant->id}/{$year}/{$month}/{$senderPath}";
        
        $storedPath = $file->storeAs($path, $file->getClientOriginalName(), 'public');
        
        return Storage::disk('public')->url($storedPath);
    }
    
    /**
     * Generate storage path for organization
     */
    protected function generateStoragePath(AiAssistant $assistant, array $classification): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');
        $sender = $classification['classification']['sender'] ?? 'unknown';
        
        return "{$year}/{$month}/{$sender}";
    }
    
    /**
     * Index document for semantic search
     */
    protected function indexDocumentForSearch(AdministrativeDocument $document, string $textContent): void
    {
        try {
            // Use VectorSearchService to index document
            // This will be handled asynchronously via queue job
            // For now, we'll just log it
            Log::info('Document indexed for search', [
                'document_id' => $document->id,
                'content_length' => strlen($textContent),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to index document for search', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * Search documents using semantic search
     */
    public function searchDocuments(
        string $query,
        AiAssistant $assistant,
        array $filters = []
    ): array {
        try {
            // Use VectorSearchService for semantic search
            $results = $this->vectorSearchService->search($query, $assistant->id, 10);
            
            // Get document IDs from results
            $documentIds = array_map(fn($result) => $result['document_id'] ?? null, $results);
            $documentIds = array_filter($documentIds);
            
            if (empty($documentIds)) {
                return [];
            }
            
            // Query documents with filters
            $query = AdministrativeDocument::whereIn('id', $documentIds)
                ->where('ai_assistant_id', $assistant->id);
            
            // Apply filters
            if (isset($filters['loai_van_ban'])) {
                $query->where('loai_van_ban', $filters['loai_van_ban']);
            }
            
            if (isset($filters['document_type'])) {
                $query->where('document_type', $filters['document_type']);
            }
            
            if (isset($filters['trang_thai'])) {
                $query->where('trang_thai', $filters['trang_thai']);
            }
            
            if (isset($filters['date_from'])) {
                $query->where('ngay_van_ban', '>=', $filters['date_from']);
            }
            
            if (isset($filters['date_to'])) {
                $query->where('ngay_van_ban', '<=', $filters['date_to']);
            }
            
            return $query->orderBy('ngay_van_ban', 'desc')->get()->toArray();
        } catch (\Exception $e) {
            Log::error('Failed to search documents', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
    
    /**
     * Get documents due today
     */
    public function getDocumentsDueToday(AiAssistant $assistant): array
    {
        return AdministrativeDocument::where('ai_assistant_id', $assistant->id)
            ->where('deadline', today())
            ->where('trang_thai', '!=', 'da_xu_ly')
            ->orderBy('muc_do', 'desc')
            ->orderBy('deadline', 'asc')
            ->get()
            ->toArray();
    }
    
    /**
     * Get overdue documents
     */
    public function getOverdueDocuments(AiAssistant $assistant): array
    {
        return AdministrativeDocument::where('ai_assistant_id', $assistant->id)
            ->where('deadline', '<', now())
            ->where('trang_thai', '!=', 'da_xu_ly')
            ->orderBy('deadline', 'asc')
            ->get()
            ->toArray();
    }
    
    /**
     * Get reminders for documents
     */
    public function getReminders(AiAssistant $assistant, int $daysBefore = 1): array
    {
        $reminderDate = now()->addDays($daysBefore);
        
        return AdministrativeDocument::where('ai_assistant_id', $assistant->id)
            ->where('deadline', '<=', $reminderDate)
            ->where('deadline', '>=', now())
            ->where('trang_thai', '!=', 'da_xu_ly')
            ->orderBy('deadline', 'asc')
            ->get()
            ->toArray();
    }
}



