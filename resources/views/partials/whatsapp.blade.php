@php($contacts = \App\Models\WhatsappContact::orderByRaw("CASE WHEN role = 'owner' THEN 0 ELSE 1 END")->orderBy('id')->get())
<button class="whatsapp-launch" type="button" data-whatsapp-open aria-label="Bantuan WhatsApp" aria-haspopup="dialog" aria-controls="whatsapp-help" hidden>
    <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor"><path d="M12 2a9.8 9.8 0 0 0-8.44 14.8L2.2 22l5.3-1.38A9.87 9.87 0 1 0 12 2Zm0 17.9c-1.43 0-2.82-.38-4.02-1.08l-.29-.17-3.12.82.83-3.03-.19-.31a8.15 8.15 0 1 1 6.79 3.77Zm4.42-6.03c-.24-.12-1.44-.71-1.66-.79-.22-.08-.38-.12-.54.12-.17.24-.63.79-.77.95-.14.16-.28.18-.52.06-.24-.12-1.03-.38-1.96-1.2-.72-.64-1.21-1.44-1.35-1.68-.14-.24-.02-.37.1-.49.11-.11.24-.28.36-.42.12-.14.16-.24.24-.4.08-.17.04-.31-.02-.43-.06-.12-.54-1.31-.74-1.8-.19-.47-.39-.41-.54-.42h-.46c-.16 0-.42.06-.64.3-.22.24-.84.82-.84 2.01s.86 2.34.98 2.5c.12.16 1.69 2.59 4.1 3.63.57.25 1.01.39 1.36.5.57.18 1.09.15 1.5.09.46-.07 1.44-.59 1.64-1.15.2-.56.2-1.04.14-1.14-.06-.1-.22-.16-.46-.28Z"/></svg>
</button>
<dialog id="whatsapp-help" class="confirm-dialog whatsapp-dialog" aria-labelledby="whatsapp-title">
    <div class="stack">
        <div class="support-heading">
            <h2 id="whatsapp-title">Bantuan WhatsApp</h2>
            <form method="dialog"><button class="button secondary" autofocus>Tutup</button></form>
        </div>
        @forelse($contacts as $contact)
            @php($roleLabel = $contact->role === 'owner' ? 'Owner' : 'Admin')
            @if($contact->active)
                <a class="whatsapp-contact" href="https://wa.me/{{ $contact->number }}" target="_blank" rel="noopener noreferrer">
                    <span><strong>{{ $contact->name }}</strong><small>{{ $roleLabel }}</small></span><span>Chat</span>
                </a>
            @else
                <div class="whatsapp-contact is-disabled" aria-disabled="true">
                    <span><strong>{{ $contact->name }}</strong><small>{{ $roleLabel }}</small></span><span>Nonaktif</span>
                </div>
            @endif
        @empty
            <p>Belum ada kontak WhatsApp.</p>
        @endforelse
    </div>
</dialog>
