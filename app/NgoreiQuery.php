<?php
namespace app;

use Exception;
use PDO;
use app\NgoreiDb;
use app\NgoreiException;
use Redis;

/**
 * Class untuk menangani query data dalam template Ngorei
 */
class NgoreiQuery {
    
    /**
     * Pattern regex untuk FlatList
     */
    private const FLATLIST_PATTERN = '/<FlatList\s+([^>]*?)data=(["\'])(.*?)\2\s+fields=(["\'])(.*?)\4\s+(?:join=(["\'])(.*?)\6\s+)?(?:where=(["\'])(.*?)\8\s+)?(?:limit=(["\'])(.*?)\10\s+)?(?:orderBy=(["\'])(.*?)\12\s+)?(?:groupBy=(["\'])(.*?)\14\s+)?keyExtractor=(["\'])(.*?)\16([^>]*?)>(.*?)<\/FlatList>/is';
    
    /**
     * Operator SQL yang diizinkan
     */
    private const ALLOWED_OPERATORS = ['=', '!=', '<', '>', '<=', '>=', 'LIKE', 'IN', 'NOT IN', 'IS NULL', 'IS NOT NULL'];
    
    /**
     * Kata kunci SQL yang diizinkan
     */
    private const ALLOWED_KEYWORDS = ['AND', 'OR', 'NOT'];

    /**
     * Tambahkan konstanta untuk caching
     */
    private const CACHE_ENABLED = true;
    private const CACHE_DURATION = 3600; // 1 jam dalam detik
    private const CACHE_DIR = 'cache/queries/';

    /**
     * Tambahkan property untuk menyimpan filter
     */
    private $filters = [];

    /**
     * Konstanta untuk API
     */
    private const API_PATTERN = '/<ApiData\s+([^>]*?)endpoint=(["\'])(.*?)\2\s+method=(["\'])(.*?)\4\s+(?:headers=(["\'])(.*?)\6\s+)?(?:params=(["\'])(.*?)\8\s+)?keyExtractor=(["\'])(.*?)\10([^>]*?)>(.*?)<\/ApiData>/is';

    /**
     * Pattern untuk SDK request
     */
    private const SDK_PATTERN = '/<FlatKey\s+([^>]*?)id=(["\'])(.*?)\2\s+keyExtractor=(["\'])(.*?)\4([^>]*?)>(.*?)<\/FlatKey>/is';

    /**
     * Pattern regex untuk JsonData
     */
    private const JSONDATA_PATTERN = '/<Module\s+([^>]*?)data=(["\'])(.*?)\2\s+fields=(["\'])(.*?)\4\s+(?:where=(["\'])(.*?)\6\s+)?(?:limit=(["\'])(.*?)\8\s+)?(?:import=(["\'])(.*?)\10\s+)?(?:orderBy=(["\'])(.*?)\12\s+)?(?:groupBy=(["\'])(.*?)\14\s+)?([^>]*?)\/>/is';

    protected $redis;
    protected $cachePrefix = 'ngorei:';
    protected $defaultTTL = 3600; // 1 jam
    protected $useRedis = false;

    public function __construct() {
        // Cek apakah ekstensi Redis tersedia
        if (extension_loaded('redis')) {
            try {
                $this->redis = new Redis();
                $this->redis->connect('127.0.0.1', 6379);
                $this->useRedis = true;
            } catch (\Exception $e) {
                error_log("Redis connection failed: " . $e->getMessage());
                $this->useRedis = false;
            }
        }
    }

    protected function getCacheKey($key) {
        return $this->cachePrefix . md5($key);
    }

    public function cache($key, $callback, $ttl = null) {
        if (!$this->useRedis) {
            // Fallback ke file cache jika Redis tidak tersedia
            return $this->fileCache($key, $callback, $ttl);
        }

        $cacheKey = $this->getCacheKey($key);
        $cached = $this->redis->get($cacheKey);

        if ($cached !== false) {
            return unserialize($cached);
        }

        $result = $callback();
        $this->redis->setex(
            $cacheKey, 
            $ttl ?? $this->defaultTTL,
            serialize($result)
        );

        return $result;
    }

    protected function fileCache($key, $callback, $ttl = null) {
        $cacheDir = APP . '/cache/queries';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cacheFile = $cacheDir . '/' . md5($key) . '.cache';
        
        // Cek cache file
        if (file_exists($cacheFile)) {
            $data = unserialize(file_get_contents($cacheFile));
            if ($data['expires'] > time()) {
                return $data['data'];
            }
        }

        // Generate data baru
        $result = $callback();
        
        // Simpan ke cache
        $cacheData = [
            'expires' => time() + ($ttl ?? $this->defaultTTL),
            'data' => $result
        ];
        
        file_put_contents($cacheFile, serialize($cacheData), LOCK_EX);
        
        return $result;
    }

