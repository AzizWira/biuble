<?php
namespace App\Models;

class OrderItem extends StoreRecord
{
    protected function casts(): array
    {
        return ['access' => 'encrypted', 'delivered_at' => 'datetime', 'ends_at' => 'datetime'];
    }
    public function order() { return $this->belongsTo(Order::class); }
    public function stockSlot() { return $this->belongsTo(StockSlot::class); }
    public function loginProof() { return $this->hasOne(LoginProof::class); }

    public function loginProofDeadline()
    {
        return $this->delivered_at?->copy()->addHours(24);
    }

    public function warrantyDeadline()
    {
        return $this->warranty_days ? $this->delivered_at?->copy()->addDays($this->warranty_days) : null;
    }

    public function warrantyBlock(): ?string
    {
        if (! $this->warranty_days) {
            return str_contains(mb_strtolower($this->name), 'dengan garansi')
                ? 'Masa garansi item ini belum tercatat. Gunakan Laporkan masalah agar staf bisa memeriksanya.'
                : 'Paket ini tidak memiliki garansi.';
        }
        if ($this->status !== 'delivered' || ! $this->delivered_at) {
            return 'Garansi hanya tersedia untuk item yang aksesnya sudah diserahkan dan masih memenuhi syarat.';
        }
        if (now()->gt($this->warrantyDeadline())) {
            return 'Masa pengajuan garansi sudah berakhir.';
        }
        if ($this->login_proof_required && $this->loginProof?->status === 'correction') {
            return 'Perbaiki bukti login yang diminta staf sebelum mengajukan garansi.';
        }
        if ($this->login_proof_required && ! $this->loginProof) {
            return now()->gt($this->loginProofDeadline())
                ? 'Batas 24 jam sudah lewat dan bukti login belum dikirim. Garansi tidak dapat diajukan.'
                : 'Unggah bukti login sebelum mengajukan garansi.';
        }

        return null;
    }
}

