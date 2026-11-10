<?php
$err = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = q('SELECT password FROM pengguna WHERE id = ?', [user()['id']])->fetch();
    $baru = (string) ($_POST['baru'] ?? '');
    if (!password_verify((string) ($_POST['lama'] ?? ''), $u['password'])) $err = 'Kata sandi lama salah.';
    elseif (strlen($baru) < 8 || !preg_match('/[A-Za-z]/', $baru) || !preg_match('/\d/', $baru)) $err = 'Kata sandi baru minimal 8 karakter berisi huruf dan angka.';
    elseif ($baru !== (string) ($_POST['ulang'] ?? '')) $err = 'Ulangi kata sandi baru dengan isian yang sama.';
    else {
        q('UPDATE pengguna SET password = ? WHERE id = ?', [password_hash($baru, PASSWORD_DEFAULT), user()['id']]);
        flash('Kata sandi berhasil diganti.');
        redirect('profil');
    }
}
$title = 'Profil';
$sub = e(user()['nama']) . ' · ' . e(LABEL_ROLE[role()]) . ' · username ' . e(user()['username']);
require __DIR__ . '/../includes/header.php';
?>
<form method="post" class="kartu" style="max-width:440px">
  <?= csrf_field() ?>
  <h3>Ganti kata sandi</h3>
  <?php if ($err): ?><div class="pesan error"><?= e($err) ?></div><?php endif; ?>
  <label class="f"><span>Kata sandi lama</span><input type="password" name="lama" required autocomplete="current-password"></label>
  <label class="f"><span>Kata sandi baru</span><input type="password" name="baru" required minlength="8" autocomplete="new-password"></label>
  <label class="f"><span>Ulangi kata sandi baru</span><input type="password" name="ulang" required autocomplete="new-password"></label>
  <button class="btn">Simpan</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
