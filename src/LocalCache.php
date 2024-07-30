<?php

declare(strict_types=1);

namespace PHP94\Cache;

use DateInterval;
use Exception;
use Psr\SimpleCache\CacheInterface;
use Throwable;

class LocalCache implements CacheInterface
{
    private $cache_dir;

    public function __construct(string $cache_dir)
    {
        if (!is_dir($cache_dir)) {
            if (false === mkdir($cache_dir, 0755, true)) {
                throw new Exception('mkdir [' . $cache_dir . '] failure!');
            }
        }
        $this->cache_dir = $cache_dir;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->getCacheFile($key);
        try {
            if (!is_file($file)) {
                return $default;
            }
            $cache = unserialize(file_get_contents($file));
            if ($cache['ttl'] < time()) {
                $this->delete($key);
                return $default;
            }
        } catch (Throwable $th) {
            return $default;
        }
        return $cache['value'];
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $file = $this->getCacheFile($key);
        try {
            $cache = [
                'key' => $key,
                'ttl' => $ttl ? time() + $ttl : 9999999999,
                'value' => $value,
            ];
            return file_put_contents($file, serialize($cache));
        } catch (Throwable $th) {
            return false;
        }
    }

    public function delete(string $key): bool
    {
        $file = $this->getCacheFile($key);
        try {
            if (is_file($file)) {
                return unlink($file);
            }
        } catch (Throwable $th) {
            return false;
        }
        return true;
    }

    public function clear(): bool
    {
        try {
            $tmp = scandir($this->cache_dir);
            foreach ($tmp as $val) {
                if ($val != '.' && $val != '..') {
                    if (is_dir($this->cache_dir . '/' . $val)) {
                        if (!rmdir($this->cache_dir . '/' . $val)) {
                            return false;
                        }
                    } else {
                        if (!unlink($this->cache_dir . '/' . $val)) {
                            return false;
                        }
                    }
                }
            }
        } catch (Throwable $th) {
            return false;
        }
        return true;
    }

    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->get($key, $default);
        }
    }

    public function setMultiple(iterable $values, null|int|DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                return false;
            }
        }
        return true;
    }

    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                return false;
            }
        }
        return true;
    }

    public function has(string $key): bool
    {
        $file = $this->getCacheFile($key);
        try {
            if (!is_file($file)) {
                return false;
            }
            $cache = unserialize(file_get_contents($file));
            if ($cache['ttl'] < time()) {
                $this->delete($key);
                return false;
            }
        } catch (Throwable $th) {
            return false;
        }
        return true;
    }

    private function getCacheFile($key): string
    {
        $this->validateKey($key);
        return $this->cache_dir . '/' . $key;
    }

    /**
     * @param string $key
     *
     * @throws InvalidArgumentException
     */
    protected function validateKey($key)
    {
        if (!is_string($key) || $key === '') {
            throw new InvalidArgumentException('Key should be a non empty string');
        }

        $unsupportedMatched = preg_match('#[' . preg_quote('{}()/\@:') . ']#', $key);
        if ($unsupportedMatched > 0) {
            throw new InvalidArgumentException('Can\'t validate the specified key');
        }
    }
}
