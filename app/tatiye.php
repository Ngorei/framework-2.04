<?php
namespace app;
use PDO;
use PDOException;
use PDOStatement;
use Exception;
use app\NgoreiDb;
use Dotenv\Dotenv;
/**
 * Class Tatiye - Kelas utama untuk mengelola scanning direktori dan routing
 */
class tatiye {
    /**
     * Constructor kelas
     */
    private static $connection = null;
    private static $queryCache = [];
    public function __construct() {
        $this->host = HOST.'/';
        $this->directory = ROOT;
        $this->controllers = PUBLIC_DIR;
    }

    private static function getConnection() {
        $db=new NgoreiDb();
        return  $db->connPDO();
    }


    public static function fetch($tabel, $bin = '*', $where = '') {
        try {
            $cacheKey = md5($tabel . $bin . $where);
            
            if (isset(self::$queryCache[$cacheKey])) {
                return self::$queryCache[$cacheKey];
            }
            
            $stmt = self::setPDO($tabel, $bin, $where);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Cache hasil untuk query yang sama
            self::$queryCache[$cacheKey] = $result;
            
            return $result;
        } catch (PDOException $e) {
            error_log("Error in fetch(): " . $e->getMessage());
            throw new Exception("Database error occurred");
        }
    }

    public static function setPDO($tabel, $bin='*', $where='', $limit='LIMIT 100') {
        try {
            $conn = self::getConnection();
            $whereClause = '';
            
            if (!empty($where)) {
                $IDWH = explode(' ', $where);
                $whereClause = in_array($IDWH[0], ['JOIN', 'GROUP']) ? $where : "WHERE $where";
            }
            
            $limitClause = empty($limit) ? 'LIMIT 1' : $limit;
            $query = "SELECT $bin FROM $tabel $whereClause $limitClause";
            
            // Add query logging for performance monitoring
            $startTime = microtime(true);
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $endTime = microtime(true);
            
            // Log slow queries (>1 second)
            if (($endTime - $startTime) > 1) {
                error_log("Slow query detected: $query. Time: " . ($endTime - $startTime) . "s");
            }
            
            return $stmt;
        } catch (PDOException $e) {
            error_log("Error in setPDO(): " . $e->getMessage());
            throw new Exception("Database error occurred");
        }
    }
    
    // Clear cache method
    public static function clearCache() {
        self::$queryCache = [];
    }
    /**
     * Mengambil data dari tabel
     */
    /* and class Tokenesia */
    /**
     * Menginisialisasi dan mengelola scanning direktori
     * @param string $key Parameter kunci (tidak digunakan saat ini)
     * @return void
     */
    public static function index($key){
         $public_path = ROOT . '/package';
         if (APP_ENV=='development') {
            $files = self::scanDirectory($public_path,'true');
            // Simpan hasil ke file package.json
                $public_path_save = PACKAGE . 'package.json';
                // Periksa izin direktori
                if (!is_writable(dirname($public_path_save))) {
                    header('HTTP/1.1 500 Internal Server Error');
                    echo json_encode(['error' => 'Direktori tidak dapat ditulis']);
                    exit;
                }
                // Tangani exception dengan lebih spesifik
                try {
                    $json_content = json_encode($files, JSON_PRETTY_PRINT);
                    if ($json_content === false) {
                        throw new Exception('Gagal mengkonversi data ke JSON');
                    }
                    $write_result = file_put_contents($public_path_save, $json_content);
                    if ($write_result === false) {
                        throw new Exception('Gagal menulis ke file');
                    }
                } catch (Exception $e) {
                    header('HTTP/1.1 500 Internal Server Error');
                    echo json_encode(['error' => $e->getMessage()]);
                    exit;
                }
                $json_arr = self::rootDirectory(PUBLIC_DIR,'public/');
                self::generateSitemap($json_arr['sdk'], PUBLIC_DIR . '/sitemap.xml');
                $metadata = tatiye::generateMetadata($json_arr['sdk']);
                self::saveMetadataToJson($metadata, PUBLIC_DIR . 'properti.json');
  
         } else {
              $files = self::scanDirectory($public_path,'true');
              $aliasFile = $public_path . '/package.json';
              $content = file_get_contents($aliasFile);
              $json_arr = json_decode($content, true);
              header('Content-Type: application/json');
              echo json_encode($json_arr, JSON_PRETTY_PRINT);
         }
         
    }
    /*
    |--------------------------------------------------------------------------
    | Initializes getBuckets 
    |--------------------------------------------------------------------------
    | Develover Tatiye.Net 2022
    | @Date  
    */
    public static function getBuckets($key){
             $Exp[]=array(
                'ststus'              =>'ON',
                'package'             =>$Exp,
                'data'                =>$Exp,
                );
          return $Exp;
        
    }
    /* and class getBuckets */
    /*
    |--------------------------------------------------------------------------
    | Initializes StorageBuckets 
    |--------------------------------------------------------------------------
    | Develover Tatiye.Net 2022
    | @Date  
    */
    public static function StorageBuckets($data) {
        try {
            // Validasi input
            if (!is_array($data)) {
                throw new \Exception('Input harus berupa array');
            }

            if (!isset($data['endpoint']) || !isset($data['payload'])) {
                throw new \Exception('Data harus memiliki endpoint dan payload');
            }
            $apiUrl = self::env('PUBLIC_URL') . '/sdk/' . $data['endpoint'];
            // Log request
            error_log('StorageBuckets request ke: ' . $apiUrl);
            error_log('StorageBuckets payload: ' . (is_string($data['payload']) ? $data['payload'] : json_encode($data['payload'])));

            // Setup curl untuk request ke API
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => is_string($data['payload']) ? $data['payload'] : json_encode($data['payload']),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);

            // Eksekusi request
            $response = curl_exec($curl);
            $err = curl_error($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            
            curl_close($curl);

            if ($err) {
                throw new \Exception('Curl Error: ' . $err);
            }

            $responseData = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
            }

            return [
                'status' => 'success',
                'http_code' => $httpCode,
                'vid' => $data['vid'],
                'response' => $responseData,
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            error_log('StorageBuckets error: ' . $e->getMessage());
            throw $e;
        }
    }
    /* and class hello */


