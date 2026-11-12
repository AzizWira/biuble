<?php

namespace App\Http\Controllers;

use App\Models\AccountRemovalRequest;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockAccount;
use App\Models\StockSlot;
use App\Models\Topup;
use App\Models\User;
use App\Models\Variant;
use App\Models\Withdrawal;
use App\Services\Checkout;
use App\Services\Store;
use App\Services\Wallet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function dashboard(Request $request)
    {
        Store::staff();
        $sections = ['pesanan'=>'Pesanan', 'topup'=>'Top-up', 'stok'=>'Stok', 'katalog'=>'Produk & varian', 'pengajuan'=>'Reseller'];
        if ($request->user()->role === 'owner') {
            $sections += ['penghapusan'=>'Pengajuan hapus akun', 'penarikan'=>'Penarikan', 'kategori'=>'Kategori',
                'admin'=>'Tim admin', 'pengaturan'=>'Pengaturan', 'aktivitas'=>'Aktivitas'];
        }
        $activeTab = $request->query('bagian', 'pesanan');
        abort_unless(is_string($activeTab) && array_key_exists($activeTab, $sections), 404);
        if ($activeTab === 'stok') {
            return view('staff', ['sections' => $sections, 'activeTab' => $activeTab] + $this->stockOverview($request));
        }

        return view('staff', [
            'sections' => $sections, 'activeTab' => $activeTab,
            'applicants' => User::where('role', 'reseller')->where('approval', 'pending')->where('account_status', '!=', 'deleted')->get(),
            'orders' => Order::with(['items', 'user'])->latest()->limit(50)->get(),
            'topups' => Topup::with('user')->where('method', 'manual')->where('status', 'pending')->get(),
            'withdrawals' => Withdrawal::with('user')->where('status', 'pending')->get(),
            'products' => Product::with('variants')->get(), 'categories' => Category::all(),
            'audits' => DB::table('store_audits')->leftJoin('users as actors', 'actors.id', '=', 'store_audits.actor_id')->select('store_audits.*', 'actors.name as actor_name')->latest('store_audits.id')->limit(40)->get(),
            'admins' => User::where('role', 'admin')->where('account_status', '!=', 'deleted')->orderBy('name')->get(),
            'resellers' => $activeTab === 'pengajuan' ? User::where('role','reseller')->where('account_status', '!=', 'deleted')->orderBy('name')->paginate(20, ['*'], 'reseller_page')->withQueryString() : collect(),
            'removals' => $activeTab === 'penghapusan' ? AccountRemovalRequest::with(['user', 'requester'])->where('status', 'pending')->oldest('id')->paginate(20)->withQueryString() : null,
        ]);
    }

    private function stockOverview(Request $request): array
    {
        $stockProducts = Product::whereHas('variants', fn ($query) => $query->where('delivery', 'stock'))
            ->orderBy('name')->get();
        $product = $stockProducts->firstWhere('slug', $request->string('app')->toString());
        $stockVariants = $product?->variants()->where('delivery', 'stock')
            ->withAvailableStock()->orderBy('type')->orderBy('name')->get() ?? collect();
        $variant = $stockVariants->first(fn (Variant $option) => (string) $option->id === $request->string('varian')->toString());
        $now = now();
        $soon = $now->copy()->addDays(3);
        $accounts = $selectedAccount = $counts = $incoming = $outgoing = null;

        if ($variant) {
            $accountQuery = StockAccount::where('variant_id', $variant->id);
            if ($request->has('akun')) {
                $selectedAccount = $accountQuery->with(['slots' => fn ($slots) => $slots
                    ->with('latestOrderItem.order:id,reference,expires_at')
                    ->withExists(['orderItems as was_delivered' => fn ($items) => $items->whereNotNull('delivered_at')])
                    ->orderBy('id')])->findOrFail($request->integer('akun'));
            } else {
                $accounts = $accountQuery->withCount(['slots', 'slots as ready_slots_count' => fn ($slots) => $slots->where('state', 'available')])
                    ->latest('id')->paginate(12)->withQueryString()->fragment('stok-akun');
            }
            $counts = $variant->stockSlots()->toBase()
                ->select('state')->selectRaw('count(*) as total')->groupBy('state')->pluck('total', 'state');
            $usedAwaitingInspection = $variant->stockSlots()->where('state', 'used')
                ->where(fn ($query) => $query->doesntHave('latestOrderItem')
                    ->orWhereHas('latestOrderItem', fn ($item) => $item
                        ->whereIn('status', StockSlot::RESTOCKABLE_ORDER_STATUSES)
                        ->orWhere('ends_at', '<=', $now)))->count();
            $counts['inspection'] = ($counts['inspection'] ?? 0) + $usedAwaitingInspection;
            $counts['used'] = max(0, ($counts['used'] ?? 0) - $usedAwaitingInspection);
            $counts['ending'] = $variant->stockSlots()->where('state', 'used')
                ->whereHas('latestOrderItem', fn ($query) => $query->where('status', 'delivered')
                    ->where('ends_at', '>', $now)->where('ends_at', '<=', $soon))->count();
        } elseif ($product) {
            $incoming = StockAccount::whereHas('variant', fn ($query) => $query->where('product_id', $product->id))
                ->with('variant')->withCount('slots')->latest('id')
                ->paginate(10, ['*'], 'masuk')->withQueryString()->fragment('stok-masuk');
            $outgoing = OrderItem::whereNotNull('stock_slot_id')->whereNotNull('delivered_at')
                ->whereHas('stockSlot.account.variant', fn ($query) => $query->where('product_id', $product->id))
                ->with(['order:id,reference', 'stockSlot.account:id,label'])->latest('delivered_at')->latest('id')
                ->paginate(10, ['*'], 'keluar')->withQueryString()->fragment('stok-keluar');
        }

        return compact('stockProducts', 'product', 'stockVariants', 'variant',
            'now', 'soon', 'accounts', 'selectedAccount', 'counts', 'incoming', 'outgoing');
    }

    public function accountStatus(Request $request, User $user)
    {
        Store::owner();
        $data=$request->validate([
            'account_status'=>'required|in:active,suspended,inactive',
            'account_status_reason'=>'nullable|string|max:255',
        ]);
        DB::transaction(function () use ($user,$data) {
            $target=Store::lockUser($user->id);
            abort_unless(in_array($target->role,['admin','reseller'],true) && ! $target->isArchived(),403);
            $target->forceFill([
                'account_status'=>$data['account_status'],
                'account_status_reason'=>$data['account_status'] === 'active' ? null : ($data['account_status_reason'] ?? null),
            ])->save();
            Store::audit('account_status_changed',$target->name.' · '.\App\Support\StoreLabels::accountStatus($target->account_status));
        });
        return back()->with('status','Status akun diperbarui. Akun suspensi/nonaktif tetap dapat melihat data, tetapi tidak dapat melakukan perubahan.');
    }

    public function applicant(Request $request, User $user)
    {
        Store::owner();
        $decision = $request->validate(['decision' => 'required|in:approved,rejected'])['decision'];
        DB::transaction(function () use ($user, $decision) {
            $user = Store::lockUser($user->id);
            Store::check($user->role === 'reseller' && $user->approval === 'pending' && ! $user->isArchived(), 'Pengajuan sudah diproses.');
            $user->forceFill(['approval' => $decision])->save();
            Store::audit('reseller_'.$decision, (string) $user->id);
        });
        return back()->with('status', 'Pengajuan reseller diproses.');
    }

    public function topup(Request $request, Topup $topup, Wallet $wallet)
    {
        Store::staff();
        $data = $request->validate(['decision' => 'required|in:approve,reject']);
        $wallet->approveTopup($topup->id, $data['decision']);
        return back()->with('status', 'Top-up diproses.');
    }

    public function withdrawal(Request $request, Withdrawal $withdrawal, Wallet $wallet)
    {
        Store::owner();
        $data = $request->validate([
            'decision' => 'required|in:approve,reject',
            'reference' => 'required_if:decision,approve|nullable|string|max:120',
        ]);
        $wallet->decideWithdrawal($withdrawal->id, $data['decision'], $data['reference'] ?? null);
        return back()->with('status', 'Pengajuan penarikan diproses.');
    }

    public function deliver(Request $request, OrderItem $item, Checkout $checkout)
    {
        Store::staff();
        $data = $request->validate(['access' => 'required|string|max:8000']);
        DB::transaction(function () use ($item, $data, $checkout, $request) {
            Store::lockUser($item->order->user_id);
            $order = Order::whereKey($item->order_id)->lockForUpdate()->firstOrFail();
            $item = OrderItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            Store::check($order->paid_at && in_array($item->status, ['waiting', 'review']), 'Item sudah diproses atau belum dibayar.');
            if ($item->delivery !== 'invite') {
                Store::owner();
            }
            $checkout->deliver($item, $data['access']);
            Store::audit('deliver', (string) $item->id);
        }, 3);
        return back()->with('status', 'Akses diberikan dan reseller mendapat notifikasi.');
    }

    public function gatewayRefund(Request $request, OrderItem $item)
    {
        Store::owner();
        $data = $request->validate(['reference' => 'required|string|max:120']);
        DB::transaction(function () use ($item, $data) {
            Store::lockUser($item->order->user_id);
            $order = Order::whereKey($item->order_id)->lockForUpdate()->firstOrFail();
            $item = OrderItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            Store::check($item->status === 'refund_requested' && $order->method !== 'wallet', 'Item tidak sedang menunggu refund DOKU.');
            $item->update(['status' => 'refunded_gateway']);
            Store::audit('gateway_refund_completed', $item->id.' / '.$data['reference']);
            Store::notify($order->user_id, 'Pengembalian melalui kanal asal sudah diproses.', $order->id);
        });
        return back()->with('status', 'Bukti referensi refund dicatat.');
    }

    public function refund(Request $request, OrderItem $item)
    {
        Store::owner();
        $data = $request->validate(['reason' => 'required|string|max:200']);
        DB::transaction(function () use ($item, $data) {
            Store::lockUser($item->order->user_id);
            $order = Order::whereKey($item->order_id)->lockForUpdate()->firstOrFail();
            $item = OrderItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            Store::check($order->method === 'wallet' && $order->paid_at
                && in_array($item->status, ['waiting', 'review', 'delivered']), 'Item tidak bisa direfund ke saldo.');
            Store::entry($order->user_id, 'refund', $item->amount, 'refund:'.$item->id, $data['reason']);
            $item->update(['status' => 'refunded']);
            if ($item->stock_slot_id) {
                StockSlot::whereKey($item->stock_slot_id)->where('reservation_token', $order->token)->update(['state' => 'inspection']);
            }
            Store::notify($order->user_id, 'Refund disetujui untuk '.$item->name, $order->id);
            Store::audit('refund', $item->id.' / '.$data['reason']);
        }, 3);
        return back()->with('status', 'Refund masuk ke saldo reseller.');
    }

    public function category(Request $request, ?Category $category = null)
    {
        Store::owner();
        $data = $request->validate([
            'name' => 'required|string|max:80', 'color' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'image' => 'nullable|image|mimes:jpeg,png,webp|max:3072',
        ]);
        $category ??= new Category();
        $category->fill($request->only(['name', 'color']));
        if ($request->hasFile('image')) {
            $category->image = $request->file('image')->store('catalog', 'public');
        }
        $category->save();
        Store::audit('category_create', (string) $category->id);
        return back()->with('status', 'Kategori disimpan.');
    }

    public function admin(Request $request)
    {
        Store::owner();
        if (is_string($email = $request->input('email'))) {
            $request->merge(['email' => Str::lower(trim($email))]);
        }
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email:rfc|max:254|unique:users,email',
        ], ['email.unique' => 'Email sudah terdaftar. Untuk reseller, gunakan tombol Jadikan admin di menu Reseller.']);
        try {
            $user = User::forceCreate($data + [
                'password' => \Illuminate\Support\Facades\Hash::make(Str::random(64)),
                'role' => 'admin', 'approval' => 'approved',
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => 'Email sudah terdaftar. Ubah role melalui daftar akun.']);
        }
        Store::audit('admin_create', (string) $user->id);
        return back()->with('status', 'Admin bisa masuk lewat OTP email atau Google.');
    }

    public function product(Request $request, ?Product $product = null)
    {
        Store::owner();
        $data = $request->validate([
            'name' => 'required|string|max:100', 'category_id' => 'required|exists:categories,id',
            'description' => 'required|string|max:3000', 'color' => 'required|regex:/^#[0-9a-fA-F]{6}$/',
            'image' => 'nullable|image|mimes:jpeg,png,webp|max:3072', 'active' => 'nullable|boolean',
        ]);
        $product ??= new Product();
        $product->fill($request->only(['name', 'category_id', 'description', 'color']));
        $product->active = $request->boolean('active');
        if (! $product->exists) {
            $product->slug = Str::slug($data['name']).'-'.Str::lower(Str::random(5));
        }
        if ($request->hasFile('image')) {
            $product->image = $request->file('image')->store('catalog', 'public');
        }
        $product->save();
        Store::audit('product_save', (string) $product->id);
        return back()->with('status', 'Produk disimpan.');
    }

    public function variant(Request $request, ?Variant $variant = null)
    {
        Store::staff();
        if (! $variant) {
            Store::owner();
        }
        $data = $request->validate([
            'product_id' => 'required|exists:products,id', 'name' => 'required|string|max:180',
            'color' => 'nullable|regex:/^#[0-9a-fA-F]{6}$/',
            'type' => 'required|in:sharing,private', 'delivery' => 'required|in:stock,manual,invite',
            'price' => 'required|integer|min:1|max:1000000000',
            'duration_days' => 'nullable|integer|min:1|max:3660',
            'warranty_days' => 'nullable|integer|min:1|max:3660',
            'login_proof_required' => 'nullable|boolean',
            'designer' => 'nullable|boolean', 'active' => 'nullable|boolean',
            'discount_percent' => 'required|integer|min:0|max:99',
        ]);
        if ($variant && $request->user()->role === 'admin') {
            $variant->update(['price' => $data['price']]);
        } else {
            $product = Product::findOrFail($data['product_id']);
            $data['designer'] = $request->boolean('designer') && $product->slug === 'canva'
                && Str::startsWith($data['name'], 'Member');
            $data['active'] = $request->boolean('active');
            if ($request->has('login_proof_required')) {
                $data['login_proof_required'] = $request->boolean('login_proof_required');
            }
            ($variant ?? new Variant())->fill($data)->save();
        }
        Store::audit('variant_save', $variant?->id ? (string) $variant->id : 'new');
        return back()->with('status', 'Varian disimpan.');
    }

    public function stock(Request $request)
    {
        Store::staff();
        $back = back()->withFragment('stok-tambah');
        $validator = validator($request->all(), [
            'variant_id' => 'required|exists:variants,id', 'credentials' => 'required|string|max:8000',
            'label' => 'nullable|string|max:100',
            'slots' => 'required|integer|min:1|max:100',
        ]);
        if ($validator->fails() && ! $request->expectsJson()) {
            return $back->withErrors($validator)
                ->withInput($request->only('variant_id', 'label', 'slots'))->with('stock_feedback_target', 'stok-tambah');
        }
        $data = $validator->validate();
        DB::transaction(function () use ($data) {
            $variant = Variant::whereKey($data['variant_id'])->lockForUpdate()->firstOrFail();
            Store::check($variant->delivery === 'stock', 'Paket ini memakai penyerahan manual atau undangan.');
            $account = StockAccount::create([
                'variant_id' => $variant->id, 'credentials' => $data['credentials'], 'label' => $data['label'] ?? null,
            ]);
            for ($i = 1; $i <= $data['slots']; $i++) {
                $account->slots()->create(['label' => 'Profil '.$i]);
            }
            Store::audit('stock_create', (string) $account->id);
        });
        return $back->with('status', 'Akun dan slot stok ditambahkan.')->with('stock_feedback_target', 'stok-tambah');
    }

    public function stockAccess(StockAccount $account)
    {
        Store::staff();

        return response()->json([
            'access' => $account->credentials,
        ]);
    }

    public function recycle(StockSlot $slot)
    {
        Store::staff();
        $anchor = 'stok-profil-'.$slot->id;
        $back = back()->withFragment($anchor);
        try {
            DB::transaction(function () use ($slot) {
                $variantId = $slot->account->variant_id;
                Variant::whereKey($variantId)->lockForUpdate()->firstOrFail();
                $slot = StockSlot::whereKey($slot->id)->lockForUpdate()->firstOrFail();
                Store::check($slot->canRestock(now()), 'Slot masih aktif atau sedang ditahan.');
                $slot->update(['state' => 'available', 'reservation_token' => null]);
                Store::audit('stock_checked', (string) $slot->id);
            });
        } catch (ValidationException $exception) {
            session()->flash('stock_feedback_target', $anchor);
            throw $exception->redirectTo($back->getTargetUrl());
        }
        return $back->with('status', 'Slot sudah diperiksa dan tersedia kembali.')->with('stock_feedback_target', $anchor);
    }

    public function settings(Request $request)
    {
        Store::owner();
        $data = $request->validate([
            'topup_fee_type' => 'required|in:fixed,percent', 'topup_fee_value' => 'required|integer|min:0|max:100000',
            'checkout_fee_type' => 'required|in:fixed,percent', 'checkout_fee_value' => 'required|integer|min:0|max:100000',
            'withdraw_fee' => 'required|integer|min:0|max:100000',
            'fee_channels' => 'nullable|array',
            'fee_channels.*' => [Rule::in(array_diff(array_keys(config('doku.channels')), ['QRIS', 'CREDIT_CARD']))],
            'transfer_instructions' => 'required|string|max:2000',
        ]);
        foreach (['topup', 'checkout'] as $flow) {
            Store::check($data[$flow.'_fee_type'] !== 'percent' || $data[$flow.'_fee_value'] <= 100,
                'Persentase maksimal 100%.');
        }
        $data['fee_channels'] = json_encode($data['fee_channels'] ?? []);
        DB::transaction(function () use ($data) {
            foreach ($data as $key => $value) {
                DB::table('store_settings')->updateOrInsert(['key' => $key], ['value' => (string) $value]);
            }
            Store::audit('settings_save', 'fees_and_transfer');
        });
        return back()->with('status', 'Pengaturan disimpan.');
    }
}



