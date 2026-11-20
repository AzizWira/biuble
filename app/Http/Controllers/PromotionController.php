<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Variant;
use App\Models\Promotion;
use App\Services\Announcements;
use App\Services\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PromotionController extends Controller
{
    public function save(Request $request, ?Promotion $promotion = null)
    {
        Store::owner();
        $data = $request->validate([
            'title' => 'required|string|max:150', 'product_id' => 'required|integer', 'variant_id' => 'nullable|integer',
            'discount_percent' => 'required|integer|min:1|max:99', 'starts_at' => 'required|date',
            'until' => 'required|in:duration,date', 'hours' => 'required_if:until,duration|nullable|integer|min:1|max:8760',
            'ends_at' => 'required_if:until,date|nullable|date|after:starts_at', 'active' => 'nullable|boolean',
        ]);
        Product::findOrFail($data['product_id']);
        if (! empty($data['variant_id'])) Variant::where('product_id', $data['product_id'])->findOrFail($data['variant_id']);
        $announcement = Announcements::validate($request);
        DB::transaction(function () use ($request, $promotion, $data, $announcement) {
            $end = $data['until'] === 'duration' ? Carbon::parse($data['starts_at'])->addHours((int) $data['hours']) : $data['ends_at'];
            ($promotion ?? new Promotion())->fill(collect($data)->only(['title', 'product_id', 'variant_id', 'discount_percent', 'starts_at'])->all()
                + ['ends_at' => $end, 'active' => $request->boolean('active')])->save();
            Announcements::publish($request, $announcement);
            Store::audit('promotion_save', $data['title']);
        });
        return back()->with('status', 'Diskon disimpan. Harga promo berlaku sesuai jadwal.');
    }

    public function remove(Promotion $promotion)
    {
        Store::owner();
        $promotion->delete();
        Store::audit('promotion_remove', $promotion->title);
        return back()->with('status', 'Diskon dihapus.');
    }
}
