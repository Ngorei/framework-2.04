<?php
use app\NgoreiDb;
$db = new NgoreiDb();
$myPdo = $db->connMysqli();

error_log("Received trigger manager request: " . file_get_contents("php://input"));

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['action'])) {
    return [
        'status' => 'error',
        'message' => 'Action not specified'
    ];
}

try {
    if ($data['action'] === 'create_trigger') {
        if (!isset($data['database'], $data['table'], $data['trigger_name'], 
            $data['timing'], $data['event'], $data['statement'])) {
            throw new Exception('Missing required parameters');
        }

        $database = $data['database'];
        $table = $data['table'];
        $triggerName = $data['trigger_name'];
        $timing = $data['timing'];
        $event = $data['event'];
        $statement = $data['statement'];

        $myPdo->select_db($database);

        $sql = "CREATE TRIGGER `{$triggerName}` {$timing} {$event} ON `{$table}`
                FOR EACH ROW
                {$statement}";
        error_log("Executing query: " . $sql);

        if ($myPdo->query($sql)) {
            return [
                'status' => 'success',
                'message' => 'Trigger created successfully'
            ];
        }
        throw new Exception($myPdo->error);
    }

    throw new Exception('Invalid action');
} catch (Exception $e) {
    error_log("Error in trigger manager: " . $e->getMessage());
    return [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
} 