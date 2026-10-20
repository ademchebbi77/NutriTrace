# Dockerfile for NutriTrace AI Service
# NOTE: This image is for CPU-only deployment or when GPU is available via NVIDIA Docker runtime
# For production with GPU, models need to be mounted or downloaded at runtime (6GB+)

FROM python:3.11-slim

# Install system dependencies
RUN apt-get update && apt-get install -y \
    curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# Copy requirements and install Python dependencies
# Using production requirements (includes PyTorch CPU version by default)
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy application code
COPY nutritrace_ai ./nutritrace_ai
COPY server.py .

# Create directories for models (to be mounted as volumes)
RUN mkdir -p models/checkpoints

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=120s --retries=3 \
    CMD curl -f http://localhost:8001/health || exit 1

# Expose AI service port
EXPOSE 8001

# Run the FastAPI server
# Use 0.0.0.0 to allow connections from other containers
CMD ["python", "-m", "uvicorn", "server:app", "--host", "0.0.0.0", "--port", "8001"]
