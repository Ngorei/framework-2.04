<?php
namespace app;
use app\NgoreiDb;

class NgoreiBuilder {
    private \mysqli $mysqli;
    private string $table;
    private array $selects = ['*'];
    private array $wheres = [];
    private array $bindings = [];
    private string $orderBy = '';
    private string $orderDirection = 'ASC';
    private ?int $limit = null;
    private ?int $offset = null;
    private $joins = [];
    private $groupBy = [];
    private $having = [];
    private array $sets = [];
    private array $files = [];
    private string $uploadsPath = 'uploads';
    private int $maxFileSize = 5242880;
    private array $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    private array $imageTypes = ['image/jpeg', 'image/png', 'image/gif'];
    private array $documentTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    private array $imageSizes = [
        '100x100' => ['width' => 100, 'height' => 100],
        '250x250' => ['width' => 250, 'height' => 250], 
        '600x600' => ['width' => 600, 'height' => 600]
    ];
    private string $baseServerPath;
    private ?int $lastInsertId = null;
    private static array $queryCache = [];
    private static array $preparedStatements = [];
    private int $cacheExpiry = 300; // 5 menit
    private bool $useCache = true;
    private int $batchSize = 1000;

    public function __construct(string $table) {
        $db = new NgoreiDb();
        $this->mysqli = $db->connMysqli();
        $this->table = $table;
        $this->baseServerPath = rtrim(dirname(dirname(__DIR__)), '/');
        
        // Mengaktifkan persistent connections
        $this->mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
        
        // Mengoptimalkan buffer
        $this->mysqli->set_opt(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
        
        $uploadsDir = $this->baseServerPath . '/' . $this->uploadsPath;
        if (!file_exists($uploadsDir)) {
            $this->createDirectory($uploadsDir);
        }
    }

    /**
     * Mengatur penggunaan cache
     */
    public function useCache(bool $use = true): self {
        $this->useCache = $use;
        return $this;
    }

    /**
     * Mengatur waktu kedaluwarsa cache
     */
    public function setCacheExpiry(int $seconds): self {
        $this->cacheExpiry = $seconds;
        return $this;
    }

    /**
     * Generate cache key berdasarkan query
     */
    private function generateCacheKey(): string {
        return md5(serialize([
            $this->table,
            $this->selects,
            $this->wheres,
            $this->bindings,
            $this->orderBy,
            $this->orderDirection,
            $this->limit,
            $this->offset,
            $this->joins,
            $this->groupBy,
            $this->having
        ]));
    }

    /**
     * Mengambil data dari cache
     */
    private function getFromCache(string $key): ?array {
        if (isset(self::$queryCache[$key])) {
            $cached = self::$queryCache[$key];
            if ($cached['expires'] > time()) {
                return $cached['data'];
            }
            unset(self::$queryCache[$key]);
        }
        return null;
    }

    /**
     * Menyimpan data ke cache
     */
    private function setCache(string $key, array $data): void {
        self::$queryCache[$key] = [
            'data' => $data,
            'expires' => time() + $this->cacheExpiry
        ];
    }

    /**
     * Optimasi execute dengan prepared statement caching
     */
    public function execute(bool $orderByIdDesc = false): array {
        try {
            if ($orderByIdDesc) {
                $this->orderBy = 'id';
                $this->orderDirection = 'DESC';
            }

            // Cek cache jika diaktifkan
            if ($this->useCache) {
                $cacheKey = $this->generateCacheKey();
                $cachedResult = $this->getFromCache($cacheKey);
                if ($cachedResult !== null) {
                    return $cachedResult;
                }
            }

            $query = $this->getQuery();
            $stmtKey = md5($query);

            // Gunakan prepared statement yang sudah ada jika tersedia
            if (!isset(self::$preparedStatements[$stmtKey])) {
                self::$preparedStatements[$stmtKey] = $this->mysqli->prepare($query);
            }

            $stmt = self::$preparedStatements[$stmtKey];
            
            if (!empty($this->bindings)) {
                $types = '';
                $params = [];
                foreach ($this->bindings as $binding) {
                    if (is_int($binding)) {
                        $types .= 'i';
                        $params[] = $binding;
                    } elseif (is_float($binding)) {
                        $types .= 'd';
                        $params[] = $binding;
                    } elseif (is_string($binding)) {
                        $types .= 's';
                        $params[] = $binding;
                    } else {
                        $types .= 'b';
                        $params[] = $binding;
                    }
                }
                
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute();
            $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

            // Simpan ke cache jika diaktifkan
            if ($this->useCache) {
                $this->setCache($cacheKey, $result);
            }

            return $result;

        } catch (\Exception $e) {
            error_log('Error pada execute: ' . $e->getMessage());
            throw new \RuntimeException('Gagal mengeksekusi query: ' . $e->getMessage());
        }
    }

    /**
     * Optimasi insertBatch dengan batching
     */
    public function insertBatch(array $rows): int {
        if (empty($rows)) {
            return 0;
        }

        try {
            $totalInserted = 0;
            $chunks = array_chunk($rows, $this->batchSize);

            $this->mysqli->begin_transaction();

            foreach ($chunks as $chunk) {
                $columns = array_keys($chunk[0]);
                $placeholders = '(' . str_repeat('?,', count($columns) - 1) . '?)';
                $allPlaceholders = str_repeat($placeholders . ',', count($chunk) - 1) . $placeholders;
                
                $query = "INSERT INTO `{$this->table}` 
                         (`" . implode('`,`', $columns) . "`) 
                         VALUES " . $allPlaceholders;
                
                $stmt = $this->mysqli->prepare($query);
                
                $values = [];
                $types = '';
                foreach ($chunk as $row) {
                    foreach ($row as $value) {
                        $values[] = $value;
                        if (is_int($value)) $types .= 'i';
                        elseif (is_float($value)) $types .= 'd';
                        elseif (is_string($value)) $types .= 's';
                        else $types .= 'b';
                    }
                }
                
                $stmt->bind_param($types, ...$values);
                $stmt->execute();
                $totalInserted += $stmt->affected_rows;
            }

            $this->mysqli->commit();
            return $totalInserted;

        } catch (\Exception $e) {
            $this->mysqli->rollback();
            error_log('Error pada insertBatch: ' . $e->getMessage());
            throw new \RuntimeException('Gagal batch insert: ' . $e->getMessage());
        }
    }

    /**
     * Optimasi update dengan prepared statement caching
     */
    public function update(array $data): int {
        try {
            $sets = [];
            $values = [];
            
            foreach ($data as $column => $value) {
                $sets[] = "`{$column}` = ?";
                $values[] = $value;
            }
            
            $values = array_merge($values, $this->bindings);
            
            $query = "UPDATE `{$this->table}` SET " . implode(', ', $sets);
            if (!empty($this->wheres)) {
                $query .= " WHERE " . implode(' AND ', $this->wheres);
            }
            
            $stmtKey = md5($query);
            
            if (!isset(self::$preparedStatements[$stmtKey])) {
                self::$preparedStatements[$stmtKey] = $this->mysqli->prepare($query);
            }
            
            $stmt = self::$preparedStatements[$stmtKey];
            
            $types = '';
            foreach ($values as $value) {
                if (is_int($value)) $types .= 'i';
                elseif (is_float($value)) $types .= 'd';
                elseif (is_string($value)) $types .= 's';
                else $types .= 'b';
            }
            
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            
            // Invalidate cache untuk tabel ini
            if ($this->useCache) {
                array_filter(self::$queryCache, function($key) {
                    return strpos($key, $this->table) === false;
                }, ARRAY_FILTER_USE_KEY);
            }
            
            return $stmt->affected_rows;
            
        } catch (\Exception $e) {
            error_log('Error pada update: ' . $e->getMessage());
            throw new \RuntimeException('Gagal update data: ' . $e->getMessage());
        }
    }

    /**
     * Menentukan kolom yang akan diselect
     * 
     * @param array $columns Array berisi nama-nama kolom
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function select(array $columns): self {
        $this->selects = array_map(function($column) {
            return $this->mysqli->real_escape_string($column);
        }, $columns);
        return $this;
    }

    /**
     * Menambahkan kondisi WHERE ke query
     * 
     * @param string $condition Kondisi WHERE dalam bentuk string
     * @param array $bindings Parameter yang akan di-bind ke kondisi
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function where(string $condition, array $bindings = []): self {
        $this->wheres[] = $condition;
        $this->bindings = array_merge($this->bindings, $bindings);
        return $this;
    }

    /**
     * Menambahkan kondisi OR WHERE ke query
     * @param string $condition Kondisi WHERE
     * @param array $bindings Parameter binding
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function orWhere(string $condition, array $bindings = []): self {
        if (!empty($this->wheres)) {
            $this->wheres[] = "OR " . $condition;
        } else {
            $this->wheres[] = $condition;
        }
        $this->bindings = array_merge($this->bindings, $bindings);
        return $this;
    }

    /**
     * Menambahkan kondisi BETWEEN ke query
     * @param string $column Nama kolom
     * @param mixed $start Nilai awal
     * @param mixed $end Nilai akhir
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function whereBetween(string $column, $start, $end): self {
        $this->wheres[] = sprintf("`%s` BETWEEN ? AND ?", $column);
        $this->bindings[] = $start;
        $this->bindings[] = $end;
        return $this;
    }

    /**
     * Menambahkan kondisi WHERE IN dengan subquery
     * @param string $column Nama kolom
     * @param callable $callback Fungsi untuk membangun subquery
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function whereIn(string $column, callable $callback): self {
        // Buat instance baru untuk subquery
        $subquery = new self('');
        
        // Jalankan callback untuk membangun subquery
        $callback($subquery);
        
        // Dapatkan query dari subquery
        $sql = $subquery->getQuery();
        
        // Tambahkan kondisi WHERE IN
        $this->wheres[] = sprintf("`%s` IN (%s)", $column, $sql);
        
        // Gabungkan bindings dari subquery
        $this->bindings = array_merge($this->bindings, $subquery->bindings);
        
        return $this;
    }

    /**
     * Menambahkan kondisi WHERE NOT IN dengan subquery
     * @param string $column Nama kolom
     * @param callable $callback Fungsi untuk membangun subquery
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function whereNotIn(string $column, callable $callback): self {
        $subquery = new self('');
        $callback($subquery);
        $sql = $subquery->getQuery();
        $this->wheres[] = sprintf("`%s` NOT IN (%s)", $column, $sql);
        $this->bindings = array_merge($this->bindings, $subquery->bindings);
        return $this;
    }

    /**
     * Menambahkan kondisi IS NULL ke query
     * @param string $column Nama kolom
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function whereNull(string $column): self {
        $this->wheres[] = sprintf("`%s` IS NULL", $column);
        return $this;
    }

    /**
     * Menambahkan kondisi IS NOT NULL ke query
     * @param string $column Nama kolom
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function whereNotNull(string $column): self {
        $this->wheres[] = sprintf("`%s` IS NOT NULL", $column);
        return $this;
    }

    /**
     * Menentukan pengurutan hasil query
     * 
     * @param string $column Nama kolom untuk pengurutan
     * @param string $direction Arah pengurutan (ASC/DESC)
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function orderBy(string $column, string $direction = 'ASC'): self {
        $this->orderBy = $this->mysqli->real_escape_string($column);
        $this->orderDirection = in_array(strtoupper($direction), ['ASC', 'DESC']) ? strtoupper($direction) : 'ASC';
        return $this;
    }

    /**
     * Membatasi jumlah baris hasil query
     * 
     * @param int $limit Jumlah maksimum baris
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function limit(int $limit): self {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Menentukan offset hasil query
     * 
     * @param int $offset Jumlah baris yang dilewati
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function offset(int $offset): self {
        $this->offset = $offset;
        return $this;
    }

    /**
     * Menambahkan JOIN ke query
     * 
     * @param string $table Nama tabel yang akan di-join
     * @param string $condition Kondisi join
     * @param string $type Tipe join (INNER/LEFT/RIGHT)
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function join(string $table, string $condition, string $type = 'INNER'): self {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'condition' => $condition
        ];
        return $this;
    }

    /**
     * Menambahkan LEFT JOIN ke query
     * 
     * @param string $table Nama tabel yang akan di-join
     * @param string $condition Kondisi join
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function leftJoin(string $table, string $condition): self {
        return $this->join($table, $condition, 'LEFT');
    }

    /**
     * Menambahkan RIGHT JOIN ke query
     * 
     * @param string $table Nama tabel yang akan di-join
     * @param string $condition Kondisi join
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function rightJoin(string $table, string $condition): self {
        return $this->join($table, $condition, 'RIGHT');
    }

    /**
     * Menambahkan GROUP BY ke query
     * 
     * @param string $columns Kolom-kolom untuk grouping (dipisahkan koma)
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function groupBy(string $columns): self {
        $this->groupBy = explode(',', $columns);
        return $this;
    }

    /**
     * Menambahkan kondisi HAVING ke query
     * 
     * @param string $condition Kondisi HAVING
     * @param array $params Parameter untuk kondisi
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function having(string $condition, array $params = []): self {
        $this->having = [
            'condition' => $condition,
            'params' => $params
        ];
        return $this;
    }

    /**
     * Menghasilkan string query SQL
     * 
     * @return string Query SQL lengkap
     */
    public function getQuery(): string {
        $query = "SELECT " . implode(', ', $this->selects) . " FROM `{$this->table}`";
        
        if (!empty($this->wheres)) {
            $query .= " WHERE " . implode(' AND ', $this->wheres);
        }
        
        if ($this->orderBy) {
            $query .= " ORDER BY `{$this->orderBy}` {$this->orderDirection}";
        }
        
        if ($this->limit !== null) {
            $query .= " LIMIT {$this->limit}";
            if ($this->offset !== null) {
                $query .= " OFFSET {$this->offset}";
            }
        }
        
        if (!empty($this->joins)) {
            $query .= $this->buildJoins();
        }
        
        if (!empty($this->groupBy)) {
            $query .= $this->buildGroupBy();
        }
        
        if (!empty($this->having)) {
            $query .= $this->buildHaving();
        }
        
        return $query;
    }

    /**
     * Menyisipkan satu baris data ke database
     * 
     * @param array $data Data yang akan disisipkan (kolom => nilai)
     * @return self Instance QueryBuilder untuk method chaining
     * @throws \RuntimeException Jika insert gagal
     */
    public function insert(array $data): self {
        try {
            // Gabungkan data dari parameter dengan data file yang sudah diupload
            $data = array_merge($this->sets, $data);
            
            $columns = array_keys($data);
            $values = array_values($data);
            $placeholders = str_repeat('?,', count($data) - 1) . '?';
            
            $query = "INSERT INTO `{$this->table}` 
                     (`" . implode('`,`', $columns) . "`) 
                     VALUES ({$placeholders})";
            
            $stmt = $this->mysqli->prepare($query);
            
            if (!$stmt) {
                throw new \RuntimeException('Prepare statement gagal: ' . $this->mysqli->error);
            }
            
            $types = '';
            foreach ($values as $value) {
                if (is_int($value)) $types .= 'i';
                elseif (is_float($value)) $types .= 'd';
                elseif (is_string($value)) $types .= 's';
                else $types .= 'b';
            }
            
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            
            // Simpan last insert id
            $this->lastInsertId = $this->mysqli->insert_id;
            
            // Reset sets array setelah insert
            $this->sets = [];
            
            return $this;
            
        } catch (\Exception $e) {
            error_log('Error pada insert: ' . $e->getMessage());
            throw new \RuntimeException('Gagal insert data: ' . $e->getMessage());
        }
    }

    /**
     * Mendapatkan data yang baru saja diinsert
     * 
     * @return array|null Data yang baru diinsert atau null jika belum ada insert
     * @throws \RuntimeException jika query gagal
     */
    public function getInsertedData(): ?array {
        if ($this->lastInsertId === null) {
            return null;
        }

        try {
            // Reset kondisi where yang mungkin ada sebelumnya
            $this->wheres = [];
            $this->bindings = [];
            
            // Set kondisi where untuk ID yang baru diinsert
            $this->where('id = ?', [$this->lastInsertId]);
            
            // Eksekusi query
            $result = $this->execute();
            
            return !empty($result) ? $result[0] : null;
            
        } catch (\Exception $e) {
            error_log('Error pada getInsertedData: ' . $e->getMessage());
            throw new \RuntimeException('Gagal mendapatkan data yang diinsert: ' . $e->getMessage());
        }
    }

    /**
     * Mendapatkan ID dari hasil insert terakhir
     * 
     * @return int|null ID dari data yang baru diinsert atau null jika belum ada insert
     */
    public function idFile(): ?int {
        return $this->lastInsertId;
    }

    /**
     * Menghapus data dari tabel yang ditentukan
     * Menggunakan kondisi WHERE yang telah diset sebelumnya
     * 
     * @return int Jumlah baris yang berhasil dihapus
     * @throws \RuntimeException Jika delete gagal
     */
    public function delete(): int {
        try {
            $query = "DELETE FROM `{$this->table}`";
            
            if (!empty($this->wheres)) {
                $query .= " WHERE " . implode(' AND ', $this->wheres);
            }
            
            $stmt = $this->mysqli->prepare($query);
            
            if (!empty($this->bindings)) {
                $types = '';
                foreach ($this->bindings as $binding) {
                    if (is_int($binding)) $types .= 'i';
                    elseif (is_float($binding)) $types .= 'd';
                    elseif (is_string($binding)) $types .= 's';
                    else $types .= 'b';
                }
                $stmt->bind_param($types, ...$this->bindings);
            }
            
            $stmt->execute();
            return $stmt->affected_rows;
            
        } catch (\Exception $e) {
            error_log('Error pada delete: ' . $e->getMessage());
            throw new \RuntimeException('Gagal delete data: ' . $e->getMessage());
        }
    }

    /**
     * Mengatur path untuk upload file dengan validasi keamanan
     * 
     * @param string $path Path direktori untuk menyimpan file
     * @return self Instance QueryBuilder untuk method chaining
     * @throws \RuntimeException Jika path tidak valid
     */
    public function setUploadPath(string $path): self {
        // Normalisasi path dengan mengubah backslash ke forward slash
        $path = str_replace('\\', '/', $path);
        
        // Hapus trailing slash
        $path = rtrim($path, '/');
        
        // Validasi path dasar
        if (empty($path)) {
            throw new \RuntimeException('Path upload tidak boleh kosong');
        }
        
        // Jika path adalah absolute path (dimulai dengan C:/ atau /)
        if (preg_match('~^([A-Za-z]:)?/~', $path)) {
            $this->uploadsPath = $path;
        } else {
            // Jika path relatif, gabungkan dengan baseServerPath
            $this->uploadsPath = $this->baseServerPath . '/' . $path;
        }
        
        // Pastikan direktori ada atau bisa dibuat
        if (!is_dir($this->uploadsPath)) {
            if (!mkdir($this->uploadsPath, 0777, true)) {
                throw new \RuntimeException("Tidak dapat membuat direktori {$this->uploadsPath}");
            }
        }
        
        // Pastikan direktori bisa ditulis
        if (!is_writable($this->uploadsPath)) {
            throw new \RuntimeException('Direktori upload tidak memiliki permission write');
        }
        
        return $this;
    }

    /**
     * Mengatur ukuran maksimum file dalam MB
     * 
     * @param int $sizeMB Ukuran maksimum dalam MB
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function size(int $sizeMB): self {
        $this->maxFileSize = $sizeMB * 1024 * 1024;
        return $this;
    }

    /**
     * Mengatur tipe file yang diizinkan
     * 
     * @param array $types Array dari MIME types yang diizinkan
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function type(array $types): self {
        $this->allowedTypes = $types;
        return $this;
    }
    
    /**
     * Memformat ukuran file ke dalam format yang mudah dibaca (B, KB, MB)
     * 
     * @param int $bytes Ukuran file dalam bytes
     * @return string Ukuran file yang sudah diformat
     */
    private function formatFileSize(int $bytes): string {
        return $bytes < 1024 ? $bytes . ' B' : 
               ($bytes < 1048576 ? round($bytes/1024, 2) . ' KB' : 
               round($bytes/1048576, 2) . ' MB');
    }

    /**
     * Mendapatkan tipe file yang sebenarnya
     * 
     * @param string $path Path ke file
     * @return string Tipe file
     */
    private function getFileType(string $path): string {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $type = finfo_file($finfo, $path);
        finfo_close($finfo);
        
        // Perbaikan pengecekan tipe file berdasarkan ekstensi
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        // Mapping ekstensi ke MIME type
        $mimeTypes = [
            'pdf'  => 'application/pdf',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls'  => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt'  => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        ];
        
        // Kembalikan MIME type yang sesuai jika ekstensi ada dalam mapping
        if (isset($mimeTypes[$extension])) {
            return $mimeTypes[$extension];
        }
        
        return $type;
    }

    /**
     * Membuat thumbnail dari gambar yang diupload
     * 
     * @param string $sourcePath Path file sumber
     * @param string $targetPath Path file tujuan
     * @param array $dimensions Dimensi yang diinginkan
     * @return bool True jika berhasil, false jika gagal
     */
    private function createThumbnail(string $sourcePath, string $targetPath, array $dimensions): bool {
        try {
            // Deteksi tipe gambar
            $imageType = exif_imagetype($sourcePath);
            
            // Buat sumber gambar berdasarkan tipe
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $sourceImage = imagecreatefromjpeg($sourcePath);
                    break;
                case IMAGETYPE_PNG:
                    $sourceImage = imagecreatefrompng($sourcePath);
                    break;
                case IMAGETYPE_GIF:
                    $sourceImage = imagecreatefromgif($sourcePath);
                    break;
                default:
                    throw new \RuntimeException('Format gambar tidak didukung');
            }

            if (!$sourceImage) {
                throw new \RuntimeException('Gagal membuat sumber gambar');
            }

            // Dapatkan dimensi sumber
            $sourceWidth = imagesx($sourceImage);
            $sourceHeight = imagesy($sourceImage);

            // Hitung dimensi thumbnail dengan mempertahankan aspek ratio
            $ratio = min($dimensions['width'] / $sourceWidth, $dimensions['height'] / $sourceHeight);
            $targetWidth = round($sourceWidth * $ratio);
            $targetHeight = round($sourceHeight * $ratio);

            // Buat gambar thumbnail
            $thumbnailImage = imagecreatetruecolor($targetWidth, $targetHeight);

            // Handling transparansi untuk PNG
            if ($imageType === IMAGETYPE_PNG) {
                imagealphablending($thumbnailImage, false);
                imagesavealpha($thumbnailImage, true);
                $transparent = imagecolorallocatealpha($thumbnailImage, 255, 255, 255, 127);
                imagefilledrectangle($thumbnailImage, 0, 0, $targetWidth, $targetHeight, $transparent);
            }

            // Resize gambar
            imagecopyresampled(
                $thumbnailImage, $sourceImage,
                0, 0, 0, 0,
                $targetWidth, $targetHeight,
                $sourceWidth, $sourceHeight
            );

            // Simpan thumbnail sesuai format asli
            $result = false;
            switch ($imageType) {
                case IMAGETYPE_JPEG:
                    $result = imagejpeg($thumbnailImage, $targetPath, 90);
                    break;
                case IMAGETYPE_PNG:
                    $result = imagepng($thumbnailImage, $targetPath, 9);
                    break;
                case IMAGETYPE_GIF:
                    $result = imagegif($thumbnailImage, $targetPath);
                    break;
            }

            // Bersihkan memory
            imagedestroy($sourceImage);
            imagedestroy($thumbnailImage);

            return $result;

        } catch (\Exception $e) {
            error_log('Error membuat thumbnail: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Menentukan folder tujuan berdasarkan tipe file dan tanggal
     */
    private function getUploadDestination(string $mimeType, ?string $size = null, bool $fullPath = true): string {
        $year = date('Y');
        $month = date('m');
        
        // Tentukan subfolder berdasarkan tipe file
        if (in_array($mimeType, $this->imageTypes)) {
            $subPath = sprintf('img/%s/%s/%s', 
                $size ?? 'original',
                $year, 
                $month
            );
        } elseif (in_array($mimeType, $this->documentTypes)) {
            $subPath = sprintf('doc/%s/%s', 
                $year, 
                $month
            );
        } else {
            $subPath = sprintf('other/%s/%s', 
                $year, 
                $month
            );
        }

        // Normalisasi base path
        $basePath = str_replace('\\', '/', $this->uploadsPath);
        
        // Gabungkan path
        $fullPathDir = $basePath . '/' . $subPath;
        
        // Buat direktori jika belum ada
        if (!file_exists($fullPathDir)) {
            if (!mkdir($fullPathDir, 0777, true)) {
                throw new \RuntimeException("Gagal membuat direktori upload: {$fullPathDir}");
            }
            chmod($fullPathDir, 0755);
        }
        
        return $fullPath ? $fullPathDir : $subPath;
    }

    /**
     * Membuat direktori rekursif dengan permission yang aman
     * 
     * @param string $path Path direktori yang akan dibuat
     * @throws \RuntimeException jika gagal membuat direktori
     */
    private function createDirectory(string $path): void {
        // Normalisasi path dengan menghapus trailing/leading slashes
        $path = rtrim($path, '/');
        
        if (!file_exists($path)) {
            // Coba buat direktori dengan permission 0755
            if (!@mkdir($path, 0755, true)) {
                $error = error_get_last();
                throw new \RuntimeException("Gagal membuat direktori {$path}: " . ($error['message'] ?? 'Unknown error'));
            }
            
            // Set permission yang aman untuk setiap level direktori
            $parts = explode('/', $path);
            $currentPath = '';
            foreach ($parts as $part) {
                $currentPath .= $part . '/';
                if (is_dir($currentPath)) {
                    chmod($currentPath, 0755);
                }
            }
        }
        
        // Periksa apakah direktori writable
        if (!is_writable($path)) {
            throw new \RuntimeException("Direktori {$path} tidak writable");
        }
    }

    /**
     * Mengupload file dan membuat multiple ukuran untuk gambar
     */
    public function uploadFile(array $file, string $column, ?string $typeColumn = null, ?string $sizeColumn = null): self {
        try {
            // Validasi file array
            if (!isset($file['tmp_name']) || !isset($file['name']) || !isset($file['error'])) {
                throw new \RuntimeException('Format file upload tidak valid');
            }

            // Cek error upload PHP
            if ($file['error'] !== UPLOAD_ERR_OK) {
                switch ($file['error']) {
                    case UPLOAD_ERR_INI_SIZE:
                        throw new \RuntimeException('File melebihi upload_max_filesize di php.ini');
                    case UPLOAD_ERR_FORM_SIZE:
                        throw new \RuntimeException('File melebihi MAX_FILE_SIZE di form HTML');
                    case UPLOAD_ERR_PARTIAL:
                        throw new \RuntimeException('File hanya terupload sebagian');
                    case UPLOAD_ERR_NO_FILE:
                        throw new \RuntimeException('Tidak ada file yang diupload');
                    default:
                        throw new \RuntimeException('Error upload tidak diketahui');
                }
            }

            // Cek tipe file
            $actual_type = $this->getFileType($file['tmp_name']);
            if (!in_array($actual_type, $this->allowedTypes)) {
                throw new \RuntimeException('Tipe file tidak diizinkan: ' . $actual_type);
            }

            // Generate nama file yang aman
            $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $safeName = preg_replace('/[^a-z0-9-_]/i', '_', $originalName);
            $filename = sprintf('%s-%s.%s', $safeName, uniqid(), $extension);

            // Upload file original
            $uploadDir = $this->getUploadDestination($actual_type);
            $destination = $uploadDir . '/' . $filename;

            // Debug log
            error_log("Mencoba upload file ke: " . $destination);
            error_log("File type: " . $actual_type);

            // Pindahkan file
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                throw new \RuntimeException('Gagal memindahkan file yang diupload ke ' . $destination);
            }

            chmod($destination, 0644);

            // Jika file adalah gambar, buat thumbnail
            if (in_array($actual_type, $this->imageTypes)) {
                foreach ($this->imageSizes as $size => $dimensions) {
                    // Buat direktori untuk ukuran thumbnail ini
                    $thumbDir = $this->getUploadDestination($actual_type, $size);
                    $thumbPath = $thumbDir . '/' . $filename;
                    
                    // Buat thumbnail
                    if (!$this->createThumbnail($destination, $thumbPath, $dimensions)) {
                        error_log("Gagal membuat thumbnail {$size} untuk {$filename}");
                    } else {
                        chmod($thumbPath, 0644);
                    }
                }
            }

            // Simpan path relatif ke database
            $relativePath = str_replace($this->uploadsPath . '/', '', $destination);
            $this->sets[$column] = $relativePath;

            // Simpan tipe dan ukuran file jika diminta
            if ($typeColumn !== null) {
                $parts = explode('/', $actual_type);
                $this->sets[$typeColumn] = end($parts);
            }
            if ($sizeColumn !== null) {
                $this->sets[$sizeColumn] = $this->formatFileSize(filesize($destination));
            }

            return $this;

        } catch (\Exception $e) {
            error_log('Error upload file: ' . $e->getMessage());
            throw new \RuntimeException('Gagal upload file: ' . $e->getMessage());
        }
    }

    /**
     * Menghapus file yang diupload
     */
    public function deleteUploadedFile(string $filepath): bool {
        if (file_exists($filepath) && is_file($filepath)) {
            return unlink($filepath);
        }
        return false;
    }

    /**
     * Mengatur base upload path
     * 
     * @param string $path Base upload path
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function setBasePath(string $path): self {
        $this->uploadsPath = rtrim($path, '/') . '/';
        return $this;
    }

    /**
     * Mengatur ukuran thumbnail yang akan dibuat
     * 
     * @param array $sizes Array ukuran thumbnail (contoh: ['100x100', '250x250'])
     * @return self Instance QueryBuilder untuk method chaining
     */
    public function imageSizes(array $sizes): self {
        $this->imageSizes = [];
        
        foreach ($sizes as $size) {
            // Parse format "widthxheight"
            if (preg_match('/^(\d+)x(\d+)$/', $size, $matches)) {
                $width = (int)$matches[1];
                $height = (int)$matches[2];
                
                $this->imageSizes[$size] = [
                    'width' => $width,
                    'height' => $height
                ];
            } else {
                throw new \RuntimeException("Format ukuran tidak valid: $size. Gunakan format: widthxheight");
            }
        }
        
        return $this;
    }

    /**
     * Mendapatkan path upload berdasarkan ID data
     * 
     * @param int $id ID data
     * @param string $column Nama kolom yang menyimpan path file
     * @return string|null Path file atau null jika tidak ditemukan
     * @throws \RuntimeException jika query gagal
     */
    public function getUploadPathById(int $id, string $column): ?string {
        try {
            // Reset kondisi where yang mungkin ada sebelumnya
            $this->wheres = [];
            $this->bindings = [];
            
            // Set kondisi where untuk ID
            $this->where('id = ?', [$id]);
            
            // Select hanya kolom yang dibutuhkan
            $this->select([$column]);
            
            // Eksekusi query
            $result = $this->execute();
            
            if (empty($result)) {
                return null;
            }
            
            $filePath = $result[0][$column] ?? null;
            
            if ($filePath === null) {
                return null;
            }
            
            // Gabungkan dengan base upload path
            return $this->uploadsPath . '/' . $filePath;
            
        } catch (\Exception $e) {
            error_log('Error pada getUploadPathById: ' . $e->getMessage());
            throw new \RuntimeException('Gagal mendapatkan path upload: ' . $e->getMessage());
        }
    }

    /**
     * Mendapatkan semua versi thumbnail untuk gambar berdasarkan ID
     * 
     * @param int $id ID data
     * @param string $column Nama kolom yang menyimpan path file
     * @return array Array berisi path untuk semua ukuran thumbnail
     * @throws \RuntimeException jika query gagal
     */
    public function getImageThumbnailsById(int $id, string $column): array {
        try {
            $originalPath = $this->getUploadPathById($id, $column);
            
            if (!$originalPath) {
                return [];
            }
            
            // Cek apakah file adalah gambar
            $mimeType = $this->getFileType($originalPath);
            if (!in_array($mimeType, $this->imageTypes)) {
                return [];
            }
            
            $thumbnails = [];
            $filename = basename($originalPath);
            
            // Dapatkan path relatif
            $relativePath = str_replace($this->uploadsPath . '/', '', $originalPath);
            $pathInfo = pathinfo($relativePath);
            
            // Tambahkan path original
            $thumbnails['original'] = $originalPath;
            
            // Dapatkan path untuk setiap ukuran thumbnail
            foreach ($this->imageSizes as $size => $dimensions) {
                $thumbPath = sprintf('%s/img/%s/%s/%s/%s',
                    $this->uploadsPath,
                    $size,
                    date('Y', filemtime($originalPath)),
                    date('m', filemtime($originalPath)),
                    $filename
                );
                
                if (file_exists($thumbPath)) {
                    $thumbnails[$size] = $thumbPath;
                }
            }
            
            return $thumbnails;
            
        } catch (\Exception $e) {
            error_log('Error pada getImageThumbnailsById: ' . $e->getMessage());
            throw new \RuntimeException('Gagal mendapatkan thumbnails: ' . $e->getMessage());
        }
    }

    /**
     * Mendapatkan data spesifik berdasarkan ID
     * 
     * @param int $id ID data yang dicari
     * @param array $columns Array kolom yang ingin diambil
     * @return array|null Data yang ditemukan atau null jika tidak ada
     * @throws \RuntimeException jika query gagal
     */
    public function getDataById(int $id, array $columns = ['*']): ?array {
        try {
            // Reset kondisi where yang mungkin ada sebelumnya
            $this->wheres = [];
            $this->bindings = [];
            
            // Set kondisi where untuk ID
            $this->where('id = ?', [$id]);
            
            // Select kolom yang diinginkan
            $this->select($columns);
            
            // Eksekusi query
            $result = $this->execute();
            
            return !empty($result) ? $result[0] : null;
            
        } catch (\Exception $e) {
            error_log('Error pada getDataById: ' . $e->getMessage());
            throw new \RuntimeException('Gagal mendapatkan data: ' . $e->getMessage());
        }
    }

    /**
     * Menutup koneksi database
     */
    public function closeConnection(): void {
        if ($this->mysqli) {
            $this->mysqli->close();
        }
    }

    public function __destruct() {
        // Bersihkan prepared statements yang tidak digunakan
        foreach (self::$preparedStatements as $stmt) {
            $stmt->close();
        }
        self::$preparedStatements = [];
        
        // Bersihkan cache yang expired
        foreach (self::$queryCache as $key => $cached) {
            if ($cached['expires'] <= time()) {
                unset(self::$queryCache[$key]);
            }
        }
    }

    /**
     * Membangun string JOIN untuk query
     */
    private function buildJoins(): string {
        $joinStr = '';
        foreach ($this->joins as $join) {
            $type = strtoupper($join['type']);
            $table = $this->mysqli->real_escape_string($join['table']);
            $joinStr .= " {$type} JOIN `{$table}` ON {$join['condition']}";
        }
        return $joinStr;
    }

    /**
     * Membangun string GROUP BY untuk query
     */
    private function buildGroupBy(): string {
        if (empty($this->groupBy)) {
            return '';
        }
        
        $columns = array_map(function($column) {
            return $this->mysqli->real_escape_string($column);
        }, $this->groupBy);
        
        return " GROUP BY " . implode(', ', $columns);
    }

    /**
     * Membangun string HAVING untuk query
     */
    private function buildHaving(): string {
        if (empty($this->having)) {
            return '';
        }
        
        $havingStr = " HAVING {$this->having['condition']}";
        
        if (!empty($this->having['params'])) {
            $this->bindings = array_merge($this->bindings, $this->having['params']);
        }
        
        return $havingStr;
    }

    /**
     * Mengambil data dalam jumlah besar dengan paginasi
     */
    public function fetchLargeData(array $params = []): array {
        try {
            // Monitoring koneksi
            error_log('Status koneksi: ' . ($this->mysqli->ping() ? 'Connected' : 'Disconnected'));
            
            $page = (int)($params['page'] ?? 1);
            $limit = (int)($params['limit'] ?? 100);
            $offset = ($page - 1) * $limit;
            
            if (!$this->mysqli->ping()) {
                error_log('Mencoba reconnect ke database...');
                $db = new NgoreiDb();
                $this->mysqli = $db->connMysqli();
                if (!$this->mysqli->ping()) {
                    throw new \RuntimeException('Koneksi database terputus');
                }
                error_log('Reconnect berhasil');
            }
            
            // Start monitoring waktu query
            $queryStart = microtime(true);
            
            $cacheKey = "table_{$this->table}_page_{$page}_limit_{$limit}";
            $cachedResult = $this->getFromCache($cacheKey);

            if ($cachedResult !== null) {
                return array_merge($cachedResult, ['cache_hit' => true]);
            }
            
            if (!empty($params['filters'])) {
                foreach ($params['filters'] as $field => $value) {
                    $this->where("`$field` = ?", [$value]);
                }
            }
            
            $this->limit($limit);
            $this->offset($offset);
            $this->orderBy('id', 'DESC');
            
            // Monitor eksekusi query
            $executeStart = microtime(true);
            $result = $this->execute();
            $executeTime = microtime(true) - $executeStart;
            error_log("Waktu eksekusi query: {$executeTime} detik");
            
            // Total waktu query
            $totalQueryTime = microtime(true) - $queryStart;
            error_log("Total waktu proses query: {$totalQueryTime} detik");
            
            // Monitor penggunaan memory
            $memoryUsage = memory_get_usage(true) / 1024 / 1024; // dalam MB
            error_log("Penggunaan memory: {$memoryUsage} MB");
            
            $finalResult = [
                'payload' => $result,
                'total' => count($result),
                'page' => $page,
                'limit' => $limit,
                'cache_hit' => false,
                'performance' => [
                    'query_time' => $totalQueryTime,
                    'execute_time' => $executeTime,
                    'memory_usage' => $memoryUsage,
                    'rows_returned' => count($result)
                ]
            ];
            
            if ($this->useCache) {
                $this->setCache($cacheKey, $finalResult);
            }
            
            return $finalResult;
            
        } catch (\Exception $e) {
            error_log('Error pada fetchLargeData: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            throw new \RuntimeException('Gagal mengambil data: ' . $e->getMessage());
        }
    }

    /**
     * Mengambil data menggunakan cursor untuk data yang sangat besar
     */
    public function fetchLargeDataCursor(array $params = []): array {
        try {
            $limit = (int)($params['limit'] ?? 1000);
            $lastId = (int)($params['last_id'] ?? 0);
            
            $this->where("id > ?", [$lastId]);
            
            if (!empty($params['filters'])) {
                foreach ($params['filters'] as $field => $value) {
                    $field = $this->mysqli->real_escape_string($field);
                    $this->where("`$field` = ?", [$value]);
                }
            }
            
            if (!empty($params['select'])) {
                $this->select($params['select']);
            }
            
            $this->orderBy('id', 'ASC');
            $this->limit($limit);
            
            $data = $this->execute();
            
            // Simpan last_id untuk pagination selanjutnya
            $lastId = !empty($data) ? end($data)['id'] : $lastId;
            
            return [
                'payload' => $data,
                'next_cursor' => $lastId,
                'has_more' => count($data) === $limit
            ];
            
        } catch (\Exception $e) {
            error_log('Error pada fetchLargeDataCursor: ' . $e->getMessage());
            throw new \RuntimeException('Gagal mengambil data: ' . $e->getMessage());
        }
    }

    /**
     * Memproses data dalam jumlah besar dengan chunks
     */
    public function processMillionRows(callable $callback): void {
        try {
            $lastId = 0;
            $batchSize = 10000; // Proses 10rb data per batch
            
            do {
                $result = $this->fetchLargeDataCursor([
                    'last_id' => $lastId,
                    'limit' => $batchSize
                ]);
                
                foreach ($result['payload'] as $row) {
                    $callback($row);
                }
                
                $lastId = $result['next_cursor'];
                
                gc_collect_cycles();
                
            } while ($result['has_more']);
            
        } catch (\Exception $e) {
            error_log('Error pada processMillionRows: ' . $e->getMessage());
            throw new \RuntimeException('Gagal memproses data: ' . $e->getMessage());
        }
    }

    /**
     * Memvalidasi keberadaan tabel
     */
    public function validateTable(): bool {
        try {
            $query = "SHOW TABLES LIKE ?";
            $stmt = $this->mysqli->prepare($query);
            
            if ($stmt === false) {
                throw new \RuntimeException('Prepare statement gagal: ' . $this->mysqli->error);
            }
            
            if (!$stmt->bind_param('s', $this->table)) {
                throw new \RuntimeException('Bind parameter gagal: ' . $stmt->error);
            }
            
            if (!$stmt->execute()) {
                throw new \RuntimeException('Execute statement gagal: ' . $stmt->error);
            }
            
            $result = $stmt->get_result();
            
            return $result->num_rows > 0;
            
        } catch (\Exception $e) {
            error_log('Error pada validateTable: ' . $e->getMessage());
            throw new \RuntimeException('Gagal memvalidasi tabel: ' . $e->getMessage());
        }
    }

    /**
     * Mendapatkan daftar kolom dari tabel
     */
    public function getTableColumns(): array {
        try {
            $query = "SHOW COLUMNS FROM `{$this->table}`";
            $result = $this->mysqli->query($query);
            
            if (!$result) {
                throw new \RuntimeException('Gagal mengambil struktur tabel');
            }
            
            $columns = [];
            while ($row = $result->fetch_assoc()) {
                $columns[] = $row['Field'];
            }
            
            return $columns;
            
        } catch (\Exception $e) {
            error_log('Error pada getTableColumns: ' . $e->getMessage());
            throw new \RuntimeException('Gagal mengambil struktur tabel: ' . $e->getMessage());
        }
    }

    /**
     * Memproses data dalam batch
     */
    private function processBatch(array $data, int $batchSize = 1000): array {
        $result = [];
        foreach (array_chunk($data, $batchSize) as $batch) {
            $result = array_merge($result, $batch);
        }
        return $result;
    }
} 