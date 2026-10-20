<?php
require 'koneksi.php';

$aksi = $_GET['aksi'] ?? 'daftar';
$errors = [];
$statusDiizinkan = ['Draf', 'Aktif', 'Selesai'];

$form = [
    'mata_kuliah_id' => $_GET['mk_id'] ?? '',
    'judul'          => '',
    'instruksi'      => '',
    'tenggat'        => '',
    'status'         => 'Draf'
];

$stmtMk = $conn->prepare('SELECT id, kode, nama FROM mata_kuliah ORDER BY kode ASC');
$stmtMk->execute();
$resMk = $stmtMk->get_result();
$opsiMataKuliah = [];
while ($barisMk = $resMk->fetch_assoc()) {
    $opsiMataKuliah[$barisMk['id']] = $barisMk;
}

function validasiTugas(array $input, array $opsiMataKuliah, array $statusDiizinkan): array {
    $err = [];
    $mkId      = validasiId($input['mata_kuliah_id'] ?? null);
    $judul     = trim($input['judul'] ?? '');
    $instruksi = trim($input['instruksi'] ?? '');
    $tenggat   = trim($input['tenggat'] ?? '');
    $status    = trim($input['status'] ?? '');

    if ($mkId === null || !isset($opsiMataKuliah[$mkId])) {
        $err['mata_kuliah_id'] = 'Pilih mata kuliah yang valid dari daftar yang tersedia.';
    }

    $pjgJudul = mb_strlen($judul);
    if ($pjgJudul < 3 || $pjgJudul > 150) {
        $err['judul'] = 'Judul tugas harus antara 3 sampai 150 karakter.';
    }

    $pjgInstruksi = mb_strlen($instruksi);
    if ($pjgInstruksi < 3 || $pjgInstruksi > 2000) {
        $err['instruksi'] = 'Instruksi tugas harus antara 3 sampai 2000 karakter.';
    }

    $dt = DateTime::createFromFormat('Y-m-d', $tenggat);
    $dtErrors = DateTime::getLastErrors();
    $jumlahWarning = is_array($dtErrors) ? $dtErrors['warning_count'] : 0;
    $jumlahError   = is_array($dtErrors) ? $dtErrors['error_count'] : 0;
    if (!$dt || $dt->format('Y-m-d') !== $tenggat || $jumlahWarning > 0 || $jumlahError > 0) {
        $err['tenggat'] = 'Masukkan tanggal tenggat yang valid.';
    }

    if (!in_array($status, $statusDiizinkan, true)) {
        $err['status'] = 'Status tugas hanya boleh dipilih antara Draf, Aktif, atau Selesai.';
    }

    return $err;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksiPost = $_POST['aksi_post'] ?? '';
    $token    = $_POST['csrf_token'] ?? '';

    if (!cekTokenCsrf($token)) {
        setPesan('error', 'Token keamanan tidak valid atau sesi telah berakhir.');
        header('Location: tugas.php');
        exit;
    }

    if ($aksiPost === 'tambah') {
        $form = [
            'mata_kuliah_id' => trim($_POST['mata_kuliah_id'] ?? ''),
            'judul'          => trim($_POST['judul'] ?? ''),
            'instruksi'      => trim($_POST['instruksi'] ?? ''),
            'tenggat'        => trim($_POST['tenggat'] ?? ''),
            'status'         => trim($_POST['status'] ?? '')
        ];

        $errors = validasiTugas($form, $opsiMataKuliah, $statusDiizinkan);
        if (empty($errors)) {
            $mkId = (int)$form['mata_kuliah_id'];
            $stmt = $conn->prepare('INSERT INTO tugas (mata_kuliah_id, judul, instruksi, tenggat, status) VALUES (?, ?, ?, ?, ?)');
            $stmt->bind_param('issss', $mkId, $form['judul'], $form['instruksi'], $form['tenggat'], $form['status']);
            $stmt->execute();

            setPesan('success', 'Tugas berhasil ditambahkan.');
            header('Location: tugas.php');
            exit;
        }
        $aksi = 'tambah';
    } elseif ($aksiPost === 'edit') {
        $id = validasiId($_POST['id'] ?? null);
        if ($id === null) {
            setPesan('error', 'ID tugas tidak valid.');
            header('Location: tugas.php');
            exit;
        }

        $stmtCek = $conn->prepare('SELECT id, mata_kuliah_id, judul, instruksi, tenggat, status FROM tugas WHERE id = ?');
        $stmtCek->bind_param('i', $id);
        $stmtCek->execute();
        $dataLama = $stmtCek->get_result()->fetch_assoc();

        if (!$dataLama) {
            setPesan('error', 'Data tugas tidak ditemukan.');
            header('Location: tugas.php');
            exit;
        }

        $form = [
            'mata_kuliah_id' => trim($_POST['mata_kuliah_id'] ?? ''),
            'judul'          => trim($_POST['judul'] ?? ''),
            'instruksi'      => trim($_POST['instruksi'] ?? ''),
            'tenggat'        => trim($_POST['tenggat'] ?? ''),
            'status'         => trim($_POST['status'] ?? '')
        ];

        $errors = validasiTugas($form, $opsiMataKuliah, $statusDiizinkan);
        if (empty($errors)) {
            $mkId = (int)$form['mata_kuliah_id'];

            if (
                (int)$dataLama['mata_kuliah_id'] === $mkId &&
                $dataLama['judul'] === $form['judul'] &&
                $dataLama['instruksi'] === $form['instruksi'] &&
                $dataLama['tenggat'] === $form['tenggat'] &&
                $dataLama['status'] === $form['status']
            ) {
                setPesan('info', 'Tidak ada perubahan data yang disimpan.');
                header('Location: tugas.php');
                exit;
            }

            $stmt = $conn->prepare('UPDATE tugas SET mata_kuliah_id = ?, judul = ?, instruksi = ?, tenggat = ?, status = ? WHERE id = ?');
            $stmt->bind_param('issssi', $mkId, $form['judul'], $form['instruksi'], $form['tenggat'], $form['status'], $id);
            $stmt->execute();

            setPesan('success', 'Data tugas berhasil diperbarui.');
            header('Location: tugas.php');
            exit;
        }
        $aksi = 'edit';
        $_GET['id'] = (string)$id;
    } elseif ($aksiPost === 'hapus') {
        $id = validasiId($_POST['id'] ?? null);
        if ($id === null) {
            setPesan('error', 'ID tugas tidak valid.');
            header('Location: tugas.php');
            exit;
        }

        $stmtCek = $conn->prepare('SELECT id FROM tugas WHERE id = ?');
        $stmtCek->bind_param('i', $id);
        $stmtCek->execute();
        if ($stmtCek->get_result()->num_rows === 0) {
            setPesan('error', 'Data tugas tidak ditemukan.');
            header('Location: tugas.php');
            exit;
        }

        $stmtDel = $conn->prepare('DELETE FROM tugas WHERE id = ?');
        $stmtDel->bind_param('i', $id);
        $stmtDel->execute();

        setPesan('success', 'Data tugas berhasil dihapus.');
        header('Location: tugas.php');
        exit;
    }
}

