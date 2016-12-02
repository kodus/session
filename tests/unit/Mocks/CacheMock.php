<?php

namespace Kodus\Session\Tests\Unit\Mocks;

use Psr\SimpleCache\CacheInterface;

//TODO replace with kodus\mock-cache
class CacheMock implements CacheInterface
{
    /**
     * @var array
     */
    private $cache = [];

    /**
     * @var int[]
     */
    private $expiration_times = [];

    /**
     * @var int
     */
    public $time = 0;

    public function __construct(int $time = 0)
    {
        $this->time = $time;
    }

    public function delete($key)
    {
        unset($this->expiration_times[$key]);
        unset($this->cache[$key]);
    }

    public function getMultiple($keys)
    {
        $result = [];

        foreach ($this->cache as $key => $value) {
            if (in_array($key, $keys) && $this->expiration_times[$key] >= $this->time) {
                $result[$key] = $value;
            } else {
                $this->delete($key); //Clean up expired
            }
        }

        return $result;
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
        if ($this->isExpired($key)) {
            $this->delete($key);
        }

        return isset($this->cache[$key]) ? $this->cache[$key] : $default;
    }

    public function set($key, $value, $ttl = null)
    {
        $this->cache[$key] = $value;
        $this->expiration_times[$key] = $this->time + $ttl;
    }

    public function clear()
    {
        $this->cache = [];
        $this->expiration_times = [];
    }

    private function isExpired($key) {
        return (is_int($this->expiration_times[$key]) && $this->expiration_times[$key] < $this->time);
    }
}
