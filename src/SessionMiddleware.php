<?php

namespace Kodus\Session;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SessionMiddleware implements MiddlewareInterface
{
    /**
     * @const string
     */
    const ATTRIBUTE_NAME = "kodus.session";

    /**
     * @var SessionService
     */
    private $service;

    public function __construct(SessionService $service)
    {
        $this->service = $service;
    }

    /**
     * Process an incoming server request and return a response, optionally delegating
     * to the next middleware component to create the response.
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $session = $this->service->createSession($request);

        $request = $request->withAttribute(self::ATTRIBUTE_NAME, $session);

        $response = $handler->handle($request);

        $response = $this->service->commitSession($session, $response);

        return $response;
    }
}
