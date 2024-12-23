<?php
namespace app;

class NgoreiCache {
    private string $cachePath;
    private int $defaultExpiry = 3600; // 1 jam
    
    public function __construct() {
        $this->cachePath = APP . '/cache';
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }
    
    public function set(string $key, $data, int $expiry = null): bool {
        $expiry = $expiry ?? $this->defaultExpiry;
        $cacheFile = $this->getCacheFile($key);
        
        $cacheData = [
            'data' => $data,
            'expires' => time() + $expiry
        ];
        
        return file_put_contents($cacheFile, serialize($cacheData), LOCK_EX) !== false;
    }
    
    public function get(string $key) {
        $cacheFile = $this->getCacheFile($key);
        
        if (!file_exists($cacheFile)) {
            return null;
        }
        
        $cacheData = unserialize(file_get_contents($cacheFile));
        
        if ($cacheData['expires'] < time()) {
            unlink($cacheFile);
            return null;
        }
        
        return $cacheData['data'];
    }
    
    private function getCacheFile(string $key): string {
        return $this->cachePath . '/' . md5($key) . '.cache';
    }
} 