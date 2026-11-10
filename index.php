<?php
require __DIR__ . '/includes/bootstrap.php';

// Halaman yang bisa dibuka tanpa login
$publik = ['login', 'cek'];

$routes = [
    'login'          => 'pages/login.php',
    'logout'         => 'pages/logout.php',
    'cek'            => 'pages/cek.php',
    'dashboard'      => 'pages/dashboard.php',
    // guru: nilai
    'nilai'          => 'pages/nilai/index.php',
    'nilai.input'    => 'pages/nilai/input.php',
    'nilai.aksi'     => 'pages/nilai/aksi.php',
    'nilai.csv'      => 'pages/nilai/csv.php',
    // wali kelas
    'wali'           => 'pages/wali/index.php',
    'wali.aksi'      => 'pages/wali/aksi.php',
    'leger'          => 'pages/leger.php',
    'rapor'          => 'pages/rapor.php',
    'progres'        => 'pages/progres.php',
    // admin
    'siswa'          => 'pages/siswa/index.php',
    'siswa.form'     => 'pages/siswa/form.php',
    'siswa.aksi'     => 'pages/siswa/aksi.php',
    'siswa.impor'    => 'pages/siswa/impor.php',
    'rombel'         => 'pages/rombel/index.php',
    'rombel.detail'  => 'pages/rombel/detail.php',
    'rombel.aksi'    => 'pages/rombel/aksi.php',
    'guru'           => 'pages/guru.php',
    'mapel'          => 'pages/mapel.php',
    'ekskul'         => 'pages/ekskul.php',
    'periode'        => 'pages/periode.php',
    'pengaturan'     => 'pages/pengaturan.php',
    'profil'         => 'pages/profil.php',
];

$page = $_GET['page'] ?? (user() ? 'dashboard' : 'login');
if (!isset($routes[$page])) {
    http_response_code(404);
    $page = user() ? 'dashboard' : 'login';
}
if (!in_array($page, $publik, true)) require_login();
verify_csrf();

require __DIR__ . '/' . $routes[$page];