    public function clearCache($pattern = '*') {
        if ($this->useRedis) {
            // Clear Redis cache
            $keys = $this->redis->keys($this->cachePrefix . $pattern);
            foreach ($keys as $key) {
                $this->redis->del($key);
            }
        } else {
            // Clear file cache
            $cacheDir = APP . '/cache/queries';
            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '/*.cache');
                foreach ($files as $file) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Memproses query dan menampilkan data dalam template
     * @param string &$content Konten yang akan diproses
     * @return void
     */
    public function processQueryData(string &$content): void 
    {
        // Log konten awal
        error_log("Processing query data, content length: " . strlen($content));
        
        $content = preg_replace_callback(self::FLATLIST_PATTERN, function($matches) {
            try {
                // Log matches yang ditemukan
                error_log("FlatList matches found: " . json_encode($matches));
                
                $params = $this->extractFlatListParams($matches);
                error_log("Extracted params: " . json_encode($params));
                
                $results = $this->executeQuery($params);
                error_log("Query results count: " . count($results));
                
                return $this->generateFlatListOutput($results, $params);
                
            } catch (Exception $e) {
                error_log("Error in processQueryData: " . $e->getMessage());
                return "<!-- Query Error: " . htmlspecialchars($e->getMessage()) . " -->";
            }
        }, $content);
        
        // Tambah proses untuk ApiData
        $content = preg_replace_callback(self::API_PATTERN, function($matches) {
            try {
                $params = [
                    'attributes' => $matches[1] . $matches[12],
                    'endpoint' => trim($matches[3]),
                    'method' => trim($matches[5]),
                    'headers' => isset($matches[7]) ? trim($matches[7]) : null,
                    'params' => isset($matches[9]) ? trim($matches[9]) : null,
                    'keyPrefix' => $matches[11],
                    'template' => $matches[13]
                ];

                $results = $this->executeApiRequest($params);
                return $this->generateFlatListOutput($results, $params);

            } catch (Exception $e) {
                error_log("Error in API data processing: " . $e->getMessage());
                return "<!-- API Error: " . htmlspecialchars($e->getMessage()) . " -->";
            }
        }, $content);
        
        // Tambah proses untuk FlatKey
        $content = preg_replace_callback(self::SDK_PATTERN, function($matches) {
            try {
                $params = [
                    'attributes' => $matches[1] . $matches[6],
                    'sdkId' => trim($matches[3]),
                    'keyPrefix' => $matches[5],
                    'template' => $matches[7]
                ];

                $results = $this->executeFlatKey($params);
                return $this->generateFlatListOutput($results, $params);

            } catch (Exception $e) {
                error_log("Error in SDK request processing: " . $e->getMessage());
                return "<!-- SDK Error: " . htmlspecialchars($e->getMessage()) . " -->";
            }
        }, $content);
        
        // Tambah proses untuk JsonData
        $content = preg_replace_callback(self::JSONDATA_PATTERN, function($matches) {
            try {
                $params = [
                    'attributes' => $matches[1] . $matches[15],
                    'tableName' => trim($matches[3]),
                    'fields' => array_map('trim', explode(',', $matches[5])),
                    'where' => isset($matches[7]) ? $matches[7] : null,
                    'limit' => isset($matches[9]) ? (int)$matches[9] : null,
                    'import' => isset($matches[11]) ? trim($matches[11]) : null,
                    'orderBy' => isset($matches[13]) ? $matches[13] : null,
                    'groupBy' => isset($matches[15]) ? $matches[15] : null
                ];

                $results = $this->executeQuery($params);
                
                // Handle import jika ada
                if (!empty($params['import'])) {
                    return $this->generateJsonWithScript($results, $params['import']);
                }
                
                return $this->generateJsonOutput($results);

            } catch (Exception $e) {
                error_log("Error in JsonData processing: " . $e->getMessage());
                return "<!-- JSON Error: " . htmlspecialchars($e->getMessage()) . " -->";
            }
        }, $content);
        
        // Log hasil akhir
        error_log("Final content length after processing: " . strlen($content));
    }

    /**
     * Mengekstrak parameter dari matches regex FlatList
     * @param array $matches Hasil regex match
     * @return array Parameter yang diekstrak
     */
    private function extractFlatListParams(array $matches): array 
    {
        return [
            'attributes' => $matches[1] . $matches[16],
            'tableName' => trim($matches[3]),
            'fields' => array_map('trim', explode(',', $matches[5])),
            'join' => isset($matches[7]) ? $this->parseJoinClause($matches[7]) : [],
            'where' => isset($matches[9]) ? $matches[9] : null,
            'limit' => isset($matches[11]) ? (int)$matches[11] : null,
            'orderBy' => isset($matches[13]) ? $matches[13] : null,
            'groupBy' => isset($matches[15]) ? $matches[15] : null,
            'keyPrefix' => $matches[17],
            'template' => $matches[19]
        ];
    }

    /**
     * Parse join clause menjadi array terstruktur
     * @param string $joinStr String join clause
     * @return array Array join clause yang terstruktur
     */
    private function parseJoinClause(string $joinStr): array 
    {
        $joins = [];
        $joinStatements = array_map('trim', explode(';', $joinStr));
        
        foreach ($joinStatements as $statement) {
            if (empty($statement)) continue;
            
            // Format: type:table:condition
            // Contoh: "LEFT:users:users.id=posts.user_id"
            $parts = array_map('trim', explode(':', $statement));
            
            if (count($parts) === 3) {
                $joins[] = [
                    'type' => strtoupper($parts[0]), // LEFT, RIGHT, INNER
                    'table' => $parts[1],
                    'condition' => $parts[2]
                ];
            }
        }
        
        return $joins;
    }

    /**
     * Mengeksekusi query NgoreiDb dengan caching
     * @param array $params Parameter query
     * @return array Hasil query
     * @throws NgoreiException Jika terjadi error
     */
    private function executeQuery(array $params): array 
    {
        try {
            // Log parameter query
            error_log("Executing query with params: " . json_encode($params));
            
            // Generate cache key berdasarkan parameter
            $cacheKey = $this->generateCacheKey($params);
            
            // Cek cache jika enabled
            if (self::CACHE_ENABLED) {
                $cachedData = $this->getCache($cacheKey);
                if ($cachedData !== null) {
                    error_log("Returning cached data");
                    return $cachedData;
                }
            }

            // Eksekusi query jika tidak ada cache
            $query = $this->buildQuery($params);
            // Log query yang dibangun
            error_log("Built SQL query: " . $query);
            
            $db = new NgoreiDb();
            $pdo = $db->connPDO();
            
            if (!$pdo) {
                error_log("NgoreiDb connection failed");
                throw new NgoreiException("Koneksi NgoreiDb gagal");
            }
            
            $stmt = $pdo->prepare($query);
            if (!$stmt) {
                error_log("Query preparation failed: " . implode(" ", $pdo->errorInfo()));
                throw new NgoreiException("Query error: " . implode(" ", $pdo->errorInfo()));
            }
            
            // Log parameter binding jika ada
            if (!empty($params['bindings'])) {
                error_log("Binding parameters: " . json_encode($params['bindings']));
                foreach ($params['bindings'] as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
            }
            
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Log jumlah hasil
            error_log("Query returned " . count($results) . " results");
            
            // Simpan hasil ke cache
            if (self::CACHE_ENABLED) {
                $this->setCache($cacheKey, $results);
            }

            return $results;
            
        } catch (Exception $e) {
            error_log("Query execution error: " . $e->getMessage());
            throw new NgoreiException("Error executing query: " . $e->getMessage());
        }
    }

    /**
     * Generate cache key berdasarkan parameter query
     * @param array $params Parameter query
     * @return string Cache key
     */
    private function generateCacheKey(array $params): string 
    {
        $keyParts = [
            'table' => $params['tableName'],
            'fields' => implode(',', $params['fields']),
            'where' => $params['where'],
            'limit' => $params['limit'],
            'orderBy' => $params['orderBy'] ?? null,
            'groupBy' => $params['groupBy'] ?? null
        ];
        
        return md5(serialize($keyParts));
    }

    /**
     * Mengambil data dari cache
     * @param string $key Cache key
     * @return array|null Data dari cache atau null jika tidak ada
     */
    private function getCache(string $key): ?array 
    {
        $cacheFile = $this->getCacheFilePath($key);
        
        if (file_exists($cacheFile)) {
            $cacheData = unserialize(file_get_contents($cacheFile));
            
            // Cek apakah cache masih valid
            if ($cacheData['expires'] > time()) {
                return $cacheData['data'];
            }
            
            // Hapus cache yang expired
            unlink($cacheFile);
        }
        
        return null;
    }

    /**
     * Menyimpan data ke cache
     * @param string $key Cache key
     * @param array $data Data yang akan dicache
     * @return bool Berhasil atau tidak
     */
    private function setCache(string $key, array $data): bool 
    {
        try {
            $cacheDir = $this->ensureCacheDirectory();
            $cacheFile = $this->getCacheFilePath($key);
            
            $cacheData = [
                'expires' => time() + self::CACHE_DURATION,
                'data' => $data
            ];
            
            return file_put_contents($cacheFile, serialize($cacheData)) !== false;
        } catch (Exception $e) {
            error_log("Error setting cache: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mendapatkan path file cache
     * @param string $key Cache key
     * @return string Path file cache
     */
    private function getCacheFilePath(string $key): string 
    {
        return $this->ensureCacheDirectory() . $key . '.cache';
    }

    /**
     * Memastikan direktori cache tersedia
     * @return string Path direktori cache
     */
    private function ensureCacheDirectory(): string 
    {
        $cacheDir = dirname(__DIR__) . '/' . self::CACHE_DIR;
        
        if (!is_dir($cacheDir)) {
            if (!mkdir($cacheDir, 0777, true)) {
                throw new NgoreiException("Gagal membuat direktori cache");
            }
            chmod($cacheDir, 0777);
        }
        
        return $cacheDir;
    }

    /**
     * Membangun query SQL
     * @param array $params Parameter untuk membangun query
     * @return string Query SQL
     */
    private function buildQuery(array $params): string 
    {
        $selectedFields = implode(',', $params['fields']);
        $query = "SELECT {$selectedFields} FROM {$params['tableName']}";
        
        // Tambahkan JOIN jika ada
        if (!empty($params['join'])) {
            foreach ($params['join'] as $join) {
                $joinType = $join['type'];
                $joinTable = $join['table'];
                $joinCondition = $join['condition'];
                
                // Validasi join type
                $allowedJoinTypes = ['LEFT', 'RIGHT', 'INNER', 'OUTER'];
                if (!in_array($joinType, $allowedJoinTypes)) {
                    $joinType = 'LEFT';
                }
                
                $query .= " {$joinType} JOIN {$joinTable} ON {$joinCondition}";
            }
        }
        
        if ($params['where'] !== null && !empty(trim($params['where']))) {
            $where = $this->sanitizeWhereClause($params['where']);
            if (!empty($where)) {
                $query .= " WHERE {$where}";
            }
        }
        
        // Tambahkan GROUP BY jika ada
        if ($params['groupBy'] !== null && !empty(trim($params['groupBy']))) {
            $groupBy = $this->sanitizeGroupByClause($params['groupBy']);
            if (!empty($groupBy)) {
                $query .= " GROUP BY {$groupBy}";
            }
        }
        
        // Tambahkan ORDER BY jika ada
        if ($params['orderBy'] !== null && !empty(trim($params['orderBy']))) {
            $orderBy = $this->sanitizeOrderByClause($params['orderBy']);
            if (!empty($orderBy)) {
                $query .= " ORDER BY {$orderBy}";
            }
        }
        
        if ($params['limit'] !== null && $params['limit'] > 0) {
            $query .= " LIMIT {$params['limit']}";
        }
        
        return $query;
    }

    /**
     * Generate output HTML dari hasil query
     * @param array $results Hasil query NgoreiDb
     * @param array $params Parameter FlatList
     * @return string Output HTML
     */
    private function generateFlatListOutput(array $results, array $params): string 
    {
        // Log parameter dan hasil
        error_log("Generating output with " . count($results) . " results");
        error_log("FlatList params: " . json_encode($params));
        
        if (empty($results)) {
            error_log("No results found");
            return "<!-- No data found -->";
        }
        
        // Pastikan results adalah array numerik
        if (!isset($results[0])) {
            // Jika results adalah array asosiatif, konversi ke array numerik
            $results = [$results];
        }
        
        $output = '';
        $index = 0;
        $rowNumber = 1;
        
        foreach ($results as $row) {
            try {
                $itemTemplate = $params['template'];
                
                // Tambahkan nomor baris otomatis
                $itemTemplate = str_replace('{row.key}', $rowNumber, $itemTemplate);
                
                // Ganti placeholder berdasarkan keyPrefix
                $keyPrefix = $params['keyPrefix'];
                foreach ($row as $field => $value) {
                    // Cek apakah ada filter
                    $pattern = '/\{' . preg_quote($keyPrefix . '.' . $field, '/') . '(?:\|([^}]+))?\}/';
                    
                    preg_match_all($pattern, $itemTemplate, $matches, PREG_SET_ORDER);
                    
                    foreach ($matches as $match) {
                        $placeholder = $match[0];
                        $value = isset($row[$field]) ? $row[$field] : '';
                        
                        // Jika ada filter, terapkan
                        if (isset($match[1])) {
                            $value = $this->processFilters($value, $match[1]);
                        }
                        
                        $itemTemplate = str_replace($placeholder, $value, $itemTemplate);
                    }
                }
                
                // Tambahkan index jika diperlukan
                $itemTemplate = str_replace('{' . $keyPrefix . '.index}', $index, $itemTemplate);
                
                $output .= $itemTemplate;
                $index++;
                $rowNumber++;
            } catch (Exception $e) {
                error_log("Error processing row: " . $e->getMessage());
                continue;
            }
        }
        
        $otherAttrs = $this->getCleanAttributes($params['attributes']);
        if (!empty($otherAttrs)) {
            $output = "<div {$otherAttrs}>{$output}</div>";
        }
        
        return $output;
    }

    /**
     * Memproses template untuk satu baris data
     * @param array $row Data baris
     * @param array $params Parameter FlatList
     * @param int $index Index baris
     * @param int $rowNumber Nomor baris
     * @return string Template yang sudah diproses
     */
    private function processRowTemplate(array $row, array $params, int $index, int $rowNumber): string 
    {
        $itemTemplate = $params['template'];
        
        // Tambahkan nomor baris otomatis
        $itemTemplate = str_replace('{row.key}', $rowNumber, $itemTemplate);
        
        // Ganti placeholder untuk setiap field
        foreach ($params['fields'] as $field) {
            // Cek apakah ada filter dengan pattern {field|filter1|filter2(arg1,arg2)}
            $pattern = '/\{' . preg_quote($params['keyPrefix'] . '.' . $field, '/') . '(?:\|([^}]+))?\}/';
            
            preg_match_all($pattern, $itemTemplate, $matches, PREG_SET_ORDER);
            
            foreach ($matches as $match) {
                $placeholder = $match[0];
                $value = isset($row[$field]) ? $row[$field] : '';
                
                // Jika ada filter, terapkan
                if (isset($match[1])) {
                    $value = $this->processFilters($value, $match[1]);
                }
                
                $itemTemplate = str_replace($placeholder, $value, $itemTemplate);
            }
        }
        
        // Tambahkan index jika diperlukan
        $itemTemplate = str_replace('{' . $params['keyPrefix'] . '.index}', $index, $itemTemplate);
        
        return $itemTemplate;
    }

    /**
     * Membersihkan dan mendapatkan atribut yang valid
     * @param string $attributes String atribut HTML
     * @return string Atribut yang sudah dibersihkan
     */
    private function getCleanAttributes(string $attributes): string 
    {
        return trim(preg_replace([
            '/\s*data=(["\']).*?\1/',
            '/\s*fields=(["\']).*?\1/',
            '/\s*where=(["\']).*?\1/',
            '/\s*limit=(["\']).*?\1/',
            '/\s*orderBy=(["\']).*?\1/',
            '/\s*groupBy=(["\']).*?\1/',
            '/\s*keyExtractor=(["\']).*?\1/'
        ], '', $attributes));
    }

    /**
     * Sanitasi WHERE clause untuk mencegah SQL injection
     * @param string $where WHERE clause yang akan disanitasi
     * @return string WHERE clause yang aman
     */
    private function sanitizeWhereClause(string $where): string 
    {
        // Hapus multiple spaces
        $where = preg_replace('/\s+/', ' ', trim($where));
        
        // Split where clause berdasarkan AND/OR
        $conditions = preg_split('/(AND|OR)/i', $where, -1, PREG_SPLIT_DELIM_CAPTURE);
        $sanitizedConditions = [];
        
        foreach ($conditions as $condition) {
            $condition = trim($condition);
            
            // Skip jika kondisi kosong
            if (empty($condition)) continue;
            
            // Jika ini adalah operator AND/OR, tambahkan langsung
            if (in_array(strtoupper($condition), self::ALLOWED_KEYWORDS)) {
                $sanitizedConditions[] = $condition;
                continue;
            }
            
            // Cek apakah kondisi menggunakan operator yang diizinkan
            if ($this->isValidCondition($condition)) {
                $sanitizedConditions[] = $condition;
            }
        }
        
        // Gabungkan kembali kondisi yang valid
        return implode(' ', $sanitizedConditions);
    }

    /**
     * Cek apakah kondisi WHERE valid
     * @param string $condition Kondisi yang akan dicek
     * @return bool True jika valid
     */
    private function isValidCondition(string $condition): bool 
    {
        foreach (self::ALLOWED_OPERATORS as $operator) {
            if (stripos($condition, $operator) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Sanitasi ORDER BY clause untuk mencegah SQL injection
     * @param string $orderBy ORDER BY clause yang akan disanitasi
     * @return string ORDER BY clause yang aman
     */
    private function sanitizeOrderByClause(string $orderBy): string 
    {
        $orderBy = trim($orderBy);
        $parts = array_map('trim', explode(',', $orderBy));
        $validParts = [];
        
        foreach ($parts as $part) {
            // Pisahkan field dan direction
            $orderParts = array_map('trim', explode(' ', $part));
            $field = $orderParts[0];
            
            // Validasi field name (hanya alphanumeric dan underscore)
            if (preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                if (isset($orderParts[1])) {
                    $direction = strtoupper($orderParts[1]);
                    if (in_array($direction, ['ASC', 'DESC'])) {
                        $validParts[] = $field . ' ' . $direction;
                    } else {
                        $validParts[] = $field;
                    }
                } else {
                    $validParts[] = $field;
                }
            }
        }
        
        return implode(', ', $validParts);
    }

    /**
     * Sanitasi GROUP BY clause untuk mencegah SQL injection
     * @param string $groupBy GROUP BY clause yang akan disanitasi
     * @return string GROUP BY clause yang aman
     */
    private function sanitizeGroupByClause(string $groupBy): string 
    {
        $groupBy = trim($groupBy);
        $fields = array_map('trim', explode(',', $groupBy));
        $validFields = [];
        
        foreach ($fields as $field) {
            // Validasi field name (hanya alphanumeric dan underscore)
            if (preg_match('/^[a-zA-Z0-9_]+$/', $field)) {
                $validFields[] = $field;
            }
        }
        
        return implode(', ', $validFields);
    }

    /**
     * Mendaftarkan filter baru
     * @param string $name Nama filter
     * @param callable $callback Function filter
     */
    public function addFilter(string $name, callable $callback): void 
    {
        $this->filters[$name] = $callback;
    }

    /**
     * Menerapkan filter pada nilai
     * @param mixed $value Nilai yang akan difilter
     * @param string $filter Nama filter
     * @param array $arguments Argument tambahan
     * @return mixed Nilai hasil filter
     */
    private function applyFilter($value, string $filter, array $arguments = []) 
    {
        // Cek apakah filter ada di custom filters
        if (isset($this->filters[$filter])) {
            return call_user_func_array($this->filters[$filter], [$value, ...$arguments]);
        }
        
        // Filter bawaan
        switch($filter) {
            case 'number_format':
                // Format angka dengan pemisah ribuan dan desimal
                if (is_numeric($value)) {
                    $decimals = $arguments[0] ?? 0;
                    $decPoint = $arguments[1] ?? ',';
                    $thousandsSep = $arguments[2] ?? '.';
                    return number_format((float)$value, $decimals, $decPoint, $thousandsSep);
                }
                return $value;
                
            case 'upper':
                // Konversi ke huruf besar
                return strtoupper($value);
                
            case 'lower':
                // Konversi ke huruf kecil
                return strtolower($value);
                
            case 'date':
                // Format tanggal dengan format custom
                // Penggunaan: {tanggal|date(Y-m-d)}
                $format = $arguments[0] ?? 'Y-m-d H:i:s';
                return date($format, strtotime($value));
                
            case 'currency':
                // Format mata uang
                // Penggunaan: {harga|currency(IDR)}
                if (!is_numeric($value)) return $value;
                $currency = $arguments[0] ?? 'IDR';
                $decimals = $arguments[1] ?? 0;
                return $currency . ' ' . number_format((float)$value, $decimals, ',', '.');
                
            case 'nl2br':
                // Konversi newline ke <br>
                return nl2br($value);
                
            case 'limit_words':
                // Batasi jumlah kata
                // Penggunaan: {content|limit_words(20,...)}
                $limit = (int)($arguments[0] ?? 20);
                $end = $arguments[1] ?? '...';
                $words = str_word_count($value, 2);
                if (count($words) > $limit) {
                    return implode(' ', array_slice($words, 0, $limit)) . $end;
                }
                return $value;
                
            case 'strip_tags':
                // Hapus HTML tags
                return strip_tags($value);
                
            case 'escape':
                // Escape HTML entities
                return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                
            case 'trim':
                // Trim whitespace
                return trim($value);
                
            case 'md5':
                // Generate MD5 hash
                return md5($value);
                
            case 'json':
                // Encode/decode JSON
                return is_string($value) ? json_decode($value, true) : json_encode($value);
                
            case 'ucfirst':
                // Kapital huruf pertama
                return ucfirst(strtolower($value));
                
            case 'ucwords':
                // Kapital setiap kata
                return ucwords(strtolower($value));
                
            case 'slug':
                // Generate URL slug
                $text = strtolower($value);
                $text = preg_replace('/[^a-z0-9\-]/', '-', $text);
                return trim(preg_replace('/-+/', '-', $text), '-');
                
            case 'phone':
                // Format nomor telepon
                $number = preg_replace('/[^0-9]/', '', $value);
                if (strlen($number) > 10) {
                    return substr($number, 0, 4) . '-' . substr($number, 4, 4) . '-' . substr($number, 8);
                }
                return $value;
                
            case 'truncate':
                // Potong teks dengan panjang tertentu
                // Penggunaan: {text|truncate(100,...)}
                $length = (int)($arguments[0] ?? 100);
                $end = $arguments[1] ?? '...';
                if (mb_strlen($value) > $length) {
                    return rtrim(mb_substr($value, 0, $length)) . $end;
                }
                return $value;
                
            case 'time_ago':
                // Konversi timestamp ke format "time ago"
                $timestamp = strtotime($value);
                $now = time();
                $diff = $now - $timestamp;
                
                $intervals = [
                    31536000 => 'tahun',
                    2592000 => 'bulan',
                    604800 => 'minggu',
                    86400 => 'hari',
                    3600 => 'jam',
                    60 => 'menit',
                    1 => 'detik'
                ];
                
                foreach ($intervals as $secs => $str) {
                    $d = $diff / $secs;
                    if ($d >= 1) {
                        $r = round($d);
                        return $r . ' ' . $str . ' yang lalu';
                    }
                }
                return 'baru saja';
                
            default:
                return $value;
        }
    }

    /**
     * Memproses filter dalam query data
     * @param mixed $value Nilai yang akan diproses
     * @param string $filterStr String filter dengan format "filter1|filter2(arg1,arg2)|filter3"
     * @return mixed Nilai yang telah diproses
     */
    public function processFilters($value, string $filterStr)
    {
        // Split filter berdasarkan pipe
        $filters = explode('|', $filterStr);
        
        // Terapkan setiap filter secara berurutan
        foreach ($filters as $filter) {
            // Parse filter dan argumennya
            if (preg_match('/^([a-z_]+)(?:\((.*?)\))?$/i', trim($filter), $matches)) {
                $filterName = $matches[1];
                $arguments = isset($matches[2]) ? 
                    array_map('trim', explode(',', $matches[2])) : 
                    [];
                    
                // Terapkan filter
                $value = $this->applyFilter($value, $filterName, $arguments);
            }
        }
        
        return $value;
    }

    /**
     * Memproses request API
     * @param array $params Parameter API request
     * @return array Response data
     */
    private function executeApiRequest(array $params): array 
    {
        try {
            $endpoint = $params['endpoint'];
            $method = strtoupper($params['method']);
            $headers = isset($params['headers']) ? json_decode($params['headers'], true) : [];
            $requestParams = isset($params['params']) ? json_decode($params['params'], true) : [];

            // Buat cache key untuk request API
            $cacheKey = $this->generateApiCacheKey($params);
            
            // Cek cache jika enabled
            if (self::CACHE_ENABLED) {
                $cachedData = $this->getCache($cacheKey);
                if ($cachedData !== null) {
                    return $cachedData;
                }
            }

            // Setup cURL request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            
            // Set headers
            if (!empty($headers)) {
                $headerArray = [];
                foreach ($headers as $key => $value) {
                    $headerArray[] = "$key: $value";
                }
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headerArray);
            }

            // Set params untuk POST/PUT
            if (in_array($method, ['POST', 'PUT']) && !empty($requestParams)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestParams));
            }

            // Execute request
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 400) {
                throw new NgoreiException("API request failed with code: $httpCode");
            }

            $data = json_decode($response, true);
            
            // Cache response jika enabled
            if (self::CACHE_ENABLED) {
                $this->setCache($cacheKey, $data);
            }

            return $data;

        } catch (Exception $e) {
            error_log("API request error: " . $e->getMessage());
            throw new NgoreiException("Error executing API request: " . $e->getMessage());
        }
    }

    /**
     * Generate cache key untuk request API
     */
    private function generateApiCacheKey(array $params): string 
    {
        $keyParts = [
            'endpoint' => $params['endpoint'],
            'method' => $params['method'],
            'headers' => $params['headers'] ?? '',
            'params' => $params['params'] ?? ''
        ];
        
        return md5('api_' . serialize($keyParts));
    }

    /**
     * Eksekusi request SDK berdasarkan ID
     */
    private function executeFlatKey(array $params): array 
    {
        try {
            // Validasi parameter wajib
            if (empty($params['sdkId'])) {
                throw new NgoreiException("SDK ID diperlukan");
            }

            // Generate cache key
            $cacheKey = $this->generateFlatKeyCacheKey($params);
            
            // Cek cache jika enabled
            if (self::CACHE_ENABLED) {
                $cachedData = $this->getCache($cacheKey);
                if ($cachedData !== null) {
                    error_log("Mengembalikan data FlatKey dari cache untuk ID: " . $params['sdkId']);
                    return $cachedData;
                }
            }

            // Baca konfigurasi package.json
            $packageFile = dirname(__DIR__) . '/package/package.json';
            if (!file_exists($packageFile)) {
                throw new NgoreiException("Konfigurasi package tidak ditemukan");
            }

            $packageConfig = json_decode(file_get_contents($packageFile), true);
            
            // Cek ID dan dapatkan path file
            if (!isset($packageConfig['sdk'][$params['sdkId']])) {
                throw new NgoreiException("ID SDK tidak valid");
            }

            $path = $packageConfig['sdk'][$params['sdkId']];
            if (strpos($path, '..') !== false) {
                throw new NgoreiException("Path SDK tidak valid");
            }

            $filePath = dirname(__DIR__) . '/package/' . str_replace('/', DIRECTORY_SEPARATOR, $path) . '.php';

            if (!file_exists($filePath)) {
                throw new NgoreiException("File tidak ditemukan: " . $path);
            }

            // Load file dan return datanya
            $result = require $filePath;

            // Set cache jika enabled
            if (self::CACHE_ENABLED) {
                error_log("Menyimpan data FlatKey ke cache untuk ID: " . $params['sdkId']);
                $this->setCache($cacheKey, $result);
            }

            return $result;

        } catch (NgoreiException $e) {
            error_log("[FlatKey Error] " . $e->getMessage());
            throw $e;
        } catch (Exception $e) {
            error_log("[FlatKey Error] Error tidak terduga: " . $e->getMessage());
            throw new NgoreiException("Terjadi kesalahan internal SDK");
        }
    }

    /**
     * Generate cache key untuk FlatKey
     * @param array $params Parameter FlatKey
     * @return string Cache key
     */
    private function generateFlatKeyCacheKey(array $params): string 
    {
        $keyParts = [
            'type' => 'flatkey',
            'sdkId' => $params['sdkId'],
            'timestamp' => filemtime(dirname(__DIR__) . '/package/package.json')
        ];
        
        // Tambahkan path file ke cache key jika tersedia
        $packageFile = dirname(__DIR__) . '/package/package.json';
        if (file_exists($packageFile)) {
            $packageConfig = json_decode(file_get_contents($packageFile), true);
            if (isset($packageConfig['sdk'][$params['sdkId']])) {
                $filePath = dirname(__DIR__) . '/package/' . 
                           str_replace('/', DIRECTORY_SEPARATOR, $packageConfig['sdk'][$params['sdkId']]) . 
                           '.php';
                if (file_exists($filePath)) {
                    $keyParts['file_timestamp'] = filemtime($filePath);
                }
            }
        }
        
        return md5(serialize($keyParts));
    }

    /**
     * Generate output JSON dari hasil query
     * @param array $results Hasil query NgoreiDb
     * @return string Output JSON
     */
    private function generateJsonOutput(array $results): string 
    {
        try {
            // Konversi hasil ke format JSON dengan pretty print
            return json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Exception $e) {
            error_log("Error generating JSON output: " . $e->getMessage());
            return "[]";
        }
    }

    /**
     * Method untuk menyimpan data JSON ke file JavaScript
     */
    private function saveJsonToJs(array $results, string $jsPath): void 
    {
        try {
            // Sanitasi path file
            $jsPath = trim($jsPath);
            if (strpos($jsPath, '..') !== false) {
                throw new NgoreiException("Invalid JS file path");
            }

            // Konversi hasil ke JSON
            $jsonData = json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            
            // Generate script untuk menyimpan data
            $script = "const jsonData = " . $jsonData . ";\n";
            
            // Path lengkap ke file JS
            $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . $jsPath;
            
            // Baca konten JS yang ada
            $existingContent = '';
            if (file_exists($fullPath)) {
                $existingContent = file_get_contents($fullPath);
            }
            
            // Gabungkan script dengan konten yang ada
            $newContent = $script . "\n" . $existingContent;
            
            // Tulis kembali ke file
            if (file_put_contents($fullPath, $newContent) === false) {
                throw new NgoreiException("Gagal menulis ke file JS");
            }
            
        } catch (Exception $e) {
            error_log("Error saving JSON to JS: " . $e->getMessage());
            throw new NgoreiException("Gagal menyimpan data ke file JS: " . $e->getMessage());
        }
    }

    /**
     * Generate output JSON dengan script tag
     */
    private function generateJsonWithScript(array $results, string $jsPath): string 
    {
        try {
            // Konversi hasil ke JSON dengan penanganan karakter khusus
            $jsonData = json_encode($results, 
                JSON_HEX_TAG |     // Mengkonversi tag HTML
                JSON_HEX_APOS |    // Mengkonversi single quotes
                JSON_HEX_QUOT |    // Mengkonversi double quotes
                JSON_HEX_AMP |     // Mengkonversi ampersands
                JSON_UNESCAPED_UNICODE | // Biarkan unicode apa adanya
                JSON_UNESCAPED_SLASHES   // Biarkan forward slashes
            );

            // Validasi JSON
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new NgoreiException('JSON encoding error: ' . json_last_error_msg());
            }
            
            // Enkripsi data JSON menggunakan base64
            $encodedData = base64_encode($jsonData);
            
            // Normalisasi path untuk file JavaScript
            $jsPath = trim($jsPath, '/');
            
            // Tambahkan data-key attribute yang berisi data JSON terenkripsi
            $output = sprintf(
                "<script type='module' src='%s/%s' data-key='%s'></script>",
                HOST,
                htmlspecialchars($jsPath),
                htmlspecialchars($encodedData, ENT_QUOTES, 'UTF-8')
            );
            
            return $output;
            
        } catch (Exception $e) {
            error_log("Error generating JSON with script: " . $e->getMessage());
            return "<!-- Error: " . htmlspecialchars($e->getMessage()) . " -->";
        }
    }
} 