<?php

namespace Kodus\Session\Tests\Unit\SessionModels;

use Kodus\Session\Interfaces\SessionModel;

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
}
