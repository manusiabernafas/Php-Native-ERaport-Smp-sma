<?php
require_role('admin', 'guru', 'kepsek');
$id = (int) ($_GET['id'] ?? 0);
$pb = pembelajaran($id);
if (!$pb) { flash('Pembelajaran tidak ditemukan atau bukan milik Anda.', 'error'); redirect('nilai'); }
$bolehUbah = (is_admin() || (int) $pb['guru_id'] === (int) user()['id']) && !nilai_terkunci();
$s = setting();
$hasil = nilai_rombel((int) $pb['rombel_id'], $id)[$id] ?? ['tp' => [], 'siswa' => []];
$tp = array_values($hasil['tp']);
$siswa = siswa_rombel((int) $pb['rombel_id'], $pb['agama'] ?: null);
$err = $_SESSION['nilai_err'] ?? [];
unset($_SESSION['nilai_err']);

$title = $pb['mapel'];
$sub = 'Kelas <b>' . e($pb['rombel']) . '</b> · fase ' . e($pb['fase']) . ' · ' . e($pb['guru'] ?: 'belum ada guru') . ' · KKTP <b>' . $pb['kktp_efektif'] . '</b> · bobot TP ' . (int) $s['bobot_tp'] . '% / SAS ' . (100 - (int) $s['bobot_tp']) . '%';
$kembali = [url(lihat_semua() && role() !== 'admin' ? 'progres' : 'nilai'), 'Daftar nilai'];
$actions = '<a class="btn putih" href="' . url('nilai.csv', ['id' => $id]) . '">' . icon('unduh', 16) . ' Unduh format CSV</a>';
require __DIR__ . '/../../includes/header.php';
?>
<?php if (nilai_terkunci()): ?><div class="pesan error"><?= icon('kunci') ?> <span>Nilai sedang dikunci operator, tidak bisa diubah.</span></div><?php endif; ?>
<?php if ($err): ?><div class="pesan error"><?= icon('awas') ?> <span><?= e(implode(' ', array_slice($err, 0, 6))) ?><?= count($err) > 6 ? ' (dan ' . (count($err) - 6) . ' lainnya)' : '' ?></span></div><?php endif; ?>

<div class="grid g21">
  <div class="kartu rata">
    <div class="kartu-kepala"><h3>Tujuan Pembelajaran (TP)</h3><span class="kecil muted">Nilai tiap TP = sumatif lingkup materi</span></div>
    <div class="tabel-wrap"><table class="tabel">
      <tbody>
      <?php foreach ($tp as $t): ?>
        <tr>
          <td class="tp-kode nowrap" style="width:60px"><?= e($t['kode']) ?></td>
          <td>
            <?php if ($bolehUbah): ?>
              <form method="post" action="<?= url('nilai.aksi') ?>" class="baris" style="flex-wrap:nowrap">
                <?= csrf_field() ?><input type="hidden" name="aksi" value="tp_ubah"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="tp" value="<?= $t['id'] ?>">
                <input class="input" name="deskripsi" value="<?= e($t['deskripsi']) ?>" maxlength="255" required aria-label="Deskripsi <?= e($t['kode']) ?>">
                <button class="btn putih kecil" title="Simpan"><?= icon('centang', 15) ?></button>
              </form>
            <?php else: ?><?= e($t['deskripsi']) ?><?php endif; ?>
          </td>
          <?php if ($bolehUbah): ?><td style="width:40px"><form method="post" action="<?= url('nilai.aksi') ?>" data-yakin="Hapus <?= e($t['kode']) ?> beserta semua nilainya?"><?= csrf_field() ?><input type="hidden" name="aksi" value="tp_hapus"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="tp" value="<?= $t['id'] ?>"><button class="tautan merah" title="Hapus TP"><?= icon('sampah', 16) ?></button></form></td><?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!$tp): ?><div class="kosong">Belum ada TP. Tambahkan minimal satu TP sebelum mengisi nilai.</div><?php endif; ?></div>
    <?php if ($bolehUbah): ?>
      <form method="post" action="<?= url('nilai.aksi') ?>" class="baris" style="padding:14px 18px;border-top:1px solid var(--garis);flex-wrap:nowrap">
        <?= csrf_field() ?><input type="hidden" name="aksi" value="tp_tambah"><input type="hidden" name="id" value="<?= $id ?>">
        <input class="input" name="deskripsi" maxlength="255" required placeholder="Tujuan pembelajaran baru, mis. Menyelesaikan masalah sistem persamaan linear">
        <button class="btn kecil"><?= icon('plus', 15) ?> TP</button>
      </form>
    <?php endif; ?>
  </div>
  <div class="tumpuk" style="align-content:start">
    <?php if ($bolehUbah): ?>
    <form method="post" action="<?= url('nilai.csv', ['id' => $id]) ?>" enctype="multipart/form-data" class="kartu">
      <h3>Impor nilai dari Excel</h3>
      <p class="kecil muted" style="margin-top:-6px">Unduh format CSV, isi di Excel, simpan sebagai CSV, lalu unggah. Siswa dicocokkan lewat NIS; sel kosong tidak mengubah nilai.</p>
      <?= csrf_field() ?>
      <input class="input" type="file" name="berkas" accept=".csv,text/csv" required>
      <button class="btn putih blok mt2"><?= icon('unggah', 16) ?> Impor CSV</button>
    </form>
    <form method="post" action="<?= url('nilai.aksi') ?>" class="kartu">
      <h3>KKTP mapel ini</h3>
      <?= csrf_field() ?><input type="hidden" name="aksi" value="kktp"><input type="hidden" name="id" value="<?= $id ?>">
      <div class="baris" style="flex-wrap:nowrap"><input class="input" name="kktp" value="<?= e($pb['kktp'] ?? '') ?>" inputmode="numeric" placeholder="ikut sekolah (<?= (int) $s['kktp'] ?>)" style="max-width:180px"><button class="btn putih kecil">Simpan</button></div>
      <p class="kecil muted m0 mt2">Kriteria Ketercapaian TP. Nilai TP di bawah KKTP masuk kalimat "Perlu bantuan".</p>
    </form>
    <?php endif; ?>
  </div>
