<?php
namespace app;
use app\tatiye;
use app\Ngorei;
use app\Routing;
use app\NgoreiDetect;
/**
 * Class Autoload
 * Kelas untuk menangani autoloading dan serving file dalam aplikasi
 */
class Autoload {
    protected $packagePath;
    protected $PUBLIC_DIRPath;
    protected $allowedExtensions = ['php', 'html', 'htm', 'css', 'js'];
    protected $defaultFile = 'index.php';
    protected $folderAliases = [];
    protected $contentTypes = [
        'js' => 'application/javascript',
        'css' => 'text/css',
        'html' => 'text/html',
        'htm' => 'text/html',
        'php' => 'text/html'
    ];
    protected $config = [
        'middleware' => [
            'api' => [],
            'sdk' => []
        ]
    ];

    /**
     * Constructor
     * Menginisialisasi autoloader dan memulai penanganan request
     * Mengatur public path dan memuat alias folder
     */
    public function __construct() {
         session_start();
        // Bersihkan output buffer yang ada
        if (ob_get_level()) ob_end_clean();
        $this->packagePath = PACKAGE;
        $this->PUBLIC_DIRPath = PUBLIC_DIR;
        $this->loadFolderAliases();
        try {
            // Mulai buffer baru
            ob_start();
            $this->handleRequest();
        } catch (\Exception $e) {
            // Bersihkan buffer jika ada error
            if (ob_get_level()) ob_end_clean();
            logError($e->getMessage());
            $this->notPage();
        }
    }

    /**
     * Menangani request HTTP yang masuk
     * Mendapatkan path yang diminta dan mencari file yang sesuai
     * @throws \Exception jika file tidak ditemukan
     */
    protected function handleRequest($type = null, $forcedMethod = null) {
        $this->handleCORS($forcedMethod);
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            return;
        }
        
        $path = $this->getRequestPath();
        $pathParts = explode('/', $path);
        
        // Ubah pengecekan untuk worker - cek apakah path mengandung 'worker'
        if (strpos($path, 'worker') !== false) {
            $tatiyeNet = new Ngorei();
            $rawData = file_get_contents("php://input");
            $dataset = json_decode($rawData, true);
            
            // Bersihkan output buffer
            if (ob_get_level()) ob_end_clean();
            
            // Pastikan dataset tidak kosong
            if (!empty($dataset) && isset($dataset['brief'])) {
                $ID = explode('//', $dataset['brief']); 
                if (isset($ID[1])) {
                    $filePath = PUBLIC_DIR . $ID[1] . '.html';
                    // Proses dataset
                    $url = isset($dataset['pageparser']) ? $dataset['pageparser'] : '';
                    $URLParser = tatiye::PageParser(HOST . '/'.$url);
                    foreach (array_merge($dataset,$URLParser) as $page => $value) {
                        $tatiyeNet->val($page, $value);
                    }
                           

        
                     $tatiyeNet->setAssets(tatiye::assets('header'), 'header');
                     $tatiyeNet->setAssets(tatiye::assets('footer'), 'footer');
                    
                    echo $tatiyeNet->Worker($filePath);
                } else {
                    echo json_encode(['error' => 'Invalid brief format']);
                }
            } else {
                echo json_encode(['error' => 'Invalid or empty dataset']);
            }
            exit;
        }
        
        // Tambahkan pengecekan khusus untuk config
        if ($path === 'config' ) {
            return $this->handleConfigRequest();
        }

        // Tambahkan pengecekan khusus untuk config
        if ($path === 'handler' ) {
            return $this->handleConfigHandler();
        } 

        if ($path === 'phpmyadmin' ) {
            return $this->handleConfigMyadmin();
        } 
        // Tambahkan pengecekan untuk sitemap.xml dan robots.txt
        if ($path === 'sitemap.xml') {
            return $this->handleSitemap();
        }
        
        if ($path === 'robots.txt') {
            return $this->handleRobots();
        }
        
        error_log("Path: " . $path);
        error_log("PathParts: " . print_r($pathParts, true));
        
        if ($pathParts[0] === 'fonts') {
            return $this->handleFontRequest($path);
        }
        
        if ($pathParts[0] === 'img') {
            return $this->handleImageRequest($path);
        }
        
