<?php
$_s = setting();
$_p = periode_aktif();
$_menu = [['dashboard', 'Beranda', 'home']];
if (role() === 'guru' || is_admin()) $_menu[] = ['nilai', is_admin() ? 'Nilai' : 'Nilai saya', 'pena'];
if (rombel_wali() || lihat_semua()) $_menu[] = ['wali', lihat_semua() ? 'Wali kelas' : 'Kelas saya', 'kelas'];
if (lihat_semua()) { $_menu[] = ['progres', 'Progres', 'grafik']; $_menu[] = ['leger', 'Leger', 'grid']; }
if (is_admin()) {
    $_menu[] = ['siswa', 'Siswa', 'users'];
    $_menu[] = ['rombel', 'Rombel', 'buku'];
    $_menu[] = ['pengaturan', 'Pengaturan', 'setel'];
}
$_cur = explode('.', $_GET['page'] ?? 'dashboard')[0];
if (in_array($_cur, ['guru', 'mapel', 'ekskul', 'periode'], true)) $_cur = 'pengaturan';
$_flash = flash();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title><?= e($title ?? 'Beranda') ?> · e-Rapor <?= e($_s['nama_sekolah']) ?></title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<header class="atas no-print">
  <div class="atas-baris">
    <a class="merek" href="<?= url('dashboard') ?>">
      <span class="logo">eR</span>
      <span><b>e-Rapor</b><small><?= e($_s['nama_sekolah']) ?></small></span>
    </a>
    <div class="periode-chip" title="Periode aktif"><?= icon('kalender', 15) ?> <?= e(label_periode($_p)) ?><?php if ($_s['kunci_nilai']): ?> · <span class="kunci"><?= icon('kunci', 13) ?> nilai dikunci</span><?php endif; ?></div>
    <div class="akun">
      <a href="<?= url('profil') ?>" class="akun-nama"><span class="av"><?= e(mb_strtoupper(mb_substr(user()['nama'], 0, 1))) ?></span><span><b><?= e(explode(',', user()['nama'])[0]) ?></b><small><?= e(LABEL_ROLE[role()] ?? role()) ?><?= rombel_wali() ? ' · Wali ' . e(implode(', ', array_column(rombel_wali(), 'nama'))) : '' ?></small></span></a>
      <a class="ibtn" href="<?= url('logout') ?>" title="Keluar" aria-label="Keluar"><?= icon('keluar') ?></a>
    </div>
  </div>
  <nav class="tab-atas" aria-label="Menu utama">
    <?php foreach ($_menu as [$_k, $_l, $_i]): ?>
      <a href="<?= url($_k) ?>" class="<?= $_cur === $_k ? 'aktif' : '' ?>"><?= icon($_i, 17) ?> <?= e($_l) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
<main class="isi">
  <div class="judul-hal">
    <div>
      <?php if (!empty($kembali)): ?><a class="kembali no-print" href="<?= e($kembali[0]) ?>"><?= icon('kiri', 15) ?> <?= e($kembali[1]) ?></a><?php endif; ?>
      <h1><?= e($title ?? 'Beranda') ?></h1>
      <?php if (!empty($sub)): ?><p><?= $sub ?></p><?php endif; ?>
    </div>
    <?php if (!empty($actions)): ?><div class="aksi no-print"><?= $actions ?></div><?php endif; ?>
  </div>
  <?php if ($_flash): ?><div class="pesan <?= e($_flash['type']) ?>"><?= icon($_flash['type'] === 'error' ? 'awas' : ($_flash['type'] === 'info' ? 'info' : 'centang')) ?> <span><?= e($_flash['msg']) ?></span></div><?php endif; ?>
