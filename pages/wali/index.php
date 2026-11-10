<?php
$per = periode_aktif();
$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    if (!lihat_semua() && count(rombel_wali()) === 1) redirect('wali', ['id' => rombel_wali()[0]['id']]);
    $daftar = lihat_semua() ? q('SELECT r.*, u.nama wali FROM rombel r LEFT JOIN pengguna u ON u.id = r.wali_id WHERE r.periode_id = ? ORDER BY tingkat, nama', [$per['id']])->fetchAll() : rombel_wali();
    $title = 'Wali kelas';
    $sub = 'Pilih kelas untuk mengisi kehadiran, catatan, ekstrakurikuler, dan mencetak rapor';
    require __DIR__ . '/../../includes/header.php';
    echo '<div class="grid g3">';
    foreach ($daftar as $r) {
        echo '<a class="kartu" style="text-decoration:none;color:inherit;border-top:4px solid var(--tosca)" href="' . url('wali', ['id' => $r['id']]) . '"><h3>' . e($r['nama']) . '</h3><div class="kecil muted">Wali: ' . e($r['wali'] ?? user()['nama']) . '</div></a>';
    }
    echo '</div>';
    if (!$daftar) echo '<div class="kosong kartu">Anda bukan wali kelas di semester ini.</div>';
    require __DIR__ . '/../../includes/footer.php';
    exit;
}
$r = q('SELECT r.*, u.nama wali FROM rombel r LEFT JOIN pengguna u ON u.id = r.wali_id WHERE r.id = ?', [$id])->fetch();
if (!$r || !boleh_rombel($id)) { flash('Kelas tidak ditemukan atau bukan kelas Anda.', 'error'); redirect('dashboard'); }
$bolehUbah = !nilai_terkunci() && (is_admin() || (int) $r['wali_id'] === (int) user()['id']);
$tab = in_array($_GET['tab'] ?? '', ['ekskul', 'rapor'], true) ? $_GET['tab'] : 'kehadiran';
$siswa = siswa_rombel($id);
$rapor = array_column(q('SELECT * FROM rapor WHERE periode_id = ?', [$per['id']])->fetchAll(), null, 'siswa_id');
$ekskul = q('SELECT * FROM ekskul ORDER BY wajib DESC, nama')->fetchAll();
$nek = [];
foreach (q('SELECT n.*, e.nama FROM nilai_ekskul n JOIN ekskul e ON e.id = n.ekskul_id WHERE n.periode_id = ?', [$per['id']])->fetchAll() as $n) $nek[$n['siswa_id']][$n['ekskul_id']] = $n;
$genap = (int) $per['semester'] === 2;
$akhir = $genap && (int) $r['tingkat'] === (setting()['jenjang'] === 'SD' ? 6 : (in_array(setting()['jenjang'], ['SMA', 'SMK'], true) ? 12 : 9));

$title = 'Kelas ' . $r['nama'];
$sub = 'Wali kelas ' . e($r['wali'] ?: '-') . ' · ' . count($siswa) . ' siswa · ' . e(label_periode($per));
$kembali = lihat_semua() ? [url('wali'), 'Semua kelas'] : null;
$actions = '<a class="btn putih" href="' . url('leger', ['id' => $id]) . '">' . icon('grid', 16) . ' Leger nilai</a>'
    . '<a class="btn" target="_blank" href="' . url('rapor', ['rombel' => $id]) . '">' . icon('cetak', 16) . ' Cetak semua rapor</a>';