</div>

<?php if ($tp): ?>
<form method="post" action="<?= url('nilai.aksi') ?>" class="kartu rata mt">
  <?= csrf_field() ?><input type="hidden" name="aksi" value="simpan"><input type="hidden" name="id" value="<?= $id ?>">
  <div class="kartu-kepala">
    <h3>Lembar nilai <span class="kecil muted">(<?= count($siswa) ?> siswa<?= $pb['agama'] ? ', khusus beragama ' . e($pb['agama']) : '' ?>)</span></h3>
    <span class="kecil muted">Isi 0–100 · panah ↑ ↓ / Enter pindah baris · nilai merah = di bawah KKTP</span>
    <?php if ($bolehUbah): ?><button class="btn hijau"><?= icon('centang', 16) ?> Simpan nilai</button><?php endif; ?>
  </div>
  <div class="lembar-wrap">
    <table class="lembar-nilai" data-bobot="<?= (int) $s['bobot_tp'] ?>" data-kktp="<?= $pb['kktp_efektif'] ?>">
      <thead><tr>
        <th>No</th><th class="kiri nm">Nama siswa</th>
        <?php foreach ($tp as $t): ?><th title="<?= e($t['deskripsi']) ?>"><?= e($t['kode']) ?></th><?php endforeach; ?>
        <th>SAS</th><th>Nilai akhir</th><th class="kiri">Capaian kompetensi (otomatis)</th>
      </tr></thead>
      <tbody>
      <?php foreach ($siswa as $i => $sw): $h = $hasil['siswa'][$sw['id']] ?? ['tp' => [], 'sas' => null, 'na' => null, 'deskripsi' => '', 'deskripsi_manual' => null]; ?>
        <tr>
          <td class="no"><?= $i + 1 ?></td>
          <td class="nm"><b><?= e($sw['nama']) ?></b><div class="kecil muted mono"><?= e($sw['nis']) ?></div></td>
          <?php foreach ($tp as $t): ?><td><input class="n n-tp" name="tp[<?= $sw['id'] ?>][<?= $t['id'] ?>]" value="<?= e($h['tp'][$t['id']] ?? '') ?>" inputmode="numeric" maxlength="3" autocomplete="off" aria-label="<?= e($t['kode'] . ' ' . $sw['nama']) ?>" <?= $bolehUbah ? '' : 'readonly' ?>></td><?php endforeach; ?>
          <td class="sas"><input class="n n-sas" name="sas[<?= $sw['id'] ?>]" value="<?= e($h['sas'] ?? '') ?>" inputmode="numeric" maxlength="3" autocomplete="off" aria-label="SAS <?= e($sw['nama']) ?>" <?= $bolehUbah ? '' : 'readonly' ?>></td>
          <td><span class="n-akhir"><?= $h['na'] ?? '–' ?></span></td>
          <td class="desk">
            <?php if ($bolehUbah): ?>
              <details <?= $h['deskripsi_manual'] ? 'open' : '' ?>><summary><?= e($h['deskripsi'] ?: 'Deskripsi muncul setelah nilai disimpan') ?></summary>
                <textarea class="input kecil" name="desk[<?= $sw['id'] ?>]" rows="2" maxlength="600" placeholder="Kosongkan untuk memakai deskripsi otomatis"><?= e($h['deskripsi_manual'] ?? '') ?></textarea></details>
            <?php else: ?><?= e($h['deskripsi']) ?><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php if (!$siswa): ?><div class="kosong">Tidak ada siswa untuk mapel ini.</div><?php endif; ?>
  </div>
  <?php if ($bolehUbah && $siswa): ?><div class="baris antara" style="padding:12px 18px;border-top:1px solid var(--garis)"><span class="kecil muted">Nilai akhir = <?= (int) $s['bobot_tp'] ?>% rata-rata TP + <?= 100 - (int) $s['bobot_tp'] ?>% SAS, dibulatkan.</span><button class="btn hijau"><?= icon('centang', 16) ?> Simpan nilai</button></div><?php endif; ?>
</form>
<?php endif; ?>
<?php require __DIR__ . '/../../includes/footer.php'; ?>
