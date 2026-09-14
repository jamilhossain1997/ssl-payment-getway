<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\SslCommerzService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        protected SslCommerzService $sslcz
    ) {}


    public function initiate(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'cus_name' => ['required', 'string', 'max:255'],
            'cus_email' => ['required', 'email'],
            'cus_phone' => ['required', 'string'],
            'order_id' => ['nullable', 'integer'],
        ]);

        $tranId = 'TXN_' . Str::upper(Str::random(10)) . '_' . time();


        $payment = Payment::create([
            'tran_id' => $tranId,
            'order_id' => $data['order_id'] ?? null,
            'amount' => $data['amount'],
            'currency' => config('sslcommerz.currency', 'BDT'),
            'status' => 'pending',
        ]);

        $response = $this->sslcz->initiate([
            'amount' => $payment->amount,
            'tran_id' => $payment->tran_id,
            'cus_name' => $data['cus_name'],
            'cus_email' => $data['cus_email'],
            'cus_phone' => $data['cus_phone'],
        ]);

        $payment->update([
            'raw_init_response' => $response,
        ]);

        if (
            ($response['status'] ?? null) !== 'SUCCESS' ||
            empty($response['GatewayPageURL'])
        ) {
            $payment->update([
                'status' => 'failed',
            ]);

            return response()->json([
                'message' => 'Could not initiate payment.',
                'details' => $response,
            ], 422);
        }

        return redirect()->away(
            $response['GatewayPageURL']
        );
    }


    public function success(Request $request)
    {
        $tranId = $request->input('tran_id');
        $valId = $request->input('val_id');

        if (!$tranId || !$valId) {
            return redirect('/payment/failed');
        }

        $payment = Payment::where('tran_id', $tranId)->first();

        if (!$payment) {
            return redirect('/payment/failed');
        }

        $this->confirmAndMark($payment, $valId);

        $payment = $payment->fresh();

        if ($payment->status === 'success') {
            return view('payment.thank-you', [
                'tranId' => $payment->tran_id
            ]);
        }

        return redirect('/payment/failed');
    }


    public function fail(Request $request)
    {
        $tranId = $request->input('tran_id');

        if ($tranId) {
            $payment = Payment::where('tran_id', $tranId)->first();

            if ($payment && $payment->status !== 'success') {
                $payment->update([
                    'status' => 'failed',
                ]);
            }
        }

        return redirect('/payment/failed');
    }

    public function cancel(Request $request)
    {
        $tranId = $request->input('tran_id');

        if ($tranId) {
            $payment = Payment::where('tran_id', $tranId)->first();

            if ($payment && $payment->status !== 'success') {
                $payment->update([
                    'status' => 'cancelled',
                ]);
            }
        }

        return redirect('/payment/cancelled');
    }


    public function ipn(Request $request)
    {
        $tranId = $request->input('tran_id');
        $valId = $request->input('val_id');

        if (!$tranId) {
            return response('Invalid transaction ID', 400);
        }

        $payment = Payment::where('tran_id', $tranId)->first();

        if (!$payment) {
            return response('Payment not found', 404);
        }

        $payment->update([
            'raw_ipn_payload' => $request->all(),
        ]);

        if ($valId) {
            $this->confirmAndMark($payment, $valId);
        }

        return response('IPN received', 200);
    }


    public function status(string $tranId): JsonResponse
    {
        $payment = Payment::where('tran_id', $tranId)->first();

        if (! $payment) {
            return response()->json([
                'code' => 404,
                'message' => 'Payment not found',
            ], 404);
        }

        $status = match ($payment->status) {
            'success' => [0, 'SUCCESS', 'SUCCESS', 0, 'SUCCESS'],
            'failed' => [6, 'FAILED', 'FAILED', 1, 'PAYMENT_FAILED'],
            'cancelled' => [7, 'CANCELLED', 'CANCELLED', 1, 'PAYMENT_CANCELLED'],
            'partial_success' => [8, 'PARTIAL_SUCCESS', 'PARTIAL', 0, 'SUCCESS'],
            default => [1, 'PENDING', 'PENDING', 0, 'PENDING'],
        };

        [$statusCode, $statusLabel, $outcome, $code, $message] = $status;
        $amount = (float) $payment->amount;
        $receivedAmount = in_array($payment->status, ['success', 'partial_success'], true)
            ? $amount
            : 0.0;

        $payload = [
            'merchant_order_no' => $payment->order_id
                ? 'ORD-' . $payment->order_id
                : $payment->tran_id,
            'platform_order_no' => $payment->tran_id,
            'amount' => $amount,
            'order_status' => $statusCode,
            'order_status_label' => $statusLabel,
            'bank_tran_id' => $payment->bank_tran_id,
            'total_received' => $receivedAmount,
            'payment_outcome' => $outcome,
            'expected_amount' => $amount,
            'received_amount' => $receivedAmount,
            'settled_amount' => $receivedAmount,
            'shortfall_amount' => max(0, $amount - $receivedAmount),
            'code' => $code,
            'message' => $message,
        ];

        $canonicalPayload = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );

        $payload['sign'] = hash_hmac(
            'sha256',
            $canonicalPayload,
            (string) config('payment_api.signing_secret')
        );

        return response()->json($payload);
    }


    protected function confirmAndMark(
        Payment $payment,
        ?string $valId
    ): void {
        if (!$valId) {
            return;
        }

        if ($payment->status === 'success') {
            return;
        }

        $validation = $this->sslcz->validateTransaction($valId);

        $isValid = $this->sslcz->isValidatedSuccess(
            $validation,
            (float) $payment->amount,
            $payment->currency,
            $payment->tran_id
        );

        $payment->update([
            'raw_validation_response' => $validation,
            'val_id' => $valId,
            'bank_tran_id' => $validation['bank_tran_id'] ?? null,
            'card_type' => $validation['card_type'] ?? null,
            'card_issuer' => $validation['card_issuer'] ?? null,
            'status' => $isValid ? 'success' : 'failed',
            'paid_at' => $isValid ? now() : null,
        ]);
    }
}