require __DIR__ . '/../../includes/header.php';
?>
<?php if (nilai_terkunci()): ?><div class="pesan error"><?= icon('kunci') ?> <span>Data rapor dikunci operator, tidak bisa diubah.</span></div><?php endif; ?>
<nav class="tab-nav">
  <a href="<?= url('wali', ['id' => $id]) ?>" class="<?= $tab === 'kehadiran' ? 'aktif' : '' ?>">Kehadiran & catatan<?= $genap ? ' & kenaikan' : '' ?></a>
  <a href="<?= url('wali', ['id' => $id, 'tab' => 'ekskul']) ?>" class="<?= $tab === 'ekskul' ? 'aktif' : '' ?>">Ekstrakurikuler</a>
  <a href="<?= url('wali', ['id' => $id, 'tab' => 'rapor']) ?>" class="<?= $tab === 'rapor' ? 'aktif' : '' ?>">Cetak rapor per siswa</a>
</nav>

<?php if ($tab === 'kehadiran'): ?>
<form method="post" action="<?= url('wali.aksi') ?>" class="kartu rata">
  <?= csrf_field() ?><input type="hidden" name="aksi" value="kehadiran"><input type="hidden" name="id" value="<?= $id ?>">
  <div class="kartu-kepala">
    <h3>Ketidakhadiran, catatan wali kelas<?= $genap ? ', keputusan' : '' ?></h3>
    <?php if ($bolehUbah): ?><span class="baris">
      <button type="button" class="btn putih kecil" data-isi-kolom=".isi-hadir" data-nilai="0">Isi 0 yang kosong</button>
      <button class="btn hijau"><?= icon('centang', 16) ?> Simpan</button></span><?php endif; ?>
  </div>
  <div class="tabel-wrap"><table class="tabel">
    <thead><tr><th>No</th><th>Nama</th><th class="tengah">Sakit</th><th class="tengah">Izin</th><th class="tengah">Tanpa ket.</th><th>Catatan wali kelas</th><?php if ($genap): ?><th><?= $akhir ? 'Kelulusan' : 'Kenaikan' ?></th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($siswa as $i => $s): $rp = $rapor[$s['id']] ?? null; ?>
      <tr>
        <td class="mono kecil"><?= $i + 1 ?></td>
        <td class="nowrap"><b><?= e($s['nama']) ?></b><div class="kecil muted mono"><?= e($s['nis']) ?></div></td>
        <?php foreach (['sakit', 'izin', 'alfa'] as $k): ?>
          <td class="tengah"><input class="input mono isi-hadir tengah" style="width:64px;padding:5px" name="<?= $k ?>[<?= $s['id'] ?>]" value="<?= e($rp[$k] ?? '') ?>" inputmode="numeric" maxlength="3" aria-label="<?= $k . ' ' . e($s['nama']) ?>" <?= $bolehUbah ? '' : 'readonly' ?>></td>
        <?php endforeach; ?>
        <td style="min-width:300px"><textarea class="input kecil" name="catatan[<?= $s['id'] ?>]" rows="2" maxlength="500" aria-label="Catatan <?= e($s['nama']) ?>" <?= $bolehUbah ? '' : 'readonly' ?>><?= e($rp['catatan'] ?? '') ?></textarea></td>
        <?php if ($genap): ?><td><select class="input" name="keputusan[<?= $s['id'] ?>]" <?= $bolehUbah ? '' : 'disabled' ?>>
          <option value="">-</option>
          <?php foreach ($akhir ? ['lulus' => 'Lulus', 'tidak_lulus' => 'Tidak lulus'] : ['naik' => 'Naik kelas', 'tidak_naik' => 'Tinggal kelas'] as $k => $l): ?><option value="<?= $k ?>" <?= ($rp['keputusan'] ?? '') === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
        </select></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</form>

<?php elseif ($tab === 'ekskul'):
  $eid = (int) ($_GET['ekskul'] ?? ($ekskul[0]['id'] ?? 0)); ?>
