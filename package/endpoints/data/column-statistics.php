<?php
use app\NgoreiDb;
$db = new NgoreiDb();
$myPdo = $db->connMysqli();

$data = json_decode(file_get_contents("php://input"), true);

// Log request untuk debugging
error_log("Received request: " . print_r($data, true));

// Cek action dari request
switch($data['action']) {
    case 'change_data_type':
        try {
            // Validasi input
            if (!isset($data['database']) || !isset($data['table']) || 
                !isset($data['column']) || !isset($data['newType'])) {
                return [
                    'status' => 'error',
                    'message' => 'Missing required parameters'
                ];
            }

            $database = $data['database'];
            $table = $data['table'];
            $column = $data['column'];
            $newType = $data['newType'];

            // Pilih database
            $myPdo->select_db($database);

            // Buat query ALTER TABLE
            $query = "ALTER TABLE `$table` MODIFY COLUMN `$column` $newType";
            error_log("Executing query: " . $query);

            if ($myPdo->query($query)) {
                return [
                    'status' => 'success',
                    'message' => 'Column type changed successfully',
                    'debug' => [
                        'query' => $query,
                        'database' => $database,
                        'table' => $table,
                        'column' => $column,
                        'newType' => $newType
                    ]
                ];
            } else {
                throw new Exception($myPdo->error);
            }
        } catch (Exception $e) {
            error_log("Error changing column type: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        break;

    case 'get_column_stats':
        try {
            // Validasi input
            if (!isset($data['database']) || !isset($data['table']) || !isset($data['column'])) {
                return [
                    'status' => 'error',
                    'message' => 'Missing required parameters'
                ];
            }

            $database = $data['database'];
            $table = $data['table'];
            $column = $data['column'];

            // Pilih database
            $myPdo->select_db($database);

            // Dapatkan informasi kolom
            $columnInfoQuery = "SELECT DATA_TYPE, IS_NULLABLE, COLUMN_TYPE, COLUMN_COMMENT 
                              FROM information_schema.COLUMNS 
                              WHERE TABLE_SCHEMA = ? 
                              AND TABLE_NAME = ? 
                              AND COLUMN_NAME = ?";
                              
            $stmt = $myPdo->prepare($columnInfoQuery);
            $stmt->bind_param('sss', $database, $table, $column);
            $stmt->execute();
            $columnInfo = $stmt->get_result()->fetch_assoc();

            if (!$columnInfo) {
                return [
                    'status' => 'error',
                    'message' => 'Column not found'
                ];
            }

            // Cek apakah kolom numerik
            $isNumeric = in_array(strtolower($columnInfo['DATA_TYPE']), 
                ['tinyint', 'smallint', 'mediumint', 'int', 'bigint', 'decimal', 'float', 'double']
            );

            // Query untuk statistik dasar
            $statsQuery = "SELECT 
                COUNT(*) as totalRows,
                COUNT(DISTINCT `$column`) as uniqueValues,
                COUNT(*) - COUNT(`$column`) as nullCount";
            
            if ($isNumeric) {
                $statsQuery .= ",
                    MIN(`$column`) as min,
                    MAX(`$column`) as max,
                    AVG(`$column`) as avg";
            }
            
            $statsQuery .= " FROM `$table`";
            
            $statsResult = $myPdo->query($statsQuery);
            $basicStats = $statsResult->fetch_assoc();

            // Query untuk sample values
            $sampleQuery = "SELECT DISTINCT `$column` 
                           FROM `$table` 
                           WHERE `$column` IS NOT NULL 
                           LIMIT 5";
            
            $sampleResult = $myPdo->query($sampleQuery);
            $sampleValues = [];
            while ($row = $sampleResult->fetch_array(MYSQLI_NUM)) {
                $sampleValues[] = $row[0];
            }

            return [
                'status' => 'success',
                'data' => [
                    'dataType' => $columnInfo['COLUMN_TYPE'],
                    'isNullable' => $columnInfo['IS_NULLABLE'] === 'YES',
                    'isNumeric' => $isNumeric,
                    'totalRows' => (int)$basicStats['totalRows'],
                    'uniqueValues' => (int)$basicStats['uniqueValues'],
                    'nullCount' => (int)$basicStats['nullCount'],
                    'min' => $isNumeric ? $basicStats['min'] : null,
                    'max' => $isNumeric ? $basicStats['max'] : null,
                    'avg' => $isNumeric ? round($basicStats['avg'], 2) : null,
                    'sampleValues' => $sampleValues,
                    'comment' => $columnInfo['COLUMN_COMMENT']
                ]
            ];

        } catch (Exception $e) {
            error_log("Error getting column statistics: " . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
        break;

    default:
        return [
            'status' => 'error',
            'message' => 'Invalid action'
        ];
}