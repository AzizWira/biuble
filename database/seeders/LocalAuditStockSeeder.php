<?php

namespace Database\Seeders;

use App\Models\StockAccount;
use App\Models\Variant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocalAuditStockSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Stok demo hanya boleh dibuat di lingkungan lokal atau testing.');
        }

        foreach (Variant::where('delivery', 'stock')->pluck('id') as $variantId) {
            DB::transaction(function () use ($variantId) {
                $variant = Variant::whereKey($variantId)->lockForUpdate()->firstOrFail();
                if ($variant->delivery !== 'stock') {
                    return;
                }
                preg_match('/\b([1-9]\d?)\s+users?\b/i', $variant->name, $users);
                $capacity = (int) ($users[1] ?? 1);
                $accounts = $capacity > 1 || $variant->duration_days === 1 ? ['A', 'B'] : ['A'];
                foreach ($accounts as $letter) {
                    $this->syncAccount($variant, $letter, $capacity);
                }
            }, 3);
        }
    }

    private function syncAccount(Variant $variant, string $letter, int $capacity): void
    {
        $prefix = 'DEMO-'.$variant->id.($letter === 'A' ? '' : '-'.$letter);
        $email = strtolower($prefix).'@example.test';
        $credentials = "DATA DEMO AUDIT — bukan akun layanan asli.\nEmail: {$email}\nKata sandi: contoh-audit-saja";
        $account = StockAccount::where('variant_id', $variant->id)
            ->whereHas('slots', fn ($slots) => $slots->where('label', $prefix.'-1'))
            ->get()->first(fn (StockAccount $candidate) => $candidate->credentials === $credentials);

        $account ??= StockAccount::create([
            'variant_id' => $variant->id, 'label' => 'DEMO Akun '.$letter, 'credentials' => $credentials,
        ]);
        if ($account->label === null) {
            $account->update(['label' => 'DEMO Akun '.$letter]);
        }
        $slots = $account->slots()->withExists('orderItems')->orderByDesc('id')->lockForUpdate()->get();
        $count = $slots->count();
        foreach ($slots as $slot) {
            if ($count <= $capacity) {
                break;
            }
            if ($slot->state === 'available' && ! $slot->reservation_token && ! $slot->order_items_exists
                && preg_match('/^'.preg_quote($prefix, '/').'-(\d+)$/D', $slot->label, $number)
                && (int) $number[1] > 1) {
                $slot->delete();
                $count--;
            }
        }
        if ($count > $capacity) {
            $this->command?->warn($account->stockLabel().' (ID '.$account->id.') masih memiliki '.$count.' slot karena riwayat atau status slot dipertahankan.');
        }
        $labels = $slots->pluck('label');
        for ($number = 1; $count < $capacity; $number++) {
            $label = $prefix.'-'.$number;
            if (! $labels->containsStrict($label)) {
                $account->slots()->create(['label' => $label]);
                $count++;
            }
        }
    }
}
