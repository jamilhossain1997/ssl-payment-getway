#!/usr/bin/env bash
#
# ngrok's free tier gives you a new random URL every time the tunnel restarts.
# SSLCommerz's IPN/success/fail/cancel URLs are derived from APP_URL in .env
# (see config/sslcommerz.php), so run this after `docker compose up` to sync
# APP_URL to whatever ngrok assigned this session, then re-register the
# webhook URL in your SSLCommerz sandbox merchant panel if it enforces one.
#
# Usage: ./scripts/sync-ngrok-url.sh
#
set -euo pipefail

NGROK_API="http://localhost:4040/api/tunnels"

echo "Waiting for ngrok tunnel..."
for i in {1..15}; do
    if curl -s "$NGROK_API" >/dev/null 2>&1; then
        break
    fi
    sleep 1
done

PUBLIC_URL=$(curl -s "$NGROK_API" | grep -o '"public_url":"https:[^"]*' | head -n1 | cut -d':' -f2- | tr -d '"')

if [ -z "$PUBLIC_URL" ]; then
    echo "Could not find an ngrok tunnel. Is the ngrok container running (docker compose ps)?"
    exit 1
fi

echo "ngrok public URL: $PUBLIC_URL"

if [ -f .env ]; then
    if grep -q "^APP_URL=" .env; then
        sed -i.bak "s|^APP_URL=.*|APP_URL=${PUBLIC_URL}|" .env
    else
        echo "APP_URL=${PUBLIC_URL}" >> .env
    fi
    echo "Updated APP_URL in .env"
else
    echo ".env not found — copy .env.docker.example to .env first."
    exit 1
fi

echo ""
echo "Callback URLs SSLCommerz will use:"
echo "  success_url: ${PUBLIC_URL}/payment/success"
echo "  fail_url:    ${PUBLIC_URL}/payment/fail"
echo "  cancel_url:  ${PUBLIC_URL}/payment/cancel"
echo "  ipn_url:     ${PUBLIC_URL}/payment/ipn"
echo ""
echo "If you're on Laravel's config cache, run: docker compose exec app php artisan config:clear"
