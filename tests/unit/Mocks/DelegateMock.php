<?php

namespace Kodus\Session\Tests\Unit\Mocks;


use Closure;
use Interop\Http\Middleware\DelegateInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response;

class DelegateMock implements DelegateInterface
{
    /**
     * @var Closure
     */
    public $stuff_to_do;

    public function __construct(Closure $stuff_to_do){
        $this->stuff_to_do = $stuff_to_do;
    }

    /**
     * Dispatch the next available middleware and return the response.
     *
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     */
    public function process(RequestInterface $request)
    {
        call_user_func_array($this->stuff_to_do, []);

        return new Response();
    }
}
