<?php
namespace Kodus\Session\Adapters;

use Codeception\Module\Cli;
use Kodus\Session\Components\ClientIP;
use Kodus\Session\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;

class CacheSessionService implements SessionService
{
    /**
     * @var string storage key prefix
     */
    const KEY_PREFIX = "kodus.session#";

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
     * @var string
     */
    private $salt;

    /**
     * @var ClientIP
     */
    private $client_ip;

    /**
     * @param CacheInterface $storage PSR-16 cache implementation, for session-data storage
     * @param string         $salt    private salt
     * @param int            $ttl     time to live (in seconds; defaults to two weeks)
     * @param ClientIP       $client_ip
     */
    public function __construct(CacheInterface $storage, $salt, $ttl = self::TWO_WEEKS, ClientIP $client_ip = null)
    {
        $this->storage = $storage;
        $this->salt = $salt;
        $this->ttl = $ttl;
        $this->client_ip = $client_ip ?: new ClientIP();
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

            if ($this->isValidSessionID($request, $session_id)) {

                $key = self::KEY_PREFIX . $session_id;

                $data = $this->storage->get($key);

                if (is_array($data)) {
                    return new SessionData($session_id, $data);
                }
            }
        }

        return new SessionData($this->createSessionID($request), []);
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

        $key = self::KEY_PREFIX . $session_id;

        $this->storage->set($key, $session->getData(), $this->ttl);

        $header = sprintf(self::COOKIE_NAME . "=%s; Path=/;", $session_id);

        return $response->withAddedHeader(self::SET_COOKIE_HEADER, $header);
    }

    /**
     * Create a new, valid Session ID for the given Request.
     *
     * @param ServerRequestInterface $request
     *
     * @return string valid, 40-byte Session ID
     */
    protected function createSessionID(ServerRequestInterface $request)
    {
        $id_part = substr(sha1(microtime(true) . rand(0, 9999999)), 0, 20);

        $checksum_part = $this->calculateChecksum($request, $id_part);

        $session_id = $id_part . $checksum_part;

        return $session_id;
    }

    /**
     * Internally validate a given Session ID against a given Request.
     *
     * @param ServerRequestInterface $request
     * @param string                 $session_id 40-byte Session ID
     *
     * @return bool TRUE, if the given Session ID is valid
     */
    protected function isValidSessionID(ServerRequestInterface $request, $session_id)
    {
        if (!strlen($session_id) === 40) {
            return false;
        }

        $id_part = substr($session_id, 0, 20);

        $checksum_part = substr($session_id, 20, 20);

        return $checksum_part === $this->calculateChecksum($request, $id_part);
    }

    /**
     * Internally calculate the second half (checksum part) of a valid 40-byte Session ID.
     *
     * @param ServerRequestInterface $request
     * @param string                 $id_part 20 bytes (ID part Session ID)
     *
     * @return string 20 bytes (checksum part of Session ID)
     */
    private function calculateChecksum(ServerRequestInterface $request, $id_part)
    {
        $user_agent = $request->getHeaderLine(self::USER_AGENT_HEADER);

        $client_ip = $this->client_ip->getIP($request);

        return substr(
            sha1(
                $this->salt
                . $user_agent
                . $client_ip
                . $id_part
                . "4ev2KH4kcUwJLX9f94csRuf2tkWvnMqV5mCVnFGy3dgYCtzgwteTJB8RYyfCsAVUmda99jZpPpwgqmVFnYqXFEjepzAnRMRQUkLj"
            ),
            0,
            20
        );
    }
}
