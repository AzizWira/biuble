<?php

use App\Http\Controllers\EmailLoginController;
use App\Http\Controllers\GoogleLoginController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\WhatsappContactController;
use App\Services\Doku;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [ShopController::class, 'catalog'])->name('catalog');
Route::get('/produk/{slug}', [ShopController::class, 'product'])->name('product');

Route::middleware('guest')->group(function () {
    Route::get('/masuk/demo', [EmailLoginController::class, 'showDemo'])->name('otp.demo');
    Route::post('/masuk/demo', [EmailLoginController::class, 'issueDemo'])->middleware('throttle:otp-send')->block()->name('otp.demo.issue');
    Route::get('/masuk/google', [GoogleLoginController::class, 'redirect'])->name('google.redirect');
    Route::get('/masuk/google/callback', [GoogleLoginController::class, 'callback'])->name('google.callback');
    Route::get('/masuk', [EmailLoginController::class, 'show'])->name('login');
    Route::post('/masuk/kirim', [EmailLoginController::class, 'send'])->middleware('throttle:otp-send')->block()->name('otp.send');
    Route::post('/masuk/verifikasi', [EmailLoginController::class, 'verify'])->middleware('throttle:otp-verify')->block()->name('otp.verify');
    Route::delete('/masuk/kode', [EmailLoginController::class, 'reset'])->block()->name('otp.reset');
});

Route::post('/doku/notification', function (Request $request, Doku $doku) {
    $doku->notification($request);
    return response()->json(['message' => 'received']);
})->name('doku.notification');

