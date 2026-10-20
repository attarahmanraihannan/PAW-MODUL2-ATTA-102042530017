<?php
require 'koneksi.php';

$aksi = $_GET['aksi'] ?? 'daftar';
$errors = [];
$pesanErrorUmum = '';

$form = [
    'kode'     => '',
    'nama'     => '',
    'dosen'    => '',
    'semester' => ''
];

function validasiMataKuliah(array $input, mysqli $conn, ?int $idSekarang = null): array {
    $err = [];
    $kode     = trim($input['kode'] ?? '');
    $nama     = trim($input['nama'] ?? '');
    $dosen    = trim($input['dosen'] ?? '');
    $semester = trim((string)($input['semester'] ?? ''));

    if ($kode === '' || !preg_match('/^[A-Z0-9-]{3,20}$/', $kode)) {
        $err['kode'] = 'Kode mata kuliah harus 3-20 karakter (huruf kapital A-Z, angka, atau tanda -).';
    } else {
        if ($idSekarang === null) {
            $stmt = $conn->prepare('SELECT id FROM mata_kuliah WHERE kode = ?');
            $stmt->bind_param('s', $kode);
        } else {
            $stmt = $conn->prepare('SELECT id FROM mata_kuliah WHERE kode = ? AND id != ?');
            $stmt->bind_param('si', $kode, $idSekarang);
        }
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $err['kode'] = 'Kode mata kuliah sudah digunakan.';
        }
    }

    $panjangNama = mb_strlen($nama);
    if ($panjangNama < 3 || $panjangNama > 100) {
        $err['nama'] = 'Nama mata kuliah harus antara 3 sampai 100 karakter.';
    }

    $panjangDosen = mb_strlen($dosen);
    if ($panjangDosen < 3 || $panjangDosen > 100) {
        $err['dosen'] = 'Nama dosen pengampu harus antara 3 sampai 100 karakter.';
    }

    $semInt = filter_var($semester, FILTER_VALIDATE_INT);
    if ($semInt === false || $semInt < 1 || $semInt > 14) {
        $err['semester'] = 'Semester harus berupa bilangan bulat antara 1 sampai 14.';
    }

    return $err;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksiPost = $_POST['aksi_post'] ?? '';
    $token    = $_POST['csrf_token'] ?? '';

    if (!cekTokenCsrf($token)) {
        setPesan('error', 'Token keamanan tidak valid atau sesi telah berakhir. Silakan coba lagi.');
        header('Location: mata_kuliah.php');
        exit;
    }

    if ($aksiPost === 'tambah') {
        $form = [
            'kode'     => trim($_POST['kode'] ?? ''),
            'nama'     => trim($_POST['nama'] ?? ''),
            'dosen'    => trim($_POST['dosen'] ?? ''),
            'semester' => trim($_POST['semester'] ?? '')
        ];

        $errors = validasiMataKuliah($form, $conn);
        if (empty($errors)) {
            try {
                $semInt = (int)$form['semester'];
                $stmt = $conn->prepare('INSERT INTO mata_kuliah (kode, nama, dosen, semester) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('sssi', $form['kode'], $form['nama'], $form['dosen'], $semInt);
                $stmt->execute();

                setPesan('success', 'Mata kuliah berhasil ditambahkan.');
                header('Location: mata_kuliah.php');
                exit;
            } catch (mysqli_sql_exception $e) {
                $pesanErrorUmum = 'Gagal menyimpan data mata kuliah. Pastikan kode belum digunakan.';
            }
        }
        $aksi = 'tambah';
    } elseif ($aksiPost === 'edit') {
        $id = validasiId($_POST['id'] ?? null);
        if ($id === null) {
            setPesan('error', 'ID mata kuliah tidak valid.');
            header('Location: mata_kuliah.php');
            exit;
        }

        $stmtCek = $conn->prepare('SELECT id, kode, nama, dosen, semester FROM mata_kuliah WHERE id = ?');
        $stmtCek->bind_param('i', $id);
        $stmtCek->execute();
        $dataLama = $stmtCek->get_result()->fetch_assoc();

        if (!$dataLama) {
            setPesan('error', 'Data mata kuliah tidak ditemukan.');
            header('Location: mata_kuliah.php');
            exit;
        }

        $form = [
            'kode'     => trim($_POST['kode'] ?? ''),
            'nama'     => trim($_POST['nama'] ?? ''),
            'dosen'    => trim($_POST['dosen'] ?? ''),
            'semester' => trim($_POST['semester'] ?? '')
        ];

        $errors = validasiMataKuliah($form, $conn, $id);
        if (empty($errors)) {
            $semInt = (int)$form['semester'];

            if (
                $dataLama['kode'] === $form['kode'] &&
                $dataLama['nama'] === $form['nama'] &&
                $dataLama['dosen'] === $form['dosen'] &&
                (int)$dataLama['semester'] === $semInt
            ) {
                setPesan('info', 'Tidak ada perubahan data yang disimpan.');
                header('Location: mata_kuliah.php');
                exit;
            }

            try {
                $stmt = $conn->prepare('UPDATE mata_kuliah SET kode = ?, nama = ?, dosen = ?, semester = ? WHERE id = ?');
                $stmt->bind_param('sssii', $form['kode'], $form['nama'], $form['dosen'], $semInt, $id);
                $stmt->execute();

                setPesan('success', 'Data mata kuliah berhasil diperbarui.');
                header('Location: mata_kuliah.php');
                exit;
            } catch (mysqli_sql_exception $e) {
                $pesanErrorUmum = 'Gagal memperbarui mata kuliah.';
            }
        }
        $aksi = 'edit';
        $_GET['id'] = (string)$id;
    } elseif ($aksiPost === 'hapus') {
        $id = validasiId($_POST['id'] ?? null);
        if ($id === null) {
            setPesan('error', 'ID mata kuliah tidak valid.');
            header('Location: mata_kuliah.php');
            exit;
        }

        $stmtCek = $conn->prepare('SELECT id, kode, nama FROM mata_kuliah WHERE id = ?');
        $stmtCek->bind_param('i', $id);
        $stmtCek->execute();
        $dataHapus = $stmtCek->get_result()->fetch_assoc();

        if (!$dataHapus) {
            setPesan('error', 'Data mata kuliah tidak ditemukan.');
            header('Location: mata_kuliah.php');
            exit;
        }

        try {
            $stmtDel = $conn->prepare('DELETE FROM mata_kuliah WHERE id = ?');
            $stmtDel->bind_param('i', $id);
            $stmtDel->execute();

            setPesan('success', 'Mata kuliah berhasil dihapus.');
            header('Location: mata_kuliah.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            $pesanErrorUmum = 'Data belum tersimpan. Mata kuliah masih memiliki materi atau tugas. Hapus data terkait terlebih dahulu.';
            $aksi = 'hapus';
            $_GET['id'] = (string)$id;
        }
    }
}

$pesan = ambilPesan();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mata Kuliah - LMS Praktikum PAW</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="brand">LMS Praktikum PAW</a>
            <div class="nav-links">
                <a href="index.php">Beranda</a>
                <a href="mata_kuliah.php" class="active">Mata kuliah</a>
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

        <?php if ($pesanErrorUmum !== ''): ?>
            <div class="alert alert-error">
                <?= e($pesanErrorUmum) ?>
            </div>
        <?php endif; ?>

        <?php
        if ($aksi === 'tambah' || $aksi === 'edit'):
            $idEdit = null;
            if ($aksi === 'edit') {
                $idEdit = validasiId($_GET['id'] ?? null);
                if ($idEdit === null) {
                    echo '<div class="alert alert-error">ID mata kuliah tidak valid.</div>';
                    echo '<a href="mata_kuliah.php" class="btn btn-secondary">Kembali</a>';
                    echo '</main></body></html>';
                    exit;
                }

                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    $stmt = $conn->prepare('SELECT kode, nama, dosen, semester FROM mata_kuliah WHERE id = ?');
                    $stmt->bind_param('i', $idEdit);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    if (!$row) {
                        echo '<div class="alert alert-error">Data mata kuliah tidak ditemukan.</div>';
                        echo '<a href="mata_kuliah.php" class="btn btn-secondary">Kembali</a>';
                        echo '</main></body></html>';
                        exit;
                    }
                    $form = $row;
                }
            }
        ?>
            <div class="card">
                <h1><?= $aksi === 'tambah' ? 'Tambah Mata Kuliah' : 'Edit Mata Kuliah' ?></h1>
                <p class="subtitle">Lengkapi isian sesuai ketentuan data LMS.</p>

                <form method="post" action="mata_kuliah.php<?= $aksi === 'edit' ? '?aksi=edit&id=' . e($idEdit) : '?aksi=tambah' ?>" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="aksi_post" value="<?= e($aksi) ?>">
                    <?php if ($aksi === 'edit'): ?>
                        <input type="hidden" name="id" value="<?= e($idEdit) ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="kode">Kode mata kuliah</label>
                        <input type="text" id="kode" name="kode" class="form-control <?= isset($errors['kode']) ? 'is-invalid' : '' ?>" value="<?= e($form['kode']) ?>" placeholder="Contoh: PAW-101" required>
                        <?php if (isset($errors['kode'])): ?>
                            <div class="field-error"><?= e($errors['kode']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="nama">Nama mata kuliah</label>
                        <input type="text" id="nama" name="nama" class="form-control <?= isset($errors['nama']) ? 'is-invalid' : '' ?>" value="<?= e($form['nama']) ?>" placeholder="Contoh: Pengembangan Aplikasi Website" required>
                        <?php if (isset($errors['nama'])): ?>
                            <div class="field-error"><?= e($errors['nama']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="dosen">Dosen pengampu</label>
                        <input type="text" id="dosen" name="dosen" class="form-control <?= isset($errors['dosen']) ? 'is-invalid' : '' ?>" value="<?= e($form['dosen']) ?>" placeholder="Contoh: Dosen PAW" required>
                        <?php if (isset($errors['dosen'])): ?>
                            <div class="field-error"><?= e($errors['dosen']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="semester">Semester (1-14)</label>
                        <input type="number" id="semester" name="semester" min="1" max="14" class="form-control <?= isset($errors['semester']) ? 'is-invalid' : '' ?>" value="<?= e($form['semester']) ?>" required>
                        <?php if (isset($errors['semester'])): ?>
                            <div class="field-error"><?= e($errors['semester']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Simpan data</button>
                        <a href="mata_kuliah.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>

        <?php
        elseif ($aksi === 'hapus'):
            $idHapus = validasiId($_GET['id'] ?? null);
            if ($idHapus === null) {
                echo '<div class="alert alert-error">ID mata kuliah tidak valid.</div>';
                echo '<a href="mata_kuliah.php" class="btn btn-secondary">Kembali</a>';
                echo '</main></body></html>';
                exit;
            }

            $stmt = $conn->prepare('SELECT id, kode, nama FROM mata_kuliah WHERE id = ?');
            $stmt->bind_param('i', $idHapus);
            $stmt->execute();
            $mk = $stmt->get_result()->fetch_assoc();

            if (!$mk) {
                echo '<div class="alert alert-error">Data mata kuliah tidak ditemukan.</div>';
                echo '<a href="mata_kuliah.php" class="btn btn-secondary">Kembali</a>';
                echo '</main></body></html>';
                exit;
            }
        ?>
            <div class="card">
                <h1>Mata kuliah</h1>
                <p style="margin: 14px 0;">Hapus <strong><?= e($mk['nama']) ?></strong> (<?= e($mk['kode']) ?>)?</p>
                <p style="color: #4b5563; margin-bottom: 20px;">Data baru dihapus setelah tombol konfirmasi dikirim.</p>

                <form method="post" action="mata_kuliah.php?aksi=hapus&id=<?= e($mk['id']) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="aksi_post" value="hapus">
                    <input type="hidden" name="id" value="<?= e($mk['id']) ?>">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger">Ya, hapus data</button>
                        <a href="mata_kuliah.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>

        <?php
        elseif ($aksi === 'detail'):
            $idDetail = validasiId($_GET['id'] ?? null);
            if ($idDetail === null) {
                echo '<div class="alert alert-error">ID mata kuliah tidak valid.</div>';
                echo '<a href="mata_kuliah.php" class="btn btn-secondary">Kembali</a>';
                echo '</main></body></html>';
                exit;
            }

            $stmt = $conn->prepare('SELECT id, kode, nama, dosen, semester FROM mata_kuliah WHERE id = ?');
            $stmt->bind_param('i', $idDetail);
            $stmt->execute();
            $mk = $stmt->get_result()->fetch_assoc();

            if (!$mk) {
                echo '<div class="alert alert-error">Data mata kuliah tidak ditemukan.</div>';
                echo '<a href="mata_kuliah.php" class="btn btn-secondary">Kembali</a>';
                echo '</main></body></html>';
                exit;
            }

            $stmtMateri = $conn->prepare('SELECT id, judul, tautan FROM materi WHERE mata_kuliah_id = ? ORDER BY id DESC');
            $stmtMateri->bind_param('i', $idDetail);
            $stmtMateri->execute();
            $listMateri = $stmtMateri->get_result();

            $stmtTugas = $conn->prepare('SELECT id, judul, tenggat, status FROM tugas WHERE mata_kuliah_id = ? ORDER BY tenggat ASC');
            $stmtTugas->bind_param('i', $idDetail);
            $stmtTugas->execute();
            $listTugas = $stmtTugas->get_result();
        ?>
            <div class="card">
                <p class="subtitle" style="margin-bottom:4px;">Mata kuliah</p>
                <h1><?= e($mk['kode']) ?> - <?= e($mk['nama']) ?></h1>

                <table class="detail-table">
                    <tr>
                        <th>Kode mata kuliah</th>
                        <td><?= e($mk['kode']) ?></td>
                    </tr>
                    <tr>
                        <th>Nama mata kuliah</th>
                        <td><?= e($mk['nama']) ?></td>
                    </tr>
                    <tr>
                        <th>Dosen pengampu</th>
                        <td><?= e($mk['dosen']) ?></td>
                    </tr>
                    <tr>
                        <th>Semester</th>
                        <td>Semester <?= e($mk['semester']) ?></td>
                    </tr>
                </table>

                <div class="form-actions">
                    <a href="mata_kuliah.php?aksi=edit&id=<?= e($mk['id']) ?>" class="btn btn-primary">Edit data</a>
                    <a href="mata_kuliah.php" class="btn btn-secondary">Kembali</a>
                </div>
            </div>

            <div class="card">
                <div class="page-header">
                    <h2>Materi pada Mata Kuliah Ini</h2>
                    <a href="materi.php?aksi=tambah&mk_id=<?= e($mk['id']) ?>" class="btn btn-primary">Tambah materi</a>
                </div>
                <?php if ($listMateri->num_rows > 0): ?>
                    <ul class="list-mk">
                        <?php while ($m = $listMateri->fetch_assoc()): ?>
                            <li>
                                <a href="materi.php?aksi=detail&id=<?= e($m['id']) ?>"><?= e($m['judul']) ?></a>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p style="color:#6b7280;">Belum ada materi untuk mata kuliah ini.</p>
                <?php endif; ?>
            </div>

            <div class="card">
                <div class="page-header">
                    <h2>Tugas pada Mata Kuliah Ini</h2>
                    <a href="tugas.php?aksi=tambah&mk_id=<?= e($mk['id']) ?>" class="btn btn-primary">Tambah tugas</a>
                </div>
                <?php if ($listTugas->num_rows > 0): ?>
                    <ul class="list-mk">
                        <?php while ($t = $listTugas->fetch_assoc()): ?>
                            <li>
                                <a href="tugas.php?aksi=detail&id=<?= e($t['id']) ?>"><?= e($t['judul']) ?></a>
                                <span style="color:#6b7280; font-size:0.9rem;"> (Tenggat: <?= e($t['tenggat']) ?> - Status: <?= e($t['status']) ?>)</span>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p style="color:#6b7280;">Belum ada tugas untuk mata kuliah ini.</p>
                <?php endif; ?>
            </div>

        <?php
        else:
            $q = trim($_GET['q'] ?? '');
            if ($q !== '') {
                $like = '%' . $q . '%';
                $stmt = $conn->prepare('SELECT id, kode, nama, dosen, semester FROM mata_kuliah WHERE kode LIKE ? OR nama LIKE ? ORDER BY kode ASC');
                $stmt->bind_param('ss', $like, $like);
            } else {
                $stmt = $conn->prepare('SELECT id, kode, nama, dosen, semester FROM mata_kuliah ORDER BY kode ASC');
            }
            $stmt->execute();
            $hasil = $stmt->get_result();
        ?>
            <div class="card">
                <div class="page-header">
                    <div>
                        <h1>Mata kuliah</h1>
                        <p class="subtitle" style="margin-bottom:0;">Menampilkan <?= e($hasil->num_rows) ?> data.</p>
                    </div>
                    <a href="mata_kuliah.php?aksi=tambah" class="btn btn-primary">Tambah mata kuliah</a>
                </div>

                <form method="get" action="mata_kuliah.php" class="filter-bar">
                    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari kode atau nama mata kuliah...">
                    <button type="submit" class="btn btn-primary">Cari</button>
                    <a href="mata_kuliah.php" class="btn btn-secondary">Reset</a>
                </form>

                <?php if ($hasil->num_rows > 0): ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Mata kuliah</th>
                                    <th>Dosen / Semester</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $hasil->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong><?= e($row['kode']) ?></strong> - <?= e($row['nama']) ?></td>
                                        <td><?= e($row['dosen']) ?> / Semester <?= e($row['semester']) ?></td>
                                        <td class="actions">
                                            <a href="mata_kuliah.php?aksi=detail&id=<?= e($row['id']) ?>">Detail</a>
                                            <a href="mata_kuliah.php?aksi=edit&id=<?= e($row['id']) ?>">Edit</a>
                                            <a href="mata_kuliah.php?aksi=hapus&id=<?= e($row['id']) ?>" class="delete-link">Hapus</a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="padding: 16px 0; color: #4b5563;">Data tidak ditemukan.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>