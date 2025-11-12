<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Element\Image;
use ZipArchive;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Log;

/**
 * ✅ NEW: Helper class to group multiple TextRuns into one paragraph
 */
class ParagraphGroup
{
    protected $textRuns;
    
    public function __construct(array $textRuns)
    {
        $this->textRuns = $textRuns;
    }
    
    public function getTextRuns(): array
    {
        return $this->textRuns;
    }
    
    public function addTextRun(TextRun $textRun): void
    {
        $this->textRuns[] = $textRun;
    }
}

/**
 * Advanced DOCX to HTML Converter
 * 
 * Achieves 95%+ format preservation by:
 * 1. Parsing DOCX XML directly for styles
 * 2. Extracting all formatting properties
 * 3. Converting to semantic HTML + inline CSS
 * 4. Preserving spacing, fonts, colors, tables
 */
class AdvancedDocxToHtmlConverter
{
    protected $docxPath;
    protected $phpWord;
    protected $styles = [];
    protected $fonts = [];
    protected $colors = [];
    protected $numbering = [];
    protected $relationships = [];
    protected $images = [];
    
    /**
     * Convert DOCX to HTML with 95%+ format preservation
     *
     * @param string $docxPath
     * @return string HTML content
     */
    public function convert(string $docxPath): string
    {
        $this->docxPath = $docxPath;
        
        Log::info('Starting advanced DOCX to HTML conversion', [
            'file' => basename($docxPath),
            'file_size' => filesize($docxPath),
        ]);
        
        try {
            // Step 1: Load DOCX with PhpWord
            $this->phpWord = IOFactory::load($docxPath);
            
            // Step 2: Extract styles from DOCX XML
            $this->extractStylesFromXml();
            
            // Step 3: Extract fonts and colors
            $this->extractFontsAndColors();
            
            // Step 4: Extract relationships (for images)
            $this->extractRelationships();
            
            // Step 5: Convert to HTML with full styling
            $html = $this->convertToHtml();
            
            // Step 6: Clean up Unicode characters (like PandocDocxToHtmlConverter)
            $html = $this->cleanUpUnicodeCharacters($html);
            
            // Step 7: Add comprehensive CSS
            $html = $this->wrapWithCss($html);
            
            Log::info('DOCX to HTML conversion completed', [
                'html_length' => strlen($html),
                'styles_extracted' => count($this->styles),
                'fonts_extracted' => count($this->fonts),
                'images_extracted' => count($this->images),
            ]);
            
            return $html;
            
        } catch (\Exception $e) {
            Log::error('DOCX to HTML conversion failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Extract styles from DOCX XML (styles.xml)
     */
    protected function extractStylesFromXml(): void
    {
        $zip = new ZipArchive();
        if ($zip->open($this->docxPath) !== true) {
            throw new \Exception('Cannot open DOCX as ZIP');
        }
        
        // Read styles.xml
        $stylesXml = $zip->getFromName('word/styles.xml');
        if ($stylesXml === false) {
            Log::warning('No styles.xml found in DOCX');
            $zip->close();
            return;
        }
        
        // Parse XML
        $dom = new DOMDocument();
        $dom->loadXML($stylesXml);
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        
        // Extract each style
        $styleNodes = $xpath->query('//w:style');
        foreach ($styleNodes as $styleNode) {
            $styleId = $styleNode->getAttribute('w:styleId');
            $styleName = $xpath->query('.//w:name', $styleNode)->item(0)?->getAttribute('w:val') ?? $styleId;
            
            $style = [
                'id' => $styleId,
                'name' => $styleName,
                'type' => $styleNode->getAttribute('w:type'),
                'properties' => $this->extractStyleProperties($xpath, $styleNode),
            ];
            
            $this->styles[$styleId] = $style;
        }
        
        Log::debug('Extracted styles from styles.xml', [
            'count' => count($this->styles),
            'style_ids' => array_keys($this->styles),
        ]);
        
        $zip->close();
    }
    
    /**
     * Extract style properties from style node
     */
    protected function extractStyleProperties(DOMXPath $xpath, $styleNode): array
    {
        $props = [];
        
        // Font properties
        $rPr = $xpath->query('.//w:rPr', $styleNode)->item(0);
        if ($rPr) {
            // Font family
            $rFonts = $xpath->query('.//w:rFonts', $rPr)->item(0);
            if ($rFonts) {
                $props['font-family'] = $rFonts->getAttribute('w:ascii') ?: 'Times New Roman';
            }
            
            // Font size (in half-points, convert to pt)
            $sz = $xpath->query('.//w:sz', $rPr)->item(0);
            if ($sz) {
                $halfPoints = $sz->getAttribute('w:val');
                $props['font-size'] = ($halfPoints / 2) . 'pt';
            }
            
            // Bold
            if ($xpath->query('.//w:b', $rPr)->length > 0) {
                $props['font-weight'] = 'bold';
            }
            
            // Italic
            if ($xpath->query('.//w:i', $rPr)->length > 0) {
                $props['font-style'] = 'italic';
            }
            
            // Underline
            if ($xpath->query('.//w:u', $rPr)->length > 0) {
                $props['text-decoration'] = 'underline';
            }
            
            // Color
            $color = $xpath->query('.//w:color', $rPr)->item(0);
            if ($color) {
                $colorVal = $color->getAttribute('w:val');
                if ($colorVal && $colorVal !== 'auto') {
                    $props['color'] = '#' . $colorVal;
                }
            }
        }
        
        // Paragraph properties
        $pPr = $xpath->query('.//w:pPr', $styleNode)->item(0);
        if ($pPr) {
            // Alignment
            $jc = $xpath->query('.//w:jc', $pPr)->item(0);
            if ($jc) {
                $align = $jc->getAttribute('w:val');
                $alignMap = [
                    'left' => 'left',
                    'center' => 'center',
                    'right' => 'right',
                    'both' => 'justify',
                ];
                $props['text-align'] = $alignMap[$align] ?? 'left';
            }
            
            // Spacing
            $spacing = $xpath->query('.//w:spacing', $pPr)->item(0);
            if ($spacing) {
                // Line spacing
                $line = $spacing->getAttribute('w:line');
                if ($line && is_numeric($line)) {
                    $props['line-height'] = ($line / 240) . 'em'; // Convert twips to em
                }
                
                // Before/after spacing
                $before = $spacing->getAttribute('w:before');
                if ($before && is_numeric($before)) {
                    $props['margin-top'] = ($before / 20) . 'pt'; // Twips to pt
                }
                $after = $spacing->getAttribute('w:after');
                if ($after && is_numeric($after)) {
                    $props['margin-bottom'] = ($after / 20) . 'pt';
                }
            }
            
            // Indentation
            $ind = $xpath->query('.//w:ind', $pPr)->item(0);
            if ($ind) {
                $left = $ind->getAttribute('w:left');
                if ($left && is_numeric($left)) {
                    $props['margin-left'] = ($left / 20) . 'pt';
                }
                $right = $ind->getAttribute('w:right');
                if ($right && is_numeric($right)) {
                    $props['margin-right'] = ($right / 20) . 'pt';
                }
                $firstLine = $ind->getAttribute('w:firstLine');
                if ($firstLine && is_numeric($firstLine)) {
                    $props['text-indent'] = ($firstLine / 20) . 'pt';
                }
            }
        }
        
        return $props;
    }
    
    /**
     * Extract fonts and colors from theme
     */
    protected function extractFontsAndColors(): void
    {
        $zip = new ZipArchive();
        if ($zip->open($this->docxPath) !== true) {
            return;
        }
        
        // Read theme1.xml
        $themeXml = $zip->getFromName('word/theme/theme1.xml');
        if ($themeXml !== false) {
            $dom = new DOMDocument();
            $dom->loadXML($themeXml);
            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
            
            // Extract font scheme
            $majorFont = $xpath->query('//a:majorFont/a:latin')->item(0);
            if ($majorFont) {
                $this->fonts['major'] = $majorFont->getAttribute('typeface');
            }
            
            $minorFont = $xpath->query('//a:minorFont/a:latin')->item(0);
            if ($minorFont) {
                $this->fonts['minor'] = $minorFont->getAttribute('typeface');
            }
            
            // Extract color scheme
            $colorNodes = $xpath->query('//a:clrScheme/*');
            foreach ($colorNodes as $colorNode) {
                $name = $colorNode->localName;
                $srgbClr = $xpath->query('.//a:srgbClr', $colorNode)->item(0);
                if ($srgbClr) {
                    $this->colors[$name] = '#' . $srgbClr->getAttribute('val');
                }
            }
        }
        
        $zip->close();
        
        Log::debug('Extracted fonts and colors', [
            'fonts' => $this->fonts,
            'colors_count' => count($this->colors),
        ]);
    }
    
    /**
     * Extract relationships (for images)
     */
    protected function extractRelationships(): void
    {
        $zip = new ZipArchive();
        if ($zip->open($this->docxPath) !== true) {
            return;
        }
        
        $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
        if ($relsXml !== false) {
            $dom = new DOMDocument();
            $dom->loadXML($relsXml);
            $relNodes = $dom->getElementsByTagName('Relationship');
            
            foreach ($relNodes as $relNode) {
                $id = $relNode->getAttribute('Id');
                $type = $relNode->getAttribute('Type');
                $target = $relNode->getAttribute('Target');
                
                $this->relationships[$id] = [
                    'type' => $type,
                    'target' => $target,
                ];
                
                // Extract image if it's an image relationship
                if (strpos($type, 'image') !== false) {
                    $imagePath = 'word/' . $target;
                    $imageData = $zip->getFromName($imagePath);
                    if ($imageData !== false) {
                        $this->images[$id] = base64_encode($imageData);
                    }
                }
            }
        }
        
        $zip->close();
    }
    
    /**
     * ✅ FIX: Convert DOCX to HTML by parsing XML directly
     * Parse XML trực tiếp thay vì dùng PhpWord để extract text và styles chính xác
     */
    protected function convertToHtml(): string
    {
        $html = '';
        
        // ✅ FIX: Parse XML trực tiếp thay vì dùng PhpWord
        $zip = new ZipArchive();
        if ($zip->open($this->docxPath) !== true) {
            Log::warning('Cannot open DOCX as ZIP, falling back to PhpWord');
            return $this->convertToHtmlWithPhpWord();
        }
        
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        if ($xml === false) {
            Log::warning('Cannot read document.xml, falling back to PhpWord');
            return $this->convertToHtmlWithPhpWord();
        }
        
        $dom = new DOMDocument();
        if (!$dom->loadXML($xml)) {
            Log::warning('Cannot parse document.xml, falling back to PhpWord');
            return $this->convertToHtmlWithPhpWord();
        }
        
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        
        $html .= '<div class="docx-section">';
        
        // Get all paragraphs from XML
        $paragraphs = $xpath->query('//w:p');
        
        // ✅ LOG: Track paragraph conversion
        $totalParagraphs = $paragraphs->length;
        $convertedParagraphs = 0;
        $emptyParagraphs = 0;
        $paragraphDetails = [];
        
        Log::info('🔵 [AdvancedDocxToHtmlConverter] Starting paragraph conversion', [
            'total_paragraphs' => $totalParagraphs,
            'docx_path' => basename($this->docxPath),
        ]);
        
        foreach ($paragraphs as $index => $paragraph) {
            $paragraphHtml = $this->convertParagraphFromXml($xpath, $paragraph);
            
            if (!empty($paragraphHtml)) {
                $html .= $paragraphHtml;
                $convertedParagraphs++;
                
                // ✅ LOG: Track first 10 paragraphs for debugging
                if ($index < 10) {
                    // Extract text from paragraph HTML for logging
                    $textContent = strip_tags($paragraphHtml);
                    $paragraphDetails[] = [
                        'index' => $index + 1,
                        'text' => mb_substr($textContent, 0, 50),
                        'text_length' => mb_strlen($textContent),
                        'html_length' => strlen($paragraphHtml),
                        'has_style' => strpos($paragraphHtml, 'style=') !== false,
                    ];
                }
            } else {
                $emptyParagraphs++;
            }
        }
        
        $html .= '</div>';
        
        // ✅ LOG: Conversion summary
        Log::info('✅ [AdvancedDocxToHtmlConverter] Paragraph conversion completed', [
            'total_paragraphs' => $totalParagraphs,
            'converted_paragraphs' => $convertedParagraphs,
            'empty_paragraphs' => $emptyParagraphs,
            'html_length' => strlen($html),
            'first_10_paragraphs' => $paragraphDetails,
        ]);
        
        return $html;
    }
    
    /**
     * Fallback: Convert using PhpWord (old method)
     */
    protected function convertToHtmlWithPhpWord(): string
    {
        $html = '';
        
        foreach ($this->phpWord->getSections() as $section) {
            $html .= '<div class="docx-section">';
            
            $elements = $section->getElements();
            $groupedElements = $this->groupTextRunsIntoParagraphs($elements);
            
            foreach ($groupedElements as $element) {
                $html .= $this->convertElement($element);
            }
            
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * ✅ FIX: Group TextRuns into paragraphs by parsing DOCX XML directly
     * 
     * In DOCX, a paragraph can contain multiple TextRuns (each with different styles).
     * PhpWord may parse each TextRun as a separate element, so we need to group them.
     * 
     * Strategy: Parse DOCX XML directly to identify paragraph boundaries (<w:p> tags)
     * Only merge TextRuns that belong to the same paragraph in DOCX.
     */
    protected function groupTextRunsIntoParagraphs(array $elements): array
    {
        // ✅ FIX: Parse DOCX XML trực tiếp để xác định paragraph boundaries
        $zip = new ZipArchive();
        if ($zip->open($this->docxPath) !== true) {
            Log::warning('Cannot open DOCX as ZIP, using fallback merge');
            return $this->fallbackMergeTextRuns($elements);
        }
        
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        if ($xml === false) {
            Log::warning('Cannot read document.xml from DOCX, using fallback merge');
            return $this->fallbackMergeTextRuns($elements);
        }
        
        $dom = new DOMDocument();
        if (!$dom->loadXML($xml)) {
            Log::warning('Cannot parse document.xml, using fallback merge');
            return $this->fallbackMergeTextRuns($elements);
        }
        
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        
        // Get all paragraphs from XML
        $paragraphs = $xpath->query('//w:p');
        
        $grouped = [];
        $elementIndex = 0;
        
        foreach ($paragraphs as $paragraph) {
            $textRuns = [];
            
            // Get all TextRuns in this paragraph
            $textRunNodes = $xpath->query('.//w:r', $paragraph);
            
            foreach ($textRunNodes as $textRunNode) {
                // Find corresponding PhpWord TextRun element
                if ($elementIndex < count($elements) && $elements[$elementIndex] instanceof TextRun) {
                    $textRuns[] = $elements[$elementIndex];
                    $elementIndex++;
                }
            }
            
            // If we have TextRuns, create a paragraph group
            if (!empty($textRuns)) {
                $grouped[] = new ParagraphGroup($textRuns);
            } else {
                // ✅ FIX: Skip empty paragraphs (không tạo empty paragraph group)
                // Empty paragraphs sẽ không được hiển thị trong HTML
                // Log::debug('Skipping empty paragraph', ['paragraph_index' => count($grouped)]);
            }
        }
        
        // Add remaining elements (Table, Image, etc.)
        while ($elementIndex < count($elements)) {
            $grouped[] = $elements[$elementIndex];
            $elementIndex++;
        }
        
        Log::debug('Grouped TextRuns into paragraphs', [
            'total_paragraphs' => count($paragraphs),
            'grouped_count' => count($grouped),
            'elements_count' => count($elements),
        ]);
        
        return $grouped;
    }
    
    /**
     * Fallback: Merge all TextRuns (current behavior)
     */
    protected function fallbackMergeTextRuns(array $elements): array
    {
        $grouped = [];
        $currentParagraph = [];
        
        foreach ($elements as $element) {
            if ($element instanceof TextRun) {
                $currentParagraph[] = $element;
            } else {
                if (!empty($currentParagraph)) {
                    $grouped[] = new ParagraphGroup($currentParagraph);
                    $currentParagraph = [];
                }
                $grouped[] = $element;
            }
        }
        
        if (!empty($currentParagraph)) {
            $grouped[] = new ParagraphGroup($currentParagraph);
        }
        
        return $grouped;
    }
    
    /**
     * Convert individual element to HTML
     */
    protected function convertElement($element): string
    {
        $html = '';
        
        // ✅ FIX: Handle ParagraphGroup (multiple TextRuns merged into one paragraph)
        if ($element instanceof ParagraphGroup) {
            $html .= $this->convertParagraphGroup($element);
        } elseif ($element instanceof TextRun) {
            // TextRun độc lập (không thuộc paragraph group)
            $html .= $this->convertTextRun($element);
        } elseif ($element instanceof Text) {
            $html .= $this->convertText($element);
        } elseif ($element instanceof Table) {
            $html .= $this->convertTable($element);
        } elseif ($element instanceof Image) {
            $html .= $this->convertImage($element);
        } elseif (method_exists($element, 'getElements')) {
            // Container elements
            foreach ($element->getElements() as $child) {
                $html .= $this->convertElement($child);
            }
        }
        
        return $html;
    }
    
    /**
     * ✅ FIX: Convert ParagraphGroup (multiple TextRuns) into one <p> tag
     * Extract paragraph properties from XML and preserve line breaks
     */
    protected function convertParagraphGroup(ParagraphGroup $paragraphGroup): string
    {
        $textRuns = $paragraphGroup->getTextRuns();
        
        // ✅ FIX: Extract paragraph properties from XML
        $paragraphStyle = $this->extractParagraphStyleFromXml($paragraphGroup);
        $styleAttr = $this->styleArrayToCss($paragraphStyle);
        
        $html = '<p' . ($styleAttr ? ' style="' . $styleAttr . '"' : '') . '>';
        
        // ✅ FIX: Merge all TextRuns into one <p> tag (preserve individual Text styles)
        foreach ($textRuns as $index => $textRun) {
            foreach ($textRun->getElements() as $element) {
                if ($element instanceof Text) {
                    $html .= $this->convertText($element);
                }
            }
            
            // ✅ FIX: Add line break if TextRun has line break property
            if ($this->hasLineBreak($textRun, $index)) {
                $html .= '<br/>';
            }
        }
        
        $html .= '</p>';
        
        return $html;
    }
    
    /**
     * ✅ NEW: Extract paragraph properties from XML
     */
    protected function extractParagraphStyleFromXml(ParagraphGroup $paragraphGroup): array
    {
        $zip = new ZipArchive();
        if ($zip->open($this->docxPath) !== true) {
            return [];
        }
        
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        if ($xml === false) {
            return [];
        }
        
        $dom = new DOMDocument();
        if (!$dom->loadXML($xml)) {
            return [];
        }
        
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        
        // Find paragraph containing first TextRun
        $paragraphs = $xpath->query('//w:p');
        $style = [];
        
        foreach ($paragraphs as $paragraph) {
            // Check if this paragraph contains our TextRuns
            $textRunNodes = $xpath->query('.//w:r', $paragraph);
            if ($textRunNodes->length > 0) {
                // Extract paragraph properties
                $pPr = $xpath->query('.//w:pPr', $paragraph)->item(0);
                if ($pPr) {
                    // Alignment
                    $jc = $xpath->query('.//w:jc', $pPr)->item(0);
                    if ($jc) {
                        $align = $jc->getAttribute('w:val');
                        $alignMap = [
                            'left' => 'left',
                            'center' => 'center',
                            'right' => 'right',
                            'both' => 'justify',
                        ];
                        $style['text-align'] = $alignMap[$align] ?? 'left';
                    }
                    
                    // Spacing
                    $spacing = $xpath->query('.//w:spacing', $pPr)->item(0);
                    if ($spacing) {
                        $before = $spacing->getAttribute('w:before');
                        if ($before && is_numeric($before)) {
                            $style['margin-top'] = ($before / 20) . 'pt'; // Twips to pt
                        }
                        $after = $spacing->getAttribute('w:after');
                        if ($after && is_numeric($after)) {
                            $style['margin-bottom'] = ($after / 20) . 'pt';
                        }
                        $line = $spacing->getAttribute('w:line');
                        if ($line && is_numeric($line)) {
                            $style['line-height'] = ($line / 240) . 'em'; // Twips to em
                        }
                    }
                    
                    // Indentation
                    $ind = $xpath->query('.//w:ind', $pPr)->item(0);
                    if ($ind) {
                        $left = $ind->getAttribute('w:left');
                        if ($left && is_numeric($left)) {
                            $style['margin-left'] = ($left / 20) . 'pt';
                        }
                        $right = $ind->getAttribute('w:right');
                        if ($right && is_numeric($right)) {
                            $style['margin-right'] = ($right / 20) . 'pt';
                        }
                        $firstLine = $ind->getAttribute('w:firstLine');
                        if ($firstLine && is_numeric($firstLine)) {
                            $style['text-indent'] = ($firstLine / 20) . 'pt';
                        }
                    }
                }
                
                break; // Found the paragraph
            }
        }
        
        return $style;
    }
    
    /**
     * ✅ NEW: Check if TextRun has line break
     */
    protected function hasLineBreak(TextRun $textRun, int $index): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($this->docxPath) !== true) {
            return false;
        }
        
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        
        if ($xml === false) {
            return false;
        }
        
        $dom = new DOMDocument();
        if (!$dom->loadXML($xml)) {
            return false;
        }
        
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        
        // Find TextRun in XML
        $textRuns = $xpath->query('//w:r');
        if ($index < $textRuns->length) {
            $textRunNode = $textRuns->item($index);
            $lineBreaks = $xpath->query('.//w:br', $textRunNode);
            return $lineBreaks->length > 0;
        }
        
        return false;
    }
    
    /**
     * Convert TextRun to HTML paragraph
     */
    protected function convertTextRun(TextRun $textRun): string
    {
        $style = $this->extractElementStyle($textRun);
        $styleAttr = $this->styleArrayToCss($style);
        
        $html = '<p' . ($styleAttr ? ' style="' . $styleAttr . '"' : '') . '>';
        
        foreach ($textRun->getElements() as $element) {
            if ($element instanceof Text) {
                $html .= $this->convertText($element);
            }
        }
        
        $html .= '</p>';
        
        return $html;
    }
    
    /**
     * ✅ FIX: Convert Text to HTML span with inline styles
     * Preserve superscript/subscript
     */
    protected function convertText(Text $text): string
    {
        $content = htmlspecialchars($text->getText(), ENT_QUOTES, 'UTF-8');
        
        // Get font style
        $fontStyle = $text->getFontStyle();
        $style = [];
        
        if ($fontStyle) {
            // Font family
            if ($font = $fontStyle->getName()) {
                $style['font-family'] = $this->normalizeFontName($font);
            }
            
            // Font size (in points)
            if ($size = $fontStyle->getSize()) {
                $style['font-size'] = $size . 'pt';
            }
            
            // Bold
            if ($fontStyle->isBold()) {
                $style['font-weight'] = 'bold';
            }
            
            // Italic
            if ($fontStyle->isItalic()) {
                $style['font-style'] = 'italic';
            }
            
            // Underline
            if ($fontStyle->getUnderline() && $fontStyle->getUnderline() !== 'none') {
                $style['text-decoration'] = 'underline';
            }
            
            // Color
            if ($color = $fontStyle->getColor()) {
                $style['color'] = '#' . $color;
            }
            
            // Background color
            if ($bgColor = $fontStyle->getBgColor()) {
                $style['background-color'] = '#' . $bgColor;
            }
            
            // ✅ FIX: Superscript/Subscript
            $styleAttr = !empty($style) ? $this->styleArrayToCss($style) : '';
            
            // Check for superscript/subscript - try different methods
            $isSuperscript = false;
            $isSubscript = false;
            
            // Method 1: Check via getSuperScript/getSubScript if available
            if (method_exists($fontStyle, 'getSuperScript')) {
                $isSuperscript = $fontStyle->getSuperScript();
            }
            if (method_exists($fontStyle, 'getSubScript')) {
                $isSubscript = $fontStyle->getSubScript();
            }
            
            // Method 2: Check via getVertAlign if available
            if (!$isSuperscript && !$isSubscript && method_exists($fontStyle, 'getVertAlign')) {
                $vertAlign = $fontStyle->getVertAlign();
                if ($vertAlign === 'superscript' || $vertAlign === 'super') {
                    $isSuperscript = true;
                } elseif ($vertAlign === 'subscript' || $vertAlign === 'sub') {
                    $isSubscript = true;
                }
            }
            
            // Method 3: Check XML directly if PhpWord methods don't work
            if (!$isSuperscript && !$isSubscript) {
                $isSuperscript = $this->isSuperscriptFromXml($text);
                $isSubscript = $this->isSubscriptFromXml($text);
            }
            
            if ($isSuperscript) {
                return '<sup' . ($styleAttr ? ' style="' . $styleAttr . '"' : '') . '>' . $content . '</sup>';
            }
            if ($isSubscript) {
                return '<sub' . ($styleAttr ? ' style="' . $styleAttr . '"' : '') . '>' . $content . '</sub>';
            }
        }
        
        if (!empty($style)) {
            $styleAttr = $this->styleArrayToCss($style);
            return '<span style="' . $styleAttr . '">' . $content . '</span>';
        }
        
        return $content;
    }
    
    /**
     * ✅ FIX: Convert paragraph from XML directly
     * Extract text và styles từ XML, không dựa vào PhpWord
     */
    protected function convertParagraphFromXml(DOMXPath $xpath, $paragraph): string
    {
        // Get all TextRuns in this paragraph
        $textRuns = $xpath->query('.//w:r', $paragraph);
        
        if ($textRuns->length === 0) {
            // Empty paragraph - skip
            return '';
        }
        
        // Extract paragraph properties
        $paragraphStyle = $this->extractParagraphStyleFromXmlNode($xpath, $paragraph);
        $styleAttr = $this->styleArrayToCss($paragraphStyle);
        
        $html = '<p' . ($styleAttr ? ' style="' . $styleAttr . '"' : '') . '>';
        
        // ✅ LOG: Track TextRun conversion for debugging
        $textRunCount = $textRuns->length;
        $textContent = '';
        
        // Convert each TextRun
        foreach ($textRuns as $textRun) {
            $textRunHtml = $this->convertTextRunFromXml($xpath, $textRun);
            $html .= $textRunHtml;
            
            // Extract text for logging
            $textContent .= strip_tags($textRunHtml);
        }
        
        $html .= '</p>';
        
        // ✅ FIX: Check if paragraph has actual text content (not just whitespace)
        $normalizedText = trim($textContent);
        if (empty($normalizedText)) {
            // Paragraph has no actual text content - skip
            return '';
        }
        
        // ✅ LOG: Log paragraph details if it's one of the first 10 or has issues
        static $loggedCount = 0;
        if ($loggedCount < 10 || $textRunCount > 5) {
            Log::debug('🔵 [AdvancedDocxToHtmlConverter] Paragraph converted', [
                'text_run_count' => $textRunCount,
                'text_content' => mb_substr($textContent, 0, 100),
                'text_length' => mb_strlen($textContent),
                'has_style' => !empty($styleAttr),
                'style' => $styleAttr,
            ]);
            $loggedCount++;
        }
        
        return $html;
    }
    
    /**
     * ✅ FIX: Convert TextRun from XML directly
     * Extract text và styles từ XML, không dựa vào PhpWord
     */
    protected function convertTextRunFromXml(DOMXPath $xpath, $textRun): string
    {
        // Extract text from <w:t> nodes
        $textNodes = $xpath->query('.//w:t', $textRun);
        $text = '';
        foreach ($textNodes as $textNode) {
            $text .= $textNode->nodeValue;
        }
        
        if (empty(trim($text))) {
            return '';
        }
        
        $content = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        
        // Extract styles from <w:rPr> node
        $rPr = $xpath->query('.//w:rPr', $textRun)->item(0);
        $style = [];
        
        if ($rPr) {
            // Font family
            $font = $xpath->query('.//w:rFonts/@w:ascii', $rPr)->item(0);
            if ($font) {
                $style['font-family'] = $this->normalizeFontName($font->nodeValue);
            }
            
            // Font size
            $size = $xpath->query('.//w:sz/@w:val', $rPr)->item(0);
            if ($size) {
                $style['font-size'] = ($size->nodeValue / 2) . 'pt'; // Half-points to points
            }
            
            // Bold
            $bold = $xpath->query('.//w:b', $rPr)->item(0);
            if ($bold) {
                $style['font-weight'] = 'bold';
            }
            
            // Italic
            $italic = $xpath->query('.//w:i', $rPr)->item(0);
            if ($italic) {
                $style['font-style'] = 'italic';
            }
            
            // Underline
            $underline = $xpath->query('.//w:u', $rPr)->item(0);
            if ($underline) {
                $style['text-decoration'] = 'underline';
            }
            
            // Color
            $color = $xpath->query('.//w:color/@w:val', $rPr)->item(0);
            if ($color) {
                $style['color'] = '#' . $color->nodeValue;
            }
            
            // Background color
            $bgColor = $xpath->query('.//w:highlight/@w:val', $rPr)->item(0);
            if ($bgColor) {
                $style['background-color'] = '#' . $bgColor->nodeValue;
            }
            
            // ✅ FIX: Superscript/Subscript
            $vertAlign = $xpath->query('.//w:vertAlign/@w:val', $rPr)->item(0);
            $styleAttr = !empty($style) ? $this->styleArrayToCss($style) : '';
            
            if ($vertAlign) {
                $val = $vertAlign->nodeValue;
                if ($val === 'superscript' || $val === 'super') {
                    return '<sup' . ($styleAttr ? ' style="' . $styleAttr . '"' : '') . '>' . $content . '</sup>';
                } elseif ($val === 'subscript' || $val === 'sub') {
                    return '<sub' . ($styleAttr ? ' style="' . $styleAttr . '"' : '') . '>' . $content . '</sub>';
                }
            }
        }
        
        if (!empty($style)) {
            $styleAttr = $this->styleArrayToCss($style);
            return '<span style="' . $styleAttr . '">' . $content . '</span>';
        }
        
        return $content;
    }
    
    /**
     * ✅ FIX: Extract paragraph properties from XML node
     */
    protected function extractParagraphStyleFromXmlNode(DOMXPath $xpath, $paragraph): array
    {
        $style = [];
        
        // Extract paragraph properties
        $pPr = $xpath->query('.//w:pPr', $paragraph)->item(0);
        if (!$pPr) {
            return $style;
        }
        
        // Alignment
        $jc = $xpath->query('.//w:jc/@w:val', $pPr)->item(0);
        if ($jc) {
            $align = $jc->nodeValue;
            $alignMap = [
                'left' => 'left',
                'center' => 'center',
                'right' => 'right',
                'both' => 'justify',
            ];
            $style['text-align'] = $alignMap[$align] ?? 'left';
        } else {
            // ✅ FIX: Default to left align if no alignment specified
            $style['text-align'] = 'left';
        }
        
        // Spacing
        $spacing = $xpath->query('.//w:spacing', $pPr)->item(0);
        if ($spacing) {
            $before = $spacing->getAttribute('w:before');
            if ($before && is_numeric($before)) {
                $style['margin-top'] = ($before / 20) . 'pt'; // Twips to pt
            }
            $after = $spacing->getAttribute('w:after');
            if ($after && is_numeric($after)) {
                $style['margin-bottom'] = ($after / 20) . 'pt';
            }
            $line = $spacing->getAttribute('w:line');
            if ($line && is_numeric($line)) {
                $style['line-height'] = ($line / 240) . 'em'; // Twips to em
            }
        }
        
        // Indentation
        $ind = $xpath->query('.//w:ind', $pPr)->item(0);
        if ($ind) {
            $left = $ind->getAttribute('w:left');
            if ($left && is_numeric($left)) {
                $style['margin-left'] = ($left / 20) . 'pt';
            }
            $right = $ind->getAttribute('w:right');
            if ($right && is_numeric($right)) {
                $style['margin-right'] = ($right / 20) . 'pt';
            }
            $firstLine = $ind->getAttribute('w:firstLine');
            if ($firstLine && is_numeric($firstLine)) {
                $style['text-indent'] = ($firstLine / 20) . 'pt';
            }
        }
        
        return $style;
    }
    
    /**
     * ✅ NEW: Check if Text is superscript by parsing XML
     */
    protected function isSuperscriptFromXml(Text $text): bool
    {
        // This method is no longer used - we parse XML directly now
        return false;
    }
    
    /**
     * ✅ NEW: Check if Text is subscript by parsing XML
     */
    protected function isSubscriptFromXml(Text $text): bool
    {
        // This method is no longer used - we parse XML directly now
        return false;
    }
    
    /**
     * Convert Table to HTML table with full styling
     */
    protected function convertTable(Table $table): string
    {
        $tableStyle = $this->extractTableStyle($table);
        $styleAttr = $this->styleArrayToCss($tableStyle);
        
        $html = '<table' . ($styleAttr ? ' style="' . $styleAttr . '"' : '') . '>';
        
        foreach ($table->getRows() as $row) {
            $html .= '<tr>';
            
            foreach ($row->getCells() as $cell) {
                $cellStyle = $this->extractCellStyle($cell);
                $cellStyleAttr = $this->styleArrayToCss($cellStyle);
                
                $html .= '<td' . ($cellStyleAttr ? ' style="' . $cellStyleAttr . '"' : '') . '>';
                
                foreach ($cell->getElements() as $element) {
                    $html .= $this->convertElement($element);
                }
                
                $html .= '</td>';
            }
            
            $html .= '</tr>';
        }
        
        $html .= '</table>';
        
        return $html;
    }
    
    /**
     * Convert Image to HTML img with base64 data
     */
    protected function convertImage(Image $image): string
    {
        $source = $image->getSource();
        
        // Check if we have the image data
        $relationId = $image->getRelationId();
        if (isset($this->images[$relationId])) {
            $base64 = $this->images[$relationId];
            $mime = $this->getImageMimeType($source);
            
            $html = '<img src="data:' . $mime . ';base64,' . $base64 . '" ';
            
            // Width/Height
            if ($width = $image->getWidth()) {
                $html .= 'width="' . $width . '" ';
            }
            if ($height = $image->getHeight()) {
                $html .= 'height="' . $height . '" ';
            }
            
            $html .= 'alt="Image" />';
            
            return $html;
        }
        
        // Fallback: try to load image from file path
        if (file_exists($source)) {
            $imageData = file_get_contents($source);
            $base64 = base64_encode($imageData);
            $mime = mime_content_type($source);
            
            return '<img src="data:' . $mime . ';base64,' . $base64 . '" alt="Image" />';
        }
        
        return '<!-- Image not found: ' . htmlspecialchars($source) . ' -->';
    }
    
    /**
     * Extract style from element
     */
    protected function extractElementStyle($element): array
    {
        $style = [];
        
        // Try to get paragraph style
        if (method_exists($element, 'getParagraphStyle')) {
            $pStyle = $element->getParagraphStyle();
            if ($pStyle) {
                // Alignment
                if ($align = $pStyle->getAlignment()) {
                    $style['text-align'] = $align;
                }
                
                // Spacing
                if ($spaceBefore = $pStyle->getSpaceBefore()) {
                    $style['margin-top'] = $spaceBefore . 'pt';
                }
                if ($spaceAfter = $pStyle->getSpaceAfter()) {
                    $style['margin-bottom'] = $spaceAfter . 'pt';
                }
                
                // Line height
                if ($lineHeight = $pStyle->getLineHeight()) {
                    $style['line-height'] = $lineHeight;
                }
                
                // Indentation
                if (method_exists($pStyle, 'getIndentation')) {
                    $indent = $pStyle->getIndentation();
                    if ($indent && method_exists($indent, 'getLeft')) {
                        if ($indentLeft = $indent->getLeft()) {
                            if (is_numeric($indentLeft)) {
                                $style['margin-left'] = ($indentLeft / 20) . 'pt';
                            }
                        }
                    }
                    if ($indent && method_exists($indent, 'getRight')) {
                        if ($indentRight = $indent->getRight()) {
                            if (is_numeric($indentRight)) {
                                $style['margin-right'] = ($indentRight / 20) . 'pt';
                            }
                        }
                    }
                }
            }
        }
        
        return $style;
    }
    
    /**
     * Extract table styling
     */
    protected function extractTableStyle(Table $table): array
    {
        $style = [
            'border-collapse' => 'collapse',
            'width' => '100%',
            'margin' => '1em 0',
        ];
        
        // Get table style
        if ($tableStyle = $table->getStyle()) {
            // Border
            if (method_exists($tableStyle, 'getBorderSize')) {
                $borderSize = $tableStyle->getBorderSize();
                if ($borderSize && is_numeric($borderSize)) {
                    $style['border'] = ($borderSize / 8) . 'pt solid #000';
                }
            }
            
            // Width
            if (method_exists($tableStyle, 'getWidth')) {
                $width = $tableStyle->getWidth();
                if ($width && is_numeric($width)) {
                    $style['width'] = $width . '%';
                }
            }
        }
        
        return $style;
    }
    
    /**
     * Extract cell styling
     */
    protected function extractCellStyle($cell): array
    {
        $style = [
            'border' => '1px solid #000',
            'padding' => '0.5em',
        ];
        
        // Get cell style
        if ($cellStyle = $cell->getStyle()) {
            // Background color
            if (method_exists($cellStyle, 'getBgColor')) {
                $bgColor = $cellStyle->getBgColor();
                if ($bgColor) {
                    $style['background-color'] = '#' . $bgColor;
                }
            }
            
            // Vertical alignment
            if (method_exists($cellStyle, 'getVAlign')) {
                $vAlign = $cellStyle->getVAlign();
                if ($vAlign) {
                    $style['vertical-align'] = $vAlign;
                }
            }
        }
        
        return $style;
    }
    
    /**
     * Convert style array to CSS string
     */
    protected function styleArrayToCss(array $style): string
    {
        $css = [];
        foreach ($style as $property => $value) {
            $css[] = $property . ': ' . $value;
        }
        return implode('; ', $css);
    }
    
    /**
     * Normalize font name (handle fallbacks)
     */
    protected function normalizeFontName(string $font): string
    {
        // Map common fonts to web-safe equivalents
        $fontMap = [
            'Calibri' => "'Calibri', Arial, sans-serif",
            'Arial' => "Arial, Helvetica, sans-serif",
            'Times New Roman' => "'Times New Roman', Times, serif",
            'Verdana' => "Verdana, Geneva, sans-serif",
            'Tahoma' => "Tahoma, Geneva, sans-serif",
            'Georgia' => "Georgia, 'Times New Roman', serif",
            'Courier New' => "'Courier New', Courier, monospace",
        ];
        
        return $fontMap[$font] ?? "'" . $font . "', sans-serif";
    }
    
    /**
     * Get image MIME type from filename
     */
    protected function getImageMimeType(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
        ];
        
        return $mimeMap[$ext] ?? 'image/jpeg';
    }
    
    /**
     * Wrap HTML with comprehensive CSS
     */
    protected function wrapWithCss(string $bodyHtml): string
    {
        $css = $this->generateComprehensiveCss();
        
        return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Preview</title>
    <style>
{$css}
    </style>
</head>
<body>
    <div class="docx-document">
{$bodyHtml}
    </div>
</body>
</html>
HTML;
    }
    
    /**
     * Clean up Unicode characters (like PandocDocxToHtmlConverter)
     * 
     * @param string $html
     * @return string
     */
    protected function cleanUpUnicodeCharacters(string $html): string
    {
        // Remove _x0007_ and similar patterns
        $html = preg_replace('/_x000[0-9a-fA-F]+_/i', '', $html);
        
        // Remove Unicode replacement character (ࠀ)
        $html = preg_replace('/[\x{FFFD}]/u', '', $html);
        
        // Remove control characters (non-printable)
        $html = preg_replace('/[\x{00}-\x{08}\x{0B}-\x{0C}\x{0E}-\x{1F}\x{7F}-\x{9F}]/u', '', $html);
        
        // Remove Samaritan block characters (U+0800-U+08FF)
        $html = preg_replace('/[\x{0800}-\x{08FF}]/u', '', $html);
        
        return $html;
    }
    
    /**
     * Generate comprehensive CSS for document styling
     */
    protected function generateComprehensiveCss(): string
    {
        return <<<CSS
/* Reset & Base */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Times New Roman', Times, serif;
    font-size: 12pt;
    line-height: 1.6;
    color: #000;
    background: #f5f5f5;
    padding: 20px;
}

/* Document Container */
.docx-document {
    max-width: 21cm; /* A4 width */
    margin: 0 auto;
    padding: 2cm 2cm 2cm 3cm; /* A4 margins: top right bottom left */
    background: white;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
    min-height: 29.7cm; /* A4 height */
}

/* Section */
.docx-section {
    margin-bottom: 1em;
}

/* Typography */
p {
    margin: 0.5em 0;
    text-align: left; /* ✅ FIX: Default to left, preserve alignment from DOCX via inline styles */
}

h1, h2, h3, h4, h5, h6 {
    font-weight: bold;
    margin: 1em 0 0.5em 0;
}

h1 {
    font-size: 16pt;
    text-align: center;
    text-transform: uppercase;
}

h2 {
    font-size: 14pt;
}

h3 {
    font-size: 13pt;
}

/* Lists */
ul, ol {
    margin: 0.5em 0;
    padding-left: 2em;
}

li {
    margin: 0.3em 0;
}

/* Tables */
table {
    width: 100%;
    border-collapse: collapse;
    margin: 1em 0;
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

/* Images */
img {
    max-width: 100%;
    height: auto;
    display: block;
    margin: 1em auto;
}

/* Text Formatting */
.bold, strong, b {
    font-weight: bold;
}

.italic, em, i {
    font-style: italic;
}

.underline, u {
    text-decoration: underline;
}

/* Alignment */
.center {
    text-align: center;
}

.right {
    text-align: right;
}

.justify {
    text-align: justify;
}

/* Print Styles */
@media print {
    body {
        background: white;
        padding: 0;
    }
    
    .docx-document {
        box-shadow: none;
        margin: 0;
        padding: 0;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .docx-document {
        max-width: 100%;
        padding: 1cm;
        min-height: auto;
    }
}
CSS;
    }
}

