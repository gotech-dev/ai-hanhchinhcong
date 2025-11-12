<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class GeminiWebSearchService
{
    /**
     * Search web and generate answer using Gemini API with Google Search
     *
     * @param string $question
     * @param array $context Additional context (assistant info, etc.)
     * @return array{answer: string, sources: array, search_results: array}
     */
    public function searchAndAnswer(string $question, array $context = []): array
    {
        try {
            $apiKey = env('GOOGLE_AI_API_KEY');
            
            if (!$apiKey) {
                Log::warning('Google AI API key not configured, falling back to ChatGPT');
                return $this->fallbackToChatGPT($question, $context);
            }

            // Build prompt with context
            $prompt = $this->buildPrompt($question, $context);

            // Call Gemini API with Google Search
            // Using gemini-1.5-flash for better performance and cost
            $model = env('GEMINI_MODEL', 'gemini-1.5-flash');
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
            
            $response = Http::timeout(60)->post($url, [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt
                            ]
                        ]
                    ]
                ],
                'tools' => [
                    [
                        'googleSearchRetrieval' => []
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.3,
                    'topK' => 40,
                    'topP' => 0.95,
                    'maxOutputTokens' => 2048,
                ],
            ]);

            if (!$response->successful()) {
                $errorBody = $response->json();
                Log::error('Gemini API error', [
                    'status' => $response->status(),
                    'error' => $errorBody['error']['message'] ?? $response->body(),
                    'question' => substr($question, 0, 100),
                ]);
                return $this->fallbackToChatGPT($question, $context);
            }

            $result = $response->json();

            // Extract answer from response
            $answer = $this->extractAnswer($result);
            
            // Extract sources/search results
            $sources = $this->extractSources($result);

            Log::info('Gemini web search completed', [
                'question' => substr($question, 0, 100),
                'answer_length' => strlen($answer),
                'sources_count' => count($sources),
            ]);

            return [
                'answer' => $answer,
                'sources' => $sources,
                'search_results' => $sources, // Alias for compatibility
            ];

        } catch (\Exception $e) {
            Log::error('Gemini web search error', [
                'error' => $e->getMessage(),
                'question' => substr($question, 0, 100),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->fallbackToChatGPT($question, $context);
        }
    }

    /**
     * Build prompt for Gemini
     */
    protected function buildPrompt(string $question, array $context): string
    {
        $prompt = "Bạn là một trợ lý AI chuyên nghiệp phục vụ trong lĩnh vực hành chính công.\n\n";
        
        if (isset($context['assistant_name'])) {
            $prompt .= "Tên trợ lý: {$context['assistant_name']}\n";
        }
        
        if (isset($context['assistant_description'])) {
            $prompt .= "Mô tả: {$context['assistant_description']}\n";
        }
        
        $prompt .= "\n**NHIỆM VỤ:**\n";
        $prompt .= "Trả lời câu hỏi của người dùng dựa trên thông tin tìm được trên mạng (Google Search).\n";
        $prompt .= "Hãy trả lời một cách chính xác, chi tiết và chuyên nghiệp.\n";
        $prompt .= "Sử dụng ngôn ngữ lịch sự, phù hợp với môi trường hành chính công.\n";
        $prompt .= "Nếu không tìm thấy thông tin cụ thể, hãy trả lời dựa trên kiến thức chung của bạn.\n\n";
        
        $prompt .= "**CÂU HỎI:**\n{$question}\n\n";
        $prompt .= "Hãy tìm kiếm thông tin trên mạng và trả lời câu hỏi một cách đầy đủ và chính xác.";

        return $prompt;
    }

    /**
     * Extract answer from Gemini response
     */
    protected function extractAnswer(array $response): string
    {
        // Check for candidates
        if (!isset($response['candidates']) || empty($response['candidates'])) {
            Log::warning('Gemini response has no candidates', [
                'response_keys' => array_keys($response),
            ]);
            return 'Xin lỗi, tôi không thể tìm thấy thông tin để trả lời câu hỏi này.';
        }

        // Check for content
        if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            Log::warning('Unexpected Gemini response format', [
                'response_structure' => [
                    'has_candidates' => isset($response['candidates']),
                    'candidates_count' => count($response['candidates'] ?? []),
                    'first_candidate_keys' => isset($response['candidates'][0]) ? array_keys($response['candidates'][0]) : [],
                ],
            ]);
            return 'Xin lỗi, tôi không thể tìm thấy thông tin để trả lời câu hỏi này.';
        }

        $answer = $response['candidates'][0]['content']['parts'][0]['text'];
        
        // Check for safety ratings (Gemini may block content)
        if (isset($response['candidates'][0]['safetyRatings'])) {
            $blocked = false;
            foreach ($response['candidates'][0]['safetyRatings'] as $rating) {
                if (($rating['probability'] ?? '') === 'HIGH' && 
                    in_array($rating['category'] ?? '', ['HARM_CATEGORY_DANGEROUS_CONTENT', 'HARM_CATEGORY_HARASSMENT'])) {
                    $blocked = true;
                    break;
                }
            }
            
            if ($blocked) {
                Log::warning('Gemini blocked content due to safety ratings', [
                    'ratings' => $response['candidates'][0]['safetyRatings'],
                ]);
                return 'Xin lỗi, tôi không thể trả lời câu hỏi này do các quy định về an toàn nội dung.';
            }
        }

        return $answer;
    }

    /**
     * Extract sources from Gemini response
     */
    protected function extractSources(array $response): array
    {
        $sources = [];

        if (!isset($response['candidates'][0])) {
            return $this->getDefaultSource();
        }

        $candidate = $response['candidates'][0];

        // Gemini với Google Search trả về sources trong groundingMetadata
        if (isset($candidate['groundingMetadata'])) {
            $metadata = $candidate['groundingMetadata'];

            // Extract search queries
            if (isset($metadata['webSearchQueries']) && is_array($metadata['webSearchQueries'])) {
                foreach ($metadata['webSearchQueries'] as $index => $query) {
                    $sources[] = [
                        'title' => "Tìm kiếm: {$query}",
                        'snippet' => "Query được sử dụng để tìm kiếm trên Google",
                        'url' => null,
                        'index' => $index,
                        'type' => 'search_query',
                    ];
                }
            }

            // Extract grounding chunks (actual search results)
            if (isset($metadata['groundingChunks']) && is_array($metadata['groundingChunks'])) {
                foreach ($metadata['groundingChunks'] as $index => $chunk) {
                    if (isset($chunk['web'])) {
                        $web = $chunk['web'];
                        $sources[] = [
                            'title' => $web['title'] ?? 'Nguồn thông tin',
                            'snippet' => $web['snippet'] ?? '',
                            'url' => $web['uri'] ?? null,
                            'index' => count($sources),
                            'type' => 'web_result',
                        ];
                    }
                }
            }
        }

        // Nếu không có sources, tạo một source generic
        if (empty($sources)) {
            return $this->getDefaultSource();
        }

        return $sources;
    }

    /**
     * Get default source when no sources found
     */
    protected function getDefaultSource(): array
    {
        return [
            [
                'title' => 'Thông tin từ Google Search',
                'snippet' => 'Kết quả tìm kiếm từ Google thông qua Gemini AI',
                'url' => null,
                'index' => 0,
                'type' => 'default',
            ],
        ];
    }

    /**
     * Fallback to ChatGPT if Gemini fails
     */
    protected function fallbackToChatGPT(string $question, array $context): array
    {
        Log::info('Falling back to ChatGPT for web search', [
            'question' => substr($question, 0, 100),
        ]);

        try {
            $assistant = $context['assistant'] ?? null;
            $systemPrompt = "Bạn là một trợ lý AI chuyên nghiệp phục vụ trong lĩnh vực hành chính công.\n\n";
            $systemPrompt .= "**NHIỆM VỤ:** Trả lời câu hỏi của người dùng dựa trên kiến thức của bạn.\n";
            $systemPrompt .= "Hãy trả lời một cách chính xác, chi tiết và chuyên nghiệp.\n";
            $systemPrompt .= "Sử dụng ngôn ngữ lịch sự, phù hợp với môi trường hành chính công.";

            $response = \OpenAI\Laravel\Facades\OpenAI::chat()->create([
                'model' => $assistant?->config['model'] ?? env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemPrompt,
                    ],
                    [
                        'role' => 'user',
                        'content' => $question,
                    ],
                ],
                'temperature' => 0.3,
            ]);

            $answer = $response->choices[0]->message->content;

            return [
                'answer' => $answer,
                'sources' => [
                    [
                        'title' => 'Kiến thức từ ChatGPT',
                        'snippet' => 'Trả lời dựa trên kiến thức của AI',
                        'url' => null,
                        'index' => 0,
                    ],
                ],
                'search_results' => [],
            ];
        } catch (\Exception $e) {
            Log::error('ChatGPT fallback also failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'answer' => 'Xin lỗi, tôi gặp khó khăn trong việc tìm kiếm thông tin. Vui lòng thử lại sau.',
                'sources' => [],
                'search_results' => [],
            ];
        }
    }
}

