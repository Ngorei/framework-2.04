<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

try {
    // Cek koneksi database
    $db = new NgoreiDb();
    $myPdo = $db->connMysqli();
    
    // Cek server resources
    $serverInfo = [
        'memory_usage' => memory_get_usage(true),
        'cpu_load' => sys_getloadavg(),
        'disk_free_space' => disk_free_space("/"),
        'disk_total_space' => disk_total_space("/"),
        'server_time' => date('Y-m-d H:i:s'),
        'php_version' => PHP_VERSION,
        'mysql_version' => $myPdo->server_info
    ];
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Server is running',
        'server_info' => $serverInfo
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
} 