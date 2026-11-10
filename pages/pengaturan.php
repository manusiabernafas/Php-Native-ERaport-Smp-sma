<?php
require_role('admin');
$s = setting();
$err = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? 'simpan';
    if ($aksi === 'kunci') {
        q('UPDATE pengaturan SET kunci_nilai = ? WHERE id = 1', [$s['kunci_nilai'] ? 0 : 1]);
        flash($s['kunci_nilai'] ? 'Nilai dibuka kembali, guru bisa mengubah nilai.' : 'Nilai dikunci. Guru & wali kelas tidak bisa mengubah nilai lagi.');
        redirect('pengaturan');
    }
    if ($aksi === 'terbit') {
        q('UPDATE pengaturan SET rapor_terbit = ? WHERE id = 1', [$s['rapor_terbit'] ? 0 : 1]);
        flash($s['rapor_terbit'] ? 'Rapor ditarik dari portal siswa.' : 'Rapor dibagikan. Siswa & orang tua bisa melihatnya dengan NISN + tanggal lahir.');
        redirect('pengaturan');
    }
    $d = [];
    foreach (['nama_sekolah', 'npsn', 'alamat', 'kelurahan', 'kecamatan', 'kabupaten', 'provinsi', 'kode_pos', 'telepon', 'email', 'website', 'kepala_sekolah', 'nip_kepsek', 'tempat_rapor'] as $k) {
        $d[$k] = mb_substr(trim($_POST[$k] ?? ''), 0, $k === 'alamat' ? 200 : 100);
    }
    $d['jenjang'] = in_array($_POST['jenjang'] ?? '', ['SD', 'SMP', 'SMA', 'SMK'], true) ? $_POST['jenjang'] : 'SMP';
    $d['kktp'] = (int) ($_POST['kktp'] ?? 75);
    $d['bobot_tp'] = (int) ($_POST['bobot_tp'] ?? 50);
    if (mb_strlen($d['nama_sekolah']) < 5) $err['nama_sekolah'] = 'Isi nama sekolah.';
    if ($d['npsn'] !== '' && !preg_match('/^\d{8}$/', $d['npsn'])) $err['npsn'] = 'NPSN 8 digit angka.';
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err['email'] = 'Email tidak valid.';
    if ($d['kktp'] < 1 || $d['kktp'] > 100) $err['kktp'] = 'KKTP 1-100.';
    if ($d['bobot_tp'] < 0 || $d['bobot_tp'] > 100) $err['bobot_tp'] = 'Bobot 0-100.';
    if ($d['nip_kepsek'] !== '' && !preg_match('/^[0-9 ]{8,25}$/', $d['nip_kepsek'])) $err['nip_kepsek'] = 'NIP hanya angka.';
    if (!$err) {
        q('UPDATE pengaturan SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($d))) . ' WHERE id = 1', array_values($d));
        flash('Pengaturan sekolah disimpan.');
        redirect('pengaturan');
    }
    $s = array_merge($s, $d);
}
$G = fn($k) => isset($err[$k]) ? '<span class="galat">' . e($err[$k]) . '</span>' : '';
$title = 'Pengaturan';
$sub = 'Identitas sekolah, aturan nilai, kunci nilai, dan pembagian rapor';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/subnav.php';
?>
<div class="grid g2" id="status">
  <div class="kartu" style="border-top:4px solid <?= $s['kunci_nilai'] ? 'var(--koral)' : 'var(--hijau)' ?>">
    <h3><?= icon($s['kunci_nilai'] ? 'kunci' : 'buka') ?> Nilai <?= $s['kunci_nilai'] ? 'dikunci' : 'terbuka' ?></h3>
    <p class="kecil muted" style="margin-top:-6px">Kunci nilai setelah semua guru selesai, agar rapor yang dicetak tidak berubah. Admin tetap bisa mengubah.</p>
    <form method="post" data-yakin="<?= $s['kunci_nilai'] ? 'Buka kembali nilai untuk guru?' : 'Kunci semua nilai sekarang?' ?>"><?= csrf_field() ?><input type="hidden" name="aksi" value="kunci"><button class="btn <?= $s['kunci_nilai'] ? 'putih' : 'koral' ?>"><?= $s['kunci_nilai'] ? 'Buka kunci nilai' : 'Kunci nilai' ?></button></form>
  </div>
  <div class="kartu" style="border-top:4px solid <?= $s['rapor_terbit'] ? 'var(--hijau)' : 'var(--garis2)' ?>">
    <h3><?= icon('mata') ?> Rapor <?= $s['rapor_terbit'] ? 'sudah dibagikan' : 'belum dibagikan' ?></h3>
    <p class="kecil muted" style="margin-top:-6px">Saat dibagikan, siswa & orang tua bisa membuka rapor semester aktif di halaman <a href="<?= url('cek') ?>" target="_blank">Lihat rapor</a> dengan NISN + tanggal lahir.</p>
    <form method="post" data-yakin="<?= $s['rapor_terbit'] ? 'Tarik rapor dari portal siswa?' : 'Bagikan rapor ke portal siswa sekarang?' ?>"><?= csrf_field() ?><input type="hidden" name="aksi" value="terbit"><button class="btn <?= $s['rapor_terbit'] ? 'putih' : 'hijau' ?>"><?= $s['rapor_terbit'] ? 'Tarik rapor' : 'Bagikan rapor' ?></button></form>
  </div>
