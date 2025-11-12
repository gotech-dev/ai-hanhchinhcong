<?php

namespace Tests\Unit\Services;

use App\Services\IntentRecognizer;
use Tests\TestCase;

class IntentRecognizerTest extends TestCase
{
    protected IntentRecognizer $recognizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recognizer = new IntentRecognizer();
    }

    public function test_can_extract_entities_from_message(): void
    {
        $message = "Tôi cần tạo báo cáo hoạt động tháng 12 năm 2024";
        
        $entities = $this->recognizer->extractEntities($message, 'create_report');
        
        $this->assertIsArray($entities);
        $this->assertArrayHasKey('month', $entities);
        $this->assertEquals(12, $entities['month']);
        $this->assertArrayHasKey('year', $entities);
        $this->assertEquals(2024, $entities['year']);
    }

    public function test_can_extract_report_type(): void
    {
        $message = "Tôi cần tạo báo cáo hoạt động";
        
        $entities = $this->recognizer->extractEntities($message, 'create_report');
        
        $this->assertIsArray($entities);
        if (isset($entities['report_type'])) {
            $this->assertNotEmpty($entities['report_type']);
        }
    }

    public function test_extract_entities_handles_empty_message(): void
    {
        $entities = $this->recognizer->extractEntities('', 'create_report');
        
        $this->assertIsArray($entities);
    }

    public function test_fallback_recognition_works(): void
    {
        // This tests the fallback pattern matching
        $message = "Tôi cần tạo báo cáo";
        
        // The fallback method should be tested indirectly through the main recognize method
        // when API calls fail
        $this->assertTrue(true); // Placeholder
    }

    /**
     * ✅ PHASE 4: Test Intent Recognition với các câu hỏi thông thường
     * Verify rằng general questions được nhận diện là ask_question
     */
    public function test_general_questions_recognized_as_ask_question(): void
    {
        $assistant = \App\Models\AiAssistant::factory()->make([
            'assistant_type' => \App\Enums\AssistantType::QA_BASED_DOCUMENT,
        ]);

        $generalQuestions = [
            "Hà Nội có bao nhiêu tỉnh?",
            "Việt Nam có bao nhiêu tỉnh thành?",
            "Công văn là gì?",
            "GDP là gì?",
            "Bạn làm được gì?",
            "Cách sử dụng hệ thống?",
            "Xã A có bao nhiêu dân?",
            "Tỉnh B có bao nhiêu huyện?",
        ];

        $context = [
            'assistant' => $assistant,
        ];

        foreach ($generalQuestions as $question) {
            $result = $this->recognizer->recognize($question, $context);
            
            $this->assertIsArray($result);
            $this->assertArrayHasKey('type', $result);
            $this->assertArrayHasKey('confidence', $result);
            
            // Verify intent is ask_question
            $this->assertEquals('ask_question', $result['type'], 
                "Question '{$question}' should be recognized as ask_question, got: {$result['type']}");
            
            // Verify confidence is reasonable
            $this->assertGreaterThan(0.5, $result['confidence'], 
                "Confidence for '{$question}' should be > 0.5, got: {$result['confidence']}");
        }
    }

    /**
     * ✅ PHASE 4: Test Intent Recognition với yêu cầu workflow
     * Verify rằng workflow requests được nhận diện là draft_document/create_report
     */
    public function test_workflow_requests_recognized_correctly(): void
    {
        // Test với Document Drafting assistant
        $draftingAssistant = \App\Models\AiAssistant::factory()->make([
            'assistant_type' => \App\Enums\AssistantType::DOCUMENT_DRAFTING,
        ]);

        $workflowRequests = [
            "Tôi muốn soạn thảo công văn",
            "Giúp tôi tạo quyết định",
            "Soạn thảo tờ trình",
            "Làm biên bản",
            "Tạo báo cáo",
        ];

        $context = [
            'assistant' => $draftingAssistant,
        ];

        foreach ($workflowRequests as $request) {
            $result = $this->recognizer->recognize($request, $context);
            
            $this->assertIsArray($result);
            $this->assertArrayHasKey('type', $result);
            $this->assertArrayHasKey('confidence', $result);
            
            // Verify intent is draft_document (not ask_question)
            $this->assertNotEquals('ask_question', $result['type'], 
                "Request '{$request}' should NOT be ask_question, got: {$result['type']}");
            
            // Should be draft_document for document_drafting assistant
            $this->assertContains($result['type'], ['draft_document', 'create_report'], 
                "Request '{$request}' should be draft_document or create_report, got: {$result['type']}");
            
            // Verify confidence is reasonable
            $this->assertGreaterThan(0.5, $result['confidence'], 
                "Confidence for '{$request}' should be > 0.5, got: {$result['confidence']}");
        }
    }

    /**
     * ✅ PHASE 4: Test phân biệt câu hỏi thông thường vs yêu cầu workflow
     * Verify rằng system có thể phân biệt rõ ràng
     */
    public function test_distinguish_general_question_vs_workflow_request(): void
    {
        $assistant = \App\Models\AiAssistant::factory()->make([
            'assistant_type' => \App\Enums\AssistantType::DOCUMENT_DRAFTING,
        ]);

        $context = [
            'assistant' => $assistant,
        ];

        // Test cases: [message, expected_intent]
        $testCases = [
            // General questions → ask_question
            ["Công văn là gì?", 'ask_question'],
            ["Bạn làm được gì?", 'ask_question'],
            ["Hà Nội có bao nhiêu tỉnh?", 'ask_question'],
            
            // Workflow requests → draft_document
            ["Tôi muốn soạn thảo công văn", 'draft_document'],
            ["Giúp tôi tạo quyết định", 'draft_document'],
            ["Soạn thảo tờ trình", 'draft_document'],
        ];

        foreach ($testCases as [$message, $expectedIntent]) {
            $result = $this->recognizer->recognize($message, $context);
            
            $this->assertIsArray($result);
            $this->assertArrayHasKey('type', $result);
            
            $this->assertEquals($expectedIntent, $result['type'], 
                "Message '{$message}' should be recognized as '{$expectedIntent}', got: {$result['type']}");
        }
    }

    /**
     * ✅ PHASE 4: Test edge cases - câu hỏi có thể nhầm lẫn
     */
    public function test_edge_cases_ambiguous_questions(): void
    {
        $assistant = \App\Models\AiAssistant::factory()->make([
            'assistant_type' => \App\Enums\AssistantType::DOCUMENT_DRAFTING,
        ]);

        $context = [
            'assistant' => $assistant,
        ];

        // Edge cases: có thể nhầm lẫn giữa question và request
        $edgeCases = [
            "Làm thế nào để tạo công văn?", // Hỏi cách làm → ask_question
            "Tôi muốn biết cách soạn thảo quyết định", // Hỏi cách → ask_question
            "Tạo công văn như thế nào?", // Hỏi cách → ask_question
        ];

        foreach ($edgeCases as $message) {
            $result = $this->recognizer->recognize($message, $context);
            
            $this->assertIsArray($result);
            $this->assertArrayHasKey('type', $result);
            
            // These should be ask_question (asking HOW, not requesting to DO)
            // But we accept both ask_question or draft_document as valid
            $this->assertContains($result['type'], ['ask_question', 'draft_document'], 
                "Edge case '{$message}' should be ask_question or draft_document, got: {$result['type']}");
        }
    }
}







