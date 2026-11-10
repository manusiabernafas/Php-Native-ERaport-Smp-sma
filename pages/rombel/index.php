<?php
require_role('admin');
$per = periode_aktif();
$data = q('SELECT r.*, u.nama wali, (SELECT COUNT(*) FROM anggota_rombel a WHERE a.rombel_id = r.id) siswa,
             (SELECT COUNT(*) FROM pembelajaran p WHERE p.rombel_id = r.id) mapel,
             (SELECT COUNT(*) FROM pembelajaran p WHERE p.rombel_id = r.id AND p.guru_id IS NULL) tanpa_guru
           FROM rombel r LEFT JOIN pengguna u ON u.id = r.wali_id WHERE r.periode_id = ? ORDER BY r.tingkat, r.nama', [$per['id']])->fetchAll();
$guru = q("SELECT id, nama FROM pengguna WHERE aktif = 1 AND role IN ('guru','kepsek') ORDER BY nama")->fetchAll();
$title = 'Rombongan belajar';
$sub = e(label_periode($per)) . ' · atur anggota kelas, wali kelas, dan guru tiap mata pelajaran';
require __DIR__ . '/../../includes/header.php';
?>
<div class="grid g21">
  <div class="kartu rata">
    <div class="tabel-wrap"><table class="tabel">
      <thead><tr><th>Rombel</th><th>Tingkat / fase</th><th>Wali kelas</th><th class="tengah">Siswa</th><th class="tengah">Mapel</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($data as $r): ?>
        <tr>
          <td><a class="nama" href="<?= url('rombel.detail', ['id' => $r['id']]) ?>"><?= e($r['nama']) ?></a></td>
          <td><?= (int) $r['tingkat'] ?> / <?= e($r['fase']) ?></td>
          <td class="kecil"><?= $r['wali'] ? e($r['wali']) : '<span class="chip koral">belum ada</span>' ?></td>
          <td class="tengah mono"><?= (int) $r['siswa'] ?></td>
          <td class="tengah mono"><?= (int) $r['mapel'] ?><?= $r['tanpa_guru'] ? ' <span class="chip pasir" title="Mapel tanpa guru">' . (int) $r['tanpa_guru'] . '</span>' : '' ?></td>
          <td class="kanan"><a class="btn putih kecil" href="<?= url('rombel.detail', ['id' => $r['id']]) ?>">Kelola</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table><?php if (!$data): ?><div class="kosong">Belum ada rombel di semester ini. Tambahkan di samping, atau salin dari semester lalu di menu Periode.</div><?php endif; ?></div>
  </div>
  <form method="post" action="<?= url('rombel.aksi') ?>" class="kartu" style="align-self:start">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan">
    <h3>Tambah rombel</h3>
    <div class="grid g2">
      <label class="f"><span>Nama</span><input name="nama" required maxlength="20" placeholder="VII C"></label>
      <label class="f"><span>Tingkat</span><input name="tingkat" required inputmode="numeric" maxlength="2" placeholder="7"></label>
    </div>
    <label class="f"><span>Fase</span><select name="fase"><?php foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $f): ?><option <?= $f === 'D' ? 'selected' : '' ?>><?= $f ?></option><?php endforeach; ?></select></label>
    <label class="f"><span>Wali kelas</span><select name="wali_id"><option value="">- pilih -</option><?php foreach ($guru as $g): ?><option value="<?= $g['id'] ?>"><?= e($g['nama']) ?></option><?php endforeach; ?></select></label>
    <label class="cek mt2"><input type="checkbox" name="semua_mapel" value="1" checked> Buat pembelajaran untuk semua mapel</label>
    <button class="btn blok mt"><?= icon('plus', 16) ?> Tambah rombel</button>
  </form>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