        try {
            // Kode yang sudah ada untuk menangani request lainnya
            if ($type !== null) {
                error_log("Using forced type: " . $type);
                error_log("Using forced method: " . $forcedMethod);
                
                $forcedMethod = strtoupper($forcedMethod);
                $_SERVER['REQUEST_METHOD'] = $forcedMethod;
                
                switch($type) {
                    case 'sdk':
                        return $this->handleSdkRequest($pathParts, $forcedMethod);
                    case 'api':
                        return $this->handleApiRequest($pathParts, $forcedMethod);
                    default:
                        return $this->handleDefaultRequest($path);
                }
            }
            
            if (!empty($pathParts[0])) {
                error_log("Using path type: " . $pathParts[0]);
                switch($pathParts[0]) {
                    case 'sdk':
                        return $this->handleSdkRequest($pathParts);
                    case 'api':
                        return $this->handleApiRequest($pathParts);
                    default:
                        return $this->handleDefaultRequest($path);
                }
            }
            
            return $this->handleDefaultRequest($path);
            
        } catch (\Exception $e) {
            error_log("Error in handleRequest: " . $e->getMessage());
            return $this->sendJsonError($e->getMessage());
        }
    }
    
    protected function handleDefaultRequest($path) {
        // Validasi path
        $path = trim($path, '/');
        
        // Tambahkan pengecekan untuk path 'logout'
        if ($path === 'logout') {
            // Hapus session
            if (isset($_SESSION['userid'])) {
                unset($_SESSION['userid']);
                session_destroy();
            }
            // Redirect ke homepage
            header('Location: ' . HOST);
            exit;
        }
      
        // Ubah pengecekan untuk path 'user'
        if ($path === 'user') {
            if (!isset($_SESSION['userid'])) {
                // Redirect ke homepage jika tidak ada session
                header('Location: ' . HOST);
                exit;
            }
            // Ubah path untuk mencari file di folder user
            $userIndexPath = PUBLIC_DIR . 'user/index.html';
            //if (file_exists($userIndexPath)) {
                return $this->template($userIndexPath);
            //}
        }
        
        // Buat cache key berdasarkan path
        $cacheKey = 'path_resolution_' . md5($path);
        
        // Coba ambil dari cache
        $cachedPath = $this->getPathFromCache($cacheKey);
        if ($cachedPath !== false) {
            return $this->template($cachedPath);
        }
        
        // Jika path kosong, tampilkan index.html
        if (empty($path)) {
            $resolvedPath = PUBLIC_DIR . 'index.html';
            $this->savePathToCache($cacheKey, $resolvedPath);
            return $this->template($resolvedPath);
        }
        
        // Khusus untuk file CSS dan JS
        if (preg_match('/\.(js|css)$/', $path)) {
            return $this->handleCssJsRequest($path);
        }
        
        // Split path menjadi array
        $pathParts = array_filter(explode('/', $path));
        
        // Cek apakah ada bagian path yang dimulai dengan "-" 
        foreach ($pathParts as $index => $part) {
            if (strpos($part, '-') === 0) {
                // Jika ditemukan "-", ambil path sampai folder sebelumnya
                $basePath = implode('/', array_slice($pathParts, 0, $index));
                $folderPath = PUBLIC_DIR . $basePath;
                
                // Cek keberadaan index.html di folder tersebut menggunakan cache
                $indexPath = $folderPath . '/index.html';
                if ($this->pathExists($indexPath)) {
                    $this->savePathToCache($cacheKey, $indexPath);
                    return $this->template($indexPath);
                }
                break;
            }
        }
        
        // Coba cari file dengan ekstensi html di path lengkap
        $fullPath = PUBLIC_DIR . $path . '.html';
        if ($this->pathExists($fullPath)) {
            $this->savePathToCache($cacheKey, $fullPath);
            return $this->template($fullPath);
        }
        
        // Jika file tidak ditemukan, cek index.html di folder yang sama
        $folderPath = PUBLIC_DIR . $path;
        if ($this->isDirectory($folderPath)) {
            $indexPath = $folderPath . '/index.html';
            if ($this->pathExists($indexPath)) {
                $this->savePathToCache($cacheKey, $indexPath);
                return $this->template($indexPath);
            }
        }
        
        // Jika masih tidak ditemukan, coba cari index.html di folder parent
        $parentFolder = dirname($folderPath);
        if ($parentFolder && $parentFolder != PUBLIC_DIR) {
            $parentIndex = $parentFolder . '/index.html';
            if ($this->pathExists($parentIndex)) {
                $this->savePathToCache($cacheKey, $parentIndex);
                return $this->template($parentIndex);
            }
        }
        
        return $this->notPage();
    }
    
    // Tambahkan method baru untuk menangani file CSS/JS
    protected function handleCssJsRequest($path) {
        // Tambahkan penanganan khusus untuk CSS
        if (strpos($path, 'doc/') === 0) {
            // Untuk file di folder doc, gunakan path lengkap
            $fullPath = PUBLIC_DIR . $path;
            if (file_exists($fullPath)) {
                return $this->serveFile($fullPath);
            }
        }
        
        // Cek lokasi lain seperti sebelumnya
        $locations = [
            PUBLIC_DIR . $path,
            PUBLIC_DIR . 'css/' . basename($path),
            PUBLIC_DIR . 'js/' . basename($path),
            ASSET . $path,
            ASSET . 'css/' . basename($path),
            ASSET . 'js/' . basename($path),
        ];
        
        foreach ($locations as $location) {
            if (file_exists($location)) {
                return $this->serveFile($location);
            }
        }
        
        return $this->notPage();
    }
    
    protected function handleSdkRequest($pathParts, $forcedMethod = null) {
        // Hapus 'sdk' dari path
        array_shift($pathParts);
        
        // Cek method yang digunakan
        $method = $forcedMethod ?? strtolower($_SERVER['REQUEST_METHOD']);
        
        // Validasi hanya menerima POST
        if ($method !== 'post') {
            return $this->sendJsonError('SDK hanya mendukung method POST', 405);
        }
        
        $service = $pathParts[0] ?? '';
        
        // Debug log yang lebih detail
        // error_log("SDK Request - Service: " . $service);
        // error_log("Available SDK services: " . print_r($this->folderAliases['sdk'] ?? [], true));
        
        // Validasi service harus ada
        if (empty($service)) {
            return $this->sendJsonError('Service parameter diperlukan');
        }
        
        // Validasi service harus terdaftar di package.json
        if (!isset($this->folderAliases['sdk'][$service])) {
            return $this->sendJsonError("Service SDK '$service' tidak ditemukan");
        }
        
        return $this->routeRequest('sdk', $service, $method);
    }
    
    protected function handleApiRequest($pathParts, $forcedMethod = null) {
        // Hapus 'api' dari path
        array_shift($pathParts);
        
        $service = $pathParts[0] ?? '';
        $method = $forcedMethod ?? strtolower($_SERVER['REQUEST_METHOD']);
        
        // Validasi service
        if (empty($service)) {
            return $this->sendJsonError('Service parameter diperlukan');
        }
        
        // Validasi service harus terdaftar di package.json
        if (!isset($this->folderAliases['api'][$service])) {
            return $this->sendJsonError("Service API '$service' tidak ditemukan");
        }
        
        return $this->routeRequest('api', $service, $method);
    }
    
    protected function routeRequest($type, $service, $method) {
        try {
            // Validasi service
            if (!isset($this->folderAliases[$type][$service])) {
                throw new \Exception("Service tidak ditemukan: $service (type: $type)");
            }
            
            // Cek jika ini adalah request API dan nilai service adalah true
            if ($type === 'api' && $this->folderAliases['api'][$service] === true) {
                // Gunakan path dari SDK untuk service yang sama
                if (!isset($this->folderAliases['sdk'][$service])) {
                    throw new \Exception("Service SDK tidak ditemukan untuk API: $service");
                }
                $servicePath = $this->folderAliases['sdk'][$service];
            } else {
                $servicePath = $this->folderAliases[$type][$service];
            }
            
            error_log("Service Path yang digunakan: " . $servicePath);
            
            // Bangun path ke file handler
            $handlerPath = $this->packagePath . '/' . $servicePath . '.php';
            
            error_log("Mencoba mengakses handler di: " . $handlerPath);
            
            if (!file_exists($handlerPath)) {
                throw new \Exception("File handler tidak ditemukan untuk service: $service");
            }
            
            // Set header
            header('Content-Type: application/json');
            
            // Tambahkan context
            $_REQUEST['_context'] = [
                'type' => $type,
                'service' => $service,
                'method' => $method
            ];
            
            // Eksekusi handler
            ob_start();
            $response = include $handlerPath;
            ob_end_clean();
            return $this->sendJsonResponse($response);
            
        } catch (\Exception $e) {
            error_log("Error dalam routeRequest: " . $e->getMessage());
            return $this->sendJsonError($e->getMessage());
        }
    }
    
    protected function sendJsonResponse($data) {
        // Bersihkan output buffer
        if (ob_get_level()) ob_end_clean();
        // Set header
        header('Content-Type: application/json');
        $response = [
            'status' => 'success',
            'data' => $data
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        exit;
    }
    
    protected function sendJsonError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'status' => 'error',
            'message' => $message,
            'debug' => [
                'request_method' => $_SERVER['REQUEST_METHOD'],
                'request_uri' => $_SERVER['REQUEST_URI'],
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
            ]
        ], JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Mendapatkan path yang diminta dari URL
     * Membersihkan dan memfilter URL untuk keamanan
     * @return string Path yang telah dibersihkan atau file default
     */
    protected function getRequestPath() {
        // Cek beberapa kemungkinan sumber URL
        $url = '';
        
        // Cek dari $_GET['url'] 
        if (isset($_GET['url'])) {
            $url = $_GET['url'];
        }
        // Cek dari REQUEST_URI sebagai fallback
        else if (isset($_SERVER['REQUEST_URI'])) {
            $url = trim($_SERVER['REQUEST_URI'], '/');
            // Hapus query string jika ada
            $url = explode('?', $url)[0];
        }

        // Bersihkan URL
        $path = filter_var(rtrim($url, '/'), FILTER_SANITIZE_URL);
        
        // Kembalikan path yang dibersihkan atau file default
        return $path ?: $this->defaultFile;
    }

    /**
     * Mencari file berdasarkan path yang diminta
     * Memeriksa alias folder, ekstensi yang diizinkan, dan file default
     * @param string $path Path file yang dicari
     * @return string|false Path lengkap file jika ditemukan, false jika tidak
     */
    protected function findFile($path) {
        // Cek dan ganti alias folder jika ada
        $pathParts = explode('/', $path);
        if (!empty($pathParts[0]) && isset($this->folderAliases[$pathParts[0]])) {
            $pathParts[0] = $this->folderAliases[$pathParts[0]];
            $path = implode('/', $pathParts);
        }

        // Cek apakah file langsung ada
        $directPath = $this->packagePath . '/' . $path;
        if (is_file($directPath) && $this->isAllowedFile($directPath)) {
            return $directPath;
        }

        // Cek dengan berbagai ekstensi
        foreach ($this->allowedExtensions as $ext) {
            $filePath = $this->packagePath . '/' . $path . '.' . $ext;
            if (is_file($filePath)) {
                return $filePath;
            }
        }

        // Cek apakah ini adalah direktori dan cari file default
        $dirPath = $this->packagePath . '/' . $path;
        if (is_dir($dirPath)) {
            $defaultPath = $dirPath . '/' . $this->defaultFile;
            if (is_file($defaultPath)) {
                return $defaultPath;
            }
        }

        return false;
    }

    /**
     * Mengirim file ke client
     * Mengatur header content-type yang sesuai dan menangani caching
     * @param string $filePath Path lengkap ke file yang akan dikirim
     */
    protected function serveFile($filePath) {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        
        // Bersihkan output buffer
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set content type
        $contentTypes = [
            'js' => 'application/javascript; charset=UTF-8',
            'css' => 'text/css; charset=UTF-8'
        ];
        
        if (isset($contentTypes[$ext])) {
            header('Content-Type: ' . $contentTypes[$ext]);
            
            // Header untuk caching
            $etag = md5_file($filePath);
            header('ETag: "' . $etag . '"');
            header('Cache-Control: public, max-age=31536000');
            
            // Support untuk resume download
            header('Accept-Ranges: bytes');
            
            $filesize = filesize($filePath);
            $offset = 0;
            $length = $filesize;

            // Tangani request range
            if (isset($_SERVER['HTTP_RANGE'])) {
                preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $_SERVER['HTTP_RANGE'], $matches);
                $offset = intval($matches[1]);
                
                if (!empty($matches[2])) {
                    $length = intval($matches[2]) - $offset + 1;
                } else {
                    $length = $filesize - $offset;
                }

                header('HTTP/1.1 206 Partial Content');
                header(sprintf('Content-Range: bytes %d-%d/%d', 
                    $offset, 
                    $offset + $length - 1, 
                    $filesize
                ));
            }

            header('Content-Length: ' . $length);
            
            // Cek if-none-match
            if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && 
                trim($_SERVER['HTTP_IF_NONE_MATCH']) === '"' . $etag . '"') {
                header('HTTP/1.1 304 Not Modified');
                exit;
            }

            // Streaming dengan buffer kecil
            if ($fp = fopen($filePath, 'rb')) {
                // Pindah ke offset yang diminta
                if ($offset > 0) {
                    fseek($fp, $offset);
                }
                
                // Ukuran chunk untuk streaming (8KB)
                $chunkSize = 8192;
                $bytesRemaining = $length;
                
                // Nonaktifkan kompresi
                if (function_exists('apache_setenv')) {
                    @apache_setenv('no-gzip', 1);
                }
                @ini_set('zlib.output_compression', 0);
                @ini_set('implicit_flush', 1);
                
                while (!feof($fp) && $bytesRemaining > 0 && connection_status() == 0) {
                    $readSize = min($chunkSize, $bytesRemaining);
                    $chunk = fread($fp, $readSize);
                    
                    if ($chunk === false) {
                        break;
                    }
                    
                    echo $chunk;
                    flush();
                    $bytesRemaining -= strlen($chunk);
                    
                    // Berikan kesempatan proses lain berjalan
                    if (function_exists('usleep')) {
                        usleep(100);
                    }
                }
                
                fclose($fp);
            } else {
                error_log("Gagal membuka file: " . $filePath);
                return $this->notPage();
            }
        }
        exit;
    }

    protected function template($filePath) {

        // tatiye::index(true);
        if (!isset($_COOKIE['VID']) || !preg_match('/^VID_[a-f0-9]+_\d+$/', $_COOKIE['VID'])) {
            $unique_id = 'VID_' . uniqid() . '_' . time();
            setcookie('VID', $unique_id, time() + (86400 * 30), '/');
            // setcookie('HOST', HOST, time() + (86400 * 30), '/');  
        }
      
        $tatiyeNet = new Ngorei();        
        // Ambil URL dengan aman
        $url = isset($_GET['url']) ? $_GET['url'] : '';
        $URLParser = tatiye::PageParser(HOST . '/'.$url);
        $getMeta = tatiye::getMetaname(HOST . ($url ? '/' . $url : ''));
        
        foreach(array_merge($URLParser, $getMeta) as $page => $value) {
            $tatiyeNet->val($page, $value);
        }
        
        $indexOn = [
            'sitename'      => APP_NAME,
            'version'       => VERSION,
            'userid'        =>isset($_SESSION['userid']) ? $_SESSION['userid'] : '',
            'home'          => HOST,
            'link'          => HOST,
            'domain'        => HOST,
            'qrcode'        => isset($_COOKIE['VID']) ? $_COOKIE['VID'] : '',
        ]; 
        
        foreach ($indexOn as $page => $value) {
            $tatiyeNet->val($page, $value);
        }

        $tatiyeNet->addSpecialVariable("link", HOST . "/");
        $tatiyeNet->addSpecialVariable("img", HOST . "/img/");
        $tatiyeNet->includeTemplate('require', PUBLIC_DIR); 
        $tatiyeNet->templateRouting('Routing', PUBLIC_DIR);
        $tatiyeNet->setPath($url);
        $tatiyeNet->setAssets(tatiye::assets('header'), 'header');
        $tatiyeNet->setAssets(tatiye::assets('footer'), 'footer');
        $tatiyeNet->setAssetHost(HOST);
        if (!empty(MOBILE_DETECT)) {
            $deviceType = NgoreiDetect::deviceType();
            $templateFile = '';
            
            switch($deviceType) {
                case 'mobile':
                    $templateFile = ROOT_PUBLIC."/mobile/index.html";
                    break;
                case 'tablet':
                    $templateFile = ROOT_PUBLIC."/tablet/index.html";
                    break;
                default:
                    $templateFile = $filePath;
            }
            
            // Cek keberadaan file template
            if (!file_exists($templateFile)) {
                $templateFile = $filePath; // Fallback ke desktop version
            }
            
            echo $tatiyeNet->SDK($templateFile);
        } else {
            echo $tatiyeNet->SDK($filePath);
        }
        
    }
    /**
     * Memeriksa apakah file diizinkan berdasarkan ekstensinya
     * @param string $filePath Path file yang akan diperiksa
     * @return bool True jika file diizinkan, false jika tidak
     */
    protected function isAllowedFile($filePath) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        return in_array($ext, $this->allowedExtensions);
    }

    /**
     * Menampilkan halaman 404 Not Found
     * Mengatur header HTTP dan menampilkan halaman error
     */
    protected function notPage() {
        if (!headers_sent()) {
            header("HTTP/1.0 404 Not Found");
        }
        include ROOT . '/404.html';
        exit();
    }

    /**
     * Memuat alias folder dari file konfigurasi
     * Membaca dan memparse file package.json untuk alias folder
     */
    protected function loadFolderAliases() {
        $aliasFile = $this->packagePath . '/package.json';
        if (file_exists($aliasFile)) {
            $content = file_get_contents($aliasFile);
            // Debug content
            error_log("Raw package.json content: " . $content);
            // Validate JSON
            $aliases = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $errorMsg = 'JSON Error: ' . json_last_error_msg();
                error_log($errorMsg);
                logError($errorMsg);
                $this->folderAliases = [];
                return;
            }
            $this->folderAliases = $aliases;
            error_log("Successfully loaded aliases: " . print_r($this->folderAliases, true));
        } else {
            error_log("package.json not found at: " . $aliasFile);
            $this->folderAliases = [];
        }
    }
    /**
     * Menambahkan alias folder baru
     * @param string $alias Nama alias
     * @param string $target Path target yang sebenarnya
     */
    public function addFolderAlias($alias, $target) {
        $this->folderAliases[$alias] = $target;
        $this->saveFolderAliases();
    }

    /**
     * Menghapus alias folder yang ada
     * @param string $alias Nama alias yang akan dihapus
     */
    public function removeFolderAlias($alias) {
        if (isset($this->folderAliases[$alias])) {
            unset($this->folderAliases[$alias]);
            $this->saveFolderAliases();
        }
    }

    /**
     * Menyimpan konfigurasi alias folder ke file
     * Menyimpan dalam format JSON dengan format yang mudah dibaca
     */
    protected function saveFolderAliases() {
         $aliasFile = $this->packagePath . '/package.json';
        $jsonData = json_encode($this->folderAliases, JSON_PRETTY_PRINT);
        
        if (!is_dir(dirname($aliasFile))) {
            mkdir(dirname($aliasFile), 0755, true);
        }
        
        file_put_contents($aliasFile, $jsonData, LOCK_EX);
    }

      protected function handleCORS($forcedMethod = null) {
        // Ubah path file konfigurasi
        $configFile = PACKAGE . '/ngorei.config'; 
        
        // Pastikan file config ada
        if (!file_exists($configFile)) {
            error_log("File konfigurasi CORS tidak ditemukan: " . $configFile);
            return;
        }

        // Baca dan parse file JSON dengan penanganan error
        try {
            $data = file_get_contents($configFile);
            if ($data === false) {
                throw new \Exception("Gagal membaca file konfigurasi");
            }
            
            $config = json_decode($data, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Format JSON tidak valid: " . json_last_error_msg());
            }
            
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            
            // Pastikan config dan allowed_origins ada
            if (!isset($config['allowed_origins']) || !is_array($config['allowed_origins'])) {
                throw new \Exception("Konfigurasi CORS tidak valid");
            }
            
            if (in_array($origin, $config['allowed_origins'], true)) {
                header('Access-Control-Allow-Origin: ' . $origin);
                header('Access-Control-Allow-Credentials: true');
                
                if (strpos($_SERVER['REQUEST_URI'], '/sdk/') === 0) {
                    header('Access-Control-Allow-Methods: POST, OPTIONS');
                } else {
                    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
                }
                
                header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
                header('Access-Control-Max-Age: 3600');
            } else {
                $this->log('WARNING', 'Unauthorized CORS attempt', ['origin' => $origin]);
                return;
            }
            
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                http_response_code(200);
                exit(0);
            }
        } catch (\Exception $e) {
            error_log("Error dalam handleCORS: " . $e->getMessage());
            return;
        }
    }

    // Contoh implementasi middleware
    protected function applyMiddleware($type, $service) {
        $middlewares = [
            'auth' => function() {
                // Cek autentikasi
                if (!isset($_SESSION['user'])) {
                    throw new \Exception('Unauthorized access');
                }
            },
            'rateLimit' => function() {
                // Implementasi rate limiting
            }
        ];
        
        // Eksekusi middleware berdasarkan konfigurasi
        foreach ($this->config['middleware'][$type][$service] as $middleware) {
            $middlewares[$middleware]();
        }
    }

    protected function cacheResponse($key, $data, $ttl = 3600) {
        $cache = [
            'data' => $data,
            'expires' => time() + $ttl
        ];
        // Simpan ke file cache atau Redis/Memcached
    }

    protected function log($level, $message, $context = []) {
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'],
            'request_id' => uniqid(),
            'context' => $context
        ];
        // Log ke file/database
    }

    protected function handleImageRequest($path) {
        // Debug log
        error_log("BASEPATH value: " . BASEPATH);
        
        // Hapus 'img/' dari awal path
        $imagePath = preg_replace('/^img\//', '', $path);
        error_log("Requested image path: " . $imagePath);
        
        // Cari file di assets dan subfoldernya
        $assetsPath = $this->findImageInDirectory(BASEPATH . '/assets', $imagePath);
        if ($assetsPath) {
            error_log("Image found in assets: " . $assetsPath);
            return $this->serveImage($assetsPath);
        }
        
        // Jika tidak ditemukan di assets, cari di drive dan subfoldernya
        $drivePath = $this->findImageInDirectory(BASEPATH . '/uploads/img/', $imagePath);
        if ($drivePath) {
            error_log("Image found in drive: " . $drivePath);
            return $this->serveImage($drivePath);
        }
        
        error_log("Image not found in any location. Showing 404.");
        return $this->handleImageNotFound();
    }

    protected function findImageInDirectory($baseDir, $targetFile) {
        error_log("Searching for {$targetFile} in {$baseDir}");
        
        // Cek file langsung di root folder
        $directPath = $baseDir . '/' . $targetFile;
        if (file_exists($directPath) && $this->isValidImage($directPath)) {
            error_log("Found file directly: " . $directPath);
            return $directPath;
        }
        
        // Cari di semua subfolder
        $iterator = new \RecursiveDirectoryIterator($baseDir);
        $iterator = new \RecursiveIteratorIterator($iterator);
        
        foreach ($iterator as $file) {
            // Skip . dan ..
            if ($file->isDir()) continue;
            
            // Bandingkan nama file
            if (strtolower($file->getFilename()) === strtolower($targetFile)) {
                $fullPath = $file->getPathname();
                if ($this->isValidImage($fullPath)) {
                    error_log("Found file in subfolder: " . $fullPath);
                    return $fullPath;
                }
            }
        }
        
        error_log("File not found in {$baseDir} or its subfolders");
        return false;
    }

    protected function isValidImage($path) {
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $isValid = in_array($extension, $allowedExtensions);
        error_log("Checking if valid image: {$path} - " . ($isValid ? 'Yes' : 'No'));
        return $isValid;
    }

    protected function serveImage($path) {
        if (!file_exists($path)) {
            error_log("Error: File does not exist: " . $path);
            return $this->handleImageNotFound();
        }
        
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $contentTypes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
            'webp' => 'image/webp'
        ];
        
        // Bersihkan output buffer
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers
        header('Content-Type: ' . $contentTypes[$extension]);
        header('Accept-Ranges: bytes');
        
        $filesize = filesize($path);
        $offset = 0;
        $length = $filesize;

        // Handle Range request
        if (isset($_SERVER['HTTP_RANGE'])) {
            if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $_SERVER['HTTP_RANGE'], $matches)) {
                $offset = intval($matches[1]);
                if (!empty($matches[2])) {
                    $length = intval($matches[2]) - $offset + 1;
                } else {
                    $length = $filesize - $offset;
                }

                header('HTTP/1.1 206 Partial Content');
                header(sprintf('Content-Range: bytes %d-%d/%d', $offset, $offset + $length - 1, $filesize));
            }
        }

        header('Content-Length: ' . $length);
        header('Cache-Control: public, max-age=31536000');
        
        // Streaming dengan buffer kecil
        if ($fp = fopen($path, 'rb')) {
            if ($offset > 0) {
                fseek($fp, $offset);
            }
            
            $chunkSize = 8192;
            $bytesRemaining = $length;
            
            if (function_exists('apache_setenv')) {
                @apache_setenv('no-gzip', 1);
            }
            @ini_set('zlib.output_compression', 0);
            @ini_set('implicit_flush', 1);
            
            while (!feof($fp) && $bytesRemaining > 0 && connection_status() == 0) {
                $readSize = min($chunkSize, $bytesRemaining);
                $chunk = fread($fp, $readSize);
                if ($chunk === false) {
                    break;
                }
                echo $chunk;
                flush();
                $bytesRemaining -= strlen($chunk);
                
                if (function_exists('usleep')) {
                    usleep(100);
                }
            }
            fclose($fp);
            exit;
        } else {
            error_log("Error: Could not open file: " . $path);
            return $this->handleImageNotFound();
        }
    }

    protected function handleImageNotFound() {
        error_log("Handling image not found");
        
        $defaultImage = BASEPATH . '/assets/default/no-image.png';
        
        if (file_exists($defaultImage)) {
            error_log("Serving default image");
            return $this->serveImage($defaultImage);
        }
        
        error_log("No default image found, sending 404");
        if (ob_get_level()) ob_end_clean();
        
        header('HTTP/1.0 404 Not Found');
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'Gambar tidak ditemukan'
        ]);
        exit;
    }

    protected function handleFontRequest($path) {
        // Hapus 'fonts/' dari awal path
        $fontPath = preg_replace('/^fonts\//', '', $path);
        
        // Lokasi yang mungkin untuk file font
        $locations = [
            PUBLIC_DIR . 'fonts/' . $fontPath,
            ASSET . 'fonts/' . $fontPath,
            BASEPATH . '/assets/fonts/' . $fontPath
        ];
        
        foreach ($locations as $location) {
            if (file_exists($location)) {
                return $this->serveFont($location);
            }
        }
        
        // Jika font tidak ditemukan
        header('HTTP/1.0 404 Not Found');
        exit;
    }

    protected function serveFont($path) {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        
        // Content types untuk berbagai format font
        $contentTypes = [
            'woff2' => 'font/woff2',
            'woff' => 'font/woff',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'otf' => 'font/otf'
        ];
        
        // Bersihkan output buffer
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers
        if (isset($contentTypes[$extension])) {
            header('Content-Type: ' . $contentTypes[$extension]);
        }
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=31536000'); // Cache 1 tahun
        
        // Kirim file
        readfile($path);
        exit;
    }

    // Tambahkan method baru untuk sitemap
    protected function handleSitemap() {
        $sitemapFile = PUBLIC_DIR . 'sitemap.xml';
        $defaultSitemap = PUBLIC_DIR . 'sitemap.xml';
        
        // Cek file sitemap di beberapa lokasi
        if (file_exists($sitemapFile)) {
            $this->serveSitemap($sitemapFile);
        } elseif (file_exists($defaultSitemap)) {
            $this->serveSitemap($defaultSitemap);
        } else {
            // Generate sitemap dinamis jika file tidak ada
            // $this->generateSitemap();
        }
    }

    protected function serveSitemap($path) {
        if (ob_get_level()) ob_end_clean();
        
        header('Content-Type: application/xml; charset=UTF-8');
        header('Content-Length: ' . filesize($path));
        
        // Cache untuk 1 hari
        header('Cache-Control: public, max-age=86400');
        
        readfile($path);
        exit;
    }

   

    // Tambahkan method untuk robots.txt
    protected function handleRobots() {
        $robotsFile = PUBLIC_DIR . 'robots.txt';
        $defaultRobots = PUBLIC_DIR . 'robots.txt';
        
        if (file_exists($robotsFile)) {
            $this->serveRobots($robotsFile);
        } elseif (file_exists($defaultRobots)) {
            $this->serveRobots($defaultRobots);
        } else {
            // Buat file robots.txt baru jika tidak ada
            $robotsContent = $this->generateRobotsContent();
            if (file_put_contents($robotsFile, $robotsContent)) {
                $this->serveRobots($robotsFile);
            } else {
                // Jika gagal membuat file, tampilkan konten langsung
                $this->serveRobotsContent($robotsContent);
            }
        }
    }

    protected function serveRobots($path) {
        if (ob_get_level()) ob_end_clean();
        
        if (!file_exists($path)) {
            // Jika file tidak ditemukan, generate konten default
            $this->serveRobotsContent($this->generateRobotsContent());
            return;
        }
        
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=86400'); // Cache 1 hari
        
        readfile($path);
        exit;
    }

    protected function generateRobotsContent() {
        return "User-agent: *\n" .
               "Allow: /\n\n" .
               "# Disallow admin paths\n" .
               "Disallow: /admin/\n" .
               "Disallow: /login/\n" .
               "Disallow: /api/\n" .
               "Disallow: /sdk/\n\n" .
               "# Sitemap\n" .
               "Sitemap: " . HOST . "/sitemap.xml\n";
    }

    protected function serveRobotsContent($content) {
        if (ob_get_level()) ob_end_clean();
        
        header('Content-Type: text/plain; charset=UTF-8');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: public, max-age=86400'); // Cache 1 hari
        
        echo $content;
        exit;
    }

    // Tambahkan method baru untuk menangani config
    protected function handleConfigRequest() {
        $configFile = PACKAGE . 'config.php';
       require_once($configFile); 
        // include $configFile;
      
    }
    protected function handleConfigHandler() {
        $configFile = PACKAGE . 'handler.php';
       require_once($configFile); 
        // include $configFile;
    }
    protected function handleConfigMyadmin() {
        $configFile = ROOT . '/phpmyadmin/index.php';
       require_once($configFile); 
        // include $configFile;
    }


    // Tambahkan method baru untuk menangani worker
    // protected function handleWorkerRequest() { ... }

    // Tambahkan method helper untuk cache
    protected function getPathFromCache($key) {
        $cacheFile = APP . '/cache/paths/' . $key;
        if ($this->pathExists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data && isset($data['path']) && isset($data['expires']) && $data['expires'] > time()) {
                return $data['path'];
            }
            // Hapus cache yang expired
            @unlink($cacheFile);
        }
        return false;
    }

    protected function savePathToCache($key, $path) {
        $cacheDir = APP . '/cache/paths';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $data = [
            'path' => $path,
            'expires' => time() + 3600 // Cache selama 1 jam
        ];
        
        file_put_contents(
            $cacheDir . '/' . $key,
            json_encode($data),
            LOCK_EX
        );
    }

    // Helper method untuk mengecek keberadaan path dengan cache
    protected function pathExists($path) {
        $cacheKey = 'exists_' . md5($path);
        $cached = $this->getPathExistsFromCache($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $exists = file_exists($path);
        $this->savePathExistsToCache($cacheKey, $exists);
        
        return $exists;
    }

    protected function isDirectory($path) {
        $cacheKey = 'dir_' . md5($path);
        $cached = $this->getPathExistsFromCache($cacheKey);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $isDir = is_dir($path);
        $this->savePathExistsToCache($cacheKey, $isDir);
        
        return $isDir;
    }

    protected function getPathExistsFromCache($key) {
        $cacheFile = APP . '/cache/exists/' . $key;
        if (file_exists($cacheFile)) {
            $data = json_decode(file_get_contents($cacheFile), true);
            if ($data && isset($data['exists']) && isset($data['expires']) && $data['expires'] > time()) {
                return $data['exists'];
            }
            @unlink($cacheFile);
        }
        return null;
    }

    protected function savePathExistsToCache($key, $exists) {
        $cacheDir = APP . '/cache/exists';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $data = [
            'exists' => $exists,
            'expires' => time() + 3600 // Cache selama 1 jam
        ];
        
        file_put_contents(
            $cacheDir . '/' . $key,
            json_encode($data),
            LOCK_EX
        );
    }
}

/**
 * Fungsi helper untuk logging
 * @param string $message Pesan error
 * @param string $level Level log (ERROR, WARNING, INFO, etc)
 * @return bool
 */
function logError($message, $level = 'ERROR') {
    try {
        // Membuat direktori logs jika belum ada
        $logDir = APP . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Format nama file: error-YYYY-MM-DD.log
        $logFile = $logDir . '/error-' . date('Y-m-d') . '.log';
        
        // Format pesan log dengan timestamp dan IP
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = sprintf(
            "[%s][%s] %s - %s\n",
            $timestamp,
            $level,
            $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            $message
        );
        
        // Tulis ke file dengan penguncian (locking)
        return file_put_contents(
            $logFile,
            $logMessage,
            FILE_APPEND | LOCK_EX
        ) !== false;
    } catch (\Exception $e) {
        // Fallback ke error_log PHP jika gagal
        error_log("Gagal menulis log: " . $e->getMessage());
        error_log($message);
        return false;
    }
}

// Contoh penggunaan:
try {
    // kode yang mungkin error
} catch (\Exception $e) {
    logError($e->getMessage());
}
?>


