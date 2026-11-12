@php($number = \App\Models\WhatsappContact::normalizeNumber($phone))
@if($number)
    <a class="button secondary" href="https://wa.me/{{ $number }}" target="_blank" rel="noopener noreferrer">Hubungi reseller via WhatsApp</a>
@else
    <p>Nomor WhatsApp reseller belum tersedia.</p>
@endif
