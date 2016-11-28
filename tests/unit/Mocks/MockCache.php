<?php

namespace Kodus\Session\Tests\Unit\Mocks;

use Psr\SimpleCache\CacheInterface;

class MockCache implements CacheInterface
{
    /**
     * @var array
     */
    private $cache = [];

    public function delete($key)
    {
        unset($this->cache[$key]);
    }

    public function getMultiple($keys)
    {
        return array_filter($this->cache, function ($key) use ($keys) {
            return in_array($key, $keys);
        });
    }

    public function setMultiple($items, $ttl = null)
    {
        foreach ($items as $key => $value) {
            $this->set($key, $value, $ttl);
        }
    }

    public function deleteMultiple($keys)
    {
        foreach ($keys as $key) {
            unset($this->cache[$key]);
        }
    }

    public function has($key)
    {
        return isset($this->cache[$key]);
    }

    public function get($key, $default = null)
    {
        return $this->cache[$key] ?? $default;
    }

    public function set($key, $value, $ttl = null)
    {
        $this->cache[$key] = $value; //ignore ttl for mock
    }

    public function clear()
    {
        $this->cache = [];
    }
}
