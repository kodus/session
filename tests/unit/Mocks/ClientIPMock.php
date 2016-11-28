<?php

namespace Kodus\Session\Tests\Unit\Mocks;


use Kodus\Session\Components\ClientIP;
use Psr\Http\Message\ServerRequestInterface;

class ClientIPMock extends ClientIP
{
    /**
     * @var string This value is returned by getIP() method
     */
    public $ip_to_return = "127.0.0.1";

    public function getIP(ServerRequestInterface $request)
    {
        return $this->ip_to_return;
    }
}
