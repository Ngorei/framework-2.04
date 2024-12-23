<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log request
error_log("Received add column request: " . file_get_contents("php://input"));

use app\NgoreiDb;
$db = new NgoreiDb();
$myPdo = $db->connMysqli();

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validasi input
    if (!isset($input['database'], $input['table'], $input['column'], $input['type'])) {
        throw new Exception('Missing required parameters');
    }

    $database = $input['database'];
    $table = $input['table'];
    $column = $input['column'];
    $type = $input['type'];
    $length = $input['length'] ?? null;
    $default = $input['default'] ?? null;
    $nullable = $input['nullable'] ?? false;
    $position = $input['position'] ?? null;
    $afterColumn = $input['after_column'] ?? null;
    $comment = $input['comment'] ?? null;

    // Pilih database
    $myPdo->select_db($database);
    
    // Buat SQL untuk menambah kolom
    $sql = "ALTER TABLE `$table` ADD COLUMN `$column` $type";
    
    // Tambahkan length jika ada
    if ($length) {
        $sql .= "($length)";
    }
    
    // Tambahkan NULL/NOT NULL
    $sql .= $nullable ? " NULL" : " NOT NULL";
    
    // Tambahkan default value jika ada
    if ($default !== null && $default !== '') {
        if (strtoupper($default) === 'NULL') {
            $sql .= " DEFAULT NULL";
        } else {
            $sql .= " DEFAULT " . (is_numeric($default) ? $default : "'$default'");
        }
    }

    // Tambahkan comment jika ada
    if ($comment) {
        $sql .= " COMMENT '" . $myPdo->real_escape_string($comment) . "'";
    }
    
    // Tambahkan posisi kolom
    if ($position === 'FIRST') {
        $sql .= " FIRST";
    } elseif ($position === 'AFTER' && $afterColumn) {
        $sql .= " AFTER `$afterColumn`";
    }

    error_log("Executing query: " . $sql); // Untuk debugging

    // Eksekusi query
    if ($myPdo->query($sql)) {
        return [
            'status' => 'success',
            'message' => 'Column added successfully',
            'debug' => [
                'query' => $sql,
                'database' => $database,
                'table' => $table
            ]
        ];
    } else {
        throw new Exception($myPdo->error);
    }

} catch (Exception $e) {
    error_log("Error adding column: " . $e->getMessage());
    return [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
} 