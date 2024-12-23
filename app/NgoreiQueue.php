<?php
namespace app;
use app\NgoreiDb;
use app\Cache\CacheInterface;
use app\Cache\MemoryCache;
class NgoreiQueue {
    private NgoreiDb $db;
    private string $table_name;
    private $lastError = '';
    private string $nodeId;
    private const LOCK_TIMEOUT = 30; // dalam detik
    private array $rateLimits = [];
    private const RATE_LIMIT_WINDOW = 60;
    private const MAX_REQUESTS = 1000;
    private $connectionPool = [];
    private CacheInterface $cache;
    private array $cacheConfig = [
        'ttl' => 300,
        'prefix' => 'queue:',
        'enabled' => true,  // default enabled
        'views_ttl' => 60,  // TTL khusus untuk view queue
        'stats_ttl' => 300  // TTL khusus untuk statistik
    ];
    private bool $cacheEnabled = true;
    
    public function __construct(
        string $table_name = 'queues',
        string $nodeId = null,
        ?CacheInterface $cache = null
    ) {
        $this->db = new NgoreiDb();
        $this->table_name = $table_name;
        $this->nodeId = $nodeId ?? gethostname();
        $this->cache = $cache ?? new MemoryCache();
    }
    
    /**
     * Menambahkan item ke dalam antrian
     * @param string $queue_name Nama antrian
     * @param array $data Data yang akan disimpan
     * @return bool
     */
    public function push(string $queue_name, array $data): bool {
        if (empty($queue_name) || empty($data)) {
            $this->lastError = "Queue name dan data tidak boleh kosong";
            return false;
        }
        
        $sql = "INSERT INTO {$this->table_name} (queue_name, payload, created_at, status) 
                VALUES (?, ?, NOW(), 'pending')";
        
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $queue_name,
                json_encode($data)
            ]);
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("Error dalam push(): " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Mengambil item berikutnya dari antrian
     * @param string $queue_name Nama antrian
     * @return array|null
     */
    public function pop(string $queue_name): ?array {
        try {
            // 1. Ambil item yang pending
            $selectSql = "SELECT id, payload, attempts FROM {$this->table_name} 
                          WHERE queue_name = ? AND status = 'pending' 
                          AND (attempts < max_attempts OR max_attempts IS NULL)
                          ORDER BY created_at ASC 
                          LIMIT 1";
            
            $stmt = $this->db->prepare($selectSql);
            $stmt->execute([$queue_name]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$result) {
                return null;
            }
            
            // 2. Update statusnya
            $updateSql = "UPDATE {$this->table_name} SET status = 'processing' WHERE id = ?";
            $updateStmt = $this->db->prepare($updateSql);
            $updateSuccess = $updateStmt->execute([$result['id']]);
            
            if (!$updateSuccess) {
                return null;
            }
            
            // Debug log
            error_log("Successfully popped item ID: " . $result['id']);
            
            return [
                'id' => $result['id'],
                'data' => json_decode($result['payload'], true)
            ];
            
        } catch (\Exception $e) {
            error_log("Error in pop(): " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Menandai item antrian sudah selesai
     * @param int $id ID antrian
     * @return bool
     */
    public function complete(int $id): bool {
        $sql = "UPDATE {$this->table_name} SET status = 'completed', completed_at = NOW() WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$id]);
        } catch (\Exception $e) {
            // Tambahkan logging jika perlu
            error_log("Error completing queue item: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Menandai item antrian gagal
     * @param int $id ID antrian
     * @param string $error Pesan error
     * @return bool
     */
    public function fail(int $id, string $error = ''): bool {
        $sql = "UPDATE {$this->table_name} SET status = 'failed', error = ?, failed_at = NOW() WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$error, $id]);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Memeriksa status antrian
     * @param string|null $queue_name Optional, filter berdasarkan nama queue
     * @return array
     */
    public function checkStatus(?string $queue_name = null): array {
        $sql = "SELECT queue_name, status, COUNT(*) as total 
                FROM {$this->table_name}";
        
        if ($queue_name) {
            $sql .= " WHERE queue_name = ?";
        }
        
        $sql .= " GROUP BY queue_name, status";
        
        try {
            $stmt = $this->db->prepare($sql);
            if ($queue_name) {
                $stmt->execute([$queue_name]);
            } else {
                $stmt->execute();
            }
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }
    
    /**
     * Melihat detail item dalam antrian
     * @param string $queue_name
     * @param string|null $status Optional, filter berdasarkan status
     * @return array
     */
    public function viewQueue(string $queue_name, ?string $status = null): array {
        // Skip cache jika dinonaktifkan
        if (!$this->cacheConfig['enabled']) {
            return $this->fetchQueueFromDB($queue_name, $status);
        }

        $cacheKey = $this->getCacheKey("view:{$queue_name}:" . ($status ?? 'all'));
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        $result = $this->fetchQueueFromDB($queue_name, $status);
        
        // Cache hanya jika enabled
        $this->cache->set($cacheKey, $result, $this->cacheConfig['views_ttl']);
        
        return $result;
    }

    // Pisahkan logic database ke method terpisah
    private function fetchQueueFromDB(string $queue_name, ?string $status = null): array {
        try {
            $sql = "SELECT id, queue_name AS tabel, payload, status, created_at, updated_at,completed_at
                    FROM {$this->table_name} 
                    WHERE queue_name = ?";
            
            if ($status) {
                $sql .= " AND status = ?";
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            if ($status) {
                $stmt->execute([$queue_name, $status]);
            } else {
                $stmt->execute([$queue_name]);
            }
            
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Proses setiap baris untuk menggabungkan payload
            return array_map(function($row) {
                $payload = json_decode($row['payload'], true);
                unset($row['payload']); // Hapus payload asli
                return array_merge($row, $payload); // Gabungkan payload dengan data utama
            }, $results);

        } catch (\Exception $e) {
            error_log("Error dalam fetchQueueFromDB: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Mengambil dan memproses item dari antrian
     * @param string $queue_name Nama antrian
     * @param callable $processor Fungsi untuk memproses item
     * @return bool
     */
    public function process(string $queue_name, callable $processor): bool {
        // Ambil item dari antrian
        $item = $this->pop($queue_name);
        
        if (!$item) {
            return false;
        }
        
        try {
            // Proses item
            $processor($item['data']);
            
            // Tandai sebagai selesai
            return $this->complete($item['id']);
        } catch (\Exception $e) {
            // Jika gagal, tandai sebagai error
            $this->fail($item['id'], $e->getMessage());
            return false;
        }
    }
    
    public function cleanup(int $daysOld = 30): bool {
        $sql = "DELETE FROM {$this->table_name} WHERE status IN ('completed', 'failed') 
                AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$daysOld]);
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Membuat tabel queue dengan nama yang dinamis
     * @param string|null $table_name Nama tabel (opsional)
     * @return bool
     */
    public function createQueueTable(?string $table_name = null): bool {
        $table = $table_name ?? $this->table_name;
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
            $this->lastError = "Nama tabel tidak valid: " . $table;
            return false;
        }

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            queue_name VARCHAR(255) NOT NULL,
            payload JSON NOT NULL,
            status ENUM('pending', 'processing', 'completed', 'failed', 'retry') DEFAULT 'pending',
            priority INT UNSIGNED DEFAULT 0,
            attempts INT UNSIGNED DEFAULT 0,
            max_attempts INT UNSIGNED DEFAULT 3,
            error TEXT,
            retry_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            failed_at TIMESTAMP NULL,
            processing_node VARCHAR(255) NULL,
            lock_acquired_at TIMESTAMP NULL,
            INDEX idx_queue_status (queue_name, status),
            INDEX idx_priority (priority),
            INDEX idx_created_at (created_at),
            INDEX idx_processing_node (processing_node, lock_acquired_at),
            INDEX idx_queue_priority_created (queue_name, priority, created_at),
            INDEX idx_status_created (status, created_at),
            INDEX idx_queue_status_priority (queue_name, status, priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute();
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("Error creating queue table: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mengecek apakah tabel sudah ada
     * @param string|null $table_name Nama tabel (opsional)
     * @return bool
     */
    public function tableExists(?string $table_name = null): bool {
        $table = $table_name ?? $this->table_name;
        
        try {
            $sql = "SHOW TABLES LIKE ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$table]);
            return $stmt->rowCount() > 0;
        } catch (\Exception $e) {
            error_log("Error checking table existence: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Menghapus tabel queue
     * @param string|null $table_name Nama tabel (opsional)
     * @return bool
     */
    public function dropTable(?string $table_name = null): bool {
        $table = $table_name ?? $this->table_name;
        
        try {
            $sql = "DROP TABLE IF EXISTS `{$table}`";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute();
        } catch (\Exception $e) {
            error_log("Error dropping table '{$table}': " . $e->getMessage());
            return false;
        }
    }

    /**
     * Menambahkan item ke dalam antrian dengan prioritas
     * @param string $queue_name Nama antrian
     * @param array $data Data yang akan disimpan
     * @param int $priority Prioritas (0-10, 10 tertinggi)
     * @return bool
     */
    public function pushWithPriority(string $queue_name, array $data, int $priority = 0): bool {
        if (empty($queue_name) || empty($data)) {
            return false;
        }
        
        $priority = max(0, min(10, $priority)); // Batasi prioritas 0-10
        
        $sql = "INSERT INTO {$this->table_name} (queue_name, payload, priority, created_at, status) 
                VALUES (?, ?, ?, NOW(), 'pending')";
        
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $queue_name,
                json_encode($data),
                $priority
            ]);
        } catch (\Exception $e) {
            error_log("Error pushing to queue: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mencoba ulang job yang gagal
     * @param int $id ID antrian
     * @return bool
     */
    public function retry(int $id): bool {
        try {
            // Update attempts dan status
            $sql = "UPDATE {$this->table_name} 
                    SET status = 'pending',
                        attempts = attempts + 1,
                        retry_at = NOW()
                    WHERE id = ? AND status = 'failed'
                    AND attempts < max_attempts";
            
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$id]);
        } catch (\Exception $e) {
            error_log("Error retrying job: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Memproses batch jobs sekaligus
     * @param string $queue_name Nama antrian
     * @param callable $processor Fungsi processor
     * @param int $batchSize Jumlah item per batch
     * @return array Status hasil processing
     */
    public function processBatch(string $queue_name, callable $processor, int $batchSize = 10): array {
        $results = [
            'success' => 0,
            'failed' => 0,
            'items' => []
        ];
        
        try {
            // Ambil multiple items
            $sql = "SELECT id, payload FROM {$this->table_name}
                    WHERE queue_name = ? AND status = 'pending'
                    ORDER BY priority DESC, created_at ASC
                    LIMIT ?";
                    
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$queue_name, $batchSize]);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($items as $item) {
                try {
                    $processor(json_decode($item['payload'], true));
                    $this->complete($item['id']);
                    $results['success']++;
                    $results['items'][] = ['id' => $item['id'], 'status' => 'success'];
                } catch (\Exception $e) {
                    $this->fail($item['id'], $e->getMessage());
                    $results['failed']++;
                    $results['items'][] = ['id' => $item['id'], 'status' => 'failed'];
                }
            }
        } catch (\Exception $e) {
            error_log("Batch processing error: " . $e->getMessage());
        }
        
        return $results;
    }

    /**
     * Mendapatkan statistik queue
     * @param string|null $queue_name Optional, filter berdasarkan nama queue
     * @return array
     */
    public function getStatistics(?string $queue_name = null): array {
        if (!$this->cacheConfig['enabled']) {
            return $this->fetchStatisticsFromDB($queue_name);
        }

        $cacheKey = $this->getCacheKey("stats:{$queue_name}");
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        $stats = $this->fetchStatisticsFromDB($queue_name);
        
        $this->cache->set($cacheKey, $stats, $this->cacheConfig['stats_ttl']);
        
        return $stats;
    }

    private function getCacheKey(string $key): string {
        return $this->cacheConfig['prefix'] . $key;
    }
    
    private function invalidateCache(string $pattern): void {
        // Implementasi untuk invalidasi cache berdasarkan pattern
    }

    /**
     * Menghapus item antrian berdasarkan ID
     * @param int $id ID antrian yang akan dihapus
     * @return bool
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM {$this->table_name} WHERE id = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$id]);
        } catch (\Exception $e) {
            error_log("Error menghapus item antrian: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mengupdate item antrian berdasarkan ID
     * @param int $id ID antrian yang akan diupdate
     * @param array $data Data baru yang akan diupdate
     * @param array $options Opsi tambahan (priority, status, dll)
     * @return bool
     */
    public function update(int $id, array $data, array $options =[
          'priority' => 5,
          'status' => 'completed',
          'max_attempts' => 5
      ]): bool {
        try {
            // Tambah debug log
            error_log("Mencoba update item ID: {$id}");
            error_log("Data: " . json_encode($data));
            error_log("Options: " . json_encode($options));
            
            // Validasi data
            if (empty($data)) {
                $this->lastError = "Data tidak boleh kosong";
                error_log("Error: Data kosong");
                return false;
            }
            
            // Cek item exists
            $checkSql = "SELECT id FROM {$this->table_name} WHERE id = ?";
            $checkStmt = $this->db->prepare($checkSql);
            $checkStmt->execute([$id]);
            
            if (!$checkStmt->fetch()) {
                $this->lastError = "Item dengan ID {$id} tidak ditemukan";
                error_log("Error: Item ID {$id} tidak ditemukan");
                return false;
            }
            
            // Tambahkan updated_at ke updates
            $updates = ['payload = ?', 'updated_at = NOW()'];
            $params = [json_encode($data)];
            
            // Validasi status dengan konstanta
            if (isset($options['status'])) {
                $validStatus = ['pending', 'processing', 'completed', 'failed', 'retry'];
                if (!in_array($options['status'], $validStatus)) {
                    $this->lastError = "Status tidak valid";
                    return false;
                }
                $updates[] = 'status = ?';
                $params[] = $options['status'];
                
                // Tambahkan timestamp sesuai status
                if ($options['status'] === 'completed') {
                    $updates[] = 'completed_at = NOW()';
                } elseif ($options['status'] === 'failed') {
                    $updates[] = 'failed_at = NOW()';
                }
            }
            
            if (isset($options['priority'])) {
                $priority = max(0, min(10, $options['priority']));
                $updates[] = 'priority = ?';
                $params[] = $priority;
            }
            
            if (isset($options['max_attempts'])) {
                $updates[] = 'max_attempts = ?';
                $params[] = max(1, (int)$options['max_attempts']);
            }
            
            // Tambahkan error message jika status failed
            if (isset($options['error']) && $options['status'] === 'failed') {
                $updates[] = 'error = ?';
                $params[] = $options['error'];
            }
            
            // Tambahkan ID di akhir params
            $params[] = $id;
            
            $sql = "UPDATE {$this->table_name} 
                    SET " . implode(', ', $updates) . "
                    WHERE id = ?";
            
            // Debug SQL
            error_log("SQL Query: " . $sql);
            error_log("Parameters: " . json_encode($params));
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);
            
            if (!$result) {
                error_log("Error executing query: " . json_encode($stmt->errorInfo()));
            }
            
            return $result;
            
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            error_log("Error mengupdate item antrian: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Method untuk debugging item
     * @param int $id
     * @return array|null
     */
    public function debugItem(int $id): ?array {
        try {
            $sql = "SELECT * FROM {$this->table_name} WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            error_log("Debug error: " . $e->getMessage());
            return null;
        }
    }

    public function getLastError() {
        return $this->lastError;
    }

    private function acquireLock(int $itemId): bool {
        $sql = "UPDATE {$this->table_name} 
                SET processing_node = ?, 
                    lock_acquired_at = NOW() 
                WHERE id = ? 
                AND (processing_node IS NULL 
                     OR lock_acquired_at < DATE_SUB(NOW(), INTERVAL ? SECOND))";
        
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$this->nodeId, $itemId, self::LOCK_TIMEOUT]);
        } catch (\Exception $e) {
            error_log("Lock acquisition failed: " . $e->getMessage());
            return false;
        }
    }

    private function releaseLock(int $itemId): bool {
        $sql = "UPDATE {$this->table_name} 
                SET processing_node = NULL, 
                    lock_acquired_at = NULL 
                WHERE id = ? 
                AND processing_node = ?";
        
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([$itemId, $this->nodeId]);
        } catch (\Exception $e) {
            error_log("Lock release failed: " . $e->getMessage());
            return false;
        }
    }

    // 1. RESTful API
    public function handleApiRequest(string $method, string $endpoint, array $data): array {
        switch ($method) {
            case 'GET':
                return $this->handleGetRequest($endpoint, $data);
            case 'POST':
                return $this->handlePostRequest($endpoint, $data);
            // dll
        }
    }

    // 2. GraphQL Support
    public function registerGraphQLSchema(): void {
        // Implementasi GraphQL schema
    }

    // 1. Metrics Collection
    public function collectMetrics(): array {
        return [
            'throughput' => $this->calculateThroughput(),
            'latency' => $this->measureLatency(),
            'error_rate' => $this->calculateErrorRate(),
            'queue_depth' => $this->getQueueDepth()
        ];
    }

    // 2. Health Checks
    public function healthCheck(): array {
        return [
            'database_connection' => $this->checkDatabaseConnection(),
            'queue_processing' => $this->checkQueueProcessing(),
            'memory_usage' => memory_get_usage(true),
            'uptime' => $this->getUptime()
        ];
    }

    // 1. Rate Limiting
    private function checkRateLimit(string $queue_name): bool {
        $key = "rate_limit:{$queue_name}";
        if (!isset($this->rateLimits[$key])) {
            $this->rateLimits[$key] = ['count' => 0, 'timestamp' => time()];
        }
        // Implementasi rate limiting logic
    }

    // 2. Access Control
    public function setAccessControl(array $permissions): void {
        $this->validatePermissions($permissions);
        $this->permissions = $permissions;
    }

    private function getConnection(): NgoreiDb {
        $poolKey = getmypid();
        if (!isset($this->connectionPool[$poolKey])) {
            $this->connectionPool[$poolKey] = new NgoreiDb();
        }
        return $this->connectionPool[$poolKey];
    }

    // Tambahkan method untuk invalidasi cache saat ada perubahan queue
    private function invalidateViewCache(string $queue_name): void {
        $this->cache->delete($this->getCacheKey("view:{$queue_name}:all"));
        $statuses = ['pending', 'processing', 'completed', 'failed', 'retry'];
        foreach ($statuses as $status) {
            $this->cache->delete($this->getCacheKey("view:{$queue_name}:{$status}"));
        }
    }

    // Tambahkan method untuk mengatur cache
    public function setCacheConfig(array $config): void {
        $this->cacheConfig = array_merge($this->cacheConfig, $config);
    }

    public function enableCache(): void {
        $this->cacheConfig['enabled'] = true;
    }

    public function disableCache(): void {
        $this->cacheConfig['enabled'] = false;
    }

    public function isCacheEnabled(): bool {
        return $this->cacheConfig['enabled'];
    }

    // Tambahkan transaction support
    private function executeInTransaction(callable $callback) {
        try {
            $this->db->beginTransaction();
            $result = $callback();
            $this->db->commit();
            return $result;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Mendapatkan item antrian berdasarkan ID
     * @param int $id ID antrian yang akan ditampilkan
     * @return array|null Data item antrian atau null jika tidak ditemukan
     */
    public function refId(int $id): ?array {
        try {
            // Cek cache terlebih dahulu jika cache diaktifkan
            if ($this->cacheConfig['enabled']) {
                $cacheKey = $this->getCacheKey("item:{$id}");
                if ($cached = $this->cache->get($cacheKey)) {
                    // Jika ada cache, langsung return dalam format yang diinginkan
                    $payload = $cached['payload'];
                    unset($cached['payload']);
                    return array_merge($cached, $payload);
                }
            }

            // Query untuk mengambil data (hanya kolom yang ada)
            $sql = "SELECT id, queue_name, payload, status, created_at, updated_at, 
                           completed_at
                    FROM {$this->table_name} 
                    WHERE id = ?";
                    
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$result) {
                return null;
            }
            
            // Decode payload JSON dan format ulang hasil
            $payload = json_decode($result['payload'], true);
            unset($result['payload']);
            $formattedResult = array_merge($result, $payload);
            
            // Simpan ke cache jika cache diaktifkan
            if ($this->cacheConfig['enabled']) {
                $cacheKey = $this->getCacheKey("item:{$id}");
                $this->cache->set($cacheKey, $formattedResult, $this->cacheConfig['ttl']);
            }
            
            return $formattedResult;
            
        } catch (\Exception $e) {
            error_log("Error dalam refId: " . $e->getMessage());
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function getQueueData() {
        // Implementasi logika untuk mendapatkan data queue
        return [
            'status' => 'success',
            'data' => [] // data queue
        ];
    }

    /**
     * Menampilkan daftar semua tabel dalam database
     * @return array Daftar nama tabel
     */
    public function showTables(): array {
        try {
            // Gunakan PDO connection dari NgoreiDb
            $db = new NgoreiDb();
            $myPdo = $db->connPDO();
            
            // Query untuk mendapatkan daftar tabel
            $sql = "SHOW TABLES";
            $stmt = $myPdo->prepare($sql);
            $stmt->execute();
            
            // Ambil semua hasil dalam bentuk array
            $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            // Format hasil
            $result = [];
            foreach ($tables as $table) {
                $result[] = [
                    'tabel' => $table,
                    'created_at' => $this->getTableCreationTime($myPdo, $table)
                ];
            }
            
            return $result;
            
        } catch (\Exception $e) {
            error_log("Error dalam showTables: " . $e->getMessage());
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    /**
     * Method helper untuk mendapatkan waktu pembuatan tabel
     * @param \PDO $pdo
     * @param string $tableName
     * @return string|null
     */
    private function getTableCreationTime(\PDO $pdo, string $tableName): ?string {
        try {
            $sql = "SELECT CREATE_TIME 
                    FROM information_schema.TABLES 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = ?";
                    
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$tableName]);
            $result = $stmt->fetch(\PDO::FETCH_COLUMN);
            
            return $result ?: null;
            
        } catch (\Exception $e) {
            error_log("Error getting table creation time: " . $e->getMessage());
            return null;
        }
    }
}

class QueueManagerSDK {
    public function generateSDK(string $language): void {
        switch ($language) {
            case 'php':
                $this->generatePHPSDK();
                break;
            case 'javascript':
                $this->generateJavaScriptSDK();
                break;
            // ... bahasa lainnya
        }
    }
    
    private function generatePHPSDK(): void {
        // Generate PHP client library
    }
}

class QueueManagerAPIDoc {
    public function generateSwaggerDoc(): array {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'Queue Manager API',
                'version' => '1.0.0'
            ],
            'paths' => [
                '/queue/push' => [
                    'post' => [
                        'summary' => 'Push item ke queue',
                        'parameters' => [
                            // ... parameter specs
                        ]
                    ]
                ]
                // ... path lainnya
            ]
        ];
    }
}

class QueueManagerAPI {
    private QueueManager $queueManager;
    
    // Endpoint untuk Queue Operations
    public function handleRequest(string $method, string $endpoint): array {
        switch ("$method $endpoint") {
            case 'POST /queue/push':
                return $this->pushToQueue();
            case 'GET /queue/status':
                return $this->getQueueStatus();
            case 'GET /queue/stats':
                return $this->getQueueStats();
            // ... endpoint lainnya
        }
    }
    
    // Contoh Endpoint Methods
    private function pushToQueue(): array {
        try {
            $data = $this->validateRequestData();
            $result = $this->queueManager->push(
                $data['queue_name'],
                $data['payload']
            );
            
            return [
                'status' => 'success',
                'message' => 'Item berhasil ditambahkan ke queue',
                'data' => ['queue_id' => $result]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}