</div>
<form method="post" class="kartu mt" style="max-width:980px">
  <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan">
  <h3>Identitas sekolah</h3>
  <div class="grid g3">
    <label class="f" style="grid-column:span 2"><span>Nama sekolah</span><input name="nama_sekolah" value="<?= e($s['nama_sekolah']) ?>" required><?= $G('nama_sekolah') ?></label>
    <label class="f"><span>Jenjang</span><select name="jenjang"><?php foreach (['SD', 'SMP', 'SMA', 'SMK'] as $j): ?><option <?= $s['jenjang'] === $j ? 'selected' : '' ?>><?= $j ?></option><?php endforeach; ?></select></label>
    <label class="f"><span>NPSN</span><input name="npsn" value="<?= e($s['npsn']) ?>" inputmode="numeric"><?= $G('npsn') ?></label>
    <label class="f"><span>Telepon</span><input name="telepon" value="<?= e($s['telepon']) ?>"></label>
    <label class="f"><span>Email</span><input name="email" value="<?= e($s['email']) ?>"><?= $G('email') ?></label>
  </div>
  <label class="f"><span>Alamat</span><input name="alamat" value="<?= e($s['alamat']) ?>"></label>
  <div class="grid g3">
    <label class="f"><span>Kelurahan / desa</span><input name="kelurahan" value="<?= e($s['kelurahan']) ?>"></label>
    <label class="f"><span>Kecamatan</span><input name="kecamatan" value="<?= e($s['kecamatan']) ?>"></label>
    <label class="f"><span>Kabupaten / kota</span><input name="kabupaten" value="<?= e($s['kabupaten']) ?>"></label>
    <label class="f"><span>Provinsi</span><input name="provinsi" value="<?= e($s['provinsi']) ?>"></label>
    <label class="f"><span>Kode pos</span><input name="kode_pos" value="<?= e($s['kode_pos']) ?>"></label>
    <label class="f"><span>Website</span><input name="website" value="<?= e($s['website']) ?>"></label>
  </div>
  <h3 class="mt">Penanda tangan & aturan nilai</h3>
  <div class="grid g3">
    <label class="f"><span>Kepala sekolah</span><input name="kepala_sekolah" value="<?= e($s['kepala_sekolah']) ?>"></label>
    <label class="f"><span>NIP kepala sekolah</span><input name="nip_kepsek" value="<?= e($s['nip_kepsek']) ?>"><?= $G('nip_kepsek') ?></label>
    <label class="f"><span>Tempat tanda tangan rapor</span><input name="tempat_rapor" value="<?= e($s['tempat_rapor']) ?>" placeholder="nama kota"></label>
    <label class="f"><span>KKTP default</span><input name="kktp" value="<?= (int) $s['kktp'] ?>" inputmode="numeric"><?= $G('kktp') ?><small>Batas TP dianggap tercapai. Bisa diubah per mapel oleh guru.</small></label>
    <label class="f"><span>Bobot rata-rata TP (%)</span><input name="bobot_tp" value="<?= (int) $s['bobot_tp'] ?>" inputmode="numeric"><?= $G('bobot_tp') ?><small>Sisanya untuk SAS. 50 = rata TP 50% + SAS 50%.</small></label>
  </div>
  <button class="btn"><?= icon('centang', 16) ?> Simpan pengaturan</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
