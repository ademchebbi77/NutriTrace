# Docker Deployment Guide for NutriTrace

This guide explains how to run NutriTrace using Docker containers.

## 📋 Prerequisites

- Docker Desktop with WSL 2 (Windows) or Docker Engine (Linux/Mac)
- At least 2GB of free disk space
- For AI service: GPU with CUDA support (optional, see configuration below)

Check your Docker installation:
```bash
docker --version
docker compose version
```

## 🏗️ Architecture

NutriTrace uses a multi-container setup:

```
┌─────────────────────────────────────────────────────────┐
│                     Docker Compose                       │
├─────────────────┬───────────────────┬───────────────────┤
│   nutritrace-app │  nutritrace-db    │  AI Service       │
│   (Laravel)      │  (MySQL 8.0)      │  (Optional)       │
│   Port: 8000     │  Port: 3306       │  Port: 8001       │
└─────────────────┴───────────────────┴───────────────────┘
```

## 🚀 Quick Start

### Option A: AI Service on Host (Recommended for GPU)

This is the **recommended** setup when you have a GPU. The AI service runs directly on your host machine to access the GPU, while the Laravel app runs in Docker.

1. **Generate Laravel application key**:
   ```bash
   cd NutriTrace
   cp .env.example .env
   php artisan key:generate
   ```

2. **Copy the generated key** from `.env` file (look for `APP_KEY=base64:...`)

3. **Set the APP_KEY environment variable**:
   ```bash
   # Windows PowerShell
   $env:APP_KEY="base64:YOUR_GENERATED_KEY_HERE"
   
   # Linux/Mac
   export APP_KEY="base64:YOUR_GENERATED_KEY_HERE"
   ```

4. **Start the AI service on your host** (in a separate terminal):
   ```bash
   cd NutriTrace/ai
   # Make sure your virtual environment is activated
   .venv\Scripts\activate  # Windows
   # source .venv/bin/activate  # Linux/Mac
   
   # Start the AI service
   python -m uvicorn server:app --host 127.0.0.1 --port 8001
   # Or simply: start.bat (Windows)
   ```

5. **Start Docker containers**:
   ```bash
   # From project root
   docker compose up -d
   ```

6. **Wait for initialization** (first run takes 2-3 minutes):
   ```bash
   docker compose logs -f app
   ```

7. **Access the application**:
   - Laravel app: http://localhost:8000
   - AI service health: http://localhost:8001/health
   - Database: localhost:3306

### Option B: All Services in Docker (CPU Only)

If you don't have a GPU or want everything containerized:

1. **Edit `docker-compose.yml`**: Uncomment the `ai` service section (lines 77-98)

2. **Comment out the AI URL in the app environment**: Change `AI_SERVICE_URL` to point to the AI container:
   ```yaml
   # Change from:
   AI_SERVICE_URL: http://host.docker.internal:8001
   # To:
   AI_SERVICE_URL: http://ai:8001
   ```

3. **Prepare AI models**:
   ```bash
   # Make sure models are trained and available
   cd NutriTrace/ai
   ls models/  # Should contain .joblib files
   ```

4. **Start all services**:
   ```bash
   docker compose up -d
   ```

**Note**: CPU-only AI service will be slower, especially for LLM operations.

## 🛠️ Common Commands

### View logs
```bash
# All services
docker compose logs -f

# Specific service
docker compose logs -f app
docker compose logs -f db
```

### Stop services
```bash
docker compose down
```

### Stop and remove volumes (⚠️ deletes database data)
```bash
docker compose down -v
```

### Rebuild after code changes
```bash
docker compose build
docker compose up -d
```

### Run Laravel commands
```bash
# Access the app container
docker compose exec app bash

# Or run commands directly
docker compose exec app php artisan migrate
docker compose exec app php artisan make:admin
docker compose exec app php artisan test
```

### Access MySQL database
```bash
docker compose exec db mysql -u nutritrace -p
# Password: nutritrace_password
```

## 🔧 Configuration

### Environment Variables

