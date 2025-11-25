<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class AiRewriteController extends Controller
{
    /**
     * Rewrite text with AI based on user instruction
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function rewrite(Request $request)
    {
        $request->validate([
            'selected_text' => 'required|string|max:5000',
            'instruction' => 'required|string|max:500',
        ]);
        
        $selectedText = $request->input('selected_text');
        $instruction = $request->input('instruction');
        
        Log::info('🔵 [AiRewrite] Rewrite request', [
            'selected_text_length' => mb_strlen($selectedText),
            'instruction' => $instruction,
        ]);
        
        try {
            $response = OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Bạn là trợ lý viết văn bản hành chính công. Nhiệm vụ của bạn là viết lại đoạn văn theo yêu cầu của người dùng.

QUAN TRỌNG - QUY TẮC FORMAT:
1. GIỮ NGUYÊN cấu trúc format của đoạn văn gốc
2. Nếu đoạn văn gốc có các bullet points (bắt đầu bằng "-"), MỖI bullet point PHẢI nằm trên MỘT DÒNG RIÊNG
3. KHÔNG gộp nhiều bullet points vào cùng một dòng
4. CHỈ trả về đoạn văn đã viết lại, KHÔNG thêm giải thích, ghi chú, hay ký tự đặc biệt'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Đoạn văn gốc:\n\"{$selectedText}\"\n\nYêu cầu: {$instruction}\n\nViết lại đoạn văn theo yêu cầu (giữ nguyên format, mỗi bullet point trên một dòng riêng):"
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens' => 2000,
            ]);
            
            $rewrittenText = $response->choices[0]->message->content;
            
            // Clean up the response
            $rewrittenText = trim($rewrittenText);
            // Remove quotes if wrapped
            $rewrittenText = preg_replace('/^["\']+|["\']+$/', '', $rewrittenText);
            // Remove markdown code blocks if present
            $rewrittenText = preg_replace('/^```[\w]*\n?|\n?```$/', '', $rewrittenText);
            $rewrittenText = trim($rewrittenText);
            
            // ✅ FIX: Đảm bảo mỗi bullet point nằm trên dòng riêng
            // Nếu AI trả về text với " - " liền nhau (không có line break), thêm line break
            // Pattern: text ends with "." or ")" followed by " - " → add line break before "-"
            $rewrittenText = preg_replace('/([.)\]])(\s*)(-\s)/u', "$1\n$3", $rewrittenText);
            
            // Also handle case where there's already newline but with extra spaces
            $rewrittenText = preg_replace('/\n\s*-\s/u', "\n- ", $rewrittenText);
            
            // ✅ FIX: Remove multiple consecutive newlines (max 1 newline)
            $rewrittenText = preg_replace('/\n{3,}/u', "\n\n", $rewrittenText);
            
            // ✅ FIX: Remove leading/trailing newlines (already trimmed, but check again)
            $rewrittenText = trim($rewrittenText);
            
            Log::info('🔵 [AiRewrite] Text after format fix', [
                'hasNewlines' => str_contains($rewrittenText, "\n"),
                'bulletCount' => substr_count($rewrittenText, "\n-"),
            ]);
            
            Log::info('✅ [AiRewrite] Rewrite successful', [
                'original_length' => mb_strlen($selectedText),
                'rewritten_length' => mb_strlen($rewrittenText),
            ]);
            
            return response()->json([
                'success' => true,
                'result_text' => $rewrittenText,
            ]);
            
        } catch (\Exception $e) {
            Log::error('🔴 [AiRewrite] Rewrite failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Không thể viết lại văn bản: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Summarize text with AI
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function summarize(Request $request)
    {
        $request->validate([
            'selected_text' => 'required|string|max:5000',
            'instruction' => 'nullable|string|max:500',
        ]);
        
        $selectedText = $request->input('selected_text');
        $instruction = $request->input('instruction', 'Tóm tắt ngắn gọn trong 2-3 câu');
        
        try {
            $response = OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Bạn là trợ lý tóm tắt văn bản hành chính công. Nhiệm vụ của bạn là tóm tắt đoạn văn một cách ngắn gọn, súc tích. CHỈ trả về bản tóm tắt, KHÔNG thêm giải thích hay ghi chú.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Đoạn văn cần tóm tắt:\n\"{$selectedText}\"\n\nYêu cầu: {$instruction}\n\nTóm tắt:"
                    ],
                ],
                'temperature' => 0.5,
                'max_tokens' => 500,
            ]);
            
            $summarizedText = trim($response->choices[0]->message->content);
            $summarizedText = preg_replace('/^["\']+|["\']+$/', '', $summarizedText);
            $summarizedText = preg_replace('/^```[\w]*\n?|\n?```$/', '', $summarizedText);
            $summarizedText = trim($summarizedText);
            
            return response()->json([
                'success' => true,
                'result_text' => $summarizedText,
            ]);
            
        } catch (\Exception $e) {
            Log::error('🔴 [AiRewrite] Summarize failed', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Không thể tóm tắt văn bản: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Expand text with AI
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function expand(Request $request)
    {
        $request->validate([
            'selected_text' => 'required|string|max:5000',
            'instruction' => 'nullable|string|max:500',
        ]);
        
        $selectedText = $request->input('selected_text');
        $instruction = $request->input('instruction', 'Mở rộng thêm chi tiết');
        
        try {
            $response = OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Bạn là trợ lý viết văn bản hành chính công. Nhiệm vụ của bạn là mở rộng đoạn văn với thêm chi tiết, số liệu, ví dụ cụ thể. CHỈ trả về đoạn văn đã mở rộng, KHÔNG thêm giải thích hay ghi chú.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Đoạn văn cần mở rộng:\n\"{$selectedText}\"\n\nYêu cầu: {$instruction}\n\nMở rộng đoạn văn:"
                    ],
                ],
                'temperature' => 0.7,
                'max_tokens' => 2000,
            ]);
            
            $expandedText = trim($response->choices[0]->message->content);
            $expandedText = preg_replace('/^["\']+|["\']+$/', '', $expandedText);
            $expandedText = preg_replace('/^```[\w]*\n?|\n?```$/', '', $expandedText);
            $expandedText = trim($expandedText);
            
            return response()->json([
                'success' => true,
                'result_text' => $expandedText,
            ]);
            
        } catch (\Exception $e) {
            Log::error('🔴 [AiRewrite] Expand failed', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Không thể mở rộng văn bản: ' . $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Fix grammar and spelling errors
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fixGrammar(Request $request)
    {
        $request->validate([
            'selected_text' => 'required|string|max:5000',
            'instruction' => 'nullable|string|max:500',
        ]);
        
        $selectedText = $request->input('selected_text');
        
        try {
            $response = OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Bạn là trợ lý sửa lỗi chính tả và ngữ pháp cho văn bản hành chính công. Nhiệm vụ của bạn là sửa tất cả lỗi chính tả, ngữ pháp, nhưng GIỮ NGUYÊN nội dung và ý nghĩa. CHỈ trả về đoạn văn đã sửa, KHÔNG thêm giải thích hay ghi chú.'
                    ],
                    [
                        'role' => 'user',
                        'content' => "Đoạn văn cần sửa lỗi:\n\"{$selectedText}\"\n\nSửa lỗi chính tả và ngữ pháp:"
                    ],
                ],
                'temperature' => 0.3,
                'max_tokens' => 2000,
            ]);
            
            $fixedText = trim($response->choices[0]->message->content);
            $fixedText = preg_replace('/^["\']+|["\']+$/', '', $fixedText);
            $fixedText = preg_replace('/^```[\w]*\n?|\n?```$/', '', $fixedText);
            $fixedText = trim($fixedText);
            
            return response()->json([
                'success' => true,
                'result_text' => $fixedText,
            ]);
            
        } catch (\Exception $e) {
            Log::error('🔴 [AiRewrite] Fix grammar failed', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Không thể sửa lỗi: ' . $e->getMessage(),
            ], 500);
        }
    }
}

