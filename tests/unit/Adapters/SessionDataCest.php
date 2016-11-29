<?php

namespace Kodus\Session\Tests\Unit\Adapters;


use Kodus\Session\SessionData;
use Kodus\Session\Session;
use Kodus\Session\Tests\Unit\SessionTest;

class SessionDataCest extends SessionTest
{
    /**
     * @var array
     */
    private $data = [];

    public function _before()
    {
        $this->data = [];
    }

    protected function getSession(string $session_id): Session
    {
        return new SessionData($session_id, $this->data);
    }
}
