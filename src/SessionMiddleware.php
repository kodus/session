<?php

namespace Kodus\Session;

use Interop\Http\Middleware\DelegateInterface;
use Interop\Http\Middleware\ServerMiddlewareInterface;
use Kodus\Session\SessionStorage;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The middleware will initialise the session based the session cookie (or lack of) in the request, and delegate to the
 * next middleware. When a response is returned, the changes are committed to the storage, and a session cookie is added
 * to the response.
 *
 * This middleware should be placed above any middleware that might cause changes to the session or need to fetch
 * session values.
 */
class SessionMiddleware implements ServerMiddlewareInterface
{
    /**
     * @var SessionStorage
     */
    private $session;

    public function __construct(SessionStorage $session)
    {
        $this->session = $session;
    }

    /**
     * The middleware begins the session by passing the server request object to the session service. The service
     * fetches the current session id from the cookies in the request object, or initiates a new session.
     *
     * After running the subsequent middleware the session is "committed", which means that any changes to the session
     * is stored into the cache storage at this point. If an error occurs in the subsequent middleware, no changes are
     * stored to the session storage, nor will be available for the next request.
     *
     * @param ServerRequestInterface $request
     * @param DelegateInterface      $delegate
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $this->session->begin($request);

        $response = $delegate->process($request);

        $response = $this->session->commit($response);

        return $response;
    }
}
