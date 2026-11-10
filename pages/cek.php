<?php
// Portal siswa & orang tua: lihat rapor periode aktif dengan NISN + tanggal lahir, setelah sekolah membagikan rapor
$s = setting();
$err = null;
$siswa = null;
$nisn = trim($_POST['nisn'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!batas_coba('cek_' . ($_SERVER['REMOTE_ADDR'] ?? ''), 8, 900)) {
        $err = 'Terlalu banyak percobaan. Coba lagi 15 menit lagi.';
    } else {
        $siswa = q('SELECT * FROM siswa WHERE nisn = ? AND tgl_lahir = ?', [$nisn, (string) ($_POST['tgl_lahir'] ?? '')])->fetch() ?: null;
        if (!$siswa) $err = 'Data tidak ditemukan. Periksa NISN dan tanggal lahir.';
        elseif (!$s['rapor_terbit']) { $err = 'Rapor semester ini belum dibagikan oleh sekolah.'; $siswa = null; }
        else {
            $ag = q('SELECT rombel_id FROM anggota_rombel WHERE siswa_id = ? AND periode_id = ?', [$siswa['id'], $s['periode_id']])->fetch();
            if (!$ag) { $err = 'Siswa tidak terdaftar di kelas mana pun pada semester ini.'; $siswa = null; }
            else { $_SESSION['rapor_siswa'] = (int) $siswa['id']; reset_coba('cek_' . ($_SERVER['REMOTE_ADDR'] ?? '')); }
        }
    }
}
if (isset($_GET['lihat'], $_SESSION['rapor_siswa']) && $s['rapor_terbit']) {
    // Tampilkan rapor lewat komponen yang sama dengan cetak rapor; hanya siswa yang lolos verifikasi di sesi ini
    $cetakSiswa = [(int) $_SESSION['rapor_siswa']];
    $modePublik = true;
    require __DIR__ . '/rapor.php';
    exit;
}
$title = 'Lihat rapor';
require __DIR__ . '/../includes/pub_header.php';
?>
<div class="pub-kartu">
  <div class="kepala-pub">
    <div class="kecil" style="color:#b8d3d8;letter-spacing:.08em;text-transform:uppercase;font-weight:700"><?= e($s['nama_sekolah']) ?></div>
    <h1>Lihat rapor siswa</h1>
  </div>
  <div class="badan">
    <?php if ($err): ?><div class="pesan error"><?= e($err) ?></div><?php endif; ?>
    <?php if ($siswa): ?>
      <div class="pesan ok">Data ditemukan: <?= e($siswa['nama']) ?></div>
      <a class="btn blok" href="<?= url('cek', ['lihat' => 1]) ?>"><?= icon('mata', 17) ?> Buka rapor <?= e(label_periode(periode_aktif())) ?></a>
    <?php else: ?>
      <form method="post">
        <?= csrf_field() ?>
        <label class="f"><span>NISN</span><input name="nisn" value="<?= e($nisn) ?>" inputmode="numeric" maxlength="10" required placeholder="10 digit"></label>
        <label class="f"><span>Tanggal lahir</span><input name="tgl_lahir" type="date" required></label>
        <button class="btn blok"><?= icon('cari', 17) ?> Cari rapor</button>
      </form>
    <?php endif; ?>
    <p class="kecil muted tengah" style="margin:16px 0 0"><a href="<?= url('login') ?>">Masuk sebagai guru / operator</a></p>
  </div>
</div>
</body>
</html>
