# Laravel on Google Cloud

Laravel 12 project configured for deployment on Google Cloud Run.

## Architecture

```
                    ┌─────────────────────────────────────────┐
                    │            Google Cloud Run              │
                    │  ┌───────┐    ┌─────────┐               │
User ──── HTTPS ───►│  │ Nginx │───►│ PHP-FPM │  (one container) │
                    │  └───────┘    └─────────┘               │
                    └──────┬──────────┬───────────┬───────────┘
                           │          │           │
                  ┌────────▼───┐ ┌────▼─────┐ ┌───▼────────────┐
                  │PlanetScale │ │Memorystore│ │ Cloud Tasks    │
                  │(PostgreSQL)│ │  (Redis)  │ │  (Queues)      │
                  └────────────┘ └──────────┘ └────────────────┘
                                                    ▲
                                             ┌──────┴────────┐
                                             │Cloud Scheduler│
                                             │   (Cron)      │
                                             └───────────────┘
```

## Service Mapping

| Laravel Feature | Service | Description |
|-----------------|---------|-------------|
| Web server | **Cloud Run** | Stateless container with Nginx + PHP-FPM |
| Database | **PlanetScale** (PostgreSQL) | Managed PostgreSQL, connects via SSL |
| Cache | **Memorystore** (Redis) | Managed Redis with private IP via VPC |
| Sessions | **Memorystore** (Redis) | Same Redis instance as cache |
| Queues / Jobs | **Cloud Tasks** | Push-based queue, sends HTTP to Cloud Run |
| Scheduled tasks | **Cloud Scheduler** | Calls `POST /cloud-scheduler/run` every minute |
| Logs | **Cloud Logging** | Automatic via `LOG_CHANNEL=stderr` |
| File storage | **Cloud Storage** | For user uploads (stateless containers have no persistent disk) |
| Secrets | **Secret Manager** | `APP_KEY`, `DB_PASSWORD`, etc. |
| SSL/HTTPS | **Cloud Run** | Automatic TLS certificate |

## Project Structure

```
├── Dockerfile                  # Multi-stage build (node → composer → php-fpm)
├── docker/
│   ├── nginx.conf              # Nginx config (listens on $PORT)
│   ├── supervisord.conf        # Manages nginx + php-fpm
│   ├── start.sh                # Entrypoint: caches config, starts services
│   ├── php.ini                 # Production PHP + OPcache settings
│   └── php-fpm-pool.conf       # FPM pool with clear_env=no
├── .dockerignore
├── .env.production.example     # Template for Cloud Run env vars
├── routes/scheduler.php        # Cloud Scheduler endpoint
└── app/Http/Middleware/
    └── VerifyCloudScheduler.php # Auth middleware for scheduler
```

## Environment Variables

All config is via Cloud Run environment variables (no `.env` file in production).
See `.env.production.example` for the full list.

Key variables:

```bash
# Database (PlanetScale PostgreSQL)
DB_CONNECTION=pgsql
DB_HOST=your-db.psdb.cloud
DB_PORT=5432
DB_SSLMODE=require

# Redis (Memorystore)
REDIS_HOST=10.0.0.X
CACHE_STORE=redis
SESSION_DRIVER=redis

# Queues (Cloud Tasks)
QUEUE_CONNECTION=cloudtasks
CLOUD_TASKS_PROJECT=my-project
CLOUD_TASKS_LOCATION=europe-west1
CLOUD_TASKS_QUEUE=default

# Scheduler
CLOUD_SCHEDULER_TOKEN=random-secret-token

# Logging
LOG_CHANNEL=stderr
```

## Local Development

Everything runs locally via Docker Compose — PostgreSQL and Redis included.

```bash
# Clone and setup
cp .env.example .env
docker compose up --build
```

App is at **http://localhost:8080**. Health check: http://localhost:8080/up

| Local Service | Container | Port | Production Equivalent |
|---------------|-----------|------|-----------------------|
| App (Nginx + PHP-FPM) | `app` | 8080 | Cloud Run |
| Queue Worker | `worker` | — | Cloud Tasks |
| PostgreSQL 17 | `postgres` | 5432 | PlanetScale |
| Redis 7 | `redis` | 6379 | Memorystore |
| Scheduler | `php artisan schedule:run` | — | Cloud Scheduler |

Locally queues use Redis + a worker container. In production, Cloud Tasks pushes HTTP requests to Cloud Run (no worker needed).

### Useful commands

```bash
# Start
docker compose up -d

# Stop
docker compose down

# Rebuild after Dockerfile changes
docker compose up --build

# Run artisan commands
docker compose exec app php artisan migrate
docker compose exec app php artisan tinker

# View logs
docker compose logs -f app

# Connect to PostgreSQL
docker compose exec postgres psql -U laravel

# Connect to Redis
docker compose exec redis redis-cli
```

### Without Docker (native)

```bash
composer install
npm install
composer dev
```

Requires PHP 8.2+, PostgreSQL, and Redis installed locally. Update `.env` hosts to `127.0.0.1`.

## Build & Deploy

```bash
# Build Docker image
docker build -t laravel-gcloud .

# Test locally
docker run -p 8080:8080 -e PORT=8080 -e APP_KEY=base64:... laravel-gcloud
curl http://localhost:8080/up

# Deploy to Cloud Run
gcloud run deploy laravel-app \
    --source . \
    --region=europe-west1 \
    --allow-unauthenticated
```

## Services Setup

### PlanetScale (PostgreSQL)
Create a database at [planetscale.com](https://planetscale.com), copy the connection credentials into env vars.

### Memorystore (Redis)
```bash
gcloud redis instances create laravel-redis \
    --size=1 \
    --region=europe-west1 \
    --redis-version=redis_7_0
```

### Cloud Tasks
```bash
gcloud tasks queues create default --location=europe-west1
```

### Cloud Scheduler
```bash
gcloud scheduler jobs create http laravel-scheduler \
    --schedule="* * * * *" \
    --uri="https://YOUR_SERVICE_URL/cloud-scheduler/run" \
    --http-method=POST \
    --headers="X-CloudScheduler-Token=YOUR_SECRET_TOKEN"
```
