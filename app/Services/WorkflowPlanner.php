<?php

namespace App\Services;

use App\Models\AiAssistant;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class WorkflowPlanner
{
    /**
     * Plan workflow based on intent and assistant config
     *
     * @param array $intent {type: string, entity: array, confidence: float}
     * @param AiAssistant $assistant
     * @param array $currentData
     * @return array{steps: array, estimated_time: int}
     */
    public function plan(array $intent, AiAssistant $assistant, array $currentData = []): array
    {
        // ✅ MỚI: Nếu assistant có steps được định nghĩa, sử dụng chúng
        $config = $assistant->config ?? [];
        $predefinedSteps = $config['steps'] ?? null;

        if ($predefinedSteps && !empty($predefinedSteps)) {
            return $this->planWithPredefinedSteps($predefinedSteps, $intent, $currentData);
        }

        // Fallback về logic cũ nếu không có steps
        // If assistant type is Q&A based, use simpler workflow
        // ✅ FIX: Convert enum to string
        $assistantType = $assistant->getAssistantTypeValue();
        
        if ($assistantType === 'qa_based_document') {
            return $this->planQaWorkflow($intent, $assistant);
        }
        
        // For report generator, use AI to plan workflow
        return $this->planReportWorkflow($intent, $assistant, $currentData);
    }

    /**
     * Plan workflow for Q&A based assistant
     *
     * @param array $intent
     * @param AiAssistant $assistant
     * @return array
     */
    protected function planQaWorkflow(array $intent, AiAssistant $assistant): array
    {
        return [
            'steps' => [
                [
                    'id' => 'search_documents',
                    'type' => 'search',
                    'action' => 'search_in_documents',
                    'required' => true,
                    'dependencies' => [],
                ],
                [
                    'id' => 'generate_answer',
                    'type' => 'generate',
                    'action' => 'generate_answer',
                    'required' => true,
                    'dependencies' => ['search_documents'],
                ],
            ],
            'estimated_time' => 5,
        ];
    }

    /**
     * Plan workflow for report generator assistant
     *
     * @param array $intent
     * @param AiAssistant $assistant
     * @param array $currentData
     * @return array
     */
    protected function planReportWorkflow(array $intent, AiAssistant $assistant, array $currentData): array
    {
        $config = $assistant->config ?? [];
        $templateFields = $config['template_fields'] ?? [];
        
        // If intent is create_report, plan information collection workflow
        if ($intent['type'] === 'create_report') {
            return $this->planReportCreationWorkflow($templateFields, $currentData);
        }
        
        // For other intents, use AI to plan
        return $this->planWithAI($intent, $assistant, $currentData);
    }

    /**
     * Plan workflow for report creation
     *
     * @param array $templateFields
     * @param array $currentData
     * @return array
     */
    protected function planReportCreationWorkflow(array $templateFields, array $currentData): array
    {
        $steps = [];
        $stepId = 1;
        
        // Check what fields are missing
        $missingFields = [];
        foreach ($templateFields as $field) {
            $fieldKey = $field['key'] ?? $field;
            if (!isset($currentData[$fieldKey]) || empty($currentData[$fieldKey])) {
                $missingFields[] = $field;
            }
        }
        
        // If all fields are collected, go straight to generation
        if (empty($missingFields)) {
            return [
                'steps' => [
                    [
                        'id' => 'generate_report',
                        'type' => 'generate',
                        'action' => 'create_report_from_template',
                        'required' => true,
                        'dependencies' => [],
                    ],
                ],
                'estimated_time' => 30,
            ];
        }
        
        // Plan steps to collect missing information
        foreach ($missingFields as $field) {
            $fieldKey = is_array($field) ? ($field['key'] ?? 'field') : $field;
            $fieldLabel = is_array($field) ? ($field['label'] ?? $fieldKey) : $fieldKey;
            
            $steps[] = [
                'id' => "collect_{$fieldKey}",
                'type' => 'collect_info',
                'action' => 'ask_about_' . $fieldKey,
                'field' => $fieldKey,
                'label' => $fieldLabel,
                'required' => is_array($field) ? ($field['required'] ?? true) : true,
                'dependencies' => $stepId > 1 ? ["collect_{$templateFields[$stepId - 2]['key']}"] : [],
            ];
            
            $stepId++;
        }
        
        // Add final generation step
        $steps[] = [
            'id' => 'generate_report',
            'type' => 'generate',
            'action' => 'create_report_from_template',
            'required' => true,
            'dependencies' => array_map(fn($field) => 'collect_' . (is_array($field) ? ($field['key'] ?? 'field') : $field), $missingFields),
        ];
        
        return [
            'steps' => $steps,
            'estimated_time' => count($steps) * 30, // 30 seconds per step
        ];
    }

    /**
     * Plan workflow using AI
     *
     * @param array $intent
     * @param AiAssistant $assistant
     * @param array $currentData
     * @return array
     */
    protected function planWithAI(array $intent, AiAssistant $assistant, array $currentData): array
    {
        try {
            $prompt = $this->buildWorkflowPrompt($intent, $assistant, $currentData);
            
            $response = OpenAI::chat()->create([
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Bạn là một AI chuyên lập kế hoạch workflow. Phân tích intent và tạo workflow phù hợp. Trả về JSON với format: {"steps": [...], "estimated_time": number}',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'temperature' => 0.3,
                'response_format' => ['type' => 'json_object'],
            ]);
            
            $content = $response->choices[0]->message->content;
            $result = json_decode($content, true);
            
            if (!$result || !isset($result['steps'])) {
                throw new \Exception('Invalid workflow response');
            }
            
            return [
                'steps' => $result['steps'],
                'estimated_time' => $result['estimated_time'] ?? 60,
            ];
        } catch (\Exception $e) {
            Log::error('Workflow planning failed', [
                'error' => $e->getMessage(),
                'intent' => $intent,
            ]);
            
            // Fallback to simple workflow
            return [
                'steps' => [
                    [
                        'id' => 'process_request',
                        'type' => 'process',
                        'action' => 'handle_request',
                        'required' => true,
                        'dependencies' => [],
                    ],
                ],
                'estimated_time' => 30,
            ];
        }
    }

    /**
     * Plan workflow with predefined steps
     *
     * @param array $steps
     * @param array $intent
     * @param array $currentData
     * @return array
     */
    protected function planWithPredefinedSteps(array $steps, array $intent, array $currentData): array
    {
        // Sắp xếp steps theo order
        usort($steps, fn($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));

        // Lọc steps dựa trên dependencies và collected data
        $filteredSteps = [];
        $completedStepIds = $currentData['completed_steps'] ?? [];

        foreach ($steps as $step) {
            // Kiểm tra dependencies
            $dependencies = $step['dependencies'] ?? [];
            $canExecute = true;
            foreach ($dependencies as $depId) {
                if (!in_array($depId, $completedStepIds)) {
                    $canExecute = false;
                    break;
                }
            }

            // Kiểm tra điều kiện (nếu là conditional step)
            if ($step['type'] === 'conditional') {
                $condition = $step['config']['condition'] ?? null;
                if ($condition && !$this->evaluateCondition($condition, $currentData)) {
                    continue; // Bỏ qua step này
                }
            }

            if ($canExecute) {
                $filteredSteps[] = $step;
            }
        }

        return [
            'steps' => $filteredSteps,
            'estimated_time' => count($filteredSteps) * 30, // 30 seconds per step
        ];
    }

    /**
     * Evaluate condition expression
     *
     * @param string $condition
     * @param array $data
     * @return bool
     */
    protected function evaluateCondition(string $condition, array $data): bool
    {
        // Đơn giản hóa: kiểm tra xem field có tồn tại và có giá trị không
        // Có thể mở rộng với expression parser phức tạp hơn
        if (preg_match('/has\((.+)\)/', $condition, $matches)) {
            $field = $matches[1];
            return isset($data[$field]) && !empty($data[$field]);
        }
        return true;
    }

    /**
     * Build prompt for workflow planning
     *
     * @param array $intent
     * @param AiAssistant $assistant
     * @param array $currentData
     * @return string
     */
    protected function buildWorkflowPrompt(array $intent, AiAssistant $assistant, array $currentData): string
    {
        $config = $assistant->config ?? [];
        $currentDataStr = !empty($currentData) ? "\nCurrent data: " . json_encode($currentData, JSON_UNESCAPED_UNICODE) : '';
        
        // ✅ FIX: Convert enum to string
        $assistantTypeEnum = $assistant->assistant_type;
        $assistantType = is_object($assistantTypeEnum) ? $assistantTypeEnum->value : $assistantTypeEnum;
        
        return "Tạo workflow dựa trên intent và cấu hình assistant.

Intent: " . json_encode($intent, JSON_UNESCAPED_UNICODE) . "
Assistant type: {$assistantType}
Assistant config: " . json_encode($config, JSON_UNESCAPED_UNICODE) . "{$currentDataStr}

Trả về JSON với format:
{
  \"steps\": [
    {
      \"id\": \"step_id\",
      \"type\": \"collect_info|generate|search|process\",
      \"action\": \"action_name\",
      \"required\": true/false,
      \"dependencies\": [\"step_id1\", \"step_id2\"]
    }
  ],
  \"estimated_time\": 60
}";
    }
}

