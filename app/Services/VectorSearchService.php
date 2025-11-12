<?php

namespace App\Services;

use App\Models\DocumentChunk;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class VectorSearchService
{
    /**
     * Create embedding for text using OpenAI
     *
     * @param string $text
     * @param string $model
     * @return array
     */
    public function createEmbedding(string $text, string $model = 'text-embedding-ada-002'): array
    {
        // Clean and normalize text
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $text); // Remove control characters
        $text = trim($text);
        
        // Cache key based on text hash
        $cacheKey = 'embedding:' . md5($text . $model);
        
        return Cache::remember($cacheKey, now()->addDays(30), function () use ($text, $model) {
            try {
                $response = OpenAI::embeddings()->create([
                    'model' => $model,
                    'input' => $text,
                ]);
                
                return $response->embeddings[0]->embedding;
            } catch (\Exception $e) {
                Log::error('Failed to create embedding', [
                    'error' => $e->getMessage(),
                    'text' => substr($text, 0, 100),
                ]);
                
                throw new \Exception("Failed to create embedding: " . $e->getMessage());
            }
        });
    }

    /**
     * Create embeddings for multiple texts
     *
     * @param array<string> $texts
     * @param string $model
     * @return array<array>
     */
    public function createEmbeddings(array $texts, string $model = 'text-embedding-ada-002'): array
    {
        // Clean and normalize all texts first
        $cleanedTexts = [];
        foreach ($texts as $index => $text) {
            try {
                // Clean and normalize text
                $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
                $text = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $text); // Remove control characters
                $text = trim($text);
                
                if (empty($text)) {
                    Log::warning('Skipping empty text chunk', ['index' => $index]);
                    $cleanedTexts[] = '';
                    continue;
                }
                
                $cleanedTexts[] = $text;
            } catch (\Exception $e) {
                Log::warning('Failed to clean text chunk', [
                    'index' => $index,
                    'error' => $e->getMessage(),
                ]);
                $cleanedTexts[] = '';
            }
        }
        
        // Filter out empty texts
        $validTexts = array_filter($cleanedTexts, fn($text) => !empty($text));
        $validIndices = array_keys($validTexts);
        
        if (empty($validTexts)) {
            Log::warning('All texts are empty after cleaning');
            return array_fill(0, count($texts), []);
        }
        
        try {
            $response = OpenAI::embeddings()->create([
                'model' => $model,
                'input' => array_values($validTexts),
            ]);
            
            // Map embeddings back to original indices
            $embeddings = array_fill(0, count($texts), []);
            foreach ($validIndices as $i => $originalIndex) {
                $embeddings[$originalIndex] = $response->embeddings[$i]->embedding;
            }
            
            return $embeddings;
        } catch (\Exception $e) {
            Log::error('Failed to create embeddings', [
                'error' => $e->getMessage(),
                'count' => count($texts),
                'valid_count' => count($validTexts),
            ]);
            
            throw new \Exception("Failed to create embeddings: " . $e->getMessage());
        }
    }

    /**
     * Search similar chunks using cosine similarity
     * Works with MySQL/PostgreSQL JSON storage
     *
     * @param string $query
     * @param int $assistantId
     * @param int $limit
     * @param float $minSimilarity
     * @param array $metadataFilter Optional metadata filter (e.g., ['source_type' => 'reference_url'])
     * @return array
     */
    public function searchSimilar(string $query, int $assistantId, int $limit = 5, float $minSimilarity = 0.7, array $metadataFilter = []): array
    {
        // Create embedding for query
        $queryEmbedding = $this->createEmbedding($query);
        
        // Get all document chunks for this assistant
        $chunks = DocumentChunk::query()
            ->whereHas('assistantDocument', function ($q) use ($assistantId) {
                $q->where('ai_assistant_id', $assistantId);
            })
            ->whereNotNull('embedding')
            ->get();
        
        // Calculate cosine similarity for each chunk
        $results = [];
        foreach ($chunks as $chunk) {
            // Apply metadata filter if provided
            if (!empty($metadataFilter)) {
                $chunkMetadata = $chunk->metadata ?? [];
                $matches = true;
                foreach ($metadataFilter as $key => $value) {
                    if (($chunkMetadata[$key] ?? null) !== $value) {
                        $matches = false;
                        break;
                    }
                }
                if (!$matches) {
                    continue;
                }
            }
            
            $similarity = $this->cosineSimilarity($queryEmbedding, $chunk->embedding);
            
            if ($similarity >= $minSimilarity) {
                $results[] = [
                    'chunk' => $chunk,
                    'similarity' => $similarity,
                    'content' => $chunk->content,
                    'metadata' => $chunk->metadata ?? [],
                ];
            }
        }
        
        // Sort by similarity (descending)
        usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);
        
        // Return top N results
        return array_slice($results, 0, $limit);
    }

    /**
     * Calculate cosine similarity between two vectors
     *
     * @param array $vectorA
     * @param array $vectorB
     * @return float
     */
    public function cosineSimilarity(array $vectorA, array $vectorB): float
    {
        if (count($vectorA) !== count($vectorB)) {
            return 0.0;
        }
        
        $dotProduct = 0;
        $magnitudeA = 0;
        $magnitudeB = 0;
        
        for ($i = 0; $i < count($vectorA); $i++) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $magnitudeA += $vectorA[$i] * $vectorA[$i];
            $magnitudeB += $vectorB[$i] * $vectorB[$i];
        }
        
        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);
        
        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0.0;
        }
        
        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * Save chunk with embedding to database
     *
     * @param int $assistantDocumentId
     * @param int $chunkIndex
     * @param string $content
     * @param array $embedding
     * @param array|null $metadata
     * @return DocumentChunk
     */
    public function saveChunk(int $assistantDocumentId, int $chunkIndex, string $content, array $embedding, ?array $metadata = null): DocumentChunk
    {
        // Clean and normalize content before saving
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        $content = preg_replace('/[\x00-\x1F\x7F-\x9F]/u', '', $content); // Remove control characters
        $content = trim($content);
        
        // Remove invalid UTF-8 characters
        // FILTER_SANITIZE_STRING is deprecated, use htmlspecialchars instead
        $content = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
        
        return DocumentChunk::create([
            'assistant_document_id' => $assistantDocumentId,
            'chunk_index' => $chunkIndex,
            'content' => $content,
            'embedding' => $embedding,
            'metadata' => $metadata ?? [],
        ]);
    }

    /**
     * Index document with chunks and embeddings
     *
     * @param int $documentId
     * @param array<string> $chunks
     * @return void
     */
    public function indexDocument(int $documentId, array $chunks): void
    {
        if (empty($chunks)) {
            return;
        }
        
        // Create embeddings for all chunks
        $embeddings = $this->createEmbeddings($chunks);
        
        // Save chunks with embeddings
        foreach ($chunks as $index => $chunk) {
            $this->saveChunk(
                $documentId,
                $index,
                $chunk,
                $embeddings[$index] ?? [],
                [
                    'chunk_index' => $index,
                    'total_chunks' => count($chunks),
                ]
            );
        }
    }
}

