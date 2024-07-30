<?php

declare(strict_types=1);

namespace PHP94\Cache;

use DateInterval;
use Psr\SimpleCache\CacheInterface;

class NullCache implements CacheInterface
{
    protected $data = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $this->validateKey($key);
        if (isset($this->data[$key]) && (!$this->data[$key]['expire_at'] || $this->data[$key]['expire_at'] >= time())) {
            return $this->data[$key]['value'];
        }
        return $default;
    }

    public function set(string $key, mixed $value, null|int|DateInterval $ttl = null): bool
    {
        $this->validateKey($key);
        $this->data[$key] = [
            'value' => $value,
            'expire_at' => is_null($ttl) ? null : time() + $ttl,
        ];
        return true;
    }

    public function delete(string $key): bool
    {
        $this->validateKey($key);
        unset($this->data[$key]);
        return true;
    }

    public function clear(): bool
    {
        $this->data = [];
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
        $this->validateKey($key);
        if (isset($this->data[$key]) && (!$this->data[$key]['expire_at'] || $this->data[$key]['expire_at'] >= time())) {
            return true;
        }
        return false;
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

        return true;
    }
}
