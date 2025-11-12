# PHASE 1: TUÂN THỦ PHÁP LUẬT
## Timeline: 2-3 tuần

---

## 🎯 MỤC TIÊU

Xây dựng tính năng kiểm tra tuân thủ pháp luật cho văn bản hành chính, đảm bảo văn bản tuân thủ quy định pháp luật Việt Nam (Nghị định 30/2020/NĐ-CP).

---

## 🛠️ CÔNG NGHỆ SỬ DỤNG

### Backend
- **Framework:** Laravel 12 (PHP 8.2)
- **AI/ML:** OpenAI API (gpt-4o-mini, gpt-4o)
- **Vector Database:** MySQL/PostgreSQL với vector search (embeddings)
- **Document Processing:** 
  - `phpoffice/phpword` - Xử lý DOCX
  - `spatie/pdf-to-text` - Extract text từ PDF
- **Database:** MySQL/PostgreSQL

### Frontend
- **Framework:** Vue 3 + Inertia.js
- **UI:** Tailwind CSS
- **Build Tool:** Vite

### AI Services
- **OpenAI API:** 
  - Chat completion (gpt-4o-mini, gpt-4o)
  - Embeddings (text-embedding-ada-002)
  - Streaming responses

---

## 🔍 CÁCH KIỂM TRA TUÂN THỦ PHÁP LUẬT

### 1. Rule-Based Checking (Format & Structure)

**Mục đích:** Kiểm tra format và cấu trúc văn bản theo quy định cứng.

**Cách làm:**
- Sử dụng **Regex patterns** để kiểm tra format
- Sử dụng **Rule engine** để kiểm tra cấu trúc
- Tự động sửa lỗi format nếu có thể

**Ví dụ:**
```php
// Kiểm tra format số văn bản: Số: 01/BC-ABC
preg_match('/Số:\s*(\d{1,3})\/([A-Z]{2,10})-([A-Z]{2,10})/i', $text)

// Kiểm tra format ngày tháng: dd/mm/yyyy
preg_match('/\d{1,2}\/\d{1,2}\/\d{4}/', $text)

// Kiểm tra cấu trúc văn bản (phần, mục, điều)
preg_match('/Phần\s+[IVX]+|Mục\s+\d+|Điều\s+\d+/i', $text)
```

**Ưu điểm:**
- ✅ Nhanh, chính xác cho format
- ✅ Không tốn API calls
- ✅ Có thể tự động sửa lỗi

**Nhược điểm:**
- ❌ Không hiểu ngữ cảnh
- ❌ Không phát hiện lỗi logic

### 2. AI-Based Checking (Content & Compliance)

**Mục đích:** Kiểm tra nội dung và tuân thủ quy định bằng AI.

**Cách làm:**
- Sử dụng **OpenAI API** để phân tích văn bản
- So sánh với quy định trong **Regulation Database**
- Phát hiện vi phạm và gợi ý sửa lỗi

**Ví dụ:**
```php
// Gửi văn bản + quy định cho AI để phân tích
$prompt = "
Bạn là chuyên gia kiểm tra tuân thủ pháp luật Việt Nam.
Hãy kiểm tra văn bản sau có tuân thủ quy định không:

Văn bản:
{$documentText}

Quy định tham khảo:
{$regulationText}

Hãy:
1. Phát hiện các vi phạm quy định
2. Gợi ý cách sửa lỗi
3. Trích dẫn điều, khoản, điểm vi phạm
";

$response = OpenAI::chat()->create([
    'model' => 'gpt-4o-mini',
    'messages' => [
        ['role' => 'system', 'content' => 'Bạn là chuyên gia kiểm tra tuân thủ pháp luật.'],
        ['role' => 'user', 'content' => $prompt],
    ],
    'temperature' => 0.3,
    'response_format' => ['type' => 'json_object'],
]);
```

**Ưu điểm:**
- ✅ Hiểu ngữ cảnh và nội dung
- ✅ Phát hiện lỗi logic
- ✅ Gợi ý sửa lỗi thông minh

**Nhược điểm:**
- ❌ Tốn API calls (chi phí)
- ❌ Có thể không chính xác 100%

### 3. Hybrid Approach (Kết hợp cả hai)

**Mục đích:** Tận dụng ưu điểm của cả hai phương pháp.

**Cách làm:**
1. **Bước 1:** Rule-based checking (format, cấu trúc)
   - Kiểm tra format số văn bản
   - Kiểm tra format ngày tháng
   - Kiểm tra cấu trúc văn bản
   - Tự động sửa lỗi format

