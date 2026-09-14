<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SslCommerzService
{
    protected string $storeId;
    protected string $storePassword;
    protected string $mode;
    protected array $urls;

    public function __construct()
    {
        $this->storeId = config('sslcommerz.store_id');
        $this->storePassword = config('sslcommerz.store_password');
        $this->mode = config('sslcommerz.mode');
        $this->urls = config('sslcommerz.urls')[$this->mode];
    }

    /**
     * Initiate a transaction. Returns the decoded JSON response from SSLCommerz,
     * which includes GatewayPageURL to redirect the customer to.
     */
    public function initiate(array $order): array
    {
        $payload = [
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'total_amount' => number_format((float) $order['amount'], 2, '.', ''),
            'currency' => $order['currency'] ?? config('sslcommerz.currency'),
            'tran_id' => $order['tran_id'],

            'success_url' => config('sslcommerz.success_url'),
            'fail_url' => config('sslcommerz.fail_url'),
            'cancel_url' => config('sslcommerz.cancel_url'),
            'ipn_url' => config('sslcommerz.ipn_url'),

            // Customer info (required by SSLCommerz)
            'cus_name' => $order['cus_name'],
            'cus_email' => $order['cus_email'],
            'cus_add1' => $order['cus_add1'] ?? 'N/A',
            'cus_city' => $order['cus_city'] ?? 'N/A',
            'cus_postcode' => $order['cus_postcode'] ?? '0000',
            'cus_country' => $order['cus_country'] ?? 'Bangladesh',
            'cus_phone' => $order['cus_phone'],

            // Shipment info (required even for non-physical goods; reuse customer info if needed)
            'shipping_method' => $order['shipping_method'] ?? 'NO',
            'num_of_item' => $order['num_of_item'] ?? 1,
            'product_name' => $order['product_name'] ?? 'Order Payment',
            'product_category' => $order['product_category'] ?? 'General',
            'product_profile' => $order['product_profile'] ?? 'general',
        ];

        $response = Http::asForm()->post($this->urls['init'], $payload);

        if ($response->failed()) {
            Log::error('SSLCommerz init failed', ['response' => $response->body()]);
            throw new \RuntimeException('Failed to initiate SSLCommerz payment.');
        }

        return $response->json();
    }

    /**
     * Validate a transaction after redirect / IPN using val_id.
     * ALWAYS call this server-side before trusting a payment as successful —
     * never trust success_url or IPN payload data alone.
     */
    public function validateTransaction(string $valId): array
    {
        $response = Http::get($this->urls['validate'], [
            'val_id' => $valId,
            'store_id' => $this->storeId,
            'store_passwd' => $this->storePassword,
            'format' => 'json',
        ]);

        if ($response->failed()) {
            Log::error('SSLCommerz validation request failed', ['val_id' => $valId]);
            throw new \RuntimeException('Failed to validate SSLCommerz transaction.');
        }

        return $response->json();
    }

    /**
     * Confirms the validation response is genuinely successful and the amount matches.
     */
    public function isValidatedSuccess(
        array $validation,
        float $expectedAmount,
        string $expectedCurrency,
        string $expectedTranId
    ): bool
    {
        $statusOk = in_array($validation['status'] ?? null, ['VALID', 'VALIDATED'], true);
        $amountOk = abs((float) ($validation['amount'] ?? 0) - $expectedAmount) < 0.01;
        $currencyOk = ($validation['currency'] ?? '') === $expectedCurrency;
        $transactionOk = hash_equals($expectedTranId, (string) ($validation['tran_id'] ?? ''));

        return $statusOk && $amountOk && $currencyOk && $transactionOk;
    }
}
