<?php
/**
 * API untuk Adjustment Stok Tool
 * Support: Tambah dan Kurangi Stok
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

try {
    // Ambil data JSON
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Format JSON tidak valid');
    }
    
    // Validasi required fields
    $required = ['tool_id', 'type', 'jumlah', 'alasan', 'keterangan', 'penanggung_jawab'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            throw new Exception("Field '$field' wajib diisi");
        }
    }
    
    // Koneksi database
    $conn = getConnection();
    
    // Sanitasi input
    $tool_id = strtoupper(sanitize($conn, $data['tool_id']));
    $type = sanitize($conn, $data['type']); // 'add' atau 'subtract'
    $jumlah = (int)$data['jumlah'];
    $alasan = sanitize($conn, $data['alasan']);
    $keterangan = sanitize($conn, $data['keterangan']);
    $penanggung_jawab = sanitize($conn, $data['penanggung_jawab']);
    
    // ========== VALIDASI ==========
    
    // Validasi type
    if (!in_array($type, ['add', 'subtract'])) {
        throw new Exception('Type harus "add" atau "subtract"');
    }
    
    // Validasi jumlah
    if ($jumlah < 1) {
        throw new Exception('Jumlah minimal 1');
    }
    
    if ($jumlah > 9999) {
        throw new Exception('Jumlah maksimal 9999');
    }
    
    // Cek tool existence
    $stmt = $conn->prepare("SELECT id, tool_id, nama_tool, stok FROM tools WHERE tool_id = ?");
    $stmt->bind_param("s", $tool_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Tool '$tool_id' tidak ditemukan");
    }
    
    $tool = $result->fetch_assoc();
    $stok_lama = $tool['stok'];
    $stmt->close();
    
    // Validasi stok jika subtract
    if ($type === 'subtract' && $jumlah > $stok_lama) {
        throw new Exception(
            "Tidak bisa kurangi $jumlah. Stok saat ini hanya $stok_lama"
        );
    }
    
    // ========== MULAI TRANSACTION ==========
    $conn->begin_transaction();
    
    try {
        // Hitung stok baru
        if ($type === 'add') {
            $stok_baru = $stok_lama + $jumlah;
            $aksi_log = 'ADJUSTMENT_ADD';
        } else {
            $stok_baru = $stok_lama - $jumlah;
            $aksi_log = 'ADJUSTMENT_SUBTRACT';
        }
        
        // Update stok di tabel tools
        $stmt = $conn->prepare("UPDATE tools SET stok = ? WHERE tool_id = ?");
        $stmt->bind_param("is", $stok_baru, $tool_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Gagal update stok: ' . $stmt->error);
        }
        $stmt->close();
        
        // Log ke tabel stock_adjustment (buat tabel baru untuk audit trail)
        // Jika tabel belum ada, buat dulu
        $conn->query("
            CREATE TABLE IF NOT EXISTS stock_adjustment (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tool_id VARCHAR(50) NOT NULL,
                type ENUM('add', 'subtract') NOT NULL,
                jumlah INT NOT NULL,
                stok_sebelum INT NOT NULL,
                stok_sesudah INT NOT NULL,
                alasan VARCHAR(100) NOT NULL,
                keterangan TEXT,
                penanggung_jawab VARCHAR(50) NOT NULL,
                waktu TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tool_id (tool_id),
                INDEX idx_waktu (waktu)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Insert log adjustment
        $stmt = $conn->prepare("
            INSERT INTO stock_adjustment 
            (tool_id, type, jumlah, stok_sebelum, stok_sesudah, alasan, keterangan, penanggung_jawab)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            "ssiissss",
            $tool_id,
            $type,
            $jumlah,
            $stok_lama,
            $stok_baru,
            $alasan,
            $keterangan,
            $penanggung_jawab
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Gagal menyimpan log: ' . $stmt->error);
        }
        
        $adjustment_id = $conn->insert_id;
        $stmt->close();
        
        // Commit transaction
        $conn->commit();
        
        // Log ke file untuk audit
        $log_message = sprintf(
            "ADJUSTMENT: %s | Tool: %s | Type: %s | Qty: %d | From: %d To: %d | By: %s | Reason: %s",
            date('Y-m-d H:i:s'),
            $tool_id,
            strtoupper($type),
            $jumlah,
            $stok_lama,
            $stok_baru,
            $penanggung_jawab,
            $alasan
        );
        error_log($log_message);
        
        // Response sukses
        echo json_encode([
            'success' => true,
            'message' => 'Adjustment stok berhasil disimpan',
            'data' => [
                'adjustment_id' => $adjustment_id,
                'tool_id' => $tool_id,
                'nama_tool' => $tool['nama_tool'],
                'type' => $type,
                'jumlah' => $jumlah,
                'stok_lama' => $stok_lama,
                'stok_baru' => $stok_baru,
                'alasan' => $alasan,
                'penanggung_jawab' => $penanggung_jawab,
                'waktu' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
    $conn->close();
    
} catch (Exception $e) {
    logError('API Adjust Stock Error: ' . $e->getMessage());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>