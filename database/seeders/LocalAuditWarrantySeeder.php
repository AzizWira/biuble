<?php

namespace Database\Seeders;

use App\Models\Variant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LocalAuditWarrantySeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Aturan garansi demo hanya tersedia di lingkungan lokal atau testing.');
        }

        $examples = [
            'chatgpt' => [
                'Sharing · 5 user · 1 bulan' => [7, true],
                'Private · dengan garansi · 1 bulan' => [7, true],
                'Private · dengan garansi · 3 bulan' => [14, false],
            ],
            'netflix' => [
                'Sharing · Bulanan · 1 user · 1 bulan' => [7, false],
                'Sharing · Bulanan · 2 user · 1 bulan' => [null, true],
            ],
            'canva' => [
                'Member · undangan email · 1 bulan' => [7, false],
            ],
        ];

        DB::transaction(function () use ($examples) {
            foreach ($examples as $slug => $variants) {
                foreach ($variants as $name => [$days, $proofRequired]) {
                    Variant::whereHas('product', fn ($query) => $query->where('slug', $slug))
                        ->where('name', $name)->whereNull('warranty_days')->where('login_proof_required', false)
                        ->update(['warranty_days' => $days, 'login_proof_required' => $proofRequired]);
                }
            }
        });
    }
}
