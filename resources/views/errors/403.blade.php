@extends('errors.shell')
@section('title', 'Halaman tidak dapat diakses')
@section('message')
@if(auth()->check() && auth()->user()->isReadOnly())
Akunmu sedang dibatasi ke mode hanya lihat. Kamu tetap bisa login dan membaca data, tetapi tidak dapat mengubah data atau melakukan transaksi baru. Hubungi owner untuk mengaktifkan kembali akses.
@elseif(auth()->check() && auth()->user()->role === 'reseller' && auth()->user()->approval !== 'approved')
Ajukan akses reseller melalui halaman Akun, lalu tunggu persetujuan owner untuk mulai bertransaksi.
@else
Akunmu tidak memiliki izin untuk membuka halaman atau menjalankan tindakan ini. Kembali ke menu yang tersedia untuk akunmu.
@endif
@endsection
