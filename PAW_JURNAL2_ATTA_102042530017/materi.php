<?php
require 'koneksi.php';

$aksi = $_GET['aksi'] ?? 'daftar';
$errors = [];
$pesanErrorUmum = '';

$form = [
    'mata_kuliah_id' => $_GET['mk_id'] ?? '',
    'judul'          => '',
    'isi'            => '',
    'tautan'         => ''
];

$stmtMk = $conn->prepare('SELECT id, kode, nama FROM mata_kuliah ORDER BY kode ASC');
$stmtMk->execute();
$resMk = $stmtMk->get_result();
$opsiMataKuliah = [];
while ($barisMk = $resMk->fetch_assoc()) {
    $opsiMataKuliah[$barisMk['id']] = $barisMk;
}

function validasiMateri(array $input, array $opsiMataKuliah): array {
    $err = [];
    $mkId   = validasiId($input['mata_kuliah_id'] ?? null);
    $judul  = trim($input['judul'] ?? '');
    $isi    = trim($input['isi'] ?? '');
    $tautan = trim($input['tautan'] ?? '');

    if ($mkId === null || !isset($opsiMataKuliah[$mkId])) {
        $err['mata_kuliah_id'] = 'Pilih mata kuliah yang valid dari daftar yang tersedia.';
    }

    $pjgJudul = mb_strlen($judul);
    if ($pjgJudul < 3 || $pjgJudul > 150) {
        $err['judul'] = 'Judul materi harus antara 3 sampai 150 karakter.';
    }

    $pjgIsi = mb_strlen($isi);
    if ($pjgIsi < 3 || $pjgIsi > 2000) {
        $err['isi'] = 'Isi materi harus antara 3 sampai 2000 karakter.';
    }

    if (
        $tautan === '' ||
        mb_strlen($tautan) > 255 ||
        !preg_match('/^https?:\/\/.+/i', $tautan) ||
        !filter_var($tautan, FILTER_VALIDATE_URL)
    ) {
        $err['tautan'] = 'Gunakan tautan http/https yang valid (maksimal 255 karakter).';
    }

    return $err;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $aksiPost = $_POST['aksi_post'] ?? '';
    $token    = $_POST['csrf_token'] ?? '';

    if (!cekTokenCsrf($token)) {
        setPesan('error', 'Token keamanan tidak valid atau sesi telah berakhir.');
        header('Location: materi.php');
        exit;
    }

    if ($aksiPost === 'tambah') {
        $form = [
            'mata_kuliah_id' => trim($_POST['mata_kuliah_id'] ?? ''),
            'judul'          => trim($_POST['judul'] ?? ''),
            'isi'            => trim($_POST['isi'] ?? ''),
            'tautan'         => trim($_POST['tautan'] ?? '')
        ];

        $errors = validasiMateri($form, $opsiMataKuliah);
        if (empty($errors)) {
            $mkId = (int)$form['mata_kuliah_id'];
            $stmt = $conn->prepare('INSERT INTO materi (mata_kuliah_id, judul, isi, tautan) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('isss', $mkId, $form['judul'], $form['isi'], $form['tautan']);
            $stmt->execute();

            setPesan('success', 'Materi pembelajaran berhasil ditambahkan.');
            header('Location: materi.php');
            exit;
        }
        $aksi = 'tambah';
    } elseif ($aksiPost === 'edit') {
        $id = validasiId($_POST['id'] ?? null);
        if ($id === null) {
            setPesan('error', 'ID materi tidak valid.');
            header('Location: materi.php');
            exit;
        }

        $stmtCek = $conn->prepare('SELECT id, mata_kuliah_id, judul, isi, tautan FROM materi WHERE id = ?');
        $stmtCek->bind_param('i', $id);
        $stmtCek->execute();
        $dataLama = $stmtCek->get_result()->fetch_assoc();

        if (!$dataLama) {
            setPesan('error', 'Data materi tidak ditemukan.');
            header('Location: materi.php');
            exit;
        }

        $form = [
            'mata_kuliah_id' => trim($_POST['mata_kuliah_id'] ?? ''),
            'judul'          => trim($_POST['judul'] ?? ''),
            'isi'            => trim($_POST['isi'] ?? ''),
            'tautan'         => trim($_POST['tautan'] ?? '')
        ];

        $errors = validasiMateri($form, $opsiMataKuliah);
        if (empty($errors)) {
            $mkId = (int)$form['mata_kuliah_id'];

            if (
                (int)$dataLama['mata_kuliah_id'] === $mkId &&
                $dataLama['judul'] === $form['judul'] &&
                $dataLama['isi'] === $form['isi'] &&
                $dataLama['tautan'] === $form['tautan']
            ) {
                setPesan('info', 'Tidak ada perubahan data yang disimpan.');
                header('Location: materi.php');
                exit;
            }

            $stmt = $conn->prepare('UPDATE materi SET mata_kuliah_id = ?, judul = ?, isi = ?, tautan = ? WHERE id = ?');
            $stmt->bind_param('isssi', $mkId, $form['judul'], $form['isi'], $form['tautan'], $id);
            $stmt->execute();

            setPesan('success', 'Data materi berhasil diperbarui.');
            header('Location: materi.php');
            exit;
        }
        $aksi = 'edit';
        $_GET['id'] = (string)$id;
    } elseif ($aksiPost === 'hapus') {
        $id = validasiId($_POST['id'] ?? null);
        if ($id === null) {
            setPesan('error', 'ID materi tidak valid.');
            header('Location: materi.php');
            exit;
        }

        $stmtCek = $conn->prepare('SELECT id FROM materi WHERE id = ?');
        $stmtCek->bind_param('i', $id);
        $stmtCek->execute();
        if ($stmtCek->get_result()->num_rows === 0) {
            setPesan('error', 'Data materi tidak ditemukan.');
            header('Location: materi.php');
            exit;
        }

        $stmtDel = $conn->prepare('DELETE FROM materi WHERE id = ?');
        $stmtDel->bind_param('i', $id);
        $stmtDel->execute();

        setPesan('success', 'Data materi berhasil dihapus.');
        header('Location: materi.php');
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
    <title>Materi - LMS Praktikum PAW</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-container">
            <a href="index.php" class="brand">LMS Praktikum PAW</a>
            <div class="nav-links">
                <a href="index.php">Beranda</a>
                <a href="mata_kuliah.php">Mata kuliah</a>
                <a href="materi.php" class="active">Materi</a>
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

        <?php
        if ($aksi === 'tambah' || $aksi === 'edit'):
            $idEdit = null;
            if ($aksi === 'edit') {
                $idEdit = validasiId($_GET['id'] ?? null);
                if ($idEdit === null) {
                    echo '<div class="alert alert-error">ID materi tidak valid.</div>';
                    echo '<a href="materi.php" class="btn btn-secondary">Kembali</a>';
                    echo '</main></body></html>';
                    exit;
                }

                if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                    $stmt = $conn->prepare('SELECT mata_kuliah_id, judul, isi, tautan FROM materi WHERE id = ?');
                    $stmt->bind_param('i', $idEdit);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    if (!$row) {
                        echo '<div class="alert alert-error">Data materi tidak ditemukan.</div>';
                        echo '<a href="materi.php" class="btn btn-secondary">Kembali</a>';
                        echo '</main></body></html>';
                        exit;
                    }
                    $form = $row;
                }
            }
        ?>
            <div class="card">
                <h1><?= $aksi === 'tambah' ? 'Tambah Materi' : 'Edit Materi' ?></h1>
                <p class="subtitle">Pastikan setiap materi terhubung dengan mata kuliah yang sesuai.</p>

                <form method="post" action="materi.php<?= $aksi === 'edit' ? '?aksi=edit&id=' . e($idEdit) : '?aksi=tambah' ?>" novalidate>
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
                        <label for="judul">Judul materi</label>
                        <input type="text" id="judul" name="judul" class="form-control <?= isset($errors['judul']) ? 'is-invalid' : '' ?>" value="<?= e($form['judul']) ?>" required>
                        <?php if (isset($errors['judul'])): ?>
                            <div class="field-error"><?= e($errors['judul']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="isi">Isi materi</label>
                        <textarea id="isi" name="isi" class="form-control <?= isset($errors['isi']) ? 'is-invalid' : '' ?>" required><?= e($form['isi']) ?></textarea>
                        <?php if (isset($errors['isi'])): ?>
                            <div class="field-error"><?= e($errors['isi']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="tautan">Tautan referensi</label>
                        <input type="url" id="tautan" name="tautan" class="form-control <?= isset($errors['tautan']) ? 'is-invalid' : '' ?>" value="<?= e($form['tautan']) ?>" placeholder="https://..." required>
                        <?php if (isset($errors['tautan'])): ?>
                            <div class="field-error"><?= e($errors['tautan']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Simpan data</button>
                        <a href="materi.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>

        <?php
        elseif ($aksi === 'hapus'):
            $idHapus = validasiId($_GET['id'] ?? null);
            if ($idHapus === null) {
                echo '<div class="alert alert-error">ID materi tidak valid.</div>';
                echo '<a href="materi.php" class="btn btn-secondary">Kembali</a>';
                echo '</main></body></html>';
                exit;
            }

            $stmt = $conn->prepare('SELECT m.id, m.judul, mk.kode, mk.nama AS nama_mk FROM materi m JOIN mata_kuliah mk ON m.mata_kuliah_id = mk.id WHERE m.id = ?');
            $stmt->bind_param('i', $idHapus);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();

            if (!$item) {
                echo '<div class="alert alert-error">Data materi tidak ditemukan.</div>';
                echo '<a href="materi.php" class="btn btn-secondary">Kembali</a>';
                echo '</main></body></html>';
                exit;
            }
        ?>
            <div class="card">
                <h1>Materi</h1>
                <p style="margin: 14px 0;">Hapus materi <strong><?= e($item['judul']) ?></strong> pada mata kuliah <?= e($item['kode']) ?> - <?= e($item['nama_mk']) ?>?</p>
                <p style="color: #4b5563; margin-bottom: 20px;">Data baru dihapus setelah tombol konfirmasi dikirim.</p>

                <form method="post" action="materi.php?aksi=hapus&id=<?= e($item['id']) ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="aksi_post" value="hapus">
                    <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                    <div class="form-actions">
                        <button type="submit" class="btn btn-danger">Ya, hapus data</button>
                        <a href="materi.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>

        <?php
        elseif ($aksi === 'detail'):
            $idDetail = validasiId($_GET['id'] ?? null);
            if ($idDetail === null) {
                echo '<div class="alert alert-error">ID materi tidak valid.</div>';
                echo '<a href="materi.php" class="btn btn-secondary">Kembali</a>';
                echo '</main></body></html>';
                exit;
            }

            $stmt = $conn->prepare('SELECT m.id, m.judul, m.isi, m.tautan, mk.id AS mk_id, mk.kode, mk.nama AS nama_mk FROM materi m JOIN mata_kuliah mk ON m.mata_kuliah_id = mk.id WHERE m.id = ?');
            $stmt->bind_param('i', $idDetail);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();

            if (!$item) {
                echo '<div class="alert alert-error">Data materi tidak ditemukan.</div>';
                echo '<a href="materi.php" class="btn btn-secondary">Kembali</a>';
                echo '</main></body></html>';
                exit;
            }
        ?>
            <div class="card">
                <p class="subtitle" style="margin-bottom:4px;">Materi</p>
                <h1><?= e($item['judul']) ?></h1>

                <table class="detail-table">
                    <tr>
                        <th>Mata kuliah</th>
                        <td><?= e($item['kode']) ?> - <?= e($item['nama_mk']) ?></td>
                    </tr>
                    <tr>
                        <th>Judul materi</th>
                        <td><?= e($item['judul']) ?></td>
                    </tr>
                    <tr>
                        <th>Isi materi</th>
                        <td style="white-space: pre-line;"><?= e($item['isi']) ?></td>
                    </tr>
                    <tr>
                        <th>Tautan referensi</th>
                        <td><a href="<?= e($item['tautan']) ?>" target="_blank" rel="noopener noreferrer"><?= e($item['tautan']) ?></a></td>
                    </tr>
                </table>

                <div class="form-actions">
                    <a href="materi.php?aksi=edit&id=<?= e($item['id']) ?>" class="btn btn-primary">Edit data</a>
                    <a href="materi.php" class="btn btn-secondary">Kembali</a>
                </div>
            </div>

        <?php
        else:
            $q    = trim($_GET['q'] ?? '');
            $mkId = validasiId($_GET['mata_kuliah_id'] ?? null);

            $sql = 'SELECT m.id, m.judul, mk.kode, mk.nama AS nama_mk FROM materi m JOIN mata_kuliah mk ON m.mata_kuliah_id = mk.id WHERE 1=1';
            $params = [];
            $types  = '';

            if ($q !== '') {
                $sql .= ' AND m.judul LIKE ?';
                $params[] = '%' . $q . '%';
                $types .= 's';
            }

            if ($mkId !== null) {
                $sql .= ' AND m.mata_kuliah_id = ?';
                $params[] = $mkId;
                $types .= 'i';
            }

            $sql .= ' ORDER BY m.id DESC';
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
                        <h1>Materi</h1>
                        <p class="subtitle" style="margin-bottom:0;">Menampilkan <?= e($hasil->num_rows) ?> data.</p>
                    </div>
                    <a href="materi.php?aksi=tambah" class="btn btn-primary">Tambah materi</a>
                </div>

                <form method="get" action="materi.php" class="filter-bar">
                    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Cari judul materi...">
                    <select name="mata_kuliah_id">
                        <option value="">Semua mata kuliah</option>
                        <?php foreach ($opsiMataKuliah as $idOpt => $mk): ?>
                            <option value="<?= e($idOpt) ?>" <?= $mkId === (int)$idOpt ? 'selected' : '' ?>>
                                <?= e($mk['kode']) ?> - <?= e($mk['nama']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-primary">Cari</button>
                    <a href="materi.php" class="btn btn-secondary">Reset</a>
                </form>

                <?php if ($hasil->num_rows > 0): ?>
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>Judul</th>
                                    <th>Mata kuliah</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $hasil->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= e($row['judul']) ?></td>
                                        <td><?= e($row['kode']) ?> - <?= e($row['nama_mk']) ?></td>
                                        <td class="actions">
                                            <a href="materi.php?aksi=detail&id=<?= e($row['id']) ?>">Detail</a>
                                            <a href="materi.php?aksi=edit&id=<?= e($row['id']) ?>">Edit</a>
                                            <a href="materi.php?aksi=hapus&id=<?= e($row['id']) ?>" class="delete-link">Hapus</a>
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