2. **Bước 2:** AI-based checking (nội dung, tuân thủ)
   - Chỉ gửi cho AI nếu rule-based pass
   - Phân tích nội dung và tuân thủ
   - So sánh với quy định
   - Phát hiện vi phạm

3. **Bước 3:** Vector search (tìm quy định liên quan)
   - Tìm quy định liên quan bằng semantic search
   - Trích dẫn điều, khoản, điểm

**Flow:**
```
Văn bản → Rule-based Check → Format OK? 
  ↓ Yes
AI Check (với quy định liên quan) → Vi phạm?
  ↓ Yes
Cảnh báo + Gợi ý sửa lỗi
```

### 4. Regulation Database Integration

**Mục đích:** Lưu trữ và tìm kiếm quy định pháp luật.

**Cách làm:**
- Lưu quy định vào database (bảng `regulations`)
- Index quy định vào vector DB (embeddings)
- Tìm kiếm quy định liên quan bằng semantic search
- Trích dẫn chính xác điều, khoản, điểm

**Ví dụ:**
```php
// Tìm quy định liên quan
$relatedRegulations = $vectorSearchService->searchSimilar(
    $documentText, 
    $assistantId, 
    5 // Top 5 quy định liên quan
);

// Trích dẫn điều, khoản, điểm
$citation = $regulationService->findArticle(
    $regulationId, 
    $articleNumber, 
    $clauseNumber
);
```

---

## 📚 NGUỒN QUY ĐỊNH PHÁP LUẬT

### ⚠️ VẤN ĐỀ QUAN TRỌNG: Lấy quy định ở đâu?

### 1. Nguồn quy định pháp luật Việt Nam

#### 1.1. Nguồn chính thức (Official Sources)

**A. Cơ sở dữ liệu quốc gia về văn bản pháp luật**
- **URL:** https://vbpl.vn (Cơ sở dữ liệu quốc gia về văn bản pháp luật)
- **Mô tả:** Nguồn chính thức của Bộ Tư pháp
- **Cách lấy:**
  - ✅ Có thể truy cập công khai
  - ⚠️ Không có API chính thức
  - 🔧 Cần web scraping (tuân thủ điều khoản sử dụng)

**B. Thư viện Pháp luật**
- **URL:** https://thuvienphapluat.vn
- **Mô tả:** Kho dữ liệu phong phú về văn bản pháp luật
- **Cách lấy:**
  - ✅ Có thể truy cập công khai
  - ⚠️ Không có API chính thức
  - 🔧 Cần web scraping (có thể có giới hạn)

**C. Cổng thông tin điện tử Chính phủ**
- **URL:** https://chinhphu.vn
- **Mô tả:** Các văn bản pháp luật mới ban hành
- **Cách lấy:**
  - ✅ Nguồn chính thức
  - ⚠️ Không có API
  - 🔧 Cần web scraping

**D. Các Bộ, ngành**
- **URL:** Các trang web của Bộ, ngành (vd: moj.gov.vn, mof.gov.vn)
- **Mô tả:** Quy định chuyên ngành
- **Cách lấy:**
  - ✅ Nguồn chính thức
  - ⚠️ Phân tán nhiều nguồn
  - 🔧 Cần web scraping từ nhiều nguồn

#### 1.2. Nguồn thương mại (Commercial Sources)

**A. Luật Việt Nam**
- **URL:** https://luatvietnam.vn
- **Mô tả:** Dịch vụ truy cập văn bản pháp luật
- **Cách lấy:**
  - ⚠️ Có thể cần đăng ký/trả phí
  - ✅ Có thể có API (cần liên hệ)

**B. Các công ty pháp lý**
- **Mô tả:** Các công ty cung cấp dịch vụ pháp lý
- **Cách lấy:**
  - ⚠️ Thường có phí
  - ✅ Có thể có API/Data feed

### 2. PHÂN TÍCH PHƯƠNG ÁN KHẢ THI

## 🔍 ĐIỀU TRA VÀ ĐÁNH GIÁ

### 2.1. Phương án 1: API Chính thức

#### ✅ KẾT QUẢ ĐIỀU TRA

**Tình trạng hiện tại:**
- ❌ **Chưa có API chính thức** cho toàn bộ hệ thống văn bản pháp luật Việt Nam
- ⚠️ Một số lĩnh vực đã có Open API (ngân hàng từ 01/3/2025)
- ✅ Có hướng dẫn kỹ thuật về hệ thống tiếp nhận thông tin từ 01/01/2026
- ✅ Luật Dữ Liệu đầu tiên của Việt Nam có hiệu lực từ 01/7/2025 (khuyến khích cung cấp dữ liệu)

