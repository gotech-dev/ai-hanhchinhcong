# AI Hành Chính Công

Ứng dụng Laravel với AI hỗ trợ xử lý hành chính công.

## Yêu Cầu Hệ Thống

- PHP >= 8.2
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

### 6. Build Frontend Assets

```bash
npm run build
```

Hoặc cho development:

```bash
npm run dev
```

## Deployment trên Server

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

# 7. Tối ưu hóa
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Lưu Ý

- **Luôn chạy `npm install` trước khi `npm run build`** trên server
- File `node_modules` không được commit vào git (đã ignore)
- File `.env` không được commit vào git (chứa thông tin nhạy cảm)

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