Create a `.env` file in the project root or set environment variables:

```env
# Application key (required)
APP_KEY=base64:YOUR_GENERATED_KEY

# AI Service (optional)
AI_SERVICE_TOKEN=your_secret_token_here

# Database (optional, defaults are in docker-compose.yml)
MYSQL_ROOT_PASSWORD=nutritrace_root_password
MYSQL_PASSWORD=nutritrace_password
```

### Custom MySQL Configuration

1. Create `docker/mysql/my.cnf`:
   ```ini
   [mysqld]
   max_connections = 200
   innodb_buffer_pool_size = 256M
   ```

2. Uncomment the volume mount in `docker-compose.yml`:
   ```yaml
   volumes:
     - ./docker/mysql/my.cnf:/etc/mysql/conf.d/my.cnf
   ```

### Persistent Storage

Data is stored in Docker volumes:
- `db_data`: MySQL database files
- `app_storage`: Laravel storage (uploads, cache, logs)

To backup:
```bash
# Database
docker compose exec db mysqldump -u nutritrace -pnutritrace_password nutritrace > backup.sql

# Storage files
docker compose cp app:/var/www/html/storage ./backup-storage
```

## 🐛 Troubleshooting

### Container fails to start
```bash
# Check logs
docker compose logs app

# Inspect container
docker compose ps
```

### Database connection errors
```bash
# Verify database is healthy
docker compose ps db

# Check network connectivity
docker compose exec app ping db
```

### AI service not reachable (Option A)
- Ensure AI service is running on host: `http://localhost:8001/health`
- Check `host.docker.internal` works from container:
  ```bash
  docker compose exec app curl http://host.docker.internal:8001/health
  ```
- Windows: Ensure Docker Desktop has "Use the WSL 2 based engine" enabled

### Permission issues
```bash
# Fix storage permissions
docker compose exec app chown -R www-data:www-data storage
docker compose exec app chmod -R 755 storage
```

### Port conflicts
If ports 8000, 3306, or 8001 are already in use, modify `docker-compose.yml`:
```yaml
ports:
  - "8080:80"  # Change from 8000:80
```

## 🔐 Security Notes

**For Production Deployment:**

1. **Never use default passwords** - Change all credentials in `docker-compose.yml`
2. **Use secrets management** - Use Docker secrets or external secret managers
3. **Enable HTTPS** - Use a reverse proxy (nginx, Tracer, Caddy) with SSL certificates
4. **Restrict network access** - Don't expose MySQL port publicly
5. **Use tagged images** - Pin specific versions instead of `latest`
6. **Regular updates** - Keep base images and dependencies updated
7. **Set APP_DEBUG=false** - Disable debug mode in production
8. **Configure proper logging** - Use centralized logging (ELK, CloudWatch, etc.)

## 📊 Health Checks

All services have health checks configured:

- **App**: `GET /api/v1/health`
- **Database**: `mysqladmin ping`
- **AI Service**: `GET /health`

Check service health:
```bash
docker compose ps
```

## 🚢 CI/CD Integration

The CI workflow (`.github/workflows/ci.yml`) automatically builds Docker images on every push to verify they build successfully.

To push images to a registry:

1. **Add registry login** to your CI workflow
2. **Tag images** with version/commit hash
3. **Push** to Docker Hub, GitHub Container Registry, or private registry

Example for GitHub Container Registry:
```yaml
- name: Log in to GitHub Container Registry
  uses: docker/login-action@v3
  with:
    registry: ghcr.io
    username: ${{ github.actor }}
    password: ${{ secrets.GITHUB_TOKEN }}

- name: Build and push
  uses: docker/build-push-action@v6
  with:
    context: ./NutriTrace
    push: true
    tags: ghcr.io/${{ github.repository }}/app:${{ github.sha }}
```

## 📚 Additional Resources

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [Laravel Deployment](https://laravel.com/docs/deployment)
- [NVIDIA Container Toolkit](https://docs.nvidia.com/datacenter/cloud-native/container-toolkit/latest/install-guide.html) (for GPU support)
