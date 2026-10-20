#!/bin/bash
# Test script to verify Docker setup for NutriTrace
# Run this to validate all Docker files are correctly configured

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================"
echo " NutriTrace Docker Setup Test"
echo "========================================${NC}"
echo

# Test counter
TESTS_PASSED=0
TESTS_FAILED=0

# Helper function
test_file() {
    local file=$1
    local description=$2
    
    if [ -f "$file" ]; then
        echo -e "${GREEN}✓${NC} $description"
        ((TESTS_PASSED++))
        return 0
    else
        echo -e "${RED}✗${NC} $description - MISSING: $file"
        ((TESTS_FAILED++))
        return 1
    fi
}

test_docker_command() {
    local cmd=$1
    local description=$2
    
    if command -v $cmd &> /dev/null; then
        local version=$($cmd --version | head -n 1)
        echo -e "${GREEN}✓${NC} $description: $version"
        ((TESTS_PASSED++))
        return 0
    else
        echo -e "${RED}✗${NC} $description - NOT INSTALLED"
        ((TESTS_FAILED++))
        return 1
    fi
}

echo -e "${YELLOW}[1/5] Checking Prerequisites${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
test_docker_command "docker" "Docker"
test_docker_command "docker-compose" "Docker Compose" || test_docker_command "docker" "Docker Compose (built-in)"
echo

echo -e "${YELLOW}[2/5] Checking Docker Files${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
test_file "docker-compose.yml" "Docker Compose configuration"
test_file "NutriTrace/Dockerfile" "Laravel Dockerfile"
test_file "NutriTrace/ai/Dockerfile" "AI service Dockerfile"
test_file ".dockerignore" "Root .dockerignore"
test_file "NutriTrace/.dockerignore" "Laravel .dockerignore"
test_file "NutriTrace/ai/.dockerignore" "AI .dockerignore"
echo

echo -e "${YELLOW}[3/5] Checking Documentation${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
test_file "docker/README.md" "Docker deployment guide"
test_file "docker/ARCHITECTURE.md" "Architecture documentation"
test_file "DOCKER_SETUP.md" "Setup summary"
test_file "NEXT_STEPS.md" "Next steps guide"
test_file ".env.docker.example" "Environment template"
echo

echo -e "${YELLOW}[4/5] Checking Scripts${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
test_file "docker-start.sh" "Linux/Mac start script"
test_file "docker-start.bat" "Windows start script"

if [ -f "docker-start.sh" ]; then
    if [ -x "docker-start.sh" ]; then
        echo -e "${GREEN}✓${NC} docker-start.sh is executable"
        ((TESTS_PASSED++))
    else
        echo -e "${YELLOW}⚠${NC} docker-start.sh is not executable (run: chmod +x docker-start.sh)"
    fi
fi
echo

echo -e "${YELLOW}[5/5] Validating Configuration Files${NC}"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

# Check docker-compose.yml syntax
if docker compose config > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} docker-compose.yml syntax is valid"
    ((TESTS_PASSED++))
else
    echo -e "${RED}✗${NC} docker-compose.yml has syntax errors"
    ((TESTS_FAILED++))
fi

# Check if APP_KEY is needed
if [ -f "NutriTrace/.env" ]; then
    if grep -q "APP_KEY=base64:" NutriTrace/.env; then
        echo -e "${GREEN}✓${NC} APP_KEY is configured in NutriTrace/.env"
        ((TESTS_PASSED++))
    else
        echo -e "${YELLOW}⚠${NC} APP_KEY not found in NutriTrace/.env (run: cd NutriTrace && php artisan key:generate)"
    fi
else
    echo -e "${YELLOW}⚠${NC} NutriTrace/.env not found (copy from .env.example)"
fi

# Check Dockerfile syntax (build without pushing)
echo
echo -e "${BLUE}Testing Dockerfile builds (this may take a few minutes)...${NC}"
if docker buildx build --platform linux/amd64 -f NutriTrace/Dockerfile -t nutritrace-app:test NutriTrace > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} Laravel Dockerfile builds successfully"
    ((TESTS_PASSED++))
else
    echo -e "${RED}✗${NC} Laravel Dockerfile has build errors"
    ((TESTS_FAILED++))
fi

if docker buildx build --platform linux/amd64 -f NutriTrace/ai/Dockerfile -t nutritrace-ai:test NutriTrace/ai > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} AI Dockerfile builds successfully"
    ((TESTS_PASSED++))
else
    echo -e "${RED}✗${NC} AI Dockerfile has build errors"
    ((TESTS_FAILED++))
fi

echo

# Summary
echo -e "${BLUE}========================================"
echo " Test Summary"
echo "========================================${NC}"
echo
echo -e "${GREEN}Passed: $TESTS_PASSED${NC}"
echo -e "${RED}Failed: $TESTS_FAILED${NC}"
echo

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed! Your Docker setup is ready.${NC}"
    echo
    echo "Next steps:"
    echo "  1. Run: ./docker-start.sh"
    echo "  2. Access: http://localhost:8000"
    echo "  3. Read: docker/README.md for more info"
    exit 0
else
    echo -e "${RED}✗ Some tests failed. Please fix the issues above.${NC}"
    exit 1
fi
