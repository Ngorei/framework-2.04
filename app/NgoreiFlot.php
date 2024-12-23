<?php
namespace app;
use PDO;
use PDOException;

class NgoreiFlot {
    private $table;
    private $select = [];
    private $where = [];
    private $groupBy = [];
    private $orderBy = [];
    private $mysqli;
    private $limit = null;
    private $offset = null;
    private $queryCache = [];
    private $indexHint = '';
    private $queryLog = [];
    private $cacheExpiry = 3600; // 1 jam
    
    public function __construct() {
        $db = new NgoreiDb();
        $this->mysqli = $db->connPDO();
    }

    /**
     * Set tabel untuk query
     * @param string $table Nama tabel
     * @return NgoreiFlot
     */
    public function table($table) {
        $this->table = $table;
        return $this;
    }

    /**
     * Set kolom yang akan diselect
     * @param mixed $columns String atau array kolom
     * @return NgoreiFlot
     */
    public function select($columns) {
        $this->select = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * Tambah kondisi WHERE
     * @param string $column Nama kolom
     * @param string $operator Operator perbandingan
     * @param mixed $value Nilai
     * @return NgoreiFlot
     */
    public function where($column, $operator, $value) {
        $this->where[] = [$column, $operator, $value];
        return $this;
    }

    /**
     * Set GROUP BY
     * @param mixed $columns String atau array kolom
     * @return NgoreiFlot
     */
    public function groupBy($columns) {
        $this->groupBy = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * Set ORDER BY
     * @param string $column Nama kolom
     * @param string $direction Arah pengurutan (ASC/DESC)
     * @return NgoreiFlot
     */
    public function orderBy($column, $direction = 'ASC') {
        $this->orderBy[] = [$column, strtoupper($direction)];
        return $this;
    }

    /**
     * Set LIMIT untuk query
     * @param int $limit Jumlah record yang akan diambil
     * @param int $offset Mulai dari record ke-n (optional)
     * @return NgoreiFlot
     */
    public function limit($limit, $offset = null) {
        $this->limit = (int)$limit;
        if ($offset !== null) {
            $this->offset = (int)$offset;
        }
        return $this;
    }

    /**
     * Set OFFSET untuk query
     * @param int $offset Mulai dari record ke-n
     * @return NgoreiFlot
     */
    public function offset($offset) {
        $this->offset = (int)$offset;
        return $this;
    }

    /**
     * Eksekusi query dan ambil hasil
     * @return array
     */
    public function get() {
        try {
            $query = $this->buildQuery();
            $cacheKey = md5($query['sql'] . serialize($query['params']));
            
            // Cek cache
            if (isset($this->queryCache[$cacheKey])) {
                return $this->queryCache[$cacheKey];
            }
            
            $stmt = $this->mysqli->prepare($query['sql']);
            
            if (!empty($query['params'])) {
                $stmt->execute($query['params']);
            } else {
                $stmt->execute();
            }
            
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Simpan ke cache
            $this->queryCache[$cacheKey] = $result;
            
            $this->resetQuery();
            
            return $result;
        } catch (PDOException $e) {
            return [
                'error' => 'Database error',
                'message' => $e->getMessage(),
                'sql' => $query['sql'] ?? null
            ];
        }
    }

    /**
     * Bangun query SQL
     * @return array
     */
    private function buildQuery() {
        $params = [];
        
        // Tambahkan index hint jika diperlukan
        $indexHint = '';
        if (!empty($this->indexHint)) {
            $indexHint = " USE INDEX ({$this->indexHint})";
        }
        
        $select = !empty($this->select) ? implode(', ', $this->select) : '*';
        $sql = "SELECT {$select} FROM {$this->table}{$indexHint}";

        // WHERE clause
        if (!empty($this->where)) {
            $whereClauses = [];
            foreach ($this->where as $condition) {
                $whereClauses[] = "{$condition[0]} {$condition[1]} ?";
                $params[] = $condition[2];
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        // GROUP BY clause
        if (!empty($this->groupBy)) {
            $sql .= " GROUP BY " . implode(', ', $this->groupBy);
        }

        // ORDER BY clause
        if (!empty($this->orderBy)) {
            $orderClauses = [];
            foreach ($this->orderBy as $order) {
                $orderClauses[] = "{$order[0]} {$order[1]}";
            }
            $sql .= " ORDER BY " . implode(', ', $orderClauses);
        }

        // LIMIT dan OFFSET - Perbaikan disini
        if ($this->limit !== null) {
            $sql .= " LIMIT " . (int)$this->limit; // Langsung konversi ke integer
            
            if ($this->offset !== null) {
                $sql .= " OFFSET " . (int)$this->offset;
            }
        }

        return [
            'sql' => $sql,
            'params' => $params
        ];
    }

    /**
     * Reset semua property query builder
     * @return void
     */
    private function resetQuery() {
        $this->select = [];
        $this->where = [];
        $this->groupBy = [];
        $this->orderBy = [];
        $this->limit = null;
        $this->offset = null;
    }

    /**
     * Format data untuk line chart
     * @param array $result Data hasil query
     * @param string $labelColumn Kolom untuk label
     * @param string $valueColumn Kolom untuk nilai
     * @return array
     */
    public function toLineChart($result, $labelColumn, $valueColumn) {
        $data = [];
        // Tambahkan pengecekan error
        if (isset($result['error'])) {
            return $result;
        }
        
        // Tambahkan debug
        if (empty($result)) {
            return ['error' => 'No data found'];
        }

        try {
            foreach ($result as $row) {
                // Pastikan kolom ada
                if (!isset($row[$labelColumn]) || !isset($row[$valueColumn])) {
                    return [
                        'error' => 'Column not found',
                        'message' => "Required columns ($labelColumn, $valueColumn) not found in result",
                        'available_columns' => array_keys($row)
                    ];
                }

                $data[] = [
                    (int)$row[$labelColumn],
                    (float)$row[$valueColumn]
                ];
            }
            return $data;
        } catch (Exception $e) {
            return [
                'error' => 'Data processing error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Format data untuk pie chart
     * @param array $result Data hasil query
     * @param string $labelColumn Kolom untuk label
     * @param string $valueColumn Kolom untuk nilai
     * @return array
     */
    public function toPieChart($result, $labelColumn, $valueColumn) {
        $data = [];
        foreach ($result as $row) {
            $data[] = [
                'label' => $row[$labelColumn],
                'data' => (float)$row[$valueColumn]
            ];
        }
        return $data;
    }

    /**
     * Format data untuk bar chart
     * @param array $result Data hasil query
     * @param string $labelColumn Kolom untuk label
     * @param string $valueColumn Kolom untuk nilai
     * @return array
     */
    public function toBarChart($result, $labelColumn, $valueColumn) {
        $data = [];
        $ticks = [];
        foreach ($result as $index => $row) {
            $data[] = [$index, (float)$row[$valueColumn]];
            $ticks[] = [$index, $row[$labelColumn]];
        }
        return [
            'data' => $data,
            'ticks' => $ticks
        ];
    }

    /**
     * Format data untuk multi line chart
     * @param array $result Data hasil query
     * @param string $dateColumn Kolom tanggal
     * @param array $series Array series yang akan ditampilkan
     * @return array
     */
    public function toMultiLineChart($result, $dateColumn, $series) {
        $data = [];
        foreach ($series as $key => $column) {
            $seriesData = [];
            foreach ($result as $row) {
                $timestamp = strtotime($row[$dateColumn]) * 1000;
                $seriesData[] = [$timestamp, (float)$row[$column]];
            }
            $data[] = [
                'label' => $key,
                'data' => $seriesData
            ];
        }
        return $data;
    }

    /**
     * Format data untuk area chart
     * @param array $result Data hasil query
     * @param string $dateColumn Kolom tanggal
     * @param string $valueColumn Kolom nilai
     * @return array
     */
    public function toAreaChart($result, $dateColumn, $valueColumn) {
        $data = [];
        foreach ($result as $row) {
            $timestamp = strtotime($row[$dateColumn]) * 1000;
            $data[] = [$timestamp, (float)$row[$valueColumn]];
        }
        return [
            'payload' => $data,
            'lines' => ['fill' => 0.8]
        ];
    }

    /**
     * Format data untuk bubble chart
     * @param array $result Data hasil query
     * @param string $xColumn Kolom untuk sumbu X
     * @param string $yColumn Kolom untuk sumbu Y
     * @param string $radiusColumn Kolom untuk radius bubble
     * @return array
     */
    public function toBubbleChart($result, $xColumn, $yColumn, $radiusColumn) {
        $data = [];
        foreach ($result as $row) {
            $data[] = [
                (float)$row[$xColumn],
                (float)$row[$yColumn],
                (float)$row[$radiusColumn]
            ];
        }
        return $data;
    }

    /**
     * Format data untuk candlestick chart
     * @param array $result Data hasil query
     * @return array
     */
    public function toCandlestickChart($result) {
        $data = [];
        foreach ($result as $row) {
            $timestamp = strtotime($row['tanggal']) * 1000;
            $data[] = [
                $timestamp,
                (float)$row['open'],
                (float)$row['high'],
                (float)$row['low'],
                (float)$row['close']
            ];
        }
        return $data;
    }

    /**
     * Format data untuk stacked bar chart
     * @param array $result Data hasil query
     * @param array $categories Kategori data
     * @param array $valueColumns Kolom nilai untuk setiap kategori
     * @return array
     */
    public function toStackedBar($result, $categories, $valueColumns) {
        $series = [];
        foreach ($valueColumns as $key => $column) {
            $data = [];
            foreach ($result as $index => $row) {
                $data[] = [$index, (float)$row[$column]];
            }
            $series[] = [
                'label' => $key,
                'data' => $data
            ];
        }
        return $series;
    }

    /**
     * Format data untuk time series chart
     * @param array $result Data hasil query
     * @param string $dateColumn Kolom tanggal
     * @param array $valueColumns Array kolom nilai
     * @return array
     */
    public function toTimeSeriesChart($result, $dateColumn, $valueColumns) {
        try {
            $series = [];
            foreach ($valueColumns as $label => $column) {
                $data = [];
                foreach ($result as $row) {
                    if (!isset($row[$dateColumn]) || !isset($row[$column])) {
                        continue;
                    }
                    $timestamp = strtotime($row[$dateColumn]) * 1000;
                    $data[] = [$timestamp, (float)$row[$column]];
                }
                $series[] = [
                    'label' => $label,
                    'data' => $data
                ];
            }
            return [
                'status' => 'success',
                'data' => $series
            ];
        } catch (Exception $e) {
            return ['error' => 'Time series processing failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Format data untuk donut chart
     * @param array $result Data hasil query
     * @param string $labelColumn Kolom label
     * @param string $valueColumn Kolom nilai
     * @return array
     */
    public function toDonutChart($result, $labelColumn, $valueColumn) {
        try {
            $data = [];
            $total = 0;
            
            foreach ($result as $row) {
                $value = (float)$row[$valueColumn];
                $total += $value;
                $data[] = [
                    'label' => $row[$labelColumn],
                    'data' => $value
                ];
            }

            // Hitung persentase
            foreach ($data as &$item) {
                $item['percentage'] = round(($item['data'] / $total) * 100, 2);
            }

            return [
                'status' => 'success',
                'data' => $data,
                'total' => $total
            ];
        } catch (Exception $e) {
            return ['error' => 'Donut chart processing failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Format data untuk radar chart
     * @param array $result Data hasil query
     * @param string $categoryColumn Kolom kategori
     * @param array $metrics Array metrik yang akan diukur
     * @return array
     */
    public function toRadarChart($result, $categoryColumn, $metrics) {
        try {
            $data = [];
            $categories = [];
            
            foreach ($result as $row) {
                $categories[] = $row[$categoryColumn];
                foreach ($metrics as $label => $metric) {
                    if (!isset($data[$label])) {
                        $data[$label] = ['label' => $label, 'data' => []];
                    }
                    $data[$label]['data'][] = (float)$row[$metric];
                }
            }

            return [
                'status' => 'success',
                'data' => array_values($data),
                'categories' => $categories
            ];
        } catch (Exception $e) {
            return ['error' => 'Radar chart processing failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Format data untuk heatmap chart
     * @param array $result Data hasil query
     * @param string $xColumn Kolom untuk sumbu X
     * @param string $yColumn Kolom untuk sumbu Y
     * @param string $valueColumn Kolom untuk nilai intensitas
     * @return array
     */
    public function toHeatmapChart($result, $xColumn, $yColumn, $valueColumn) {
        try {
            $data = [];
            $xCategories = [];
            $yCategories = [];
            
            foreach ($result as $row) {
                if (!in_array($row[$xColumn], $xCategories)) {
                    $xCategories[] = $row[$xColumn];
                }
                if (!in_array($row[$yColumn], $yCategories)) {
                    $yCategories[] = $row[$yColumn];
                }
                
                $data[] = [
                    array_search($row[$xColumn], $xCategories),
                    array_search($row[$yColumn], $yCategories),
                    (float)$row[$valueColumn]
                ];
            }

            return [
                'status' => 'success',
                'data' => $data,
                'xCategories' => $xCategories,
                'yCategories' => $yCategories
            ];
        } catch (Exception $e) {
            return ['error' => 'Heatmap processing failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Format data untuk waterfall chart
     * @param array $result Data hasil query
     * @param string $labelColumn Kolom label
     * @param string $valueColumn Kolom nilai
     * @return array
     */
    public function toWaterfallChart($result, $labelColumn, $valueColumn) {
        try {
            $data = [];
            $cumulative = 0;
            
            foreach ($result as $row) {
                $value = (float)$row[$valueColumn];
                $start = $cumulative;
                $cumulative += $value;
                
                $data[] = [
                    'label' => $row[$labelColumn],
                    'start' => $start,
                    'end' => $cumulative,
                    'value' => $value
                ];
            }

            return [
                'status' => 'success',
                'data' => $data,
                'total' => $cumulative
            ];
        } catch (Exception $e) {
            return ['error' => 'Waterfall chart processing failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * Method baru untuk set index hint
     * @param string $index Nama index
     * @return NgoreiFlot
     */
    public function useIndex($index) {
        $this->indexHint = $index;
        return $this;
    }

    public function getChunked($chunkSize = 1000) {
        try {
            $query = $this->buildQuery();
            $stmt = $this->mysqli->prepare($query['sql']);
            
            if (!empty($query['params'])) {
                $stmt->execute($query['params']);
            } else {
                $stmt->execute();
            }
            
            // Gunakan cursor untuk menghemat memori
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $results = [];
            $count = 0;
            
            while ($row = $stmt->fetch()) {
                $results[] = $row;
                $count++;
                
                if ($count >= $chunkSize) {
                    yield $results;
                    $results = [];
                    $count = 0;
                }
            }
            
            if (!empty($results)) {
                yield $results;
            }
            
            $this->resetQuery();
        } catch (PDOException $e) {
            return [
                'error' => 'Database error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function logQuery($sql, $params, $executionTime) {
        $this->queryLog[] = [
            'sql' => $sql,
            'params' => $params,
            'execution_time' => $executionTime,
            'timestamp' => microtime(true)
        ];
    }

    public function getQueryLog() {
        return $this->queryLog;
    }

    /**
     * Export data ke format CSV
     * @param array $result Data hasil query
     * @param string $filename Nama file output
     * @return void
     */
    public function toCSV($result, $filename = 'export.csv') {
        $f = fopen('php://memory', 'w');
        
        // Tulis header
        if (!empty($result)) {
            fputcsv($f, array_keys($result[0]));
        }
        
        // Tulis data
        foreach ($result as $row) {
            fputcsv($f, $row);
        }
        
        // Output file
        fseek($f, 0);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        fpassthru($f);
        fclose($f);
    }

    /**
     * Hitung total dari kolom
     * @param string $column Nama kolom
     * @return NgoreiFlot
     */
    public function sum($column) {
        $this->select[] = "SUM($column) as total_$column";
        return $this;
    }

    /**
     * Hitung rata-rata dari kolom
     * @param string $column Nama kolom
     * @return NgoreiFlot
     */
    public function avg($column) {
        $this->select[] = "AVG($column) as avg_$column";
        return $this;
    }

    /**
     * Set waktu expired cache
     * @param int $seconds Detik
     * @return NgoreiFlot
     */
    public function setCacheExpiry($seconds) {
        $this->cacheExpiry = $seconds;
        return $this;
    }

    /**
     * Simpan data ke cache
     * @param string $key Cache key
     * @param mixed $data Data yang akan disimpan
     */
    private function setCache($key, $data) {
        $cacheFile = sys_get_temp_dir() . '/ngoreiflot_' . md5($key);
        $cache = [
            'expires' => time() + $this->cacheExpiry,
            'data' => $data
        ];
        file_put_contents($cacheFile, serialize($cache));
    }

    /**
     * Ambil data dari cache
     * @param string $key Cache key
     * @return mixed|null
     */
    private function getCache($key) {
        $cacheFile = sys_get_temp_dir() . '/ngoreiflot_' . md5($key);
        if (file_exists($cacheFile)) {
            $cache = unserialize(file_get_contents($cacheFile));
            if ($cache['expires'] > time()) {
                return $cache['data'];
            }
            unlink($cacheFile);
        }
        return null;
    }

    /**
     * Eksekusi raw SQL query
     * @param string $sql Query SQL
     * @param array $params Parameter query
     * @return array
     */
    public function raw($sql, $params = []) {
        try {
            $stmt = $this->mysqli->prepare($sql);
            if (!empty($params)) {
                $stmt->execute($params);
            } else {
                $stmt->execute();
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [
                'error' => 'Database error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Validasi data sebelum diproses
     * @param array $result Data hasil query
     * @param array $required Kolom yang harus ada
     * @return array
     */
    protected function validateData($result, $required = []) {
        if (empty($result)) {
            return ['error' => 'Data kosong'];
        }
        
        if (!empty($required)) {
            $columns = array_keys($result[0]);
            $missing = array_diff($required, $columns);
            if (!empty($missing)) {
                return [
                    'error' => 'Kolom tidak ditemukan',
                    'missing' => $missing
                ];
            }
        }
        
        return ['status' => 'valid'];
    }

    /**
     * Format angka ke format rupiah
     * @param float $number Angka
     * @return string
     */
    public function toRupiah($number) {
        return 'Rp ' . number_format($number, 0, ',', '.');
    }

    /**
     * Format tanggal ke format Indonesia
     * @param string $date Tanggal
     * @return string
     */
    public function toIndonesianDate($date) {
        $bulan = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];
        
        $tgl = date('j', strtotime($date));
        $bln = $bulan[date('n', strtotime($date)) - 1];
        $thn = date('Y', strtotime($date));
        
        return "$tgl $bln $thn";
    }
}
?> 