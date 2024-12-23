<?php
use app\NgoreiDb;
$db = new NgoreiDb();
$myPdo = $db->connMysqli();

error_log("Received backup manager request: " . file_get_contents("php://input"));

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action']) && $_POST['action'] === 'restore') {
            // Handle restore
            if (!isset($_FILES['file'], $_POST['database'])) {
                throw new Exception('Missing required parameters');
            }

            $database = $_POST['database'];
            $file = $_FILES['file'];

            $myPdo->select_db($database);
            
            // Read and execute SQL file
            $sql = file_get_contents($file['tmp_name']);
            if ($myPdo->multi_query($sql)) {
                return [
                    'status' => 'success',
                    'message' => 'Database restored successfully'
                ];
            }
            throw new Exception($myPdo->error);
        } else {
            // Handle backup
            $data = json_decode(file_get_contents("php://input"), true);
            if (!isset($data['database'])) {
                throw new Exception('Missing required parameters');
            }

            $database = $data['database'];
            $myPdo->select_db($database);

            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . $database . '_backup.sql"');

            // Get all tables
            $tables = [];
            $result = $myPdo->query("SHOW TABLES");
            while ($row = $result->fetch_row()) {
                $tables[] = $row[0];
            }

            // Generate SQL dump
            foreach ($tables as $table) {
                $result = $myPdo->query("SHOW CREATE TABLE `$table`");
                $row = $result->fetch_row();
                echo $row[1] . ";\n\n";

                $result = $myPdo->query("SELECT * FROM `$table`");
                while ($row = $result->fetch_assoc()) {
                    $values = array_map(function($value) use ($myPdo) {
                        return $value === null ? 'NULL' : "'" . $myPdo->real_escape_string($value) . "'";
                    }, $row);
                    echo "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n";
                }
                echo "\n";
            }
            exit;
        }
    }
} catch (Exception $e) {
    error_log("Error in backup manager: " . $e->getMessage());
    return [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
} 