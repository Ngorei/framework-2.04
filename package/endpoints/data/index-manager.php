<?php
use app\NgoreiDb;
$db = new NgoreiDb();
$myPdo = $db->connMysqli();

error_log("Received index manager request: " . file_get_contents("php://input"));

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['action'])) {
    return [
        'status' => 'error',
        'message' => 'Action not specified'
    ];
}

try {
    switch($data['action']) {
        case 'create_index':
            if (!isset($data['database'], $data['table'], $data['index_name'], 
                $data['index_type'], $data['index_columns'])) {
                throw new Exception('Missing required parameters');
            }

            $database = $data['database'];
            $table = $data['table'];
            $indexName = $data['index_name'];
            $indexType = $data['index_type'];
            $columns = implode(',', $data['index_columns']);

            $myPdo->select_db($database);

            $sql = "CREATE {$indexType} INDEX `{$indexName}` ON `{$table}` ({$columns})";
            error_log("Executing query: " . $sql);

            if ($myPdo->query($sql)) {
                return [
                    'status' => 'success',
                    'message' => 'Index created successfully'
                ];
            }
            throw new Exception($myPdo->error);

        case 'delete_index':
            if (!isset($data['database'], $data['table'], $data['index_name'])) {
                throw new Exception('Missing required parameters');
            }

            $database = $data['database'];
            $table = $data['table'];
            $indexName = $data['index_name'];

            $myPdo->select_db($database);

            error_log("Checking all indexes on table {$table}:");
            $allIndexes = $myPdo->query("SELECT DISTINCT INDEX_NAME 
                                        FROM INFORMATION_SCHEMA.STATISTICS 
                                        WHERE TABLE_SCHEMA = '{$database}' 
                                        AND TABLE_NAME = '{$table}'");
            
            while ($idx = $allIndexes->fetch_assoc()) {
                error_log("Found index: " . $idx['INDEX_NAME']);
            }

            $checkSql = "SELECT DISTINCT INDEX_NAME 
                         FROM INFORMATION_SCHEMA.STATISTICS 
                         WHERE TABLE_SCHEMA = '{$database}' 
                         AND TABLE_NAME = '{$table}' 
                         AND INDEX_NAME = '{$indexName}'";
                         
            error_log("Checking index with query: " . $checkSql);
            $result = $myPdo->query($checkSql);
            
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                if ($row['INDEX_NAME'] === 'PRIMARY') {
                    throw new Exception("Cannot delete PRIMARY KEY");
                }

                $sql = "DROP INDEX `{$indexName}` ON `{$table}`";
                error_log("Executing DROP INDEX query: " . $sql);
                
                if ($myPdo->query($sql)) {
                    return [
                        'status' => 'success',
                        'message' => 'Index deleted successfully'
                    ];
                }
                throw new Exception($myPdo->error ?: 'Failed to delete index');
            } else {
                error_log("Failed to find index: {$indexName}");
                throw new Exception("Index '{$indexName}' does not exist");
            }

        case 'get_indexes':
            if (!isset($data['database'], $data['table'])) {
                throw new Exception('Missing required parameters');
            }

            $database = $data['database'];
            $table = $data['table'];

            $myPdo->select_db($database);
            
            // Gunakan query sederhana untuk mendapatkan index
            $sql = "SHOW INDEXES FROM `{$table}`";
            error_log("Getting indexes with query: " . $sql);
            
            $result = $myPdo->query($sql);
            
            if ($result) {
                $indexes = [];
                while ($row = $result->fetch_assoc()) {
                    // Skip PRIMARY KEY
                    if ($row['Key_name'] === 'PRIMARY') {
                        continue;
                    }
                    
                    $indexName = $row['Key_name'];
                    error_log("Processing index: " . $indexName);
                    
                    // Inisialisasi index jika belum ada
                    if (!isset($indexes[$indexName])) {
                        $indexes[$indexName] = [
                            'name' => $indexName,
                            'type' => $row['Non_unique'] == 0 ? 'UNIQUE' : 'INDEX',
                            'columns' => []
                        ];
                    }
                    
                    // Tambahkan kolom ke index
                    $indexes[$indexName]['columns'][] = $row['Column_name'];
                }
                
                error_log("Found indexes: " . json_encode(array_values($indexes)));
                return [
                    'status' => 'success',
                    'data' => array_values($indexes)
                ];
            }
            throw new Exception($myPdo->error);

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log("Error in index manager: " . $e->getMessage());
    return [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
} 