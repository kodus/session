<?php
namespace Kodus\Session;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface SessionService
{
    /**
     * Create a Session for the given Request.
     *
     * @param ServerRequestInterface $request the incoming HTTP Request
     *
     * @return SessionData
     */
    public function createSession(ServerRequestInterface $request): SessionData;

    /**
     * Commit Session to storage and decorate the given Response with the Session ID cookie.
     *
     * @param SessionData       $session
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function commitSession(SessionData $session, ResponseInterface $response): ResponseInterface;
}
