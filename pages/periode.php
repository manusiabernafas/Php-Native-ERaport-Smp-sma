<?php
require_role('admin');
$s = setting();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksi = $_POST['aksi'] ?? '';
    if ($aksi === 'tambah') {
        $ta = trim($_POST['tahun_ajaran'] ?? '');
        $sm = (int) ($_POST['semester'] ?? 1);
        $tgl = $_POST['tgl_rapor'] ?? '';
        if (!preg_match('/^(\d{4})\/(\d{4})$/', $ta, $m) || (int) $m[2] !== (int) $m[1] + 1) flash('Tahun ajaran harus berformat 2026/2027.', 'error');
        elseif (!in_array($sm, [1, 2], true) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) flash('Pilih semester dan tanggal rapor.', 'error');
        elseif (q('SELECT 1 FROM periode WHERE tahun_ajaran = ? AND semester = ?', [$ta, $sm])->fetchColumn()) flash('Periode itu sudah ada.', 'error');
        else { q('INSERT INTO periode (tahun_ajaran, semester, tgl_rapor) VALUES (?,?,?)', [$ta, $sm, $tgl]); flash('Periode ditambahkan.'); }
    } elseif ($aksi === 'tgl') {
        $tgl = $_POST['tgl_rapor'] ?? '';
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) { q('UPDATE periode SET tgl_rapor = ? WHERE id = ?', [$tgl, (int) $_POST['id']]); flash('Tanggal rapor diperbarui.'); }
    } elseif ($aksi === 'aktifkan') {
        $id = (int) $_POST['id'];
        if (q('SELECT 1 FROM periode WHERE id = ?', [$id])->fetchColumn()) {
            // Ganti semester: kunci & publikasi direset agar semester baru mulai bersih
            q('UPDATE pengaturan SET periode_id = ?, kunci_nilai = 0, rapor_terbit = 0 WHERE id = 1', [$id]);
            flash('Periode aktif diganti. Semua menu sekarang memakai semester ini.');
        }
    } elseif ($aksi === 'salin') {
        // Salin rombel, anggota, wali, dan pembagian guru dari periode lain ke periode aktif (mis. semester 1 -> semester 2)
        $dari = (int) $_POST['dari'];
        $ke = (int) $s['periode_id'];
        if ($dari === $ke) flash('Pilih periode sumber yang berbeda dengan periode aktif.', 'error');
        elseif (q('SELECT 1 FROM rombel WHERE periode_id = ?', [$ke])->fetchColumn()) flash('Periode aktif sudah punya rombel. Penyalinan hanya untuk periode yang masih kosong.', 'error');
        else {
            db()->beginTransaction();
            $n = 0;
            foreach (q('SELECT * FROM rombel WHERE periode_id = ?', [$dari])->fetchAll() as $r) {
                q('INSERT INTO rombel (periode_id, nama, tingkat, fase, wali_id) VALUES (?,?,?,?,?)', [$ke, $r['nama'], $r['tingkat'], $r['fase'], $r['wali_id']]);
                $baru = (int) db()->lastInsertId();
                q("INSERT INTO anggota_rombel (rombel_id, periode_id, siswa_id) SELECT ?, ?, a.siswa_id FROM anggota_rombel a JOIN siswa s ON s.id = a.siswa_id WHERE a.rombel_id = ? AND s.status = 'aktif'", [$baru, $ke, $r['id']]);
                q('INSERT INTO pembelajaran (rombel_id, mapel_id, guru_id, kktp) SELECT ?, mapel_id, guru_id, kktp FROM pembelajaran WHERE rombel_id = ?', [$baru, $r['id']]);
                $n++;
            }
            db()->commit();
            flash("$n rombel beserta anggota dan pembagian guru disalin. TP & nilai dimulai kosong.");
        }
    }
    redirect('periode');
}
$data = q('SELECT p.*, (SELECT COUNT(*) FROM rombel r WHERE r.periode_id = p.id) rombel,
             (SELECT COUNT(*) FROM anggota_rombel a WHERE a.periode_id = p.id) siswa FROM periode p ORDER BY tahun_ajaran DESC, semester DESC')->fetchAll();
$title = 'Pengaturan';
$sub = 'Periode tahun ajaran & semester';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/subnav.php';
?>
<div class="grid g21">
  <div class="kartu rata">
    <div class="tabel-wrap"><table class="tabel">
      <thead><tr><th>Tahun ajaran</th><th>Semester</th><th>Tanggal rapor</th><th class="tengah">Rombel</th><th class="tengah">Siswa</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($data as $p): $aktif = (int) $p['id'] === (int) $s['periode_id']; ?>
        <tr>
          <td class="tebal"><?= e($p['tahun_ajaran']) ?> <?= $aktif ? '<span class="chip hijau">aktif</span>' : '' ?></td>
          <td><?= $p['semester'] == 1 ? '1 (Ganjil)' : '2 (Genap)' ?></td>
          <td><form method="post" class="baris" style="flex-wrap:nowrap"><?= csrf_field() ?><input type="hidden" name="aksi" value="tgl"><input type="hidden" name="id" value="<?= $p['id'] ?>"><input class="input" type="date" name="tgl_rapor" value="<?= e($p['tgl_rapor']) ?>" style="width:auto"><button class="btn putih kecil">Simpan</button></form></td>
          <td class="tengah mono"><?= (int) $p['rombel'] ?></td><td class="tengah mono"><?= (int) $p['siswa'] ?></td>
          <td class="kanan"><?php if (!$aktif): ?><form method="post" data-yakin="Jadikan <?= e($p['tahun_ajaran']) ?> semester <?= (int) $p['semester'] ?> sebagai periode aktif?"><?= csrf_field() ?><input type="hidden" name="aksi" value="aktifkan"><input type="hidden" name="id" value="<?= $p['id'] ?>"><button class="btn putih kecil">Aktifkan</button></form><?php endif; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
  <div class="tumpuk" style="align-content:start">
    <form method="post" class="kartu">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="tambah">
      <h3>Tambah periode</h3>
      <div class="grid g2">
        <label class="f"><span>Tahun ajaran</span><input name="tahun_ajaran" placeholder="2026/2027" required></label>
        <label class="f"><span>Semester</span><select name="semester"><option value="1">1 (Ganjil)</option><option value="2">2 (Genap)</option></select></label>
      </div>
      <label class="f"><span>Tanggal rapor</span><input type="date" name="tgl_rapor" required></label>
      <button class="btn blok"><?= icon('plus', 16) ?> Tambah</button>
    </form>
    <form method="post" class="kartu" data-yakin="Salin rombel, anggota, dan pembagian guru ke periode aktif?">
      <?= csrf_field() ?><input type="hidden" name="aksi" value="salin">
      <h3>Salin rombel ke periode aktif</h3>
      <p class="kecil muted" style="margin-top:-6px">Cocok untuk pindah ke semester genap: kelas, siswa, wali kelas, dan guru mapel sama, TP & nilai dimulai baru.</p>
      <select class="input" name="dari"><?php foreach ($data as $p): if ((int) $p['id'] === (int) $s['periode_id'] || !$p['rombel']) continue; ?><option value="<?= $p['id'] ?>"><?= e($p['tahun_ajaran']) ?> smt <?= (int) $p['semester'] ?></option><?php endforeach; ?></select>
      <button class="btn putih blok mt2">Salin</button>
    </form>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
