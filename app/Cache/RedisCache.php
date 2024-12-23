<?php
namespace app\Cache;

class RedisCache implements CacheInterface {
    private $redis;
    
    public function __construct() {
        $this->redis = new \Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }
    
    public function get(string $key) {
        $value = $this->redis->get($key);
        return $value !== false ? json_decode($value, true) : null;
    }
    
    public function set(string $key, $value, int $ttl = 300): bool {
        return $this->redis->setex($key, $ttl, json_encode($value));
    }
    
    public function delete(string $key): bool {
        return $this->redis->del($key) > 0;
    }
    
    public function clear(): bool {
        return $this->redis->flushDB();
    }
} 