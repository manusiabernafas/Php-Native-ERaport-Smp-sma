<?php
require_role('admin', 'guru');
$per = periode_aktif();
$rombel = q('SELECT id, nama FROM rombel WHERE periode_id = ? ORDER BY tingkat, nama', [$per['id']])->fetchAll();
$fr = (int) ($_GET['rombel'] ?? 0);
$data = progres_pembelajaran(is_admin() ? null : user()['id'], $fr ?: null);
$kelasBar = fn($p) => $p >= 100 ? '' : ($p >= 60 ? 'sedang' : 'kurang');
$title = is_admin() ? 'Nilai semua mata pelajaran' : 'Nilai saya';
$sub = 'Isi nilai per Tujuan Pembelajaran (TP) dan Sumatif Akhir Semester (SAS)';
require __DIR__ . '/../../includes/header.php';
?>
<?php if (nilai_terkunci()): ?><div class="pesan error"><?= icon('kunci') ?> <span>Nilai sedang dikunci operator. Anda masih bisa melihat, tetapi tidak bisa mengubah.</span></div><?php endif; ?>
<div class="kartu rata">
  <?php if (is_admin()): ?>
  <form class="kartu-kepala filter" data-auto>
    <input type="hidden" name="page" value="nilai">
    <select class="input" name="rombel"><option value="">Semua kelas</option><?php foreach ($rombel as $r): ?><option value="<?= $r['id'] ?>" <?= $fr === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option><?php endforeach; ?></select>
    <span class="kecil muted"><?= count($data) ?> mapel-kelas</span>
  </form>
  <?php endif; ?>
  <div class="tabel-wrap"><table class="tabel">
    <thead><tr><th>Kelas</th><th>Mata pelajaran</th><?php if (is_admin()): ?><th>Guru</th><?php endif; ?><th class="tengah">TP</th><th class="tengah">Siswa</th><th style="width:200px">Kelengkapan</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($data as $r): $p = persen_progres($r); ?>
      <tr>
        <td class="tebal"><?= e($r['rombel']) ?></td>
        <td><a class="nama" href="<?= url('nilai.input', ['id' => $r['id']]) ?>"><?= e($r['mapel']) ?></a></td>
        <?php if (is_admin()): ?><td class="kecil"><?= e($r['guru'] ?: '-') ?></td><?php endif; ?>
        <td class="tengah mono"><?= (int) $r['jml_tp'] ?></td>
        <td class="tengah mono"><?= (int) $r['jml_siswa'] ?></td>
        <td><div class="baris" style="flex-wrap:nowrap"><div class="bar grow <?= $kelasBar($p) ?>"><i style="width:<?= $p ?>%"></i></div><span class="mono kecil"><?= $p ?>%</span></div></td>
        <td class="kanan"><a class="btn putih kecil" href="<?= url('nilai.input', ['id' => $r['id']]) ?>"><?= icon('pena', 15) ?> Buka</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$data): ?><div class="kosong">Belum ada pembelajaran. <?= is_admin() ? 'Atur pembagian guru di menu Rombel.' : 'Hubungi operator sekolah.' ?></div><?php endif; ?></div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