**Cơ quan có thể cung cấp API:**
- **Bộ Tư pháp** (quản lý vbpl.vn)
- **Văn phòng Chính phủ** (quản lý chinhphu.vn)
- **Các Bộ, ngành** (quản lý quy định chuyên ngành)

**Yêu cầu sử dụng API (nếu có):**
- Xác thực qua API Key, OAuth2.0 hoặc OpenID Connect
- Đăng ký và tuân thủ quy định
- Có thể yêu cầu quyền truy cập đặc biệt

#### 📊 ĐÁNH GIÁ KHẢ THI

**Ưu điểm:**
- ✅ Dữ liệu chính xác và cập nhật (từ nguồn chính thức)
- ✅ Tuân thủ pháp luật (không vi phạm điều khoản)
- ✅ Bảo mật cao (có cơ chế xác thực)
- ✅ Ổn định và đáng tin cậy

**Nhược điểm:**
- ❌ **Chưa có API chính thức** (cần liên hệ và đăng ký)
- ❌ Quy trình đăng ký có thể phức tạp và mất thời gian
- ❌ Có thể yêu cầu quyền truy cập đặc biệt
- ❌ Phụ thuộc vào chính sách của cơ quan nhà nước

**Khả năng triển khai:**
- 🔮 **Tương lai (6-12 tháng):** Có thể có API sau khi Luật Dữ Liệu có hiệu lực
- ⚠️ **Hiện tại:** Cần liên hệ với cơ quan nhà nước để xác minh

#### 🎯 KHUYẾN NGHỊ

**Hành động ngay:**
1. ✅ Liên hệ Bộ Tư pháp để hỏi về API cho vbpl.vn
2. ✅ Liên hệ Văn phòng Chính phủ về API cho chinhphu.vn
3. ✅ Đăng ký nếu có API (có thể mất 1-3 tháng)

**Kế hoạch dài hạn:**
- Theo dõi Luật Dữ Liệu (có hiệu lực 01/7/2025)
- Theo dõi hướng dẫn kỹ thuật (có hiệu lực 01/01/2026)
- Sẵn sàng tích hợp khi có API

---

### 2.2. Phương án 2: Web Crawling (Tự động thu thập)

#### ✅ KẾT QUẢ ĐIỀU TRA

**Nguồn có thể crawl:**
- ✅ **thuvienphapluat.vn** - Công khai, có thể crawl
- ✅ **vbpl.vn** - Công khai, có thể crawl
- ✅ **chinhphu.vn** - Công khai, có thể crawl
- ✅ **congbao.chinhphu.vn** - Công báo điện tử

**Điều khoản sử dụng:**
- ⚠️ Cần kiểm tra robots.txt và Terms of Service
- ⚠️ Cần tuân thủ Luật An ninh mạng
- ⚠️ Không được crawl dữ liệu cá nhân
- ✅ Dữ liệu công khai có thể crawl (theo quy định)

**Công cụ và kỹ thuật:**
- **Python:** Scrapy, BeautifulSoup, Selenium
- **PHP:** Goutte, Symfony DOM Crawler, Guzzle
- **Node.js:** Puppeteer, Cheerio

#### 📊 ĐÁNH GIÁ KHẢ THI

**Ưu điểm:**
- ✅ **Khả thi ngay** (không cần đăng ký)
- ✅ Chủ động thu thập dữ liệu
- ✅ Linh hoạt (có thể tùy chỉnh)
- ✅ Có thể tự động hóa hoàn toàn

**Nhược điểm:**
- ⚠️ Cần tuân thủ pháp luật (Luật An ninh mạng)
- ⚠️ Cần xử lý rate limiting (tránh quá tải server)
- ⚠️ Cần xử lý cấu trúc HTML thay đổi
- ⚠️ Cần bảo trì khi website thay đổi

**Khả năng triển khai:**
- ✅ **Khả thi ngay** (có thể triển khai trong 1-2 tuần)
- ✅ Có thể tự động hóa hoàn toàn
- ✅ Có thể cập nhật định kỳ

#### 🎯 KHUYẾN NGHỊ

**Triển khai ngay:**
1. ✅ Bắt đầu với **thuvienphapluat.vn** (dễ crawl nhất)
2. ✅ Implement rate limiting (delay 2-5 giây giữa các request)
3. ✅ Respect robots.txt
4. ✅ Handle errors gracefully
5. ✅ Test với một số quy định mẫu trước

