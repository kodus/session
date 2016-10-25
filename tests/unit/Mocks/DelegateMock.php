<?php

namespace Kodus\Session\Tests\Unit\Mocks;


use Closure;
use Interop\Http\Middleware\DelegateInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response;

/**
 * For testing PSR-15 middleware.
 *
 * The closure $next is used to inject functionality that is run when / if the middleware under test calls
 * $next->process($request);
 *
 * I.e. When testing session middleware, the actual interaction with the session service is supposed to happen during the
 * nested call to the next middleware.
 *
 * @see Kodus\Session\Tests\Unit\Middleware\CacheSessionMiddlewareCest
 */
class DelegateMock implements DelegateInterface
{
    /**
     * @var Closure
     */
    public $next;

    public function __construct()
    {
        $this->next = function () {
            //Empty function
        };
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
        $response = call_user_func_array($this->next, [$request]);

        return ($response instanceof ResponseInterface) ? $response : new Response();
    }

    public function setNextClosure(Closure $next)
    {
        $this->next = $next;
    }
}
