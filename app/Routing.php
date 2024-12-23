<?php
namespace app;
use app\tatiye;

/**
 * Class Routing
 * Kelas untuk memindai dan mengindeks file public HTML
 * 
 * @package app
 * @author Ngorei
 * @version 4.0.2
 */
class Routing {
    /** @var array Cache untuk menyimpan hasil pemindaian */
    private static $cache = [];
    
    /** @var array Ekstensi file yang diizinkan */
    private $allowedExtensions = ['html', 'htm'];
    
    /** @var int Kedalaman maksimum pemindaian direktori */
    private $maxDepth = 5;
    
    /** @var int Kedalaman saat ini dalam pemindaian */
    private $currentDepth = 0;
    
    /** @var array Log untuk menyimpan pesan debug */
    private $logs = [];

    /**
     * Constructor dengan parameter konfigurasi
     * 
     * @param int $maxDepth Kedalaman maksimum pemindaian
     * @param array $extensions Array ekstensi file yang diizinkan
     */
    public function __construct($maxDepth = 5, $extensions = ['html', 'htm']) {
        $this->maxDepth = max(1, $maxDepth);
        $this->allowedExtensions = array_map('strtolower', $extensions);
    }

    /**
     * Membersihkan cache scanner
     * 
     * @return void
     */
    public static function clearCache() {
        self::$cache = [];
    }

    /**
     * Mendapatkan log pemindaian
     * 
     * @return array
     */
    public function getLogs() {
        return $this->logs;
    }

    /**
     * Scan direktori template untuk file HTML
     * @param string $directory Path direktori yang akan di scan
     * @return array Result dalam format terstruktur
     */
    public function scanDirectory($directory) {
        // Cek cache
        $cacheKey = md5($directory);
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        // Validasi direktori
        if (!$this->validateDirectory($directory)) {
            return ['status' => 'error', 'message' => 'Directory invalid or not accessible'];
        }

        // Struktur result yang sudah diperbaiki
        $result = [
             'status' => 'success',
             'page' => []
          
        ];

        try {
            $files = scandir($directory);
            foreach($files as $file) {
                if ($file === '.' || $file === '..') continue;
                
                $full_path = $directory . '/' . $file;
                $normalized_path = str_replace('\\', '/', $full_path);
                
                // Cek apakah file HTML
                if (is_file($full_path) && in_array(pathinfo($file, PATHINFO_EXTENSION), $this->allowedExtensions)) {
                    $this->processFile($normalized_path, $result);
                } elseif (is_dir($full_path)) {
                    $this->processDirectory($full_path, $result);
                }
            }

            self::$cache[$cacheKey] = $result;
            return $result;

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function validateDirectory($directory) {
        return is_dir($directory) && is_readable($directory);
    }

    private function processFile($normalized_path, &$result) {
        $pathparts = explode('/', $normalized_path);
        if (count($pathparts) <= 3) return;

        $IDpath = explode('public/', $normalized_path);
        if (!isset($IDpath[1])) return;

        $path = explode('.html', $IDpath[1])[0]; // Khusus untuk file HTML
        
        if (strpos($path, '/') !== false) {
            $pathpreg = str_replace("/", ".", $path);
            $pathpreg2 = explode("/", $path);
            
            if ($pathpreg2[1] === 'index') {
                $result['page'][$pathpreg] = HOST.'/'.$pathpreg2[0];
            } else {
                $result['page'][$pathpreg] = HOST.'/'.$path;
            }
        }
    }

    private function processDirectory($full_path, &$result) {
        $this->currentDepth++;
        
        if ($this->currentDepth <= $this->maxDepth) {
            $subScanner = new self();
            $subResult = $subScanner->scanDirectory($full_path);
            
            if ($subResult['status'] === 'success') {
                // Merge langsung ke level page yang benar
                $result['page'] = array_merge(
                $result['page'], 
                $subResult['page']
                );
            }
        }
        
        $this->currentDepth--;
    }
}



