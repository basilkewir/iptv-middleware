# IPTV Middleware

A comprehensive IPTV middleware platform built with Laravel 10, providing channel management, streaming, VOD, EPG, subscription, and payment functionality.

## Requirements

- PHP 8.1+
- MySQL 8.0+
- Redis 7.0+
- Node.js 18+
- FFmpeg (for transcoding)

## Setup

```bash
# Clone and install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Configure .env with your database, Redis, and streaming settings

# Database setup
php artisan migrate
php artisan db:seed

# Start development servers
php artisan serve
npm run dev
```

## Docker Setup

```bash
docker-compose up -d
```

## Testing

```bash
php artisan test
```

## API Documentation

The API is versioned under `/api/v1`. Key endpoints:

- **Auth**: `POST /api/v1/auth/register`, `POST /api/v1/auth/login`
- **Channels**: `GET /api/v1/channels`, `GET /api/v1/channels/{slug}`
- **VOD**: `GET /api/v1/vod`, `GET /api/v1/vod/{slug}`
- **EPG**: `GET /api/v1/epg`, `GET /api/v1/epg/{channel}`
- **Subscriptions**: `GET /api/v1/subscription`, `POST /api/v1/subscription/subscribe`
- **Payments**: `GET /api/v1/payment/methods`, `POST /api/v1/payment/invoice`

## Project Structure

```
app/
├── Console/Commands/       # Artisan commands
├── Http/Controllers/Api/   # API controllers
├── Models/                 # Eloquent models
├── Repositories/           # Data access layer
├── Services/               # Business logic
config/
├── streaming.php           # Streaming server config
├── epg.php                 # EPG config
├── payment.php             # Payment gateway config
database/
├── migrations/             # Database migrations
├── seeders/                # Database seeders
routes/
├── api.php                 # API routes
```
