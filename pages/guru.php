<?php
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if (($_POST['aksi'] ?? '') === 'aktif') {
        if ($id === (int) user()['id']) { flash('Anda tidak bisa menonaktifkan akun sendiri.', 'error'); redirect('guru'); }
        q('UPDATE pengguna SET aktif = 1 - aktif WHERE id = ?', [$id]);
        flash('Status akun diperbarui.');
        redirect('guru');
    }
    $nama = mb_substr(trim($_POST['nama'] ?? ''), 0, 100);
    $nip = preg_replace('/\s+/', '', (string) ($_POST['nip'] ?? ''));
    $username = strtolower(trim($_POST['username'] ?? ''));
    $role = in_array($_POST['role'] ?? '', ['admin', 'guru', 'kepsek'], true) ? $_POST['role'] : 'guru';
    $sandi = (string) ($_POST['password'] ?? '');
    $e = null;
    if (mb_strlen($nama) < 3) $e = 'Isi nama lengkap beserta gelar.';
    elseif ($nip !== '' && !preg_match('/^\d{8,25}$/', $nip)) $e = 'NIP hanya angka (8-25 digit).';
    elseif (!preg_match('/^[a-z0-9._]{3,40}$/', $username)) $e = 'Username 3-40 karakter: huruf kecil, angka, titik, garis bawah.';
    elseif (q('SELECT 1 FROM pengguna WHERE username = ? AND id <> ?', [$username, $id])->fetchColumn()) $e = 'Username sudah dipakai.';
    elseif ((!$id || $sandi !== '') && strlen($sandi) < 8) $e = 'Kata sandi minimal 8 karakter.';
    elseif ($id === (int) user()['id'] && $role !== 'admin') $e = 'Anda tidak bisa menurunkan peran akun sendiri.';
    if ($e) { flash($e, 'error'); redirect('guru'); }
    if ($id) {
        q('UPDATE pengguna SET nama = ?, nip = ?, username = ?, role = ? WHERE id = ?', [$nama, $nip, $username, $role, $id]);
        if ($sandi !== '') q('UPDATE pengguna SET password = ? WHERE id = ?', [password_hash($sandi, PASSWORD_DEFAULT), $id]);
        flash('Data pengguna diperbarui.');
    } else {
        q('INSERT INTO pengguna (nama, nip, username, password, role, aktif, created_at) VALUES (?,?,?,?,?,1,NOW())', [$nama, $nip, $username, password_hash($sandi, PASSWORD_DEFAULT), $role]);
        flash("Pengguna $username ditambahkan.");
    }
    redirect('guru');
}
$data = q('SELECT p.*, (SELECT COUNT(*) FROM pembelajaran pb JOIN rombel r ON r.id = pb.rombel_id WHERE pb.guru_id = p.id AND r.periode_id = ?) mengajar,
             (SELECT GROUP_CONCAT(r.nama) FROM rombel r WHERE r.wali_id = p.id AND r.periode_id = ?) wali
           FROM pengguna p ORDER BY p.aktif DESC, FIELD(p.role, "admin", "kepsek", "guru"), p.nama', [periode_aktif()['id'], periode_aktif()['id']])->fetchAll();
$edit = isset($_GET['id']) ? q('SELECT * FROM pengguna WHERE id = ?', [(int) $_GET['id']])->fetch() : null;
$title = 'Pengaturan';
$sub = 'Akun guru, kepala sekolah, dan operator';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/subnav.php';
?>
<div class="grid g21">
  <div class="kartu rata">
    <div class="tabel-wrap"><table class="tabel">
      <thead><tr><th>Nama</th><th>Username</th><th>Peran</th><th class="tengah">Mengajar</th><th>Wali</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($data as $p): ?>
        <tr style="<?= $p['aktif'] ? '' : 'opacity:.55' ?>">
          <td><b><?= e($p['nama']) ?></b><?php if ($p['nip']): ?><div class="kecil muted mono">NIP <?= e($p['nip']) ?></div><?php endif; ?></td>
          <td class="mono"><?= e($p['username']) ?></td>
          <td><span class="chip <?= $p['role'] === 'admin' ? 'koral' : ($p['role'] === 'kepsek' ? 'tosca' : 'abu') ?>"><?= e(LABEL_ROLE[$p['role']]) ?></span><?= $p['aktif'] ? '' : ' <span class="chip abu">nonaktif</span>' ?></td>
          <td class="tengah mono"><?= (int) $p['mengajar'] ?></td>
          <td><?= e($p['wali'] ?? '') ?></td>
          <td class="kanan nowrap"><a class="btn putih kecil" href="<?= url('guru', ['id' => $p['id']]) ?>">Ubah</a>
            <?php if ((int) $p['id'] !== (int) user()['id']): ?><form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="aksi" value="aktif"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="tautan kecil <?= $p['aktif'] ? 'merah' : '' ?>"><?= $p['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <form method="post" class="kartu" style="align-self:start">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <h3><?= $edit ? 'Ubah ' . e($edit['username']) : 'Tambah pengguna' ?></h3>
    <label class="f"><span>Nama lengkap & gelar</span><input name="nama" value="<?= e($edit['nama'] ?? '') ?>" required></label>
    <label class="f"><span>NIP (opsional)</span><input name="nip" value="<?= e($edit['nip'] ?? '') ?>" inputmode="numeric"></label>
    <div class="grid g2">
      <label class="f"><span>Username</span><input name="username" value="<?= e($edit['username'] ?? '') ?>" required autocomplete="off"></label>
      <label class="f"><span>Peran</span><select name="role"><?php foreach (LABEL_ROLE as $k => $l): ?><option value="<?= $k ?>" <?= ($edit['role'] ?? 'guru') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></label>
    </div>
    <label class="f"><span><?= $edit ? 'Kata sandi baru (kosongkan bila tetap)' : 'Kata sandi awal' ?></span><input name="password" type="password" autocomplete="new-password" <?= $edit ? '' : 'required' ?> minlength="8"></label>
    <div class="baris"><button class="btn"><?= icon('centang', 16) ?> Simpan</button><?php if ($edit): ?><a href="<?= url('guru') ?>" class="kecil">Batal</a><?php endif; ?></div>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
