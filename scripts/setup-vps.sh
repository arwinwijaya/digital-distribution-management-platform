#!/bin/bash
set -e

# ─────────────────────────────────────────────────────────────────────────────
# Setup VPS untuk pertama kali
# Jalankan SATU KALI di VPS sebagai user ubuntu:
#
#   curl -fsSL https://raw.githubusercontent.com/.../scripts/setup-vps.sh | bash
#   # atau manual:
#   git clone <repo-url> ~/ddp && cd ~/ddp && bash scripts/setup-vps.sh
#
# Akan menginstall: Docker, Docker Compose, Git, dan clone project
# ─────────────────────────────────────────────────────────────────────────────

VPS_IP="${IP:-119.28.113.18}"
DEPLOY_PATH="${DEPLOY_PATH:-$HOME/ddp}"
REPO_URL="${REPO_URL:-https://github.com/arwinwijaya/digital-distribution-management-platform.git}"

echo "=========================================="
echo "  DDP - VPS Setup (One-time)"
echo "  IP: $VPS_IP"
echo "  Path: $DEPLOY_PATH"
echo "=========================================="
echo ""

# ── 1. Update & install dependencies ────────────────────────────────────────
echo "📦 Installing dependencies..."
sudo apt-get update
sudo apt-get install -y git curl openssl ufw

# ── 2. Install Docker ───────────────────────────────────────────────────────
if ! command -v docker &> /dev/null; then
  echo "🐳 Installing Docker..."
  curl -fsSL https://get.docker.com | sh
  sudo usermod -aG docker $USER
  echo "✅ Docker installed. Re-login may be required for group changes."
else
  echo "✅ Docker already installed: $(docker --version)"
fi

# Install docker compose plugin jika belum ada
if ! docker compose version &> /dev/null 2>&1; then
  echo "🐳 Installing Docker Compose..."
  sudo apt-get install -y docker-compose-plugin
fi
echo "✅ Docker Compose: $(docker compose version)"

# ── 3. Setup UFW firewall ───────────────────────────────────────────────────
echo ""
echo "🔥 Configuring firewall..."
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw allow 8080/tcp  # App port
sudo ufw --force enable
echo "✅ Firewall configured"
sudo ufw status

# ── 4. Clone project ────────────────────────────────────────────────────────
if [ ! -d "$DEPLOY_PATH" ]; then
  echo ""
  echo "📥 Cloning project to $DEPLOY_PATH..."
  git clone "$REPO_URL" "$DEPLOY_PATH"
else
  echo "✅ Project already exists at $DEPLOY_PATH"
  cd "$DEPLOY_PATH" && git pull origin main || true
fi

cd "$DEPLOY_PATH"

# ── 5. Setup .env ───────────────────────────────────────────────────────────
if [ ! -f ".env" ]; then
  echo ""
  echo "⚙️  Creating .env..."
  cp .env.example .env

  # Generate secrets
  APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
  JWT_SECRET=$(openssl rand -hex 32)

  # Escape untuk sed
  APP_KEY_ESCAPED=$(echo "$APP_KEY" | sed 's/[&/\]/\\&/g')

  sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY_ESCAPED|" .env
  sed -i "s|^JWT_SECRET=.*|JWT_SECRET=$JWT_SECRET|" .env
  sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 16)|" .env

  # Set APP_URL ke IP VPS agar bisa diakses dari luar
  sed -i "s|^APP_URL=.*|APP_URL=http://$VPS_IP:8080|" .env
  sed -i "s|^CORS_ALLOWED_ORIGINS=.*|CORS_ALLOWED_ORIGINS=http://$VPS_IP:8080|" .env
  sed -i "s|^NEXT_PUBLIC_API_URL=.*|NEXT_PUBLIC_API_URL=http://$VPS_IP:8080/api|" .env

  echo "✅ .env created with generated secrets"
  echo "   ⚠️  Pastikan APP_KEY, JWT_SECRET, DB_PASSWORD sudah terisi!"
else
  echo "✅ .env already exists"
fi

# ── 6. Build & start ────────────────────────────────────────────────────────
echo ""
echo "🐳 Building and starting services..."
# Gunakan sg agar docker group langsung aktif tanpa re-login
sg docker -c "cd $DEPLOY_PATH && docker compose build && docker compose up -d" || {
  echo "⚠️  sg failed, trying direct docker compose..."
  docker compose build
  docker compose up -d
}

echo ""
echo "⏳ Waiting for services..."
sleep 15
docker compose ps
docker compose logs --tail=30 api 2>&1 | head -50 || true

echo ""
echo "=========================================="
echo "  ✅ VPS Setup selesai!"
echo "=========================================="
echo ""
echo "  🌐 App URL: http://$VPS_IP:8080"
echo "  🔗 API Health: http://$VPS_IP:8080/api/health"
echo "  📁 Deploy path: $DEPLOY_PATH"
echo ""
echo "  Next steps:"
echo "    1. Cek health: curl http://$VPS_IP:8080/api/health"
echo "    2. Deploy ulang: cd $DEPLOY_PATH && ./scripts/deploy.sh"
echo "    3. Lihat logs: docker compose logs -f"
echo ""
echo "  Untuk GitHub Actions auto-deploy, set secrets:"
echo "    - VPS_HOST=$VPS_IP"
echo "    - VPS_USER=ubuntu"
echo "    - VPS_DEPLOY_PATH=$DEPLOY_PATH"
echo "    - VPS_SSH_PRIVATE_KEY=(isi private key untuk SSH)"
echo ""
