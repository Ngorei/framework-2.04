<?php
use app\NgoreiDb;

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$db = new NgoreiDb();
$myPdo = $db->connMysqli();

try {
    $data = json_decode(file_get_contents("php://input"), true);
    
    if ($data['action'] === 'monitor_performance') {
        // Dapatkan metrics performa dasar
        $activeConnections = $myPdo->query("SHOW STATUS LIKE 'Threads_connected'")->fetch_assoc();
        $queriesPerSecond = $myPdo->query("SHOW STATUS LIKE 'Questions'")->fetch_assoc();
        $memoryUsage = $myPdo->query("SHOW STATUS LIKE 'Global_memory_used'")->fetch_assoc();
        
        // Informasi performa tabel aktif
        $tableStats = [];
        if (!empty($data['active_table']) && !empty($data['active_database'])) {
            // 1. Table size dan index size
            $tableSizeQuery = "SELECT 
                data_length/1024/1024 as data_size,
                index_length/1024/1024 as index_size,
                table_rows,
                update_time
            FROM information_schema.tables 
            WHERE table_schema = ? AND table_name = ?";
            
            $stmt = $myPdo->prepare($tableSizeQuery);
            $stmt->bind_param('ss', $data['active_database'], $data['active_table']);
            $stmt->execute();
            $tableInfo = $stmt->get_result()->fetch_assoc();
            
            // 2. Index statistics
            $indexStatsQuery = "SHOW INDEX FROM {$data['active_database']}.{$data['active_table']}";
            $indexStats = $myPdo->query($indexStatsQuery);
            $indexes = [];
            while ($row = $indexStats->fetch_assoc()) {
                $indexes[] = [
                    'name' => $row['Key_name'],
                    'column' => $row['Column_name'],
                    'cardinality' => $row['Cardinality']
                ];
            }
            
            // 3. Table status (termasuk auto_increment, row_format dll)
            $tableStatusQuery = "SHOW TABLE STATUS FROM {$data['active_database']} LIKE '{$data['active_table']}'";
            $tableStatus = $myPdo->query($tableStatusQuery)->fetch_assoc();
            
            // 4. Query performance pada tabel ini
            $tableQueriesQuery = "SELECT event_time, argument as query, 
                TIMESTAMPDIFF(MICROSECOND, event_time, NOW())/1000 as duration
            FROM mysql.general_log 
            WHERE argument LIKE ?
            AND command_type='Query'
            ORDER BY event_time DESC LIMIT 5";
            
            $searchPattern = "%{$data['active_table']}%";
            $stmt = $myPdo->prepare($tableQueriesQuery);
            $stmt->bind_param('s', $searchPattern);
            $stmt->execute();
            $tableQueries = $stmt->get_result();
            
            $recentTableQueries = [];
            while ($row = $tableQueries->fetch_assoc()) {
                $recentTableQueries[] = [
                    'timestamp' => $row['event_time'],
                    'query' => $row['query'],
                    'duration' => round($row['duration'], 2)
                ];
            }
            
            $tableStats = [
                'size_info' => [
                    'data_size' => round($tableInfo['data_size'], 2),
                    'index_size' => round($tableInfo['index_size'], 2),
                    'total_rows' => $tableInfo['table_rows'],
                    'last_update' => $tableInfo['update_time']
                ],
                'indexes' => $indexes,
                'status' => [
                    'engine' => $tableStatus['Engine'],
                    'row_format' => $tableStatus['Row_format'],
                    'auto_increment' => $tableStatus['Auto_increment'],
                    'collation' => $tableStatus['Collation'],
                    'create_time' => $tableStatus['Create_time']
                ],
                'recent_queries' => $recentTableQueries
            ];
        }
        
        // Metrics database secara umum
        $dbSize = 0;
        $sizeQuery = $myPdo->query("SELECT table_schema AS 'Database',
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size' 
            FROM information_schema.tables 
            GROUP BY table_schema");
        
        if ($sizeQuery) {
            while ($row = $sizeQuery->fetch_assoc()) {
                $dbSize += floatval($row['Size']);
            }
        }
        
        // Recent queries global
        $recentQueries = [];
        $logQuery = "SELECT event_time as timestamp, 
                           argument as sql_text,
                           TIMESTAMPDIFF(MICROSECOND, event_time, NOW())/1000 as duration
                    FROM mysql.general_log 
                    WHERE command_type='Query'
                    AND argument NOT LIKE 'SHOW%'
                    AND argument NOT LIKE 'SELECT * FROM mysql.general_log%'
                    ORDER BY event_time DESC LIMIT 5";
                    
        $result = $myPdo->query($logQuery);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recentQueries[] = [
                    'timestamp' => $row['timestamp'],
                    'sql' => $row['sql_text'],
                    'duration' => round($row['duration'], 2),
                    'status' => 'success'
                ];
            }
        }
        
        return [
            'status' => 'success',
            'active_connections' => (int)($activeConnections['Value'] ?? 0),
            'queries_per_second' => (int)($queriesPerSecond['Value'] ?? 0),
            'memory_usage' => round(($memoryUsage['Value'] ?? 0) / 1024 / 1024, 2),
            'database_size' => round($dbSize, 2),
            'recent_queries' => $recentQueries,
            'active_table_stats' => $tableStats
        ];
    } else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    return [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}
