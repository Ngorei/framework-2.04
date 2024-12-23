<?php
use app\NgoreiDb;
$db = new NgoreiDb();
$myPdo = $db->connMysqli();

// Ambil dan decode data JSON dari request
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
    
    // Ubah tipe kolom dan tambahkan komentar
    $query = "ALTER TABLE `$table` MODIFY COLUMN `$column` $newType";
    
    // Jika nama kolom berbeda, gunakan nama baru
    if ($newName !== $column) {
        $query .= " `$newName`";
    }
    
    // Tambahkan komentar jika ada
    if (!empty($comment)) {
        $query .= " $comment";
    }

    $result = $myPdo->query($query);

    if ($result) {
        return [
            'status' => 'success',
            'message' => 'Column modified successfully'
        ];
    }
    
    throw new Exception("Failed to modify column");

} catch (Exception $e) {
    return [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
} 