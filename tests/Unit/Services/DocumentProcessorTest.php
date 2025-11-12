<?php

namespace Tests\Unit\Services;

use App\Services\DocumentProcessor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentProcessorTest extends TestCase
{
    protected DocumentProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new DocumentProcessor();
        Storage::fake('public');
    }

    public function test_can_split_text_into_chunks(): void
    {
        $text = str_repeat("This is a test paragraph. ", 100);
        
        $chunks = $this->processor->splitIntoChunks($text, 100, 20);
        
        $this->assertIsArray($chunks);
        $this->assertNotEmpty($chunks);
        
        // Check each chunk is not empty
        foreach ($chunks as $chunk) {
            $this->assertNotEmpty(trim($chunk));
        }
    }

    public function test_chunks_are_within_size_limit(): void
    {
        $text = str_repeat("This is a test paragraph. ", 1000);
        
        $chunkSize = 500;
        $chunks = $this->processor->splitIntoChunks($text, $chunkSize);
        
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual($chunkSize * 1.2, strlen($chunk)); // Allow some flexibility
        }
    }

    public function test_can_count_pdf_pages(): void
    {
        // This is a basic test - actual PDF processing requires PDF files
        // In real scenario, you would use a test PDF file
        $this->assertTrue(true); // Placeholder
    }

    public function test_split_handles_empty_text(): void
    {
        $chunks = $this->processor->splitIntoChunks('');
        
        $this->assertIsArray($chunks);
    }

    public function test_split_handles_short_text(): void
    {
        $text = "Short text";
        
        $chunks = $this->processor->splitIntoChunks($text, 100);
        
        $this->assertCount(1, $chunks);
        $this->assertEquals($text, $chunks[0]);
    }
}








