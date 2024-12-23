<?php
namespace app;

class Cache {
    private $cacheDir;
    private $defaultExpiry;

    public function __construct($cacheDir = null, $defaultExpiry = 3600) {
        $this->cacheDir = $cacheDir ?? __DIR__ . '/../cache';
        $this->defaultExpiry = $defaultExpiry;
        
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }
    }

    public function set($key, $data, $expiry = null) {
        $expiry = $expiry ?? $this->defaultExpiry;
        $cacheData = [
            'data' => $data,
            'expiry' => time() + $expiry
        ];
        
        $filename = $this->getCacheFilename($key);
        return file_put_contents($filename, serialize($cacheData));
    }

    public function get($key) {
        $filename = $this->getCacheFilename($key);
        
        if (!file_exists($filename)) {
            return null;
        }

        $cacheData = unserialize(file_get_contents($filename));
        
        if (time() > $cacheData['expiry']) {
            unlink($filename);
            return null;
        }

        return $cacheData['data'];
    }

    private function getCacheFilename($key) {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }
} 