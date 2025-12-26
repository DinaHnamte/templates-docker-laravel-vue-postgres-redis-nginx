#!/usr/bin/env bash
set -e

IMAGE="ghcr.io/yourorg/yourapp:latest"
CONTAINER="app"

echo "📦 Pulling image"
docker pull $IMAGE

echo "🛑 Stopping old container"
docker rm -f $CONTAINER || true

echo "🚀 Starting new container"
docker run -d \
  --name $CONTAINER \
  --network=host \
  --env-file .env \
  --restart unless-stopped \
  $IMAGE

echo "✅ Deploy complete"
