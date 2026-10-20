CREATE DATABASE IF NOT EXISTS lms_db
CHARACTER SET utf8mb4 COLLATE
utf8mb4_unicode_ci;
USE lms_db;
CREATE TABLE mata_kuliah(  
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,  
    kode VARCHAR(20) NOT NULL UNIQUE,  
    nama VARCHAR(100) NOT NULL,  
    dosen VARCHAR(100) NOT NULL,  
    semester TINYINT UNSIGNED NOT NULL );

CREATE TABLE materi (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mata_kuliah_id INT UNSIGNED NOT NULL,
    judul VARCHAR(150) NOT NULL,
    isi TEXT NOT NULL,
    tautan VARCHAR(255) NOT NULL,
    FOREIGN KEY (mata_kuliah_id) REFERENCES mata_kuliah(id) ON DELETE RESTRICT );
    
CREATE TABLE tugas (  
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    mata_kuliah_id INT UNSIGNED NOT NULL,
    judul VARCHAR(150) NOT NULL,
    instruksi TEXT NOT NULL,
    tenggat DATE NOT NULL,  
    status VARCHAR(20) NOT NULL,  
    FOREIGN KEY(mata_kuliah_id)    
    REFERENCES mata_kuliah(id) ON DELETE RESTRICT );

INSERT INTO mata_kuliah (kode, nama, dosen, semester) VALUES
('ALP-101', 'Algoritma dan Pemrograman', 'Dosen ALP', 1),
('PAW-101', 'Pengembangan Aplikasi Website', 'Dosen PAW', 3);

INSERT INTO materi (mata_kuliah_id, judul, isi, tautan) VALUES
(1, 'Pengenalan Algoritma dan Flowchart', 'Materi ini membahas konsep dasar penyusunan algoritma, pseudocode, serta pembuatan diagram alir untuk menyelesaikan masalah pemrograman.', 'https://www.php.net/manual/en/langref.php'),
(2, 'Dasar PHP dan Transaksi Database', 'Materi membahas pengelolaan perubahan data pada database aplikasi website menggunakan ekstensi MySQLi dan prepared statements.', 'https://www.php.net/manual/en/book.mysqli.php');

INSERT INTO tugas (mata_kuliah_id, judul, instruksi, tenggat, status) VALUES
(1, 'Latihan Membuat Flowchart Percabangan', 'Buatlah studi kasus percabangan bersarang dalam bentuk flowchart dan pseudocode, lalu jelaskan alur eksekusinya.', '2026-11-15', 'Aktif'),
(2, 'Latihan CRUD dengan PHP & MySQL', 'Bangun aplikasi pengelolaan data sederhana yang menerapkan operasi Create, Read, Update, dan Delete dengan prepared statement.', '2026-11-20', 'Draf');