**Cách triển khai:**
- Sử dụng **PHP** (phù hợp với Laravel)
- Hoặc **Python** (nếu cần xử lý phức tạp hơn)
- Chạy qua **Queue/Job** (tránh timeout)
- Lưu vào database và index vào vector DB

---

## 📋 SO SÁNH VÀ KẾT LUẬN

| Tiêu chí | API Chính thức | Web Crawling |
|----------|----------------|--------------|
| **Khả thi hiện tại** | ❌ Chưa có | ✅ Khả thi ngay |
| **Thời gian triển khai** | 3-6 tháng | 1-2 tuần |
| **Độ chính xác** | ✅ Rất cao | ⚠️ Phụ thuộc parsing |
| **Tuân thủ pháp luật** | ✅ 100% | ⚠️ Cần cẩn thận |
| **Bảo trì** | ✅ Ít | ⚠️ Nhiều (HTML thay đổi) |
| **Chi phí** | ⚠️ Có thể có phí | ✅ Miễn phí |
| **Tự động hóa** | ✅ Hoàn toàn | ✅ Hoàn toàn |
| **Rate limiting** | ✅ Có sẵn | ⚠️ Cần tự implement |

### 🎯 KẾT LUẬN VÀ KHUYẾN NGHỊ

#### Phương án khả thi: **Web Crawling** (Ngắn hạn)

**Lý do:**
1. ✅ **Khả thi ngay** - Không cần đợi API
2. ✅ **Triển khai nhanh** - 1-2 tuần
3. ✅ **Tự động hóa** - Có thể crawl định kỳ
4. ✅ **Tuân thủ pháp luật** - Nếu crawl đúng cách

**Cách triển khai:**
- Bắt đầu với **thuvienphapluat.vn**
- Implement rate limiting
- Respect robots.txt
- Test kỹ trước khi deploy

#### Phương án lý tưởng: **API Chính thức** (Dài hạn)

**Lý do:**
1. ✅ **Chính xác 100%** - Từ nguồn chính thức
2. ✅ **Tuân thủ pháp luật** - Không có rủi ro
3. ✅ **Bảo trì ít** - Không phụ thuộc HTML
4. ✅ **Ổn định** - Có SLA từ nhà cung cấp

**Hành động:**
- Liên hệ cơ quan nhà nước ngay
- Đăng ký sử dụng API (nếu có)
- Sẵn sàng chuyển sang API khi có

---

### 2.3. Cách thu thập quy định (Chi tiết triển khai)

#### 2.3.1. Phương án 1: Web Scraping (Triển khai ngay)

**Công cụ cần cài đặt:**
```bash
# PHP (Laravel)
composer require symfony/dom-crawler
composer require symfony/css-selector
composer require guzzlehttp/guzzle

# Hoặc Python (nếu dùng Python)
pip install scrapy beautifulsoup4 requests
```

**Cách triển khai với PHP (Laravel):**

