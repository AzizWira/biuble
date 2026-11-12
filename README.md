# Biuble

Unggahan bukti login dan lampiran pengajuan memakai PHP GD dengan dukungan WebP. Aktifkan ekstensi GD pada PHP yang menjalankan web agar Biuble bisa mengonversi gambar sebelum menyimpannya.

Satu aplikasi Laravel 13 + Blade + CSS + JavaScript ringan. Cakupan bisnis tersedia di ../PRD.md.

## Menjalankan lokal

1. Jalankan composer install.
2. Salin .env.example ke .env bila belum ada. Jalankan php artisan key:generate sekali saat instalasi baru.
3. Buat database MySQL bernama biuble. Sesuaikan DB_HOST, DB_PORT, DB_USERNAME, dan DB_PASSWORD di .env. Aplikasi memakai MySQL InnoDB.
4. Jalankan php artisan migrate --seed dan php artisan storage:link.
5. Siapkan owner dengan php artisan biuble:staff EMAIL_OWNER owner. Perintah yang sama dengan role admin menyiapkan admin. Dari dashboard, owner juga dapat membuat akun admin baru.
6. Konfigurasikan SMTP dan alamat pengirim untuk OTP, lalu jalankan php artisan serve.
7. Jalankan php artisan schedule:work di pengembangan, atau jadwalkan php artisan schedule:run setiap menit di produksi.

## Halaman dan alur

- / katalog publik, /produk/SLUG daftar paket.
- /masuk untuk OTP email dan Google. Google aktif setelah GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI diisi dan callback didaftarkan.
- /akun untuk pengajuan reseller dan notifikasi.
- /keranjang dan /checkout untuk pesanan multi-item. Akun belum disetujui ditolak server.
- /pesanan untuk riwayat, akses, invoice cetak PDF browser, dan pembatalan item manual.
- /saldo untuk top-up manual atau DOKU, rincian sumber saldo, pengajuan penarikan dan ledger.
- /toko untuk pengelolaan katalog, stok, pesanan, persetujuan, dan pengaturan biaya.
- /keamanan untuk verifikasi OTP owner sebelum tindakan keuangan, perubahan biaya, dan penambahan admin. Verifikasi berlaku 15 menit.
- Admin mengedit harga dan stok, menyetujui top-up dan mengirim undangan. Owner menangani penarikan, refund, dan pemberian akun manual.
- Kategori dan produk bisa diunggah gambarnya, diatur warna dan status publikasi. Produk awal memakai inisial sampai foto katalog diunggah.

## DOKU

DOKU dinonaktifkan sampai DOKU_ENABLED, DOKU_CLIENT_ID, dan DOKU_SECRET dikonfigurasi. Sandbox adalah default. Aktifkan produksi hanya setelah pengujian merchant dan persetujuan kanal selesai. Atur daftar kanal di config/doku.php sesuai kanal yang aktif pada merchant.

Daftarkan endpoint HTTPS /doku/notification di dashboard DOKU. Hanya endpoint ini dikecualikan dari CSRF karena memverifikasi tanda tangan body mentah dan header merchant. Redirect pembayaran tidak mengubah status keuangan. Kode memverifikasi nominal dan referensi, serta memproses sukses sekali. URL bayar dapat dicoba lagi dengan referensi yang sama jika koneksi gagal.

php artisan biuble:reconcile memeriksa status pembayaran pending yang punya tautan, lewat API DOKU. Scheduler menjalankannya setiap lima menit. php artisan biuble:expire melepaskan reservasi yang belum dibayar dan menandai stok habis masa aktif untuk inspeksi. Pembayaran setelah slot dilepas masuk pemeriksaan owner; slot pembeli lain tetap terlindungi.

MDR QRIS tidak diteruskan ke pembeli. Biaya top-up otomatis dan checkout dapat diatur terpisah. Tarif nominal default Rp0, dan persentase dibulatkan ke rupiah terdekat, setengah ke atas. Kanal berbayar perlu diizinkan pada pengaturan owner setelah ketentuan merchant dipastikan. QRIS dan kartu tidak ditambahkan biaya checkout.