$pesan = ambilPesan();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tugas - LMS Praktikum PAW</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="brand">LMS Praktikum PAW</a>
            <div class="nav-links">
                <a href="index.php">Beranda</a>
                <a href="mata_kuliah.php">Mata kuliah</a>
                <a href="materi.php">Materi</a>
                <a href="tugas.php" class="active">Tugas</a>
            </div>
        </div>
    </nav>

    <main class="container">
        <?php if ($pesan): ?>
            <div class="alert alert-<?= e($pesan['tipe']) ?>">
                <?= e($pesan['teks']) ?>
            </div>
        <?php endif; ?>

        <?php
        if ($aksi === 'tambah' || $aksi === 'edit'):
            $idEdit = null;
            if ($aksi === 'edit') {
                $idEdit = validasiId($_GET['id'] ?? null);
                if ($idEdit === null) {
                    echo '<div class="alert alert-error">ID tugas tidak valid.</div>';
                    echo '<a href="tugas.php" class="btn btn-secondary">Kembali</a>';
                    echo '</main></body></html>';
                    exit;
                }

                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    $stmt = $conn->prepare('SELECT mata_kuliah_id, judul, instruksi, tenggat, status FROM tugas WHERE id = ?');
                    $stmt->bind_param('i', $idEdit);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    if (!$row) {
                        echo '<div class="alert alert-error">Data tugas tidak ditemukan.</div>';
                        echo '<a href="tugas.php" class="btn btn-secondary">Kembali</a>';
                        echo '</main></body></html>';
                        exit;
                    }
                    $form = $row;
                }
            }
        ?>
            <div class="card">
                <h1><?= $aksi === 'tambah' ? 'Tambah Tugas' : 'Edit Tugas' ?></h1>
                <p class="subtitle">Kelola instruksi tugas, tenggat waktu, dan status pengerjaan.</p>

                <form method="post" action="tugas.php<?= $aksi === 'edit' ? '?aksi=edit&id=' . e($idEdit) : '?aksi=tambah' ?>" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="aksi_post" value="<?= e($aksi) ?>">
                    <?php if ($aksi === 'edit'): ?>
                        <input type="hidden" name="id" value="<?= e($idEdit) ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="mata_kuliah_id">Mata kuliah</label>
                        <select id="mata_kuliah_id" name="mata_kuliah_id" class="form-control <?= isset($errors['mata_kuliah_id']) ? 'is-invalid' : '' ?>" required>
                            <option value="">Pilih Mata kuliah</option>
                            <?php foreach ($opsiMataKuliah as $idMk => $mk): ?>
                                <option value="<?= e($idMk) ?>" <?= (string)$form['mata_kuliah_id'] === (string)$idMk ? 'selected' : '' ?>>
                                    <?= e($mk['kode']) ?> - <?= e($mk['nama']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['mata_kuliah_id'])): ?>
                            <div class="field-error"><?= e($errors['mata_kuliah_id']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="judul">Judul tugas</label>
                        <input type="text" id="judul" name="judul" class="form-control <?= isset($errors['judul']) ? 'is-invalid' : '' ?>" value="<?= e($form['judul']) ?>" required>
                        <?php if (isset($errors['judul'])): ?>
                            <div class="field-error"><?= e($errors['judul']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="instruksi">Instruksi tugas</label>
                        <textarea id="instruksi" name="instruksi" class="form-control <?= isset($errors['instruksi']) ? 'is-invalid' : '' ?>" required><?= e($form['instruksi']) ?></textarea>
                        <?php if (isset($errors['instruksi'])): ?>
                            <div class="field-error"><?= e($errors['instruksi']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="tenggat">Tenggat</label>
                        <input type="date" id="tenggat" name="tenggat" class="form-control <?= isset($errors['tenggat']) ? 'is-invalid' : '' ?>" value="<?= e($form['tenggat']) ?>" required>
                        <?php if (isset($errors['tenggat'])): ?>
                            <div class="field-error"><?= e($errors['tenggat']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" name="status" class="form-control <?= isset($errors['status']) ? 'is-invalid' : '' ?>" required>
                            <?php foreach ($statusDiizinkan as $st): ?>
                                <option value="<?= e($st) ?>" <?= $form['status'] === $st ? 'selected' : '' ?>>
                                    <?= e($st) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['status'])): ?>
                            <div class="field-error"><?= e($errors['status']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Simpan data</button>
                        <a href="tugas.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>

        <?php
        elseif ($aksi === 'hapus'):
            $idHapus = validasiId($_GET['id'] ?? null);
            if ($idHapus === null) {
                echo '<div class="alert alert-error">ID tugas tidak valid.</div>';
                echo '<a href="tugas.php" class="btn btn-secondary">Kembali</a>';
                echo '</main></body></html>';
                exit;
            }

            $stmt = $conn->prepare('SELECT t.id, t.judul, mk.kode, mk.nama AS nama_mk FROM tugas t JOIN mata_kuliah mk ON t.mata_kuliah_id = mk.id WHERE t.id = ?');
            $stmt->bind_param('i', $idHapus);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();

            if (!$item) {
                echo '<div class="alert alert-error">Data tugas tidak ditemukan.</div>';
                echo '<a href="tugas.php" class="btn btn-secondary">Kembali</a>';
                echo '</main></body></html>';
                exit;
            }
        ?>
            <div class="card">
                <h1>Tugas</h1>
                <p style="margin: 14px 0;">Hapus tugas <strong><?= e($item['judul']) ?></strong> pada mata kuliah <?= e($item['kode']) ?> - <?= e($item['nama_mk']) ?>?</p>
                <p style="color: #4b5563; margin-bottom: 20px;">Data baru dihapus setelah tombol konfirmasi dikirim.</p>

                <form method="post" action="tugas.php?aksi=hapus&id=<?= e($item['id']) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="aksi_post" value="hapus">
                    <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger">Ya, hapus data</button>
                        <a href="tugas.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>

        <?php
        elseif ($aksi === 'detail'):
            $idDetail = validasiId($_GET['id'] ?? null);
            if ($idDetail === null) {
                echo '<div class="alert alert-error">ID tugas tidak valid.</div>';
                echo '<a href="tugas.php" class="btn btn-secondary">Kembali</a>';
                echo '</main></body></html>';
                exit;
            }

            $stmt = $conn->prepare('SELECT t.id, t.judul, t.instruksi, t.tenggat, t.status, mk.kode, mk.nama AS nama_mk FROM tugas t JOIN mata_kuliah mk ON t.mata_kuliah_id = mk.id WHERE t.id = ?');
            $stmt->bind_param('i', $idDetail);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();

            if (!$item) {
                echo '<div class="alert alert-error">Data tugas tidak ditemukan.</div>';
                echo '<a href="tugas.php" class="btn btn-secondary">Kembali</a>';
                echo '</main></body></html>';
                exit;
            }
        ?>
            <div class="card">
                <p class="subtitle" style="margin-bottom:4px;">Tugas</p>
                <h1><?= e($item['judul']) ?></h1>

                <table class="detail-table">
                    <tr>
                        <th>Mata kuliah</th>
                        <td><?= e($item['kode']) ?> - <?= e($item['nama_mk']) ?></td>
                    </tr>
                    <tr>
                        <th>Judul tugas</th>
                        <td><?= e($item['judul']) ?></td>
                    </tr>
                    <tr>
                        <th>Instruksi tugas</th>
                        <td style="white-space: pre-line;"><?= e($item['instruksi']) ?></td>
                    </tr>
                    <tr>
                        <th>Tenggat</th>
                        <td><?= e($item['tenggat']) ?></td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>
                            <span class="badge badge-<?= e(strtolower($item['status'])) ?>">
                                <?= e($item['status']) ?>
                            </span>
                        </td>
                    </tr>
                </table>

                <div class="form-actions">
                    <a href="tugas.php?aksi=edit&id=<?= e($item['id']) ?>" class="btn btn-primary">Edit data</a>
                    <a href="tugas.php" class="btn btn-secondary">Kembali</a>
                </div>
            </div>

        <?php
        else:
            $q    = trim($_GET['q'] ?? '');
            $mkId = validasiId($_GET['mata_kuliah_id'] ?? null);

            $sql = 'SELECT t.id, t.judul, t.tenggat, t.status, mk.kode, mk.nama AS nama_mk FROM tugas t JOIN mata_kuliah mk ON t.mata_kuliah_id = mk.id WHERE 1=1';
            $params = [];
            $types  = '';

            if ($q !== '') {
                $sql .= ' AND t.judul LIKE ?';
                $params[] = '%' . $q . '%';
                $types .= 's';
            }

            if ($mkId !== null) {
                $sql .= ' AND t.mata_kuliah_id = ?';
                $params[] = $mkId;
                $types .= 'i';
            }

            $sql .= ' ORDER BY t.tenggat ASC, t.id DESC';
            $stmt = $conn->prepare($sql);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $hasil = $stmt->get_result();
        ?>
            <div class="card">
                <div class="page-header">
                    <div>
                        <h1>Tugas</h1>
                        <p class="subtitle" style="margin-bottom:0;">Menampilkan <?= e($hasil->num_rows) ?> data.</p>
                    </div>
                    <a href="tugas.php?aksi=tambah" class="btn btn-primary">Tambah tugas</a>
                </div>

                <form method="get" action="tugas.php" class="filter-bar">
                    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari judul tugas...">
                    <select name="mata_kuliah_id">
                        <option value="">Semua mata kuliah</option>
                        <?php foreach ($opsiMataKuliah as $idOpt => $mk): ?>
                            <option value="<?= e($idOpt) ?>" <?= $mkId === (int)$idOpt ? 'selected' : '' ?>>
                                <?= e($mk['kode']) ?> - <?= e($mk['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Cari</button>
                    <a href="tugas.php" class="btn btn-secondary">Reset</a>
                </form>

                <?php if ($hasil->num_rows > 0): ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Judul</th>
                                    <th>Mata kuliah</th>
                                    <th>Tenggat</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $hasil->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= e($row['judul']) ?></td>
                                        <td><?= e($row['kode']) ?> - <?= e($row['nama_mk']) ?></td>
                                        <td><?= e($row['tenggat']) ?></td>
                                        <td>
                                            <span class="badge badge-<?= e(strtolower($row['status'])) ?>">
                                                <?= e($row['status']) ?>
                                            </span>
                                        </td>
                                        <td class="actions">
                                            <a href="tugas.php?aksi=detail&id=<?= e($row['id']) ?>">Detail</a>
                                            <a href="tugas.php?aksi=edit&id=<?= e($row['id']) ?>">Edit</a>
                                            <a href="tugas.php?aksi=hapus&id=<?= e($row['id']) ?>" class="delete-link">Hapus</a>
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