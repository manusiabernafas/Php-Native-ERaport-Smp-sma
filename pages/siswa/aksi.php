<?php
require_role('admin');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('siswa');
$id = (int) ($_POST['id'] ?? 0);

require_once __DIR__ . '/aksi_fungsi.php';

switch ($_POST['aksi'] ?? '') {
    case 'simpan':
        [$d, $e] = validasi_siswa($_POST, $id);
        if ($e) {
            $_SESSION['form_siswa'] = $d;
            $_SESSION['form_err'] = $e;
            flash('Periksa kembali isian yang ditandai.', 'error');
            redirect('siswa.form', $id ? ['id' => $id] : []);
        }
        $kol = array_keys($d);
        if ($id) {
            q('UPDATE siswa SET ' . implode(', ', array_map(fn($k) => "$k = ?", $kol)) . ' WHERE id = ?', [...array_values($d), $id]);
            flash('Data siswa diperbarui.');
        } else {
            q('INSERT INTO siswa (' . implode(', ', $kol) . ') VALUES (' . implode(',', array_fill(0, count($kol), '?')) . ')', array_values($d));
            flash('Siswa ditambahkan. Masukkan ke rombel lewat menu Rombel.');
        }
        redirect('siswa');

    case 'hapus':
        q('DELETE FROM siswa WHERE id = ?', [$id]);
        flash('Siswa dihapus.');
        redirect('siswa');
}
flash('Aksi tidak dikenal.', 'error');
redirect('siswa');
