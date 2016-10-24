<?php

namespace Kodus\Session\Tests\Unit\Mocks;

use Kodus\Session\Service\CacheSessionService;

/**
 * This class is a test extension for CacheSessionService. This allows you to set a static time() value, by
 * overriding the time() method in the class.
 */
class CacheSessionServiceMock extends CacheSessionService
{
    /**
     * @var int
     */
    public $time = 0;

    protected function time()
    {
        return $this->time;
    }
}
