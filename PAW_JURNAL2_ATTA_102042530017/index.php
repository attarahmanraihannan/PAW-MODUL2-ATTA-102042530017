<?php
require 'koneksi.php';

$jumlahMataKuliah = hitungData($conn, 'mata_kuliah');
$jumlahMateri     = hitungData($conn, 'materi');
$jumlahTugas      = hitungData($conn, 'tugas');

$stmt = $conn->prepare('SELECT id, kode, nama, dosen, semester FROM mata_kuliah ORDER BY kode ASC');
$stmt->execute();
$daftarMataKuliah = $stmt->get_result();

$pesan = ambilPesan();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda - Learning Management System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="brand">LMS Praktikum PAW</a>
            <div class="nav-links">
                <a href="index.php" class="active">Beranda</a>
                <a href="mata_kuliah.php">Mata kuliah</a>
                <a href="materi.php">Materi</a>
                <a href="tugas.php">Tugas</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <?php if ($pesan): ?>
            <div class="alert alert-<?= e($pesan['tipe']) ?>">
                <?= e($pesan['teks']) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <h1>Learning Management System</h1>
            <p class="subtitle">Pengelolaan mata kuliah, materi pembelajaran, dan tugas.</p>

            <div class="summary-grid">
                <a href="mata_kuliah.php" class="summary-box">
                    <div class="label">Mata kuliah</div>
                    <div class="count"><?= e($jumlahMataKuliah) ?></div>
                </a>
                <a href="materi.php" class="summary-box">
                    <div class="label">Materi</div>
                    <div class="count"><?= e($jumlahMateri) ?></div>
                </a>
                <a href="tugas.php" class="summary-box">
                    <div class="label">Tugas</div>
                    <div class="count"><?= e($jumlahTugas) ?></div>
                </a>
            </div>

            <h2>Mata kuliah tersedia</h2>
            <?php if ($daftarMataKuliah->num_rows > 0): ?>
                <ul class="list-mk">
                    <?php while ($mk = $daftarMataKuliah->fetch_assoc()): ?>
                        <li>
                            <a href="mata_kuliah.php?aksi=detail&id=<?= e($mk['id']) ?>">
                                <?= e($mk['kode']) ?> - <?= e($mk['nama']) ?>
                            </a>
                            <span style="color: #6b7280; font-size: 0.9rem;">
                                (<?= e($mk['dosen']) ?> / Semester <?= e($mk['semester']) ?>)
                            </span>
                        </li>
                    <?php endwhile; ?>
                </ul>
            <?php else: ?>
                <p style="color: #6b7280;">Belum ada mata kuliah yang terdaftar.</p>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>