<?php

namespace Kodus\Session\Storage;

use Kodus\Session\Component\UUID;
use Kodus\Session\Interfaces\SessionStorage;
use Kodus\Session\Interfaces\TransactionalSessionStorage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * A TransactionalSessionStorage adapter that stores the session data in a PSR-16 compliant cache storage.
 */
class CacheSessionStorage implements SessionStorage, TransactionalSessionStorage
{
    const COOKIE_KEY                = "sessionID";
    const FLASHES_STORAGE_INDEX     = "kodus.session.flashes";
    const SESSION_INDEX_PREFIX      = "kodus.session.";
    const SESSION_EXPIRATION_PREFIX = "kodus.session.expiration.";

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
    private $clear_at_commit = false;

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
        $this->writeDeferred($key, $value, true);

        return;
    }

    public function set(string $key, $value)
    {
        $this->writeDeferred($key, $value, false);

        return;
    }

    public function get(string $key)
    {
        $value = $this->read_cache[$key] ?? $this->getFromStorage($key);

        return $value;
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

        $this->clear_at_commit = true;

        return;
    }

    public function getSessionID(): string
    {
        return $this->session_id;
    }

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

    public function commit(ResponseInterface $response): ResponseInterface
    {
        //Gather all write and remove operations in one array, and write it all in one action
        //Because CacheStorage::get() returns null for a non-existing or deleted key, setting a value to null
        //is equivalent to deleting it.
        $write_batch = [];

        if ($this->clear_at_commit) {
            # CLEAR STORAGE (if clear() was called during the request)
            $this->storage->clear();
        }

        # CLEAR OLD FLASHES AND MARK NEW FLASHES FOR NEXT REQUEST.
        # (If redirect or error response code, then keep old flashes for next request).
        $flashes = $this->storage->get(self::FLASHES_STORAGE_INDEX) ?: [];

        if ($response->getStatusCode() < 300) {
            foreach ($flashes as $key) {
                $write_batch[$this->storageKey($key)] = null;
            }
            $write_batch[self::FLASHES_STORAGE_INDEX] = $this->flashed;
        } else {
            $write_batch[self::FLASHES_STORAGE_INDEX] = array_merge($flashes, $this->flashed);
        }

        # DELETE SESSION MODELS THAT WERE REMOVED DURING THIS REQUEST
        foreach ($this->removed as $key) {
            $write_batch[$this->storageKey($key)] = null;
        }

        # WRITE OPERATIONS
        foreach ($this->write_cache as $key => $object) {
            $write_batch[$this->storageKey($key)] = $object;
        }

        # SET EXPIRATION TIME FOR SESSION ID IN STORAGE.
        $write_batch[self::SESSION_EXPIRATION_PREFIX . $this->session_id] = $this->time() + $this->session_ttl;

        $this->storage->setMultiple($write_batch);

        # ADD COOKIE TO RESPONSE.
        $response = $this->addSessionCookie($response);

        # CLEAR STATE FOR NEXT REQUEST.
        $this->clearState();

        return $response;
    }

    /**
     * A deferred write operation. The actual write operation will occur at commit()
     *
     * @param string $key
     * @param mixed  $value
     * @param bool   $is_flash
     */
    protected function writeDeferred(string $key, $value, bool $is_flash = false)
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
     * If an object of type $type APPEARS to be in cache, then register it in the read_cache array attribute and
     * return the object.
     *
     * What is meant by "APPEARS to be in cache"?:
     * If during the lifetime of the CacheSessionStorage, the $type was either removed, or the whole session storage
     * cleared by calling clear(), the connection to the storage is effectively cut-off, so the result reflects whether
     * the session was cleared or the $type removed since the last commit to storage
     *
     * @param string $key
     *
     * @return mixed|null
     */
    protected function getFromStorage($key)
    {
        if ($this->clear_at_commit || isset($this->removed[$key])) {
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

        $expiration_time = $this->storage->get(self::SESSION_EXPIRATION_PREFIX . $session_id);
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
        $this->clear_at_commit = false;
        $this->read_cache = [];
        $this->write_cache = [];
        $this->removed = [];
        $this->flashed = [];
    }
}