Route::middleware(['auth', 'account-write', 'throttle:store-web'])->group(function () {
    Route::get('/status-akun', fn (Request $request) => response()->json([
        'version' => \App\Services\Store::updates($request->user()->id),
    ]))->name('updates');
    Route::post('/keluar', [EmailLoginController::class, 'logout'])->name('logout');
    Route::get('/keamanan', [\App\Http\Controllers\SecurityController::class, 'show'])->name('security');
    Route::post('/keamanan/kirim', [\App\Http\Controllers\SecurityController::class, 'send'])->block()->middleware('throttle:security-send')->name('security.send');
    Route::post('/keamanan/verifikasi', [\App\Http\Controllers\SecurityController::class, 'verify'])->block()->middleware('throttle:security-verify')->name('security.verify');
    Route::get('/akun', [ShopController::class, 'account'])->name('account');
    Route::post('/akun/pengajuan', [ShopController::class, 'apply'])->name('reseller.apply');
    Route::get('/keranjang', [ShopController::class, 'cart'])->name('cart');
    Route::post('/keranjang', [ShopController::class, 'addCart'])->block()->name('cart.add');
    Route::delete('/keranjang/{line}', [ShopController::class, 'removeCart'])->block()->name('cart.remove');
    Route::post('/checkout', [ShopController::class, 'checkout'])->block()->name('checkout');
    Route::get('/pesanan', [ShopController::class, 'orders'])->name('orders');
    Route::get('/pesanan/{order}', [ShopController::class, 'showOrder'])->name('orders.show');
    Route::post('/item/{item}/batal', [ShopController::class, 'cancel'])->name('item.cancel');
    Route::post('/item/{item}/bukti-login', [SupportController::class, 'uploadProof'])->middleware('throttle:8,1')->name('support.proof.upload');
    Route::post('/toko/item/{item}/bukti-login', [SupportController::class, 'reviewProof'])->name('support.proof.review');
    Route::get('/bantuan/{kind}', [SupportController::class, 'index'])->where('kind', 'garansi|masalah')->name('support.index');
    Route::get('/bantuan/{kind}/baru', [SupportController::class, 'create'])->where('kind', 'garansi|masalah')->name('support.create');
    Route::post('/bantuan/{kind}', [SupportController::class, 'store'])->where('kind', 'garansi|masalah')->middleware('throttle:8,1')->name('support.store');
    Route::get('/bantuan/tiket/{case}', [SupportController::class, 'show'])->name('support.show');
    Route::post('/bantuan/tiket/{case}/balas', [SupportController::class, 'reply'])->middleware('throttle:15,1')->name('support.reply');
    Route::post('/bantuan/tiket/{case}/status', [SupportController::class, 'review'])->name('support.review');
    Route::get('/bantuan/lampiran/{file}', [SupportController::class, 'file'])->name('support.file');
    Route::delete('/bantuan/lampiran/{file}', [SupportController::class, 'deleteFile'])->name('support.file.delete');
    Route::post('/toko/kontak-whatsapp/{contact?}', [WhatsappContactController::class, 'save'])->name('staff.whatsapp.save');
    Route::delete('/toko/kontak-whatsapp/{contact}', [WhatsappContactController::class, 'remove'])->name('staff.whatsapp.remove');
    Route::get('/saldo', [ShopController::class, 'wallet'])->name('wallet');
    Route::post('/saldo/topup', [ShopController::class, 'topup'])->block()->middleware('throttle:money-actions')->name('topup');
    Route::post('/saldo/tarik', [ShopController::class, 'withdraw'])->block()->middleware('throttle:money-actions')->name('withdraw');
    Route::get('/bukti/{topup}', [ShopController::class, 'proof'])->name('proof');
    Route::post('/bayar/{reference}', [ShopController::class, 'payment'])->block()->name('payment');
    Route::post('/bayar/{reference}/cek-status', [ShopController::class, 'checkPayment'])->middleware('throttle:money-actions')->name('payment.check');
    Route::get('/hasil/{reference}', [ShopController::class, 'paymentReturn'])->name('payment.return');
    Route::get('/toko', [StaffController::class, 'dashboard'])->name('staff');
    Route::post('/toko/reseller/{user}', [StaffController::class, 'applicant'])->name('staff.applicant');
    Route::post('/toko/topup/{topup}', [StaffController::class, 'topup'])->name('staff.topup');
    Route::post('/toko/penarikan/{withdrawal}', [StaffController::class, 'withdrawal'])->middleware('recent-owner')->name('staff.withdrawal');
    Route::post('/toko/item/{item}/akses', [StaffController::class, 'deliver'])->name('staff.deliver');
    Route::post('/toko/item/{item}/refund', [StaffController::class, 'refund'])->middleware('recent-owner')->name('staff.refund');
    Route::post('/toko/item/{item}/refund-doku', [StaffController::class, 'gatewayRefund'])->middleware('recent-owner')->name('staff.gateway-refund');
    Route::post('/toko/kategori/{category?}', [StaffController::class, 'category'])->name('staff.category');
    Route::post('/toko/akun/{user}/status', [StaffController::class, 'accountStatus'])->middleware('recent-owner')->name('staff.account-status');
    Route::post('/toko/admin', [StaffController::class, 'admin'])->middleware('recent-owner')->name('staff.admin');
    Route::post('/toko/akun/{user}/role', [\App\Http\Controllers\AccountManagementController::class, 'changeRole'])->middleware('recent-owner')->name('staff.account-role');
    Route::post('/toko/akun/{user}/hapus', [\App\Http\Controllers\AccountManagementController::class, 'remove'])->middleware('recent-owner')->name('staff.account-remove');
    Route::post('/toko/akun/{user}/ajukan-hapus', [\App\Http\Controllers\AccountManagementController::class, 'requestRemoval'])->name('staff.account-removal-request');
    Route::post('/toko/penghapusan/{removal}', [\App\Http\Controllers\AccountManagementController::class, 'review'])->middleware('recent-owner')->name('staff.account-removal-review');
    Route::post('/toko/produk/{product?}', [StaffController::class, 'product'])->name('staff.product');
    Route::post('/toko/varian/{variant?}', [StaffController::class, 'variant'])->name('staff.variant');
    Route::post('/toko/stok', [StaffController::class, 'stock'])->name('staff.stock');
    Route::get('/toko/stok/akun/{account}/akses', [StaffController::class, 'stockAccess'])->name('staff.stock-access');
    Route::post('/toko/slot/{slot}', [StaffController::class, 'recycle'])->name('staff.recycle');
    Route::post('/toko/pengaturan', [StaffController::class, 'settings'])->middleware('recent-owner')->name('staff.settings');
});



