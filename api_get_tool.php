<?php
/**
 * API untuk Mendapatkan Informasi Tool
 * Berdasarkan Tool ID
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

try {
    // Get Tool ID from query parameter
    if (!isset($_GET['tool_id']) || empty(trim($_GET['tool_id']))) {
        throw new Exception('Tool ID wajib diisi');
    }
    
    $tool_id = strtoupper(trim($_GET['tool_id']));
    
    // Koneksi database
    $conn = getConnection();
    
    // Query tool info
    $stmt = $conn->prepare("
        SELECT 
            id,
            tool_id,
            nama_tool,
            stok,
            lokasi,
            kategori,
            created_at,
            updated_at
        FROM tools 
        WHERE tool_id = ?
    ");
    
    $stmt->bind_param("s", $tool_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("Tool dengan ID '$tool_id' tidak ditemukan dalam database");
    }
    
    $tool = $result->fetch_assoc();
    $stmt->close();
    
    // Get transaction history (optional - last 5 transactions)
    $stmt = $conn->prepare("
        SELECT 
            aksi,
            operator,
            DATE_FORMAT(waktu, '%d-%m-%Y %H:%i') as waktu
        FROM transaksi 
        WHERE tool_id = ? 
        ORDER BY waktu DESC 
        LIMIT 5
    ");
    
    $stmt->bind_param("s", $tool_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();
    
    $conn->close();
    
    // Response sukses
    echo json_encode([
        'success' => true,
        'data' => $tool,
        'history' => $history,
        'message' => "Tool '$tool_id' ditemukan"
    ]);
    
} catch (Exception $e) {
    logError('API Get Tool Error: ' . $e->getMessage());
    
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>