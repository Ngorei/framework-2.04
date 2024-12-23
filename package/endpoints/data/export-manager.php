<?php
use app\NgoreiDb;
$db = new NgoreiDb();
$myPdo = $db->connMysqli();

error_log("Received export manager request: " . file_get_contents("php://input"));

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (isset($data['action']) && $data['action'] === 'export_table') {
            $database = $data['database'];
            $table = $data['table'];
            $format = $data['format'];
            
            $myPdo->select_db($database);

            switch($format) {
                case 'sql':
                    header('Content-Type: application/sql');
                    header('Content-Disposition: attachment; filename="' . $table . '_export.sql"');
                    
                    // Generate SQL dump
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
                    break;

                case 'csv':
                    header('Content-Type: text/csv');
                    header('Content-Disposition: attachment; filename="' . $table . '_export.csv"');
                    
                    // Get column names
                    $result = $myPdo->query("SHOW COLUMNS FROM `$table`");
                    $columns = [];
                    while ($row = $result->fetch_assoc()) {
                        $columns[] = $row['Field'];
                    }
                    echo implode(',', $columns) . "\n";

                    // Get data
                    $result = $myPdo->query("SELECT * FROM `$table`");
                    while ($row = $result->fetch_assoc()) {
                        $values = array_map(function($value) {
                            return $value === null ? '' : '"' . str_replace('"', '""', $value) . '"';
                        }, $row);
                        echo implode(',', $values) . "\n";
                    }
                    break;

                case 'json':
                    header('Content-Type: application/json');
                    header('Content-Disposition: attachment; filename="' . $table . '_export.json"');
                    
                    $result = $myPdo->query("SELECT * FROM `$table`");
                    $data = [];
                    while ($row = $result->fetch_assoc()) {
                        $data[] = $row;
                    }
                    echo json_encode($data, JSON_PRETTY_PRINT);
                    break;

                default:
                    throw new Exception('Format tidak didukung: ' . $format);
            }
            exit;
        } elseif (isset($data['action']) && $data['action'] === 'export_database') {
            $database = $data['database'];
            $format = $data['format'];
            
            $myPdo->select_db($database);

            switch($format) {
                case 'sql':
                    header('Content-Type: application/sql');
                    header('Content-Disposition: attachment; filename="' . $database . '_export.sql"');
                    
                    // Get all tables
                    $tables = [];
                    $result = $myPdo->query("SHOW TABLES");
                    while ($row = $result->fetch_row()) {
                        $tables[] = $row[0];
                    }

                    // Export each table
                    foreach ($tables as $table) {
                        // Table structure
                        $result = $myPdo->query("SHOW CREATE TABLE `$table`");
                        $row = $result->fetch_row();
                        echo "DROP TABLE IF EXISTS `$table`;\n";
                        echo $row[1] . ";\n\n";

                        // Table data
                        $result = $myPdo->query("SELECT * FROM `$table`");
                        while ($row = $result->fetch_assoc()) {
                            $values = array_map(function($value) use ($myPdo) {
                                return $value === null ? 'NULL' : "'" . $myPdo->real_escape_string($value) . "'";
                            }, $row);
                            echo "INSERT INTO `$table` VALUES (" . implode(',', $values) . ");\n";
                        }
                        echo "\n";
                    }
                    break;

                default:
                    throw new Exception('Format tidak didukung untuk ekspor database: ' . $format);
            }
            exit;
        }

        // Handle import
        if (isset($_POST['action']) && $_POST['action'] === 'import') {
            if (!isset($_FILES['file'], $_POST['database'], $_POST['table'])) {
                throw new Exception('Missing required parameters');
            }

            $database = $_POST['database'];
            $table = $_POST['table'];
            $file = $_FILES['file'];

            $myPdo->select_db($database);
            
            // Read and execute SQL file
            $sql = file_get_contents($file['tmp_name']);
            if ($myPdo->multi_query($sql)) {
                return [
                    'status' => 'success',
                    'message' => 'Data imported successfully'
                ];
            }
            throw new Exception($myPdo->error);
        }
    }
} catch (Exception $e) {
    error_log("Error in export manager: " . $e->getMessage());
    http_response_code(500);
    return [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}