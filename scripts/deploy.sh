#!/bin/bash
set -e

# ─────────────────────────────────────────────────────────────────────────────
# Deploy script for Digital Distribution Management Platform
# Jalankan di VPS: ./deploy.sh  atau  bash scripts/deploy.sh
#
# Langkah:
#   1. Pull kode terbaru dari git
#   2. Build ulang Docker images
#   3. Restart services dengan docker compose
# ─────────────────────────────────────────────────────────────────────────────

PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_DIR"

echo "=========================================="
echo "  DDP - Deploy to VPS"
echo "  $(date '+%Y-%m-%d %H:%M:%S')"
echo "=========================================="
echo ""

# ── 1. Git pull ─────────────────────────────────────────────────────────────
echo "📥 Pulling latest code..."
if [ -d ".git" ]; then
  git pull origin main
else
  echo "⚠️  Not a git repo, skipping git pull"
fi

# ── Load .env ───────────────────────────────────────────────────────────────
set -a
[ -f .env ] && source .env
set +a

# ── 2. Validasi .env ────────────────────────────────────────────────────────
if [ ! -f ".env" ]; then
  echo "❌ .env tidak ditemukan! Copy dari .env.example dan isi secrets:"
  echo "   cp .env.example .env"
  echo "   # lalu edit APP_KEY, JWT_SECRET, DB_PASSWORD"
  exit 1
fi

# Cek APP_KEY & JWT_SECRET terisi
if grep -q "^APP_KEY=$" .env || grep -q "^APP_KEY= *$" .env; then
  echo "⚠️  APP_KEY kosong, generate..."
  APP_KEY=$(openssl rand -base64 32 | tr -d '\n' | awk '{print "base64:"$1}')
  # fallback: php artisan key:generate jika ada
  sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" .env
  echo "✅ APP_KEY generated"
fi

if grep -q "^JWT_SECRET=$" .env || grep -q "^JWT_SECRET= *$" .env; then
  echo "⚠️  JWT_SECRET kosong, generate..."
  JWT_SECRET=$(openssl rand -hex 32)
  sed -i "s|^JWT_SECRET=.*|JWT_SECRET=$JWT_SECRET|" .env
  echo "✅ JWT_SECRET generated"
fi

# ── 3. Docker build & up ────────────────────────────────────────────────────
echo ""
echo "🐳 Building Docker images..."
docker compose build

echo ""
echo "🚀 Starting services..."
docker compose up -d

echo ""
echo "⏳ Waiting for services to be healthy..."
for i in {1..60}; do
  if docker compose ps | grep -q "healthy\|running"; then
    # Cek API health endpoint
    if curl -sf http://localhost:${APP_PORT:-8080}/api/health > /dev/null 2>&1; then
      echo "✅ API is healthy!"
      break
    fi
  fi
  echo "   Waiting... ($i/60)"
  sleep 5
  if [ "$i" -eq 60 ]; then
    echo "⚠️  Health check timeout, cek logs:"
    docker compose ps
    docker compose logs --tail=50 api nginx
  fi
done

echo ""
echo "📊 Service status:"
docker compose ps

echo ""
echo "=========================================="
echo "  ✅ Deploy selesai!"
echo "  Akses: http://$(grep APP_URL .env | cut -d= -f2 | tr -d '\"')"
echo "  Health: http://$(grep APP_URL .env | cut -d= -f2 | tr -d '\"')/api/health"
echo "=========================================="
echo ""
echo "📋 Logs: docker compose logs -f"
echo "🔄 Restart: docker compose restart"
echo "🛑 Stop: docker compose down"
