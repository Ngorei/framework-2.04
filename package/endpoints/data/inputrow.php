<?php
use app\NgoreiDb;
$db = new NgoreiDb();
$myPdo = $db->connMysqli();

error_log("Received input row request: " . file_get_contents("php://input"));

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

            // Pastikan response dalam format yang benar
            echo json_encode([
                'status' => 'success',
                'data' => $columns
            ]);
            exit;

        case 'insert_row':
            if (!isset($data['data']) || !is_array($data['data'])) {
                throw new Exception('Invalid row data');
            }

            $rowData = $data['data'];
            
            // Build INSERT query
            $columns = array_keys($rowData);
            $values = array_values($rowData);
            
            // Escape values
            $escapedValues = array_map(function($value) use ($myPdo) {
                if ($value === null || $value === '') {
                    return 'NULL';
                }
                return "'" . $myPdo->real_escape_string($value) . "'";
            }, $values);

            $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) " .
                   "VALUES (" . implode(', ', $escapedValues) . ")";

            if (!$myPdo->query($sql)) {
                throw new Exception($myPdo->error);
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Row inserted successfully',
                'id' => $myPdo->insert_id
            ]);
            exit;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    error_log("Error in input row: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
    exit;
} 