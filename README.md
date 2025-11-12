# AI Hành Chính Công

Ứng dụng Laravel với AI hỗ trợ xử lý hành chính công.

## Yêu Cầu Hệ Thống

- PHP >= 8.2 với các extensions:
  - php-xml (DOMDocument)
  - php-mbstring
  - php-curl
  - php-zip
  - php-gd
  - php-mysql (hoặc php-pgsql)
- Composer
- Node.js >= 18.x và npm
- MySQL/PostgreSQL
- Redis (tùy chọn, cho queue)

## Cài Đặt

### 1. Clone Repository

```bash
git clone https://github.com/gotech-dev/ai-hanhchinhcong.git
cd ai-hanhchinhcong
```

### 2. Cài Đặt PHP Dependencies

```bash
composer install
```

### 3. Cài Đặt Node Dependencies

```bash
npm install
```

### 4. Cấu Hình Environment

```bash
cp .env.example .env
php artisan key:generate
```

Chỉnh sửa file `.env` với thông tin database và các API keys cần thiết.

### 5. Chạy Migrations

```bash
php artisan migrate
```

### 6. Chạy Seeders (Tạo tài khoản và trợ lý mặc định)

```bash
php artisan db:seed
```

Hoặc chạy từng seeder riêng:

```bash
# Chỉ tạo tài khoản
php artisan db:seed --class=UserSeeder

# Chỉ tạo assistant types
php artisan db:seed --class=AssistantTypeSystemPromptSeeder

# Chỉ tạo danh sách trợ lý
php artisan db:seed --class=AiAssistantSeeder
```

**Tài khoản mặc định được tạo:**
- **Admin**: `admin@gotechjsc.com` / `123456`
- **User**: `gotechjsc@gmail.com` / `123456`

**Danh sách trợ lý mặc định được tạo:**
- Trợ lý Q&A Tài liệu
- Trợ lý Soạn thảo Văn bản
- Trợ lý Quản lý Văn bản
- Trợ lý Quản lý Nhân sự
- Trợ lý Quản lý Tài chính
- Trợ lý Quản lý Dự án
- Trợ lý Quản lý Khiếu nại
- Trợ lý Tổ chức Sự kiện
- Trợ lý Quản lý Tài sản

### 7. Build Frontend Assets

```bash
npm run build
```

Hoặc cho development:

```bash
npm run dev
```

## Deployment trên Server

### Cài đặt PHP Extensions (Ubuntu/Debian)

Trước tiên, đảm bảo đã cài đặt các PHP extensions cần thiết:

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install php-xml php-mbstring php-curl php-zip php-gd php-mysql

# Hoặc nếu dùng PostgreSQL
sudo apt install php-xml php-mbstring php-curl php-zip php-gd php-pgsql

# Khởi động lại PHP-FPM (nếu dùng)
sudo systemctl restart php8.2-fpm
# hoặc
sudo systemctl restart php-fpm
```

### Các bước Deployment

Sau khi clone repository trên server, thực hiện các bước sau:

```bash
# 1. Cài đặt PHP dependencies
composer install --no-dev --optimize-autoloader

# 2. Cài đặt Node dependencies (QUAN TRỌNG!)
npm install

# 3. Build frontend assets
npm run build

# 4. Cấu hình environment
cp .env.example .env
# Chỉnh sửa .env với thông tin server

# 5. Generate key
php artisan key:generate

# 6. Chạy migrations
php artisan migrate --force

# 7. Chạy seeders (tạo tài khoản, assistant types và danh sách trợ lý)
php artisan db:seed

# 8. Tối ưu hóa
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Troubleshooting

### Lỗi "Class DOMDocument not found"

Cài đặt extension php-xml:

```bash
sudo apt install php-xml
sudo systemctl restart php8.2-fpm  # hoặc php-fpm
```

### Lỗi "vite: not found"

Chạy `npm install` trước khi `npm run build`:

```bash
npm install
npm run build
```

### Kiểm tra PHP Extensions

```bash
php -m | grep -E "xml|mbstring|curl|zip|gd|mysql|pgsql"
```

## Lưu Ý

- **Luôn chạy `npm install` trước khi `npm run build`** trên server
- **Đảm bảo đã cài đặt đầy đủ PHP extensions** trước khi chạy migrations
- File `node_modules` không được commit vào git (đã ignore)
- File `.env` không được commit vào git (chứa thông tin nhạy cảm)

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
