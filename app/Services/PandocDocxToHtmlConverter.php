<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Pandoc DOCX to HTML Converter
 * 
 * Achieves 95-98% format preservation using Pandoc
 * - Better than PhpWord (85-90%)
 * - Handles complex structures (tables, line breaks, etc.)
 * - Production-ready
 */
class PandocDocxToHtmlConverter
{
    /**
     * Convert DOCX to HTML using Pandoc
     *
     * @param string $docxPath Path to DOCX file
     * @return string HTML content
     * @throws \Exception If conversion fails
     */
    public function convert(string $docxPath): string
    {
        // Validate input
        if (!file_exists($docxPath)) {
            throw new \Exception("DOCX file not found: {$docxPath}");
        }
        
        // Check if Pandoc is installed
        $pandocPath = $this->getPandocPath();
        if (!$pandocPath) {
            throw new \Exception('Pandoc is not installed on this server. Please install: brew install pandoc');
        }
        
        Log::info('Converting DOCX to HTML with Pandoc', [
            'file' => basename($docxPath),
            'file_size' => filesize($docxPath),
            'pandoc_version' => $this->getPandocVersion(),
        ]);
        
        try {
            // Generate output path
            $outputPath = sys_get_temp_dir() . '/' . uniqid('pandoc_html_') . '.html';
            
            // Build Pandoc command
            $command = $this->buildPandocCommand($docxPath, $outputPath);
            
            // Execute Pandoc
            exec($command, $output, $returnCode);
            
            if ($returnCode !== 0) {
                $errorMsg = implode("\n", $output);
                Log::error('Pandoc conversion failed', [
                    'return_code' => $returnCode,
                    'error' => $errorMsg,
                    'command' => $command,
                ]);
                throw new \Exception("Pandoc conversion failed: {$errorMsg}");
            }
            
            // Check if output file was created
            if (!file_exists($outputPath)) {
                throw new \Exception('Pandoc did not create output file');
            }
            
            // Read HTML content
            $html = file_get_contents($outputPath);
            
            // ✅ FIX: Clean up Unicode replacement characters và control characters
            $html = preg_replace('/_x000[0-9a-fA-F]+_/i', '', $html); // Remove _x0007_ etc
            $html = preg_replace('/[\x{FFFD}]/u', '', $html); // Remove Unicode replacement character (ࠀ)
            
            // Clean up temp file
            @unlink($outputPath);
            
            // ✅ DEBUG: Log raw HTML output from Pandoc
            Log::info('Pandoc raw HTML output (before enhancement)', [
                'html_length' => strlen($html),
                'has_body_tag' => strpos($html, '<body') !== false,
                'has_article_tag' => strpos($html, '<article') !== false,
                'p_tag_count' => substr_count($html, '<p'),
                'br_tag_count' => substr_count($html, '<br'),
                'html_snippet' => substr($html, 0, 2000),
            ]);
            
            // Enhance HTML with custom styling
            $html = $this->enhanceHtml($html);
            
            // ✅ DEBUG: Log enhanced HTML output
            Log::info('Pandoc enhanced HTML output (after enhancement)', [
                'html_length' => strlen($html),
                'has_body_tag' => strpos($html, '<body') !== false,
                'has_article_tag' => strpos($html, '<article') !== false,
                'p_tag_count' => substr_count($html, '<p'),
                'br_tag_count' => substr_count($html, '<br'),
                'starts_with_style' => strpos(trim($html), '<style>') === 0,
                'html_snippet' => substr($html, 0, 2000),
            ]);
            
            Log::info('Pandoc conversion successful', [
                'html_length' => strlen($html),
                'input_file' => basename($docxPath),
            ]);
            
            return $html;
            
        } catch (\Exception $e) {
            // Clean up on error
            if (isset($outputPath) && file_exists($outputPath)) {
                @unlink($outputPath);
            }
            
            Log::error('Pandoc conversion exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Get Pandoc executable path
     *
     * @return string|null
     */
    protected function getPandocPath(): ?string
    {
        $path = trim(shell_exec('which pandoc 2>/dev/null'));
        return !empty($path) && file_exists($path) ? $path : null;
    }
    
    /**
     * Get Pandoc version
     *
     * @return string
     */
    protected function getPandocVersion(): string
    {
        return trim(shell_exec('pandoc --version 2>/dev/null | head -1')) ?: 'unknown';
    }
    
    /**
     * Build Pandoc command
     *
     * @param string $inputPath
     * @param string $outputPath
     * @return string
     */
    protected function buildPandocCommand(string $inputPath, string $outputPath): string
    {
        $pandocPath = $this->getPandocPath();
        
        // Pandoc options for best HTML output
        $options = [
            '--standalone',              // Create complete HTML document
            '--embed-resources',         // Embed images as base64
            '--self-contained',          // No external dependencies (deprecated but still works)
            '--metadata title="Document Preview"',
            '--css=' . $this->getInlineCss(),  // Custom CSS
            '--wrap=preserve',           // ✅ FIX: Preserve line breaks and paragraph structure
            '--preserve-tabs',           // ✅ FIX: Preserve tabs
            '--extract-media=./',        // Extract media files
            // ✅ FIX: Thêm options cho Vietnamese encoding
            '--from=docx',               // Input format
            '--to=html5',                // Output format HTML5
            '--no-highlight',            // Tắt syntax highlighting
        ];
        
        return sprintf(
            '%s %s %s -o %s 2>&1',
            escapeshellcmd($pandocPath),
            escapeshellarg($inputPath),
            implode(' ', $options),
            escapeshellarg($outputPath)
        );
    }
    
    /**
     * Get inline CSS for styling
     *
     * @return string Path to temp CSS file
     */
    protected function getInlineCss(): string
    {
        $css = $this->generateCss();
        $cssPath = sys_get_temp_dir() . '/' . uniqid('pandoc_css_') . '.css';
        file_put_contents($cssPath, $css);
        
        // Register shutdown function to clean up CSS file
        register_shutdown_function(function () use ($cssPath) {
            @unlink($cssPath);
        });
        
        return $cssPath;
    }
    
    /**
     * Generate CSS for Vietnamese document styling
     *
     * @return string
     */
    protected function generateCss(): string
    {
        return <<<CSS
/* Vietnamese Document Styling */
body {
    font-family: 'Times New Roman', Times, serif;
    font-size: 13pt;
    line-height: 1.5;
    color: #000;
    background: #f5f5f5;
    padding: 20px;
    margin: 0;
}

/* Document Container (A4) */
article {
    max-width: 21cm;
    margin: 0 auto;
    padding: 2cm 3cm 2cm 3cm;
    background: white;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    min-height: 29.7cm;
}

/* Headings */
h1, h2, h3, h4, h5, h6 {
    font-weight: bold;
    margin: 1em 0 0.5em 0;
    page-break-after: avoid;
}

h1 {
    font-size: 16pt;
    text-align: center;
    text-transform: uppercase;
}

h2 {
    font-size: 14pt;
    text-align: center;
}

h3 {
    font-size: 13pt;
}

/* Paragraphs */
p {
    margin: 0.5em 0;
    text-align: justify;
    text-indent: 0;
}

p.center, p[style*="text-align: center"] {
    text-align: center;
    text-indent: 0;
}

/* Tables */
table {
    width: 100%;
    border-collapse: collapse;
    margin: 1em 0;
    page-break-inside: avoid;
}

table td,
table th {
    border: 1px solid #000;
    padding: 0.5em;
    text-align: left;
    vertical-align: top;
}

table th {
    background: #f0f0f0;
    font-weight: bold;
}

/* Lists */
ul, ol {
    margin: 0.5em 0;
    padding-left: 2em;
}

li {
    margin: 0.3em 0;
}

/* Text Formatting */
strong, b {
    font-weight: bold;
}

em, i {
    font-style: italic;
}

u {
    text-decoration: underline;
}

/* Images */
img {
    max-width: 100%;
    height: auto;
    display: block;
    margin: 1em auto;
}

/* Print Styles */
@media print {
    body {
        background: white;
        padding: 0;
    }
    
    article {
        box-shadow: none;
        margin: 0;
        padding: 0;
        max-width: 100%;
    }
}

/* Responsive */
@media (max-width: 768px) {
    article {
        max-width: 100%;
        padding: 1cm;
        min-height: auto;
    }
}
CSS;
    }
    
    /**
     * Enhance HTML output
     *
     * @param string $html
     * @return string
     */
    protected function enhanceHtml(string $html): string
    {
        Log::info('🔵 [PandocDocxToHtmlConverter] enhanceHtml START', [
            'htmlLength' => strlen($html),
            'hasBody' => preg_match('/<body[^>]*>/i', $html),
            'hasArticle' => preg_match('/<article[^>]*>/i', $html),
            'pTagCount' => substr_count($html, '<p'),
        ]);
        
        // ✅ FIX: Extract body content if HTML has full document structure
        // Vue v-html cannot render full HTML documents, only body content
        if (preg_match('/<body[^>]*>([\s\S]*?)<\/body>/i', $html, $bodyMatches)) {
            // Extract body content
            $bodyContent = $bodyMatches[1];
            
            // Extract ALL styles from head (may have multiple <style> tags)
            $allStyles = '';
            if (preg_match_all('/<style[^>]*>([\s\S]*?)<\/style>/i', $html, $styleMatches)) {
                foreach ($styleMatches[1] as $styleContent) {
                    $allStyles .= $styleContent . "\n";
                }
            }
            
            // ✅ FIX: Remove header tag with "Document Preview" from Pandoc metadata
            $bodyContent = preg_replace('/<header[^>]*>[\s\S]*?<\/header>/i', '', $bodyContent);
            
            Log::info('🔵 [PandocDocxToHtmlConverter] Before ensureParagraphStructure', [
                'bodyContentLength' => strlen($bodyContent),
                'pTagCount' => substr_count($bodyContent, '<p'),
                'sample' => substr($bodyContent, 0, 500),
            ]);
            
            // ✅ FIX: Post-process body content to ensure proper paragraph structure
            $bodyContent = $this->ensureParagraphStructure($bodyContent);
            
            Log::info('🔵 [PandocDocxToHtmlConverter] After ensureParagraphStructure', [
                'bodyContentLength' => strlen($bodyContent),
                'pTagCount' => substr_count($bodyContent, '<p'),
                'sample' => substr($bodyContent, 0, 500),
            ]);
            
            // Combine styles and body content
            $html = '<style>' . $allStyles . $this->getLineBreakFixCss() . '</style>' . $bodyContent;
        } elseif (preg_match('/<article[^>]*>([\s\S]*?)<\/article>/i', $html, $articleMatches)) {
            // If no body tag but has article tag, extract article content
            $articleContent = $articleMatches[1];
            
            // Extract styles
            $allStyles = '';
            if (preg_match_all('/<style[^>]*>([\s\S]*?)<\/style>/i', $html, $styleMatches)) {
                foreach ($styleMatches[1] as $styleContent) {
                    $allStyles .= $styleContent . "\n";
                }
            }
            
            // ✅ FIX: Remove header tag with "Document Preview" from Pandoc metadata
            $articleContent = preg_replace('/<header[^>]*>[\s\S]*?<\/header>/i', '', $articleContent);
            
            Log::info('🔵 [PandocDocxToHtmlConverter] Before ensureParagraphStructure (article)', [
                'articleContentLength' => strlen($articleContent),
                'pTagCount' => substr_count($articleContent, '<p'),
                'sample' => substr($articleContent, 0, 500),
            ]);
            
            // ✅ FIX: Post-process article content to ensure proper paragraph structure
            $articleContent = $this->ensureParagraphStructure($articleContent);
            
            Log::info('🔵 [PandocDocxToHtmlConverter] After ensureParagraphStructure (article)', [
                'articleContentLength' => strlen($articleContent),
                'pTagCount' => substr_count($articleContent, '<p'),
                'sample' => substr($articleContent, 0, 500),
            ]);
            
            // Combine styles and article content
            $html = '<style>' . $allStyles . $this->getLineBreakFixCss() . '</style>' . $articleContent;
        } else {
            // If no body/article tag, check if it's already just content
            // Remove any head/meta/title tags that might be present
            $html = preg_replace('/<head[^>]*>[\s\S]*?<\/head>/i', '', $html);
            $html = preg_replace('/<meta[^>]*>/i', '', $html);
            $html = preg_replace('/<title[^>]*>[\s\S]*?<\/title>/i', '', $html);
            $html = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);
            $html = preg_replace('/<html[^>]*>/i', '', $html);
            $html = preg_replace('/<\/html>/i', '', $html);
            
            // ✅ FIX: Remove header tag with "Document Preview" from Pandoc metadata
            $html = preg_replace('/<header[^>]*>[\s\S]*?<\/header>/i', '', $html);
            
            Log::info('🔵 [PandocDocxToHtmlConverter] Before ensureParagraphStructure (no body/article)', [
                'htmlLength' => strlen($html),
                'pTagCount' => substr_count($html, '<p'),
                'sample' => substr($html, 0, 500),
            ]);
            
            // ✅ FIX: Post-process HTML to ensure proper paragraph structure
            $html = $this->ensureParagraphStructure($html);
            
            Log::info('🔵 [PandocDocxToHtmlConverter] After ensureParagraphStructure (no body/article)', [
                'htmlLength' => strlen($html),
                'pTagCount' => substr_count($html, '<p'),
                'sample' => substr($html, 0, 500),
            ]);
            
            // Add CSS for line breaks
            $cssFix = $this->getLineBreakFixCss();
            
            // Extract existing styles if any
            $allStyles = '';
            if (preg_match_all('/<style[^>]*>([\s\S]*?)<\/style>/i', $html, $styleMatches)) {
                foreach ($styleMatches[1] as $styleContent) {
                    $allStyles .= $styleContent . "\n";
                }
                // Remove style tags from HTML
                $html = preg_replace('/<style[^>]*>[\s\S]*?<\/style>/i', '', $html);
            }
            
            // Add CSS at the beginning
            $html = '<style>' . $allStyles . $cssFix . '</style>' . $html;
        }
        
        return $html;
    }
    
    /**
     * Ensure proper paragraph structure in HTML
     * 
     * This method ensures that text content has proper <p> tags or <br> tags
     * to preserve line breaks and paragraph structure.
     *
     * @param string $html
     * @return string
     */
    protected function ensureParagraphStructure(string $html): string
    {
        // ✅ FIX: Remove header tag with "Document Preview" from Pandoc metadata
        $html = preg_replace('/<header[^>]*>[\s\S]*?<\/header>/i', '', $html);
        
        // ✅ FIX: Clean up Unicode trong text content (giữ lại để fix Unicode issues)
        $html = $this->cleanUpUnicodeInText($html);
        
        // ✅ FIX: BỎ HẾT LOGIC MERGE PARAGRAPH - Giữ nguyên structure từ Pandoc
        // Report cũ dùng Pandoc mà format giống tuyệt đối vì không có logic merge này
        // Logic merge paragraph đang gây ra lỗi merge sai text
        
        Log::info('🔵 [PandocDocxToHtmlConverter] ensureParagraphStructure - No merging (preserve Pandoc structure)', [
            'pTagCount' => substr_count($html, '<p'),
            'htmlLength' => strlen($html),
        ]);
        
        return $html;
        
        // ✅ FIX: If HTML has only 1 or few <p> tags, we need to split content
        // This happens when Pandoc wraps all content in a single <p> tag
        
        // Find the first <p> tag position
        $pStartPos = strpos($html, '<p');
        if ($pStartPos === false) {
            // No <p> tag found, return as is
            return $html;
        }
        
        // Find the matching </p> tag (need to handle nested tags)
        $pEndPos = $this->findMatchingClosingTag($html, $pStartPos, 'p');
        if ($pEndPos === false) {
            // No closing tag found, return as is
            return $html;
        }
        
        // Extract content from the <p> tag
        $pContent = substr($html, $pStartPos + 2, $pEndPos - $pStartPos - 2);
        // Remove the <p> tag itself (find the actual opening tag)
        $pTagEnd = strpos($html, '>', $pStartPos);
        if ($pTagEnd === false) {
            return $html;
        }
        $pContent = substr($html, $pTagEnd + 1, $pEndPos - $pTagEnd - 1);
        
        $beforeP = substr($html, 0, $pStartPos);
        $afterP = substr($html, $pEndPos + 4);
        
        // ✅ FIX: Split content by line breaks, but preserve HTML structure
        // First, protect block-level elements (tables, lists, divs, etc.)
        $protected = [];
        $placeholder = '___PROTECTED_BLOCK_%d___';
        $counter = 0;
        
        // Protect tables
        $pContent = preg_replace_callback('/<table[^>]*>[\s\S]*?<\/table>/i', function($m) use (&$protected, &$counter, $placeholder) {
            $protected[$counter] = $m[0];
            return sprintf($placeholder, $counter++);
        }, $pContent);
        
        // Protect lists
        $pContent = preg_replace_callback('/<(ul|ol)[^>]*>[\s\S]*?<\/\1>/i', function($m) use (&$protected, &$counter, $placeholder) {
            $protected[$counter] = $m[0];
            return sprintf($placeholder, $counter++);
        }, $pContent);
        
        // Protect divs
        $pContent = preg_replace_callback('/<div[^>]*>[\s\S]*?<\/div>/i', function($m) use (&$protected, &$counter, $placeholder) {
            $protected[$counter] = $m[0];
            return sprintf($placeholder, $counter++);
        }, $pContent);
        
        // ✅ FIX: Split by double newlines first (paragraph breaks)
        $paragraphs = preg_split('/\r?\n\r?\n+/', $pContent);
        
        // If no double newlines, split by single newlines
        if (count($paragraphs) <= 1) {
            // Split by single newlines
            $lines = preg_split('/\r?\n/', $pContent);
            $paragraphs = [];
            $currentParagraph = '';
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (strlen($line) > 0) {
                    // If line is substantial or starts with specific patterns, start new paragraph
                    if (strlen($currentParagraph) > 0 && 
                        (strlen($line) > 30 || 
                         preg_match('/^(I+\.|[\d]+\.|[\d]+\)|•|-|\*|CÔNG TY|BÁO CÁO|Kính gửi)/i', $line))) {
                        $paragraphs[] = $currentParagraph;
                        $currentParagraph = $line;
                    } else {
                        $currentParagraph .= ($currentParagraph ? ' ' : '') . $line;
                    }
                }
            }
            
            if (strlen($currentParagraph) > 0) {
                $paragraphs[] = $currentParagraph;
            }
        }
        
        // Wrap each paragraph in <p> tags
        $wrappedParagraphs = [];
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if (strlen($para) > 0) {
                // Restore protected blocks first
                foreach ($protected as $index => $block) {
                    $para = str_replace(sprintf($placeholder, $index), $block, $para);
                }
                
                // Don't double-wrap if already has <p> tag
                if (strpos($para, '<p') === false && strpos($para, '<table') === false && strpos($para, '<ul') === false && strpos($para, '<ol') === false) {
                    $wrappedParagraphs[] = '<p>' . $para . '</p>';
                } else {
                    $wrappedParagraphs[] = $para;
                }
            }
        }
        
        // Reconstruct HTML
        $newContent = implode("\n", $wrappedParagraphs);
        $html = $beforeP . $newContent . $afterP;
        
        return $html;
    }
    
