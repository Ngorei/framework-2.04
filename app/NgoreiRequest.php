<?php
namespace app;
use app\tatiye;
class NgoreiRequest {
    private $publicDir;
    private $cache = [];
    private $cacheEnabled = true;
    private $cacheExpiry = 3600;
    private $pathCache = []; // Cache untuk path resolution
    
    public function __construct($publicDir = PUBLIC_DIR) {
        $this->publicDir = rtrim($publicDir, '/') . '/'; // Normalisasi sekali saja
    }

    protected function getCacheKey($path) {
        return 'request_' . sha1($path);
    }

    protected function getFromCache($path) {
        if (!$this->cacheEnabled) return null;
        
        $key = $this->getCacheKey($path);
        if (isset($this->cache[$key])) {
            $cached = $this->cache[$key];
            if ($cached['expires'] > time()) {
                return $cached['data'];
            }
            unset($this->cache[$key]);
            
            // Garbage collection ringan
            if (count($this->cache) > 1000) { // Batasi ukuran cache
                $this->cleanExpiredCache();
            }
        }
        return null;
    }

    protected function cleanExpiredCache() {
        $now = time();
        foreach ($this->cache as $key => $item) {
            if ($item['expires'] <= $now) {
                unset($this->cache[$key]);
            }
        }
    }

    protected function processRequest($path) {
        // Cache resolusi path
        $cacheKey = 'path_' . $path;
        if (isset($this->pathCache[$cacheKey])) {
            return $this->template($this->pathCache[$cacheKey]);
        }

        if (preg_match('/\.(js|css)$/', $path)) {
            return $this->handleCssJsRequest($path);
        }

        $pathParts = explode('/', $path);
        $resolvedPath = null;

        // Optimasi pengecekan "-"
        foreach ($pathParts as $index => $part) {
            if ($part[0] === '-') {
                $basePath = implode('/', array_slice($pathParts, 0, $index));
                $indexPath = $this->publicDir . $basePath . '/index.html';
                $resolvedPath = $indexPath;
                break;
            }
        }

        if (!$resolvedPath) {
            // Cek path secara berurutan dengan early return
            $htmlPath = $this->publicDir . $path . '.html';
            $indexPath = $this->publicDir . $path . '/index.html';
            $parentPath = dirname($this->publicDir . $path) . '/index.html';

            if ($result = $this->template($htmlPath)) {
                $resolvedPath = $htmlPath;
            } elseif ($result = $this->template($indexPath)) {
                $resolvedPath = $indexPath;
            } elseif ($result = $this->template($parentPath)) {
                $resolvedPath = $parentPath;
            }
        }

        if ($resolvedPath) {
            $this->pathCache[$cacheKey] = $resolvedPath;
            return $this->template($resolvedPath);
        }

        return $this->notPage();
    }

    protected function template($path) {
        try {
            return $path ?: false;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function handleCssJsRequest($path) {
        $filePath = $this->publicDir . $path;
        if (!is_file($filePath)) {
            return false;
        }
        
        // Tambahkan header untuk content type
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $contentType = $extension === 'css' ? 'text/css' : 'application/javascript';
        header("Content-Type: $contentType");
        
        // Tambahkan header untuk caching
        $etag = md5_file($filePath);
        $lastModified = gmdate('D, d M Y H:i:s', filemtime($filePath)) . ' GMT';
        
        header("ETag: \"$etag\"");
        header("Last-Modified: $lastModified");
        header('Cache-Control: public, max-age=31536000'); // Cache selama 1 tahun
        
        // Cek if-none-match header
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH'], '"') === $etag) {
            header('HTTP/1.1 304 Not Modified');
            return true;
        }
        
        // Streaming dengan ukuran chunk yang optimal
        $handle = fopen($filePath, 'rb');
        if ($handle === false) {
            return false;
        }
        
        // Set header untuk content length
        header('Content-Length: ' . filesize($filePath));
        
        // Gunakan chunk yang lebih besar untuk file besar (256KB)
        $chunkSize = 262144; 
        while (!feof($handle)) {
            echo fread($handle, $chunkSize);
            flush();
        }
        fclose($handle);
        return true;
    }

    protected function notPage() {
        return "404 - Halaman tidak ditemukan";
    }

    public function handleDefaultRequest($path) {
        $cachedResponse = $this->getFromCache($path);
        if ($cachedResponse !== null) {
            return $cachedResponse;
        }

        $path = trim($path, '/');
        
        if (empty($path)) {
            $response = $this->template($this->publicDir . 'index.html');
            $this->setCache($path, $response);
            return $response;
        }
        
        $response = $this->processRequest($path);
        $this->setCache($path, $response);
        return $response;
    }

    protected function setCache($path, $data) {
        if (!$this->cacheEnabled) return;
        
        $key = $this->getCacheKey($path);
        $this->cache[$key] = [
            'data' => $data,
            'expires' => time() + $this->cacheExpiry
        ];
    }
}