     public static function PageParser(?string $url): array {
        // Gunakan HOST sebagai base
        $base_url = HOST;
        
        // Validasi URL
        if (empty($url)) {
            return [];
        }

        // Parse URL dan ambil path
        $parsed_url = parse_url($url);
        $pathToUse = !empty($parsed_url['path']) ? $parsed_url['path'] : '';
        
        // Hapus base_url dari path jika ada
        $pathToUse = str_replace($base_url, '', $pathToUse);
        
        // Bersihkan path
        $parts = explode('/', trim($pathToUse, '/'));
        
        // Sanitasi setiap bagian
        $parts = array_map(function($part) {
            $part = str_replace('-', ' ', $part);
            return htmlspecialchars($part, ENT_QUOTES, 'UTF-8');
        }, $parts);
        
        // Buat array hasil
        $result = [];
        foreach ($parts as $index => $value) {
            $key = $index === 0 ? 'page' : 'page' . $index;
            $result[$key] = trim($value);
        }
        
        return $result;
    }


    /**
     * Melakukan scanning direktori secara rekursif
     * @param string $directory Path direktori yang akan di-scan
     * @param string $str Parameter untuk menentukan nilai api (true/false)
     * @return array Array hasil scanning ['sdk' => [], 'api' => []]
     */
    public static function scanDirectory($directory, $str='') {
        $result = [
            'sdk' => [],
            'api' => []
        ];
        $files = scandir($directory);
        foreach($files as $net => $file) {
            if ($file === '.' || $file === '..') continue;
            $full_path = $directory . '/' . $file;
            $normalized_path = str_replace('\\', '/', $full_path);
            
            if (is_file($full_path)) {
                // Debug: cek path file
                // error_log("Checking file: " . $normalized_path);
                
                // Pastikan file berada dalam subfolder dengan mengecek public/
                $parts = explode('package/', $normalized_path);
                if (count($parts) < 2 || !strpos($parts[1], '/')) {
                    // Skip file yang di root
                    continue;
                }
                
                $IDfile = explode('.', $file);
                $IDpath = $parts; // Gunakan parts yang sudah ada
                $IDpathfile = explode('.', $IDpath[1]);
                $key = $IDfile[0];
                $path = $IDpathfile[0];
                
                $pathparts = explode('/', $path);
                if (count($pathparts) == 2) {
                    $pathpreg = implode('_', $pathparts);
                } else {
                    $pathpreg = preg_replace("/^[^\/]+\//", "", $path);
                }
                
                // Hanya tambahkan jika benar-benar dalam folder
                if (count($pathparts) > 1) {
                    $result['sdk'][self::newKey(str_replace("/", "_", $pathpreg))] = $path;
                    $result['api'][self::newKey(str_replace("/", "_", $pathpreg))] = $str === 'true' ? true : false;
                }
            }
            if (is_dir($full_path)) {
                $subResult = self::scanDirectory($full_path, $str);
                $result['sdk'] = array_merge($result['sdk'], $subResult['sdk']);
                $result['api'] = array_merge($result['api'], $subResult['api']);
            }
        }
        return $result;
    }
    /**
     * Menghasilkan kunci unik berdasarkan string input
     * @param string $key String input untuk di-hash
     * @return string Format: XXXXX-XXXXX-XXXXX-XXXXX (hash MD5 yang diformat)
     */
     public static function newKey($key){
          $md5 = strtoupper(md5($key));
          $code[] = substr ($md5, 0, 5);
          $code[] = substr ($md5, 5, 5);
          $code[] = substr ($md5, 10, 5);
          $code[] = substr ($md5, 15, 5);
          $membcode = implode ("-", $code);
          return $membcode;
    }