```php
// app/Services/RegulationScraper.php
namespace App\Services;

use Goutte\Client;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class RegulationScraper
{
    protected $client;
    protected $guzzleClient;
    
    public function __construct()
    {
        $this->guzzleClient = new GuzzleClient([
            'timeout' => 30,
            'verify' => false, // Tắt SSL verify nếu cần
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ],
        ]);
        
        $this->client = new Client();
        $this->client->setClient($this->guzzleClient);
    }
    
    /**
     * Crawl từ thuvienphapluat.vn
     */
    public function scrapeFromThuvienphapluat(string $url): array
    {
        try {
            // Rate limiting: delay 2-5 giây
            sleep(rand(2, 5));
            
            // Fetch page
            $crawler = $this->client->request('GET', $url);
            
            // Extract regulation data
            $regulation = [
                'title' => $this->extractTitle($crawler),
                'number' => $this->extractNumber($crawler),
                'type' => $this->extractType($crawler),
                'content' => $this->extractContent($crawler),
                'articles' => $this->extractArticles($crawler),
                'effective_date' => $this->extractEffectiveDate($crawler),
                'source_url' => $url,
                'source_type' => 'thuvienphapluat',
            ];
            
            return $regulation;
        } catch (\Exception $e) {
            Log::error('Failed to scrape from thuvienphapluat', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Crawl từ vbpl.vn
     */
    public function scrapeFromVBPL(string $url): array
    {
        try {
            // Rate limiting
            sleep(rand(2, 5));
            
            $crawler = $this->client->request('GET', $url);
            
            // Extract data (cấu trúc khác với thuvienphapluat)
            $regulation = [
                'title' => $this->extractTitleVBPL($crawler),
                'number' => $this->extractNumberVBPL($crawler),
                'type' => $this->extractTypeVBPL($crawler),
                'content' => $this->extractContentVBPL($crawler),
                'articles' => $this->extractArticlesVBPL($crawler),
                'effective_date' => $this->extractEffectiveDateVBPL($crawler),
                'source_url' => $url,
                'source_type' => 'vbpl',
            ];
            
            return $regulation;
        } catch (\Exception $e) {
            Log::error('Failed to scrape from vbpl', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
    
    /**
     * Extract title từ thuvienphapluat
     */
    protected function extractTitle($crawler): string
    {
        return $crawler->filter('h1.title, .document-title')->first()->text();
    }
    
    /**
     * Extract number (Số hiệu)
     */
    protected function extractNumber($crawler): string
    {
        // Tìm pattern: "Số: 30/2020/NĐ-CP"
        $text = $crawler->text();
        if (preg_match('/Số:\s*([\d\/\-A-Z]+)/i', $text, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }
    
    /**
     * Extract type (Loại văn bản)
     */
    protected function extractType($crawler): string
    {
        $text = $crawler->text();
        $types = ['Nghị định', 'Thông tư', 'Quyết định', 'Luật', 'Pháp lệnh'];
        
        foreach ($types as $type) {
            if (stripos($text, $type) !== false) {
                return $type;
            }
        }
        return 'Khác';
    }
    
    /**
     * Extract content (Nội dung đầy đủ)
     */
    protected function extractContent($crawler): string
    {
        return $crawler->filter('.document-content, .content, #content')->first()->text();
    }
    
    /**
     * Extract articles (Các điều, khoản, điểm)
     */
    protected function extractArticles($crawler): array
    {
        $articles = [];
        
        // Tìm các điều (Điều 1, Điều 2, ...)
        $crawler->filter('p, div')->each(function ($node) use (&$articles) {
            $text = $node->text();
            
            // Pattern: "Điều 1. Tên điều"
            if (preg_match('/Điều\s+(\d+)\.?\s*(.+)/i', $text, $matches)) {
                $articles[] = [
                    'number' => (int)$matches[1],
                    'title' => trim($matches[2]),
                    'content' => $text,
                ];
            }
        });
        
        return $articles;
    }
    
    /**
     * Extract effective date
     */
    protected function extractEffectiveDate($crawler): ?string
    {
        $text = $crawler->text();
        
        // Pattern: "Có hiệu lực từ ngày dd/mm/yyyy"
        if (preg_match('/(?:hiệu lực|có hiệu lực).*?(\d{1,2}\/\d{1,2}\/\d{4})/i', $text, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    // Similar methods for VBPL...
    protected function extractTitleVBPL($crawler): string { /* ... */ }
    protected function extractNumberVBPL($crawler): string { /* ... */ }
    // ...
}
```

**Queue Job để crawl tự động:**
```php
// app/Jobs/CrawlRegulationJob.php
namespace App\Jobs;

use App\Services\RegulationScraper;
use App\Models\Regulation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CrawlRegulationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    protected $url;
    protected $source;
    
    public function __construct(string $url, string $source = 'thuvienphapluat')
    {
        $this->url = $url;
        $this->source = $source;
    }
    
    public function handle(RegulationScraper $scraper)
    {
        try {
            // Crawl regulation
            $data = $scraper->scrapeFromThuvienphapluat($this->url);
            
            // Save to database
            $regulation = Regulation::updateOrCreate(
                ['number' => $data['number']],
                $data
            );
            
            // Index to vector DB
            // ... (sử dụng VectorSearchService)
            
        } catch (\Exception $e) {
            \Log::error('Failed to crawl regulation', [
                'url' => $this->url,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
```

**Cron job để crawl định kỳ:**
```php
// app/Console/Commands/CrawlNewRegulations.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\RegulationScraper;
use App\Jobs\CrawlRegulationJob;

class CrawlNewRegulations extends Command
{
    protected $signature = 'regulations:crawl-new';
    protected $description = 'Crawl new regulations from sources';
    
    public function handle()
    {
        // Danh sách URL cần crawl
        $urls = [
            'https://thuvienphapluat.vn/van-ban/...',
            // ...
        ];
        
        foreach ($urls as $url) {
            CrawlRegulationJob::dispatch($url, 'thuvienphapluat');
        }
        
        $this->info('Dispatched ' . count($urls) . ' crawl jobs');
    }
}
```

