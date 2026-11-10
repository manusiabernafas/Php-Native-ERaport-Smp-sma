<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = require __DIR__ . '/../config/database.php';
        $dsn = "mysql:host={$c['host']};port={$c['port']};dbname={$c['database']};charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, $c['username'], $c['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            die('<h3>Koneksi database gagal.</h3><p>Cek <b>config/database.php</b> dan pastikan database <b>'
                . htmlspecialchars($c['database']) . '</b> sudah di-import dari folder <b>database/</b>.</p>');
        }
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

const BULAN_ID = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

function tgl($date, bool $panjang = true): string
{
    if (!$date) return '-';
    $t = strtotime($date);
    $b = BULAN_ID[(int) date('n', $t)];
    return date('j', $t) . ' ' . ($panjang ? $b : substr($b, 0, 3)) . ' ' . date('Y', $t);
}

function url(string $page = 'dashboard', array $params = []): string
{
    return 'index.php?' . http_build_query(['page' => $page] + $params);
}

function redirect(string $page, array $params = []): void
{
    header('Location: ' . url($page, $params));
    exit;
}

function flash(?string $msg = null, string $type = 'ok')
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

// ---------------------------------------------------------------- akun & peran

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): void
{
    if (!user()) {
        flash('Silakan masuk terlebih dahulu.', 'error');
        redirect('login');
    }
    // Baca ulang dari database: perubahan peran / penonaktifan langsung berlaku
    $u = q('SELECT id, nama, username, role, aktif FROM pengguna WHERE id = ?', [user()['id']])->fetch();
    if (!$u || !$u['aktif']) {
        $_SESSION = [];
        flash('Akun Anda tidak aktif. Hubungi operator sekolah.', 'error');
        redirect('login');
    }
    $_SESSION['user'] = ['id' => (int) $u['id'], 'nama' => $u['nama'], 'username' => $u['username'], 'role' => $u['role']];
}

function role(): string
{
    return user()['role'] ?? '';
}

function is_admin(): bool
{
    return role() === 'admin';
}

/** Admin & kepala sekolah boleh melihat semua kelas. */
function lihat_semua(): bool
{
    return in_array(role(), ['admin', 'kepsek'], true);
}

function require_role(string ...$roles): void
{
    if (!in_array(role(), $roles, true)) {
        flash('Menu itu tidak tersedia untuk akun Anda.', 'error');
        redirect('dashboard');
    }
}

const LABEL_ROLE = ['admin' => 'Operator / Admin', 'guru' => 'Guru', 'kepsek' => 'Kepala Sekolah'];

// ---------------------------------------------------------------- keamanan

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrf_token() . '">';
}

function verify_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(csrf_token(), (string) ($_POST['_csrf'] ?? ''))) {
        http_response_code(403);
        die('Sesi formulir kedaluwarsa. Muat ulang halaman lalu coba lagi.');
    }
}

/** Batas percobaan sederhana berbasis sesi + IP (login & cek rapor). */
function batas_coba(string $kunci, int $maks = 5, int $detik = 600): bool
{
    $now = time();
    $d = $_SESSION['coba'][$kunci] ?? ['n' => 0, 't' => $now];
    if ($now - $d['t'] > $detik) $d = ['n' => 0, 't' => $now];
    if ($d['n'] >= $maks) {
        $_SESSION['coba'][$kunci] = $d;
        return false;
    }
    $d['n']++;
    $_SESSION['coba'][$kunci] = $d;
    return true;
}

function reset_coba(string $kunci): void
{
    unset($_SESSION['coba'][$kunci]);
}

// ---------------------------------------------------------------- data sekolah & periode

function setting(): array
{
    static $s = null;
    return $s ??= q('SELECT * FROM pengaturan WHERE id = 1')->fetch() ?: [];
}

function periode_aktif(): array
{
    static $p = null;
    return $p ??= q('SELECT * FROM periode WHERE id = ?', [setting()['periode_id']])->fetch() ?: ['id' => 0, 'tahun_ajaran' => '-', 'semester' => 1, 'tgl_rapor' => date('Y-m-d')];
}

function label_periode(array $p): string
{
    return 'Semester ' . ($p['semester'] == 1 ? '1 (Ganjil)' : '2 (Genap)') . ' ' . $p['tahun_ajaran'];
}

/** Nilai terkunci: guru & wali kelas tidak bisa mengubah, admin tetap bisa. */
function nilai_terkunci(): bool
{
    return (bool) setting()['kunci_nilai'] && !is_admin();
}

