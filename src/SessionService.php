<?php

namespace Kodus\Session;

use DateTime;
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
     * @var int one year in seconds
     */
    const ONE_YEAR = 31536000;

    /**
     * @var string session cookie name (weird name as recommended by OWASP)
     */
    const COOKIE_NAME = "KSID";

    /**
     * @var SessionStorage $storage
     */
    protected $storage;

    /**
     * @var int time-to-live for session data, in storage (in seconds)
     */
    protected $ttl;

    /**
     * @var bool
     */
    private $secure_only;

    /**
     * @var int cookie expiration time (in seconds)
     */
    private $expiration;

    /**
     * @var string domain attribute of the session cookie (defaults to null)
     *
     * @link https://stackoverflow.com/a/30676300/283851
     */
    private $domain;

    /**
     * IMPORTANT SECURITY NOTE:
     *
     * We make no attempts to prevent session hijacking - see the following post for details:
     *
     *     https://stackoverflow.com/a/12234563/283851
     *
     * The default value of `false` for `$secure_only` is intended for development *only* and
     * *must* be set to `true` in production environments!
     *
     * The `Domain` attribute isn't set by default, which means your session cookie will *not*
     * be accessible from sub-domains; if you need to access the session cookie from sub-domains,
     * you must explicitly set the `$domain` value to e.g. `"example.com"`. See the following
     * post for additional details:
     *
     *     https://stackoverflow.com/a/30676300/283851
     *
     * Notes regarding TTL and expiration:
     *
     * The `$ttl` value defines the time-to-live for the session data in storage - the default
     * value (two weeks) is intended for "average security" applications, and you may wish to
     * lower this for "higher security" applications.
     *
     * We use a separate `$expiration` value for the expiration timestamp on the cookie itself,
     * with a very high default value of one year - this enables us to invalidate old sessions
     * and remove them from storage, when a user returns (after the TTL) and starts a new session.
     *
     * @param SessionStorage $storage     Session Storage adapter (for storage of raw Session Data)
     * @param int            $ttl         time to live (in seconds; defaults to two weeks)
     * @param bool           $secure_only if TRUE, the session cookie is flagged as "Secure" (SSL transport required)
     * @param int            $expiration  cookie expiration time (in seconds)
     * @param string|null    $domain      cookie domain attribute (or null to omit domain attribute)
     */
    public function __construct(
        SessionStorage $storage,
        int $ttl = self::TWO_WEEKS,
        bool $secure_only = false,
        int $expiration = self::ONE_YEAR,
        ?string $domain = null
    ) {
        $this->storage = $storage;
        $this->ttl = $ttl;
        $this->secure_only = $secure_only;
        $this->expiration = $expiration;
        $this->domain = $domain;
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
                return new SessionData($session_id, $data, false);
            }
        }

        return new SessionData(UUID::create(), [], true);
    }

    /**
     * Commit Session to storage and add the Session Cookie to the given Response.
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

        if ($session->isRenewed()) {
            // The session was renewed - destroy the data that was stored under the old Session ID:

            $this->storage->destroy($session->getOldSessionID());
        }

        if (count($data) === 0) {
            // The session is empty - it should not be stored.

            if (! $session->isNew()) {
                // This session contained data previously and became empty - it should be destroyed:

                $this->storage->destroy($session_id);

                // The cookie should be expired immediately:

                $response = $response->withAddedHeader(self::SET_COOKIE_HEADER, $this->createCookie("", 0));
            }
        } else {
            // The session contains data - it should be stored:

            $this->storage->write($session_id, $data, $this->ttl);

            if ($session->isNew() || $session->isRenewed()) {
                // We've stored a new (or renewed) session - issue a cookie with the new Session ID:

                $response = $response->withAddedHeader(
                    self::SET_COOKIE_HEADER,
                    $this->createCookie($session_id, $this->getTime() + $this->expiration)
                );
            }
        }

        return $response;
    }

    protected function getTime(): int
    {
        return time();
    }

    private function createCookie(string $value, int $expires): string
    {
        $cookie = [self::COOKIE_NAME . "={$value}"];

        $cookie[] = "Path=/"; // session cookie accessible from any path

        $cookie[] = "HTTPOnly"; // disallow client-side access (from JavaScript)

        $cookie[] = "SameSite=Lax"; // see https://www.owasp.org/index.php/SameSite

        if ($this->domain) {
            $cookie[] = "Domain={$this->domain}"; // makes the cookie accessible under this domain and subdomains
        }

        if ($this->secure_only) {
            $cookie[] = "Secure"; // tells the client to send this cookie only with secure (HTTPS) requests
        }

        $cookie[] = "Expires=" . DateTime::createFromFormat("U", $expires, timezone_open('UTC'))->format(DateTime::COOKIE);

        return implode("; ", $cookie);
    }
}
