<?php

namespace Kodus\Session\Tests\Unit\Mocks;

use Kodus\Session\Service\CacheSessionService;
use Psr\SimpleCache\CacheInterface;

/**
 * This class is a test extension for CacheSessionService. This allows you to set a static time() value, by
 * overriding the time() method in the class.
 */
class StaticTimeCacheSessionService extends CacheSessionService
{
    /**
     * @var int
     */
    public $time = 0;

    protected function time()
    {
        return $this->time;
    }

    public function __construct(
        CacheInterface $storage,
        $session_ttl = 60 * 60 * 24 * 14,
        $session_id = null,
        $static_time = 0
    ) {
        $this->time = $static_time;

        parent::__construct($storage, $session_ttl, $session_id);
    }
}
