<?php

namespace Tests\Unit\Services;

use App\Models\DocumentChunk;
use App\Services\VectorSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class VectorSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    protected VectorSearchService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VectorSearchService();
        Cache::flush();
    }

    public function test_can_calculate_cosine_similarity(): void
    {
        $vectorA = [1, 0, 0];
        $vectorB = [1, 0, 0];
        
        $similarity = $this->service->cosineSimilarity($vectorA, $vectorB);
        
        $this->assertEquals(1.0, $similarity);
    }

    public function test_cosine_similarity_returns_zero_for_orthogonal_vectors(): void
    {
        $vectorA = [1, 0, 0];
        $vectorB = [0, 1, 0];
        
        $similarity = $this->service->cosineSimilarity($vectorA, $vectorB);
        
        $this->assertEquals(0.0, $similarity);
    }

    public function test_cosine_similarity_handles_different_length_vectors(): void
    {
        $vectorA = [1, 2, 3];
        $vectorB = [4, 5];
        
        $similarity = $this->service->cosineSimilarity($vectorA, $vectorB);
        
        $this->assertEquals(0.0, $similarity);
    }

    public function test_cosine_similarity_returns_value_between_zero_and_one(): void
    {
        $vectorA = [1, 2, 3];
        $vectorB = [4, 5, 6];
        
        $similarity = $this->service->cosineSimilarity($vectorA, $vectorB);
        
        $this->assertGreaterThanOrEqual(0.0, $similarity);
        $this->assertLessThanOrEqual(1.0, $similarity);
    }
}








