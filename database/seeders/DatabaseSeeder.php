<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('catalog') as $slug => $data) {
            $color = ['peach' => '#f7c9b9', 'mint' => '#d9f0e6', 'butter' => '#ffedb7'][$data['tone']];
            $category = Category::firstOrCreate(['name' => $data['category']], ['color' => $color]);
            $product = Product::firstOrCreate(['slug' => $slug], [
                'category_id' => $category->id, 'name' => $data['name'],
                'description' => $data['description'], 'color' => $color,
            ]);
            foreach ($data['groups'] as $group => $variants) {
                foreach ($variants as $label => $price) {
                    $name = $group.' · '.$label;
                    preg_match('/(\d+) (hari|minggu|bulan)/u', $label, $duration);
                    $days = isset($duration[1]) ? (int) $duration[1] * ['hari' => 1, 'minggu' => 7, 'bulan' => 30][$duration[2]] : null;
                    $delivery = Str::contains($group, 'undangan') ? 'invite'
                        : (Str::contains($group, 'disiapkan') ? 'manual' : 'stock');
                    Variant::firstOrCreate(['product_id' => $product->id, 'name' => $name], [
                        'price' => $price, 'delivery' => $delivery, 'duration_days' => $days,
                        'type' => Str::contains(Str::lower($group), 'private') ? 'private' : 'sharing',
                        'designer' => $slug === 'canva' && Str::startsWith($group, 'Member'),
                    ]);
                }
            }
        }
    }
}

