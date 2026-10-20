<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "localhost";
$user = "root";
$pass = "P*ssword604";
$db   = "lms_db";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Inisialisasi CSRF Token jika belum ada
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fungsi Helper Keamanan & Output
function e($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

function setPesan(string $tipe, string $teks): void {
    $_SESSION['pesan'] = [
        'tipe' => $tipe,
        'teks' => $teks
    ];
}

function ambilPesan() {
    if (isset($_SESSION['pesan'])) {
        $pesan = $_SESSION['pesan'];
        unset($_SESSION['pesan']);
        return $pesan;
    }
    return null;
}

function validasiId($id): ?int {
    $filtered = filter_var($id, FILTER_VALIDATE_INT);
    return ($filtered !== false && $filtered > 0) ? (int)$filtered : null;
}

function cekTokenCsrf(?string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token ?? '');
}

// Fungsi Hitung Data untuk Beranda
function hitungData(mysqli $conn, string $namaTabel): int {
    $result = $conn->query("SELECT COUNT(*) as total FROM $namaTabel");
    if ($result) {
        $row = $result->fetch_assoc();
        return (int)$row['total'];
    }
    return 0;
}
?>