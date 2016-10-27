<?php

namespace Kodus\Session\Storage;

use Kodus\Session\Component\UUID;
use Kodus\Session\SessionStorage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;

/**
 * A session service that stores the session data in a PSR-16 compliant cache storage.
 *
 * get(), set(), flash(), unset(), and clear() actions are cached individually and stored to the cache at commit().
 */
class CacheSessionStorage implements SessionStorage
{
    const COOKIE_KEY             = "sessionID";
    const SESSION_INDEX_PREFIX   = "kodus.session.";
    const FLASHES_STORAGE_INDEX  = "kodus.session.flashes";
    const SESSION_EXPIRATION_KEY = "kodus.session.expiration.";

    /**
     * @var CacheInterface
     */
    private $storage;

    /**
     * @var array Objects to be written to the cache storage on commit()
     */
    private $write_cache = [];

    /**
     * @var array Level one cache for reading objects from the cache storage.
     */
    private $read_cache = [];

    /**
     * @var array Type names of objects to be removed from cache storage on commit()
     */
    private $removed = [];

    /**
     * @var bool Indicates whether the clear() method was called since the last commit()
     */
    private $cleared = false;

    /**
     * @var array Index of the object types that was set as flash sessions. These will be removed during next request.
     */
    private $flashed = [];

    /**
     * @var string The session ID of the current session.
     */
    private $session_id;

    /**
     * @var int
     */
    private $session_ttl;

    /**
     * @param CacheInterface $storage     The cache provider to store session data in.
     * @param int            $session_ttl Session lifetime in seconds - Default: two weeks
     */
    public function __construct(CacheInterface $storage, $session_ttl = 1209600)
    {
        $this->storage = $storage;
        $this->session_ttl = $session_ttl;
    }

    public function flash(string $key, $value)
    {
        $this->writeDeffered($key, $value, true);

        return;
    }

    public function set(string $key, $value)
    {
        $this->writeDeffered($key, $value, false);

        return;
    }

    public function get(string $key)
    {
        $value = $this->read_cache[$key] ?? $this->getFromStorage($key);

        if (is_null($value)) {
            throw new RuntimeException("No value found for key#{$key} found in session!");
        }

        return $value;
    }

    public function has(string $key): bool
    {
        return isset($this->read_cache[$key]) || $this->existsInStorage($key);
    }

    public function unset(string $key)
    {
        unset($this->read_cache[$key]);
        unset($this->write_cache[$key]);

        $this->removed[$key] = $key;

        return;
    }

    public function clear()
    {
        foreach ($this->write_cache as $key => $value) {
            unset($this->read_cache[$key]);
            unset($this->write_cache[$key]);
        }

        $this->cleared = true;

        return;
    }

    public function sessionID(): string
    {
        return $this->session_id;
    }

    /**
     * Initiate the session from the cookie params found in the server request
     *
     * @param ServerRequestInterface $request
     */
    public function begin(ServerRequestInterface $request)
    {
        $this->clearState();

        $cookies = $request->getCookieParams();
        $session_id = $cookies[CacheSessionStorage::COOKIE_KEY] ?? null;

        if (! $this->sessionIsActive($session_id)) {
            $session_id = base64_encode(UUID::create());
        }

        $this->session_id = $session_id;
    }

