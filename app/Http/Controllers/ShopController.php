<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Topup;
use App\Models\Variant;
use App\Models\Withdrawal;
use App\Services\Checkout;
use App\Services\Doku;
use App\Services\Store;
use App\Services\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ShopController extends Controller
{
    public function catalog()
    {
        return view('catalog', [
            'products' => Product::with(['category', 'variants' => fn ($q) => $q->where('active', true)->withAvailableStock()])
                ->where('active', true)->get(),
            'categories' => Category::all(),
        ]);
    }

    public function product(string $slug)
    {
        $product = Product::with(['variants' => fn ($q) => $q->where('active', true)->withAvailableStock()])
            ->where('slug', $slug)->where('active', true)->firstOrFail();

        return view('product', compact('product'));
    }

    public function account(Request $request)
    {
        return view('account', [
            'user' => $request->user(),
            'notifications' => DB::table('store_notifications')->where('user_id', $request->user()->id)
                ->latest('id')->limit(30)->get(),
        ]);
    }

    public function apply(Request $request)
    {
        abort_unless($request->user()->role === 'reseller', 403);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => ['required', 'regex:/^0[0-9]{9,14}$/', Rule::unique('users')->ignore($request->user()->id)],
        ]);
        DB::transaction(function () use ($request, $data) {
            $user = Store::lockUser($request->user()->id);
            abort_unless($user->role === 'reseller', 403);
            Store::writable($user);
            Store::check(in_array($user->approval, ['draft', 'rejected'], true), 'Pengajuan sudah diproses atau masih menunggu.');
            $user->forceFill($data + ['approval' => 'pending'])->save();
            Store::audit('reseller_apply', (string) $user->id);
        });

        return back()->with('status', 'Pengajuan terkirim. Owner akan memeriksa nomor WhatsApp di grup BIUBLE.');
    }

    public function addCart(Request $request)
    {
        Store::reseller($request->user());
        $data = $request->validate([
            'variant' => 'required|integer|exists:variants,id', 'quantity' => 'required|integer|min:1|max:20',
            'designer' => 'nullable|boolean', 'email' => 'nullable|email:rfc|max:254',
        ]);
        $variant = Variant::with('product')->withAvailableStock()->findOrFail($data['variant']);
        Store::check($variant->active && $variant->product->active, 'Paket tidak tersedia.');
        Store::check($variant->delivery !== 'invite' || ! empty($data['email']), 'Isi email tujuan undangan.');
        $cart = $request->session()->get('cart', []);
        if ($variant->delivery === 'stock') {
            $requested = (int) $data['quantity'] + collect($cart)->where('variant', $variant->id)->sum('quantity');
            Store::check($requested <= $variant->availableUnits(), 'Stok paket ini tidak cukup. Kurangi jumlah atau pilih paket lain.');
        }
        Store::check(count($cart) < 20, 'Maksimal 20 baris dalam keranjang.');
        $cart[] = $data;
        $request->session()->put('cart', $cart);

        return redirect()->route('cart')->with('status', 'Paket ditambahkan ke keranjang.');
    }

    public function cart(Request $request)
    {
        if (in_array($request->user()->role, ['owner', 'admin'], true)) {
            return redirect()->route('staff');
        }
        Store::reseller($request->user());
        $lines = [];
        $subtotal = 0;
        $stockIssues = [];
        $cart = $request->session()->get('cart', []);
        $requestedUnits = collect($cart)->groupBy('variant')->map(fn ($rows) => $rows->sum('quantity'));
        foreach ($cart as $key => $row) {
            $variant = Variant::with('product')->withAvailableStock()->find($row['variant']);
            if (! $variant) {
                continue;
            }
            if (! $variant->active || ! $variant->product->active
                || ($variant->delivery === 'stock' && $requestedUnits->get($variant->id) > $variant->availableUnits())) {
                $stockIssues[$variant->id] = 'Ketersediaan '.$variant->product->name.' · '.$variant->name.' berubah. Hapus paket ini atau pilih paket lain.';
            }
            $price = $variant->unitPrice(! empty($row['designer']));
            $lines[] = compact('key', 'row', 'variant', 'price');
            $subtotal += $price * $row['quantity'];
        }

        return view('cart', compact('lines', 'subtotal', 'stockIssues') + [
            'channels' => $this->channels(), 'balances' => Store::balances($request->user()->id),
            'fees' => collect($this->channels())->mapWithKeys(fn ($label, $key) => [$key => Store::fee($subtotal, 'checkout', $key)]),
        ]);
    }

    public function removeCart(Request $request, int $line)
    {
        $cart = $request->session()->get('cart', []);
        unset($cart[$line]);
        $request->session()->put('cart', array_values($cart));

        return back();
    }

    public function checkout(Request $request, Checkout $checkout)
    {
        Store::reseller($request->user());
        $data = $request->validate([
            'method' => ['required', Rule::in(array_merge(['wallet'], array_keys($this->channels())))],
            'token' => 'required|uuid', 'expected_total' => 'required|integer|min:1',
        ]);
        $cart = $request->session()->get('cart', []);
        $order = DB::transaction(function () use ($request, $checkout, $cart, $data) {
            $order = $checkout->create($request->user(), $cart, $data['method'], $data['token']);
            Store::check($order->total === (int) $data['expected_total'], 'Harga berubah. Periksa total dan konfirmasi lagi.');
            return $order;
        });
        $request->session()->forget('cart');

        return redirect()->route('orders.show', $order->id);
    }

    public function orders(Request $request)
    {
        if (in_array($request->user()->role, ['owner', 'admin'], true)) {
            return redirect()->route('staff');
        }
        $orders = Order::where('user_id', $request->user()->id)
            ->with(['items' => fn ($items) => $items->where('login_proof_required', true)
                ->select(['id', 'order_id', 'name', 'status', 'delivered_at', 'login_proof_required'])
                ->with('loginProof:id,order_item_id,status')->orderBy('id')])
            ->latest()->paginate(20);

        return view('orders', compact('orders'));
    }

    public function showOrder(Request $request, Order $order)
    {
        abort_unless($order->user_id === $request->user()->id || in_array($request->user()->role, ['owner', 'admin']), 403);
        $order->load('items.loginProof.files')->loadExists(['payment as pending_doku_link' => fn ($query) => $query
            ->where('status', 'pending')->whereNotNull('url')]);

        return view('order', ['order' => $order, 'invoice' => $request->boolean('invoice')]);
    }

    public function cancel(Request $request, OrderItem $item, Checkout $checkout)
    {
        $checkout->cancel($item->id, $request->user());
        return back()->with('status', 'Permintaan pembatalan sudah diproses.');
    }

    public function wallet(Request $request)
    {
        if (in_array($request->user()->role, ['owner', 'admin'], true)) {
            return redirect()->route('staff');
        }
        Store::reseller($request->user());
        $entries = DB::table('wallet_entries')->where('user_id', $request->user()->id)->latest('id')->paginate(30);
        $orderIds = $entries->getCollection()->map(fn ($entry) => \App\Support\StoreLabels::entryOrderId($entry))->filter()->unique();
        $ledgerOrders = Order::with('items:id,order_id,name')->where('user_id', $request->user()->id)->whereIn('id', $orderIds)->get()->keyBy('id');
        return view('wallet', [
            'ledgerOrders' => $ledgerOrders,
            'balances' => Store::balances($request->user()->id), 'channels' => $this->channels(),
            'topups' => Topup::where('user_id', $request->user()->id)
                ->withExists(['payment as pending_doku_link' => fn ($query) => $query->where('status', 'pending')->whereNotNull('url')])
                ->latest()->limit(30)->get(),
            'withdrawals' => Withdrawal::where('user_id', $request->user()->id)->latest()->limit(30)->get(),
            'entries' => $entries,
        ]);
    }

    public function topup(Request $request, Wallet $wallet)
    {
        Store::reseller($request->user());
        $data = $request->validate([
            'amount' => 'required|integer|min:1|max:1000000000',
            'method' => ['required', Rule::in(array_merge(['manual'], array_keys($this->channels())))],
            'proof' => 'required_if:method,manual|nullable|file|mimes:jpg,jpeg,png,pdf|max:4096',
            'token' => 'required|uuid', 'expected_total' => 'required|integer|min:1',
        ]);
        $fee = Store::fee($data['amount'], 'topup', $data['method']);
        Store::check($data['amount'] + $fee === (int) $data['expected_total'], 'Biaya berubah. Periksa rincian dan konfirmasi ulang.');
        $proof = $request->file('proof')?->store('proofs', 'local');
        Store::check($data['method'] !== 'manual' || is_string($proof), 'Bukti transfer belum tersimpan. Coba lagi.');
        try {
            $topup = $wallet->topup($request->user(), $data, $data['token'], $proof);
        } catch (\Throwable $exception) {
            if ($proof) Storage::disk('local')->delete($proof);
            throw $exception;
        }
        if ($proof && $topup->proof !== $proof) Storage::disk('local')->delete($proof);
        return redirect()->route('wallet')->with('status', 'Top-up '.$topup->reference.' dibuat.');
    }

    public function withdraw(Request $request, Wallet $wallet)
    {
        $data = $request->validate([
            'topup' => 'required|integer|min:0|max:1000000000',
            'cancellation' => 'required|integer|min:0|max:1000000000',
            'refund' => 'required|integer|min:0|max:1000000000',
            'destination' => 'required|string|max:200',
            'token' => 'required|uuid', 'expected_fee' => 'required|integer|min:0',
        ]);
        $wallet->withdraw($request->user(), array_map('intval', $request->only(['topup', 'cancellation', 'refund'])),
            $data['destination'], $data['token'], (int) $data['expected_fee']);

        return back()->with('status', 'Pengajuan terkirim. Dana ditahan sampai owner memprosesnya.');
    }

    public function payment(Request $request, string $reference, Doku $doku)
    {
        $payment = Payment::where('reference', $reference)->firstOrFail();
        $target = $payment->kind === 'order' ? Order::findOrFail($payment->target_id) : Topup::findOrFail($payment->target_id);
        abort_unless($target->user_id === $request->user()->id, 403);
        try {
            return redirect()->away($doku->link($payment));
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            report($e);
            return back()->withErrors(['store' => 'Koneksi ke server DOKU gagal atau terlalu lama. Periksa koneksi internet lalu lanjutkan transaksi yang sama; tidak perlu membuat top-up baru.']);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            if (in_array('PAYMENT CHANNEL IS INACTIVE', (array) $e->response->json('message'), true)) {
                return back()->withErrors(['store' => 'Kanal pembayaran ini belum aktif di akun DOKU. Hubungi admin atau pilih kanal lain yang aktif untuk transaksi baru.']);
            }
            report($e);
            $message = in_array($e->response->status(), [401, 403], true)
                ? 'DOKU menolak akses pembayaran. Owner perlu memeriksa Client ID, Secret Key, dan kesesuaian lingkungan sandbox atau production.'
                : 'DOKU belum menerima permintaan pembayaran ini. Coba lanjutkan transaksi yang sama atau hubungi admin jika kendala berulang.';
            return back()->withErrors(['store' => $message]);
        }
    }

    public function paymentReturn(Request $request, string $reference)
    {
        $payment = Payment::where('reference', $reference)->firstOrFail();
        $target = $payment->kind === 'order' ? Order::findOrFail($payment->target_id) : Topup::findOrFail($payment->target_id);
        abort_unless($target->user_id === $request->user()->id, 403);
        return $payment->kind === 'order'
            ? redirect()->route('orders.show', $target->id)
            : redirect()->route('wallet')->with('status', 'Status saldo menunggu konfirmasi resmi DOKU.');
    }

    public function checkPayment(Request $request, string $reference, Doku $doku)
    {
        $payment = Payment::where('reference', $reference)->firstOrFail();
        $target = $payment->kind === 'order' ? Order::findOrFail($payment->target_id) : Topup::findOrFail($payment->target_id);
        abort_unless($target->user_id === $request->user()->id, 403);
        $destination = $payment->kind === 'order' ? redirect()->route('orders.show', $target) : redirect()->route('wallet');

        if ($payment->status === 'paid') {
            return $destination->with('status', 'Pembayaran sudah dikonfirmasi DOKU.');
        }
        if ($payment->status !== 'pending' || ! $payment->url || ! $doku->available()) {
            return $destination->withErrors(['store' => 'Transaksi ini belum bisa diperiksa lewat DOKU.']);
        }
        if (! Cache::add('doku:manual-status:'.$payment->id, true, 60)) {
            return $destination->with('status', 'Status baru saja diperiksa. Tunggu sekitar satu menit sebelum mencoba lagi.');
        }

        try {
            $doku->reconcile($payment);
        } catch (\Illuminate\Http\Client\ConnectionException|\Illuminate\Http\Client\RequestException $exception) {
            return $destination->withErrors(['store' => 'Status pembayaran belum bisa diperiksa di DOKU. Coba lagi setelah satu menit.']);
        }

        return $destination->with('status', $payment->refresh()->status === 'paid'
            ? 'Pembayaran sudah dikonfirmasi DOKU.'
            : 'DOKU belum mengonfirmasi pembayaran. Kalau baru selesai bayar, tunggu satu menit lalu cek kembali.');
    }

    public function proof(Request $request, Topup $topup)
    {
        abort_unless($topup->user_id === $request->user()->id || in_array($request->user()->role, ['owner', 'admin']), 403);
        abort_unless($topup->proof, 404);
        return Storage::disk('local')->download($topup->proof);
    }

    private function channels(): array
    {
        return app(Doku::class)->channels();
    }
}