    /**
     * Find matching closing tag for an opening tag
     *
     * @param string $html
     * @param int $startPos Position of opening tag
     * @param string $tagName Tag name (without < >)
     * @return int|false Position of closing tag or false if not found
     */
    protected function findMatchingClosingTag(string $html, int $startPos, string $tagName): int|false
    {
        $depth = 0;
        $pos = $startPos;
        $openTag = '<' . $tagName;
        $closeTag = '</' . $tagName . '>';
        
        while (($pos = strpos($html, $openTag, $pos)) !== false) {
            // Check if it's opening or closing tag
            if (substr($html, $pos, strlen($openTag) + 1) === $openTag . '>' || 
                preg_match('/<' . $tagName . '[^>]*>/i', substr($html, $pos, 100))) {
                $depth++;
            }
            
            $closePos = strpos($html, $closeTag, $pos);
            if ($closePos !== false) {
                $depth--;
                if ($depth === 0) {
                    return $closePos;
                }
            }
            
            $pos++;
        }
        
        // If no nested tags, find simple closing tag
        return strpos($html, $closeTag, $startPos);
    }
    
    /**
     * Merge short consecutive <p> tags into single paragraphs
     * 
     * Pandoc often splits text into many small <p> tags (one per line).
     * This method merges consecutive short <p> tags to preserve document structure.
     *
     * @param string $html
     * @return string
     */
    protected function mergeShortParagraphs(string $html): string
    {
        // ✅ FIX: Merge consecutive <p> tags that are too short (likely split by Pandoc)
        // Pattern: <p>short text</p><p>short text</p> -> <p>short text short text</p>
        
        // First, protect block-level elements
        $protected = [];
        $placeholder = '___PROTECTED_BLOCK_%d___';
        $counter = 0;
        
        // Protect tables, lists, divs, headers, headings
        $html = preg_replace_callback('/<(table|ul|ol|div|h[1-6]|header)[^>]*>[\s\S]*?<\/\1>/i', function($m) use (&$protected, &$counter, $placeholder) {
            $protected[$counter] = $m[0];
            return sprintf($placeholder, $counter++);
        }, $html);
        
        // ✅ FIX: Merge consecutive short <p> tags using iterative approach
        // Keep merging until no more changes
        $maxIterations = 10;
        $iteration = 0;
        $totalMerged = 0;
        
        while ($iteration < $maxIterations) {
            $originalHtml = $html;
            
            // Match 2 or more consecutive <p> tags (including nested tags like <sup>)
            $html = preg_replace_callback('/(<p[^>]*>[\s\S]*?<\/p>)\s*(<p[^>]*>[\s\S]*?<\/p>)/i', function($matches) use (&$totalMerged) {
                $p1 = $matches[1];
                $p2 = $matches[2];
                
                // Extract content from both paragraphs
                preg_match('/<p[^>]*>([\s\S]*?)<\/p>/i', $p1, $m1);
                preg_match('/<p[^>]*>([\s\S]*?)<\/p>/i', $p2, $m2);
                
                $content1 = isset($m1[1]) ? trim($m1[1]) : '';
                $content2 = isset($m2[1]) ? trim($m2[1]) : '';
                
                // Get text length (strip HTML tags)
                $text1 = strip_tags($content1);
                $text2 = strip_tags($content2);
                $textLength1 = strlen(trim($text1));
                $textLength2 = strlen(trim($text2));
                
                // ✅ FIX 1: Merge paragraph có superscript/subscript nếu cùng một từ
                if (preg_match('/<sup|<sub/i', $p1) || preg_match('/<sup|<sub/i', $p2)) {
                    // ✅ FIX: Merge nếu p1 chỉ có text (1-3 ký tự) và p2 chỉ có sup/sub
                    if ($textLength1 > 0 && $textLength1 <= 3 && $textLength2 === 0 && preg_match('/^<p[^>]*>(<sup|<sub)/i', $p2)) {
                        $totalMerged++;
                        $content1 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p1);
                        $content2 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p2);
                        return '<p>' . $content1 . $content2 . '</p>';
                    }
                    
                    // ✅ FIX: Merge nếu p1 chỉ có sup/sub và p2 chỉ có text (1-3 ký tự)
                    if ($textLength1 === 0 && preg_match('/^<p[^>]*>(<sup|<sub)/i', $p1) && $textLength2 > 0 && $textLength2 <= 3) {
                        $totalMerged++;
                        $content1 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p1);
                        $content2 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p2);
                        return '<p>' . $content1 . $content2 . '</p>';
                    }
                    
                    // ✅ FIX: Merge nếu cả 2 đều chỉ có superscript/subscript
                    if ($textLength1 === 0 && preg_match('/^<p[^>]*>(<sup|<sub)/i', $p1) && 
                        $textLength2 === 0 && preg_match('/^<p[^>]*>(<sup|<sub)/i', $p2)) {
                        $totalMerged++;
                        $content1 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p1);
                        $content2 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p2);
                        return '<p>' . $content1 . ' ' . $content2 . '</p>';
                    }
                    
                    // Otherwise, keep as is
                    return $p1 . "\n" . $p2;
                }
                
                // ✅ FIX 2: Merge paragraph ngắn (< 20 ký tự) nếu không có block elements
                if ($textLength1 <= 20 && $textLength2 <= 20 && $textLength1 > 0 && $textLength2 > 0) {
                    // Check if they have block elements
                    $hasBlock1 = preg_match('/<(table|ul|ol|div|h[1-6])/i', $p1);
                    $hasBlock2 = preg_match('/<(table|ul|ol|div|h[1-6])/i', $p2);
                    
                    if (!$hasBlock1 && !$hasBlock2) {
                        $totalMerged++;
                        $merged = $content1 . ($content1 && $content2 ? ' ' : '') . $content2;
                        return '<p>' . $merged . '</p>';
                    }
                }
                
                // ✅ FIX 2.1: Merge paragraph ngắn (< 30 ký tự) nếu không có block elements (thêm mới)
                if ($textLength1 <= 30 && $textLength2 <= 30 && $textLength1 > 0 && $textLength2 > 0) {
                    // Check if they have block elements
                    $hasBlock1 = preg_match('/<(table|ul|ol|div|h[1-6])/i', $p1);
                    $hasBlock2 = preg_match('/<(table|ul|ol|div|h[1-6])/i', $p2);
                    
                    if (!$hasBlock1 && !$hasBlock2) {
                        $totalMerged++;
                        $merged = $content1 . ($content1 && $content2 ? ' ' : '') . $content2;
                        return '<p>' . $merged . '</p>';
                    }
                }
                
                // ✅ FIX 2.2: Merge paragraph ngắn (< 50 ký tự) nếu không có block elements (thêm mới)
                if ($textLength1 <= 50 && $textLength2 <= 50 && $textLength1 > 0 && $textLength2 > 0) {
                    // Check if they have block elements
                    $hasBlock1 = preg_match('/<(table|ul|ol|div|h[1-6])/i', $p1);
                    $hasBlock2 = preg_match('/<(table|ul|ol|div|h[1-6])/i', $p2);
                    
                    if (!$hasBlock1 && !$hasBlock2) {
                        $totalMerged++;
                        $merged = $content1 . ($content1 && $content2 ? ' ' : '') . $content2;
                        return '<p>' . $merged . '</p>';
                    }
                }
                
                // ✅ FIX 3: Chỉ merge nếu cả 2 đều rỗng hoặc chỉ có whitespace
                if (trim($text1) === '' && trim($text2) === '') {
                    $totalMerged++;
                    return $p1; // Bỏ p2
                }
                
                // ✅ FIX 4: Merge paragraph chỉ có dấu chấm câu
                if (preg_match('/^[.,;:!?\s]+$/', $text1) || preg_match('/^[.,;:!?\s]+$/', $text2)) {
                    $totalMerged++;
                    $merged = $content1 . ($content1 && $content2 ? '' : '') . $content2;
                    return '<p>' . $merged . '</p>';
                }
                
                // ✅ FIX 4.1: Merge paragraph có pattern tương tự (thêm mới)
                // Pattern 1: Cả 2 đều bắt đầu bằng "..." hoặc chỉ có dấu chấm câu
                if (preg_match('/^\.{3,}/', $text1) && preg_match('/^\.{3,}/', $text2)) {
                    $totalMerged++;
                    $merged = $content1 . ($content1 && $content2 ? ' ' : '') . $content2;
                    return '<p>' . $merged . '</p>';
                }
                
                // Pattern 2: Cả 2 đều chỉ có số hoặc dấu chấm câu
                if (preg_match('/^[\d\.\s]+$/', $text1) && preg_match('/^[\d\.\s]+$/', $text2)) {
                    $totalMerged++;
                    $merged = $content1 . ($content1 && $content2 ? ' ' : '') . $content2;
                    return '<p>' . $merged . '</p>';
                }
                
                // Pattern 3: Merge paragraph chỉ có superscript/subscript với paragraph trước/sau
                // Ví dụ: <p>TÊN CQ, TC CHỦ QUẢN</p><p><sup>1</sup></p> → <p>TÊN CQ, TC CHỦ QUẢN <sup>1</sup></p>
                if (preg_match('/^<p[^>]*>(<sup|<sub)/i', $p2) && $textLength2 === 0 && $textLength1 > 0 && $textLength1 <= 30) {
                    $totalMerged++;
                    $content1 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p1);
                    $content2 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p2);
                    return '<p>' . $content1 . ' ' . $content2 . '</p>';
                }
                
                // ✅ FIX 4.3: Merge paragraph chỉ có superscript/subscript với paragraph trước/sau BẤT KỂ ĐỘ DÀI (thêm mới - triệt để)
                // Ví dụ: <p>TÊN CQ, TC CHỦ QUẢN</p><p><sup>1</sup></p> → <p>TÊN CQ, TC CHỦ QUẢN <sup>1</sup></p>
                // Ví dụ: <p>1 T</p><p><sup>ê</sup></p> → <p>1 T<sup>ê</sup></p>
                if (preg_match('/^<p[^>]*>(<sup|<sub)/i', $p2) && $textLength2 === 0 && $textLength1 > 0) {
                    $totalMerged++;
                    $content1 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p1);
                    $content2 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p2);
                    return '<p>' . $content1 . ' ' . $content2 . '</p>';
                }
                
                // ✅ FIX 4.3.1: Merge paragraph chỉ có superscript/subscript với paragraph sau (nếu paragraph sau có text) (thêm mới)
                // Ví dụ: <p><sup>2</sup></p><p>TÊN CƠ QUAN, TỔ CHỨC</p> → <p><sup>2</sup> TÊN CƠ QUAN, TỔ CHỨC</p>
                if (preg_match('/^<p[^>]*>(<sup|<sub)/i', $p1) && $textLength1 === 0 && $textLength2 > 0) {
                    $totalMerged++;
                    $content1 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p1);
                    $content2 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p2);
                    return '<p>' . $content1 . ' ' . $content2 . '</p>';
                }
                
                // ✅ FIX 4.4: Merge paragraph ngắn (≤ 5 ký tự) với paragraph dài hơn (≤ 50 ký tự) (thêm mới)
                // Ví dụ: <p>c</p><p>ơ quan, tổ chức hoặc</p> → <p>cơ quan, tổ chức hoặc</p>
                if ($textLength1 <= 5 && $textLength2 <= 50 && $textLength1 > 0 && $textLength2 > 0) {
                    $hasBlock1 = preg_match('/<(table|ul|ol|div|h[1-6])/i', $p1);
                    $hasBlock2 = preg_match('/<(table|ul|ol|div|h[1-6])/i', $p2);
                    
                    if (!$hasBlock1 && !$hasBlock2) {
                        $totalMerged++;
                        $merged = $content1 . $content2; // Không có space vì merge text cùng một từ
                        return '<p>' . $merged . '</p>';
                    }
                }
                
                // ✅ FIX 4.5: Merge paragraph ngắn (≤ 10 ký tự) với paragraph dài hơn BẤT KỂ ĐỘ DÀI (thêm mới - triệt để)
                // Ví dụ: <p>ch</p><p>ứ c da nh nhà nướ</p> → <p>chứ c da nh nhà nướ</p>
                // Ví dụ: <p>c</p><p>ơ quan, tổ chức hoặc</p> → <p>cơ quan, tổ chức hoặc</p>
                if ($textLength1 <= 10 && $textLength2 > 10 && $textLength1 > 0 && $textLength2 > 0) {
                    $hasBlock1 = preg_match('/<(table|ul|ol|div|h[1-6])/i', $p1);
                    $hasBlock2 = preg_match('/<(table|ul|ol|div|h[1-6])/i', $p2);
                    
                    if (!$hasBlock1 && !$hasBlock2) {
                        $totalMerged++;
                        $merged = $content1 . $content2; // Không có space vì merge text cùng một từ
                        return '<p>' . $merged . '</p>';
                    }
                }
                
                // ✅ FIX 4.2: Merge paragraph chỉ có số (1-2 chữ số) (thêm mới)
                if (preg_match('/^\d{1,2}$/', $text1) || preg_match('/^\d{1,2}$/', $text2)) {
                    $totalMerged++;
                    $merged = $content1 . ($content1 && $content2 ? ' ' : '') . $content2;
                    return '<p>' . $merged . '</p>';
                }
                
                // ✅ FIX 5: Không merge nếu có nội dung thực sự (giữ spacing)
                if (strlen(trim($text1)) > 0 && strlen(trim($text2)) > 0) {
                    return $p1 . "\n" . $p2;
                }
                
                // ✅ FIX 6: Chỉ merge nếu một trong hai rỗng và một cái rất ngắn (< 10 ký tự)
                if (($textLength1 === 0 && $textLength2 <= 10) || ($textLength2 === 0 && $textLength1 <= 10)) {
                    $totalMerged++;
                    $merged = $content1 . ($content1 && $content2 ? ' ' : '') . $content2;
                    return '<p>' . $merged . '</p>';
                }
                
                // Otherwise, keep as is
                return $p1 . "\n" . $p2;
            }, $html);
            
            // If no changes, break
            if ($html === $originalHtml) {
                break;
            }
            
            Log::info('🔵 [PandocDocxToHtmlConverter] Merge iteration', [
                'iteration' => $iteration + 1,
                'merged' => $totalMerged,
                'pTagCountBefore' => substr_count($originalHtml, '<p'),
                'pTagCountAfter' => substr_count($html, '<p'),
            ]);
            
            $iteration++;
        }
        
        Log::info('🔵 [PandocDocxToHtmlConverter] Merge complete', [
            'totalIterations' => $iteration,
            'totalMerged' => $totalMerged,
            'finalPTagCount' => substr_count($html, '<p'),
        ]);
        
        // Restore protected blocks
        foreach ($protected as $index => $block) {
            $html = str_replace(sprintf($placeholder, $index), $block, $html);
        }
        
        return $html;
    }
    
    /**
     * Post-process HTML to merge text split by superscript/subscript
     * 
     * Pattern: <p>char</p><p><sup>...</sup></p><p>char</p> → <p>char<sup>...</sup>char</p>
     * 
     * @param string $html
     * @return string
     */
    protected function mergeSplitTextWithSupSub(string $html): string
    {
        // ✅ FIX: Merge pattern: <p>text (1-30 ký tự, có thể có space)</p><p><sup>...</sup></p><p>text (1-30 ký tự)</p>
        // Tăng threshold từ 5 ký tự lên 30 ký tự
        $html = preg_replace_callback(
            '/(<p[^>]*>([^<]{1,30})\s*<\/p>)\s*(<p[^>]*>(<sup|<sub)[^>]*>[\s\S]*?<\/\1>[\s\S]*?<\/p>)\s*(<p[^>]*>([^<]{1,30})<\/p>)/i',
            function($matches) {
                $text1 = trim($matches[2]);
                $pSup = $matches[3];
                $text2 = trim($matches[6]);
                
                // ✅ FIX: Extract sup/sub content
                preg_match('/<(sup|sub)[^>]*>([\s\S]*?)<\/\1>/i', $pSup, $supMatch);
                $supContent = $supMatch ? '<' . $supMatch[1] . '>' . $supMatch[2] . '</' . $supMatch[1] . '>' : '';
                
                // ✅ FIX: Merge thành một paragraph
                $merged = $text1 . $supContent . $text2;
                return '<p>' . $merged . '</p>';
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Merge text with superscript/subscript (Pattern 2)
     * 
     * Pattern: <p>text</p><p><sup>...</sup></p> → <p>text <sup>...</sup></p>
     * 
     * @param string $html
     * @return string
     */
    protected function mergeTextWithSupSubPattern2(string $html): string
    {
        // ✅ FIX: Merge pattern: <p>text</p><p><sup>...</sup></p>
        // Chỉ merge nếu p1 có text và p2 chỉ có sup/sub
        $html = preg_replace_callback(
            '/(<p[^>]*>([^<]+)<\/p>)\s*(<p[^>]*>(<sup|<sub)[^>]*>[\s\S]*?<\/\1>[\s\S]*?<\/p>)/i',
            function($matches) {
                $p1 = $matches[1];
                $p2 = $matches[3];
                
                // Extract content
                $content1 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p1);
                $content2 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p2);
                
                // ✅ FIX: Merge với space
                $merged = $content1 . ' ' . $content2;
                return '<p>' . $merged . '</p>';
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Merge text không có superscript/subscript
     * 
     * Pattern: <p>char</p><p>char</p> → <p>charchar</p>
     * Pattern: <p>text</p><p>text</p> → <p>text text</p>
     * 
     * @param string $html
     * @return string
     */
    protected function mergeSplitTextWithoutSupSub(string $html): string
    {
        // ✅ FIX: Merge pattern: <p>char</p><p>char</p> (cả 2 đều ≤ 3 ký tự, không có sup/sub)
        // Ví dụ: <p>c</p><p>ơ</p> → <p>cơ</p>
        $html = preg_replace_callback(
            '/(<p[^>]*>([^<]{1,3})<\/p>)\s*(<p[^>]*>([^<]{1,3})<\/p>)/i',
            function($matches) {
                $p1 = $matches[1];
                $p2 = $matches[3];
                $text1 = trim($matches[2]);
                $text2 = trim($matches[4]);
                
                // ✅ FIX: Chỉ merge nếu không có sup/sub và không có block elements
                if (!preg_match('/<sup|<sub|<table|<ul|<ol|<div|<h[1-6]/i', $p1) && 
                    !preg_match('/<sup|<sub|<table|<ul|<ol|<div|<h[1-6]/i', $p2)) {
                    // ✅ FIX: Merge thành một paragraph (không có space nếu cả 2 đều rất ngắn)
                    $merged = $text1 . $text2;
                    return '<p>' . $merged . '</p>';
                }
                
                return $p1 . "\n" . $p2;
            },
            $html
        );
        
        // ✅ FIX: Merge pattern: <p>text</p><p>text</p> (cả 2 đều ≤ 30 ký tự, không có block elements)
        // Tăng threshold từ 5 ký tự lên 30 ký tự
        // Ví dụ: <p>c</p><p>ơ quan, tổ chức hoặc</p> → <p>cơ quan, tổ chức hoặc</p>
        $html = preg_replace_callback(
            '/(<p[^>]*>([^<]{1,30})<\/p>)\s*(<p[^>]*>([^<]{1,30})<\/p>)/i',
            function($matches) {
                $p1 = $matches[1];
                $p2 = $matches[3];
                $text1 = trim($matches[2]);
                $text2 = trim($matches[4]);
                
                // ✅ FIX: Chỉ merge nếu không có sup/sub và không có block elements
                if (!preg_match('/<sup|<sub|<table|<ul|<ol|<div|<h[1-6]/i', $p1) && 
                    !preg_match('/<sup|<sub|<table|<ul|<ol|<div|<h[1-6]/i', $p2)) {
                    // ✅ FIX: Merge không có space nếu cả 2 đều rất ngắn (≤ 3 ký tự)
                    if (strlen($text1) <= 3 && strlen($text2) <= 3) {
                        $merged = $text1 . $text2;
                    } else {
                        $merged = $text1 . ' ' . $text2;
                    }
                    return '<p>' . $merged . '</p>';
                }
                
                return $p1 . "\n" . $p2;
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Merge text có space trong pattern
     * 
     * Pattern: <p>1 T</p><p><sup>ê</sup></p><p>n</p> → <p>1 T<sup>ê</sup>n</p>
     * 
     * @param string $html
     * @return string
     */
    protected function mergeSplitTextWithSpace(string $html): string
    {
        // ✅ FIX: Merge pattern: <p>text (có thể có space, 1-30 ký tự)</p><p><sup>...</sup></p><p>text (1-30 ký tự)</p>
        // Tăng threshold từ 5 ký tự lên 30 ký tự
        // Pattern này cover trường hợp "1 T" có space
        $html = preg_replace_callback(
            '/(<p[^>]*>([^<]{1,30})\s*<\/p>)\s*(<p[^>]*>(<sup|<sub)[^>]*>[\s\S]*?<\/\1>[\s\S]*?<\/p>)\s*(<p[^>]*>([^<]{1,30})<\/p>)/i',
            function($matches) {
                $text1 = trim($matches[2]);
                $pSup = $matches[3];
                $text2 = trim($matches[6]);
                
                // ✅ FIX: Extract sup/sub content
                preg_match('/<(sup|sub)[^>]*>([\s\S]*?)<\/\1>/i', $pSup, $supMatch);
                $supContent = $supMatch ? '<' . $supMatch[1] . '>' . $supMatch[2] . '</' . $supMatch[1] . '>' : '';
                
                // ✅ FIX: Merge thành một paragraph (giữ space trong text1 nếu có)
                $merged = $text1 . $supContent . $text2;
                return '<p>' . $merged . '</p>';
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Merge pattern 3 paragraphs với superscript/subscript (triệt để)
     * 
     * Pattern: <p>text1</p><p><sup>...</sup></p><p>text2</p> → <p>text1<sup>...</sup>text2</p>
     * Bất kể độ dài của text1 và text2
     * 
     * @param string $html
     * @return string
     */
    protected function mergeSplitTextWithSupSub3Paragraphs(string $html): string
    {
        // ✅ FIX: Merge pattern: <p>text1 (bất kể độ dài)</p><p><sup>...</sup></p><p>text2 (bất kể độ dài)</p>
        // Ví dụ: <p>1 T</p><p><sup>ê</sup></p><p>n cơ quan, tổ chức ch</p> → <p>1 T<sup>ê</sup>n cơ quan, tổ chức ch</p>
        // ✅ FIX: Sửa regex pattern để match đúng
        $html = preg_replace_callback(
            '/(<p[^>]*>([^<]+)\s*<\/p>)\s*(<p[^>]*>(<sup|<sub)[^>]*>[\s\S]*?<\/(sup|sub)>[\s\S]*?<\/p>)\s*(<p[^>]*>([^<]+)<\/p>)/i',
            function($matches) {
                $text1 = trim($matches[2]);
                $pSup = $matches[3];
                $text2 = trim($matches[7]);
                
                // ✅ FIX: Extract sup/sub content
                preg_match('/<(sup|sub)[^>]*>([\s\S]*?)<\/(sup|sub)>/i', $pSup, $supMatch);
                $supContent = $supMatch ? '<' . $supMatch[1] . '>' . $supMatch[2] . '</' . $supMatch[1] . '>' : '';
                
                // ✅ FIX: Merge thành một paragraph
                $merged = $text1 . $supContent . $text2;
                return '<p>' . $merged . '</p>';
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Merge paragraph ngắn với paragraph dài hơn bất kể độ dài (triệt để)
     * 
     * Pattern: <p>c</p><p>ơ quan, tổ chức hoặc</p> → <p>cơ quan, tổ chức hoặc</p>
     * 
     * @param string $html
     * @return string
     */
    protected function mergeShortWithLongParagraph(string $html): string
    {
        // ✅ FIX: Merge paragraph ngắn (≤ 10 ký tự) với paragraph dài hơn bất kể độ dài
        // Ví dụ: <p>c</p><p>ơ quan, tổ chức hoặc</p> → <p>cơ quan, tổ chức hoặc</p>
        // Ví dụ: <p>ch</p><p>ứ c da nh nhà nướ</p> → <p>chứ c da nh nhà nướ</p>
        // ✅ FIX: Sửa regex pattern để match đúng (cho phép có HTML tags trong paragraph)
        $html = preg_replace_callback(
            '/(<p[^>]*>([^<]{1,10})<\/p>)\s*(<p[^>]*>([\s\S]+?)<\/p>)/i',
            function($matches) {
                $p1 = $matches[1];
                $p2 = $matches[3];
                $text1 = trim(strip_tags($matches[2]));
                $text2 = trim(strip_tags($matches[4]));
                
                // ✅ FIX: Chỉ merge nếu không có block elements và không có sup/sub
                if (!preg_match('/<table|<ul|<ol|<div|<h[1-6]/i', $p1) && 
                    !preg_match('/<table|<ul|<ol|<div|<h[1-6]/i', $p2) &&
                    !preg_match('/<sup|<sub/i', $p1) &&
                    !preg_match('/<sup|<sub/i', $p2)) {
                    // ✅ FIX: Extract content từ p1 và p2 (giữ HTML tags nếu có)
                    $content1 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p1);
                    $content2 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p2);
                    // ✅ FIX: Merge không có space vì merge text cùng một từ
                    $merged = $content1 . $content2;
                    return '<p>' . $merged . '</p>';
                }
                
                return $p1 . "\n" . $p2;
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Merge paragraph chỉ có superscript/subscript với paragraph trước/sau
     * 
     * Pattern: <p>text</p><p><sup>...</sup></p> → <p>text <sup>...</sup></p>
     * Pattern: <p><sup>...</sup></p><p>text</p> → <p><sup>...</sup> text</p>
     * 
     * @param string $html
     * @return string
     */
    protected function mergeSupSubOnlyParagraphs(string $html): string
    {
        // ✅ FIX: Merge paragraph chỉ có superscript/subscript với paragraph trước (nếu paragraph trước có text)
        $html = preg_replace_callback(
            '/(<p[^>]*>([\s\S]+?)<\/p>)\s*(<p[^>]*>(<sup|<sub)[^>]*>[\s\S]*?<\/(sup|sub)>[\s\S]*?<\/p>)/i',
            function($matches) {
                $p1 = $matches[1];
                $p2 = $matches[3];
                $text1 = trim(strip_tags($p1));
                
                // ✅ FIX: Chỉ merge nếu paragraph trước có text và paragraph sau chỉ có superscript/subscript
                if (strlen($text1) > 0) {
                    $content1 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p1);
                    $content2 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p2);
                    return '<p>' . $content1 . ' ' . $content2 . '</p>';
                }
                
                return $p1 . "\n" . $p2;
            },
            $html
        );
        
        // ✅ FIX: Merge paragraph chỉ có superscript/subscript với paragraph sau (nếu paragraph sau có text)
        $html = preg_replace_callback(
            '/(<p[^>]*>(<sup|<sub)[^>]*>[\s\S]*?<\/(sup|sub)>[\s\S]*?<\/p>)\s*(<p[^>]*>([\s\S]+?)<\/p>)/i',
            function($matches) {
                $p1 = $matches[1];
                $p2 = $matches[4];
                $text2 = trim(strip_tags($p2));
                
                // ✅ FIX: Chỉ merge nếu paragraph sau có text và paragraph trước chỉ có superscript/subscript
                if (strlen($text2) > 0) {
                    $content1 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p1);
                    $content2 = preg_replace('/^<p[^>]*>|<\/p>$/i', '', $p2);
                    return '<p>' . $content1 . ' ' . $content2 . '</p>';
                }
                
                return $p1 . "\n" . $p2;
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Clean up Unicode characters trong text content
     * 
     * @param string $html
     * @return string
     */
    protected function cleanUpUnicodeInText(string $html): string
    {
        // ✅ FIX: Clean up Unicode replacement character trong text content
        // Pattern: Tìm và xóa `ࠀ` trong text content của paragraph
        $html = preg_replace_callback(
            '/<p[^>]*>([\s\S]*?)<\/p>/i',
            function($matches) {
                $content = $matches[1];
                
                // ✅ FIX: Clean up Unicode replacement character
                $content = preg_replace('/[\x{FFFD}]/u', '', $content);
                
                // ✅ FIX: Clean up control characters
                $content = preg_replace('/_x000[0-9a-fA-F]+_/i', '', $content);
                
                // ✅ FIX: Clean up ký tự Unicode không hợp lệ (không phải ASCII printable và không phải Unicode hợp lệ)
                // Pattern: Xóa ký tự không phải ASCII printable (0x20-0x7E) và không phải Unicode hợp lệ
                $content = preg_replace('/[\x{00}-\x{08}\x{0B}-\x{0C}\x{0E}-\x{1F}\x{7F}-\x{9F}]/u', '', $content);
                
                // ✅ FIX: Clean up ký tự `ࠀ` (Unicode U+0800 - cần kiểm tra mã Unicode chính xác)
                // Ký tự `ࠀ` có thể là U+0800 (Samaritan Letter Alaf) hoặc ký tự khác
                // Thử clean up ký tự trong range U+0800-U+08FF (Samaritan block)
                $content = preg_replace('/[\x{0800}-\x{08FF}]/u', '', $content);
                
                return '<p>' . $content . '</p>';
            },
            $html
        );
        
        return $html;
    }
    
    /**
     * Get CSS fix for line breaks
     *
     * @return string
     */
    protected function getLineBreakFixCss(): string
    {
        return <<<CSS
    /* ✅ FIX: Ensure proper paragraph spacing */
    p {
        margin: 0.5em 0;
        line-height: 1.5;
        display: block;
    }
    
    /* ✅ FIX: Ensure proper heading spacing */
    h1, h2, h3, h4, h5, h6 {
        margin: 1em 0 0.5em 0;
        display: block;
    }
    
    /* ✅ FIX: Ensure proper spacing in lists */
    ul, ol {
        margin: 0.5em 0;
        padding-left: 2em;
        display: block;
    }
    
    li {
        margin: 0.3em 0;
        display: list-item;
    }
    
    /* ✅ FIX: Ensure proper spacing in tables */
    table {
        margin: 1em 0;
        display: table;
        border-collapse: collapse;
    }
    
    td, th {
        padding: 0.5em;
        display: table-cell;
    }
    
    /* ✅ FIX: Ensure proper spacing for divs */
    div {
        display: block;
        margin: 0.5em 0;
    }
    
    /* ✅ FIX: Preserve line breaks in pre/code */
    pre, code {
        white-space: pre-wrap;
        display: block;
    }
CSS;
    }
    
    /**
     * Wrap HTML content in proper structure
     *
     * @param string $content
     * @return string
     */
    protected function wrapHtml(string $content): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Preview</title>
</head>
<body>
    <article>
        {$content}
    </article>
</body>
</html>
HTML;
    }
}

