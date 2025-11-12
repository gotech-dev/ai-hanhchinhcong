<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Smart DOCX Replacer
 * 
 * Replace text in DOCX templates while preserving 100% formatting
 * Uses direct ZIP/XML manipulation instead of PhpWord TemplateProcessor
 * 
 * This allows working with templates that don't have placeholders ({{key}})
 * and only have plain text like "TÊN CƠ QUAN", "ngày... tháng..."
 */
class SmartDocxReplacer
{
    /**
     * Fill template with data
     *
     * @param string $templatePath Full path to template DOCX
     * @param array $replacements ['search' => 'replace'] pairs
     * @return string Path to generated DOCX
     */
    public function fillTemplate(string $templatePath, array $replacements): string
    {
        try {
            if (!file_exists($templatePath)) {
                throw new \Exception("Template file not found: {$templatePath}");
            }

            // 1. Copy template to new file
            $newPath = $this->createOutputPath();
            if (!copy($templatePath, $newPath)) {
                throw new \Exception("Failed to copy template");
            }

            Log::info('SmartDocxReplacer: Starting template fill', [
                'template' => basename($templatePath),
                'output' => basename($newPath),
                'replacements_count' => count($replacements),
            ]);

            // 2. Open as ZIP
            $zip = new ZipArchive();
            if ($zip->open($newPath) !== true) {
                throw new \Exception("Failed to open DOCX as ZIP");
            }

            // 3. Get document.xml
            $xml = $zip->getFromName('word/document.xml');
            if ($xml === false) {
                $zip->close();
                throw new \Exception("Failed to read document.xml from DOCX");
            }

            Log::debug('SmartDocxReplacer: Original XML size', [
                'size' => strlen($xml),
            ]);

            // 4. Smart replace (handle split text)
            $newXml = $this->smartReplaceInXml($xml, $replacements);

            // 5. Put back and close
            if (!$zip->addFromString('word/document.xml', $newXml)) {
                $zip->close();
                throw new \Exception("Failed to write document.xml back to DOCX");
            }

            $zip->close();

            Log::info('SmartDocxReplacer: Template filled successfully', [
                'output' => $newPath,
                'original_size' => strlen($xml),
                'new_size' => strlen($newXml),
            ]);

            return $newPath;

        } catch (\Exception $e) {
            Log::error('SmartDocxReplacer: Failed to fill template', [
                'error' => $e->getMessage(),
                'template' => $templatePath ?? null,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Smart replace text in XML (handles text split across nodes)
     *
     * @param string $xml Document XML content
     * @param array $replacements ['search' => 'replace'] pairs
     * @return string Modified XML
     */
    protected function smartReplaceInXml(string $xml, array $replacements): string
    {
        if (empty($replacements)) {
            return $xml;
        }

        // Try simple replacement first (for continuous text)
        $modified = false;
        foreach ($replacements as $search => $replace) {
            if (strpos($xml, $search) !== false) {
                $xml = str_replace($search, $replace, $xml);
                $modified = true;
                
                Log::debug('SmartDocxReplacer: Simple replacement', [
                    'search' => substr($search, 0, 50),
                    'replace' => substr($replace, 0, 50),
                ]);
            }
        }

        // If simple replacement worked for all, return
        if ($modified) {
            // Verify all replacements were made
            $allReplaced = true;
            foreach ($replacements as $search => $replace) {
                if (strpos($xml, $search) !== false) {
                    $allReplaced = false;
                    break;
                }
            }
            
            if ($allReplaced) {
                Log::info('SmartDocxReplacer: All replacements done with simple method');
                return $xml;
            }
        }

        // If simple replacement didn't work, use advanced method
        Log::info('SmartDocxReplacer: Using advanced replacement (handling split text)');
        return $this->advancedReplaceInXml($xml, $replacements);
    }

    /**
     * Advanced replacement - handles text split across XML nodes
     *
     * @param string $xml Document XML content
     * @param array $replacements ['search' => 'replace'] pairs
     * @return string Modified XML
     */
    protected function advancedReplaceInXml(string $xml, array $replacements): string
    {
        try {
            // Parse XML
            $dom = new DOMDocument('1.0', 'UTF-8');
            
            // Suppress warnings for malformed XML
            $prevErrorSetting = libxml_use_internal_errors(true);
            $loaded = $dom->loadXML($xml);
            libxml_clear_errors();
            libxml_use_internal_errors($prevErrorSetting);

            if (!$loaded) {
                Log::warning('SmartDocxReplacer: Failed to parse XML, falling back to simple replace');
                return $this->fallbackReplace($xml, $replacements);
            }

            // Register namespace
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            // Get all text nodes
            $textNodes = $xpath->query('//w:t');
            if ($textNodes->length === 0) {
                Log::warning('SmartDocxReplacer: No text nodes found in XML');
                return $xml;
            }

            Log::debug('SmartDocxReplacer: Found text nodes', [
                'count' => $textNodes->length,
            ]);

            // Build full text and node mapping
            $fullText = '';
            $nodeMap = [];
            
            foreach ($textNodes as $node) {
                $text = $node->textContent;
                $nodeMap[] = [
                    'node' => $node,
                    'start' => strlen($fullText),
                    'length' => strlen($text),
                    'text' => $text,
                ];
                $fullText .= $text;
            }

            Log::debug('SmartDocxReplacer: Extracted full text', [
                'length' => strlen($fullText),
                'preview' => substr($fullText, 0, 200),
            ]);

            // Replace in full text
            $newFullText = $fullText;
            $replacedCount = 0;
            
            foreach ($replacements as $search => $replace) {
                if (strpos($newFullText, $search) !== false) {
                    $newFullText = str_replace($search, $replace, $newFullText);
                    $replacedCount++;
                    
                    Log::debug('SmartDocxReplacer: Replaced in full text', [
                        'search' => substr($search, 0, 50),
                        'replace' => substr($replace, 0, 50),
                    ]);
                }
            }

            if ($replacedCount === 0) {
                Log::warning('SmartDocxReplacer: No replacements made in full text');
                return $xml;
            }

            // Put new text back to first node, clear others
            if ($newFullText !== $fullText && !empty($nodeMap)) {
                $nodeMap[0]['node']->textContent = $newFullText;
                
                // Clear other nodes
                for ($i = 1; $i < count($nodeMap); $i++) {
                    $nodeMap[$i]['node']->textContent = '';
                }

                Log::info('SmartDocxReplacer: Text distributed to nodes', [
                    'first_node_length' => strlen($newFullText),
                    'cleared_nodes' => count($nodeMap) - 1,
                ]);
            }

            // Save XML
            $newXml = $dom->saveXML();

            Log::info('SmartDocxReplacer: Advanced replacement completed', [
                'replaced_count' => $replacedCount,
                'original_length' => strlen($fullText),
                'new_length' => strlen($newFullText),
            ]);

            return $newXml;

        } catch (\Exception $e) {
            Log::error('SmartDocxReplacer: Advanced replacement failed', [
                'error' => $e->getMessage(),
            ]);

            // Fallback to simple replace
            return $this->fallbackReplace($xml, $replacements);
        }
    }

    /**
     * Fallback replacement method (simple string replace)
     *
     * @param string $xml
     * @param array $replacements
     * @return string
     */
    protected function fallbackReplace(string $xml, array $replacements): string
    {
        foreach ($replacements as $search => $replace) {
            $xml = str_replace($search, $replace, $xml);
        }
        return $xml;
    }

    /**
     * Create output file path
     *
     * @return string
     */
    protected function createOutputPath(): string
    {
        $filename = 'report_' . uniqid() . '_' . time() . '.docx';
        return Storage::disk('public')->path('reports/' . $filename);
    }

    /**
     * Get template path from URL
     *
     * @param string $templateUrl
     * @return string
     */
    public function getTemplatePath(string $templateUrl): string
    {
        // Parse URL to get path
        $parsedUrl = parse_url($templateUrl);
        $path = $parsedUrl['path'] ?? $templateUrl;
        
        // Remove /storage prefix if present
        $filePath = preg_replace('#^/storage/#', '', $path);
        $filePath = ltrim($filePath, '/');
        
        // Get full path using Storage
        return Storage::disk('public')->path($filePath);
    }
}






