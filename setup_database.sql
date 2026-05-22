-- ============================================================
-- Tool Room QR System - Database Setup
-- PT Aldzama
-- ============================================================

-- Hapus database lama jika ada (hati-hati!)
DROP DATABASE IF EXISTS toolroom;

-- Buat database baru
CREATE DATABASE toolroom CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Gunakan database
USE toolroom;

-- ============================================================
-- Tabel tools (master data alat)
-- ============================================================
CREATE TABLE tools (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  tool_id     VARCHAR(50)  UNIQUE NOT NULL,
  nama_tool   VARCHAR(100) NOT NULL,
  stok        INT          DEFAULT 0,
  lokasi      VARCHAR(100),
  kategori    VARCHAR(50),
  created_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Tabel transaksi (riwayat keluar-masuk)
-- FIX: tambah kolom paired_with_id dan created_by
-- ============================================================
CREATE TABLE transaksi (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  tool_id         VARCHAR(50)  NOT NULL,
  operator        VARCHAR(50)  NOT NULL,
  aksi            ENUM('IN','OUT') NOT NULL,
  keterangan      TEXT,
  paired_with_id  INT          DEFAULT NULL,   -- FIX: pasangan transaksi IN<->OUT
  created_by      VARCHAR(50)  DEFAULT 'web_system', -- FIX: sumber transaksi
  waktu           TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tool_id       (tool_id),
  INDEX idx_aksi          (aksi),
  INDEX idx_waktu         (waktu),
  INDEX idx_paired        (paired_with_id),
  FOREIGN KEY (tool_id) REFERENCES tools(tool_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Tabel stock_adjustment (audit trail adjustment stok)
-- ============================================================
CREATE TABLE IF NOT EXISTS stock_adjustment (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  tool_id           VARCHAR(50)              NOT NULL,
  type              ENUM('add', 'subtract')  NOT NULL,
  jumlah            INT                      NOT NULL,
  stok_sebelum      INT                      NOT NULL,
  stok_sesudah      INT                      NOT NULL,
  alasan            VARCHAR(100)             NOT NULL,
  keterangan        TEXT,
  penanggung_jawab  VARCHAR(50)              NOT NULL,
  waktu             TIMESTAMP                DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tool_id (tool_id),
  INDEX idx_waktu   (waktu)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Data contoh
-- ============================================================
INSERT INTO tools (tool_id, nama_tool, stok, lokasi, kategori) VALUES
('TOOL001', 'Tang Potong 8 inch',  5,  'Rak A1', 'Hand Tools'),
('TOOL002', 'Obeng Plus (+) Set',  10, 'Rak A2', 'Hand Tools'),
('TOOL003', 'Obeng Minus (-) Set', 8,  'Rak A2', 'Hand Tools'),
('TOOL004', 'Kunci Inggris 10 inch', 3, 'Rak B1', 'Hand Tools'),
('TOOL005', 'Kunci Inggris 12 inch', 2, 'Rak B1', 'Hand Tools'),
('TOOL006', 'Multimeter Digital',  2,  'Rak C1', 'Measuring'),
('TOOL007', 'Tang Ampere',         1,  'Rak C1', 'Measuring'),
('TOOL008', 'Bor Listrik Makita',  4,  'Rak D1', 'Power Tools'),
('TOOL009', 'Gerinda Tangan',      3,  'Rak D2', 'Power Tools'),
('TOOL010', 'Kunci Sok Set',       6,  'Rak B2', 'Hand Tools');

-- ============================================================
-- View: stok tool
-- ============================================================
CREATE VIEW view_stok_tool AS
SELECT 
  tool_id,
  nama_tool,
  stok,
  lokasi,
  kategori,
  CASE 
    WHEN stok > 5 THEN 'Aman'
    WHEN stok > 0 THEN 'Terbatas'
    ELSE 'Habis'
  END AS status_stok
FROM tools
ORDER BY nama_tool;

-- ============================================================
-- View: tool yang sedang dipinjam (menggunakan pairing system)
-- ============================================================
CREATE VIEW view_tool_dipinjam AS
SELECT 
  t.tool_id,
  t.nama_tool,
  tr.operator,
  tr.waktu                                         AS waktu_pinjam,
  TIMESTAMPDIFF(HOUR, tr.waktu, NOW())             AS durasi_jam
FROM transaksi tr
JOIN tools t ON t.tool_id = tr.tool_id
WHERE tr.aksi = 'OUT'
  AND tr.paired_with_id IS NULL
ORDER BY tr.waktu DESC;