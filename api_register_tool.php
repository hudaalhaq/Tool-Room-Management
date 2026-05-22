<?php
/**
 * API untuk Registrasi Tool Baru
 * Tool Room Management System
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'config.php';

// Hanya terima method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false, 
        'message' => 'Method tidak diizinkan. Gunakan POST.'
    ]);
    exit;
}

try {
    // Ambil data JSON dari request body
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Validasi JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Format JSON tidak valid: ' . json_last_error_msg());
    }
    
    // Validasi field required
    $required_fields = ['tool_id', 'nama_tool', 'stok', 'lokasi', 'kategori'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            throw new Exception("Field '$field' wajib diisi");
        }
    }
    
    // Koneksi database
    $conn = getConnection();
    
    // Sanitasi input
    $tool_id = strtoupper(sanitize($conn, $data['tool_id']));
    $nama_tool = sanitize($conn, $data['nama_tool']);
    $stok = (int)$data['stok'];
    $lokasi = sanitize($conn, $data['lokasi']);
    $kategori = sanitize($conn, $data['kategori']);
    $keterangan = isset($data['keterangan']) ? sanitize($conn, $data['keterangan']) : '';
    
    // ========== VALIDASI DATA ==========
    
    // 1. Validasi format Tool ID
    if (!preg_match('/^TOOL[0-9]{3,}$/', $tool_id)) {
        throw new Exception(
            'Format Tool ID tidak valid. Harus: TOOL diikuti minimal 3 angka (contoh: TOOL001, TOOL002)'
        );
    }
    
    // 2. Validasi stok
    if ($stok < 0) {
        throw new Exception('Stok tidak boleh negatif');
    }
    
    if ($stok > 9999) {
        throw new Exception('Stok maksimal 9999');
    }
    
    // 3. Validasi panjang nama
    if (strlen($nama_tool) < 3) {
        throw new Exception('Nama tool minimal 3 karakter');
    }
    
    if (strlen($nama_tool) > 100) {
        throw new Exception('Nama tool maksimal 100 karakter');
    }
    
    // 4. Validasi kategori
    $valid_categories = [
        'Hand Tools', 
        'Power Tools', 
        'Measuring Tools',
        'Sleeve & Hose',
        'Fasterner',
        'Filter & Spare Parts', 
        'Hydraulic & Mechanical Parts',
        'Electrical Tools',
        'Electrical Parts',
        'Fluida & Consumable',
        'Other'
    ];
    
    if (!in_array($kategori, $valid_categories)) {
        throw new Exception('Kategori tidak valid. Pilih dari daftar yang tersedia.');
    }
    
    // ========== CEK DUPLIKASI ==========
    
    // Cek apakah Tool ID sudah ada
    $stmt = $conn->prepare("SELECT tool_id, nama_tool FROM tools WHERE tool_id = ?");
    $stmt->bind_param("s", $tool_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $existing = $result->fetch_assoc();
        throw new Exception(
            "Tool ID '$tool_id' sudah terdaftar untuk '{$existing['nama_tool']}'. " .
            "Gunakan ID yang berbeda."
        );
    }
    $stmt->close();
    
    // Cek apakah nama tool sudah ada (warning, tapi tetap boleh input)
    $stmt = $conn->prepare("SELECT tool_id FROM tools WHERE LOWER(nama_tool) = LOWER(?)");
    $stmt->bind_param("s", $nama_tool);
    $stmt->execute();
    $result = $stmt->get_result();
    $duplicate_name_warning = '';
    
    if ($result->num_rows > 0) {
        $duplicate_name_warning = " (Peringatan: Nama tool serupa sudah ada)";
    }
    $stmt->close();
    
    // ========== INSERT KE DATABASE ==========
    
    $stmt = $conn->prepare("
        INSERT INTO tools (tool_id, nama_tool, stok, lokasi, kategori) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param("ssiss", $tool_id, $nama_tool, $stok, $lokasi, $kategori);
    
    if (!$stmt->execute()) {
        throw new Exception('Gagal menyimpan data ke database: ' . $stmt->error);
    }
    
    $insert_id = $conn->insert_id;
    $stmt->close();
    
    // ========== LOG AKTIVITAS (OPTIONAL) ==========
    // Bisa ditambahkan log siapa yang register tool
    
    $conn->close();
    
    // ========== RESPONSE SUKSES ==========
    echo json_encode([
        'success' => true,
        'message' => "Tool '$nama_tool' berhasil diregistrasi!{$duplicate_name_warning}",
        'data' => [
            'id' => $insert_id,
            'tool_id' => $tool_id,
            'nama_tool' => $nama_tool,
            'stok' => $stok,
            'lokasi' => $lokasi,
            'kategori' => $kategori,
            'keterangan' => $keterangan
        ]
    ]);
    
} catch (Exception $e) {
    // Log error
    logError('API Register Tool Error: ' . $e->getMessage());
    
    // Response error
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>