**Schedule trong app/Console/Kernel.php:**
```php
protected function schedule(Schedule $schedule)
{
    // Crawl mới mỗi ngày lúc 2h sáng
    $schedule->command('regulations:crawl-new')
        ->dailyAt('02:00');
}
```

#### 2.3.2. Phương án 2: API Integration (Khi có API)

**Cách triển khai khi có API:**
```php
// app/Services/RegulationApiService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RegulationApiService
{
    protected $apiKey;
    protected $baseUrl;
    
    public function __construct()
    {
        $this->apiKey = config('services.regulation_api.key');
        $this->baseUrl = config('services.regulation_api.base_url');
    }
    
    /**
     * Fetch regulation by number
     */
    public function getRegulation(string $number): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
        ])->get("{$this->baseUrl}/regulations/{$number}");
        
        if ($response->successful()) {
            return $response->json();
        }
        
        throw new \Exception('Failed to fetch regulation: ' . $response->body());
    }
    
    /**
     * Search regulations
     */
    public function searchRegulations(string $query, array $filters = []): array
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
        ])->get("{$this->baseUrl}/regulations/search", [
            'q' => $query,
            ...$filters,
        ]);
        
        return $response->json();
    }
}
```

---

## 📝 TÓM TẮT KHUYẾN NGHỊ

### ✅ Phương án triển khai ngay: **Web Crawling**

1. **Bắt đầu với thuvienphapluat.vn**
   - Dễ crawl nhất
   - Dữ liệu phong phú
   - Công khai

2. **Implement rate limiting**
   - Delay 2-5 giây giữa các request
   - Respect robots.txt
   - Không quá tải server

3. **Tự động hóa hoàn toàn**
   - Queue job để crawl
   - Cron job để crawl định kỳ
   - Tự động lưu và index

4. **Tuân thủ pháp luật**
   - Chỉ crawl dữ liệu công khai
   - Không crawl dữ liệu cá nhân
   - Tuân thủ Luật An ninh mạng

### 🔮 Phương án tương lai: **API Chính thức**

1. **Liên hệ cơ quan nhà nước ngay**
   - Bộ Tư pháp (vbpl.vn)
   - Văn phòng Chính phủ (chinhphu.vn)

2. **Theo dõi Luật Dữ Liệu**
   - Có hiệu lực 01/7/2025
   - Khuyến khích cung cấp dữ liệu

3. **Sẵn sàng chuyển sang API**
   - Khi có API chính thức
   - Đảm bảo tính chính xác 100%

---

### 3. Database Schema cho Quy định

```php
// database/migrations/xxxx_create_regulations_table.php
Schema::create('regulations', function (Blueprint $table) {
    $table->id();
    $table->string('title'); // Tên quy định
    $table->string('number'); // Số hiệu (vd: 30/2020/NĐ-CP)
    $table->string('type'); // Loại (Nghị định, Thông tư, Quyết định, ...)
    $table->text('content'); // Nội dung đầy đủ
    $table->json('articles'); // Các điều, khoản, điểm (structured)
    $table->date('effective_date'); // Ngày có hiệu lực
    $table->date('expiry_date')->nullable(); // Ngày hết hiệu lực
    $table->string('status')->default('active'); // active, expired, replaced
    $table->string('source_url')->nullable(); // URL nguồn
    $table->string('source_type')->nullable(); // vbpl, thuvienphapluat, manual
    $table->timestamps();
    
    $table->index('number');
    $table->index('type');
    $table->index('status');
    $table->index('effective_date');
});

// JSON structure cho articles:
// {
//   "articles": [
//     {
//       "number": 1,
//       "title": "Phạm vi điều chỉnh",
//       "content": "...",
//       "clauses": [
//         {
//           "number": 1,
//           "content": "..."
//         }
//       ]
//     }
//   ]
// }
```

### 4. Quy trình thu thập và cập nhật

#### 4.1. Quy trình ban đầu (Initial Setup)

**Bước 1: Thu thập quy định cơ bản**
- [ ] Nghị định 30/2020/NĐ-CP (Công tác văn thư) - **QUAN TRỌNG NHẤT**
- [ ] Các quy định phổ biến khác (tùy nhu cầu)
- [ ] Manual import hoặc web scraping

**Bước 2: Parse và lưu vào database**
- [ ] Extract text từ file/HTML
- [ ] Parse structure (điều, khoản, điểm)
- [ ] Lưu vào bảng `regulations`
- [ ] Index vào vector DB

