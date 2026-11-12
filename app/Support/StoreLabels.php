<?php

namespace App\Support;

class StoreLabels
{
    public static function supportStatus(string $status): string
    {
        return [
            'submitted' => 'Dikirim', 'reviewing' => 'Sedang diperiksa',
            'waiting_customer' => 'Perlu informasi tambahan', 'waiting_owner' => 'Menunggu keputusan owner',
            'resolved' => 'Selesai', 'rejected' => 'Ditolak',
        ][$status] ?? 'Perlu diperiksa';
    }

    public static function accountStatus(string $status): string
    {
        return ['active'=>'Aktif','suspended'=>'Suspensi','inactive'=>'Nonaktif'][$status] ?? 'Perlu diperiksa';
    }

    public static function date($value, bool $withTime = true): string
    {
        if (! $value) return '—';
        $date = \Illuminate\Support\Carbon::parse($value, config('app.timezone'))->setTimezone(config('app.timezone'))->locale('id');
        return $date->isoFormat('D MMMM YYYY').($withTime ? ' · '.$date->format('H:i') : '');
    }

    public static function entryOrderId(object $entry): ?int
    {
        return preg_match('/^order:(\d+):/', $entry->key, $match) ? (int) $match[1] : null;
    }

    public static function entryTitle(object $entry): string
    {
        $prefix = explode(':', $entry->key)[0];
        return match ($prefix) {
            'order' => 'Pembelian produk',
            'topup' => 'Top-up saldo masuk',
            'cancel' => 'Dana pembatalan masuk',
            'refund' => 'Pengembalian dana dari owner',
            'hold' => 'Dana ditahan untuk penarikan',
            'release' => 'Dana penarikan dikembalikan',
            'demo' => 'Saldo contoh untuk audit',
            default => $entry->amount < 0 ? 'Saldo keluar' : 'Saldo masuk',
        };
    }

    public static function variantName(string $name): string
    {
        $parts = array_filter(explode(' · ', $name), fn ($part) => ! in_array(mb_strtolower(trim($part)),
            ['sharing', 'private', 'undangan email', 'disiapkan setelah bayar'], true));
        return implode(' · ', $parts) ?: $name;
    }

    public static function status(string $status): string
    {
        return ['pending'=>'Menunggu', 'paid'=>'Sudah dibayar', 'waiting'=>'Menunggu akses',
            'delivered'=>'Akses siap', 'reserved'=>'Menunggu pembayaran', 'cancelled'=>'Dibatalkan',
            'refunded'=>'Dana dikembalikan', 'refund_requested'=>'Menunggu refund',
            'refunded_gateway'=>'Refund selesai', 'review'=>'Perlu diperiksa', 'rejected'=>'Ditolak',
            'transferred'=>'Sudah ditransfer', 'expired'=>'Kedaluwarsa', 'approved'=>'Disetujui',
            'failed'=>'Pembayaran gagal', 'processing'=>'Sedang diproses', 'draft'=>'Belum diajukan', 'available'=>'Tersedia', 'used'=>'Terpakai', 'inspection'=>'Perlu diperiksa',
        ][$status] ?? 'Status perlu diperiksa';
    }

    public static function method(string $method): string
    {
        return ['wallet'=>'Saldo Biuble', 'manual'=>'Transfer manual', 'QRIS'=>'QRIS',
            'CREDIT_CARD'=>'Kartu kredit', 'VIRTUAL_ACCOUNT_BCA'=>'Virtual Account BCA',
            'VIRTUAL_ACCOUNT_BANK_MANDIRI'=>'Virtual Account Mandiri', 'VIRTUAL_ACCOUNT_BNI'=>'Virtual Account BNI',
            'EMONEY_DANA'=>'DANA'][$method] ?? 'Pembayaran online';
    }

    public static function subject(string $subject): string
    {
        return ['fees_and_transfer'=>'Biaya layanan dan rekening transfer', 'new'=>'Varian baru'][$subject] ?? $subject;
    }

    public static function source(string $source): string
    {
        return ['topup'=>'Top-up', 'cancellation'=>'Pembatalan', 'refund'=>'Refund pesanan'][$source] ?? 'Saldo lainnya';
    }

    public static function activity(string $action): string
    {
        return [
            'account_status_changed'=>'Status akun diperbarui', 'account_role_changed'=>'Role akun diubah',
            'account_removed'=>'Akun dihapus', 'account_removal_requested'=>'Penghapusan akun diajukan',
            'account_removal_approved'=>'Penghapusan disetujui', 'account_removal_rejected'=>'Penghapusan ditolak',
            'support_review'=>'Status pengajuan bantuan diperbarui', 'login_proof_review'=>'Bukti login diperiksa',
            'support_file_deleted'=>'Gambar bukti dihapus',
            'reseller_apply'=>'Pengajuan reseller', 'reseller_approved'=>'Reseller disetujui',
            'reseller_rejected'=>'Pengajuan reseller ditolak', 'topup_approve'=>'Top-up disetujui',
            'topup_reject'=>'Top-up ditolak', 'withdraw_approve'=>'Penarikan selesai',
            'withdraw_reject'=>'Penarikan ditolak', 'settings'=>'Pengaturan diperbarui', 'settings_save'=>'Biaya layanan dan rekening diperbarui',
            'category_create'=>'Kategori disimpan', 'admin_create'=>'Admin toko ditambahkan',
            'product_save'=>'Produk disimpan', 'variant_save'=>'Varian disimpan',
            'stock_create'=>'Akun dan slot ditambahkan', 'stock_checked'=>'Stok diperiksa dan tersedia kembali',
            'deliver'=>'Akses akun atau undangan diserahkan', 'refund'=>'Dana pesanan dikembalikan ke saldo',
            'gateway_refund_completed'=>'Pengembalian dana DOKU dicatat selesai', 'cancel_item'=>'Item pesanan dibatalkan',
        ][$action] ?? 'Aktivitas toko';
    }
}
