<?php
use app\NgoreiDb;
$db = new NgoreiDb();
$myPdo = $db->connMysqli();

// Ambil dan decode data JSON dari request
$data = json_decode(file_get_contents("php://input"), true);

// Validasi parameter yang diperlukan
if (!isset($data['database']) || !isset($data['table']) || !isset($data['id'])) {
    return [
        'status' => 'error',
        'message' => 'Missing required parameters'
    ];
}

try {
    // Ambil parameter
    $database = $data['database'];
    $table = $data['table'];
    $id = $data['id'];

    // Pilih database
    $myPdo->select_db($database);
    
    // Hapus data berdasarkan ID
    $query = "DELETE FROM `$table` WHERE id = ?";
    $stmt = $myPdo->prepare($query);
    $stmt->bind_param('s', $id);
    $result = $stmt->execute();

    if ($result) {
        return [
            'status' => 'success',
            'message' => 'Data berhasil dihapus',
            'database' => $database,
            'table' => $table,
            'id' => $id
        ];
    }
    
    throw new Exception("Gagal menghapus data");

} catch (Exception $e) {
    return [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}
