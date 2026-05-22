<?php
/**
 * API Dashboard Tool yang Sedang Dipinjam
 * FINAL VERSION - Menggunakan Pairing System
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';

try {
    $conn = getConnection();
    
    // Query: Ambil semua transaksi OUT yang paired_with_id masih NULL
    // (artinya belum ada transaksi IN yang pair dengan OUT ini)
    $sql = "SELECT 
        t.tool_id,
        t.nama_tool,
        t.kategori,
        t.lokasi,
        tr.id as transaksi_id,
        tr.operator,
        DATE_FORMAT(tr.waktu, '%d-%m-%Y %H:%i:%s') as waktu,
        tr.waktu as waktu_raw,
        TIMESTAMPDIFF(MINUTE, tr.waktu, NOW()) as durasi_menit,
        TIMESTAMPDIFF(HOUR, tr.waktu, NOW()) as durasi_jam,
        CASE 
            WHEN TIMESTAMPDIFF(HOUR, tr.waktu, NOW()) > 24 THEN 'danger'
            WHEN TIMESTAMPDIFF(HOUR, tr.waktu, NOW()) > 8 THEN 'warning'
            ELSE 'normal'
        END as status_durasi,
        CASE 
            WHEN TIMESTAMPDIFF(HOUR, tr.waktu, NOW()) > 24 THEN '🔴 Overdue'
            WHEN TIMESTAMPDIFF(HOUR, tr.waktu, NOW()) > 8 THEN '🟡 Lama Dipinjam'
            ELSE '🟢 Normal'
        END as status_label
    FROM transaksi tr
    JOIN tools t ON t.tool_id = tr.tool_id
    WHERE tr.aksi = 'OUT'
    AND tr.paired_with_id IS NULL
    ORDER BY tr.waktu ASC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        throw new Exception('Query error: ' . $conn->error);
    }
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    // Statistik tambahan
    $stats = [
        'total_dipinjam' => count($data),
        'overdue' => 0,
        'warning' => 0,
        'normal' => 0
    ];
    
    foreach ($data as $item) {
        if ($item['status_durasi'] === 'danger') $stats['overdue']++;
        elseif ($item['status_durasi'] === 'warning') $stats['warning']++;
        else $stats['normal']++;
    }
    
    echo json_encode([
        'success' => true,
        'total' => count($data),
        'statistics' => $stats,
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