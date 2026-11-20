<fieldset class="announcement-option span-full" data-announcement-option>
    <label class="check-label"><input type="checkbox" name="announce" value="1" data-announcement-toggle> Berikan pengumuman di pusat informasi</label>
    <div class="stack announcement-fields" data-announcement-fields hidden>
        <label class="field">Judul pengumuman<input name="announcement_title" maxlength="150" placeholder="Contoh Stok Netflix sudah tersedia"></label>
        <label class="field">Isi pengumuman<textarea name="announcement_body" maxlength="5000" rows="4" placeholder="Tulis informasi yang ingin disampaikan kepada reseller"></textarea></label>
        <label class="field">Masa tayang<select name="announcement_until" data-until-select><option value="manual">Sampai dinonaktifkan</option><option value="duration">Durasi tertentu</option><option value="date">Tanggal dan jam tertentu</option></select></label>
        <label class="field" data-until="duration" hidden>Durasi dalam jam<input name="announcement_hours" type="number" min="1" max="8760" value="24"></label>
        <label class="field" data-until="date" hidden>Tayang sampai (WIB)<input name="announcement_end" type="datetime-local"></label>
        <small>Pengumuman diterbitkan setelah data berhasil disimpan. Untuk menggabungkan informasi beberapa aplikasi, buat satu pengumuman melalui menu Pusat informasi.</small>
    </div>
</fieldset>