**Bước 3: Test và verify**
- [ ] Test tìm kiếm quy định
- [ ] Test trích dẫn điều, khoản, điểm
- [ ] Verify độ chính xác

#### 4.2. Quy trình cập nhật (Ongoing Updates)

**Tự động (nếu có API/web scraping):**
- [ ] Cron job chạy hàng ngày/tuần
- [ ] Check quy định mới
- [ ] Tự động import và index

**Thủ công (khuyến nghị):**
- [ ] Admin check quy định mới định kỳ
- [ ] Manual import khi có quy định mới
- [ ] Review và approve trước khi publish

### 5. Khuyến nghị triển khai

#### Phase 1.1: Manual Import (Tuần 1)
- ✅ Bắt đầu với **Nghị định 30/2020/NĐ-CP** (quan trọng nhất)
- ✅ Admin upload file DOCX/PDF
- ✅ Hệ thống extract và parse
- ✅ Admin review và approve
- ✅ Lưu vào database và index

#### Phase 1.2: Web Scraping (Tuần 2-3)
- ⚠️ Chỉ nếu có thể tuân thủ điều khoản sử dụng
- ⚠️ Implement rate limiting
- ⚠️ Handle errors gracefully
- ⚠️ Test với một số quy định mẫu

#### Phase 1.3: API Integration (Tương lai)
- 🔮 Liên hệ với cơ quan nhà nước
- 🔮 Đăng ký sử dụng API/Data feed
- 🔮 Tích hợp vào hệ thống

### 6. Quy định ưu tiên ban đầu

**Danh sách quy định cần thu thập trước:**

1. **Nghị định 30/2020/NĐ-CP** - Công tác văn thư ⭐⭐⭐
   - Quy định về format văn bản hành chính
   - Quan trọng nhất cho Phase 1

2. **Nghị định 01/2021/NĐ-CP** - Đăng ký doanh nghiệp
   - Nếu cần hỗ trợ thủ tục đăng ký

3. **Các Thông tư hướng dẫn** (tùy nhu cầu)
   - Thông tư hướng dẫn Nghị định 30
   - Các thông tư khác liên quan

**Cách lấy:**
- Download từ https://thuvienphapluat.vn
- Hoặc https://vbpl.vn
- Manual import vào hệ thống

---

## 📋 TODO LIST

### 1. Legal Compliance Checker

- [ ] Tạo service `LegalComplianceChecker`
  - [ ] Kiểm tra format số và ký hiệu văn bản (Số: 01/BC-ABC)
  - [ ] Kiểm tra format ngày tháng (dd/mm/yyyy)
  - [ ] Kiểm tra cấu trúc văn bản (theo Nghị định 30)
  - [ ] Kiểm tra thuật ngữ pháp lý chính xác
  - [ ] Phát hiện vi phạm quy định
  - [ ] Tạo cảnh báo và gợi ý sửa lỗi

- [ ] Tích hợp vào `ReportGenerator`
  - [ ] Tự động kiểm tra khi tạo báo cáo
  - [ ] Hiển thị cảnh báo trong chat
  - [ ] Cho phép user xem chi tiết lỗi

- [ ] Tích hợp vào `SmartAssistantEngine`
  - [ ] Kiểm tra trước khi trả lời
  - [ ] Gợi ý sửa lỗi tự động

### 2. Regulation Database

- [ ] Tạo migration cho bảng `regulations`
  - [ ] `id`, `title`, `number`, `type` (Nghị định, Thông tư, ...)
  - [ ] `content` (nội dung quy định)
  - [ ] `articles` (JSON: các điều, khoản, điểm)
  - [ ] `effective_date`, `expiry_date`
  - [ ] `status` (active, expired, replaced)
  - [ ] `created_at`, `updated_at`

- [ ] Tạo model `Regulation`
  - [ ] Relationships
  - [ ] Scopes (active, expired, ...)
  - [ ] Methods (search, findArticle, ...)

- [ ] Tạo seeder cho quy định cơ bản
  - [ ] Nghị định 30/2020/NĐ-CP (Công tác văn thư)
  - [ ] Các quy định phổ biến khác

- [ ] Tạo service `RegulationService`
  - [ ] Tìm kiếm quy định liên quan
  - [ ] Trích dẫn điều, khoản, điểm
  - [ ] Cập nhật quy định mới

- [ ] Tích hợp vào `VectorSearchService`
  - [ ] Index quy định vào vector DB
  - [ ] Tìm kiếm semantic trong quy định

