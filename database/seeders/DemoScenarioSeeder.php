<?php

namespace Database\Seeders;

use App\Models\AccountRemovalRequest;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\StockAccount;
use App\Models\SupportCase;
use App\Models\Topup;
use App\Models\User;
use App\Models\Variant;
use App\Models\WhatsappContact;
use App\Services\Checkout;
use App\Services\OrderPayment;
use App\Services\Store;
use App\Services\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DemoScenarioSeeder extends Seeder
{
    private User $owner;
    private User $admin;
    private User $buyer;

    public function run(): void
    {
        if (Store::setting('demo_scenarios_version') === '2026-11-23') {
            $this->command?->info('Skenario demo sudah tersedia. Data yang sudah dicoba tetap dipertahankan.');
            return;
        }

        $this->call(DatabaseSeeder::class);
        try {
            DB::transaction(function () {
                $this->accounts();
                Auth::setUser($this->owner);
                $this->catalog();
                $this->stock();
                $this->wallet();
                $items = $this->orders();
                $this->support($items);
                $this->withdrawals();
                $this->information();
                $this->promotions();
                $this->archivedStock();
                $this->cart();
                foreach (['stock_create' => 'DEMO akun dengan profil/PIN dan slot biasa',
                    'product_save' => 'DEMO pengaturan bukti login per aplikasi',
                    'reseller_apply' => 'DEMO pengajuan reseller baru',
                    'announcement_save' => 'DEMO informasi stok beberapa aplikasi',
                    'promotion_save' => 'DEMO diskon berjangka di beranda'] as $action => $subject) {
                    Store::audit($action, $subject);
                }
                DB::table('store_settings')->updateOrInsert(['key' => 'demo_scenarios_version'], ['value' => '2026-11-23']);
            });
        } finally {
            Auth::forgetGuards();
        }
        $this->command?->info('Skenario demo lengkap siap. Semua saldo, bukti gambar, akun layanan, dan transaksi DEMO adalah simulasi.');
        $this->command?->info('Owner: biubleemail@gmail.com | Admin: hrchyosanzu@gmail.com | Reseller: biubleemail01@gmail.com');
    }

    private function account(string $email, string $name, string $role, string $phone, array $extra = []): User
    {
        return User::forceCreate($extra + [
            'email' => $email, 'name' => $name, 'role' => $role, 'phone' => $phone,
            'approval' => 'approved', 'account_status' => 'active', 'email_verified_at' => now(),
            'password' => Hash::make(Str::random(64)),
        ]);
    }

    private function accounts(): void
    {
        $this->owner = $this->account('biubleemail@gmail.com', 'Owner Demo', 'owner', '080000001001');
        $this->admin = $this->account('hrchyosanzu@gmail.com', 'Admin Demo', 'admin', '080000001002');
        $this->buyer = $this->account('biubleemail01@gmail.com', 'Reseller Demo', 'reseller', '080000001003');
        $this->account('admin-kedua@example.test', 'DEMO Admin Kedua', 'admin', '080000001004');
        $this->account('admin-nonaktif@example.test', 'DEMO Admin Nonaktif', 'admin', '080000001005', ['account_status' => 'inactive', 'account_status_reason' => 'Contoh admin hanya dapat melihat data.']);
        $this->account('calon-reseller@example.test', 'DEMO Calon Reseller Baru', 'reseller', '080000001006', ['approval' => 'pending']);
        $this->account('calon-reseller-2@example.test', 'DEMO Pengajuan Reseller Kedua', 'reseller', '080000001007', ['approval' => 'pending']);
        $this->account('reseller-ditolak@example.test', 'DEMO Pengajuan Ditolak', 'reseller', '080000001008', ['approval' => 'rejected']);
        $this->account('reseller-suspensi@example.test', 'DEMO Reseller Suspensi', 'reseller', '080000001009', ['account_status' => 'suspended', 'account_status_reason' => 'Contoh reseller hanya dapat melihat pesanan.']);
        $this->account('reseller-draf@example.test', 'DEMO Belum Mengajukan', 'reseller', '080000001010', ['approval' => 'draft']);
        $removable = $this->account('reseller-ajukan-hapus@example.test', 'DEMO Reseller Diajukan Hapus', 'reseller', '080000001011');
        AccountRemovalRequest::create(['user_id' => $removable->id, 'requested_by' => $this->admin->id,
            'reason' => 'DEMO pengajuan admin yang memerlukan keputusan owner.', 'status' => 'pending']);
        $refused = $this->account('reseller-hapus-ditolak@example.test', 'DEMO Penghapusan Ditolak', 'reseller', '080000001012');
        AccountRemovalRequest::create(['user_id' => $refused->id, 'requested_by' => $this->admin->id,
            'reviewed_by' => $this->owner->id, 'reason' => 'DEMO pengajuan yang sudah ditolak owner.', 'status' => 'rejected']);
        WhatsappContact::create(['name' => 'DEMO Owner - nomor contoh', 'role' => 'owner', 'number' => '6280000001001', 'active' => true]);
        WhatsappContact::create(['name' => 'DEMO Admin - sedang offline', 'role' => 'admin', 'number' => '6280000001002', 'active' => false]);
    }

    private function catalog(): void
    {
        Product::where('slug', 'netflix')->update(['stock_mode' => 'profiles', 'login_proof_required' => true]);
        Product::whereIn('slug', ['canva', 'chatgpt'])->update(['stock_mode' => 'slots', 'login_proof_required' => false]);
        Variant::whereHas('product', fn ($q) => $q->where('slug', 'netflix'))->update(['warranty_days' => 7, 'login_proof_required' => true]);
        $this->variant('netflix', 'Sharing · Bulanan · 2 user · 1 bulan')->update(['warranty_days' => null]);
        $this->variant('netflix', 'Sharing · Bulanan · 1 user · 3 bulan')->update(['active' => false]);
        $this->variant('canva', 'Member · undangan email · 1 bulan')->update(['warranty_days' => 7]);
        $this->variant('chatgpt', 'Sharing · 5 user · 1 bulan')->update(['warranty_days' => 14]);
        Variant::whereHas('product', fn ($q) => $q->where('slug', 'chatgpt'))->where('name', 'like', '%dengan garansi%')->update(['warranty_days' => 14]);
        foreach (['disney' => ['Disney+ (DEMO)', 'profiles', 7, '#e9ddf5'],
            'prime' => ['Prime Video (DEMO)', 'profiles', 6, '#cce9f7'],
            'viu' => ['Viu (DEMO)', 'slots', 4, '#ffedb7'],
            'wetv' => ['WeTV (DEMO)', 'slots', 3, '#d9f0e6'],
            'iqiyi' => ['iQIYI (DEMO)', 'slots', 4, '#f7c9b9']] as $slug => [$name, $mode, $capacity, $color]) {
            $product = Product::create(['category_id' => Category::where('name', 'Streaming')->value('id'),
                'slug' => $slug.'-demo', 'name' => $name, 'color' => $color, 'stock_mode' => $mode,
                'login_proof_required' => $mode === 'profiles', 'description' => 'Contoh DEMO pengelolaan akun. Harga dan akses adalah data simulasi.']);
            $product->variants()->create(['name' => 'Sharing · '.$capacity.' slot · 1 bulan', 'type' => 'sharing',
                'delivery' => 'stock', 'price' => 20000, 'duration_days' => 30, 'warranty_days' => 7, 'active' => true]);
        }
        $empty = Product::create(['category_id' => Category::where('name', 'Streaming')->value('id'),
            'slug' => 'demo-stok-kosong', 'name' => 'DEMO Stok Kosong', 'description' => 'Contoh aplikasi yang seluruh stoknya habis.', 'color' => '#e9ddf5']);
        $empty->variants()->create(['name' => 'Sharing · stok habis', 'price' => 15000, 'delivery' => 'stock', 'active' => true]);
        Category::create(['name' => 'DEMO Kategori Kosong', 'color' => '#def3f3']);
        $hidden = Product::create(['category_id' => $empty->category_id, 'slug' => 'demo-produk-disembunyikan',
            'name' => 'DEMO Produk Disembunyikan', 'description' => 'Contoh produk nonaktif.', 'active' => false]);
        $hidden->variants()->create(['name' => 'Private · contoh nonaktif', 'price' => 10000, 'delivery' => 'manual', 'active' => false]);
    }

    private function variant(string $slug, string $name): Variant
    {
        return Variant::whereHas('product', fn ($q) => $q->where('slug', $slug))->where('name', $name)->firstOrFail();
    }

    private function stock(): void
    {
        foreach (Variant::with('product')->where('delivery', 'stock')->get() as $variant) {
            if ($variant->product->slug === 'demo-stok-kosong'
                || $variant->name === 'Sharing · Harian · 1 user · 3 hari') continue;
            preg_match('/\b(\d+) (?:user|slot)/', $variant->name, $match);
            $capacity = (int) ($match[1] ?? 1);
            if ($variant->product->slug === 'netflix' && $variant->name === 'Sharing · Bulanan · 1 user · 1 bulan') $capacity = 5;
            if ($variant->product->slug === 'netflix' && $variant->duration_days === 1) $capacity = 5;
            $count = $capacity > 1 ? 2 : 1;
            for ($number = 1; $number <= $count; $number++) {
                $profileMode = $variant->product->stock_mode === 'profiles';
                $account = StockAccount::create(['variant_id' => $variant->id, 'label' => 'DEMO '.$variant->product->name.' - Akun '.chr(64 + $number),
                    'credentials' => 'DATA DEMO - akses simulasi, bukan akun layanan asli.'."\nEmail: ".$variant->product->slug.'-'.$variant->id.'-'.$number.'@example.test'."\nKata sandi: contoh-demo-saja"]);
                for ($slot = 1; $slot <= $capacity; $slot++) {
                    $account->slots()->create(['label' => ($profileMode ? 'Profil ' : 'Slot ').$slot,
                        'pin' => $profileMode ? str_pad((string) ($number * 100 + $slot), 4, '0', STR_PAD_LEFT) : null]);
                }
            }
        }
    }

    private function wallet(): void
    {
        foreach (['withdraw_fee' => '1000', 'topup_fee_type' => 'fixed', 'topup_fee_value' => '0',
            'checkout_fee_type' => 'percent', 'checkout_fee_value' => '2', 'fee_channels' => '["EMONEY_DANA"]',
            'transfer_instructions' => 'DATA DEMO - jangan melakukan transfer nyata. Gunakan bukti contoh untuk menguji konfirmasi manual.',
            'local_audit_data' => '1', 'demo_scenarios_data' => '1'] as $key => $value) {
            DB::table('store_settings')->updateOrInsert(['key' => $key], ['value' => $value]);
        }
        $topup = Topup::create(['user_id' => $this->buyer->id, 'token' => (string) Str::uuid(), 'reference' => 'DEMO-TOPUP-SALDO-AWAL',
            'method' => 'manual', 'amount' => 2000000, 'total' => 2000000, 'fee' => 0, 'status' => 'paid', 'created_at' => now()->subDays(40)]);
        Store::entry($this->buyer->id, 'topup', $topup->amount, 'topup:'.$topup->id, 'DEMO saldo awal untuk mencoba semua alur');
        DB::table('wallet_entries')->where('key', 'topup:'.$topup->id)->update(['created_at' => $topup->created_at]);
        foreach (['PERTAMA' => 25000, 'KEDUA' => 50000] as $label => $amount) {
            $pending = Topup::create(['user_id' => $this->buyer->id, 'token' => (string) Str::uuid(), 'reference' => 'DEMO-TOPUP-MANUAL-'.$label,
                'method' => 'manual', 'amount' => $amount, 'total' => $amount, 'fee' => 0, 'status' => 'pending']);
            $pending->update(['proof' => $this->image('topup-'.$pending->id, 'BUKTI TRANSFER CONTOH')]);
        }
        Topup::create(['user_id' => $this->buyer->id, 'token' => (string) Str::uuid(), 'reference' => 'DEMO-TOPUP-DITOLAK',
            'method' => 'manual', 'amount' => 10000, 'total' => 10000, 'fee' => 0, 'status' => 'rejected']);
        $gateway = app(Wallet::class)->topup($this->buyer, ['amount' => 20000, 'method' => 'EMONEY_DANA'], (string) Str::uuid(), null);
        $gateway->update(['reference' => 'DEMO-TOPUP-DOKU-MENUNGGU']);
        $gateway->payment->update(['reference' => $gateway->reference]);
    }

    private function order(string $reference, Variant $variant, string $method = 'wallet', int $quantity = 1, bool $designer = false): Order
    {
        $order = app(Checkout::class)->create($this->buyer, [['variant' => $variant->id, 'quantity' => $quantity,
            'email' => $variant->delivery === 'invite' ? 'customer-demo@example.test' : null, 'designer' => $designer]], $method, (string) Str::uuid());
        // References are readable in the demo. Gateway requests still receive unique invoice references.
        $order->update(['reference' => 'DEMO-'.$reference, 'created_at' => now()->subMinutes(10),
            'paid_at' => $order->paid_at ? now()->subMinutes(9) : null]);
        return $order;
    }

    private function delivered(string $reference, Variant $variant, int $hoursAgo): OrderItem
    {
        $order = $this->order($reference, $variant);
        $item = $order->items()->firstOrFail();
        if ($item->status !== 'delivered') app(Checkout::class)->deliver($item, 'DEMO akses akun atau undangan siap.');
        $start = now()->subHours($hoursAgo);
        $item->update(['delivered_at' => $start, 'ends_at' => $item->duration_days ? $start->copy()->addDays($item->duration_days) : null]);
        $order->update(['created_at' => $start->copy()->subMinutes(5), 'paid_at' => $start->copy()->subMinutes(4), 'expires_at' => $start->copy()->addMinutes(55)]);
        DB::table('wallet_entries')->where('user_id', $this->buyer->id)->where('key', 'like', 'order:'.$order->id.':%')
            ->update(['created_at' => $order->paid_at]);
        return $item->fresh();
    }

    private function proof(OrderItem $item, string $status): void
    {
        $proof = $item->loginProof()->create(['status' => $status, 'device' => 'DEMO Samsung Galaxy A55', 'location' => 'DEMO Kota Bandung, Jawa Barat',
            'created_at' => $item->delivered_at->copy()->addMinutes(15),
            'review_note' => $status === 'correction' ? 'DEMO mohon unggah ulang bagian halaman profil agar lebih jelas.' : null]);
        $proof->files()->create(['path' => $this->image('login-'.$item->id, 'BUKTI LOGIN CONTOH'), 'created_at' => $proof->created_at]);
    }

    private function orders(): array
    {
        $netflix = $this->variant('netflix', 'Sharing · Bulanan · 1 user · 1 bulan');
        $items = [];
        $items['missing'] = $this->delivered('LOGIN-BELUM-DIUNGGAH', $netflix, 1);
        $items['submitted'] = $this->delivered('LOGIN-MENUNGGU-PEMERIKSAAN', $netflix, 2);
        $this->proof($items['submitted'], 'submitted');
        $items['accepted'] = $this->delivered('LOGIN-DITERIMA', $netflix, 3);
        $this->proof($items['accepted'], 'accepted');
        $items['correction'] = $this->delivered('LOGIN-PERLU-PERBAIKAN', $netflix, 4);
        $this->proof($items['correction'], 'correction');
        $items['late'] = $this->delivered('LOGIN-TERLEWAT-24-JAM', $netflix, 26);
        $items['ending'] = $this->delivered('AKSES-BERAKHIR-BESOK', $netflix, 29 * 24);
        $this->proof($items['ending'], 'accepted');
        $items['inspection'] = $this->delivered('STOK-PERLU-DIPERIKSA', $netflix, 32 * 24);
        $items['inspection']->stockSlot->update(['state' => 'inspection']);
        $this->order('BAYAR-ATAU-GANTI-METODE', $netflix, 'VIRTUAL_ACCOUNT_BCA');
        $noWarranty = $this->variant('netflix', 'Sharing · Bulanan · 2 user · 1 bulan');
        $items['proof_without_warranty'] = $this->delivered('WAJIB-BUKTI-TANPA-GARANSI', $noWarranty, 1);
        $chatgpt = $this->variant('chatgpt', 'Sharing · 5 user · 1 bulan');
        $items['warranty_without_proof'] = $this->delivered('GARANSI-TANPA-BUKTI-LOGIN', $chatgpt, 2);
        $this->delivered('TANPA-GARANSI-TANPA-BUKTI', $this->variant('chatgpt', 'Sharing · 8 user · 1 bulan'), 2);
        $member = $this->variant('canva', 'Member · undangan email · 1 bulan');
        $items['canva'] = $this->delivered('CANVA-UNDANGAN-SUDAH-DIKIRIM', $member, 1);
        $this->order('CANVA-DESIGNER-MENUNGGU-INVITE', $member, 'wallet', 1, true);
        $this->order('CANVA-EDUCATION-MENUNGGU-INVITE', $this->variant('canva', 'Education · undangan email · Lifetime'));
        $private = $this->variant('netflix', 'Private · disiapkan setelah bayar · Akun seller · 1 minggu');
        $waiting = $this->order('PRIVATE-MENUNGGU-LEBIH-30-MENIT', $private);
        $waiting->update(['paid_at' => now()->subMinutes(40)]);
        $this->order('PRIVATE-MENUNGGU-KURANG-30-MENIT', $private);
        $this->order('CANVA-HEAD-MENUNGGU-AKUN', $this->variant('canva', 'Head · disiapkan setelah bayar · 1 bulan'));
        $cancel = $this->order('PEMBATALAN-MASUK-SALDO', $private);
        $cancel->update(['paid_at' => now()->subMinutes(45)]);
        app(Checkout::class)->cancel($cancel->items()->value('id'), $this->buyer);
        $unpaid = $this->order('PESANAN-DIBATALKAN-BELUM-BAYAR', $private, 'VIRTUAL_ACCOUNT_BCA');
        app(OrderPayment::class)->cancel($this->buyer, $unpaid);
        $expired = $this->order('TAGIHAN-KEDALUWARSA', $private, 'VIRTUAL_ACCOUNT_BCA');
        $expired->update(['status' => 'expired', 'expires_at' => now()->subHours(2)]);
        $expired->items()->update(['status' => 'expired']);
        $changed = $this->order('METODE-SUDAH-DIGANTI-KE-DANA', $private, 'VIRTUAL_ACCOUNT_BCA');
        $oldPayment = $changed->payment;
        app(OrderPayment::class)->change($this->buyer, $changed, 'EMONEY_DANA', $changed->subtotal + Store::fee($changed->subtotal, 'checkout', 'EMONEY_DANA'), $oldPayment->id);
        $oldPayment->update(['status' => 'paid']);
        Store::entry($this->buyer->id, 'cancellation', $oldPayment->amount, 'latepayment:'.$oldPayment->id, 'DEMO pembayaran tagihan lama dikembalikan tanpa biaya penarikan');
        Store::notify($this->buyer->id, 'DEMO pembayaran tagihan lama masuk ke saldo pembatalan. Gunakan tagihan terbaru untuk melanjutkan pesanan.', $changed->id);
        $gatewayRefund = $this->order('REFUND-DOKU-MENUNGGU-OWNER', $private, 'EMONEY_DANA');
        app(Checkout::class)->complete($gatewayRefund);
        $gatewayRefund->payment->update(['status' => 'paid']);
        $gatewayRefund->update(['paid_at' => now()->subMinutes(45)]);
        app(Checkout::class)->cancel($gatewayRefund->items()->value('id'), $this->buyer);
        $items['refund'] = $this->delivered('REFUND-PEMBELIAN-SELESAI', $this->variant('chatgpt', 'Sharing · 8 user · 1 bulan'), 3);
        $items['refund']->update(['status' => 'refunded']);
        $items['refund']->stockSlot->update(['state' => 'inspection']);
        Store::entry($this->buyer->id, 'refund', $items['refund']->amount, 'refund:'.$items['refund']->id, 'DEMO pengembalian produk bermasalah');
        foreach (['topup' => 0, 'cancellation' => 30000, 'refund' => 40000] as $source => $amount) {
            if ($amount) Store::entry($this->buyer->id, $source, $amount, 'demo:saldo:'.$source, 'DEMO saldo contoh untuk pengajuan penarikan');
        }
        DB::table('store_notifications')->where('user_id', $this->buyer->id)->where('order_id', $items['accepted']->order_id)->update(['read_at' => now()]);
        return $items;
    }

    private function support(array $items): void
    {
        foreach ([
            ['accepted', 'garansi', 'reviewing', 'DEMO garansi akun sedang diperiksa'],
            ['warranty_without_proof', 'garansi', 'waiting_owner', 'DEMO garansi menunggu keputusan owner'],
            ['canva', 'masalah', 'submitted', 'DEMO pertanyaan undangan Canva'],
            ['correction', 'masalah', 'waiting_customer', 'DEMO perlu informasi perangkat tambahan'],
            ['refund', 'masalah', 'resolved', 'DEMO masalah selesai dan dana dikembalikan'],
            ['inspection', 'masalah', 'rejected', 'DEMO pengaduan setelah masa akses berakhir'],
        ] as [$key, $kind, $status, $subject]) {
            $item = $items[$key];
            $case = SupportCase::create(['user_id' => $this->buyer->id, 'order_id' => $item->order_id,
                'order_item_id' => $item->id, 'kind' => $kind, 'status' => $status, 'subject' => $subject]);
            $message = $case->messages()->create(['user_id' => $this->buyer->id,
                'body' => 'DATA DEMO. Ini contoh laporan agar pemeriksaan bukti, balasan, dan status pengajuan dapat dicoba.']);
            $message->files()->create(['path' => $this->image('case-'.$case->id, 'BUKTI MASALAH CONTOH')]);
            if ($status !== 'submitted') {
                $case->messages()->create(['user_id' => $this->admin->id,
                    'body' => 'DEMO balasan admin. Pengajuan sudah diperiksa dan mengikuti status yang tercantum.']);
                Store::notify($this->buyer->id, 'DEMO ada balasan pada pengajuan #'.$case->id.'.', $item->order_id, $case->id);
            }
        }
    }

    private function withdrawals(): void
    {
        $wallet = app(Wallet::class);
        foreach (['pending', 'transferred', 'rejected'] as $status) {
            $withdrawal = $wallet->withdraw($this->buyer, ['topup' => 5000, 'cancellation' => 5000, 'refund' => 5000],
                'DEMO rekening contoh - jangan ditransfer', (string) Str::uuid(), 1000);
            if ($status !== 'pending') $wallet->decideWithdrawal($withdrawal->id, $status === 'transferred' ? 'approve' : 'reject', 'DEMO-TRANSFER-CONTOH');
        }
        $wallet->withdraw($this->buyer, ['topup' => 0, 'cancellation' => 10000, 'refund' => 0],
            'DEMO penarikan pembatalan tanpa biaya', (string) Str::uuid(), 0);
    }

    private function information(): void
    {
        $examples = [
            ['Selamat datang di demo Biuble', 'Semua saldo, akun aplikasi, bukti, dan transaksi pada demo ini adalah data simulasi. Jangan melakukan transfer atau menggunakan akun contoh sebagai akses layanan asli.\n\nCoba menu Pesanan, Saldo, Stok, Pusat informasi, dan Diskon untuk melihat pembaruannya.', true, now()->subMinutes(30), null, $this->owner->id],
            ['Stok Netflix, Disney+, dan Prime tersedia', "Contoh informasi gabungan beberapa aplikasi.\n\n- Netflix memiliki profil dengan PIN masing-masing.\n- Disney+ memiliki 7 profil per akun.\n- Prime Video memiliki 6 profil per akun.\n- Viu, WeTV, iQIYI, dan ChatGPT menggunakan slot tanpa PIN.", true, now()->subMinutes(20), now()->addDays(3), $this->admin->id],
            ['Promo terbaru sudah dimulai', 'Lihat bagian Diskon untukmu di atas kategori. Terdapat promo Netflix, Canva, dan Viu dengan hitung mundur.', true, now()->subMinutes(10), now()->addDays(2), $this->owner->id],
            ['DEMO informasi yang sudah dibaca', 'Contoh pengumuman yang masih tayang tetapi sudah dibaca oleh reseller.', true, now()->subHour(), now()->addDays(2), $this->owner->id],
            ['DEMO pengumuman terjadwal', 'Informasi ini akan muncul otomatis setelah waktu mulai.', true, now()->addHours(6), now()->addDays(2), $this->owner->id],
            ['DEMO pengumuman sudah berakhir', 'Contoh pengumuman yang masa tayangnya selesai.', true, now()->subDays(2), now()->subHour(), $this->owner->id],
            ['DEMO draf pengumuman', 'Contoh draf yang dapat diedit dan diaktifkan oleh owner.', false, now(), null, $this->owner->id],
        ];
        foreach ($examples as [$title, $body, $published, $start, $end, $author]) {
            $announcement = Announcement::create(['title' => $title, 'body' => str_replace('\\n', "\n", $body), 'published' => $published,
                'starts_at' => $start, 'ends_at' => $end, 'user_id' => $author]);
            if ($title === 'DEMO informasi yang sudah dibaca') {
                DB::table('announcement_reads')->insert(['announcement_id' => $announcement->id, 'user_id' => $this->buyer->id,
                    'revision' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    private function promotions(): void
    {
        foreach ([
            ['netflix', null, 'DEMO Promo Netflix 48 jam', 15, true, now()->subMinutes(5), now()->addDays(2)],
            ['canva', $this->variant('canva', 'Member · undangan email · 1 bulan')->id, 'DEMO Promo Canva Member', 25, true, now()->subMinutes(5), now()->addDay()],
            ['viu-demo', null, 'DEMO Promo Viu 6 jam', 10, true, now()->subMinutes(5), now()->addHours(6)],
            ['chatgpt', null, 'DEMO Diskon terjadwal', 20, true, now()->addHours(3), now()->addHours(9)],
            ['prime-demo', null, 'DEMO Diskon sudah berakhir', 20, true, now()->subDay(), now()->subHour()],
            ['disney-demo', null, 'DEMO Diskon dinonaktifkan', 30, false, now()->subHour(), now()->addDay()],
        ] as [$slug, $variant, $title, $discount, $active, $start, $end]) {
            Promotion::create(['product_id' => Product::where('slug', $slug)->value('id'), 'variant_id' => $variant,
                'title' => $title, 'discount_percent' => $discount, 'active' => $active, 'starts_at' => $start, 'ends_at' => $end]);
        }
    }

    private function archivedStock(): void
    {
        $product = Product::create(['category_id' => Category::where('name', 'Streaming')->value('id'), 'slug' => 'demo-produk-dihapus',
            'name' => 'DEMO Produk Sudah Dihapus', 'description' => 'Contoh riwayat pesanan tetap tersedia setelah produk dihapus.']);
        $variant = $product->variants()->create(['name' => 'Sharing · contoh arsip', 'price' => 1000, 'delivery' => 'stock', 'duration_days' => 1, 'active' => true]);
        $account = StockAccount::create(['variant_id' => $variant->id, 'label' => 'DEMO Akun Arsip', 'credentials' => 'DEMO akses arsip']);
        $slot = $account->slots()->create(['label' => 'Slot arsip']);
        $this->delivered('RIWAYAT-PRODUK-SUDAH-DIHAPUS', $variant, 72);
        $slot->update(['state' => 'inspection']);
        $slot->delete();
        $account->delete();
        $variant->delete();
        $product->delete();
    }

    private function image(string $key, string $title): string
    {
        $path = 'demo-fixtures/'.$key.'.webp';
        $image = imagecreatetruecolor(1000, 600);
        $cream = imagecolorallocate($image, 255, 249, 239);
        $ink = imagecolorallocate($image, 35, 52, 81);
        $mint = imagecolorallocate($image, 217, 240, 230);
        imagefill($image, 0, 0, $cream);
        imagefilledrectangle($image, 50, 50, 950, 140, $mint);
        imagestring($image, 5, 85, 85, 'BIUBLE - DATA DEMO', $ink);
        imagestring($image, 5, 85, 210, $title, $ink);
        imagestring($image, 4, 85, 270, 'Gambar contoh untuk menguji unggah, pemeriksaan, dan unduh bukti.', $ink);
        imagestring($image, 4, 85, 320, 'Tidak menunjukkan pembayaran, login, atau masalah layanan nyata.', $ink);
        imagestring($image, 4, 85, 425, 'Kode contoh: '.$key, $ink);
        ob_start();
        imagewebp($image, null, 80);
        $bytes = ob_get_clean();
        imagedestroy($image);
        if (! is_string($bytes) || ! Storage::disk('local')->put($path, $bytes)) {
            throw new \RuntimeException('Gambar bukti contoh belum dapat disimpan.');
        }
        return $path;
    }

    private function cart(): void
    {
        $netflix = $this->variant('netflix', 'Sharing · Bulanan · 1 user · 1 bulan');
        $canva = $this->variant('canva', 'Member · undangan email · 1 bulan');
        DB::table('store_settings')->updateOrInsert(['key' => 'demo_cart:'.$this->buyer->email], ['value' => json_encode([
            ['variant' => $netflix->id, 'quantity' => 1],
            ['variant' => $canva->id, 'quantity' => 2, 'email' => 'customer-demo@example.test', 'designer' => true],
        ], JSON_THROW_ON_ERROR)]);
    }
}
