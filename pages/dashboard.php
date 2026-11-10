<?php
$s = setting();
$per = periode_aktif();
$u = user();
$mengajar = role() === 'guru' ? progres_pembelajaran($u['id']) : [];
$wali = rombel_wali();

if (lihat_semua()) {
    $prog = progres_pembelajaran();
    $jmlSiswa = (int) q('SELECT COUNT(*) FROM anggota_rombel WHERE periode_id = ?', [$per['id']])->fetchColumn();
    $jmlRombel = (int) q('SELECT COUNT(*) FROM rombel WHERE periode_id = ?', [$per['id']])->fetchColumn();
    $jmlGuru = (int) q("SELECT COUNT(*) FROM pengguna WHERE role = 'guru' AND aktif = 1")->fetchColumn();
    $totalPersen = $prog ? (int) round(array_sum(array_map('persen_progres', $prog)) / count($prog)) : 0;
    $selesai = count(array_filter($prog, fn($r) => persen_progres($r) === 100));
    // Ringkasan per rombel: rata-rata progres nilai + kelengkapan data wali kelas
    $perRombel = [];
    foreach ($prog as $r) {
        $perRombel[$r['rombel_id']]['nama'] = $r['rombel'];
        $perRombel[$r['rombel_id']]['p'][] = persen_progres($r);
    }
    $waliData = q('SELECT r.id, u.nama wali, (SELECT COUNT(*) FROM anggota_rombel a WHERE a.rombel_id = r.id) siswa,
                     (SELECT COUNT(*) FROM rapor rp JOIN anggota_rombel a ON a.siswa_id = rp.siswa_id AND a.rombel_id = r.id WHERE rp.periode_id = r.periode_id) rapor
                   FROM rombel r LEFT JOIN pengguna u ON u.id = r.wali_id WHERE r.periode_id = ?', [$per['id']])->fetchAll();
    $waliData = array_column($waliData, null, 'id');
    $tertinggal = array_filter($prog, fn($r) => persen_progres($r) < 100 && $r['jml_siswa'] > 0);
    usort($tertinggal, fn($a, $b) => persen_progres($a) <=> persen_progres($b));
}

$title = 'Halo, ' . explode(' ', str_replace(['Dra. ', 'Drs. ', 'Dr. '], '', $u['nama']))[0];
$sub = e(label_periode($per)) . ' · tanggal rapor ' . tgl($per['tgl_rapor']);
require __DIR__ . '/../includes/header.php';
$kelasBar = fn($p) => $p >= 100 ? '' : ($p >= 60 ? 'sedang' : 'kurang');
?>

<?php if ($s['rapor_terbit']): ?><div class="pesan ok"><?= icon('centang') ?> <span>Rapor semester ini sudah dibagikan. Siswa & orang tua bisa melihatnya di halaman <b>Lihat rapor</b> dengan NISN dan tanggal lahir.</span></div><?php endif; ?>

<?php if (lihat_semua()): ?>
  <div class="grid g4">
    <div class="angka-box"><div class="l">Siswa semester ini</div><div class="v"><?= $jmlSiswa ?></div><div class="s"><?= $jmlRombel ?> rombel · <?= $jmlGuru ?> guru</div></div>
    <div class="angka-box <?= $totalPersen >= 100 ? 'hijau' : 'pasir' ?>"><div class="l">Pengisian nilai</div><div class="v"><?= $totalPersen ?>%</div><div class="s"><?= $selesai ?> dari <?= count($prog) ?> mapel-kelas lengkap</div></div>
    <div class="angka-box <?= $s['kunci_nilai'] ? 'koral' : '' ?>"><div class="l">Status nilai</div><div class="v teks"><?= $s['kunci_nilai'] ? 'Dikunci' : 'Terbuka' ?></div><div class="s"><?= $s['kunci_nilai'] ? 'Guru tidak bisa mengubah nilai' : 'Guru masih bisa mengisi nilai' ?></div></div>
    <div class="angka-box <?= $s['rapor_terbit'] ? 'hijau' : '' ?>"><div class="l">Rapor</div><div class="v teks"><?= $s['rapor_terbit'] ? 'Dibagikan' : 'Belum dibagikan' ?></div><div class="s"><a href="<?= url('pengaturan') ?>#status">Atur di Pengaturan</a></div></div>
  </div>

  <div class="grid g21 mt">
    <div class="kartu rata">
      <div class="kartu-kepala"><h3>Kesiapan rapor per kelas</h3><a class="kecil tebal" href="<?= url('progres') ?>">Rincian per mapel</a></div>
      <div class="tabel-wrap"><table class="tabel">
        <thead><tr><th>Kelas</th><th>Wali kelas</th><th>Nilai mapel</th><th>Data wali kelas</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($perRombel as $rid => $r): $p = (int) round(array_sum($r['p']) / count($r['p'])); $w = $waliData[$rid] ?? ['wali' => '-', 'siswa' => 0, 'rapor' => 0]; $pw = $w['siswa'] ? (int) round($w['rapor'] / $w['siswa'] * 100) : 0; ?>
          <tr>
            <td><a class="nama" href="<?= url('wali', ['id' => $rid]) ?>"><?= e($r['nama']) ?></a></td>
            <td class="kecil"><?= e($w['wali'] ?: '-') ?></td>
            <td><div class="baris" style="flex-wrap:nowrap"><div class="bar grow <?= $kelasBar($p) ?>"><i style="width:<?= $p ?>%"></i></div><span class="mono kecil"><?= $p ?>%</span></div></td>
            <td><div class="baris" style="flex-wrap:nowrap"><div class="bar grow <?= $kelasBar($pw) ?>"><i style="width:<?= $pw ?>%"></i></div><span class="mono kecil"><?= $pw ?>%</span></div></td>
            <td class="kanan nowrap"><a class="btn putih kecil" href="<?= url('leger', ['id' => $rid]) ?>"><?= icon('grid', 15) ?> Leger</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
    <div class="kartu">
      <h3>Belum lengkap</h3>
      <?php if (!$tertinggal): ?><p class="muted m0">Semua guru sudah melengkapi nilai.</p><?php endif; ?>
      <div class="daftar">
        <?php foreach (array_slice($tertinggal, 0, 8) as $r): $p = persen_progres($r); ?>
          <div>
            <span class="grow" style="min-width:0"><b class="kecil"><?= e($r['mapel']) ?> · <?= e($r['rombel']) ?></b><div class="kecil muted"><?= e($r['guru'] ?: 'Belum ada guru') ?></div></span>
            <span class="chip <?= $p >= 60 ? 'pasir' : 'koral' ?>"><?= $p ?>%</span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
<?php endif; ?>

<?php if ($mengajar): ?>
  <div class="kartu rata mt">
    <div class="kartu-kepala"><h3>Mata pelajaran yang saya ajar</h3><span class="kecil muted">Klik untuk mengisi nilai</span></div>
    <div class="tabel-wrap"><table class="tabel">
      <thead><tr><th>Kelas</th><th>Mata pelajaran</th><th class="tengah">TP</th><th class="tengah">Siswa</th><th style="width:220px">Kelengkapan nilai</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($mengajar as $r): $p = persen_progres($r); ?>
        <tr>
          <td class="tebal"><?= e($r['rombel']) ?></td>
          <td><a class="nama" href="<?= url('nilai.input', ['id' => $r['id']]) ?>"><?= e($r['mapel']) ?></a></td>
          <td class="tengah mono"><?= (int) $r['jml_tp'] ?></td>
          <td class="tengah mono"><?= (int) $r['jml_siswa'] ?></td>
          <td><div class="baris" style="flex-wrap:nowrap"><div class="bar grow <?= $kelasBar($p) ?>"><i style="width:<?= $p ?>%"></i></div><span class="mono kecil"><?= $p ?>%</span></div></td>
          <td class="kanan"><a class="btn <?= $p < 100 ? '' : 'putih' ?> kecil" href="<?= url('nilai.input', ['id' => $r['id']]) ?>"><?= icon('pena', 15) ?> <?= $p < 100 ? 'Isi nilai' : 'Lihat' ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
<?php elseif (role() === 'guru'): ?>
  <div class="pesan info"><?= icon('info') ?> <span>Anda belum dijadwalkan mengajar di semester ini. Hubungi operator sekolah.</span></div>
<?php endif; ?>

<?php foreach ($wali as $r):
  $jml = count(siswa_rombel((int) $r['id']));
  $isi = (int) q('SELECT COUNT(*) FROM rapor rp JOIN anggota_rombel a ON a.siswa_id = rp.siswa_id AND a.rombel_id = ? WHERE rp.periode_id = ?', [$r['id'], $per['id']])->fetchColumn();
  $pp = progres_pembelajaran(null, (int) $r['id']);
  $pn = $pp ? (int) round(array_sum(array_map('persen_progres', $pp)) / count($pp)) : 0; ?>
  <div class="kartu mt" style="border-left:5px solid var(--koral)">
    <div class="baris antara">
      <div><h3 style="font-size:20px">Wali kelas <?= e($r['nama']) ?></h3><div class="kecil muted"><?= $jml ?> siswa · fase <?= e($r['fase']) ?></div></div>
      <div class="baris">
        <a class="btn" href="<?= url('wali', ['id' => $r['id']]) ?>"><?= icon('kelas', 16) ?> Kelola kelas</a>
        <a class="btn putih" href="<?= url('leger', ['id' => $r['id']]) ?>"><?= icon('grid', 16) ?> Leger</a>
        <a class="btn putih" target="_blank" href="<?= url('rapor', ['rombel' => $r['id']]) ?>"><?= icon('cetak', 16) ?> Cetak semua rapor</a>
      </div>
    </div>
    <div class="grid g2 mt">
      <div><div class="kecil tebal">Nilai dari guru mapel</div><div class="baris mt2" style="flex-wrap:nowrap"><div class="bar grow <?= $kelasBar($pn) ?>"><i style="width:<?= $pn ?>%"></i></div><span class="mono kecil"><?= $pn ?>%</span></div></div>
      <div><div class="kecil tebal">Kehadiran & catatan wali kelas</div><div class="baris mt2" style="flex-wrap:nowrap"><div class="bar grow <?= $kelasBar($jml ? (int) round($isi / $jml * 100) : 0) ?>"><i style="width:<?= $jml ? round($isi / $jml * 100) : 0 ?>%"></i></div><span class="mono kecil"><?= $isi ?>/<?= $jml ?></span></div></div>
    </div>
  </div>
<?php endforeach; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
