<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Topup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Doku
{
    public function available(): bool
    {
        return config('doku.enabled') && config('doku.client_id') && config('doku.secret');
    }

    public function channels(): array
    {
        if (! $this->available()) {
            return [];
        }
        $channels = config('doku.channels');
        if (! config('doku.production')) {
            unset($channels['QRIS']);
        }

        return $channels;
    }

    public function signature(string $requestId, string $timestamp, string $path, string $body): string
    {
        $digest = base64_encode(hash('sha256', $body, true));
        $components = 'Client-Id:'.config('doku.client_id')."\nRequest-Id:".$requestId
            ."\nRequest-Timestamp:".$timestamp."\nRequest-Target:".$path."\nDigest:".$digest;

        return 'HMACSHA256='.base64_encode(hash_hmac('sha256', $components, config('doku.secret'), true));
    }

    public function link(Payment $payment): string
    {
        Store::check($this->available(), 'Pembayaran DOKU sedang belum tersedia.');
        $target = $payment->kind === 'order' ? Order::findOrFail($payment->target_id) : Topup::findOrFail($payment->target_id);
        Store::check(config('doku.production') || $target->method !== 'QRIS',
            'QRIS tidak tersedia di DOKU Sandbox. Gunakan kanal sandbox lain yang aktif untuk transaksi baru.');
        if ($payment->url) {
            return $payment->url;
        }
        Store::check($target->status === 'pending', 'Transaksi ini sudah diproses.');
        $body = json_encode([
            'order' => ['amount' => $payment->amount, 'invoice_number' => $payment->reference,
                'callback_url' => route('payment.return', $payment->reference), 'currency' => 'IDR'],
            'payment' => ['payment_due_date' => 60, 'payment_method_types' => [$target->method]],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $timestamp = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $path = '/checkout/v1/payment';
        $base = config('doku.production') ? 'https://api.doku.com' : 'https://api-sandbox.doku.com';
        $response = Http::connectTimeout(8)->timeout(25)->withHeaders([
            'Client-Id' => config('doku.client_id'), 'Request-Id' => $payment->request_id,
            'Request-Timestamp' => $timestamp,
            'Signature' => $this->signature($payment->request_id, $timestamp, $path, $body),
        ])->withBody($body, 'application/json')->post($base.$path)->throw();
        $url = $response->json('response.payment.url');
        Store::check(is_string($url) && str_starts_with($url, 'https://'), 'DOKU belum mengembalikan tautan pembayaran.');
        $payment->update(['url' => $url]);

        return $url;
    }

    public function notification(Request $request): void
    {
        abort_unless($this->available(), 503);
        abort_unless(hash_equals((string) config('doku.client_id'), (string) $request->header('Client-Id')), 401);
        $id = $request->header('Request-Id');
        $timestamp = $request->header('Request-Timestamp');
        abort_unless(is_string($id) && is_string($timestamp), 401);
        $expected = $this->signature($id, $timestamp, $request->getPathInfo(), $request->getContent());
        abort_unless(hash_equals($expected, (string) $request->header('Signature')), 401);

        $data = $request->json()->all();
        $this->acceptStatus($data);
    }

    public function reconcile(Payment $payment): void
    {
        Store::check($this->available(), 'DOKU belum dikonfigurasi.');
        $id = (string) Str::uuid();
        $timestamp = now()->utc()->format('Y-m-d\TH:i:s\Z');
        $path = '/orders/v1/status/'.rawurlencode($payment->reference);
        $components = 'Client-Id:'.config('doku.client_id')."\nRequest-Id:".$id
            ."\nRequest-Timestamp:".$timestamp."\nRequest-Target:".$path;
        $signature = 'HMACSHA256='.base64_encode(hash_hmac('sha256', $components, config('doku.secret'), true));
        $base = config('doku.production') ? 'https://api.doku.com' : 'https://api-sandbox.doku.com';
        $response = Http::connectTimeout(8)->timeout(20)->withHeaders([
            'Client-Id' => config('doku.client_id'), 'Request-Id' => $id,
            'Request-Timestamp' => $timestamp, 'Signature' => $signature,
        ])->get($base.$path)->throw();
        $data = $response->json();
        Store::check(data_get($data, 'order.invoice_number') === $payment->reference,
            'Referensi hasil pemeriksaan DOKU tidak cocok.');
        $this->acceptStatus($data);
    }

    private function acceptStatus(array $data): void
    {
        $reference = data_get($data, 'order.invoice_number');
        $amount = data_get($data, 'order.amount');
        abort_unless(is_string($reference) && is_numeric($amount), 422);
        $target = Payment::where('reference', $reference)->firstOrFail();
        $model = $target->kind === 'order' ? Order::findOrFail($target->target_id) : Topup::findOrFail($target->target_id);
        DB::transaction(function () use ($target, $model, $data, $amount) {
            Store::lockUser($model->user_id);
            $payment = Payment::whereKey($target->id)->lockForUpdate()->firstOrFail();
            abort_unless((string) $payment->amount === (string) $amount, 422);
            if ($payment->status === 'paid' || data_get($data, 'transaction.status') !== 'SUCCESS') {
                return;
            }
            if ($payment->kind === 'order') {
                $order = Order::whereKey($payment->target_id)->lockForUpdate()->firstOrFail();
                app(Checkout::class)->complete($order);
            } else {
                $topup = Topup::whereKey($payment->target_id)->lockForUpdate()->firstOrFail();
                if ($topup->status !== 'paid') {
                    Store::entry($topup->user_id, 'topup', $topup->amount, 'topup:'.$topup->id, 'Top-up otomatis DOKU');
                    $topup->update(['status' => 'paid']);
                }
            }
            $payment->update(['status' => 'paid']);
        }, 3);
    }
}

