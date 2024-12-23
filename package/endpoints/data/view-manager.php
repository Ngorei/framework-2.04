<?php
use app\NgoreiDb;
$db = new NgoreiDb();
$myPdo = $db->connMysqli();

error_log("Received view manager request: " . file_get_contents("php://input"));

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['action'])) {
    return [
        'status' => 'error',
        'message' => 'Action not specified'
    ];
}

try {
    if ($data['action'] === 'create_view') {
        if (!isset($data['database'], $data['view_name'], $data['query'])) {
            throw new Exception('Missing required parameters');
        }

        $database = $data['database'];
        $viewName = $data['view_name'];
        $query = $data['query'];

        $myPdo->select_db($database);

        $sql = "CREATE VIEW `{$viewName}` AS {$query}";
        error_log("Executing query: " . $sql);

        if ($myPdo->query($sql)) {
            return [
                'status' => 'success',
                'message' => 'View created successfully'
            ];
        }
        throw new Exception($myPdo->error);
    }

    throw new Exception('Invalid action');
} catch (Exception $e) {
    error_log("Error in view manager: " . $e->getMessage());
    return [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
} 