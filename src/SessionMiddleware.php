<?php
namespace Kodus\Session;

use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class SessionMiddleware implements ServerMiddlewareInterface
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
     * @param ServerRequestInterface $request
     * @param DelegateInterface      $delegate
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $session = $this->service->createSession($request);

        $request = $request->withAttribute(self::ATTRIBUTE_NAME, $session);

        $response = $delegate->process($request);

        $response = $this->service->commitSession($session, $response);

        return $response;
    }
}
