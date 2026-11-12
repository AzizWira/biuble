<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupportImages
{
    public const RULE = 'image|mimes:jpeg,png,webp|max:2048|dimensions:max_width=4096,max_height=4096';

    public function store(UploadedFile $file): string
    {
        abort_unless(function_exists('imagewebp'), 503, 'Pemrosesan gambar belum tersedia.');
        $info = getimagesize($file->getPathname());
        Store::check($info && $info[0] * $info[1] <= 12000000, 'Resolusi gambar terlalu besar.');
        $image = @imagecreatefromstring($file->get());
        Store::check($image !== false, 'Gambar tidak bisa dibaca.');
        $edge = max(imagesx($image), imagesy($image));
        $encoded = '';
        foreach ([2000, 1600, 1280] as $limit) {
            $ratio = min(1, $limit / $edge);
            $width = max(1, (int) round(imagesx($image) * $ratio));
            $height = max(1, (int) round(imagesy($image) * $ratio));
            $canvas = imagecreatetruecolor($width, $height);
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image));
            ob_start();
            try {
                $ok = imagewebp($canvas, null, 88);
                $encoded = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            if ($ok && $encoded && strlen($encoded) <= 2097152) break;
        }
        abort_unless($ok && $encoded && strlen($encoded) <= 2097152, 422, 'Gambar belum bisa diproses. Gunakan tangkapan layar yang lebih kecil.');
        $path = 'support/'.Str::uuid().'.webp';
        abort_unless(Storage::disk('local')->put($path, $encoded), 503, 'Bukti belum bisa disimpan. Silakan coba lagi.');

        return $path;
    }
}