    public static function siteproperti() {
        $file = PUBLIC_DIR . 'package.json';

        if (!file_exists($file)) {
            throw new Exception("File tidak ditemukan: " . $file);
        }

        $jsonData = file_get_contents($file);
        $data = json_decode($jsonData, true); 

        if (json_last_error() !== JSON_ERROR_NONE) {
            // throw new Exception("Error dalam decoding JSON: " . json_last_error_msg());
        }

        if (!isset($data)) {
            // throw new Exception("Key tidak ditemukan dalam data aset: " . $key);
        }

        return $data;
    }


    public static function assets($key) {
        $file = PUBLIC_DIR . 'package.json';

        if (!file_exists($file)) {
            throw new Exception("File tidak ditemukan: " . $file);
        }

        $jsonData = file_get_contents($file);
        $data = json_decode($jsonData, true); 

        if (json_last_error() !== JSON_ERROR_NONE) {
            // throw new Exception("Error dalam decoding JSON: " . json_last_error_msg());
        }

        if (!isset($data['assets'][$key])) {
            //throw new Exception("Key tidak ditemukan dalam data aset: " . $key);
        }

        return $data['assets'][$key];
    }


    
    /**
     * Membuat cookie persistent seperti Facebook
     * @param string $name Nama cookie
     * @param string $value Nilai cookie
     * @param bool $remember Opsi "Remember Me" (default false)
     * @param string $path Path cookie (default '/')
     * @return bool True jika berhasil, False jika gagal
     */
    public static function setAuthCookie($name, $value, $remember = false, $path = '/') {
        try {
            if ($remember) {
                // Jika "Remember Me" dicentang, cookie bertahan 1 tahun
                $expire = time() + (365 * 24 * 60 * 60); // 1 tahun
            } else {
                // Jika tidak, cookie bertahan 2 hari
                $expire = time() + (2 * 24 * 60 * 60); // 2 hari
            }

            // Generate token unik untuk keamanan
            $token = bin2hex(random_bytes(32));
            
            // Gabungkan value dengan token
            $secureValue = base64_encode(json_encode([
                'value' => $value,
                'token' => $token,
                'created' => time()
            ]));

            return setcookie($name, $secureValue, [
                'expires' => $expire,
                'path' => $path,
                'secure' => true,     
                'httponly' => true,   
                'samesite' => 'Strict'
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Mengambil dan memverifikasi auth cookie
     * @param string $name Nama cookie
     * @return mixed Array data cookie atau null jika invalid
     */
    public static function getAuthCookie($name) {
        if (!isset($_COOKIE[$name])) {
            return null;
        }

        try {
            $data = json_decode(base64_decode($_COOKIE[$name]), true);
            
            // Verifikasi struktur data
            if (!isset($data['value']) || !isset($data['token']) || !isset($data['created'])) {
                return null;
            }

            // Cek umur cookie (opsional)
            $age = time() - $data['created'];
            if ($age > (365 * 24 * 60 * 60)) { // Lebih dari 1 tahun
                self::deleteAuthCookie($name);
                return null;
            }

            // Verifikasi token
            $token = bin2hex($data['token']);
            $expectedToken = hash('sha256', $data['value'] . $data['created']);
            if ($token !== $expectedToken) {
                self::deleteAuthCookie($name);
                return null;
            }

            return $data;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Menghapus auth cookie
     * @param string $name Nama cookie
     * @return void
     */
    public static function deleteAuthCookie($name) {
        setcookie($name, '', [
            'expires' => time() - 3600,
            'path' => '/'
        ]);
    }

    /**
     * Kunci enkripsi default (sebaiknya simpan di config)
     * Ganti dengan kunci random yang kuat
     */
    private static $encryptionKey = 'your-secret-key-here';

    /**
     * Membuat encrypted cookie dengan kunci
     * @param string $name Nama cookie
     * @param mixed $value Nilai cookie
     * @param string $key Kunci enkripsi (opsional)
     * @param int $expire Waktu expire dalam detik (default 30 hari)
     * @return bool
     */
    public static function setEncryptedCookie($name, $value, $key = null, $expire = 2592000) {
        try {
            // Gunakan kunci yang diberikan atau default
            $encryptionKey = $key ?? self::$encryptionKey;
            
            // Generate IV (Initialization Vector)
            $iv = random_bytes(16);
            
            // Siapkan data untuk dienkripsi
            $data = [
                'value' => $value,
                'timestamp' => time(),
                'signature' => hash_hmac('sha256', serialize($value), $encryptionKey)
            ];
            
            // Enkripsi data
            $encrypted = openssl_encrypt(
                serialize($data),
                'AES-256-CBC',
                $encryptionKey,
                0,
                $iv
            );
            
            // Gabungkan IV dan data terenkripsi
            $secureValue = base64_encode($iv . $encrypted);
            
            return setcookie($name, $secureValue, [
                'expires' => time() + $expire,
                'path' => '/',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Mengambil dan mendekripsi cookie
     * @param string $name Nama cookie
     * @param string $key Kunci enkripsi (opsional)
     * @return mixed
     */
    public static function getEncryptedCookie($name, $key = null) {
        if (!isset($_COOKIE[$name])) {
            return null;
        }

        try {
            $encryptionKey = $key ?? self::$encryptionKey;
            
            // Decode base64
            $combined = base64_decode($_COOKIE[$name]);
            
            // Pisahkan IV dan data
            $iv = substr($combined, 0, 16);
            $encrypted = substr($combined, 16);
            
            // Dekripsi data
            $decrypted = openssl_decrypt(
                $encrypted,
                'AES-256-CBC',
                $encryptionKey,
                0,
                $iv
            );
            
            if ($decrypted === false) {
                return null;
            }
            
            $data = unserialize($decrypted);
            
            // Verifikasi signature
            $expectedSignature = hash_hmac('sha256', serialize($data['value']), $encryptionKey);
            if (!hash_equals($expectedSignature, $data['signature'])) {
                return null;
            }

            return $data['value'];
        } catch (Exception $e) {
            return null;
        }
    }
    public static function rootDirectory($directory, $str='', $limit = 1000) {
        $count = 0;
        $start_time = microtime(true);
        $memory_start = memory_get_usage();
        try {
            if (!is_readable($directory)) {
                throw new Exception("Direktori tidak dapat dibaca");
            }
            
            // Validasi direktori
            if (!is_dir($directory)) {
                return ['sdk' => [], 'error' => 'Direktori tidak valid'];
            }

            $result = [
                'sdk' => [],
            ];
            
            // Tambahkan error reporting
            error_reporting(E_ALL);
            ini_set('display_errors', 1);
            
            $files = scandir($directory);
            foreach($files as $net => $file) {
                if ($file === '.' || $file === '..') continue;
                $full_path = $directory . '/' . $file;
                $normalized_path = str_replace('\\', '/', $full_path);
                
                if (is_file($full_path)) {
                    // Ambil nama file dan path relatif dari package/
                    $parts = explode($str, $normalized_path);
                    if (count($parts) >= 2) {
                        $relativePath = $parts[1];
                        
                        // Hitung jumlah subfolder dengan menghitung karakter '/'
                        $subfolderCount = substr_count($relativePath, '/');
                        
                        // Ambil file yang berada di subfolder level 2 atau lebih
                        if ($subfolderCount >= 2) {
                            $folder = dirname($relativePath);
                            $filename = basename($relativePath);
                            $IDFile = explode('.',$filename);
                            
                            // Tambahkan perhitungan ukuran file
                            $filesize = filesize($full_path);
                            $formatted_size = $filesize < 1024 ? $filesize . ' B' : 
                                            ($filesize < 1048576 ? round($filesize/1024, 2) . ' KB' : 
                                            round($filesize/1048576, 2) . ' MB');
                            
                            // Normalisasi path menggunakan realpath dan DIRECTORY_SEPARATOR
                            $normalized_full_path = realpath($full_path);
                            if ($normalized_full_path !== false) {
                                $normalized_full_path = str_replace(
                                    [DIRECTORY_SEPARATOR, '//'], 
                                    '/', 
                                    $normalized_full_path
                                );
                            } else {
                                // Fallback jika realpath gagal
                                $normalized_full_path = str_replace(
                                    ['\\\\', '\\', '//'], 
                                    '/', 
                                    $directory . $relativePath
                                );
                            }
                            
                            // Modifikasi link berdasarkan title dan type
                            $title = $IDFile[0];
                            $type = strtolower($IDFile[1]);
                            $link = HOST.$folder;
                            
                            // Cek tipe file
                            $asset_types = ['css', 'js', 'json'];
                            if (in_array($type, $asset_types)) {
                                // Untuk asset files (CSS, JS, JSON), tambahkan ekstensi
                                $link .= '/'.$title.'.'.$type;
                            } else {
                                // Untuk file lain, gunakan logika sebelumnya
                                if (strtolower($title) !== 'index') {
                                    $link .= '/'.$title;
                                }
                            }
                            if ($title=='index') {
                                $folder2 = ltrim($folder, '/');
                                $output2 = str_replace('/', ' ', $folder2);
                               $nmTitel=$output2;
                            } else {
                               $nmTitel=$title;
                                // code...
                            }
                                $IDpublic=explode('public/',$normalized_full_path);


                            $filename = pathinfo($IDpublic[1], PATHINFO_FILENAME);
                            $dirname = dirname($IDpublic[1]);
                            $path = ($dirname === '.') ? $filename : $dirname . '/' . $filename;
                            $result['sdk'][] = [
                                'title' => $nmTitel,
                                'link' => $link,
                                'file' => $filename,
                                'type' => $type,
                                'folder' => $folder,
                                'full_path' =>$IDpublic[1],
                                'path' => $path,
                                'size' => $formatted_size,
                                'created_time' => date('Y-m-d H:i:s', filectime($full_path)),
                                'modified_time' => date('Y-m-d H:i:s', filemtime($full_path)),
                                'permissions' => substr(sprintf('%o', fileperms($full_path)), -4),
                                'mime_type' => mime_content_type($full_path)
                            ];
                        }
                    }
                }
                if (is_dir($full_path)) {
                    $subResult = self::rootDirectory($full_path, $str);
                    $result['sdk'] = array_merge($result['sdk'], $subResult['sdk']);
                   
                }
                if ($count++ >= $limit) {
                    break;
                }
            }
            $execution_time = microtime(true) - $start_time;
            $memory_usage = memory_get_usage() - $memory_start;
            
            error_log(sprintf(
                'Directory scan completed in %.2f seconds, using %.2f MB memory',
                $execution_time,
                $memory_usage / 1048576
            ));
            return $result;
        } catch (Exception $e) {
            return [
                'sdk' => [],
                'error' => $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }
        public static function generateSitemap($data, $outputPath = null) {
            try {
                // Gunakan namespace global untuk DOMDocument
                $dom = new \DOMDocument('1.0', 'UTF-8');
                $dom->preserveWhiteSpace = false;
                $dom->formatOutput = true;
                
                // Cek file yang sudah ada
                if ($outputPath !== null && file_exists($outputPath) && filesize($outputPath) > 0) {
                    $existingXml = @simplexml_load_file($outputPath);
                    if ($existingXml) {
                        $existingUrls = [];
                        foreach ($existingXml->url as $url) {
                            $existingUrls[] = (string)$url->loc;
                        }
                        
                        $newData = [];
                        foreach ($data as $item) {
                            if ($item['type'] === 'html' && !in_array($item['link'], $existingUrls)) {
                                $newData[] = $item;
                            }
                        }
                        
                        if (empty($newData)) {
                            return false;
                        }
                        
                        foreach ($newData as $item) {
                            $url = $existingXml->addChild('url');
                            $url->addChild('loc', $item['link']);
                            $url->addChild('lastmod', date('Y-m-d', strtotime($item['modified_time'])));
                            $url->addChild('changefreq', 'weekly');
                            $url->addChild('priority', '0.8');
                        }
                        
                        // Format XML dengan indentasi yang rapi
                        $dom->loadXML($existingXml->asXML());
                        $dom->save($outputPath);
                        return true;
                    }
                }
                
                // Jika file kosong atau tidak valid, buat file baru
                $urlset = $dom->createElement('urlset');
                $urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
                $dom->appendChild($urlset);
                
                foreach ($data as $item) {
                    if ($item['type'] === 'html') {
                        $url = $dom->createElement('url');
                        $loc = $dom->createElement('loc', $item['link']);
                        $lastmod = $dom->createElement('lastmod', date('Y-m-d', strtotime($item['modified_time'])));
                        $changefreq = $dom->createElement('changefreq', 'weekly');
                        $priority = $dom->createElement('priority', '0.8');
                        
                        $url->appendChild($loc);
                        $url->appendChild($lastmod);
                        $url->appendChild($changefreq);
                        $url->appendChild($priority);
                        $urlset->appendChild($url);
                    }
                }
                
                if ($outputPath !== null) {
                    $dom->save($outputPath);
                    return true;
                }
                
                return $dom->saveXML();
            } catch (\Exception $e) {
                error_log("Error generating sitemap: " . $e->getMessage());
                return false;
            }
        }

        public static function generateMetadata($sdkData) {
            $all_metadata = [];
            $existing_urls = []; // Array untuk tracking URL yang sudah ada
            
            foreach ($sdkData as $value) {
                if ($value['type'] == 'html') {
                    // Skip jika URL sudah ada
                    if (in_array($value['link'], $existing_urls)) {
                        continue;
                    }
                    
                    $metadata = [
                        'og_title' => $value['title'],
                        'og_type' => 'website', 
                        'og_url' => $value['link'],
                        'og_image' => !empty($value['image']) ? $value['image'] : '',
                        'og_description' => !empty($value['description']) ? $value['description'] : '',
                        'og_site_name' => APP_NAME,
                        'og_path_name' => $value['path'],
                        'og_path_full' => $value['full_path'],
                        'og_app_id' => ''
                    ];
                    
                    $existing_urls[] = $value['link']; // Tambahkan URL ke tracking
                    $all_metadata[] = $metadata;
                }
            }
            
            return $all_metadata;
        }

        public static function saveMetadataToJson($newMetadata, $filePath = null) {
            $existing_metadata = [];
            
            // Baca file JSON yang sudah ada jika ada
            if ($filePath && file_exists($filePath)) {
                $existing_json = file_get_contents($filePath);
                $existing_metadata = json_decode($existing_json, true) ?: [];
            }
            
            // Gabungkan data baru dengan yang ada, hindari duplikasi URL
            $final_metadata = $existing_metadata;
            $existing_urls = array_column($existing_metadata, 'og_url');
            
            foreach ($newMetadata as $metadata) {
                if (!in_array($metadata['og_url'], $existing_urls)) {
                    $final_metadata[] = $metadata;
                }
            }
            
            $json_output = json_encode($final_metadata, JSON_PRETTY_PRINT);
            
            if ($filePath) {
                file_put_contents($filePath, $json_output);
            }
            
            return $json_output;
        }
        public static function metaProperti() {
            $file = PUBLIC_DIR . 'properti.json';

            if (!file_exists($file)) {
                throw new Exception("File tidak ditemukan: " . $file);
            }

            $jsonData = file_get_contents($file);
            $data = json_decode($jsonData, true); 

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Error dalam decoding JSON: " . json_last_error_msg());
            }

            if (!isset($data)) {
                throw new Exception("Key tidak ditemukan dalam data aset: " . $key);
            }

            return $data;
        }

        public static function getMetaname($requestUrl){
            // Hapus trailing slash jika ada
            $requestUrl = rtrim($requestUrl, '/');
            
            // Tambahkan validasi URL
            if (!filter_var($requestUrl, FILTER_VALIDATE_URL)) {
                return ['error' => 'URL tidak valid'];
            }
            
            try {
                $meta_arr = tatiye::siteproperti('properti');
                if (!is_array($meta_arr)) {
                    $meta_arr = ['properti' => []];
                }
                
                $json_arr = tatiye::metaProperti('properti');
                
                // Ambil nilai default dari properti
                $default_favicon = isset($meta_arr['properti']['favicon']) ? $meta_arr['properti']['favicon'] : '';
                $default_title = isset($meta_arr['properti']['title']) ? $meta_arr['properti']['title'] : '';
                $default_icon = isset($meta_arr['properti']['icon']) ? $meta_arr['properti']['icon'] : '';
                $default_description = isset($meta_arr['properti']['description']) ? $meta_arr['properti']['description'] : '';
                
                // Pastikan $json_arr adalah array sebelum diproses
                if (!is_array($json_arr)) {
                    $json_arr = [];
                }
                
                // Filter data berdasarkan url (tanpa trailing slash)
                $filtered_arr = [];
                if (!empty($requestUrl) && !empty($json_arr)) {
                    foreach ($json_arr as $item) {
                        // Hapus trailing slash dari URL item juga
                        $itemUrl = rtrim($item['og_url'], '/');
                        if ($itemUrl === $requestUrl) {
                            if (empty($item['og_image'])) {
                                $item['og_image'] = HOST.'/img/'.$default_icon;
                            }
                            if (empty($item['og_description'])) {
                                $item['og_description'] = $default_description;
                            }
                            $item['og_favicon'] =  HOST.'/img/'.$default_favicon;
                            $filtered_arr = [$item];
                            break;
                        }
                    }
                    
                    // Jika tidak ada yang cocok, cek HOST
                    if (empty($filtered_arr)) {
                        foreach ($json_arr as $item) {
                            $itemUrl = rtrim($item['og_url'], '/');
                            $hostUrl = rtrim(HOST, '/');
                            if ($itemUrl === $hostUrl) {
                                if (empty($item['og_image'])) {
                                    $item['og_image'] = HOST.'/img/'.$default_icon;
                                }
                                if (empty($item['og_description'])) {
                                    $item['og_title'] = $default_title;
                                    $item['og_description'] = $default_description;
                                }
                                $item['og_favicon'] =  HOST.'/img/'.$default_favicon;
                                $filtered_arr = [$item];
                                break;
                            }
                        }
                    }
                }

                // Return default values if no matching URL found
                if (empty($filtered_arr)) {
                    return [
                        'og_title' => $default_title,
                        'og_description' => $default_description,
                        'og_image' => HOST.'/img/'.$default_icon,
                        'og_favicon' => HOST.'/img/'.$default_favicon,
                        'og_url' => $requestUrl
                    ];
                }

                return $filtered_arr[0];
            } catch (Exception $e) {
                return ['error' => 'Terjadi kesalahan saat mengambil data meta'];
            }
        }

    /**
     * Menghapus encrypted cookie
     * @param string $name Nama cookie
     * @return void
     */
    public static function deleteEncryptedCookie($name) {
        setcookie($name, '', [
            'expires' => time() - 3600,
            'path' => '/'
        ]);
    }

    public function processData($data) {
        // Implementasi logika pemrosesan data
        return [
            'processed' => true,
            'result' => $data
        ];
    }

  /*
  |--------------------------------------------------------------------------
  | Initializes title 
  |--------------------------------------------------------------------------
  | Develover Tatiye.Net 2022
  | @Date  
  */
  public static function getDirList($searchTitle, $default) {
    $json_arr = self::metaProperti();
    
    // Data default jika tidak ditemukan
    $defaultPath = array(
        'dir' => PUBLIC_DIR.$default.'.html'
    );
    
    // Normalisasi path untuk pencarian
    $searchPath = strtolower(trim($searchTitle, '/'));
    
    if (!empty($json_arr)) {
        foreach ($json_arr as $value) {
            if (strtolower($value['og_path_name']) === $searchPath) {
                return array(
                    'dir' => PUBLIC_DIR.$value['og_path_full']
                );
            }
        }
    }
    
    return $defaultPath;
}
  /* and class title */

    /**
     * Memuat dan mengelola environment variables dari file .env
     * @param string|null $key Kunci environment variable yang ingin diambil
     * @param mixed $default Nilai default jika key tidak ditemukan
     * @return mixed
     */
    public static function env($key = null, $default = null) {
        static $env = null;
        @define('BASEPATH', dirname(__DIR__));
        // Load .env file jika belum dimuat
        if ($env === null) {
            $envFile = BASEPATH . '/.env';
            if (!file_exists($envFile)) {
                throw new \Exception('File .env tidak ditemukan di: ' . $envFile);
            }

            $env = [];
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                // Skip komentar
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }

                list($name, $value) = explode('=', $line, 2) + [null, null];
                if (!empty($name)) {
                    $name = trim($name);
                    $value = trim($value);
                    
                    // Hapus quotes jika ada
                    $value = trim($value, '"\'');
                    
                    // Parse boolean values
                    if (strtolower($value) === 'true') $value = true;
                    if (strtolower($value) === 'false') $value = false;
                    if (strtolower($value) === 'null') $value = null;
                    
                    $env[$name] = $value;
                }
            }
        }

        // Return semua environment variables jika key null
        if ($key === null) {
            return $env;
        }

        // Return specific value atau default
        return isset($env[$key]) ? $env[$key] : $default;
    }
    /*
    |--------------------------------------------------------------------------
    | Initializes pathRequest 
    |--------------------------------------------------------------------------
    | Develover Tatiye.Net 2022
    | @Date  
    */
    public static function pathRequest($PUBLIC,$PACKAGE){
        $variable = self::rootDirectory($PUBLIC,'public/');
        foreach ($variable['sdk'] as $key => $value) {
            if ($value['type']=='html') {
               $SetFile=implode('/', array_slice(explode('/', $value['path']), 0, -1))."/-".$value['file'];
               $originalPath=$value['path'];
            } else {
               $originalPath=$value['full_path'];
               $SetFile=$value['full_path'];
            }
             $FolderEnd=implode('/', array_slice(explode('/', $value['path']), 0, -1));
            $Exp[]=array(
                'title'          => $value['file'],
                'type'           => $value['type'],
                'index'          => $FolderEnd.'/index.html',  
                'originalPath'   =>$originalPath,
                'modifiedPath'   => $SetFile,
                'path'          => $value['full_path'],
            );
        }


        // Simpan ke file JSON
        $jsonFile = $PACKAGE;
        file_put_contents($jsonFile, json_encode($Exp, JSON_PRETTY_PRINT));
    }
    /* and class pathRequest */
        public static function pathGetRequest() {
            $file = PACKAGE . '/properti.json';

            if (!file_exists($file)) {
                throw new Exception("File tidak ditemukan: " . $file);
            }

            $jsonData = file_get_contents($file);
            $data = json_decode($jsonData, true); 

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Error dalam decoding JSON: " . json_last_error_msg());
            }

            if (!isset($data)) {
                throw new Exception("Key tidak ditemukan dalam data aset: " . $key);
            }

            return $data;
        }

        public static function pathPayload() {
            $file = PACKAGE . '/package.json';

            if (!file_exists($file)) {
                throw new Exception("File tidak ditemukan: " . $file);
            }

            $jsonData = file_get_contents($file);
            $data = json_decode($jsonData, true); 

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("Error dalam decoding JSON: " . json_last_error_msg());
            }

            if (!isset($data)) {
                throw new Exception("Key tidak ditemukan dalam data aset: " . $key);
            }

            return $data;
        }
    /*
    |--------------------------------------------------------------------------
    | Initializes buildTree 
    |--------------------------------------------------------------------------
    | Develover Tatiye.Net 2022
    | @Date  
    */
    public static function buildTree(){
                $variable = self::pathPayload();
                $tree = [];
                foreach ($variable['sdk'] as $key => $path) {
                    $parts = explode('/', $path);
                    $current = &$tree;
                    
                    // Simpan key untuk node terakhir
                    $nodeKey = $key;
                    $lastPart = end($parts);
                    
                    foreach ($parts as $part) {
                        if (!isset($current[$part])) {
                            $current[$part] = [
                                '_meta' => []
                            ];
                        }
                        $current = &$current[$part];
                        
                        // Jika ini adalah bagian terakhir, simpan key
                        if ($part === $lastPart) {
                            $current['_meta'] = [
                                'key' => $nodeKey
                            ];
                        }
                    }
                }

                // Konversi ke format yang lebih terstruktur
                function buildTreeMenu($tree, $parent = '') {
                    $result = [];
                    $counter = 0;
                    foreach ($tree as $key => $children) {
                        if ($key === '_meta') continue; // Skip metadata
                        
                        $nodeId = empty($parent) ? $key : $parent . '/' . $key;
                        $item = [
                            'id' => $nodeId,
                            'text' => $key,
                            'type' => 'folder',
                
                        ];
                        
                        // Tambahkan key jika ada
                        if (isset($children['_meta']['key'])) {
                            $item['key'] = $children['_meta']['key'];
                            $item['type'] = 'php';

                        } else {
                            $item['key'] = str_replace('/', '_', $nodeId);
                            $item['type'] = 'folder';
                        }
                        
                        if (!empty($children) && $key !== '_meta') {
                            $childItems = buildTreeMenu($children, $item['id']);
                            if (!empty($childItems)) {
                                $item['children'] = $childItems;
                            }
                        }
                        
                        $result[] = $item;
                        $counter++;
                    }
                    return $result;
                }

                $treeMenu = buildTreeMenu($tree);
                return $treeMenu;
        
    }
    /* and class buildTree */
    /*
    |--------------------------------------------------------------------------
    | Initializes EnvAsJson 
    |--------------------------------------------------------------------------
    | Develover Tatiye.Net 2022
    | @Date  
    */
    public static function EnvAsJson(){
            $dotenv = Dotenv::createImmutable(BASEPATH);
            $dotenv->load();

            // Fungsi untuk membaca dan mengembalikan isi .env sebagai JSON
            function getEnvAsJson() {
                $envFile = BASEPATH . '/.env';
                $envContent = [];
                
                if (file_exists($envFile)) {
                    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                            list($key, $value) = explode('=', $line, 2);
                            $envContent[trim($key)] = trim($value);
                        }
                    }
                }
                
                // header('Content-Type: application/json');
                return $envContent;
            }
            return getEnvAsJson();
    }
    /* and class EnvAsJson */

    /**
     * Set atau update environment variable
     * @param string $key Kunci yang akan diset/update
     * @param mixed $value Nilai baru
     * @return bool
     */
    

}