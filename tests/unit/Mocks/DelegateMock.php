<?php

namespace Kodus\Session\Tests\Unit\Mocks;

use Closure;
use Interop\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
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
 * @see \Kodus\Session\Tests\Unit\SessionMiddlewareCest
 */
class DelegateMock implements RequestHandlerInterface
{
    /**
     * @var Closure
     */
    public $next;

    public function __construct(Closure $next)
    {
        $this->next = $next;
    }

    /**
     * Dispatch the next available middleware and return the response.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = call_user_func_array($this->next, [$request]);

        return ($response instanceof ResponseInterface) ? $response : new Response();
    }
}
