<?php

namespace Kodus\Session\Tests\Unit\SessionModels;

use Kodus\Session\SessionModel;

class TestSessionModelB implements SessionModel
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