Untuk pembayaran saldo, pembatalan manual setelah 30 menit mengembalikan nilai bersih item beserta add-on ke saldo cancellation. Untuk DOKU, pembatalan membuat antrean refund kanal asal. Owner mencatat referensi setelah pengembalian benar-benar selesai. Tidak ada kredit ganda ke saldo sekaligus refund gateway.

## Keamanan dan keputusan implementasi

- Pembelian memakai refund lain dulu, kemudian refund pembatalan, lalu top-up.
- Penarikan memotong nominal pengajuan dari saldo. Biaya dipotong dari nominal tersebut, sehingga owner mentransfer angka net. Tarif disimpan saat pengajuan.
- Harga dan biaya checkout ditampilkan sebelum konfirmasi dan diverifikasi ulang server.
- Durasi awal paket bulanan di-seed sebagai 30 hari per bulan dan dapat diedit owner. Paket lifetime memakai durasi kosong.
- Akun produk disimpan terenkripsi. Bukti top-up disimpan privat dan hanya bisa diunduh pemilik atau staf.
- Approval, stok, pemenuhan, refund dan penarikan memakai transaksi DB dan penguncian. Ledger memiliki referensi unik. Aktor dan tindakan finansial dicatat.
- SMTP, OAuth Google, dan sandbox DOKU memerlukan kredensial nyata. Tes otomatis memakai fixture dan simulasi HTTP, tanpa memindahkan uang sungguhan.
- APP_DEBUG=false, APP_ENV=production, SESSION_SECURE_COOKIE=true dan HTTPS wajib saat produksi. Cadangkan database, storage privat, serta APP_KEY dengan akses terpisah. Rotasi APP_KEY memerlukan migrasi data terenkripsi.
- Jangan gunakan mailer log/array untuk produksi. Pengiriman OTP ditolak jika salah satunya masih dipakai di produksi. Pada pengembangan lokal, mailer log bawaan menulis kode untuk debugging; gunakan SMTP lokal atau mailer array agar kode tidak masuk log.

## Verifikasi

Jalankan php artisan test. Suite otomatis memakai SQLite in-memory yang terisolasi sesuai phpunit.xml; pengujian paralel MySQL dilakukan pada database pengujian terpisah. Skenario mencakup akses pengguna, top-up, debit saldo, idempotensi, penarikan campuran, pembatalan item, dan signature webhook. Uji alur merchant di sandbox dan uji paralel di MySQL sebelum menerima uang nyata.


## Data audit lokal

Jalankan `php artisan db:seed --class=LocalAuditSeeder` untuk menyiapkan natashasheiyla@gmail.com (reseller disetujui), wira123widodo@gmail.com (owner), dan auraiftitahh@gmail.com (admin). Login tetap OTP atau Google; tidak ada password atau kode OTP bawaan. Seeder ini hanya berjalan di local/testing dan tidak dipanggil oleh seeder produksi.

Data berlabel DEMO mencakup saldo simulasi, stok dengan akses palsu, pesanan siap/menunggu akun/undangan, top-up, penarikan, dan calon reseller contoh. Tidak ada pembayaran DOKU, email undangan, atau transfer nyata yang dilakukan. Seeder dapat diulang tanpa menambah saldo atau pesanan demo dua kali. Jangan gunakan database audit ini sebagai database produksi.

Menu kelola toko menggunakan `?bagian=pesanan` (default), topup, stok, katalog, serta menu khusus owner pengajuan, penarikan, kategori, admin, pengaturan, aktivitas. Hanya bagian aktif yang dirender.

## Status akun admin dan reseller

Owner mengubah status di Kelola toko > Reseller atau Tim admin > Ubah status. Tindakan ini memerlukan verifikasi owner. Aktif mengizinkan penggunaan sesuai role dan persetujuan reseller. Suspensi dan Nonaktif saat ini sama-sama memberikan mode hanya-lihat: login, akses data yang memang diizinkan, serta logout tetap tersedia; perubahan data dan transaksi baru ditolak oleh middleware dan service keuangan. Kedua label tidak memiliki masa berakhir otomatis. Pemulihan dilakukan owner dengan memilih Aktif.

Status akses ini terpisah dari persetujuan pengajuan reseller. Semua akun lama dimigrasikan menjadi aktif, dan owner tidak dapat membatasi akun owner melalui endpoint ini. Callback pembayaran sah yang sudah berlangsung tetap dicatat dengan perlindungan transaksi ganda.