<div class="grid g21">
  <form method="post" action="<?= url('wali.aksi') ?>" class="kartu rata">
    <?= csrf_field() ?><input type="hidden" name="aksi" value="ekskul"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="ekskul" value="<?= $eid ?>">
    <div class="kartu-kepala">
      <div class="baris"><h3>Nilai ekstrakurikuler</h3>
        <select class="input" onchange="location.href='<?= url('wali', ['id' => $id, 'tab' => 'ekskul']) ?>&ekskul='+this.value" style="width:auto" aria-label="Pilih ekstrakurikuler">
          <?php foreach ($ekskul as $ek): ?><option value="<?= $ek['id'] ?>" <?= $eid === (int) $ek['id'] ? 'selected' : '' ?>><?= e($ek['nama']) ?><?= $ek['wajib'] ? ' (wajib)' : '' ?></option><?php endforeach; ?>
        </select></div>
      <?php if ($bolehUbah): ?><span class="baris"><button type="button" class="btn putih kecil" data-isi-kolom=".isi-pred" data-nilai="Baik">Isi "Baik" yang kosong</button><button class="btn hijau"><?= icon('centang', 16) ?> Simpan</button></span><?php endif; ?>
    </div>
    <div class="tabel-wrap"><table class="tabel">
      <thead><tr><th>Nama</th><th>Predikat</th><th>Keterangan</th></tr></thead>
      <tbody>
      <?php foreach ($siswa as $s): $n = $nek[$s['id']][$eid] ?? null; ?>
        <tr>
          <td class="nowrap"><b><?= e($s['nama']) ?></b></td>
          <td><select class="input isi-pred" name="predikat[<?= $s['id'] ?>]" aria-label="Predikat <?= e($s['nama']) ?>" <?= $bolehUbah ? '' : 'disabled' ?>>
            <option value="">Tidak ikut</option>
            <?php foreach (['Sangat Baik', 'Baik', 'Cukup', 'Kurang'] as $p): ?><option <?= ($n['predikat'] ?? '') === $p ? 'selected' : '' ?>><?= $p ?></option><?php endforeach; ?>
          </select></td>
          <td><input class="input" name="ket[<?= $s['id'] ?>]" value="<?= e($n['keterangan'] ?? '') ?>" maxlength="200" aria-label="Keterangan <?= e($s['nama']) ?>" <?= $bolehUbah ? '' : 'readonly' ?>></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </form>
  <div class="kartu" style="align-self:start">
    <h3>Rekap ekstrakurikuler</h3>
    <div class="daftar">
      <?php foreach ($ekskul as $ek): $jml = count(array_filter($siswa, fn($s) => isset($nek[$s['id']][$ek['id']]))); ?>
        <div><span class="grow"><?= e($ek['nama']) ?><div class="kecil muted"><?= e($ek['pembina']) ?></div></span><span class="mono"><?= $jml ?></span></div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php else: ?>
<div class="kartu rata">
  <div class="tabel-wrap"><table class="tabel">
    <thead><tr><th>No</th><th>Nama</th><th>NISN</th><th>Kelengkapan</th><th></th></tr></thead>
    <tbody>
    <?php $nilai = nilai_rombel($id);
    foreach ($siswa as $i => $s):
        $mp = 0; $ok = 0;
        foreach ($nilai as $pbn) { if (!isset($pbn['siswa'][$s['id']])) continue; $mp++; if ($pbn['siswa'][$s['id']]['lengkap']) $ok++; }
        $wl = isset($rapor[$s['id']]); ?>
      <tr>
        <td class="mono kecil"><?= $i + 1 ?></td>
        <td><b><?= e($s['nama']) ?></b></td>
        <td class="mono"><?= e($s['nisn']) ?></td>
        <td><span class="chip <?= $ok === $mp ? 'hijau' : 'pasir' ?>">Nilai <?= $ok ?>/<?= $mp ?></span> <span class="chip <?= $wl ? 'hijau' : 'koral' ?>"><?= $wl ? 'Kehadiran terisi' : 'Kehadiran kosong' ?></span></td>
        <td class="kanan"><a class="btn putih kecil" target="_blank" href="<?= url('rapor', ['siswa' => $s['id']]) ?>"><?= icon('cetak', 15) ?> Rapor</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
