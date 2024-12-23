<?php
use app\NgoreiDb;
$db = new NgoreiDb();
$myPdo = $db->connMysqli();

$data = json_decode(file_get_contents("php://input"), true);

// Debug log
error_log("Move column request: " . print_r($data, true));

// Validasi parameter
if (!isset($data['database']) || !isset($data['table']) || 
    !isset($data['column']) || !isset($data['position'])) {
    return [
        'status' => 'error',
        'message' => 'Missing required parameters'
    ];
}

try {
    $database = $data['database'];
    $table = $data['table'];
    $column = $data['column'];
    $position = $data['position'];
    $afterColumn = $data['afterColumn'] ?? null;

    // Pilih database
    $myPdo->select_db($database);
    
    // Buat query MODIFY COLUMN dengan posisi
    $query = "ALTER TABLE `$table` MODIFY COLUMN `$column` ";
    
    // Dapatkan tipe data kolom saat ini
    $columnInfo = $myPdo->query("SHOW COLUMNS FROM `$table` WHERE Field = '$column'")->fetch_assoc();
    $query .= $columnInfo['Type'];
    
    // Tambahkan posisi
    if ($position === 'FIRST') {
        $query .= " FIRST";
    } else if ($position === 'AFTER' && $afterColumn) {
        $query .= " AFTER `$afterColumn`";
    }
    
    error_log("Executing query: " . $query);
    
    $result = $myPdo->query($query);

    if ($result) {
        return [
            'status' => 'success',
            'message' => 'Column moved successfully',
            'debug' => [
                'query' => $query,
                'database' => $database,
                'table' => $table,
                'column' => $column,
                'position' => $position,
                'afterColumn' => $afterColumn
            ]
        ];
    } else {
        throw new Exception($myPdo->error);
    }

} catch (Exception $e) {
    error_log("Error moving column: " . $e->getMessage());
    return [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
} 