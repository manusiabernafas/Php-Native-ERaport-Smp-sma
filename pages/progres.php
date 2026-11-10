<?php
require_role('admin', 'kepsek');
$data = progres_pembelajaran();
$guru = [];
foreach ($data as $r) {
    $g = $r['guru'] ?: 'Belum ada guru';
    $guru[$g]['p'][] = persen_progres($r);
    $guru[$g]['n'] = ($guru[$g]['n'] ?? 0) + 1;
}
uasort($guru, fn($a, $b) => array_sum($a['p']) / count($a['p']) <=> array_sum($b['p']) / count($b['p']));
$f = $_GET['f'] ?? '';
if ($f === 'belum') $data = array_filter($data, fn($r) => persen_progres($r) < 100);
$kelasBar = fn($p) => $p >= 100 ? '' : ($p >= 60 ? 'sedang' : 'kurang');
$title = 'Progres pengisian nilai';
$sub = e(label_periode(periode_aktif()));
require __DIR__ . '/../includes/header.php';
?>
<div class="grid g21">
  <div class="kartu rata">
    <div class="kartu-kepala"><h3>Per mata pelajaran & kelas</h3>
      <nav class="baris kecil"><a href="<?= url('progres') ?>" class="tebal" <?= $f ? '' : 'style="color:var(--koral)"' ?>>Semua</a><a href="<?= url('progres', ['f' => 'belum']) ?>" class="tebal" <?= $f ? 'style="color:var(--koral)"' : '' ?>>Belum lengkap</a></nav></div>
    <div class="tabel-wrap"><table class="tabel">
      <thead><tr><th>Kelas</th><th>Mapel</th><th>Guru</th><th class="tengah">TP</th><th style="width:170px">Kelengkapan</th></tr></thead>
      <tbody>
      <?php foreach ($data as $r): $p = persen_progres($r); ?>
        <tr><td class="tebal"><?= e($r['rombel']) ?></td><td><a class="nama" href="<?= url('nilai.input', ['id' => $r['id']]) ?>"><?= e($r['mapel']) ?></a></td><td class="kecil"><?= e($r['guru'] ?: '-') ?></td><td class="tengah mono"><?= (int) $r['jml_tp'] ?></td>
          <td><div class="baris" style="flex-wrap:nowrap"><div class="bar grow <?= $kelasBar($p) ?>"><i style="width:<?= $p ?>%"></i></div><span class="mono kecil"><?= $p ?>%</span></div></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table><?php if (!$data): ?><div class="kosong">Semua nilai sudah lengkap.</div><?php endif; ?></div>
  </div>
  <div class="kartu" style="align-self:start">
    <h3>Per guru</h3>
    <div class="daftar">
      <?php foreach ($guru as $g => $x): $p = (int) round(array_sum($x['p']) / count($x['p'])); ?>
        <div><span class="grow kecil"><b><?= e($g) ?></b><div class="muted"><?= $x['n'] ?> mapel-kelas</div></span><span class="chip <?= $p >= 100 ? 'hijau' : ($p >= 60 ? 'pasir' : 'koral') ?>"><?= $p ?>%</span></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
