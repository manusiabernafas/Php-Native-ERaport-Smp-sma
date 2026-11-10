<?php
require_role('admin');
$id = (int) ($_GET['id'] ?? 0);
$r = q('SELECT * FROM rombel WHERE id = ?', [$id])->fetch();
if (!$r) { flash('Rombel tidak ditemukan.', 'error'); redirect('rombel'); }
$guru = q("SELECT id, nama FROM pengguna WHERE aktif = 1 AND role IN ('guru','kepsek') ORDER BY nama")->fetchAll();
$anggota = siswa_rombel($id);
$tanpa = q("SELECT s.id, s.nis, s.nama, s.jk FROM siswa s WHERE s.status = 'aktif'
            AND NOT EXISTS (SELECT 1 FROM anggota_rombel a WHERE a.siswa_id = s.id AND a.periode_id = ?) ORDER BY s.nama", [$r['periode_id']])->fetchAll();
$rombelLain = q('SELECT id, nama FROM rombel WHERE periode_id = ? AND id <> ? ORDER BY tingkat, nama', [$r['periode_id'], $id])->fetchAll();
$mapel = q('SELECT m.*, p.id pid, p.guru_id, (SELECT COUNT(*) FROM tp WHERE tp.pembelajaran_id = p.id) jml_tp
            FROM mapel m LEFT JOIN pembelajaran p ON p.mapel_id = m.id AND p.rombel_id = ? ORDER BY m.kelompok = "mulok", m.urutan', [$id])->fetchAll();
$tab = ($_GET['tab'] ?? '') === 'mapel' ? 'mapel' : 'anggota';
$title = 'Rombel ' . $r['nama'];
$sub = 'Tingkat ' . (int) $r['tingkat'] . ' · fase ' . e($r['fase']) . ' · ' . count($anggota) . ' siswa';
$kembali = [url('rombel'), 'Semua rombel'];
require __DIR__ . '/../../includes/header.php';
$opsiGuru = function ($pilih) use ($guru) { $o = ''; foreach ($guru as $g) $o .= '<option value="' . $g['id'] . '"' . ((int) $pilih === (int) $g['id'] ? ' selected' : '') . '>' . e($g['nama']) . '</option>'; return $o; };
?>
<form method="post" action="<?= url('rombel.aksi') ?>" class="kartu baris" style="margin-bottom:16px">
  <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= $id ?>">
  <label class="f m0"><span>Nama</span><input name="nama" value="<?= e($r['nama']) ?>" required maxlength="20" style="width:120px"></label>
  <label class="f m0"><span>Tingkat</span><input name="tingkat" value="<?= (int) $r['tingkat'] ?>" required style="width:80px"></label>
  <label class="f m0"><span>Fase</span><select name="fase" style="width:80px"><?php foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $f): ?><option <?= $f === $r['fase'] ? 'selected' : '' ?>><?= $f ?></option><?php endforeach; ?></select></label>
  <label class="f m0 grow"><span>Wali kelas</span><select name="wali_id"><option value="">- belum ada -</option><?= $opsiGuru($r['wali_id']) ?></select></label>
  <button class="btn putih" style="align-self:flex-end">Simpan</button>
</form>
<nav class="tab-nav">
  <a href="<?= url('rombel.detail', ['id' => $id]) ?>" class="<?= $tab === 'anggota' ? 'aktif' : '' ?>">Anggota (<?= count($anggota) ?>)</a>
  <a href="<?= url('rombel.detail', ['id' => $id, 'tab' => 'mapel']) ?>" class="<?= $tab === 'mapel' ? 'aktif' : '' ?>">Mata pelajaran & guru</a>
</nav>

<?php if ($tab === 'anggota'): ?>
<div class="grid g2">
  <form method="post" action="<?= url('rombel.aksi') ?>" class="kartu rata">
    <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
    <div class="kartu-kepala"><h3>Anggota kelas</h3>
      <span class="baris"><select class="input" name="tujuan" style="width:auto" aria-label="Rombel tujuan"><?php foreach ($rombelLain as $x): ?><option value="<?= $x['id'] ?>"><?= e($x['nama']) ?></option><?php endforeach; ?></select>
      <button class="btn putih kecil" name="aksi" value="pindah">Pindahkan</button><button class="btn putih kecil" name="aksi" value="keluarkan">Keluarkan</button></span></div>
    <div class="tabel-wrap"><table class="tabel">
      <tbody>
      <?php foreach ($anggota as $i => $s): ?>
        <tr><td style="width:30px"><input type="checkbox" name="siswa[]" value="<?= $s['id'] ?>" aria-label="Pilih <?= e($s['nama']) ?>"></td><td class="mono kecil"><?= $i + 1 ?></td><td><b><?= e($s['nama']) ?></b> <span class="kecil muted mono"><?= e($s['nis']) ?></span></td><td><?= $s['jk'] ?></td><td class="kecil"><?= e($s['agama']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table><?php if (!$anggota): ?><div class="kosong">Belum ada anggota.</div><?php endif; ?></div>
  </form>
  <form method="post" action="<?= url('rombel.aksi') ?>" class="kartu rata" style="align-self:start">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="tambah_anggota"><input type="hidden" name="id" value="<?= $id ?>">
    <div class="kartu-kepala"><h3>Siswa belum punya kelas (<?= count($tanpa) ?>)</h3><button class="btn kecil"><?= icon('plus', 15) ?> Masukkan</button></div>
    <div class="tabel-wrap" style="max-height:520px;overflow:auto"><table class="tabel"><tbody>
      <?php foreach ($tanpa as $s): ?><tr><td style="width:30px"><input type="checkbox" name="siswa[]" value="<?= $s['id'] ?>" aria-label="Pilih <?= e($s['nama']) ?>"></td><td><?= e($s['nama']) ?> <span class="kecil muted mono"><?= e($s['nis']) ?></span></td><td><?= $s['jk'] ?></td></tr><?php endforeach; ?>
    </tbody></table><?php if (!$tanpa): ?><div class="kosong">Semua siswa aktif sudah punya kelas.</div><?php endif; ?></div>
  </form>
</div>
<?php else: ?>
<form method="post" action="<?= url('rombel.aksi') ?>" class="kartu rata">
  <?= csrf_field() ?><input type="hidden" name="aksi" value="pembelajaran"><input type="hidden" name="id" value="<?= $id ?>">
  <div class="kartu-kepala"><h3>Guru pengampu tiap mata pelajaran</h3><button class="btn hijau"><?= icon('centang', 16) ?> Simpan</button></div>
  <div class="tabel-wrap"><table class="tabel">
    <thead><tr><th>Mata pelajaran</th><th>Kelompok</th><th>Guru pengampu</th><th class="tengah">TP</th></tr></thead>
    <tbody>
    <?php foreach ($mapel as $m): ?>
      <tr>
        <td><b><?= e($m['nama']) ?></b><?= $m['agama'] ? ' <span class="chip tosca">' . e($m['agama']) . '</span>' : '' ?></td>
        <td class="kecil"><?= $m['kelompok'] === 'mulok' ? 'Muatan lokal' : 'Umum' ?></td>
        <td><select class="input" name="guru[<?= $m['id'] ?>]" aria-label="Guru <?= e($m['nama']) ?>">
          <option value="">— tidak diajarkan di kelas ini —</option>
          <option value="0" <?= $m['pid'] && !$m['guru_id'] ? 'selected' : '' ?>>Diajarkan, guru belum ditentukan</option>
          <?= $opsiGuru($m['guru_id']) ?></select></td>
        <td class="tengah mono"><?= $m['pid'] ? (int) $m['jml_tp'] : '–' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</form>
<?php endif; ?>
<?php if (!$anggota): ?>
<form method="post" action="<?= url('rombel.aksi') ?>" class="mt" data-yakin="Hapus rombel ini beserta pembagian mapel & nilainya?">
  <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= $id ?>"><button class="tautan merah kecil">Hapus rombel ini</button>
</form>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
