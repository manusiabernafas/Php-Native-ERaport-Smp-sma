<?php
require_role('admin');
$per = periode_aktif();
$rombel = q('SELECT id, nama FROM rombel WHERE periode_id = ? ORDER BY tingkat, nama', [$per['id']])->fetchAll();
$fr = $_GET['rombel'] ?? '';
$status = in_array($_GET['status'] ?? '', ['aktif', 'lulus', 'pindah', 'semua'], true) ? $_GET['status'] : 'aktif';
$cari = trim($_GET['q'] ?? '');
$w = [];
$p = [$per['id']];
if ($status !== 'semua') { $w[] = 's.status = ?'; $p[] = $status; }
if ($fr === 'tanpa') $w[] = 'a.id IS NULL';
elseif ($fr !== '') { $w[] = 'a.rombel_id = ?'; $p[] = (int) $fr; }
if ($cari !== '') { $w[] = '(s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ?)'; array_push($p, "%$cari%", "%$cari%", "%$cari%"); }
$data = q('SELECT s.*, r.nama rombel FROM siswa s LEFT JOIN anggota_rombel a ON a.siswa_id = s.id AND a.periode_id = ? LEFT JOIN rombel r ON r.id = a.rombel_id'
    . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . ' ORDER BY r.tingkat IS NULL, r.tingkat, r.nama, s.nama LIMIT 1000', $p)->fetchAll();
$tanpa = (int) q("SELECT COUNT(*) FROM siswa s WHERE s.status = 'aktif' AND NOT EXISTS (SELECT 1 FROM anggota_rombel a WHERE a.siswa_id = s.id AND a.periode_id = ?)", [$per['id']])->fetchColumn();
$title = 'Data siswa';
$sub = count($data) . ' siswa ditampilkan';
$actions = '<a class="btn putih" href="' . url('siswa.impor') . '">' . icon('unggah', 16) . ' Impor CSV</a><a class="btn" href="' . url('siswa.form') . '">' . icon('plus', 16) . ' Tambah siswa</a>';
require __DIR__ . '/../../includes/header.php';
?>
<?php if ($tanpa): ?><div class="pesan info"><?= icon('info') ?> <span><?= $tanpa ?> siswa aktif belum masuk rombel semester ini. <a href="<?= url('siswa', ['rombel' => 'tanpa']) ?>">Lihat</a>, lalu masukkan lewat menu Rombel.</span></div><?php endif; ?>
<div class="kartu rata">
  <form class="kartu-kepala filter">
    <input type="hidden" name="page" value="siswa">
    <input class="input" name="q" value="<?= e($cari) ?>" placeholder="Cari nama, NIS, atau NISN" style="min-width:230px">
    <select class="input" name="rombel"><option value="">Semua rombel</option><option value="tanpa" <?= $fr === 'tanpa' ? 'selected' : '' ?>>Belum masuk rombel</option><?php foreach ($rombel as $r): ?><option value="<?= $r['id'] ?>" <?= $fr === (string) $r['id'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option><?php endforeach; ?></select>
    <select class="input" name="status"><?php foreach (['aktif' => 'Aktif', 'lulus' => 'Lulus', 'pindah' => 'Pindah', 'semua' => 'Semua status'] as $k => $l): ?><option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
    <button class="btn putih kecil"><?= icon('cari', 15) ?> Tampilkan</button>
  </form>
  <div class="tabel-wrap"><table class="tabel">
    <thead><tr><th>NIS</th><th>NISN</th><th>Nama</th><th>L/P</th><th>Agama</th><th>Kelas</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($data as $s): ?>
      <tr>
        <td class="mono"><?= e($s['nis']) ?></td><td class="mono"><?= e($s['nisn']) ?></td>
        <td><a class="nama" href="<?= url('siswa.form', ['id' => $s['id']]) ?>"><?= e($s['nama']) ?></a><div class="kecil muted"><?= e($s['tempat_lahir']) ?>, <?= tgl($s['tgl_lahir'], false) ?></div></td>
        <td><?= $s['jk'] ?></td><td><?= e($s['agama']) ?></td>
        <td><?= $s['rombel'] ? e($s['rombel']) : '<span class="chip pasir">belum</span>' ?></td>
        <td><span class="chip <?= $s['status'] === 'aktif' ? 'hijau' : 'abu' ?>"><?= e($s['status']) ?></span></td>
        <td class="kanan"><a class="btn putih kecil" href="<?= url('siswa.form', ['id' => $s['id']]) ?>">Ubah</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table><?php if (!$data): ?><div class="kosong">Tidak ada siswa yang cocok.</div><?php endif; ?></div>
</div>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