/** Rombel di periode aktif yang diwalikan pengguna ini. */
function rombel_wali(): array
{
    static $r = null;
    return $r ??= q('SELECT * FROM rombel WHERE periode_id = ? AND wali_id = ? ORDER BY tingkat, nama', [periode_aktif()['id'], user()['id'] ?? 0])->fetchAll();
}

function boleh_rombel(int $rombelId): bool
{
    if (lihat_semua()) return true;
    foreach (rombel_wali() as $r) if ((int) $r['id'] === $rombelId) return true;
    return false;
}

/** Ambil pembelajaran beserta rombel & mapel; null bila tidak ada / bukan hak pengguna. */
function pembelajaran(int $id, bool $cekHak = true): ?array
{
    $p = q('SELECT pb.*, r.nama rombel, r.tingkat, r.fase, r.periode_id, m.nama mapel, m.kode, m.agama, u.nama guru
            FROM pembelajaran pb JOIN rombel r ON r.id = pb.rombel_id JOIN mapel m ON m.id = pb.mapel_id
            LEFT JOIN pengguna u ON u.id = pb.guru_id WHERE pb.id = ?', [$id])->fetch();
    if (!$p) return null;
    if ($cekHak && !lihat_semua() && (int) $p['guru_id'] !== (int) user()['id'] && !boleh_rombel((int) $p['rombel_id'])) return null;
    $p['kktp_efektif'] = (int) ($p['kktp'] ?: setting()['kktp']);
    return $p;
}

/** Siswa anggota rombel; bila mapel agama hanya siswa beragama sama. */
function siswa_rombel(int $rombelId, ?string $agama = null): array
{
    $sql = 'SELECT s.* FROM anggota_rombel a JOIN siswa s ON s.id = a.siswa_id WHERE a.rombel_id = ?';
    $p = [$rombelId];
    if ($agama) { $sql .= ' AND s.agama = ?'; $p[] = $agama; }
    return q($sql . ' ORDER BY s.nama', $p)->fetchAll();
}

// ---------------------------------------------------------------- perhitungan nilai rapor

function lcfirst_id(string $s): string
{
    // Jangan kecilkan singkatan/nama diri seperti "Al-Qur'an", "IPA", "Pancasila" di tengah kalimat
    $kata = strtok($s, ' ');
    if ($kata !== false && preg_match('/^[A-Z][a-z]/', $kata)) return mb_strtolower(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    return $s;
}

function gabung(array $x): string
{
    if (count($x) <= 1) return $x[0] ?? '';
    return implode(', ', array_slice($x, 0, -1)) . ' dan ' . end($x);
}

/**
 * Deskripsi capaian kompetensi otomatis ala Kurikulum Merdeka:
 * TP tertinggi yang mencapai KKTP -> "Menunjukkan penguasaan yang baik dalam ...",
 * TP di bawah KKTP -> "Perlu bantuan dalam ...", bila semua tercapai -> "Perlu peningkatan dalam <TP terendah>".
 * @param array $tp daftar ['deskripsi' => ..., 'nilai' => ...]
 */
function deskripsi_capaian(array $tp, int $kktp): string
{
    $tp = array_values(array_filter($tp, fn($t) => $t['nilai'] !== null));
    if (!$tp) return '';
    $atas = array_values(array_filter($tp, fn($t) => $t['nilai'] >= $kktp));
    $bawah = array_values(array_filter($tp, fn($t) => $t['nilai'] < $kktp));
    usort($atas, fn($a, $b) => $b['nilai'] <=> $a['nilai']);
    usort($bawah, fn($a, $b) => $a['nilai'] <=> $b['nilai']);
    $k = [];
    if ($atas) $k[] = 'Menunjukkan penguasaan yang baik dalam ' . gabung(array_map(fn($t) => lcfirst_id($t['deskripsi']), array_slice($atas, 0, 2))) . '.';
    if ($bawah) {
        $k[] = 'Perlu bantuan dalam ' . gabung(array_map(fn($t) => lcfirst_id($t['deskripsi']), array_slice($bawah, 0, 2))) . '.';
    } elseif (count($atas) > 2) {
        $k[] = 'Perlu peningkatan dalam ' . lcfirst_id(end($atas)['deskripsi']) . '.';
    }
    return implode(' ', $k);
}

/** Nilai akhir = bobot% rata-rata nilai TP + sisa% nilai SAS (dibulatkan). Null bila belum lengkap. */
function nilai_akhir(?float $rataTp, ?int $sas): ?int
{
    if ($rataTp === null || $sas === null) return null;
    $b = (int) setting()['bobot_tp'];
    return (int) round(($b * $rataTp + (100 - $b) * $sas) / 100);
}

/**
 * Hitung nilai seluruh pembelajaran satu rombel sekaligus (3 query).
 * @return array [pembelajaran_id => ['info' => pembelajaran, 'tp' => [...], 'siswa' => [siswa_id => hasil]]]
 */
function nilai_rombel(int $rombelId, ?int $hanyaPembelajaran = null): array
{
    $kktpUmum = (int) setting()['kktp'];
    $w = $hanyaPembelajaran ? ' AND pb.id = ' . (int) $hanyaPembelajaran : '';
    $pbs = q("SELECT pb.*, m.nama mapel, m.kode, m.kelompok, m.agama, m.urutan, u.nama guru FROM pembelajaran pb
              JOIN mapel m ON m.id = pb.mapel_id LEFT JOIN pengguna u ON u.id = pb.guru_id
              WHERE pb.rombel_id = ? $w ORDER BY m.kelompok = 'mulok', m.urutan, m.nama", [$rombelId])->fetchAll();
    $hasil = [];
    foreach ($pbs as $pb) $hasil[$pb['id']] = ['info' => $pb + ['kktp_efektif' => (int) ($pb['kktp'] ?: $kktpUmum)], 'tp' => [], 'siswa' => []];
    if (!$hasil) return [];
    $ids = implode(',', array_map('intval', array_keys($hasil)));
    foreach (q("SELECT * FROM tp WHERE pembelajaran_id IN ($ids) ORDER BY id")->fetchAll() as $t) $hasil[$t['pembelajaran_id']]['tp'][$t['id']] = $t;
    $nilaiTp = [];
    foreach (q("SELECT n.* , t.pembelajaran_id FROM nilai_tp n JOIN tp t ON t.id = n.tp_id WHERE t.pembelajaran_id IN ($ids)")->fetchAll() as $n) {
        $nilaiTp[$n['pembelajaran_id']][$n['siswa_id']][$n['tp_id']] = (int) $n['nilai'];
    }
    $sas = [];
    foreach (q("SELECT * FROM nilai_sas WHERE pembelajaran_id IN ($ids)")->fetchAll() as $n) $sas[$n['pembelajaran_id']][$n['siswa_id']] = $n;
    $anggota = siswa_rombel($rombelId);
    foreach ($hasil as $pid => &$h) {
        foreach ($anggota as $s) {
            if ($h['info']['agama'] && $s['agama'] !== $h['info']['agama']) continue;
            $nt = $nilaiTp[$pid][$s['id']] ?? [];
            $tpList = [];
            foreach ($h['tp'] as $tid => $t) $tpList[] = ['deskripsi' => $t['deskripsi'], 'nilai' => $nt[$tid] ?? null];
            $rata = $nt ? array_sum($nt) / count($nt) : null;
            $sv = $sas[$pid][$s['id']] ?? null;
            $nilaiSas = $sv && $sv['nilai'] !== null ? (int) $sv['nilai'] : null;
            $lengkap = $h['tp'] && count($nt) === count($h['tp']) && $nilaiSas !== null;
            $h['siswa'][$s['id']] = [
                'tp' => $nt,
                'rata_tp' => $rata,
                'sas' => $nilaiSas,
                'na' => nilai_akhir($rata, $nilaiSas),
                'lengkap' => $lengkap,
                'deskripsi_manual' => $sv['deskripsi'] ?? null,
                'deskripsi' => ($sv['deskripsi'] ?? '') !== '' && $sv['deskripsi'] !== null ? $sv['deskripsi'] : deskripsi_capaian($tpList, $h['info']['kktp_efektif']),
            ];
        }
    }
    unset($h);
    return $hasil;
}

/** Progres pengisian: [pembelajaran_id => ['siswa' => n, 'lengkap' => n]] untuk periode aktif. */
function progres_pembelajaran(?int $guruId = null, ?int $rombelId = null): array
{
    $w = ['r.periode_id = ?'];
    $p = [periode_aktif()['id']];
    if ($guruId) { $w[] = 'pb.guru_id = ?'; $p[] = $guruId; }
    if ($rombelId) { $w[] = 'pb.rombel_id = ?'; $p[] = $rombelId; }
    return q("SELECT pb.id, pb.rombel_id, r.nama rombel, r.tingkat, m.nama mapel, m.kode, u.nama guru,
                (SELECT COUNT(*) FROM tp WHERE tp.pembelajaran_id = pb.id) jml_tp,
                (SELECT COUNT(*) FROM anggota_rombel a JOIN siswa s ON s.id = a.siswa_id WHERE a.rombel_id = pb.rombel_id AND (m.agama IS NULL OR s.agama = m.agama)) jml_siswa,
                (SELECT COUNT(*) FROM nilai_sas ns JOIN anggota_rombel a ON a.siswa_id = ns.siswa_id AND a.rombel_id = pb.rombel_id
                  WHERE ns.pembelajaran_id = pb.id AND ns.nilai IS NOT NULL) jml_sas,
                (SELECT COUNT(*) FROM nilai_tp nt JOIN tp ON tp.id = nt.tp_id JOIN anggota_rombel a ON a.siswa_id = nt.siswa_id AND a.rombel_id = pb.rombel_id
                  WHERE tp.pembelajaran_id = pb.id) jml_ntp
              FROM pembelajaran pb JOIN rombel r ON r.id = pb.rombel_id JOIN mapel m ON m.id = pb.mapel_id LEFT JOIN pengguna u ON u.id = pb.guru_id
              WHERE " . implode(' AND ', $w) . " ORDER BY r.tingkat, r.nama, m.urutan", $p)->fetchAll();
}

function persen_progres(array $r): int
{
    $butuh = (int) $r['jml_siswa'] * ((int) $r['jml_tp'] + 1);
    if ($butuh === 0) return (int) $r['jml_siswa'] === 0 ? 100 : 0;
    return (int) min(100, round(((int) $r['jml_ntp'] + (int) $r['jml_sas']) / $butuh * 100));
}

/** Ambil angka nilai 0-100 dari input; '' = kosong (null); selain itu false (tidak valid). */
function baca_nilai($v)
{
    $v = trim((string) $v);
    if ($v === '') return null;
    if (!preg_match('/^\d{1,3}$/', $v) || (int) $v > 100) return false;
    return (int) $v;
}

/** Baca CSV unggahan (pemisah ; atau , dideteksi otomatis, BOM dibuang). */
function baca_csv(string $path, int $maksBaris = 2000): array
{
    $isi = file_get_contents($path, false, null, 0, 2_000_000);
    if ($isi === false) return [];
    $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi);
    $baris1 = strtok($isi, "\n");
    $pisah = substr_count((string) $baris1, ';') >= substr_count((string) $baris1, ',') ? ';' : ',';
    $out = [];
    $fh = fopen('php://temp', 'r+');
    fwrite($fh, $isi);
    rewind($fh);
    while (($r = fgetcsv($fh, 0, $pisah, '"', '')) !== false && count($out) < $maksBaris) {
        if ($r === [null] || (count($r) === 1 && trim((string) $r[0]) === '')) continue;
        $out[] = array_map(fn($x) => trim((string) $x), $r);
    }
    fclose($fh);
    return $out;
}

function kirim_csv(string $nama, array $baris): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $nama) . '"');
    $o = fopen('php://output', 'w');
    fwrite($o, "\xEF\xBB\xBF");
    foreach ($baris as $r) {
        // Sel diawali = + - @ diberi kutip satu agar tidak dijalankan Excel sebagai rumus
        fputcsv($o, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v, $r), ';', '"', '');
    }
    fclose($o);
    exit;
}

