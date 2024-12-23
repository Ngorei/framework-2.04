<?php
use app\NgoreiDb;
$db = new NgoreiDb();
$myPdo = $db->connMysqli();

$data = json_decode(file_get_contents("php://input"), true);

// Log request untuk debugging
error_log("Received request: " . print_r($data, true));

// Validasi parameter yang diperlukan
if (!isset($data['database']) || !isset($data['table']) || !isset($data['action'])) {
    return [
        'status' => 'error',
        'message' => 'Missing required parameters'
    ];
}

if ($data['action'] !== 'getColumns') {
    return [
        'status' => 'error',
        'message' => 'Invalid action'
    ];
}

try {
    $database = $data['database'];
    $table = $data['table'];

    // Query untuk mendapatkan daftar kolom
    $query = "SHOW COLUMNS FROM `$database`.`$table`";
    $result = $myPdo->query($query);
    
    // Ambil hasil query
    $columnNames = [];
    while ($row = $result->fetch_assoc()) {
        $columnNames[] = $row['Field'];
    }

    return [
        'status' => 'success',
        'data' => $columnNames
    ];

} catch (Exception $e) {
    error_log("Error getting columns: " . $e->getMessage());
    return [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}
?>