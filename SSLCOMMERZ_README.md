# SSLCommerz — Laravel + MySQL Integration

Drop-in files for integrating SSLCommerz's hosted checkout into a Laravel app.

## 0. First time only — this is an add-on, not a full Laravel skeleton

This repo ships only the SSLCommerz-specific files (config, model, service, controller, Docker
setup). It does **not** include Laravel core files (`composer.json`, `artisan`, `public/`,
`bootstrap/`, etc.) — those need to be scaffolded once, without clobbering the custom files
already here:

```bash
docker compose exec app bash -c "composer create-project laravel/laravel:^11.0 /tmp/laravel && cp -rn /tmp/laravel/. /var/www/html/ && rm -rf /tmp/laravel"
```

`cp -rn` (no-clobber) fills in everything Laravel needs (`artisan`, `public/index.php`,
`bootstrap/`, default `config/*.php`, etc.) while **skipping** any file that already exists —
so your `config/sslcommerz.php`, `app/Models/Payment.php`, `app/Services/SslCommerzService.php`,
and `app/Http/Controllers/PaymentController.php` are left untouched.

Then continue with the steps below (`.env`, `key:generate`, `migrate`, adding the routes).

Laravel 11's default skeleton no longer ships `app/Http/Middleware/VerifyCsrfToken.php` — CSRF
exceptions are registered in `bootstrap/app.php` instead (see step 4 below).

## 1. Files are already in place

Since you scaffolded Laravel directly into this folder in step 0, `config/sslcommerz.php`,
`database/migrations/..._create_payments_table.php`, `app/Models/Payment.php`,
`app/Services/SslCommerzService.php`, and `app/Http/Controllers/PaymentController.php` are
already where Laravel expects them — nothing to copy. Just add the contents of
`routes_snippet.php` to the bottom of `routes/web.php` (Laravel's default skeleton creates its
own `routes/web.php` with a `/` route — append below it, don't replace the file).

## 2. Environment variables (.env)

```
SSLCOMMERZ_MODE=sandbox
SSLCOMMERZ_STORE_ID=softw6aa7bc3c2a892
SSLCOMMERZ_STORE_PASSWORD=your_store_password
SSLCOMMERZ_CURRENCY=BDT
APP_URL=https://your-app.test
```

Switch `SSLCOMMERZ_MODE=live` and use your live store_id/password when you go to production.
`APP_URL` must be a publicly reachable HTTPS URL in production — SSLCommerz needs to call
your `ipn_url` from their servers.

## 3. Run the migration

```bash
php artisan migrate
```

## 4. Exempt SSLCommerz callback routes from CSRF

SSLCommerz posts `success_url`, `fail_url`, `cancel_url`, and `ipn_url` as standard HTML form
POSTs, without a Laravel CSRF token — so those 4 routes must be excluded.

**Laravel 11+ (the skeleton `composer create-project` gives you)** — edit `bootstrap/app.php`:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'payment/success',
        'payment/fail',
        'payment/cancel',
        'payment/ipn',
    ]);
})
```

**Laravel 10 and earlier** — edit `app/Http/Middleware/VerifyCsrfToken.php`:

```php
protected $except = [
    'payment/success',
    'payment/fail',
    'payment/cancel',
    'payment/ipn',
];
```

## 5. Kick off a payment

From your checkout page, POST to `/payment/initiate` with `amount`, `cus_name`, `cus_email`,
`cus_phone`, and optionally `order_id`. The controller creates a `pending` Payment row, calls
SSLCommerz's init API, and redirects the browser to the returned `GatewayPageURL`.

## 6. How confirmation works (important)

Never trust `success_url` alone — a customer can hit back/refresh, or the redirect can be
interrupted. This implementation:

1. On `success_url` redirect **and** on the async `ipn_url` webhook, it calls
   `validateTransaction($val_id)` against SSLCommerz's server-to-server validation API.
2. It only marks a payment `success` if the validation response's status, amount, and
   currency all match what you expect (`isValidatedSuccess()`).
3. The IPN webhook is the reliable source of truth — configure `ipn_url` in your SSLCommerz
   merchant panel too, since some gateways only fire IPN if it's registered there as well as
   passed in the init payload.

## 7. Sandbox test cards

SSLCommerz's sandbox docs list dummy card numbers for testing success/fail flows — check your
merchant panel's sandbox documentation section, since these are rotated periodically.

## 8. Running locally with Docker (so SSLCommerz can reach your callbacks)

SSLCommerz's `success_url`, `fail_url`, `cancel_url`, and especially `ipn_url` must be
publicly reachable — their servers call `ipn_url` directly, and `localhost` means nothing to
them. The included Docker stack solves this with an **ngrok** tunnel sitting in front of your
containers.

**Stack:** `nginx` (port 8000) → `app` (PHP-FPM/Laravel) → `mysql`, plus an `ngrok` container
that exposes `nginx` on a public HTTPS URL.

### Setup

```bash
cp .env.docker.example .env
# fill in NGROK_AUTHTOKEN (free at https://dashboard.ngrok.com/get-started/your-authtoken)
# and your SSLCommerz sandbox store_id / store_password

docker compose up -d --build

# first time only: install deps, generate key, migrate
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate

# sync your callback URLs to the live ngrok tunnel
chmod +x scripts/sync-ngrok-url.sh
./scripts/sync-ngrok-url.sh
```

Your app is now reachable locally at `http://localhost:8000` and publicly at whatever ngrok
printed (e.g. `https://abcd1234.ngrok-free.app`) — that public URL is what actually gets
registered as `success_url` / `fail_url` / `cancel_url` / `ipn_url` in the SSLCommerz init
payload, since they're derived from `APP_URL`.

### Watching webhooks arrive

- **ngrok inspector**: open `http://localhost:4040` — every request ngrok forwards to your app
  (including SSLCommerz's IPN POSTs) shows up here with full headers/body, and you can replay
  any request without re-triggering a real payment. This is the fastest way to debug IPN.
- **Laravel logs**: `docker compose logs -f app` or `tail -f storage/logs/laravel.log`.

### Important gotchas with ngrok's free tier

- The URL changes every time the tunnel restarts. Re-run `sync-ngrok-url.sh` each session,
  and if your SSLCommerz sandbox panel lets you pin a fixed IPN URL, update it there too.
- If Laravel has cached config (`php artisan config:cache`), clear it after changing `.env`:
  `docker compose exec app php artisan config:clear`.
- ngrok free tier shows an interstitial "visit site" warning page to browsers (not to
  server-to-server POSTs like SSLCommerz's IPN), so it won't break the webhook — only a human
  clicking the success-page redirect link will see it once.
- For a stable subdomain across restarts, a paid ngrok plan or a static domain
  (`ngrok http --url=your-static-domain.ngrok-free.app nginx:80`) removes the need to re-sync.

## Endpoints implemented

| Route | Method | Purpose |
|---|---|---|
| `/payment/initiate` | POST | Create Payment row, call init API, redirect to gateway |
| `/payment/success` | POST | Browser redirect after payment — triggers validation |
| `/payment/fail` | POST | Browser redirect on failure |
| `/payment/cancel` | POST | Browser redirect on cancel |
| `/payment/ipn` | POST | Async server-to-server notification — triggers validation |
