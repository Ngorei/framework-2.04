<?php
use app\Ngorei;

// Cache instance
static $ngorei = null;
if ($ngorei === null) {
    $ngorei = new Ngorei();
}

// Validasi input
$val = json_decode(file_get_contents("php://input"), true);
$database = filter_var($val['database'] ?? DATABASE, FILTER_SANITIZE_STRING);
$tableName = filter_var($val['table'] ?? 'demo', FILTER_SANITIZE_STRING);

error_log("Received request for database: $database, table: $tableName");

if (empty($database)) {
    return [
        'status' => 'error',
        'message' => 'Database name is required'
    ];
}

if (empty($tableName)) {
    return [
        'status' => 'error',
        'message' => 'Table name is required'
    ];
}

$page = filter_var($val['page'] ?? 1, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
$limit = filter_var($val['limit'] ?? 10, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 100]]);

try {
    // Set database aktif
    $ngorei->setDatabase($database);
    
    // Log untuk debugging
    error_log("Processing request for {$database}.{$tableName}");
    
    try {
        // Inisialisasi tabel
        $ngorei->Brief($tableName);
    } catch (\Exception $e) {
        error_log("Error initializing table: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => "Tabel tidak ditemukan: {$tableName}",
            'database' => $database
        ];
    }
    
    // Ambil data
    $result = $ngorei->getTableData($tableName, $page, $limit);
    
    if ($result['status'] === 'error') {
        return $result;
    }

    // Proses data
    $processedData = [];
    foreach ($result['data'] as $row) {
        $processedRow = [];
        foreach ($row as $key => $value) {
            $processedRow[$key] = is_numeric($value) ? (0 + $value) : $value;
        }
        $processedData[] = $processedRow;
    }

    return [
        'status' => 'success',
        'data' => $processedData,
        'total' => (int)$result['total'],
        'page' => (int)$page,
        'limit' => (int)$limit,
        'database' => $database,
        'table' => $tableName
    ];

} catch (Exception $e) {
    error_log("Error processing request: " . $e->getMessage());
    return [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

