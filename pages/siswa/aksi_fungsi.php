<?php
/** Validasi satu baris data siswa (dipakai juga oleh impor CSV). @return array [data, galat] */
function validasi_siswa(array $in, int $id = 0): array
{
    $d = [];
    foreach (['nis', 'nisn', 'nama', 'tempat_lahir', 'tgl_lahir', 'nama_ayah', 'nama_ibu', 'pekerjaan_ayah', 'pekerjaan_ibu', 'alamat', 'telepon_ortu', 'tgl_masuk'] as $k) {
        $d[$k] = mb_substr(trim((string) ($in[$k] ?? '')), 0, $k === 'alamat' ? 200 : 100);
    }
    $d['jk'] = strtoupper(substr(trim((string) ($in['jk'] ?? 'L')), 0, 1)) === 'P' ? 'P' : 'L';
    $d['agama'] = in_array($in['agama'] ?? '', ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'], true) ? $in['agama'] : 'Islam';
    $d['status'] = in_array($in['status'] ?? '', ['aktif', 'lulus', 'pindah'], true) ? $in['status'] : 'aktif';
    if ($d['tgl_masuk'] === '') $d['tgl_masuk'] = date('Y') . '-07-14';
    $e = [];
    if (!preg_match('/^[A-Za-z0-9.\-\/]{3,20}$/', $d['nis'])) $e['nis'] = 'NIS 3-20 karakter.';
    if (!preg_match('/^\d{10}$/', $d['nisn'])) $e['nisn'] = 'NISN harus 10 digit angka.';
    if (mb_strlen($d['nama']) < 3) $e['nama'] = 'Isi nama lengkap.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['tgl_lahir']) || !strtotime($d['tgl_lahir'])) $e['tgl_lahir'] = 'Tanggal lahir tidak valid (format YYYY-MM-DD).';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['tgl_masuk'])) $e['tgl_masuk'] = 'Tanggal masuk tidak valid.';
    if ($d['telepon_ortu'] !== '' && !preg_match('/^(\+62|62|0)8[0-9]{7,12}$/', $d['telepon_ortu'])) $e['telepon_ortu'] = 'Nomor telepon tidak valid.';
    if (!isset($e['nis']) && q('SELECT 1 FROM siswa WHERE nis = ? AND id <> ?', [$d['nis'], $id])->fetchColumn()) $e['nis'] = 'NIS sudah dipakai siswa lain.';
    if (!isset($e['nisn']) && q('SELECT 1 FROM siswa WHERE nisn = ? AND id <> ?', [$d['nisn'], $id])->fetchColumn()) $e['nisn'] = 'NISN sudah dipakai siswa lain.';
    return [$d, $e];
}
