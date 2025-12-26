#!/usr/bin/env bash
set -e

echo "🔧 Updating system"
sudo apt update -y

echo "🐳 Installing Docker"
if ! command -v docker >/dev/null; then
  sudo apt install -y docker.io
  sudo systemctl enable docker
  sudo systemctl start docker
fi

echo "🐘 Installing PostgreSQL"
if ! command -v psql >/dev/null; then
  sudo apt install -y postgresql postgresql-contrib
  sudo systemctl enable postgresql
  sudo systemctl start postgresql
fi

echo "🧠 Installing Redis"
if ! command -v redis-server >/dev/null; then
  sudo apt install -y redis-server
  sudo systemctl enable redis-server
  sudo systemctl start redis-server
fi

echo "✅ Infrastructure ready"
