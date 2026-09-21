#!/usr/bin/env bash
set -euo pipefail

BASE_URL=${1:-http://localhost:8080/api}
PASS=${2:-password123}

echo "Testing login at $BASE_URL"

users=(
  "ahmad.wijaya@ddp.test|platform_owner"
  "ratna.sari@ddp.test|admin"
  "dimas.pratama@ddp.test|admin"
  "siti.nurhaliza@ddp.test|outlet"
  "budi.santoso@ddp.test|outlet"
  "hendra.kurniawan@ddp.test|supplier"
  "maya.indah@ddp.test|supplier"
  "riko.firmansyah@ddp.test|sales"
  "anisa.putri@ddp.test|sales"
  "ferry.gunawan@ddp.test|sales"
  "joko.widodo@ddp.test|driver"
  "andi.saputra@ddp.test|driver"
  "rudi.hermawan@ddp.test|driver"
  "dewi.lestari@ddp.test|finance"
  "tono.sugiarto@ddp.test|finance"
)

for entry in "${users[@]}"; do
  email="${entry%%|*}"
  role="${entry##*|}"
  resp=$(curl -s -X POST "$BASE_URL/auth/login" -H "Content-Type: application/json" -d "{\"email\":\"$email\",\"password\":\"$PASS\"}")
  ok=$(echo "$resp" | grep -o '"token"' || true)
  if [ -n "$ok" ]; then
    echo "✅ $role | $email"
  else
    echo "❌ $role | $email"
  fi
done
