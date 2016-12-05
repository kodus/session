<?php
namespace Kodus\Session\Adapters;

use Kodus\Session\Components\UUID;
use Kodus\Session\SessionData;
use Kodus\Session\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;

class CacheSessionService implements SessionService
{
    /**
     * @var string
     */
    const SET_COOKIE_HEADER = "Set-Cookie";

    /**
     * @var string
     */
    const USER_AGENT_HEADER = "User-Agent";

    /**
     * @var int two weeks in seconds
     */
    const TWO_WEEKS = 1209600;

    /**
     * @var string session cookie name
     */
    const COOKIE_NAME = "kodus.session";

    /**
     * @var CacheInterface
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
     * @param CacheInterface $storage     PSR-16 cache implementation, for session-data storage
     * @param int            $ttl         time to live (in seconds; defaults to two weeks)
     * @param bool           $secure_only If true, sessions are only recognized over HTTPS
     */
    public function __construct(CacheInterface $storage, int $ttl = self::TWO_WEEKS, bool $secure_only = true)
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
    public function createSession(ServerRequestInterface $request)
    {
        $cookies = $request->getCookieParams();

        if (isset($cookies[self::COOKIE_NAME])) {
            $session_id = $cookies[self::COOKIE_NAME];

            $data = $this->storage->get($session_id);

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
    public function commitSession(SessionData $session, ResponseInterface $response)
    {
        $session_id = $session->getSessionID();

        $this->storage->set($session_id, $session->getData(), $this->ttl);

        $secure = $this->secure_only ? " Secure;" : "";
        $header = sprintf(self::COOKIE_NAME . "=%s; Path=/; HTTPOnly;%s", $session_id, $secure);

        return $response->withAddedHeader(self::SET_COOKIE_HEADER, $header);
    }
}
