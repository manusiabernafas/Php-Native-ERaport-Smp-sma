<?php $_sn = $_GET['page'] ?? 'pengaturan'; ?>
<nav class="tab-nav">
  <?php foreach (['pengaturan' => 'Sekolah & rapor', 'periode' => 'Periode', 'guru' => 'Pengguna', 'mapel' => 'Mata pelajaran', 'ekskul' => 'Ekstrakurikuler'] as $_k => $_l): ?>
    <a href="<?= url($_k) ?>" class="<?= $_sn === $_k ? 'aktif' : '' ?>"><?= $_l ?></a>
  <?php endforeach; ?>
</nav>
