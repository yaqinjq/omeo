#!/bin/bash
# Deploy script untuk server produksi (aaPanel).
# Jalankan dari terminal aaPanel: bash deploy.sh
#
# Kalau ada error "local changes would be overwritten by merge", itu artinya
# ada file yang di-edit manual langsung di server tanpa lewat git. STOP,
# jangan dipaksa - cek dulu file mana yang dimaksud sebelum lanjut.

set -e

cd "$(dirname "$0")"
PHP="/www/server/php/84/bin/php"

echo "=== Cek status git (harus bersih sebelum pull) ==="
git status --short

echo ""
echo "=== Pull dari GitHub ==="
git pull origin main

echo ""
echo "=== Jalankan migration ==="
"$PHP" artisan migrate --force

echo ""
echo "=== Bersihkan cache ==="
"$PHP" artisan route:clear
"$PHP" artisan view:clear
"$PHP" artisan config:clear

echo ""
echo "=== Selesai! Deploy sukses. ==="
