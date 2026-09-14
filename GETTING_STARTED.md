# Getting Started — SSLCommerz + Laravel 11 + Docker

This is a **complete Laravel 11 project** (official skeleton) with the SSLCommerz payment
integration already wired in: config, model, service, controller, routes, and the
CSRF exemption in `bootstrap/app.php` are all done. You do **not** need to run
`composer create-project` — this already is one.

## 1. Extract and set up environment

```bash
cp .env.example .env
```

Edit `.env` and fill in:
- `NGROK_AUTHTOKEN` — free at https://dashboard.ngrok.com/get-started/your-authtoken
- `SSLCOMMERZ_STORE_ID` / `SSLCOMMERZ_STORE_PASSWORD` — your sandbox credentials

The `DB_*` values already match `docker-compose.yml` — no changes needed there.

## 2. Start the stack

```bash
docker compose up -d --build
```

If you're on **Git Bash / MINGW on Windows**, prefix every `docker compose exec` command
below with `MSYS_NO_PATHCONV=1` — otherwise Git Bash rewrites `/var/www/html`-style paths
into broken Windows paths. Example:

```bash
MSYS_NO_PATHCONV=1 docker compose exec app composer install
```

(PowerShell or CMD don't have this problem, if you'd rather switch.)

## 3. Install dependencies and set up the app

```bash
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

## 4. Expose your local app publicly (for SSLCommerz callbacks/IPN)

```bash
chmod +x scripts/sync-ngrok-url.sh
./scripts/sync-ngrok-url.sh
```

This prints a public HTTPS URL and writes it to `.env` as `APP_URL` — that's what
`success_url` / `fail_url` / `cancel_url` / `ipn_url` are built from (see
`config/sslcommerz.php`). Watch incoming webhooks live at `http://localhost:4040`.

## 5. Test it

Your app is live at `http://localhost:8000` locally, and at the ngrok URL publicly.
POST to `/payment/initiate` (e.g. from a simple HTML form) with `amount`, `cus_name`,
`cus_email`, `cus_phone` to kick off a sandbox payment.

Full details on the payment flow, validation logic, and troubleshooting are in
`SSLCOMMERZ_README.md`. Laravel's own docs/starter info are in `LARAVEL_README.md`.
