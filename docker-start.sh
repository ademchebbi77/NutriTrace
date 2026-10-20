#!/bin/bash
# Quick start script for NutriTrace with Docker (Linux/Mac)

set -e

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo "========================================"
echo " NutriTrace Docker Quick Start"
echo "========================================"
echo

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    echo -e "${RED}[ERROR]${NC} Docker is not installed"
    echo "Please install Docker from https://docs.docker.com/get-docker/"
    exit 1
fi

echo -e "${GREEN}[OK]${NC} Docker is installed"
echo

# Check if .env file exists
if [ ! -f ".env" ]; then
    echo -e "${YELLOW}[INFO]${NC} No .env file found. Checking for APP_KEY..."
    
    if [ -f "NutriTrace/.env" ]; then
        echo -e "${YELLOW}[INFO]${NC} Found NutriTrace/.env file"
        
        APP_KEY=$(grep "^APP_KEY=" NutriTrace/.env | cut -d'=' -f2)
        
        if [ -n "$APP_KEY" ]; then
            echo -e "${GREEN}[OK]${NC} APP_KEY found"
            echo "APP_KEY=$APP_KEY" > .env
            echo -e "${GREEN}[OK]${NC} Created .env file with APP_KEY"
        else
            echo -e "${RED}[ERROR]${NC} APP_KEY not found in NutriTrace/.env"
            echo "Please run: cd NutriTrace && php artisan key:generate"
            exit 1
        fi
    else
        echo -e "${RED}[ERROR]${NC} No Laravel .env file found"
        echo
        echo "Please generate an application key first:"
        echo "  1. cd NutriTrace"
        echo "  2. cp .env.example .env"
        echo "  3. php artisan key:generate"
        echo "  4. Run this script again"
        exit 1
    fi
else
    echo -e "${GREEN}[OK]${NC} .env file exists"
fi

echo
echo "========================================"
echo " AI Service Configuration"
echo "========================================"
echo
echo "How do you want to run the AI service?"
echo
echo "[A] On host machine (GPU support) - RECOMMENDED"
echo "    - AI service runs directly on your PC with GPU"
echo "    - Better performance for ML models"
echo
echo "[B] In Docker container (CPU only)"
echo "    - All services in Docker"
echo "    - Slower AI inference"
echo
read -p "Enter your choice (A/B): " AI_CHOICE

if [[ "$AI_CHOICE" =~ ^[Aa]$ ]]; then
    echo
    echo -e "${YELLOW}[INFO]${NC} Using external AI service on host"
    echo
    echo "IMPORTANT: Make sure to start the AI service separately:"
    echo "  1. Open a new terminal"
    echo "  2. cd NutriTrace/ai"
    echo "  3. source .venv/bin/activate"
    echo "  4. python -m uvicorn server:app --host 127.0.0.1 --port 8001"
    echo
    read -p "Press Enter when the AI service is running..."
elif [[ "$AI_CHOICE" =~ ^[Bb]$ ]]; then
    echo
    echo -e "${YELLOW}[INFO]${NC} Using containerized AI service"
    echo -e "${YELLOW}[WARNING]${NC} This will use CPU only - slower inference"
    echo
    echo "TODO: You need to edit docker-compose.yml manually:"
    echo "  1. Uncomment the 'ai' service section"
    echo "  2. Change AI_SERVICE_URL to http://ai:8001"
    read -p "Press Enter to continue..."
else
    echo -e "${RED}[ERROR]${NC} Invalid choice. Exiting."
    exit 1
fi

echo
echo "========================================"
echo " Starting Docker Containers"
echo "========================================"
echo

echo -e "${YELLOW}[INFO]${NC} Building images (this may take a few minutes on first run)..."
docker compose build

echo
echo -e "${YELLOW}[INFO]${NC} Starting containers..."
docker compose up -d

echo
echo -e "${GREEN}[OK]${NC} Containers started successfully!"
echo
echo "========================================"
echo " Waiting for services to be ready..."
echo "========================================"
sleep 10

echo
echo -e "${YELLOW}[INFO]${NC} Checking service health..."
docker compose ps

echo
echo "========================================"
echo " NutriTrace is ready!"
echo "========================================"
echo
echo -e "- Application: ${GREEN}http://localhost:8000${NC}"
echo "- Database: localhost:3306"
if [[ "$AI_CHOICE" =~ ^[Aa]$ ]]; then
    echo -e "- AI Service: ${GREEN}http://localhost:8001${NC} (external)"
else
    echo "- AI Service: http://localhost:8001 (container)"
fi
echo
echo "Useful commands:"
echo "  View logs:     docker compose logs -f"
echo "  Stop:          docker compose down"
echo "  Restart:       docker compose restart"
echo "  Shell access:  docker compose exec app bash"
echo
read -p "Press Enter to view logs (Ctrl+C to exit)..."

docker compose logs -f
