<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class ReportContentParser
{
    /**
     * Parse AI-generated content into structured data
     * Extract sections, data points, and map to template structure
     *
     * @param string $aiContent AI-generated content
     * @param array $templateStructure Template structure (sections, headings, placeholders)
     * @return array{sections: array, data: array}
     */
    public function parseContent(string $aiContent, array $templateStructure): array
    {
        try {
            $parsedContent = [
                'sections' => [],
                'data' => [],
            ];
            
            // 1. Extract sections from AI content
            $parsedContent['sections'] = $this->extractSections($aiContent, $templateStructure);
            
            // 2. Extract data points (for placeholders)
            $parsedContent['data'] = $this->extractDataPoints($aiContent, $templateStructure);
            
            Log::debug('Parsed AI content', [
                'sections_count' => count($parsedContent['sections']),
                'data_points_count' => count($parsedContent['data']),
            ]);
            
            return $parsedContent;
        } catch (\Exception $e) {
            Log::error('Failed to parse AI content', [
                'error' => $e->getMessage(),
            ]);
            
            // Return basic structure
            return [
                'sections' => [],
                'data' => $this->extractBasicData($aiContent, $templateStructure),
            ];
        }
    }

    /**
     * Extract sections from AI-generated content
     *
     * @param string $aiContent
     * @param array $templateStructure
     * @return array
     */
    protected function extractSections(string $aiContent, array $templateStructure): array
    {
        $sections = [];
        
        // If template has sections, try to match AI content to template sections
        if (!empty($templateStructure['sections'])) {
            foreach ($templateStructure['sections'] as $templateSection) {
                $sectionTitle = is_array($templateSection) ? ($templateSection['title'] ?? '') : $templateSection;
                
                // Try to find matching section in AI content
                $sectionContent = $this->findSectionContent($aiContent, $sectionTitle);
                
                if ($sectionContent) {
                    $sections[$sectionTitle] = $sectionContent;
                }
            }
        } else {
            // If no template sections, extract sections from AI content
            $sections = $this->extractSectionsFromContent($aiContent);
        }
        
        return $sections;
    }

    /**
     * Find section content in AI-generated content
     *
     * @param string $aiContent
     * @param string $sectionTitle
     * @return string|null
     */
    protected function findSectionContent(string $aiContent, string $sectionTitle): ?string
    {
        // Normalize section title for matching
        $normalizedTitle = strtolower(trim($sectionTitle));
        
        // Try to find section by title
        $patterns = [
            // Pattern: "## Section Title" or "**Section Title**" or "Section Title:"
            '/^#+\s*' . preg_quote($sectionTitle, '/') . '\s*$/mi',
            '/^\*\*' . preg_quote($sectionTitle, '/') . '\*\*\s*$/mi',
            '/^' . preg_quote($sectionTitle, '/') . '[:：]\s*$/mi',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $aiContent, $matches, PREG_OFFSET_CAPTURE)) {
                $startPos = $matches[0][1] + strlen($matches[0][0]);
                
                // Find next section or end of content
                $nextSectionPattern = '/^#+\s+|\*\*[^*]+\*\*|^[A-Z][A-Z\s]+$/m';
                if (preg_match($nextSectionPattern, $aiContent, $nextMatches, PREG_OFFSET_CAPTURE, $startPos)) {
                    $endPos = $nextMatches[0][1];
                    return trim(substr($aiContent, $startPos, $endPos - $startPos));
                } else {
                    // No next section, take until end
                    return trim(substr($aiContent, $startPos));
                }
            }
        }
        
        // Try fuzzy matching
        $normalizedContent = strtolower($aiContent);
        $normalizedTitleWords = explode(' ', $normalizedTitle);
        
        foreach ($normalizedTitleWords as $word) {
            if (strlen($word) > 3 && strpos($normalizedContent, $word) !== false) {
                // Found word, try to extract surrounding content
                $pos = strpos($normalizedContent, $word);
                $startPos = max(0, $pos - 50);
                $endPos = min(strlen($aiContent), $pos + 500);
                
                return trim(substr($aiContent, $startPos, $endPos - $startPos));
            }
        }
        
        return null;
    }

    /**
     * Extract sections from AI content (when template has no sections)
     *
     * @param string $aiContent
     * @return array
     */
    protected function extractSectionsFromContent(string $aiContent): array
    {
        $sections = [];
        
        // Extract sections by headings (## Heading or **Heading**)
        $pattern = '/^(?:#+\s+|\*\*)(.+?)(?:\*\*|$)/m';
        if (preg_match_all($pattern, $aiContent, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[1] as $index => $match) {
                $title = trim($match[0]);
                $startPos = $match[1];
                
                // Find end of section (next heading or end of content)
                $endPos = isset($matches[1][$index + 1]) 
                    ? $matches[1][$index + 1][1] 
                    : strlen($aiContent);
                
                $content = trim(substr($aiContent, $startPos, $endPos - $startPos));
                $sections[$title] = $content;
            }
        }
        
        return $sections;
    }

    /**
     * Extract data points from AI-generated content
     * Map to template placeholders
     *
     * @param string $aiContent
     * @param array $templateStructure
     * @return array
     */
    protected function extractDataPoints(string $aiContent, array $templateStructure): array
    {
        $data = [];
        
        // Extract placeholders from template
        $placeholders = $templateStructure['placeholders'] ?? [];
        
        // For each placeholder, try to extract value from AI content
        foreach ($placeholders as $placeholder) {
            // Normalize placeholder (remove brackets, braces)
            $key = preg_replace('/[\[\{\}]/s', '', $placeholder);
            $key = trim($key);
            
            // Try to find value in AI content
            $value = $this->extractValueForPlaceholder($aiContent, $key, $placeholder);
            
            if ($value) {
                // Map to all placeholder formats
                $data[$placeholder] = $value;
                $data['{{' . $key . '}}'] = $value;
                $data['${' . $key . '}'] = $value;
                $data['[' . $key . ']'] = $value;
            }
        }
        
        // Also extract common data patterns
        $commonData = $this->extractCommonData($aiContent);
        $data = array_merge($data, $commonData);
        
        return $data;
    }

    /**
     * Extract value for a specific placeholder
     *
     * @param string $aiContent
     * @param string $key
     * @param string $placeholder
     * @return string|null
     */
    protected function extractValueForPlaceholder(string $aiContent, string $key, string $placeholder): ?string
    {
        // Try various patterns to find value
        $patterns = [
            // Pattern: "Key: Value"
            '/' . preg_quote($key, '/') . '\s*[:：]\s*(.+?)(?:\n|$|\.)/i',
            // Pattern: "**Key:** Value"
            '/\*\*' . preg_quote($key, '/') . '\*\*\s*[:：]?\s*(.+?)(?:\n|$|\.)/i',
            // Pattern: "## Key: Value"
            '/#+\s*' . preg_quote($key, '/') . '\s*[:：]\s*(.+?)(?:\n|$|(?=\n#+))/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $aiContent, $matches)) {
                $value = trim($matches[1]);
                if (!empty($value)) {
                    return $value;
                }
            }
        }
        
        return null;
    }

    /**
     * Extract common data patterns from AI content
     *
     * @param string $aiContent
     * @return array
     */
    protected function extractCommonData(string $aiContent): array
    {
        $data = [];
        
        // Common patterns: "Tên: ...", "Năm: ...", "Địa chỉ: ..."
        $commonPatterns = [
            'ten' => '/(?:Tên|Họ tên|Tên công ty|Tên đơn vị)[:：]\s*(.+?)(?:\n|$)/i',
            'nam' => '/(?:Năm|Năm báo cáo)[:：]\s*(.+?)(?:\n|$)/i',
            'dia_chi' => '/(?:Địa chỉ|Địa điểm)[:：]\s*(.+?)(?:\n|$)/i',
            'so_dien_thoai' => '/(?:Số điện thoại|Điện thoại|SĐT)[:：]\s*(.+?)(?:\n|$)/i',
            'email' => '/(?:Email|E-mail)[:：]\s*(.+?)(?:\n|$)/i',
        ];
        
        foreach ($commonPatterns as $key => $pattern) {
            if (preg_match($pattern, $aiContent, $matches)) {
                $value = trim($matches[1]);
                if (!empty($value)) {
                    $data['{{' . $key . '}}'] = $value;
                    $data['${' . $key . '}'] = $value;
                    $data['[' . $key . ']'] = $value;
                }
            }
        }
        
        return $data;
    }

    /**
     * Extract basic data (fallback)
     *
     * @param string $aiContent
     * @param array $templateStructure
     * @return array
     */
    protected function extractBasicData(string $aiContent, array $templateStructure): array
    {
        $data = [];
        
        // Extract placeholders from template
        $placeholders = $templateStructure['placeholders'] ?? [];
        
        // Simple extraction: look for key-value pairs
        foreach ($placeholders as $placeholder) {
            $key = preg_replace('/[\[\{\}]/s', '', $placeholder);
            $key = trim($key);
            
            // Try simple pattern matching
            if (preg_match('/' . preg_quote($key, '/') . '\s*[:：]\s*(.+?)(?:\n|$)/i', $aiContent, $matches)) {
                $value = trim($matches[1]);
                if (!empty($value)) {
                    $data[$placeholder] = $value;
                }
            }
        }
        
        return $data;
    }
}






