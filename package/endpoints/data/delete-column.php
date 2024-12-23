<?php
use app\NgoreiDb;
$db = new NgoreiDb();
$myPdo = $db->connMysqli();

// Ambil dan decode data JSON dari request
$data = json_decode(file_get_contents("php://input"), true);

// Debug log
error_log("Delete column request: " . print_r($data, true));

// Validasi parameter yang diperlukan
if (!isset($data['database']) || !isset($data['table']) || !isset($data['column'])) {
    return [
        'status' => 'error',
        'message' => 'Missing required parameters'
    ];
}

try {
    $database = $data['database'];
    $table = $data['table'];
    $column = $data['column'];

    // Pilih database
    $myPdo->select_db($database);
    
    // Buat query DROP COLUMN
    $query = "ALTER TABLE `$table` DROP COLUMN `$column`";
    error_log("Executing query: " . $query);
    
    $result = $myPdo->query($query);

    if ($result) {
        return [
            'status' => 'success',
            'message' => 'Column deleted successfully',
            'debug' => [
                'query' => $query,
                'database' => $database,
                'table' => $table,
                'column' => $column
            ]
        ];
    } else {
        throw new Exception($myPdo->error);
    }

} catch (Exception $e) {
    error_log("Error deleting column: " . $e->getMessage());
    return [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
} 