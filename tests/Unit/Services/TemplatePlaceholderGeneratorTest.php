<?php

namespace Tests\Unit\Services;

use App\Services\DocumentProcessor;
use App\Services\TemplatePlaceholderGenerator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;
use Tests\TestCase;

class TemplatePlaceholderGeneratorTest extends TestCase
{
    protected TemplatePlaceholderGenerator $generator;
    protected DocumentProcessor $documentProcessor;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        
        $this->documentProcessor = new DocumentProcessor();
        $this->generator = new TemplatePlaceholderGenerator($this->documentProcessor);
    }

    /**
     * Test extractExistingPlaceholders with template that has placeholders
     */
    public function test_extract_existing_placeholders_with_placeholders(): void
    {
        // Create a test DOCX file with placeholders
        $templatePath = $this->createTestDocxWithPlaceholders();
        
        $placeholders = $this->generator->extractExistingPlaceholders($templatePath);
        
        $this->assertIsArray($placeholders);
        $this->assertNotEmpty($placeholders);
        $this->assertArrayHasKey('${so_van_ban}', $placeholders);
        $this->assertEquals('so_van_ban', $placeholders['${so_van_ban}']);
    }

    /**
     * Test extractExistingPlaceholders with template that has no placeholders
     */
    public function test_extract_existing_placeholders_without_placeholders(): void
    {
        // Create a test DOCX file without placeholders
        $templatePath = $this->createTestDocxWithoutPlaceholders();
        
        $placeholders = $this->generator->extractExistingPlaceholders($templatePath);
        
        $this->assertIsArray($placeholders);
        // Should return empty array or empty placeholders
        $this->assertEmpty($placeholders);
    }

    /**
     * Test normalizePlaceholderKey
     */
    public function test_normalize_placeholder_key(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('normalizePlaceholderKey');
        $method->setAccessible(true);
        
        $this->assertEquals('so_van_ban', $method->invoke($this->generator, 'Số Văn Bản'));
        $this->assertEquals('ngay_thang', $method->invoke($this->generator, 'Ngày Tháng'));
        $this->assertEquals('ten_co_quan', $method->invoke($this->generator, 'Tên Cơ Quan'));
        $this->assertEquals('noi_dung', $method->invoke($this->generator, 'Nội Dung'));
        $this->assertEquals('test_key', $method->invoke($this->generator, 'Test Key!!!'));
        $this->assertEquals('key_with_123', $method->invoke($this->generator, 'Key With 123'));
    }

    /**
     * Test removeVietnameseAccents
     */
    public function test_remove_vietnamese_accents(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('removeVietnameseAccents');
        $method->setAccessible(true);
        
        $this->assertEquals('so_van_ban', $method->invoke($this->generator, 'số_văn_bản'));
        $this->assertEquals('ngay_thang', $method->invoke($this->generator, 'ngày_tháng'));
        $this->assertEquals('ten_co_quan', $method->invoke($this->generator, 'tên_cơ_quan'));
    }

    /**
     * Test simpleReplaceInXml
     */
    public function test_simple_replace_in_xml(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('simpleReplaceInXml');
        $method->setAccessible(true);
        
        $xml = '<w:t>Số: ...</w:t><w:t>Ngày: ...</w:t>';
        $mappings = [
            'Số: ...' => 'so_van_ban',
            'Ngày: ...' => 'ngay_thang',
        ];
        
        $result = $method->invoke($this->generator, $xml, $mappings);
        
        $this->assertStringContainsString('${so_van_ban}', $result);
        $this->assertStringContainsString('${ngay_thang}', $result);
        $this->assertStringNotContainsString('Số: ...', $result);
        $this->assertStringNotContainsString('Ngày: ...', $result);
    }

    /**
     * Test getModifiedPath
     */
    public function test_get_modified_path(): void
    {
        $reflection = new \ReflectionClass($this->generator);
        $method = $reflection->getMethod('getModifiedPath');
        $method->setAccessible(true);
        
        $originalPath = '/path/to/template.docx';
        $modifiedPath = $method->invoke($this->generator, $originalPath);
        
        $this->assertStringContainsString('template_modified_', $modifiedPath);
        $this->assertStringEndsWith('.docx', $modifiedPath);
        $this->assertNotEquals($originalPath, $modifiedPath);
    }

    /**
     * Test generatePlaceholders returns existing placeholders if present
     */
    public function test_generate_placeholders_returns_existing_if_present(): void
    {
        $templatePath = $this->createTestDocxWithPlaceholders();
        
        $placeholders = $this->generator->generatePlaceholders($templatePath);
        
        $this->assertIsArray($placeholders);
        $this->assertNotEmpty($placeholders);
        // Should return existing placeholders, not generate new ones
        $this->assertArrayHasKey('${so_van_ban}', $placeholders);
    }

    /**
     * Test generatePlaceholders with empty template
     */
    public function test_generate_placeholders_with_empty_template(): void
    {
        $templatePath = $this->createEmptyTestDocx();
        
        $placeholders = $this->generator->generatePlaceholders($templatePath);
        
        $this->assertIsArray($placeholders);
        $this->assertEmpty($placeholders);
    }

    /**
     * Test error handling when template file doesn't exist
     */
    public function test_generate_placeholders_handles_missing_file(): void
    {
        $nonExistentPath = '/path/to/nonexistent/file.docx';
        
        $placeholders = $this->generator->generatePlaceholders($nonExistentPath);
        
        $this->assertIsArray($placeholders);
        $this->assertEmpty($placeholders);
    }

    /**
     * Test error handling when extractExistingPlaceholders fails
     */
    public function test_extract_existing_placeholders_handles_errors(): void
    {
        $invalidPath = '/path/to/invalid/file.docx';
        
        $placeholders = $this->generator->extractExistingPlaceholders($invalidPath);
        
        $this->assertIsArray($placeholders);
        $this->assertEmpty($placeholders);
    }

    /**
     * Helper: Create a test DOCX file with placeholders
     */
    protected function createTestDocxWithPlaceholders(): string
    {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Số: ${so_van_ban}');
        $section->addText('Ngày: ${ngay_thang}');
        $section->addText('Nội dung: ${noi_dung}');
        
        $tempPath = sys_get_temp_dir() . '/test_with_placeholders_' . uniqid() . '.docx';
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempPath);
        
        return $tempPath;
    }

    /**
     * Helper: Create a test DOCX file without placeholders
     */
    protected function createTestDocxWithoutPlaceholders(): string
    {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Số: ...');
        $section->addText('Ngày: ...');
        $section->addText('Nội dung: ...');
        
        $tempPath = sys_get_temp_dir() . '/test_without_placeholders_' . uniqid() . '.docx';
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempPath);
        
        return $tempPath;
    }

    /**
     * Helper: Create an empty test DOCX file
     */
    protected function createEmptyTestDocx(): string
    {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->addSection();
        
        $tempPath = sys_get_temp_dir() . '/test_empty_' . uniqid() . '.docx';
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempPath);
        
        return $tempPath;
    }

    protected function tearDown(): void
    {
        // Clean up temporary files
        $files = glob(sys_get_temp_dir() . '/test_*.docx');
        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        
        parent::tearDown();
    }
}



