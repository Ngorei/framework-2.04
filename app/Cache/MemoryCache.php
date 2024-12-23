<?php
namespace app\Cache;

class MemoryCache implements CacheInterface {
    private array $storage = [];
    
    public function get(string $key) {
        if ($this->has($key)) {
            $item = $this->storage[$key];
            if ($item['expires'] > time()) {
                return $item['data'];
            }
            $this->delete($key);
        }
        return null;
    }
    
    public function set(string $key, $value, int $ttl = 300): bool {
        $this->storage[$key] = [
            'data' => $value,
            'expires' => time() + $ttl
        ];
        return true;
    }
    
    public function has(string $key): bool {
        return isset($this->storage[$key]);
    }
    
    public function delete(string $key): bool {
        unset($this->storage[$key]);
        return true;
    }
    
    public function clear(): bool {
        $this->storage = [];
        return true;
    }
} 