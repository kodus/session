<?php

namespace Kodus\Session\Tests\Unit\Adapters;

use Kodus\Session\Adapters\CacheSessionService;
use Kodus\Session\SessionService;
use Kodus\Session\Tests\Unit\Mocks\MockCache;
use Kodus\Session\Tests\Unit\SessionServiceTest;

class CacheSessionServiceCest extends SessionServiceTest
{
    protected function getSessionService(): SessionService
    {
        return new CacheSessionService(new MockCache(), "a salty string");
    }
}
