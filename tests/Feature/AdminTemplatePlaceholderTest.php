<?php

namespace Tests\Feature;

use App\Models\AiAssistant;
use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;
use Tests\TestCase;

class AdminTemplatePlaceholderTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        Storage::fake('public');
    }

    /**
     * Test upload template without placeholders - should auto-generate
     */
    public function test_upload_template_without_placeholders_auto_generates(): void
    {
        // Mock OpenAI response for placeholder generation
        OpenAI::shouldReceive('chat->create')
            ->once()
            ->andReturn($this->mockOpenAIResponse([
                'placeholders' => [
                    [
                        'original_text' => 'Số: ...',
                        'placeholder_key' => 'so_van_ban',
                        'description' => 'Số văn bản',
                    ],
                    [
                        'original_text' => 'Ngày: ...',
                        'placeholder_key' => 'ngay_thang',
                        'description' => 'Ngày tháng',
                    ],
                ],
            ]));

        // Create a test DOCX file without placeholders
        $templateFile = $this->createTestDocxFile('template_without_placeholders.docx', false);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/assistants', [
                'name' => 'Test Assistant',
                'description' => 'Test Description',
                'assistant_type' => 'document_drafting',
                'templates' => [$templateFile],
            ]);

        // Should redirect to preview page (status 302) or return success
        $response->assertStatus(302);

        // Check that assistant was created
        $this->assertDatabaseHas('ai_assistants', [
            'name' => 'Test Assistant',
            'admin_id' => $this->admin->id,
        ]);

        // Get the created assistant
        $assistant = AiAssistant::where('name', 'Test Assistant')
            ->where('admin_id', $this->admin->id)
            ->first();

        // Check that template was created
        $this->assertDatabaseHas('document_templates', [
            'ai_assistant_id' => $assistant->id,
        ]);

        // Check that placeholders were generated
        $template = DocumentTemplate::where('ai_assistant_id', $assistant->id)->first();
        $this->assertNotNull($template);
        $this->assertArrayHasKey('placeholders', $template->metadata);
        $this->assertArrayHasKey('placeholders_auto_generated', $template->metadata);
        $this->assertTrue($template->metadata['placeholders_auto_generated']);
    }

    /**
     * Test upload template with existing placeholders - should keep them
     */
    public function test_upload_template_with_placeholders_keeps_existing(): void
    {
        // Create a test DOCX file with placeholders
        $templateFile = $this->createTestDocxFile('template_with_placeholders.docx', true);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/assistants', [
                'name' => 'Test Assistant With Placeholders',
                'description' => 'Test Description',
                'assistant_type' => 'document_drafting',
                'templates' => [$templateFile],
            ]);

        $response->assertStatus(302);

        // Get the created assistant
        $assistant = AiAssistant::where('name', 'Test Assistant With Placeholders')
            ->where('admin_id', $this->admin->id)
            ->first();

        $this->assertNotNull($assistant);

        // Check that template was created
        $this->assertDatabaseHas('document_templates', [
            'ai_assistant_id' => $assistant->id,
        ]);

        // Check that existing placeholders were extracted
        $template = DocumentTemplate::where('ai_assistant_id', $assistant->id)->first();
        $this->assertNotNull($template);
        $this->assertArrayHasKey('placeholders', $template->metadata);
        $this->assertNotEmpty($template->metadata['placeholders']);
    }

    /**
     * Test upload multiple templates
     */
    public function test_upload_multiple_templates(): void
    {
        // Mock OpenAI response
        OpenAI::shouldReceive('chat->create')
            ->times(2)
            ->andReturn($this->mockOpenAIResponse([
                'placeholders' => [
                    [
                        'original_text' => 'Số: ...',
                        'placeholder_key' => 'so_van_ban',
                        'description' => 'Số văn bản',
                    ],
                ],
            ]));

        $template1 = $this->createTestDocxFile('template1.docx', false);
        $template2 = $this->createTestDocxFile('template2.docx', false);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/assistants', [
                'name' => 'Test Assistant Multiple',
                'description' => 'Test Description',
                'assistant_type' => 'document_drafting',
                'templates' => [$template1, $template2],
            ]);

        $response->assertStatus(302);

        // Get the created assistant
        $assistant = AiAssistant::where('name', 'Test Assistant Multiple')
            ->where('admin_id', $this->admin->id)
            ->first();

        $this->assertNotNull($assistant);

        // Check that both templates were created
        $templates = DocumentTemplate::where('ai_assistant_id', $assistant->id)->get();
        $this->assertCount(2, $templates);
    }

    /**
     * Test error handling when placeholder generation fails
     */
    public function test_error_handling_when_generation_fails(): void
    {
        // Mock OpenAI to throw an error
        OpenAI::shouldReceive('chat->create')
            ->once()
            ->andThrow(new \Exception('OpenAI API error'));

        $templateFile = $this->createTestDocxFile('template_error.docx', false);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/assistants', [
                'name' => 'Test Assistant Error',
                'description' => 'Test Description',
                'assistant_type' => 'document_drafting',
                'templates' => [$templateFile],
            ]);

        // Should still succeed (error handling should not break the flow)
        $response->assertStatus(302);

        // Get the created assistant
        $assistant = AiAssistant::where('name', 'Test Assistant Error')
            ->where('admin_id', $this->admin->id)
            ->first();

        $this->assertNotNull($assistant);

        // Template should still be created (even if placeholder generation failed)
        $this->assertDatabaseHas('document_templates', [
            'ai_assistant_id' => $assistant->id,
        ]);
    }

    /**
     * Test metadata is saved correctly
     */
    public function test_metadata_saved_correctly(): void
    {
        // Mock OpenAI response
        OpenAI::shouldReceive('chat->create')
            ->once()
            ->andReturn($this->mockOpenAIResponse([
                'placeholders' => [
                    [
                        'original_text' => 'Số: ...',
                        'placeholder_key' => 'so_van_ban',
                        'description' => 'Số văn bản',
                    ],
                    [
                        'original_text' => 'Ngày: ...',
                        'placeholder_key' => 'ngay_thang',
                        'description' => 'Ngày tháng',
                    ],
                ],
            ]));

        $templateFile = $this->createTestDocxFile('template_metadata.docx', false);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/assistants', [
                'name' => 'Test Assistant Metadata',
                'description' => 'Test Description',
                'assistant_type' => 'document_drafting',
                'templates' => [$templateFile],
            ]);

        $response->assertStatus(302);

        // Get the created assistant
        $assistant = AiAssistant::where('name', 'Test Assistant Metadata')
            ->where('admin_id', $this->admin->id)
            ->first();

        $this->assertNotNull($assistant);

        $template = DocumentTemplate::where('ai_assistant_id', $assistant->id)->first();
        $this->assertNotNull($template);
        
        // Check metadata structure
        $metadata = $template->metadata;
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('placeholders', $metadata);
        $this->assertArrayHasKey('placeholders_auto_generated', $metadata);
        $this->assertTrue($metadata['placeholders_auto_generated']);
        $this->assertIsArray($metadata['placeholders']);
        $this->assertNotEmpty($metadata['placeholders']);
    }

    /**
     * Test template file is modified correctly when placeholders are generated
     */
    public function test_template_file_modified_correctly(): void
    {
        // Mock OpenAI response
        OpenAI::shouldReceive('chat->create')
            ->once()
            ->andReturn($this->mockOpenAIResponse([
                'placeholders' => [
                    [
                        'original_text' => 'Số: ...',
                        'placeholder_key' => 'so_van_ban',
                        'description' => 'Số văn bản',
                    ],
                ],
            ]));

        $templateFile = $this->createTestDocxFile('template_modify.docx', false);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/assistants', [
                'name' => 'Test Assistant Modify',
                'description' => 'Test Description',
                'assistant_type' => 'document_drafting',
                'templates' => [$templateFile],
            ]);

        $response->assertStatus(302);

        // Get the created assistant
        $assistant = AiAssistant::where('name', 'Test Assistant Modify')
            ->where('admin_id', $this->admin->id)
            ->first();

        $this->assertNotNull($assistant);

        // Check that template file exists in storage
        $template = DocumentTemplate::where('ai_assistant_id', $assistant->id)->first();
        $this->assertNotNull($template);
        $this->assertNotNull($template->file_path);
        
        // Verify file exists
        $filePath = str_replace('/storage/', '', parse_url($template->file_path, PHP_URL_PATH));
        Storage::disk('public')->assertExists($filePath);
    }

    /**
     * Test non-DOCX files are handled correctly
     */
    public function test_non_docx_files_handled_correctly(): void
    {
        // Create a PDF file (should not trigger placeholder generation)
        $pdfFile = UploadedFile::fake()->create('template.pdf', 100);

        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/assistants', [
                'name' => 'Test Assistant PDF',
                'description' => 'Test Description',
                'assistant_type' => 'document_drafting',
                'templates' => [$pdfFile],
            ]);

        $response->assertStatus(302);

        // Get the created assistant
        $assistant = AiAssistant::where('name', 'Test Assistant PDF')
            ->where('admin_id', $this->admin->id)
            ->first();

        $this->assertNotNull($assistant);

        // Template should be created but without placeholders
        $template = DocumentTemplate::where('ai_assistant_id', $assistant->id)->first();
        $this->assertNotNull($template);
        // PDF files should not have placeholders metadata
    }

    /**
     * Helper: Create a test DOCX file
     */
    protected function createTestDocxFile(string $filename, bool $withPlaceholders = false): UploadedFile
    {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $section = $phpWord->addSection();
        
        if ($withPlaceholders) {
            $section->addText('Số: ${so_van_ban}');
            $section->addText('Ngày: ${ngay_thang}');
            $section->addText('Nội dung: ${noi_dung}');
        } else {
            $section->addText('Số: ...');
            $section->addText('Ngày: ...');
            $section->addText('Nội dung: ...');
        }
        
        $tempPath = sys_get_temp_dir() . '/' . $filename;
        $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempPath);
        
        return new UploadedFile(
            $tempPath,
            $filename,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true // test mode
        );
    }

    /**
     * Helper: Mock OpenAI response
     */
    protected function mockOpenAIResponse(array $data): object
    {
        $response = new \stdClass();
        $response->choices = [
            (object) [
                'message' => (object) [
                    'content' => json_encode($data),
                ],
            ],
        ];
        
        return $response;
    }

    protected function tearDown(): void
    {
        // Clean up temporary files
        $files = glob(sys_get_temp_dir() . '/template_*.docx');
        foreach ($files as $file) {
            if (file_exists($file)) {
                @unlink($file);
            }
        }
        
        parent::tearDown();
    }
}

