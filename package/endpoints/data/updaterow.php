<?php
use app\NgoreiDb;
$db = new NgoreiDb();
$myPdo = $db->connMysqli();

error_log("Received update row request: " . file_get_contents("php://input"));

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
        case 'get_table_structure':
            $sql = "DESCRIBE `$table`";
            $result = $myPdo->query($sql);
            
            if (!$result) {
                throw new Exception($myPdo->error);
            }

            $columns = [];
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row;
            }

            echo json_encode([
                'status' => 'success',
                'data' => $columns
            ]);
            exit;

        case 'update_row':
            if (!isset($data['id'], $data['data']) || !is_array($data['data'])) {
                throw new Exception('Invalid update data');
            }

            $id = $data['id'];
            $updateData = $data['data'];
            
            // Build UPDATE query
            $updates = [];
            foreach ($updateData as $column => $value) {
                if ($value === null || $value === '') {
                    $updates[] = "`$column` = NULL";
                } else {
                    $escapedValue = $myPdo->real_escape_string($value);
                    $updates[] = "`$column` = '$escapedValue'";
                }
            }

            $sql = "UPDATE `$table` SET " . implode(', ', $updates) . " WHERE id = " . (int)$id;

            if (!$myPdo->query($sql)) {
                throw new Exception($myPdo->error);
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Row updated successfully',
                'affected_rows' => $myPdo->affected_rows
            ]);
            exit;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log("Error in update row: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    exit;
}