    /**
     * Save Session data into the cache storage and add session cookie to response
     *
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function commit(ResponseInterface $response)
    {
        if ($this->cleared) {
            # CLEAR STORAGE (if clear() was called during the request)
            $this->storage->clear();
        } else {
            # CLEAR OLD FLASHES AND MARK NEW FLASHES FOR NEXT REQUEST.
            # (If redirect or error response code, then keep old flashes for next request).
            $flashes = $this->storage->get(self::FLASHES_STORAGE_INDEX) ?: [];

            if ($response->getStatusCode() < 300) {
                foreach ($flashes as $key) {
                    $this->storage->delete($this->storageKey($key));
                }
                $this->storage->set(self::FLASHES_STORAGE_INDEX, $this->flashed);
            } else {
                $this->storage->set(self::FLASHES_STORAGE_INDEX, array_merge($flashes, $this->flashed));
            }

            # DELETE SESSION MODELS THAT WERE REMOVED DURING THIS REQUEST
            foreach ($this->removed as $key) {
                $this->storage->delete($this->storageKey($key));
            }
        }

        # WRITE OPERATIONS
        foreach ($this->write_cache as $key => $type) {
            $this->storage->set($this->storageKey($key), $type);
        }

        # SET EXPIRATION TIME FOR SESSION ID IN STORAGE.
        $this->storage->set(self::SESSION_EXPIRATION_KEY . $this->session_id, $this->time() + $this->session_ttl);

        # ADD COOKIE TO RESPONSE.
        $response = $this->addSessionCookie($response);

        # CLEAR STATE FOR NEXT REQUEST.
        $this->clearState();

        return $response;
    }

    /**
     * A deffered write operation. The actual write operation will occur at commit()
     *
     * @param string $key
     * @param mixed  $value
     * @param bool   $is_flash
     */
    protected function writeDeffered(string $key, $value, bool $is_flash = false)
    {
        unset($this->removed[$key]);

        $this->read_cache[$key] = $value;
        $this->write_cache[$key] = $value;

        if ($is_flash) {
            $this->flashed[$key] = $key;
        } else {
            unset($this->flashed[$key]);
        }
    }

    /**
     * Checks if an object with the class name $type APPEARS to be in cache.
     *
     * What is meant by "APPEARS to be in cache"?:
     * If during the lifetime of the CacheSessionStorage, the $type was either removed, or the whole session storage
     * cleared by calling clear(), the connection to the storage is effectively cut-off, so the result reflects whether
     * the session was cleared or the $type removed since the last commit to storage.
     *
     * @param string $key
     *
     * @return bool returns true if an object of the given $type appears to be stored in storage.
     */
    protected function existsInStorage($key)
    {
        if ($this->cleared || isset($this->removed[$key])) {
            return false;
        }

        $storage_key = $this->storageKey($key);

        return $this->storage->exists($storage_key);

    }

    /**
     * If an object of type $type APPEARS to be in cache, then register it in the read_cache array attribute and
     * return the object.
     *
     * What is meant by "APPEARS to be in cache"?:
     *
     * @see CacheSessionStorage::existsInStorage
     *
     * @param string $key
     *
     * @return mixed|null
     */
    protected function getFromStorage($key)
    {
        if ($this->cleared || isset($this->removed[$key])) {
            return null;
        }

        $storage_key = $this->storageKey($key);

        $value = $this->storage->get($storage_key);

        if ($value) {
            $this->read_cache[$key] = $value;
        }

        return $value;
    }

    /**
     * Prefixes the type name with a session "namespace" and the session_id, so the index in the cache storage is
     * distinguishable from an index for the same type but a different session.
     *
     * @param string $key
     *
     * @return string
     */
    protected function storageKey($key)
    {
        return self::SESSION_INDEX_PREFIX . $this->session_id . ".$key";
    }

    /**
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    protected function addSessionCookie(ResponseInterface $response)
    {
        $cookie_string = sprintf(
            self::COOKIE_KEY . "=%s; Max-Age=%s; Expires=%s; Path=/;",
            $this->session_id,
            $this->session_ttl,
            $this->time() + $this->session_ttl
        );

        $response = $response->withAddedHeader("set-cookie", $cookie_string);

        return $response;
    }

    /**
     * @param string|null $session_id
     *
     * @return bool
     */
    protected function sessionIsActive($session_id)
    {
        if ($session_id === null) {
            return false;
        }

        $expiration_time = $this->storage->get(self::SESSION_EXPIRATION_KEY . $session_id);
        if (is_int($expiration_time) && $expiration_time > $this->time()) {
            return true;
        }

        return false;
    }

    /**
     * Returns the current value of time().
     *
     * The purpose of calling this method instead of the time() function directly is for testing purposes.
     *
     * If you want to make a test for this class that is dependent on knowing the current time, make an extension class,
     * with this method overridden with a known time value, that can be tested against, and test that extension instead.
     *
     * @return int the current time in seconds since the Epoch
     */
    protected function time()
    {
        return time();
    }

    /**
     * Clear all internal state. Should be called as the first thing in begin() and the last thing in commit().
     *
     * @return void
     */
    private function clearState()
    {
        $this->cleared = false;
        $this->read_cache = [];
        $this->write_cache = [];
        $this->removed = [];
        $this->flashed = [];
    }
}