<?php

namespace Kodus\Session\Middleware;

use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;
use Kodus\Session\Service\CacheSessionService;
use Psr\Http\Message\ServerRequestInterface;

class CacheSessionMiddleware implements ServerMiddlewareInterface
{

    /**
     * @var CacheSessionService
     */
    private $session;

    public function __construct(CacheSessionService $session)
    {
        $this->session = $session;
    }

    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $this->session->begin($request);

        $response = $delegate->process($request);

        $response = $this->session->commit($response);

        return $response;
    }
}
