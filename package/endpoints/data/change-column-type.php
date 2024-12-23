<?php
use app\NgoreiDb;
$db = new NgoreiDb();
$myPdo = $db->connMysqli();

// Log untuk debugging
error_log("Received request: " . file_get_contents("php://input"));

$data = json_decode(file_get_contents("php://input"), true);

// Validasi parameter yang diperlukan
if (!isset($data['database']) || !isset($data['table']) || 
    !isset($data['column']) || !isset($data['newType'])) {
    return [
        'status' => 'error',
        'message' => 'Missing required parameters'
    ];
}

try {
    // Ambil parameter
    $database = $data['database'];
    $table = $data['table'];
    $column = $data['column'];
    $newType = $data['newType'];
    $newName = $data['newName'] ?? $column;
    $comment = isset($data['comment']) ? "COMMENT '" . $myPdo->real_escape_string($data['comment']) . "'" : '';

    // Pilih database
    $myPdo->select_db($database);
    
    // Buat query ALTER TABLE
    $query = "ALTER TABLE `$table` CHANGE COLUMN `$column` `$newName` $newType";
    
    // Tambahkan comment jika ada
    if (!empty($comment)) {
        $query .= " $comment";
    }

    error_log("Executing query: " . $query);

    if ($myPdo->query($query)) {
        return [
            'status' => 'success',
            'message' => 'Column modified successfully',
            'debug' => [
                'query' => $query,
                'database' => $database,
                'table' => $table
            ]
        ];
    } else {
        throw new Exception($myPdo->error);
    }

} catch (Exception $e) {
    error_log("Error modifying column: " . $e->getMessage());
    return [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
} 