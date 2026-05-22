<?php
/**
 * API Dashboard Stok Tool
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

try {
    $conn = getConnection();
    
    // Query semua tool dengan status
    $sql = "SELECT 
        tool_id,
        nama_tool,
        stok,
        lokasi,
        kategori,
        CASE 
            WHEN stok > 5 THEN 'Aman'
            WHEN stok > 0 THEN 'Terbatas'
            ELSE 'Habis'
        END as status_stok,
        CASE 
            WHEN stok > 5 THEN 'success'
            WHEN stok > 0 THEN 'warning'
            ELSE 'danger'
        END as status_class
    FROM tools 
    ORDER BY CAST(SUBSTRING(tool_id, 5) AS UNSIGNED)";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Query error: ' . $conn->error);
    }
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'total' => count($data),
        'data' => $data
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    logError($e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>