<?php
namespace app\Cache;

interface CacheInterface {
    public function get(string $key);
    public function set(string $key, $value, int $ttl = 300): bool;
    public function has(string $key): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
} 