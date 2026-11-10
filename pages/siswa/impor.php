<?php
require_role('admin');
$KOLOM = ['nis', 'nisn', 'nama', 'jk', 'tempat_lahir', 'tgl_lahir', 'agama', 'nama_ayah', 'nama_ibu', 'pekerjaan_ayah', 'pekerjaan_ibu', 'alamat', 'telepon_ortu', 'tgl_masuk'];
if (isset($_GET['format'])) {
    kirim_csv('format-impor-siswa.csv', [$KOLOM, ['2601001', '0131234500', 'Contoh Nama Siswa', 'L', 'Sukamaju', '2013-05-17', 'Islam', 'Nama Ayah', 'Nama Ibu', 'Wiraswasta', 'Ibu Rumah Tangga', 'Jl. Contoh RT 01/RW 02', '081234567890', date('Y') . '-07-14']]);
}
$per = periode_aktif();
$rombel = q('SELECT id, nama FROM rombel WHERE periode_id = ? ORDER BY tingkat, nama', [$per['id']])->fetchAll();
$hasil = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/aksi_fungsi.php';
    $f = $_FILES['berkas'] ?? null;
    $rid = (int) ($_POST['rombel'] ?? 0);
    if ($rid && !q('SELECT 1 FROM rombel WHERE id = ? AND periode_id = ?', [$rid, $per['id']])->fetchColumn()) $rid = 0;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK || $f['size'] > 2_000_000 || !is_uploaded_file($f['tmp_name'])) {
        flash('Berkas CSV gagal diunggah (maks 2 MB).', 'error');
        redirect('siswa.impor');
    }
    $rows = baca_csv($f['tmp_name']);
    $head = array_map(fn($h) => strtolower(trim($h)), $rows[0] ?? []);
    if (!in_array('nis', $head, true) || !in_array('nisn', $head, true) || !in_array('nama', $head, true)) {
        flash('Kolom nis, nisn, dan nama wajib ada. Gunakan format CSV dari tombol Unduh format.', 'error');
        redirect('siswa.impor');
    }
    $galat = [];
    $siap = [];
    $nisDipakai = [];
    foreach (array_slice($rows, 1) as $i => $r) {
        $in = [];
        foreach ($head as $k => $h) $in[$h] = $r[$k] ?? '';
        if (trim(implode('', $in)) === '') continue;
        // Siswa yang sudah ada (NIS sama) diperbarui, bukan dibuat ganda
        $ada = q('SELECT id FROM siswa WHERE nis = ?', [trim($in['nis'])])->fetchColumn();
        [$d, $e] = validasi_siswa($in, (int) $ada);
        if (isset($nisDipakai[$d['nis']])) $e['nis'] = 'NIS ganda di dalam berkas.';
        $nisDipakai[$d['nis']] = true;
        if ($e) { $galat[] = 'Baris ' . ($i + 2) . ': ' . implode(' ', $e); continue; }
        $siap[] = [$d, (int) $ada];
    }
    if ($galat) {
        $hasil = ['galat' => $galat];
    } else {
        db()->beginTransaction();
        $baru = $ubah = $masuk = 0;
        foreach ($siap as [$d, $ada]) {
            $kol = array_keys($d);
            if ($ada) { q('UPDATE siswa SET ' . implode(', ', array_map(fn($k) => "$k = ?", $kol)) . ' WHERE id = ?', [...array_values($d), $ada]); $sid = $ada; $ubah++; }
            else { q('INSERT INTO siswa (' . implode(', ', $kol) . ') VALUES (' . implode(',', array_fill(0, count($kol), '?')) . ')', array_values($d)); $sid = (int) db()->lastInsertId(); $baru++; }
            if ($rid) { $masuk += q('INSERT IGNORE INTO anggota_rombel (rombel_id, periode_id, siswa_id) VALUES (?,?,?)', [$rid, $per['id'], $sid])->rowCount(); }
        }
        db()->commit();
        flash("Impor selesai: $baru siswa baru, $ubah diperbarui" . ($rid ? ", $masuk dimasukkan ke rombel" : '') . '.');
        redirect('siswa');
    }
}
$title = 'Impor siswa dari CSV';
$kembali = [url('siswa'), 'Data siswa'];
require __DIR__ . '/../../includes/header.php';
?>
<?php if ($hasil): ?><div class="pesan error"><?= icon('awas') ?> <span>Impor dibatalkan, tidak ada data yang disimpan. Perbaiki baris berikut lalu unggah ulang:<br><?= implode('<br>', array_map('e', array_slice($hasil['galat'], 0, 15))) ?><?= count($hasil['galat']) > 15 ? '<br>... dan ' . (count($hasil['galat']) - 15) . ' baris lain' : '' ?></span></div><?php endif; ?>
<div class="grid g2">
  <form method="post" enctype="multipart/form-data" class="kartu">
    <?= csrf_field() ?>
    <h3>Unggah berkas</h3>
    <label class="f"><span>Berkas CSV</span><input type="file" name="berkas" accept=".csv,text/csv" required></label>
    <label class="f"><span>Masukkan langsung ke rombel (opsional)</span><select name="rombel"><option value="">Jangan masukkan ke rombel</option><?php foreach ($rombel as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['nama']) ?></option><?php endforeach; ?></select></label>
    <button class="btn"><?= icon('unggah', 16) ?> Impor</button>
  </form>
  <div class="kartu">
    <h3>Cara impor</h3>
    <ol class="kecil" style="padding-left:18px;margin:0">
      <li>Klik <b>Unduh format</b>, buka di Excel, isi satu siswa per baris.</li>
      <li>Kolom wajib: <b>nis, nisn</b> (10 digit), <b>nama, tgl_lahir</b> (format 2013-05-17).</li>
      <li>Simpan sebagai <b>CSV</b> (pemisah titik koma atau koma, keduanya bisa).</li>
      <li>Siswa dengan NIS yang sudah ada akan diperbarui, bukan dibuat ganda.</li>
      <li>Bila ada satu baris salah, seluruh impor dibatalkan supaya data tidak setengah masuk.</li>
    </ol>
    <a class="btn putih mt" href="<?= url('siswa.impor', ['format' => 1]) ?>"><?= icon('unduh', 16) ?> Unduh format</a>
  </div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
