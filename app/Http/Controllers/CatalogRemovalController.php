<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Variant;
use App\Models\StockAccount;
use App\Models\StockSlot;
use App\Services\Store;
use Illuminate\Support\Facades\DB;

class CatalogRemovalController extends Controller
{
    public function remove(string $kind, int $id)
    {
        Store::owner();
        $models = ['category' => Category::class, 'product' => Product::class, 'variant' => Variant::class,
            'account' => StockAccount::class, 'slot' => StockSlot::class];
        abort_unless(isset($models[$kind]), 404);
        $target = $models[$kind]::findOrFail($id);
        DB::transaction(function () use ($kind, $target) {
            $products = Product::query()->when($kind === 'category', fn ($q) => $q->where('category_id', $target->id))
                ->when($kind !== 'category', fn ($q) => $q->whereKey($kind === 'product' ? $target->id : 0))->pluck('id');
            $variants = Variant::query()->when(in_array($kind, ['category', 'product']), fn ($q) => $q->whereIn('product_id', $products))
                ->when($kind === 'variant', fn ($q) => $q->whereKey($target->id))
                ->when($kind === 'account', fn ($q) => $q->whereKey($target->variant_id))
                ->when($kind === 'slot', fn ($q) => $q->whereKey($target->account->variant_id))->orderBy('id')->lockForUpdate()->get();
            $accounts = StockAccount::query()->whereIn('variant_id', $variants->modelKeys())
                ->when($kind === 'account', fn ($q) => $q->whereKey($target->id))
                ->when($kind === 'slot', fn ($q) => $q->whereKey($target->stock_account_id))->pluck('id');
            $slots = StockSlot::whereIn('stock_account_id', $accounts)->when($kind === 'slot', fn ($q) => $q->whereKey($target->id))->lockForUpdate()->get();
            foreach ($slots as $slot) {
                Store::check($slot->state !== 'reserved' && ($slot->state !== 'used' || $slot->canRestock(now())),
                    'Masih ada slot yang dipesan atau sedang digunakan. Selesaikan transaksi terkait sebelum menghapus stok ini.');
            }
            StockSlot::whereIn('id', $slots->modelKeys())->delete();
            if ($kind !== 'slot') StockAccount::whereIn('id', $accounts)->delete();
            if (in_array($kind, ['category', 'product', 'variant'])) Variant::whereIn('id', $variants->modelKeys())->delete();
            if (in_array($kind, ['category', 'product'])) Product::whereIn('id', $products)->delete();
            if ($kind === 'category') $target->delete();
            Store::audit('catalog_removed', $kind.' #'.$target->id);
        }, 3);
        return redirect()->route('staff', ['bagian' => match ($kind) {
            'category' => 'kategori', 'product', 'variant' => 'katalog', default => 'stok',
        }])->with('status', 'Data dihapus dari daftar aktif. Riwayat transaksi tetap tersimpan.');
    }
}
