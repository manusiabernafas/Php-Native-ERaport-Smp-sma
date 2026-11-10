<?php
// GET: unduh format/isi nilai CSV · POST: impor nilai dari CSV
$id = (int) ($_GET['id'] ?? 0);
$pb = pembelajaran($id);
if (!$pb) { flash('Pembelajaran tidak ditemukan.', 'error'); redirect('nilai'); }
$tp = q('SELECT id, kode FROM tp WHERE pembelajaran_id = ? ORDER BY id', [$id])->fetchAll();
$siswa = siswa_rombel((int) $pb['rombel_id'], $pb['agama'] ?: null);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $h = nilai_rombel((int) $pb['rombel_id'], $id)[$id]['siswa'] ?? [];
    $baris = [array_merge(['No', 'NIS', 'Nama'], array_column($tp, 'kode'), ['SAS'])];
    foreach ($siswa as $i => $s) {
        $r = [$i + 1, $s['nis'], $s['nama']];
        foreach ($tp as $t) $r[] = $h[$s['id']]['tp'][$t['id']] ?? '';
        $r[] = $h[$s['id']]['sas'] ?? '';
        $baris[] = $r;
    }
    kirim_csv('nilai-' . $pb['kode'] . '-' . str_replace(' ', '', $pb['rombel']) . '.csv', $baris);
}

if (!is_admin() && (int) $pb['guru_id'] !== (int) user()['id']) { flash('Anda tidak berhak mengubah nilai mapel ini.', 'error'); redirect('nilai'); }
if (nilai_terkunci()) { flash('Nilai sedang dikunci operator.', 'error'); redirect('nilai.input', ['id' => $id]); }
$f = $_FILES['berkas'] ?? null;
if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 1_000_000 || !is_uploaded_file($f['tmp_name'])) {
    flash('Berkas CSV gagal diunggah (maks 1 MB).', 'error');
    redirect('nilai.input', ['id' => $id]);
}
$rows = baca_csv($f['tmp_name']);
$head = array_map('strtoupper', $rows[0] ?? []);
$kolNis = array_search('NIS', $head, true);
if ($kolNis === false) { flash('Kolom NIS tidak ditemukan. Gunakan format CSV yang diunduh dari halaman ini.', 'error'); redirect('nilai.input', ['id' => $id]); }
$kolTp = [];
foreach ($tp as $t) { $k = array_search(strtoupper($t['kode']), $head, true); if ($k !== false) $kolTp[$t['id']] = $k; }
$kolSas = array_search('SAS', $head, true);
$byNis = array_column($siswa, 'id', 'nis');
$err = [];
$ops = [];
foreach (array_slice($rows, 1) as $n => $r) {
    $nis = $r[$kolNis] ?? '';
    if ($nis === '') continue;
    if (!isset($byNis[$nis])) { $err[] = "Baris " . ($n + 2) . ": NIS $nis bukan siswa kelas ini."; continue; }
    $sid = $byNis[$nis];
    foreach ($kolTp as $tid => $k) {
        $v = baca_nilai($r[$k] ?? '');
        if ($v === false) { $err[] = "Baris " . ($n + 2) . ": nilai TP tidak valid."; continue; }
        if ($v !== null) $ops[] = ['tp', $tid, $sid, $v];
    }
    if ($kolSas !== false) {
        $v = baca_nilai($r[$kolSas] ?? '');
        if ($v === false) $err[] = "Baris " . ($n + 2) . ": SAS tidak valid.";
        elseif ($v !== null) $ops[] = ['sas', $id, $sid, $v];
    }
}
if ($err) {
    $_SESSION['nilai_err'] = array_values(array_unique($err));
    flash('Impor dibatalkan, tidak ada nilai yang diubah. Perbaiki baris yang salah lalu unggah ulang.', 'error');
    redirect('nilai.input', ['id' => $id]);
}
db()->beginTransaction();
$a = db()->prepare('INSERT INTO nilai_tp (tp_id, siswa_id, nilai) VALUES (?,?,?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)');
$b = db()->prepare('INSERT INTO nilai_sas (pembelajaran_id, siswa_id, nilai) VALUES (?,?,?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)');
foreach ($ops as [$j, $x, $sid, $v]) ($j === 'tp' ? $a : $b)->execute([$x, $sid, $v]);
db()->commit();
flash(count($ops) . ' nilai berhasil diimpor dari CSV.');
redirect('nilai.input', ['id' => $id]);