### 3. Format Checker cho văn bản hành chính

- [ ] Tạo service `DocumentFormatChecker`
  - [ ] Kiểm tra format số văn bản (Số: XX/YY-ZZZ)
  - [ ] Kiểm tra format ngày tháng (dd/mm/yyyy)
  - [ ] Kiểm tra format địa danh (Tỉnh/Thành phố, Quận/Huyện, ...)
  - [ ] Kiểm tra format tên cơ quan (viết hoa đúng quy định)
  - [ ] Kiểm tra format chữ ký và con dấu
  - [ ] Kiểm tra cấu trúc văn bản (phần, mục, điều, khoản)

- [ ] Tạo rules cho từng loại văn bản
  - [ ] Báo cáo
  - [ ] Công văn
  - [ ] Quyết định
  - [ ] Tờ trình
  - [ ] Biên bản

- [ ] Tích hợp vào `ReportGenerator`
  - [ ] Tự động kiểm tra format khi tạo báo cáo
  - [ ] Tự động sửa format nếu có thể
  - [ ] Cảnh báo nếu không thể tự sửa

### 4. API Endpoints

- [ ] `POST /api/compliance/check`
  - [ ] Kiểm tra tuân thủ cho văn bản
  - [ ] Trả về danh sách lỗi và cảnh báo

- [ ] `GET /api/regulations`
  - [ ] Danh sách quy định
  - [ ] Tìm kiếm quy định

- [ ] `GET /api/regulations/{id}`
  - [ ] Chi tiết quy định
  - [ ] Trích dẫn điều, khoản, điểm

- [ ] `POST /api/regulations`
  - [ ] Thêm quy định mới (admin only)

- [ ] `PUT /api/regulations/{id}`
  - [ ] Cập nhật quy định (admin only)

### 5. Frontend Components

- [ ] `ComplianceChecker.vue`
  - [ ] Hiển thị kết quả kiểm tra
  - [ ] Danh sách lỗi và cảnh báo
  - [ ] Gợi ý sửa lỗi

- [ ] `RegulationViewer.vue`
  - [ ] Hiển thị quy định
  - [ ] Tìm kiếm quy định
  - [ ] Trích dẫn điều, khoản, điểm

- [ ] Tích hợp vào chat interface
  - [ ] Hiển thị cảnh báo trong chat
  - [ ] Link đến quy định liên quan

### 6. Testing

- [ ] Unit tests cho `LegalComplianceChecker`
- [ ] Unit tests cho `RegulationService`
- [ ] Unit tests cho `DocumentFormatChecker`
- [ ] Feature tests cho API endpoints
- [ ] Integration tests cho workflow

### 7. Documentation

- [ ] Document API endpoints
- [ ] Document cách sử dụng
- [ ] Document cách thêm quy định mới
- [ ] Update README

---

## 📅 TIMELINE

### Tuần 1: Legal Compliance Checker
- Ngày 1-2: Tạo service `LegalComplianceChecker`
- Ngày 3-4: Tích hợp vào `ReportGenerator`
- Ngày 5: Testing và fix bugs

### Tuần 2: Regulation Database
- Ngày 1-2: Tạo migration, model, seeder
- Ngày 3-4: Tạo service `RegulationService`
- Ngày 5: Tích hợp vào `VectorSearchService`

### Tuần 3: Format Checker & Integration
- Ngày 1-2: Tạo service `DocumentFormatChecker`
- Ngày 3: Tạo API endpoints
- Ngày 4: Tạo Frontend components
- Ngày 5: Testing tổng hợp và fix bugs

---

## ✅ KẾT QUẢ MONG ĐỢI

Sau khi hoàn thành Phase 1, hệ thống có thể:

1. ✅ Tự động kiểm tra tuân thủ pháp luật cho văn bản
2. ✅ Cảnh báo và gợi ý sửa lỗi vi phạm quy định
3. ✅ Trích dẫn chính xác quy định pháp luật
4. ✅ Kiểm tra format văn bản hành chính chuẩn
5. ✅ Tự động sửa format nếu có thể

---

## 🔗 LIÊN KẾT

- [Nghị định 30/2020/NĐ-CP](https://thuvienphapluat.vn/van-ban/Bo-may-hanh-chinh/Nghi-dinh-30-2020-ND-CP-cong-tac-van-thu-440111.aspx)
- [advanced-feature.md](./advanced-feature.md) - Tài liệu tổng quan về các tính năng nâng cao

