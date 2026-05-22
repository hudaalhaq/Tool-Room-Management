<?php
/**
 * API untuk Mendapatkan Tool ID Berikutnya
 * Auto-suggest untuk form registrasi
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

try {
    $conn = getConnection();
    
    // Query untuk mendapatkan Tool ID terakhir
    // Hanya ambil yang formatnya TOOL[angka]
    $sql = "SELECT tool_id 
            FROM tools 
            WHERE tool_id REGEXP '^TOOL[0-9]+$' 
            ORDER BY CAST(SUBSTRING(tool_id, 5) AS UNSIGNED) DESC 
            LIMIT 1";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Query error: ' . $conn->error);
    }
    
    if ($result->num_rows > 0) {
        // Ada data tool
        $row = $result->fetch_assoc();
        $last_id = $row['tool_id'];
        
        // Extract angka dari format TOOL001 -> 001
        if (preg_match('/TOOL(\d+)/', $last_id, $matches)) {
            $last_num = (int)$matches[1];
            
            // Increment
            $next_num = $last_num + 1;
            
            // Format dengan leading zeros (minimal 3 digit)
            // TOOL001, TOOL002, ... TOOL010, ... TOOL100, ... TOOL1000
            $digit_count = max(3, strlen((string)$next_num));
            $next_id = 'TOOL' . str_pad($next_num, $digit_count, '0', STR_PAD_LEFT);
            
        } else {
            // Jika format tidak sesuai, mulai dari TOOL001
            $next_id = 'TOOL001';
        }
        
    } else {
        // Belum ada data tool, mulai dari TOOL001
        $next_id = 'TOOL001';
    }
    
    // Response sukses
    echo json_encode([
        'success' => true,
        'next_id' => $next_id,
        'message' => "ID berikutnya yang tersedia: $next_id"
    ]);
    
    $conn->close();
    
} catch (Exception $e) {
    // Log error
    logError('API Get Next ID Error: ' . $e->getMessage());
    
    // Response error
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Gagal mendapatkan ID berikutnya: ' . $e->getMessage(),
        'next_id' => 'TOOL001' // Fallback
    ]);
}
?>