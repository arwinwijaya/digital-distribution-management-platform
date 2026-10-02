# Deploy ke VPS

Panduan deployment Digital Distribution Management Platform ke VPS Ubuntu.

## Informasi VPS

| Item | Value |
|------|-------|
| IP | `119.28.113.18` |
| User | `ubuntu` |
| App Port | `8080` |
| URL | `http://119.28.113.18:8080` |
| Health Check | `http://119.28.113.18:8080/api/health` |

---

## 1. Setup VPS (Sekali Saja)

SSH ke VPS dan jalankan:

```bash
ssh ubuntu@119.28.113.18
```

### Opsi A: Auto Setup (Recommended)

```bash
git clone https://github.com/arwinwijaya/digital-distribution-management-platform.git ~/ddp
cd ~/ddp
bash scripts/setup-vps.sh
```

Script `setup-vps.sh` akan otomatis:
- Install Docker & Docker Compose
- Buka firewall (22, 80, 443, 8080)
- Generate `.env` dengan `APP_KEY`, `JWT_SECRET`, `DB_PASSWORD`
- Set `APP_URL` dan `CORS` ke IP VPS (`119.28.113.18:8080`)
- Build & start semua containers

### Opsi B: Manual

```bash
# Install Docker
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER
# Re-login agar group docker aktif
exit && ssh ubuntu@119.28.113.18

# Firewall
sudo ufw allow 22/tcp
sudo ufw allow 8080/tcp
sudo ufw --force enable

# Clone & setup .env
git clone https://github.com/arwinwijaya/digital-distribution-management-platform.git ~/ddp
cd ~/ddp
cp .env.vps.example .env

# Generate secrets
APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
JWT_SECRET=$(openssl rand -hex 32)
DB_PASS=$(openssl rand -base64 16 | tr -dc 'a-zA-Z0-9' | head -c 16)
sed -i "s|^APP_KEY=.*|APP_KEY=$APP_KEY|" .env
sed -i "s|^JWT_SECRET=.*|JWT_SECRET=$JWT_SECRET|" .env
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=$DB_PASS|" .env

# Deploy
bash scripts/deploy.sh
```

### Verifikasi

```bash
curl http://119.28.113.18:8080/api/health
docker compose ps
docker compose logs -f
```

---

## 2. Deploy Ulang (Setiap Update Code)

Di VPS:

```bash
cd ~/ddp
bash scripts/deploy.sh
# atau
./scripts/deploy.sh
```

Script akan: `git pull` → validasi `.env` → `docker compose build` → `docker compose up -d` → health check.

---

## 3. Auto Deploy via GitHub Actions

Setiap `git push` ke branch `main` akan otomatis deploy ke VPS.

### Setup GitHub Secrets

Buka GitHub → Repository → **Settings → Secrets and variables → Actions** → **New repository secret**:

| Secret Name | Value | Contoh |
|-------------|-------|--------|
| `VPS_HOST` | IP VPS | `119.28.113.18` |
| `VPS_USER` | SSH user | `ubuntu` |
| `VPS_DEPLOY_PATH` | Path project di VPS | `/home/ubuntu/ddp` |
| `VPS_SSH_PRIVATE_KEY` | Private key SSH | isi `cat ~/.ssh/id_rsa` |

#### Generate SSH Key untuk GitHub Actions

Di laptop/server lokal:

```bash
ssh-keygen -t ed25519 -C "github-actions-deploy" -f ~/.ssh/ddp_deploy -N ""
cat ~/.ssh/ddp_deploy        # → copy ke VPS_SSH_PRIVATE_KEY
cat ~/.ssh/ddp_deploy.pub    # → tambahkan ke VPS
```

Di VPS:

```bash
mkdir -p ~/.ssh && chmod 700 ~/.ssh
echo "isi-public-key" >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

Atau jika sudah ada key, pakai yang sudah ada:

```bash
cat ~/.ssh/id_rsa  # private key → GitHub Secret
```

### Workflow

File: `.github/workflows/deploy-vps.yml`

```yaml
on:
  push:
    branches: [main]        # auto deploy saat push ke main
  workflow_dispatch:        # manual trigger dari GitHub UI
```

Manual trigger: GitHub → **Actions → Deploy to VPS → Run workflow**

### Verifikasi Workflow

Setelah push ke `main`, cek di **Actions** tab. Step terakhir akan `curl http://VPS_HOST:8080/api/health` untuk memastikan API hidup.

---

## 4. Konfigurasi .env di VPS

File `.env` di VPS **harus** pakai IP public (bukan localhost) agar bisa diakses dari luar:

```env
APP_PORT=8080
APP_URL=http://119.28.113.18:8080
CORS_ALLOWED_ORIGINS=http://119.28.113.18:8080
NEXT_PUBLIC_API_URL=http://119.28.113.18:8080/api

APP_KEY=base64:...
JWT_SECRET=... (64 hex chars)
DB_PASSWORD=...
```

> ⚠️ **JANGAN** commit file `.env` asli ke Git! File `.env` di VPS hanya ada di server.

### Jika pakai Domain + SSL (opsional)

Ganti `APP_URL` ke domain dan setup reverse proxy (Nginx/Caddy) dengan Let's Encrypt:

```env
APP_URL=https://ddp.example.com
CORS_ALLOWED_ORIGINS=https://ddp.example.com
NEXT_PUBLIC_API_URL=https://ddp.example.com/api
```

---

## 5. Perintah Berguna di VPS

```bash
cd ~/ddp

# Status
docker compose ps

# Logs
docker compose logs -f              # semua service
docker compose logs -f api          # hanya API
docker compose logs -f web          # hanya Next.js
docker compose logs -f nginx        # hanya nginx

# Restart
docker compose restart
docker compose restart api

# Stop / Start
docker compose down
docker compose up -d

# Rebuild total
docker compose down
docker compose build --no-cache
docker compose up -d

# Health check
curl http://localhost:8080/api/health
curl http://119.28.113.18:8080/api/health
```

---

## 6. Troubleshooting

| Masalah | Solusi |
|---------|--------|
| `APP_KEY is empty` | `php -r "echo 'base64:'.base64_encode(random_bytes(32)).PHP_EOL;"` lalu update `.env` |
| `JWT error: Key shorter than 256 bits` | `JWT_SECRET` harus 64 hex chars: `openssl rand -hex 32` |
| Port 8080 tidak bisa diakses | `sudo ufw allow 8080/tcp && sudo ufw status` |
| `docker: permission denied` | `sudo usermod -aG docker $USER` lalu re-login |
| API 502 Bad Gateway | `docker compose logs api` — cek apakah migrasi gagal |
| GitHub Actions SSH failed | Pastikan `VPS_SSH_PRIVATE_KEY` benar dan public key ada di `~/.ssh/authorized_keys` di VPS |

---

## 7. Keamanan

- [ ] Ganti password default VPS (`matrix-73@-panda`) dengan yang lebih kuat atau pakai SSH key saja
- [ ] Disable password auth setelah SSH key setup: `PasswordAuthentication no` di `/etc/ssh/sshd_config`
- [ ] Jangan simpan `IP/Username/Password` di `.env` yang ter-commit — pakai GitHub Secrets
- [ ] Setup SSL (Let's Encrypt) jika pakai domain
- [ ] Backup `postgres_data` volume secara berkala
