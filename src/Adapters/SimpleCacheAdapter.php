<?php

namespace Kodus\Session\Adapters;

use Kodus\Session\SessionStorage;
use Psr\SimpleCache\CacheInterface;

/**
 * This class implements a Session Storage adapter for a PSR-16 Cache.
 */
class SimpleCacheAdapter implements SessionStorage
{
    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @param CacheInterface $cache PSR-16 cache implementation, for session-data storage
     */
    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    public function read(string $session_id): ?array
    {
        return $this->cache->get($session_id);
    }

    public function write(string $session_id, array $data, int $ttl)
    {
        $this->cache->set($session_id, $data, $ttl);
    }

    public function destroy(string $session_id)
    {
        $this->cache->delete($session_id);
    }
}
