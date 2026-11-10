<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('nilai');
$id = (int) ($_POST['id'] ?? 0);
$pb = pembelajaran($id);
// Hanya guru pengampu (atau admin) yang boleh mengubah nilai & TP
if (!$pb || (!is_admin() && (int) $pb['guru_id'] !== (int) user()['id'])) { flash('Anda tidak berhak mengubah nilai mapel ini.', 'error'); redirect('nilai'); }
if (nilai_terkunci()) { flash('Nilai sedang dikunci operator.', 'error'); redirect('nilai.input', ['id' => $id]); }

switch ($_POST['aksi'] ?? '') {
    case 'tp_tambah':
        $d = mb_substr(trim($_POST['deskripsi'] ?? ''), 0, 255);
        if (mb_strlen($d) < 10) { flash('Deskripsi TP minimal 10 karakter.', 'error'); break; }
        $n = (int) q('SELECT COUNT(*) FROM tp WHERE pembelajaran_id = ?', [$id])->fetchColumn();
        if ($n >= 12) { flash('Maksimal 12 TP per mapel dalam satu semester.', 'error'); break; }
        // Kode berikutnya dari nomor terbesar, bukan jumlah (aman bila ada TP yang dihapus)
        $maks = (int) q("SELECT COALESCE(MAX(CAST(SUBSTRING(kode, 3) AS UNSIGNED)), 0) FROM tp WHERE pembelajaran_id = ?", [$id])->fetchColumn();
        q('INSERT INTO tp (pembelajaran_id, kode, deskripsi) VALUES (?,?,?)', [$id, 'TP' . ($maks + 1), $d]);
        flash('TP ditambahkan.');
        break;

    case 'tp_ubah':
        $d = mb_substr(trim($_POST['deskripsi'] ?? ''), 0, 255);
        if (mb_strlen($d) < 10) { flash('Deskripsi TP minimal 10 karakter.', 'error'); break; }
        q('UPDATE tp SET deskripsi = ? WHERE id = ? AND pembelajaran_id = ?', [$d, (int) $_POST['tp'], $id]);
        flash('TP diperbarui.');
        break;

    case 'tp_hapus':
        q('DELETE FROM tp WHERE id = ? AND pembelajaran_id = ?', [(int) $_POST['tp'], $id]);
        flash('TP dihapus beserta nilainya.');
        break;

    case 'kktp':
        $k = trim($_POST['kktp'] ?? '');
        if ($k !== '' && (!ctype_digit($k) || (int) $k < 1 || (int) $k > 100)) { flash('KKTP harus 1-100.', 'error'); break; }
        q('UPDATE pembelajaran SET kktp = ? WHERE id = ?', [$k === '' ? null : (int) $k, $id]);
        flash($k === '' ? 'KKTP mengikuti pengaturan sekolah.' : "KKTP mapel ini diatur $k.");
        break;

    case 'simpan':
        $tpIds = array_map('intval', q('SELECT id FROM tp WHERE pembelajaran_id = ?', [$id])->fetchAll(PDO::FETCH_COLUMN));
        $siswaIds = array_map(fn($s) => (int) $s['id'], siswa_rombel((int) $pb['rombel_id'], $pb['agama'] ?: null));
        $tpPost = (array) ($_POST['tp'] ?? []);
        $sasPost = (array) ($_POST['sas'] ?? []);
        $deskPost = (array) ($_POST['desk'] ?? []);
        $nama = array_column(siswa_rombel((int) $pb['rombel_id']), 'nama', 'id');
        $err = [];
        $simpanTp = $hapusTp = $simpanSas = [];
        foreach ($siswaIds as $sid) {
            foreach ($tpIds as $tid) {
                $v = baca_nilai($tpPost[$sid][$tid] ?? '');
                if ($v === false) { $err[] = "Nilai tidak valid untuk {$nama[$sid]}."; continue; }
                if ($v === null) $hapusTp[] = [$tid, $sid]; else $simpanTp[] = [$tid, $sid, $v];
            }
            $v = baca_nilai($sasPost[$sid] ?? '');
            if ($v === false) { $err[] = "SAS tidak valid untuk {$nama[$sid]}."; continue; }
            $desk = mb_substr(trim((string) ($deskPost[$sid] ?? '')), 0, 600);
            $simpanSas[] = [$id, $sid, $v, $desk === '' ? null : $desk];
        }
        if ($err) {
            // Tidak ada yang disimpan bila ada nilai salah, agar data tetap konsisten
            $_SESSION['nilai_err'] = array_values(array_unique($err));
            flash('Nilai belum disimpan. Perbaiki isian yang salah (harus angka 0-100).', 'error');
            break;
        }
        db()->beginTransaction();
        $ins = db()->prepare('INSERT INTO nilai_tp (tp_id, siswa_id, nilai) VALUES (?,?,?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)');
        foreach ($simpanTp as $r) $ins->execute($r);
        $del = db()->prepare('DELETE FROM nilai_tp WHERE tp_id = ? AND siswa_id = ?');
        foreach ($hapusTp as $r) $del->execute($r);
        $sas = db()->prepare('INSERT INTO nilai_sas (pembelajaran_id, siswa_id, nilai, deskripsi) VALUES (?,?,?,?)
                              ON DUPLICATE KEY UPDATE nilai = VALUES(nilai), deskripsi = VALUES(deskripsi)');
        foreach ($simpanSas as $r) $sas->execute($r);
        db()->commit();
        flash('Nilai ' . count($siswaIds) . ' siswa tersimpan.');
        break;

    default:
        flash('Aksi tidak dikenal.', 'error');
}
redirect('nilai.input', ['id' => $id]);
