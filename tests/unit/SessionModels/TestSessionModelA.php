<?php

namespace Kodus\Session\Tests\Unit\SessionModels;

use Kodus\Session\SessionModel;

class TestSessionModelA implements SessionModel
{
    /**
     * @var string
     */
    public $foo;

    /**
     * @var string
     */
    public $bar;

    public function isEmpty(): bool
    {
        return empty($this->foo) && empty($this->bar);
    }
}
