<?php
require_role('admin');
$id = (int) ($_GET['id'] ?? 0);
$s = $id ? q('SELECT * FROM siswa WHERE id = ?', [$id])->fetch() : null;
if ($id && !$s) { flash('Siswa tidak ditemukan.', 'error'); redirect('siswa'); }
$s = $_SESSION['form_siswa'] ?? $s ?: ['nis' => '', 'nisn' => '', 'nama' => '', 'jk' => 'L', 'tempat_lahir' => '', 'tgl_lahir' => '', 'agama' => 'Islam', 'nama_ayah' => '',
    'nama_ibu' => '', 'pekerjaan_ayah' => '', 'pekerjaan_ibu' => '', 'alamat' => '', 'telepon_ortu' => '', 'tgl_masuk' => date('Y') . '-07-14', 'status' => 'aktif'];
$err = $_SESSION['form_err'] ?? [];
unset($_SESSION['form_siswa'], $_SESSION['form_err']);
$G = fn($k) => isset($err[$k]) ? '<span class="galat">' . e($err[$k]) . '</span>' : '';
$title = $id ? 'Ubah data siswa' : 'Tambah siswa';
$kembali = [url('siswa'), 'Data siswa'];
require __DIR__ . '/../../includes/header.php';
?>
<form method="post" action="<?= url('siswa.aksi') ?>" class="kartu" style="max-width:900px">
  <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= $id ?>">
  <h3>Identitas siswa</h3>
  <div class="grid g3">
    <label class="f"><span>NIS</span><input name="nis" value="<?= e($s['nis']) ?>" required maxlength="20"><?= $G('nis') ?></label>
    <label class="f"><span>NISN</span><input name="nisn" value="<?= e($s['nisn']) ?>" required maxlength="10" inputmode="numeric"><?= $G('nisn') ?></label>
    <label class="f"><span>Status</span><select name="status"><?php foreach (['aktif' => 'Aktif', 'lulus' => 'Lulus', 'pindah' => 'Pindah / keluar'] as $k => $l): ?><option value="<?= $k ?>" <?= $s['status'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
  </div>
  <label class="f"><span>Nama lengkap</span><input name="nama" value="<?= e($s['nama']) ?>" required maxlength="100"><?= $G('nama') ?></label>
  <div class="grid g3">
    <label class="f"><span>Jenis kelamin</span><select name="jk"><option value="L" <?= $s['jk'] === 'L' ? 'selected' : '' ?>>Laki-laki</option><option value="P" <?= $s['jk'] === 'P' ? 'selected' : '' ?>>Perempuan</option></select></label>
    <label class="f"><span>Tempat lahir</span><input name="tempat_lahir" value="<?= e($s['tempat_lahir']) ?>" maxlength="60"></label>
    <label class="f"><span>Tanggal lahir</span><input type="date" name="tgl_lahir" value="<?= e($s['tgl_lahir']) ?>" required><?= $G('tgl_lahir') ?></label>
  </div>
  <div class="grid g3">
    <label class="f"><span>Agama</span><select name="agama"><?php foreach (['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'] as $a): ?><option <?= $s['agama'] === $a ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?></select><small>Menentukan mapel agama di rapor.</small></label>
    <label class="f"><span>Tanggal masuk</span><input type="date" name="tgl_masuk" value="<?= e($s['tgl_masuk']) ?>" required></label>
    <label class="f"><span>Telepon orang tua</span><input name="telepon_ortu" value="<?= e($s['telepon_ortu']) ?>" maxlength="20" inputmode="tel"><?= $G('telepon_ortu') ?></label>
  </div>
  <label class="f"><span>Alamat</span><input name="alamat" value="<?= e($s['alamat']) ?>" maxlength="200"></label>
  <h3 class="mt">Orang tua</h3>
  <div class="grid g2">
    <label class="f"><span>Nama ayah</span><input name="nama_ayah" value="<?= e($s['nama_ayah']) ?>" maxlength="100"></label>
    <label class="f"><span>Pekerjaan ayah</span><input name="pekerjaan_ayah" value="<?= e($s['pekerjaan_ayah']) ?>" maxlength="60"></label>
    <label class="f"><span>Nama ibu</span><input name="nama_ibu" value="<?= e($s['nama_ibu']) ?>" maxlength="100"></label>
    <label class="f"><span>Pekerjaan ibu</span><input name="pekerjaan_ibu" value="<?= e($s['pekerjaan_ibu']) ?>" maxlength="60"></label>
  </div>
  <div class="baris antara">
    <button class="btn"><?= icon('centang', 16) ?> Simpan</button>
  </div>
</form>
<?php if ($id): ?>
<form method="post" action="<?= url('siswa.aksi') ?>" class="mt" data-yakin="Hapus siswa ini beserta seluruh nilainya? Tindakan ini tidak bisa dibatalkan.">
  <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= $id ?>">
  <button class="tautan merah kecil">Hapus siswa ini (beserta nilainya)</button>
</form>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
