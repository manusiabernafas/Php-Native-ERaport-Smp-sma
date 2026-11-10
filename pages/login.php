<?php
if (user()) redirect('dashboard');
$err = null;
$username = strtolower(trim($_POST['username'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kunci = 'login_' . $username . '_' . ($_SERVER['REMOTE_ADDR'] ?? '');
    if (!batas_coba($kunci, 5, 600)) {
        $err = 'Terlalu banyak percobaan masuk. Coba lagi 10 menit lagi.';
    } else {
        $u = q('SELECT * FROM pengguna WHERE username = ?', [$username])->fetch();
        if (!$u || !password_verify((string) ($_POST['password'] ?? ''), $u['password'])) {
            $err = 'Username atau kata sandi salah.';
        } elseif (!$u['aktif']) {
            $err = 'Akun ini dinonaktifkan. Hubungi operator sekolah.';
        } else {
            reset_coba($kunci);
            session_regenerate_id(true);
            $_SESSION['user'] = ['id' => (int) $u['id'], 'nama' => $u['nama'], 'username' => $u['username'], 'role' => $u['role']];
            redirect('dashboard');
        }
    }
}
$title = 'Masuk';
require __DIR__ . '/../includes/pub_header.php';
?>
<div class="pub-kartu">
  <div class="kepala-pub">
    <div class="kecil" style="color:#b8d3d8;letter-spacing:.08em;text-transform:uppercase;font-weight:700"><?= e($_s['nama_sekolah']) ?></div>
    <h1>e-Rapor Kurikulum Merdeka</h1>
  </div>
  <form method="post" class="badan">
    <?= csrf_field() ?>
    <?php if ($_flash): ?><div class="pesan <?= e($_flash['type']) ?>"><?= e($_flash['msg']) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="pesan error"><?= e($err) ?></div><?php endif; ?>
    <label class="f"><span>Username</span><input name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus></label>
    <label class="f"><span>Kata sandi</span><input name="password" type="password" autocomplete="current-password" required></label>
    <button class="btn blok"><?= icon('kunci', 17) ?> Masuk</button>
    <p class="kecil muted tengah" style="margin:16px 0 0">Siswa / orang tua? <a href="<?= url('cek') ?>">Lihat rapor dengan NISN</a></p>
  </form>
</div>
</body>
</html>
