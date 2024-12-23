<?php
use app\NgoreiDb;
$db = new NgoreiDb();
$myPdo = $db->connMysqli();

error_log("Received create table request: " . file_get_contents("php://input"));

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['action']) || $data['action'] !== 'create_table') {
    return [
        'status' => 'error',
        'message' => 'Invalid action'
    ];
}

try {
    if (!isset($data['database'], $data['table_name'], $data['columns'])) {
        throw new Exception('Missing required parameters');
    }

    $database = $data['database'];
    $tableName = $data['table_name'];
    $columns = $data['columns'];

    $myPdo->select_db($database);

    // Buat query CREATE TABLE
    $sql = "CREATE TABLE `{$tableName}` (";
    
    $columnDefinitions = [];
    $hasAutoIncrement = false;

    foreach ($columns as $column) {
        $def = "`{$column['name']}` {$column['type']}";
        
        if (!empty($column['length'])) {
            $def .= "({$column['length']})";
        }
        
        if (isset($column['nullable']) && !$column['nullable']) {
            $def .= " NOT NULL";
        }
        
        if (isset($column['autoIncrement']) && $column['autoIncrement']) {
            if (!$hasAutoIncrement) {
                $def .= " AUTO_INCREMENT";
                $hasAutoIncrement = true;
            }
        }
        
        $columnDefinitions[] = $def;
    }

    // Tambahkan PRIMARY KEY untuk kolom id
    $columnDefinitions[] = "PRIMARY KEY (`id`)";
    
    $sql .= implode(", ", $columnDefinitions);
    $sql .= ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    error_log("Executing query: " . $sql);

    if ($myPdo->query($sql)) {
        return [
            'status' => 'success',
            'message' => 'Table created successfully'
        ];
    }
    throw new Exception($myPdo->error);

} catch (Exception $e) {
    error_log("Error creating table: " . $e->getMessage());
    return [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
} 