<?php
use app\NgoreiDb;
$db = new NgoreiDb();
$myPdo = $db->connMysqli();

error_log("Received FK manager request: " . file_get_contents("php://input"));

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['action'])) {
    return [
        'status' => 'error',
        'message' => 'Action not specified'
    ];
}

try {
    if ($data['action'] === 'create_fk') {
        if (!isset($data['database'], $data['table'], $data['constraint_name'], 
            $data['column_name'], $data['reference_table'], $data['reference_column'])) {
            throw new Exception('Missing required parameters');
        }

        $database = $data['database'];
        $table = $data['table'];
        $constraintName = $data['constraint_name'];
        $column = $data['column_name'];
        $refTable = $data['reference_table'];
        $refColumn = $data['reference_column'];

        $myPdo->select_db($database);

        $sql = "ALTER TABLE `{$table}` ADD CONSTRAINT `{$constraintName}` 
                FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}`(`{$refColumn}`)";
        error_log("Executing query: " . $sql);

        if ($myPdo->query($sql)) {
            return [
                'status' => 'success',
                'message' => 'Foreign key created successfully'
            ];
        }
        throw new Exception($myPdo->error);
    }

    throw new Exception('Invalid action');
} catch (Exception $e) {
    error_log("Error in FK manager: " . $e->getMessage());
    return [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
} 