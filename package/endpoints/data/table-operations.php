<?php
use app\NgoreiDb;
$db = new NgoreiDb();
$myPdo = $db->connMysqli();

error_log("Received table operation request: " . file_get_contents("php://input"));

try {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($data['action'])) {
        throw new Exception('Missing action parameter');
    }

    if (!isset($data['database'], $data['table'])) {
        throw new Exception('Missing required parameters');
    }

    $database = $data['database'];
    $table = $data['table'];
    
    $myPdo->select_db($database);

    switch ($data['action']) {
        case 'truncate_table':
            $sql = "TRUNCATE TABLE `$table`";
            if ($myPdo->query($sql)) {
                return [
                    'status' => 'success',
                    'message' => 'Table emptied successfully'
                ];
            }
            throw new Exception($myPdo->error);

        case 'drop_table':
            $sql = "DROP TABLE `$table`";
            if ($myPdo->query($sql)) {
                return [
                    'status' => 'success',
                    'message' => 'Table deleted successfully'
                ];
            }
            throw new Exception($myPdo->error);

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log("Error in table operations: " . $e->getMessage());
    return [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
} 