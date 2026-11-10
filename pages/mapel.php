<?php
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['aksi'] ?? '') === 'hapus') {
        if (q('SELECT 1 FROM pembelajaran pb JOIN tp ON tp.pembelajaran_id = pb.id WHERE pb.mapel_id = ?', [$id])->fetchColumn()) flash('Mapel sudah dipakai dan punya TP/nilai, tidak bisa dihapus.', 'error');
        else { q('DELETE FROM mapel WHERE id = ?', [$id]); flash('Mata pelajaran dihapus.'); }
        redirect('mapel');
    }
    $kode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['kode'] ?? '')));
    $nama = mb_substr(trim($_POST['nama'] ?? ''), 0, 100);
    $kel = ($_POST['kelompok'] ?? '') === 'mulok' ? 'mulok' : 'umum';
    $agama = in_array($_POST['agama'] ?? '', ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'], true) ? $_POST['agama'] : null;
    $urutan = max(0, min(99, (int) ($_POST['urutan'] ?? 0)));
    if (strlen($kode) < 2 || strlen($kode) > 12 || mb_strlen($nama) < 3) flash('Isi kode (2-12 huruf/angka) dan nama mapel.', 'error');
    elseif (q('SELECT 1 FROM mapel WHERE kode = ? AND id <> ?', [$kode, $id])->fetchColumn()) flash("Kode $kode sudah dipakai.", 'error');
    elseif ($id) { q('UPDATE mapel SET kode = ?, nama = ?, kelompok = ?, agama = ?, urutan = ? WHERE id = ?', [$kode, $nama, $kel, $agama, $urutan, $id]); flash('Mata pelajaran diperbarui.'); }
    else { q('INSERT INTO mapel (kode, nama, kelompok, agama, urutan) VALUES (?,?,?,?,?)', [$kode, $nama, $kel, $agama, $urutan]); flash('Mata pelajaran ditambahkan. Atur gurunya di menu Rombel.'); }
    redirect('mapel');
}
$data = q('SELECT m.*, (SELECT COUNT(*) FROM pembelajaran p WHERE p.mapel_id = m.id) dipakai FROM mapel m ORDER BY kelompok = "mulok", urutan, nama')->fetchAll();
$title = 'Pengaturan';
$sub = 'Mata pelajaran. Mapel agama hanya muncul di rapor siswa yang agamanya sama.';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/subnav.php';
$baris = function ($m = null) { ob_start(); ?>
  <form method="post" class="baris" style="flex-wrap:nowrap;gap:6px">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($m['id'] ?? 0) ?>">
    <input class="input mono" name="kode" value="<?= e($m['kode'] ?? '') ?>" placeholder="KODE" style="width:90px" required aria-label="Kode">
    <input class="input" name="nama" value="<?= e($m['nama'] ?? '') ?>" placeholder="Nama mata pelajaran" required aria-label="Nama">
    <select class="input" name="kelompok" style="width:130px" aria-label="Kelompok"><option value="umum">Umum</option><option value="mulok" <?= ($m['kelompok'] ?? '') === 'mulok' ? 'selected' : '' ?>>Muatan lokal</option></select>
    <select class="input" name="agama" style="width:120px" aria-label="Khusus agama"><option value="">Semua agama</option><?php foreach (['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'] as $a): ?><option <?= ($m['agama'] ?? '') === $a ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?></select>
    <input class="input mono" name="urutan" value="<?= (int) ($m['urutan'] ?? 0) ?>" style="width:60px" aria-label="Urutan" title="Urutan di rapor">
    <button class="btn <?= $m ? 'putih' : '' ?> kecil"><?= $m ? 'Simpan' : 'Tambah' ?></button>
  </form>
<?php return ob_get_clean(); };
?>
<div class="kartu rata">
  <div class="tabel-wrap"><table class="tabel"><tbody>
    <?php foreach ($data as $m): ?>
      <tr><td><?= $baris($m) ?></td><td class="kecil muted nowrap"><?= (int) $m['dipakai'] ?> kelas</td>
        <td><?php if (!$m['dipakai']): ?><form method="post" data-yakin="Hapus <?= e($m['nama']) ?>?"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= $m['id'] ?>"><button class="tautan merah" title="Hapus"><?= icon('sampah', 16) ?></button></form><?php endif; ?></td></tr>
    <?php endforeach; ?>
    <tr><td colspan="3" style="background:#fbfbf8"><?= $baris() ?></td></tr>
  </tbody></table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
