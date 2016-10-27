<?php

namespace Kodus\Session\Tests\Unit\Mocks;


use Kodus\Session\Storage\CacheSessionStorage;

class CacheSessionStorageMock extends CacheSessionStorage
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
