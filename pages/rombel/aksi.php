<?php
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('rombel');
$per = periode_aktif();
$id = (int) ($_POST['id'] ?? 0);
$r = $id ? q('SELECT * FROM rombel WHERE id = ?', [$id])->fetch() : null;
if ($id && !$r) { flash('Rombel tidak ditemukan.', 'error'); redirect('rombel'); }
$kembali = fn(array $x = []) => redirect($id ? 'rombel.detail' : 'rombel', $id ? ['id' => $id] + $x : []);
$siswa = array_map('intval', (array) ($_POST['siswa'] ?? []));

switch ($_POST['aksi'] ?? '') {
    case 'simpan':
        $nama = mb_substr(trim($_POST['nama'] ?? ''), 0, 20);
        $tingkat = (int) ($_POST['tingkat'] ?? 0);
        $fase = in_array($_POST['fase'] ?? '', ['A', 'B', 'C', 'D', 'E', 'F'], true) ? $_POST['fase'] : 'D';
        $wali = (int) ($_POST['wali_id'] ?? 0) ?: null;
        if ($nama === '' || $tingkat < 1 || $tingkat > 12) { flash('Isi nama rombel dan tingkat 1-12.', 'error'); $kembali(); }
        $periodeId = $r['periode_id'] ?? $per['id'];
        if (q('SELECT 1 FROM rombel WHERE periode_id = ? AND nama = ? AND id <> ?', [$periodeId, $nama, $id])->fetchColumn()) { flash("Rombel $nama sudah ada di semester ini.", 'error'); $kembali(); }
        if ($wali && q('SELECT 1 FROM rombel WHERE periode_id = ? AND wali_id = ? AND id <> ?', [$periodeId, $wali, $id])->fetchColumn()) { flash('Guru itu sudah menjadi wali kelas lain di semester ini.', 'error'); $kembali(); }
        if ($id) {
            q('UPDATE rombel SET nama = ?, tingkat = ?, fase = ?, wali_id = ? WHERE id = ?', [$nama, $tingkat, $fase, $wali, $id]);
            flash('Rombel diperbarui.');
        } else {
            q('INSERT INTO rombel (periode_id, nama, tingkat, fase, wali_id) VALUES (?,?,?,?,?)', [$per['id'], $nama, $tingkat, $fase, $wali]);
            $id = (int) db()->lastInsertId();
            if (!empty($_POST['semua_mapel'])) q('INSERT INTO pembelajaran (rombel_id, mapel_id) SELECT ?, id FROM mapel', [$id]);
            flash("Rombel $nama dibuat. Masukkan siswa dan tentukan guru tiap mapel.");
        }
        redirect('rombel.detail', ['id' => $id]);

    case 'hapus':
        if (q('SELECT 1 FROM anggota_rombel WHERE rombel_id = ?', [$id])->fetchColumn()) { flash('Keluarkan semua siswa sebelum menghapus rombel.', 'error'); $kembali(); }
        q('DELETE FROM rombel WHERE id = ?', [$id]);
        flash('Rombel dihapus.');
        redirect('rombel');

    case 'tambah_anggota':
        $n = 0;
        foreach ($siswa as $sid) {
            // UNIQUE(periode_id, siswa_id): siswa yang sudah punya kelas di semester ini dilewati
            $n += q("INSERT IGNORE INTO anggota_rombel (rombel_id, periode_id, siswa_id) SELECT ?, ?, id FROM siswa WHERE id = ? AND status = 'aktif'", [$id, $r['periode_id'], $sid])->rowCount();
        }
        flash("$n siswa dimasukkan ke {$r['nama']}.");
        $kembali();

    case 'keluarkan':
        if (!$siswa) { flash('Centang siswa yang akan dikeluarkan.', 'error'); $kembali(); }
        $n = q('DELETE FROM anggota_rombel WHERE rombel_id = ? AND siswa_id IN (' . implode(',', $siswa) . ')', [$id])->rowCount();
        flash("$n siswa dikeluarkan dari {$r['nama']}. Nilai yang sudah ada tetap tersimpan.");
        $kembali();

    case 'pindah':
        $tujuan = q('SELECT * FROM rombel WHERE id = ? AND periode_id = ?', [(int) ($_POST['tujuan'] ?? 0), $r['periode_id']])->fetch();
        if (!$tujuan || !$siswa) { flash('Centang siswa dan pilih rombel tujuan.', 'error'); $kembali(); }
        $n = q('UPDATE anggota_rombel SET rombel_id = ? WHERE rombel_id = ? AND siswa_id IN (' . implode(',', $siswa) . ')', [$tujuan['id'], $id])->rowCount();
        flash("$n siswa dipindahkan ke {$tujuan['nama']}. Nilai mapel di kelas lama perlu diisi ulang oleh guru kelas tujuan.");
        $kembali();

    case 'pembelajaran':
        $ada = array_column(q('SELECT p.mapel_id, p.id, (SELECT COUNT(*) FROM tp WHERE tp.pembelajaran_id = p.id) tp FROM pembelajaran p WHERE p.rombel_id = ?', [$id])->fetchAll(), null, 'mapel_id');
        $guruSah = array_map('intval', q("SELECT id FROM pengguna WHERE role IN ('guru','kepsek')")->fetchAll(PDO::FETCH_COLUMN));
        $ditolak = [];
        db()->beginTransaction();
        foreach (q('SELECT id, nama FROM mapel')->fetchAll() as $m) {
            $v = $_POST['guru'][$m['id']] ?? '';
            if ($v === '') {
                if (isset($ada[$m['id']])) {
                    // Jangan hapus pembelajaran yang sudah punya TP/nilai tanpa sengaja
                    if ($ada[$m['id']]['tp'] > 0) { $ditolak[] = $m['nama']; continue; }
                    q('DELETE FROM pembelajaran WHERE id = ?', [$ada[$m['id']]['id']]);
                }
                continue;
            }
            $g = (int) $v && in_array((int) $v, $guruSah, true) ? (int) $v : null;
            if (isset($ada[$m['id']])) q('UPDATE pembelajaran SET guru_id = ? WHERE id = ?', [$g, $ada[$m['id']]['id']]);
            else q('INSERT INTO pembelajaran (rombel_id, mapel_id, guru_id) VALUES (?,?,?)', [$id, $m['id'], $g]);
        }
        db()->commit();
        flash($ditolak ? 'Disimpan, kecuali ' . implode(', ', $ditolak) . ' yang sudah punya TP/nilai (hapus TP-nya dulu bila memang tidak diajarkan).' : 'Pembagian guru disimpan.', $ditolak ? 'info' : 'ok');
        redirect('rombel.detail', ['id' => $id, 'tab' => 'mapel']);
}
flash('Aksi tidak dikenal.', 'error');
$kembali();
