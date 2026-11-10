<?php
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['aksi'] ?? '') === 'hapus') {
        q('DELETE FROM ekskul WHERE id = ?', [$id]);
        flash('Ekstrakurikuler dihapus beserta nilainya.');
        redirect('ekskul');
    }
    $nama = mb_substr(trim($_POST['nama'] ?? ''), 0, 60);
    $pembina = mb_substr(trim($_POST['pembina'] ?? ''), 0, 100);
    $wajib = !empty($_POST['wajib']) ? 1 : 0;
    if (mb_strlen($nama) < 3) flash('Isi nama ekstrakurikuler.', 'error');
    elseif ($id) { q('UPDATE ekskul SET nama = ?, pembina = ?, wajib = ? WHERE id = ?', [$nama, $pembina, $wajib, $id]); flash('Ekstrakurikuler diperbarui.'); }
    else { q('INSERT INTO ekskul (nama, pembina, wajib) VALUES (?,?,?)', [$nama, $pembina, $wajib]); flash('Ekstrakurikuler ditambahkan.'); }
    redirect('ekskul');
}
$data = q('SELECT e.*, (SELECT COUNT(*) FROM nilai_ekskul n WHERE n.ekskul_id = e.id AND n.periode_id = ?) peserta FROM ekskul e ORDER BY wajib DESC, nama', [periode_aktif()['id']])->fetchAll();
$title = 'Pengaturan';
$sub = 'Ekstrakurikuler. Nilainya diisi wali kelas di menu Kelas saya.';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/subnav.php';
$f = function ($x = null) { ob_start(); ?>
  <form method="post" class="baris" style="flex-wrap:nowrap;gap:6px">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($x['id'] ?? 0) ?>">
    <input class="input" name="nama" value="<?= e($x['nama'] ?? '') ?>" placeholder="Nama ekstrakurikuler" required aria-label="Nama">
    <input class="input" name="pembina" value="<?= e($x['pembina'] ?? '') ?>" placeholder="Pembina" aria-label="Pembina">
    <label class="cek kecil nowrap"><input type="checkbox" name="wajib" value="1" <?= !empty($x['wajib']) ? 'checked' : '' ?>> wajib</label>
    <button class="btn <?= $x ? 'putih' : '' ?> kecil"><?= $x ? 'Simpan' : 'Tambah' ?></button>
  </form>
<?php return ob_get_clean(); };
?>
<div class="kartu rata" style="max-width:900px">
  <div class="tabel-wrap"><table class="tabel"><tbody>
    <?php foreach ($data as $x): ?>
      <tr><td><?= $f($x) ?></td><td class="kecil muted nowrap"><?= (int) $x['peserta'] ?> peserta</td>
        <td><form method="post" data-yakin="Hapus <?= e($x['nama']) ?> beserta nilainya?"><?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= $x['id'] ?>"><button class="tautan merah" title="Hapus"><?= icon('sampah', 16) ?></button></form></td></tr>
    <?php endforeach; ?>
    <tr><td colspan="3" style="background:#fbfbf8"><?= $f() ?></td></tr>
  </tbody></table></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
