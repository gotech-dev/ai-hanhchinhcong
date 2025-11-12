<?php

namespace App\Services;

use PhpOffice\PhpWord\IOFactory;
use Spatie\PdfToText\Pdf;
use Thiagoalessio\TesseractOCR\TesseractOCR;

class DocumentProcessor
{
    /**
     * Extract text from document file (PDF, DOCX, DOC)
     *
     * @param \Illuminate\Http\UploadedFile|string $file
     * @return string
     * @throws \Exception
     */
    public function extractText($file): string
    {
        $filePath = is_string($file) ? $file : $file->getRealPath();
        $extension = is_string($file) ? pathinfo($file, PATHINFO_EXTENSION) : $file->getClientOriginalExtension();
        
        $extension = strtolower($extension);
        
        if ($extension === 'pdf') {
            return $this->extractFromPdf($filePath);
        } elseif (in_array($extension, ['doc', 'docx'])) {
            return $this->extractFromWord($filePath);
        } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            return $this->extractFromImage($filePath);
        } else {
            throw new \Exception("Unsupported file type: {$extension}");
        }
    }

    /**
     * Extract text from PDF file
     *
     * @param string $filePath
     * @return string
     */
    protected function extractFromPdf(string $filePath): string
    {
        try {
            return (new Pdf())
                ->setPdf($filePath)
                ->text();
        } catch (\Exception $e) {
            throw new \Exception("Failed to extract text from PDF: " . $e->getMessage());
        }
    }

    /**
     * Extract text from Word document (DOCX, DOC)
     *
     * @param string $filePath
     * @return string
     */
    protected function extractFromWord(string $filePath): string
    {
        try {
            $phpWord = IOFactory::load($filePath);
            $text = '';
            
            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . "\n";
                    }
                }
            }
            
            return trim($text);
        } catch (\Exception $e) {
            throw new \Exception("Failed to extract text from Word document: " . $e->getMessage());
        }
    }

    /**
     * Extract text from image using OCR
     *
     * @param string $filePath
     * @return string
     */
    protected function extractFromImage(string $filePath): string
    {
        try {
            // Check if Tesseract is available
            $tesseractPath = $this->getTesseractPath();
            if (!$tesseractPath) {
                throw new \Exception("Tesseract OCR is not installed. Please install it first.");
            }
            
            $ocr = new TesseractOCR($filePath);
            $ocr->executable($tesseractPath);
            
            // Support Vietnamese and English
            $ocr->lang('vie', 'eng');
            
            // Improve OCR quality
            $ocr->psm(6); // Assume uniform block of text
            $ocr->oem(3); // Default, based on what is available
            
            return trim($ocr->run());
        } catch (\Exception $e) {
            throw new \Exception("Failed to extract text from image: " . $e->getMessage());
        }
    }
    
    /**
     * Get Tesseract executable path
     *
     * @return string|null
     */
    protected function getTesseractPath(): ?string
    {
        // Try common paths
        $paths = [
            '/usr/bin/tesseract',
            '/usr/local/bin/tesseract',
            '/opt/homebrew/bin/tesseract', // macOS Homebrew
            'tesseract', // In PATH
        ];
        
        foreach ($paths as $path) {
            if ($path === 'tesseract') {
                // Check if it's in PATH
                $output = shell_exec('which tesseract 2>/dev/null');
                if ($output) {
                    return trim($output);
                }
            } elseif (file_exists($path) && is_executable($path)) {
                return $path;
            }
        }
        
        return null;
    }

    /**
     * Split text into chunks for embedding
     * Chia theo paragraph để giữ nguyên ngữ nghĩa
     *
     * @param string $text
     * @param int $chunkSize Maximum chunk size in characters (default: 8000)
     * @param int $overlap Overlap size between chunks in characters (default: 200)
     * @return array<string>
     */
    public function splitIntoChunks(string $text, int $chunkSize = 8000, int $overlap = 200): array
    {
        // Normalize line breaks
        $text = preg_replace('/\r\n|\r/', "\n", $text);
        
        // Split by double newlines (paragraphs)
        $paragraphs = preg_split('/\n\n+/', $text);
        
        $chunks = [];
        $currentChunk = '';
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            
            if (empty($paragraph)) {
                continue;
            }
            
            // If paragraph itself is larger than chunk size, split it by sentences
            if (strlen($paragraph) > $chunkSize) {
                // Save current chunk if exists
                if (!empty($currentChunk)) {
                    $chunks[] = trim($currentChunk);
                    $currentChunk = '';
                }
                
                // Split large paragraph into smaller chunks
                $sentenceChunks = $this->splitLargeParagraph($paragraph, $chunkSize, $overlap);
                $chunks = array_merge($chunks, $sentenceChunks);
            } elseif (strlen($currentChunk) + strlen($paragraph) + 2 > $chunkSize) {
                // Current chunk is full, save it
                if (!empty($currentChunk)) {
                    $chunks[] = trim($currentChunk);
                }
                
                // Start new chunk with overlap if needed
                if ($overlap > 0 && !empty($chunks)) {
                    $lastChunk = end($chunks);
                    $overlapText = substr($lastChunk, -$overlap);
                    $currentChunk = $overlapText . "\n\n" . $paragraph;
                } else {
                    $currentChunk = $paragraph;
                }
            } else {
                // Add paragraph to current chunk
                if (empty($currentChunk)) {
                    $currentChunk = $paragraph;
                } else {
                    $currentChunk .= "\n\n" . $paragraph;
                }
            }
        }
        
        // Add remaining chunk
        if (!empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }
        
        // Filter out empty chunks
        return array_filter($chunks, fn($chunk) => !empty(trim($chunk)));
    }

    /**
     * Split large paragraph by sentences
     *
     * @param string $paragraph
     * @param int $chunkSize
     * @param int $overlap
     * @return array<string>
     */
    protected function splitLargeParagraph(string $paragraph, int $chunkSize, int $overlap): array
    {
        // Split by sentences (., !, ?)
        $sentences = preg_split('/([.!?]+\s+)/', $paragraph, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        $chunks = [];
        $currentChunk = '';
        
        foreach ($sentences as $sentence) {
            if (strlen($currentChunk) + strlen($sentence) > $chunkSize) {
                if (!empty($currentChunk)) {
                    $chunks[] = trim($currentChunk);
                    
                    // Start new chunk with overlap
                    if ($overlap > 0) {
                        $overlapText = substr($currentChunk, -$overlap);
                        $currentChunk = $overlapText . $sentence;
                    } else {
                        $currentChunk = $sentence;
                    }
                }
            } else {
                $currentChunk .= $sentence;
            }
        }
        
        if (!empty($currentChunk)) {
            $chunks[] = trim($currentChunk);
        }
        
        return array_filter($chunks, fn($chunk) => !empty(trim($chunk)));
    }

    /**
     * Count pages in PDF (approximate)
     *
     * @param string $filePath
     * @return int
     */
    public function countPdfPages(string $filePath): int
    {
        try {
            $pdf = new Pdf();
            $pdf->setPdf($filePath);
            $text = $pdf->text();
            
            // Approximate page count based on text length
            // Average page has ~2000-3000 characters
            return max(1, (int) ceil(strlen($text) / 2500));
        } catch (\Exception $e) {
            return 1;
        }
    }
}

