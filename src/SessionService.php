<?php

namespace Kodus\Session;

use Kodus\Helpers\UUID;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * This class provides integration between the PSR-7 Request / Response models and
 * the Session Data abstraction.
 */
class SessionService
{
    /**
     * @var string
     */
    const SET_COOKIE_HEADER = "Set-Cookie";

    /**
     * @var int two weeks in seconds
     */
    const TWO_WEEKS = 1209600;

    /**
     * @var string session cookie name (weird name as recommended by OWASP)
     */
    const COOKIE_NAME = "KSID";

    /**
     * @var SessionStorage $storage
     */
    protected $storage;

    /**
     * @var int time to live (in seconds)
     */
    protected $ttl;

    /**
     * @var bool
     */
    private $secure_only;

    /**
     * @param SessionStorage $storage     Session Storage adapter (for storage of raw Session Data)
     * @param int            $ttl         time to live (in seconds; defaults to two weeks)
     * @param bool           $secure_only if TRUE, the session cookie is flagged as "Secure" (SSL transport required)
     */
    public function __construct(SessionStorage $storage, int $ttl = self::TWO_WEEKS, bool $secure_only = false)
    {
        $this->storage = $storage;
        $this->ttl = $ttl;
        $this->secure_only = $secure_only;
    }

    /**
     * Create a Session for the given Request.
     *
     * @param ServerRequestInterface $request the incoming HTTP Request
     *
     * @return SessionData
     */
    public function createSession(ServerRequestInterface $request): SessionData
    {
        $cookies = $request->getCookieParams();

        if (isset($cookies[self::COOKIE_NAME])) {
            $session_id = $cookies[self::COOKIE_NAME];

            $data = $this->storage->read($session_id);

            if (is_array($data)) {
                return new SessionData($session_id, $data);
            }
        }

        return new SessionData(UUID::create(), []);
    }

    /**
     * Commit Session to storage and decorate the given Response with the Session ID cookie.
     *
     * @param SessionData       $session
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function commitSession(SessionData $session, ResponseInterface $response): ResponseInterface
    {
        $session_id = $session->getSessionID();

        $data = $session->getData();

        if (count($data) === 0) {
            $this->storage->destroy($session_id);
        } else {
            $this->storage->write($session_id, $data, $this->ttl);
        }

        $secure = $this->secure_only ? " Secure;" : "";

        $header = sprintf(self::COOKIE_NAME . "=%s; Path=/; HTTPOnly; SameSite=Lax;%s", $session_id, $secure);

        return $response->withAddedHeader(self::SET_COOKIE_HEADER, $header);
    }
}
