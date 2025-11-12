<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpWord\IOFactory;
use App\Services\AdvancedDocxToHtmlConverter;
use DOMDocument;
use DOMXPath;

class CompareDocxHtml extends Command
{
    protected $signature = 'docx:compare {docx_path}';
    protected $description = 'Compare DOCX file with HTML preview';

    public function handle()
    {
        $docxPath = $this->argument('docx_path');
        
        if (!file_exists($docxPath)) {
            $this->error("File not found: {$docxPath}");
            return 1;
        }
        
        $this->info("Extracting text from DOCX...");
        $docxText = $this->extractTextFromDocx($docxPath);
        
        $this->info("Converting to HTML...");
        $converter = new AdvancedDocxToHtmlConverter();
        $html = $converter->convert($docxPath);
        
        $this->info("Extracting text from HTML...");
        $htmlText = $this->extractTextFromHtml($html);
        
        $this->info("Comparing...");
        $differences = $this->compare($docxText, $htmlText);
        
        $this->report($differences, $docxText, $htmlText);
        
        return 0;
    }
    
    protected function extractTextFromDocx(string $docxPath): array
    {
        $phpWord = IOFactory::load($docxPath);
        $text = [];
        
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                    $line = '';
                    foreach ($element->getElements() as $textElement) {
                        if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
                            $line .= $textElement->getText();
                        }
                    }
                    $normalized = $this->normalizeText($line);
                    if (!empty($normalized)) {
                        $text[] = $normalized;
                    }
                }
            }
        }
        
        return $text;
    }
    
    protected function extractTextFromHtml(string $html): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        
        $text = [];
        $paragraphs = $xpath->query('//p');
        
        foreach ($paragraphs as $paragraph) {
            $normalized = $this->normalizeText($paragraph->textContent);
            if (!empty($normalized)) {
                $text[] = $normalized;
            }
        }
        
        return $text;
    }
    
    protected function normalizeText(string $text): string
    {
        // Remove extra spaces
        $text = preg_replace('/\s+/', ' ', $text);
        // Trim
        $text = trim($text);
        return $text;
    }
    
    protected function compare(array $docxText, array $htmlText): array
    {
        $differences = [];
        $maxLines = max(count($docxText), count($htmlText));
        
        for ($i = 0; $i < $maxLines; $i++) {
            $docxLine = $docxText[$i] ?? '';
            $htmlLine = $htmlText[$i] ?? '';
            
            if ($docxLine !== $htmlLine) {
                $differences[] = [
                    'line' => $i + 1,
                    'docx' => $docxLine,
                    'html' => $htmlLine,
                    'diff' => $this->computeDiff($docxLine, $htmlLine)
                ];
            }
        }
        
        return $differences;
    }
    
    protected function computeDiff(string $docx, string $html): array
    {
        $diff = [];
        $maxLen = max(mb_strlen($docx), mb_strlen($html));
        
        for ($i = 0; $i < $maxLen; $i++) {
            $docxChar = mb_substr($docx, $i, 1) ?: '';
            $htmlChar = mb_substr($html, $i, 1) ?: '';
            
            if ($docxChar !== $htmlChar) {
                $diff[] = [
                    'position' => $i,
                    'docx' => $docxChar === '' ? '[EMPTY]' : $docxChar,
                    'html' => $htmlChar === '' ? '[EMPTY]' : $htmlChar
                ];
            }
        }
        
        return $diff;
    }
    
    protected function report(array $differences, array $docxText, array $htmlText): void
    {
        $this->info("=== COMPARISON REPORT ===");
        $this->info("DOCX lines: " . count($docxText));
        $this->info("HTML lines: " . count($htmlText));
        $this->info("Differences: " . count($differences));
        $this->newLine();
        
        if (empty($differences)) {
            $this->info("✅ No differences found!");
            return;
        }
        
        $this->warn("⚠️ Found " . count($differences) . " differences:");
        $this->newLine();
        
        foreach (array_slice($differences, 0, 20) as $diff) {
            $this->line("Line {$diff['line']}:");
            $this->line("  DOCX: " . ($diff['docx'] ?: '[EMPTY]'));
            $this->line("  HTML: " . ($diff['html'] ?: '[EMPTY]'));
            
            if (!empty($diff['diff'])) {
                $this->line("  Diff: " . count($diff['diff']) . " character(s) different");
                foreach (array_slice($diff['diff'], 0, 5) as $charDiff) {
                    $this->line("    Position {$charDiff['position']}: '{$charDiff['docx']}' vs '{$charDiff['html']}'");
                }
                if (count($diff['diff']) > 5) {
                    $this->line("    ... and " . (count($diff['diff']) - 5) . " more");
                }
            }
            
            $this->newLine();
        }
        
        if (count($differences) > 20) {
            $this->warn("... and " . (count($differences) - 20) . " more differences");
        }
    }
}



