<?php

namespace App\Http\Controllers;

use App\Models\WhatsappContact;
use App\Services\Store;
use Illuminate\Http\Request;

class WhatsappContactController extends Controller
{
    public function save(Request $request, ?WhatsappContact $contact = null)
    {
        Store::owner();
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'role' => 'required|in:owner,admin',
            'number' => 'required|string|max:30|regex:/^\+?[0-9][0-9 ()-]*$/',
            'active' => 'required|boolean',
        ]);
        $number = WhatsappContact::normalizeNumber($data['number']);
        Store::check($number !== null,
            'Gunakan nomor WhatsApp yang valid, misalnya 08… atau +62…');
        $data['number'] = $number;
        ($contact ?? new WhatsappContact())->fill($data)->save();

        return back()->with('status', 'Kontak WhatsApp disimpan.');
    }

    public function remove(WhatsappContact $contact)
    {
        Store::owner();
        $contact->delete();

        return back()->with('status', 'Kontak WhatsApp dihapus.');
    }
}
