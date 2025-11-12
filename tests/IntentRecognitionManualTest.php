<?php

/**
 * ✅ PHASE 4: Manual Test Script for Intent Recognition
 * 
 * Test Intent Recognition với các câu hỏi khác nhau để verify accuracy
 * 
 * Usage: php artisan tinker
 * Then: require 'tests/IntentRecognitionManualTest.php';
 * Then: runTestCases();
 */

use App\Services\IntentRecognizer;
use App\Models\AiAssistant;
use App\Enums\AssistantType;

function runTestCases()
{
    $recognizer = new IntentRecognizer();
    
    echo "🧪 Testing Intent Recognition - Phase 4\n";
    echo "========================================\n\n";
    
    // Test 1: General Questions
    echo "📋 Test 1: General Questions → ask_question\n";
    echo "-------------------------------------------\n";
    
    $qaAssistant = AiAssistant::where('assistant_type', AssistantType::QA_BASED_DOCUMENT->value)->first();
    if (!$qaAssistant) {
        echo "⚠️  Q&A Assistant not found, creating mock...\n";
        $qaAssistant = new AiAssistant();
        $qaAssistant->assistant_type = AssistantType::QA_BASED_DOCUMENT;
        $qaAssistant->name = 'Q&A Test Assistant';
    }
    
    $generalQuestions = [
        "Hà Nội có bao nhiêu tỉnh?",
        "Việt Nam có bao nhiêu tỉnh thành?",
        "Công văn là gì?",
        "GDP là gì?",
        "Bạn làm được gì?",
        "Cách sử dụng hệ thống?",
    ];
    
    $context = ['assistant' => $qaAssistant];
    $passed = 0;
    $failed = 0;
    
    foreach ($generalQuestions as $question) {
        try {
            $result = $recognizer->recognize($question, $context);
            $isCorrect = $result['type'] === 'ask_question';
            $confidence = $result['confidence'] ?? 0;
            
            if ($isCorrect) {
                echo "✅ '{$question}' → ask_question (confidence: {$confidence})\n";
                $passed++;
            } else {
                echo "❌ '{$question}' → {$result['type']} (expected: ask_question, confidence: {$confidence})\n";
                $failed++;
            }
        } catch (\Exception $e) {
            echo "⚠️  Error testing '{$question}': {$e->getMessage()}\n";
            $failed++;
        }
    }
    
    echo "\n📊 Test 1 Results: {$passed} passed, {$failed} failed\n\n";
    
    // Test 2: Workflow Requests
    echo "📋 Test 2: Workflow Requests → draft_document/create_report\n";
    echo "------------------------------------------------------------\n";
    
    $draftingAssistant = AiAssistant::where('assistant_type', AssistantType::DOCUMENT_DRAFTING->value)->first();
    if (!$draftingAssistant) {
        echo "⚠️  Document Drafting Assistant not found, creating mock...\n";
        $draftingAssistant = new AiAssistant();
        $draftingAssistant->assistant_type = AssistantType::DOCUMENT_DRAFTING;
        $draftingAssistant->name = 'Document Drafting Test Assistant';
    }
    
    $workflowRequests = [
        "Tôi muốn soạn thảo công văn",
        "Giúp tôi tạo quyết định",
        "Soạn thảo tờ trình",
        "Làm biên bản",
        "Tạo báo cáo",
    ];
    
    $context = ['assistant' => $draftingAssistant];
    $passed2 = 0;
    $failed2 = 0;
    
    foreach ($workflowRequests as $request) {
        try {
            $result = $recognizer->recognize($request, $context);
            $isCorrect = in_array($result['type'], ['draft_document', 'create_report']) && 
                         $result['type'] !== 'ask_question';
            $confidence = $result['confidence'] ?? 0;
            
            if ($isCorrect) {
                echo "✅ '{$request}' → {$result['type']} (confidence: {$confidence})\n";
                $passed2++;
            } else {
                echo "❌ '{$request}' → {$result['type']} (expected: draft_document/create_report, confidence: {$confidence})\n";
                $failed2++;
            }
        } catch (\Exception $e) {
            echo "⚠️  Error testing '{$request}': {$e->getMessage()}\n";
            $failed2++;
        }
    }
    
    echo "\n📊 Test 2 Results: {$passed2} passed, {$failed2} failed\n\n";
    
    // Test 3: Distinguish Test
    echo "📋 Test 3: Distinguish General Question vs Workflow Request\n";
    echo "------------------------------------------------------------\n";
    
    $testCases = [
        ["Công văn là gì?", 'ask_question'],
        ["Bạn làm được gì?", 'ask_question'],
        ["Hà Nội có bao nhiêu tỉnh?", 'ask_question'],
        ["Tôi muốn soạn thảo công văn", 'draft_document'],
        ["Giúp tôi tạo quyết định", 'draft_document'],
        ["Soạn thảo tờ trình", 'draft_document'],
    ];
    
    $passed3 = 0;
    $failed3 = 0;
    
    foreach ($testCases as [$message, $expectedIntent]) {
        try {
            $result = $recognizer->recognize($message, $context);
            $isCorrect = $result['type'] === $expectedIntent;
            $confidence = $result['confidence'] ?? 0;
            
            if ($isCorrect) {
                echo "✅ '{$message}' → {$result['type']} (expected: {$expectedIntent}, confidence: {$confidence})\n";
                $passed3++;
            } else {
                echo "❌ '{$message}' → {$result['type']} (expected: {$expectedIntent}, confidence: {$confidence})\n";
                $failed3++;
            }
        } catch (\Exception $e) {
            echo "⚠️  Error testing '{$message}': {$e->getMessage()}\n";
            $failed3++;
        }
    }
    
    echo "\n📊 Test 3 Results: {$passed3} passed, {$failed3} failed\n\n";
    
    // Summary
    $totalPassed = $passed + $passed2 + $passed3;
    $totalFailed = $failed + $failed2 + $failed3;
    $totalTests = $totalPassed + $totalFailed;
    $accuracy = $totalTests > 0 ? round(($totalPassed / $totalTests) * 100, 2) : 0;
    
    echo "========================================\n";
    echo "📊 SUMMARY\n";
    echo "========================================\n";
    echo "Total Tests: {$totalTests}\n";
    echo "Passed: {$totalPassed} ✅\n";
    echo "Failed: {$totalFailed} ❌\n";
    echo "Accuracy: {$accuracy}%\n";
    echo "========================================\n";
    
    return [
        'total' => $totalTests,
        'passed' => $totalPassed,
        'failed' => $totalFailed,
        'accuracy' => $accuracy,
    ];
}


