<?php
// Leger nilai akhir satu kelas: siswa x mata pelajaran, jumlah, rata-rata, peringkat. ?csv=1 untuk unduh.
$per = periode_aktif();
$rombelList = lihat_semua() ? q('SELECT id, nama FROM rombel WHERE periode_id = ? ORDER BY tingkat, nama', [$per['id']])->fetchAll() : rombel_wali();
$id = (int) ($_GET['id'] ?? ($rombelList[0]['id'] ?? 0));
if (!$id || !boleh_rombel($id)) { flash('Leger hanya untuk wali kelas, kepala sekolah, dan operator.', 'error'); redirect('dashboard'); }
$r = q('SELECT r.*, u.nama wali FROM rombel r LEFT JOIN pengguna u ON u.id = r.wali_id WHERE r.id = ?', [$id])->fetch();
$nilai = nilai_rombel($id);
$siswa = siswa_rombel($id);
// Kolom mapel: mapel agama digabung jadi satu kolom "Agama"
$kol = [];
foreach ($nilai as $pid => $p) {
    $k = $p['info']['agama'] ? 'AGAMA' : $p['info']['kode'];
    $kol[$k] ??= ['label' => $p['info']['agama'] ? 'Agama' : $p['info']['kode'], 'nama' => $p['info']['agama'] ? 'Pendidikan Agama & Budi Pekerti' : $p['info']['mapel'], 'pb' => []];
    $kol[$k]['pb'][] = $pid;
}
$baris = [];
foreach ($siswa as $s) {
    $b = ['s' => $s, 'n' => [], 'jumlah' => 0, 'isi' => 0, 'kurang' => 0];
    foreach ($kol as $k => $c) {
        $v = null;
        foreach ($c['pb'] as $pid) if (isset($nilai[$pid]['siswa'][$s['id']])) $v = $nilai[$pid]['siswa'][$s['id']]['na'];
        $b['n'][$k] = $v;
        if ($v !== null) { $b['jumlah'] += $v; $b['isi']++; } else $b['kurang']++;
    }
    $b['rata'] = $b['isi'] ? $b['jumlah'] / $b['isi'] : 0;
    $baris[] = $b;
}
// Peringkat berdasarkan jumlah; nilai sama mendapat peringkat sama
$urut = $baris;
usort($urut, fn($a, $b) => $b['jumlah'] <=> $a['jumlah']);
$rank = [];
foreach ($urut as $i => $b) $rank[$b['s']['id']] = ($i > 0 && $b['jumlah'] === $urut[$i - 1]['jumlah']) ? $rank[$urut[$i - 1]['s']['id']] : $i + 1;
$rataKol = [];
foreach ($kol as $k => $c) { $v = array_filter(array_column(array_column($baris, 'n'), $k), fn($x) => $x !== null); $rataKol[$k] = $v ? array_sum($v) / count($v) : null; }

if (!empty($_GET['csv'])) {
    $out = [array_merge(['No', 'NIS', 'NISN', 'Nama'], array_column($kol, 'label'), ['Jumlah', 'Rata-rata', 'Peringkat'])];
    foreach ($baris as $i => $b) $out[] = array_merge([$i + 1, $b['s']['nis'], $b['s']['nisn'], $b['s']['nama']], array_values($b['n']), [$b['jumlah'], number_format($b['rata'], 2, ',', ''), $rank[$b['s']['id']]]);
    kirim_csv('leger-' . str_replace(' ', '', $r['nama']) . '-' . str_replace('/', '-', $per['tahun_ajaran']) . '-smt' . $per['semester'] . '.csv', $out);
}
$kktp = (int) setting()['kktp'];
$title = 'Leger nilai ' . $r['nama'];
$sub = e(label_periode($per)) . ' · wali kelas ' . e($r['wali'] ?: '-') . ' · nilai merah di bawah KKTP ' . $kktp;
$actions = '<a class="btn putih" href="' . url('leger', ['id' => $id, 'csv' => 1]) . '">' . icon('unduh', 16) . ' Unduh CSV (Excel)</a><button class="btn putih" onclick="print()">' . icon('cetak', 16) . ' Cetak</button>';
require __DIR__ . '/../includes/header.php';
?>
<?php if (count($rombelList) > 1): ?>
<nav class="tab-nav no-print"><?php foreach ($rombelList as $x): ?><a href="<?= url('leger', ['id' => $x['id']]) ?>" class="<?= (int) $x['id'] === $id ? 'aktif' : '' ?>"><?= e($x['nama']) ?></a><?php endforeach; ?></nav>
<?php endif; ?>
<div class="kartu rata">
  <div class="tabel-wrap"><table class="tabel" style="font-size:13.5px">
    <thead><tr><th>No</th><th>Nama</th><?php foreach ($kol as $c): ?><th class="tengah" title="<?= e($c['nama']) ?>"><?= e($c['label']) ?></th><?php endforeach; ?><th class="tengah">Jumlah</th><th class="tengah">Rata</th><th class="tengah">Rank</th></tr></thead>
    <tbody>
    <?php foreach ($baris as $i => $b): ?>
      <tr>
        <td class="mono kecil"><?= $i + 1 ?></td>
        <td class="nowrap"><b><?= e($b['s']['nama']) ?></b><?= $b['kurang'] ? ' <span class="chip pasir" title="Nilai belum lengkap">' . $b['kurang'] . ' kosong</span>' : '' ?></td>
        <?php foreach ($b['n'] as $v): ?><td class="tengah nil <?= $v !== null && $v < $kktp ? 'rendah' : '' ?>"><?= $v ?? '<span class="muted">–</span>' ?></td><?php endforeach; ?>
        <td class="tengah nil"><?= $b['jumlah'] ?></td>
        <td class="tengah nil"><?= number_format($b['rata'], 1, ',', '') ?></td>
        <td class="tengah nil"><?= $rank[$b['s']['id']] ?></td>
      </tr>
    <?php endforeach; ?>
    <tr style="background:#fbfbf8"><td></td><td class="tebal">Rata-rata kelas</td><?php foreach ($rataKol as $v): ?><td class="tengah nil"><?= $v === null ? '–' : number_format($v, 1, ',', '') ?></td><?php endforeach; ?><td colspan="3"></td></tr>
    </tbody>
  </table></div>
</div>
<div class="kartu mt kecil muted"><?php foreach ($kol as $c): ?><span class="nowrap" style="margin-right:14px"><b class="mono"><?= e($c['label']) ?></b> = <?= e($c['nama']) ?></span> <?php endforeach; ?></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
