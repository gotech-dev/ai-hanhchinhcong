<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

class WebCrawlerService
{
    /**
     * Crawl content from URL
     *
     * @param string $url
     * @return array{success: bool, title?: string, description?: string, content?: string, content_length?: int, error?: string}
     */
    public function crawlUrl(string $url): array
    {
        try {
            Log::info('Starting to crawl URL', ['url' => $url]);
            
            // Fetch HTML với User-Agent hợp lệ
            $response = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'vi-VN,vi;q=0.9,en-US;q=0.8,en;q=0.7',
                ])
                ->get($url);
            
            if (!$response->successful()) {
                throw new \Exception("HTTP {$response->status()}: " . substr($response->body(), 0, 200));
            }
            
            $html = $response->body();
            
            // Check if HTML is too short (might be redirect or error page)
            if (strlen($html) < 1000) {
                Log::warning('HTML content is very short', [
                    'url' => $url,
                    'html_length' => strlen($html),
                ]);
            }
            
            // Parse HTML
            $crawler = new Crawler($html);
            
            // Extract title
            $title = $this->extractTitle($crawler, $url);
            
            // Extract main content
            $content = $this->extractContent($crawler);
            
            // Extract description (meta description hoặc first paragraph)
            $description = $this->extractDescription($crawler);
            
            Log::info('Successfully crawled URL', [
                'url' => $url,
                'title' => $title,
                'content_length' => strlen($content),
            ]);
            
            return [
                'success' => true,
                'title' => $title,
                'description' => $description,
                'content' => $content,
                'content_length' => strlen($content),
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to crawl URL', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Extract title from HTML
     */
    protected function extractTitle(Crawler $crawler, string $url): string
    {
        // Try multiple selectors
        $selectors = [
            'h1',
            'title',
            '.title',
            '.document-title',
            'h2.title',
            '[class*="title"]',
        ];
        
        foreach ($selectors as $selector) {
            try {
                $nodes = $crawler->filter($selector);
                if ($nodes->count() > 0) {
                    $title = trim($nodes->first()->text());
                    if (!empty($title) && mb_strlen($title) > 5) {
                        return $title;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        // Fallback: Extract from URL
        $parsedUrl = parse_url($url);
        return $parsedUrl['host'] ?? 'Untitled';
    }
    
    /**
     * Extract main content from HTML
     */
    protected function extractContent(Crawler $crawler): string
    {
        // Remove script, style, nav, footer, etc.
        try {
            $crawler->filter('script, style, nav, footer, header, .sidebar, .menu, .navigation, .advertisement, .ads, .login, .register, .header, .footer, .breadcrumb, .social-share, .comment, .related-articles')->each(function (Crawler $node) {
                try {
                    $node->getNode(0)->parentNode->removeChild($node->getNode(0));
                } catch (\Exception $e) {
                    // Ignore errors when removing nodes
                }
            });
        } catch (\Exception $e) {
            // Continue if removal fails
        }
        
        // Try to find main content area with more specific selectors
        $contentSelectors = [
            // Specific selectors for common Vietnamese legal sites
            '.article-content',
            '.post-content',
            '.entry-content',
            '.content-detail',
            '.detail-content',
            '.main-content',
            'article .content',
            '.article-body',
            '.post-body',
            // Generic selectors
            'main article',
            'article',
            'main',
            '.content',
            '.document-content',
            '[class*="content"]',
            '[class*="article"]',
            '[class*="post"]',
            'body',
        ];
        
        foreach ($contentSelectors as $selector) {
            try {
                $nodes = $crawler->filter($selector);
                if ($nodes->count() > 0) {
                    $content = trim($nodes->first()->text());
                    // Check if content is meaningful (not just navigation/login text)
                    if (mb_strlen($content) > 500 && !$this->isNavigationText($content)) {
                        return $content;
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        // Fallback: Get all text but filter out navigation
        try {
            $allText = trim($crawler->text());
            if (!$this->isNavigationText($allText)) {
                return $allText;
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        return '';
    }
    
    /**
     * Check if text is mostly navigation/login text
     */
    protected function isNavigationText(string $text): bool
    {
        $navigationKeywords = [
            'đăng nhập',
            'đăng ký',
            'vui lòng',
            'trải nghiệm',
            'tin chức',
            'dịch vụ',
        ];
        
        $textLower = mb_strtolower($text);
        $matches = 0;
        foreach ($navigationKeywords as $keyword) {
            if (mb_strpos($textLower, $keyword) !== false) {
                $matches++;
            }
        }
        
        // If more than 2 navigation keywords found in first 200 chars, likely navigation
        $first200 = mb_substr($textLower, 0, 200);
        return $matches >= 2 && mb_strlen($first200) < 100;
    }
    
    /**
     * Extract description
     */
    protected function extractDescription(Crawler $crawler): ?string
    {
        // Try meta description
        try {
            $metaDesc = $crawler->filter('meta[name="description"]');
            if ($metaDesc->count() > 0) {
                $desc = $metaDesc->attr('content');
                if (!empty($desc)) {
                    return trim($desc);
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        // Try first paragraph
        try {
            $paragraphs = $crawler->filter('p');
            if ($paragraphs->count() > 0) {
                $firstPara = trim($paragraphs->first()->text());
                if (mb_strlen($firstPara) > 50 && mb_strlen($firstPara) < 300) {
                    return $firstPara;
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }
        
        return null;
    }
}