// ---------------------------------------------------------------- ikon

function icon(string $name, int $size = 18): string
{
    // Ikon garis gaya Lucide, disematkan agar tetap jalan tanpa internet
    static $p = [
        'home' => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'pena' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'grid' => '<rect width="18" height="18" x="3" y="3" rx="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/>',
        'buku' => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H19a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6.5a1 1 0 0 1 0-5H20"/>',
        'kelas' => '<path d="M2 20h20M5 20V9l7-5 7 5v11"/><path d="M10 20v-5h4v5"/>',
        'cetak' => '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/>',
        'unduh' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
        'unggah' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
        'setel' => '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>',
        'keluar' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
        'plus' => '<path d="M5 12h14M12 5v14"/>',
        'centang' => '<path d="M20 6 9 17l-5-5"/>',
        'silang' => '<path d="M18 6 6 18M6 6l12 12"/>',
        'cari' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'kunci' => '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'buka' => '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 9.9-1"/>',
        'kiri' => '<path d="m12 19-7-7 7-7M19 12H5"/>',
        'kanan' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'grafik' => '<path d="M3 3v18h18"/><path d="M18 17V9M13 17V5M8 17v-3"/>',
        'kalender' => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'bintang' => '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
        'awas' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'sampah' => '<path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
        'mata' => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'salin' => '<rect width="14" height="14" x="8" y="8" rx="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
    ];
    return '<svg class="ic" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? $p['info']) . '</svg>';
}
