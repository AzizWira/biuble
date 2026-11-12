<?php

return [
    'netflix' => [
        'name' => 'Netflix', 'category' => 'Streaming', 'tone' => 'peach', 'mark' => 'N',
        'description' => 'Pilih durasi dan tipe akun yang sesuai kebutuhanmu.',
        'groups' => [
            'Sharing' => [
                'Harian · 1 user · 1 hari' => 2500, 'Harian · 1 user · 2 hari' => 5000,
                'Harian · 1 user · 3 hari' => 7500, 'Harian · 1 user · 5 hari' => 10000,
                'Mingguan · 1 user · 1 minggu' => 12000, 'Mingguan · 1 user · 2 minggu' => 20000,
                'Anti Limit · 4 user · 1 perangkat · 1 bulan' => 45000,
                'Bulanan · 1 user · 1 bulan' => 35000, 'Bulanan · 2 user · 1 bulan' => 18000,
                'Bulanan · 1 user · 2 bulan' => 72000, 'Bulanan · 2 user · 2 bulan' => 38000,
                'Bulanan · 1 user · 3 bulan' => 107000, 'Bulanan · 2 user · 3 bulan' => 56000,
                'Single Screen · 1 perangkat · 1 bulan' => 50000,
                'Semi Private · 2 perangkat · 1 bulan' => 45000,
                'Semi Private · 2 perangkat · 2 bulan' => 92000,
                'Semi Private · 2 perangkat · 3 bulan' => 137000,
            ],
            'Private · disiapkan setelah bayar' => [
                'Akun seller · 1 minggu' => 70000, 'Akun seller · 1 bulan' => 165000,
            ],
        ],
    ],
    'canva' => [
        'name' => 'Canva', 'category' => 'Desain', 'tone' => 'mint', 'mark' => 'C',
        'description' => 'Member dan Education dikirim lewat undangan. Akun Head disiapkan setelah bayar.',
        'groups' => [
            'Member · undangan email' => [
                '1 hari' => 500, '1 bulan' => 2000, '2 bulan' => 4000, '3 bulan' => 6000,
            ],
            'Education · undangan email' => ['Lifetime' => 10000],
            'Head · disiapkan setelah bayar' => ['1 bulan' => 15000],
        ],
    ],
    'chatgpt' => [
        'name' => 'ChatGPT', 'category' => 'AI', 'tone' => 'butter', 'mark' => 'G',
        'description' => 'Paket Sharing atau Private dengan pilihan durasi dan garansi.',
        'groups' => [
            'Sharing' => ['5 user · 1 bulan' => 12000, '8 user · 1 bulan' => 10000],
            'Private · tanpa garansi' => ['1 bulan' => 25000, '3 bulan' => 85000],
            'Private · dengan garansi' => ['1 bulan' => 37000, '3 bulan' => 97000],
        ],
    ],
];

