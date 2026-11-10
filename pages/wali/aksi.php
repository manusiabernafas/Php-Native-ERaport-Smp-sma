<?php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('wali');
$id = (int) ($_POST['id'] ?? 0);
$r = q('SELECT * FROM rombel WHERE id = ?', [$id])->fetch();
// Hanya wali kelas rombel ini atau admin
if (!$r || (!is_admin() && (int) $r['wali_id'] !== (int) user()['id'])) { flash('Anda bukan wali kelas rombel ini.', 'error'); redirect('dashboard'); }
if (nilai_terkunci()) { flash('Data rapor sedang dikunci operator.', 'error'); redirect('wali', ['id' => $id]); }
$per = (int) $r['periode_id'];
$siswaIds = array_map(fn($s) => (int) $s['id'], siswa_rombel($id));

switch ($_POST['aksi'] ?? '') {
    case 'kehadiran':
        $err = 0;
        $data = [];
        foreach ($siswaIds as $sid) {
            $v = [];
            foreach (['sakit', 'izin', 'alfa'] as $k) {
                $x = trim((string) ($_POST[$k][$sid] ?? ''));
                if ($x === '') { $v[$k] = 0; continue; }
                if (!ctype_digit($x) || (int) $x > 200) { $err++; $v[$k] = 0; continue; }
                $v[$k] = (int) $x;
            }
            $kep = (string) ($_POST['keputusan'][$sid] ?? '');
            if (!in_array($kep, ['', 'naik', 'tidak_naik', 'lulus', 'tidak_lulus'], true)) $kep = '';
            $cat = mb_substr(trim((string) ($_POST['catatan'][$sid] ?? '')), 0, 500);
            // Lewati siswa yang belum diisi sama sekali agar progres wali kelas tetap jujur
            $kosong = $cat === '' && $kep === '' && trim(($_POST['sakit'][$sid] ?? '') . ($_POST['izin'][$sid] ?? '') . ($_POST['alfa'][$sid] ?? '')) === '';
            if (!$kosong) $data[] = [$per, $sid, $v['sakit'], $v['izin'], $v['alfa'], $cat === '' ? null : $cat, $kep];
        }
        if ($err) { flash('Jumlah hari harus angka 0-200.', 'error'); break; }
        db()->beginTransaction();
        $st = db()->prepare('INSERT INTO rapor (periode_id, siswa_id, sakit, izin, alfa, catatan, keputusan) VALUES (?,?,?,?,?,?,?)
                             ON DUPLICATE KEY UPDATE sakit = VALUES(sakit), izin = VALUES(izin), alfa = VALUES(alfa), catatan = VALUES(catatan), keputusan = VALUES(keputusan)');
        foreach ($data as $d) $st->execute($d);
        db()->commit();
        flash('Data ' . count($data) . ' siswa tersimpan.');
        break;

    case 'ekskul':
        $eid = (int) ($_POST['ekskul'] ?? 0);
        if (!q('SELECT 1 FROM ekskul WHERE id = ?', [$eid])->fetchColumn()) { flash('Ekstrakurikuler tidak ditemukan.', 'error'); break; }
        $n = 0;
        db()->beginTransaction();
        foreach ($siswaIds as $sid) {
            $p = (string) ($_POST['predikat'][$sid] ?? '');
            if (in_array($p, ['Sangat Baik', 'Baik', 'Cukup', 'Kurang'], true)) {
                q('INSERT INTO nilai_ekskul (periode_id, siswa_id, ekskul_id, predikat, keterangan) VALUES (?,?,?,?,?)
                   ON DUPLICATE KEY UPDATE predikat = VALUES(predikat), keterangan = VALUES(keterangan)',
                    [$per, $sid, $eid, $p, mb_substr(trim((string) ($_POST['ket'][$sid] ?? '')), 0, 200)]);
                $n++;
            } else {
                q('DELETE FROM nilai_ekskul WHERE periode_id = ? AND siswa_id = ? AND ekskul_id = ?', [$per, $sid, $eid]);
            }
        }
        db()->commit();
        flash("Nilai ekstrakurikuler $n siswa tersimpan.");
        redirect('wali', ['id' => $id, 'tab' => 'ekskul', 'ekskul' => $eid]);

    default:
        flash('Aksi tidak dikenal.', 'error');
}
redirect('wali', ['id' => $id]);
