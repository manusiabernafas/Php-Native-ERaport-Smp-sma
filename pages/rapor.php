<?php
// Cetak rapor A4: ?siswa=ID atau ?rombel=ID (semua siswa). Juga dipakai portal siswa (pages/cek.php) dengan $cetakSiswa.
$s = setting();
$per = periode_aktif();
$modePublik = $modePublik ?? false;
$identitas = !empty($_GET['identitas']);

if ($modePublik) {
    $ids = $cetakSiswa;
} elseif (!empty($_GET['rombel'])) {
    $rid = (int) $_GET['rombel'];
    if (!boleh_rombel($rid)) { flash('Anda tidak berhak mencetak rapor kelas ini.', 'error'); redirect('dashboard'); }
    $ids = array_map(fn($x) => (int) $x['id'], siswa_rombel($rid));
} else {
    $ids = [(int) ($_GET['siswa'] ?? 0)];
}

// Kelompokkan siswa per rombel agar nilai satu kelas dihitung sekali
$anggota = $ids ? q('SELECT a.siswa_id, r.* , u.nama wali, u.nip nip_wali FROM anggota_rombel a JOIN rombel r ON r.id = a.rombel_id
                     LEFT JOIN pengguna u ON u.id = r.wali_id WHERE a.periode_id = ? AND a.siswa_id IN (' . implode(',', array_map('intval', $ids)) . ')', [$per['id']])->fetchAll() : [];
$anggota = array_column($anggota, null, 'siswa_id');
if (!$modePublik) {
    foreach ($anggota as $a) if (!boleh_rombel((int) $a['id'])) { flash('Anda tidak berhak mencetak rapor siswa ini.', 'error'); redirect('dashboard'); }
}
$ids = array_values(array_filter($ids, fn($x) => isset($anggota[$x])));
if (!$ids) { flash('Siswa tidak ditemukan di semester ini.', 'error'); redirect($modePublik ? 'cek' : 'dashboard'); }

$siswa = array_column(q('SELECT * FROM siswa WHERE id IN (' . implode(',', $ids) . ')')->fetchAll(), null, 'id');
$nilaiRombel = [];
$rapor = array_column(q('SELECT * FROM rapor WHERE periode_id = ? AND siswa_id IN (' . implode(',', $ids) . ')', [$per['id']])->fetchAll(), null, 'siswa_id');
$ekskul = [];
foreach (q('SELECT n.*, e.nama FROM nilai_ekskul n JOIN ekskul e ON e.id = n.ekskul_id WHERE n.periode_id = ? AND n.siswa_id IN (' . implode(',', $ids) . ') ORDER BY e.wajib DESC, e.nama', [$per['id']])->fetchAll() as $n) $ekskul[$n['siswa_id']][] = $n;
$genap = (int) $per['semester'] === 2;
$KEPUTUSAN = ['naik' => 'Naik ke kelas berikutnya', 'tidak_naik' => 'Tinggal di kelas yang sama', 'lulus' => 'LULUS', 'tidak_lulus' => 'TIDAK LULUS'];
$alamatSekolah = trim($s['alamat'] . ', ' . $s['kelurahan'] . ', ' . $s['kecamatan'] . ', ' . $s['kabupaten'], ', ');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Rapor<?= count($ids) === 1 ? ' ' . e($siswa[$ids[0]]['nama']) : ' ' . e($anggota[$ids[0]]['nama']) ?> · <?= e(label_periode($per)) ?></title>
<link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="alat-cetak no-print" style="padding:16px 12px 0">
  <?php if ($modePublik): ?>
    <a class="btn putih" href="<?= url('cek') ?>"><?= icon('kiri', 16) ?> Kembali</a>
  <?php else: ?>
    <a class="btn putih" href="<?= url('wali', ['id' => $anggota[$ids[0]]['id'], 'tab' => 'rapor']) ?>"><?= icon('kiri', 16) ?> Kembali</a>
    <a class="btn putih" href="?<?= e(http_build_query(array_merge($_GET, ['identitas' => $identitas ? null : 1]))) ?>"><?= $identitas ? 'Tanpa' : 'Dengan' ?> halaman identitas</a>
  <?php endif; ?>
  <button class="btn" onclick="print()"><?= icon('cetak', 16) ?> Cetak / simpan PDF</button>
  <span class="kecil muted"><?= count($ids) ?> rapor · gunakan kertas A4, margin "Tidak ada"</span>
</div>
<?php foreach ($ids as $no => $sid):
    $sw = $siswa[$sid];
    $rb = $anggota[$sid];
    $nilaiRombel[$rb['id']] ??= nilai_rombel((int) $rb['id']);
    $mapel = array_filter($nilaiRombel[$rb['id']], fn($p) => isset($p['siswa'][$sid]));
    $umum = array_filter($mapel, fn($p) => $p['info']['kelompok'] === 'umum');
    $mulok = array_filter($mapel, fn($p) => $p['info']['kelompok'] === 'mulok');
    $rp = $rapor[$sid] ?? ['sakit' => 0, 'izin' => 0, 'alfa' => 0, 'catatan' => '', 'keputusan' => ''];
    $ttd = function () use ($s, $per, $rb) { ?>
      <div class="ttd">
        <div>Mengetahui,<br>Orang Tua/Wali<div class="nm">..............................</div></div>
        <div><?= e($s['tempat_rapor']) ?>, <?= tgl($per['tgl_rapor']) ?><br>Wali Kelas<div class="nm"><?= e($rb['wali'] ?: '..............................') ?></div><?= $rb['nip_wali'] ? 'NIP. ' . e($rb['nip_wali']) : '' ?></div>
      </div>
      <div class="ttd" style="justify-content:center;margin-top:8px"><div>Mengetahui,<br>Kepala Sekolah<div class="nm"><?= e($s['kepala_sekolah']) ?></div><?= $s['nip_kepsek'] ? 'NIP. ' . e($s['nip_kepsek']) : '' ?></div></div>
    <?php };
?>
  <?php if ($identitas): ?>
  <div class="lembar-cetak <?= $no ? 'putus' : '' ?>">
    <h2>IDENTITAS PESERTA DIDIK</h2>
    <table class="ident" style="margin-top:14px">
      <?php $baris = [
        'Nama Lengkap Peserta Didik' => $sw['nama'], 'NIS / NISN' => $sw['nis'] . ' / ' . $sw['nisn'],
        'Tempat, Tanggal Lahir' => $sw['tempat_lahir'] . ', ' . tgl($sw['tgl_lahir']), 'Jenis Kelamin' => $sw['jk'] === 'L' ? 'Laki-laki' : 'Perempuan',
        'Agama' => $sw['agama'], 'Alamat Peserta Didik' => $sw['alamat'], 'Diterima di sekolah ini pada tanggal' => tgl($sw['tgl_masuk']),
        'Nama Ayah' => $sw['nama_ayah'], 'Pekerjaan Ayah' => $sw['pekerjaan_ayah'], 'Nama Ibu' => $sw['nama_ibu'], 'Pekerjaan Ibu' => $sw['pekerjaan_ibu'],
        'Telepon Orang Tua' => $sw['telepon_ortu']];
      $i = 0; foreach ($baris as $k => $v): $i++; ?>
        <tr><td style="width:28px"><?= $i ?>.</td><td style="width:42%;padding:5px 4px"><?= e($k) ?></td><td style="width:12px">:</td><td style="padding:5px 4px"><?= e($v ?: '-') ?></td></tr>
      <?php endforeach; ?>
    </table>
    <div class="ttd" style="justify-content:flex-end;margin-top:40px"><div><?= e($s['tempat_rapor']) ?>, <?= tgl($sw['tgl_masuk']) ?><br>Kepala Sekolah<div class="nm"><?= e($s['kepala_sekolah']) ?></div><?= $s['nip_kepsek'] ? 'NIP. ' . e($s['nip_kepsek']) : '' ?></div></div>
  </div>
  <?php endif; ?>

  <div class="lembar-cetak <?= $no || $identitas ? 'putus' : '' ?>">
    <table class="ident">
      <tr><td style="width:17%">Nama</td><td style="width:2%">:</td><td style="width:38%"><b><?= e($sw['nama']) ?></b></td><td style="width:15%">Kelas</td><td style="width:2%">:</td><td><?= e($rb['nama']) ?></td></tr>
      <tr><td>NIS / NISN</td><td>:</td><td><?= e($sw['nis']) ?> / <?= e($sw['nisn']) ?></td><td>Fase</td><td>:</td><td><?= e($rb['fase']) ?></td></tr>
      <tr><td>Nama Sekolah</td><td>:</td><td><?= e($s['nama_sekolah']) ?></td><td>Semester</td><td>:</td><td><?= (int) $per['semester'] ?> (<?= $per['semester'] == 1 ? 'Ganjil' : 'Genap' ?>)</td></tr>
      <tr><td>Alamat</td><td>:</td><td><?= e($alamatSekolah) ?></td><td>Tahun Pelajaran</td><td>:</td><td><?= e($per['tahun_ajaran']) ?></td></tr>
    </table>
    <div class="garis-kop"></div>
    <h2>LAPORAN HASIL BELAJAR</h2>
    <table class="tb">
      <thead><tr><th style="width:5%">No</th><th style="width:27%">Mata Pelajaran</th><th style="width:10%">Nilai Akhir</th><th>Capaian Kompetensi</th></tr></thead>
      <tbody>
      <?php $n = 0; foreach ($umum as $p): $h = $p['info']; $x = $p['siswa'][$sid]; ?>
        <tr><td class="tengah"><?= ++$n ?></td><td><?= e($h['mapel']) ?></td><td class="tengah"><b><?= $x['na'] ?? '-' ?></b></td><td><?= e($x['deskripsi']) ?></td></tr>
      <?php endforeach; ?>
      <?php if ($mulok): ?>
        <tr><td colspan="4"><b>Muatan Lokal</b></td></tr>
        <?php foreach ($mulok as $p): $h = $p['info']; $x = $p['siswa'][$sid]; ?>
          <tr><td class="tengah"><?= ++$n ?></td><td><?= e($h['mapel']) ?></td><td class="tengah"><b><?= $x['na'] ?? '-' ?></b></td><td><?= e($x['deskripsi']) ?></td></tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>

    <div class="sub-judul">Ekstrakurikuler</div>
    <table class="tb">
      <thead><tr><th style="width:5%">No</th><th style="width:32%">Kegiatan Ekstrakurikuler</th><th style="width:16%">Predikat</th><th>Keterangan</th></tr></thead>
      <tbody>
      <?php foreach ($ekskul[$sid] ?? [] as $i => $ek): ?>
        <tr><td class="tengah"><?= $i + 1 ?></td><td><?= e($ek['nama']) ?></td><td class="tengah"><?= e($ek['predikat']) ?></td><td><?= e($ek['keterangan']) ?></td></tr>
      <?php endforeach; ?>
      <?php if (empty($ekskul[$sid])): ?><tr><td class="tengah">1</td><td>-</td><td class="tengah">-</td><td>-</td></tr><?php endif; ?>
      </tbody>
    </table>

    <div style="display:flex;gap:14px;margin-top:12px;align-items:flex-start">
      <table class="tb" style="width:42%">
        <thead><tr><th colspan="2">Ketidakhadiran</th></tr></thead>
        <tbody>
          <tr><td>Sakit</td><td class="tengah" style="width:35%"><?= (int) $rp['sakit'] ?> hari</td></tr>
          <tr><td>Izin</td><td class="tengah"><?= (int) $rp['izin'] ?> hari</td></tr>
          <tr><td>Tanpa Keterangan</td><td class="tengah"><?= (int) $rp['alfa'] ?> hari</td></tr>
        </tbody>
      </table>
      <table class="tb" style="flex:1">
        <thead><tr><th>Catatan Wali Kelas</th></tr></thead>
        <tbody><tr><td style="height:70px"><?= e($rp['catatan'] ?: '-') ?></td></tr></tbody>
      </table>
    </div>
    <?php if ($genap && $rp['keputusan']): ?>
      <table class="tb" style="margin-top:12px"><tbody><tr><td><b>Keputusan:</b> Berdasarkan pencapaian seluruh kompetensi, peserta didik dinyatakan <b><?= e($KEPUTUSAN[$rp['keputusan']] ?? '-') ?></b></td></tr></tbody></table>
    <?php endif; ?>
    <?php $ttd(); ?>
  </div>
<?php endforeach; ?>
</body>
</html>
