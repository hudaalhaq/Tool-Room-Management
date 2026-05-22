<?php
/**
 * API Transaksi Tool (IN/OUT)
 */

// Set header JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Include config
require_once 'config.php';

// Hanya terima method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan']);
    exit;
}

try {
    // Ambil data dari request body
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Validasi JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Format JSON tidak valid');
    }
    
    // Validasi field required
    if (empty($data['tool_id'])) {
        throw new Exception('Tool ID wajib diisi');
    }
    if (empty($data['operator'])) {
        throw new Exception('Nama operator wajib diisi');
    }
    if (empty($data['aksi']) || !in_array($data['aksi'], ['IN', 'OUT'])) {
        throw new Exception('Aksi tidak valid (harus IN atau OUT)');
    }
    
    // Koneksi database
    $conn = getConnection();
    
    // Sanitasi input
    $tool_id = sanitize($conn, $data['tool_id']);
    $operator = sanitize($conn, $data['operator']);
    $aksi = sanitize($conn, $data['aksi']);
    $keterangan = isset($data['keterangan']) ? sanitize($conn, $data['keterangan']) : '';
    
    // Cek apakah tool ada di database
    $stmt = $conn->prepare("SELECT tool_id, nama_tool, stok FROM tools WHERE tool_id = ?");
    $stmt->bind_param("s", $tool_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Tool ID '$tool_id' tidak ditemukan dalam database");
    }
    
    $tool = $result->fetch_assoc();
    $stmt->close();
    
    // Mulai transaction
    $conn->begin_transaction();
    
    try {
        if ($aksi === 'OUT') {
            // AMBIL TOOL - Kurangi stok
            
            // Cek stok tersedia
            if ($tool['stok'] <= 0) {
                throw new Exception("Stok {$tool['nama_tool']} habis (0). Tidak bisa dipinjam.");
            }
            
            // Update stok (kurangi 1)
            $stmt = $conn->prepare("UPDATE tools SET stok = stok - 1 WHERE tool_id = ? AND stok > 0");
            $stmt->bind_param("s", $tool_id);
            $stmt->execute();
            
            if ($stmt->affected_rows === 0) {
                throw new Exception('Gagal mengurangi stok. Stok mungkin sudah habis.');
            }
            
            $stmt->close();
            $pesan = "Tool '{$tool['nama_tool']}' berhasil dipinjam oleh $operator";
            
        } else if ($aksi === 'IN') {
            // KEMBALIKAN TOOL - Tambah stok
            
            $stmt = $conn->prepare("UPDATE tools SET stok = stok + 1 WHERE tool_id = ?");
            $stmt->bind_param("s", $tool_id);
            $stmt->execute();
            $stmt->close();
            
            $pesan = "Tool '{$tool['nama_tool']}' berhasil dikembalikan oleh $operator";
        }
        
		// Catat transaksi
		$stmt = $conn->prepare("INSERT INTO transaksi (tool_id, operator, aksi, keterangan, created_by) VALUES (?, ?, ?, ?, 'web_system')");
		$stmt->bind_param("ssss", $tool_id, $operator, $aksi, $keterangan);
		$stmt->execute();
		$transaksi_id = $conn->insert_id;
		$stmt->close();

		// ========== AUTO PAIRING SYSTEM (BY OPERATOR) ==========
		if ($aksi === 'IN') {

			// Cari transaksi OUT milik operator yang SAMA
			// yang belum dipair, diurutkan dari yang paling awal
			$stmt = $conn->prepare("
				SELECT id, operator as out_operator, waktu as out_waktu
				FROM transaksi 
				WHERE tool_id = ? 
				AND aksi = 'OUT' 
				AND paired_with_id IS NULL
				AND operator = ?
				AND waktu < NOW()
				ORDER BY waktu ASC 
				LIMIT 1
			");
			$stmt->bind_param("ss", $tool_id, $operator);
			$stmt->execute();
			$result = $stmt->get_result();
			
			if ($result->num_rows > 0) {
				// ✅ Ditemukan OUT milik operator yang sama
				$out_row = $result->fetch_assoc();
				$out_id = $out_row['id'];
				$out_operator = $out_row['out_operator'];
				
				// Pair IN -> OUT
				$stmt_update_in = $conn->prepare("
					UPDATE transaksi SET paired_with_id = ? WHERE id = ?
				");
				$stmt_update_in->bind_param("ii", $out_id, $transaksi_id);
				$stmt_update_in->execute();
				$stmt_update_in->close();
				
				// Pair OUT -> IN
				$stmt_update_out = $conn->prepare("
					UPDATE transaksi SET paired_with_id = ? WHERE id = ?
				");
				$stmt_update_out->bind_param("ii", $transaksi_id, $out_id);
				$stmt_update_out->execute();
				$stmt_update_out->close();
				
				error_log("PAIRING OK: OUT #{$out_id} ({$out_operator}) <-> IN #{$transaksi_id} ({$operator})");

			} else {
				// ❌ Tidak ditemukan OUT milik operator ini
				// Rollback transaksi IN dan stok
				$conn->rollback();
				throw new Exception(
					"Tidak ada peminjaman atas nama '{$operator}' untuk tool ini. " .
					"Pastikan nama operator sama persis dengan saat meminjam."
				);
			}
			
			$stmt->close();
		}
		// ========== END AUTO PAIRING ==========
        
        // Commit transaction
        $conn->commit();
        
        // Response sukses
        echo json_encode([
            'success' => true,
            'message' => $pesan,
            'data' => [
                'transaksi_id' => $transaksi_id,
                'tool_id' => $tool_id,
                'nama_tool' => $tool['nama_tool'],
                'operator' => $operator,
                'aksi' => $aksi,
                'waktu' => date('Y-m-d H:i:s')
            ]
        ]);
        
    } catch (Exception $e) {
        // Rollback jika error
        $conn->rollback();
        throw $e;
    }
    
    $conn->close();
    
} catch (Exception $e) {
    // Log error
    logError($e->getMessage());
    
    // Response error
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>