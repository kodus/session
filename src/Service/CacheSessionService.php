<?php

namespace Kodus\Session\Service;

use Kodus\Session\Components\UUID;
use Kodus\Session\SessionModel;
use Kodus\Session\SessionService;
use Psr\Http\Message\ResponseInterface;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;

// TODO clean up the logic of this class - for now we care about passing tests.
// TODO more handling of session ttl than cookie lifetime.
class CacheSessionService implements SessionService
{
    const SESSION_COOKIE_KEY    = "kodus-session";
    const SESSION_INDEX_PREFIX  = "kodus.session.";
    const FLASHES_STORAGE_INDEX = "kodus.session.flashes";

    /**
     * @var CacheInterface
     */
    private $storage;

    /**
     * @var array
     */
    private $write_cache = [];

    /**
     * @var array
     */
    private $read_cache = [];

    /**
     * @var array
     */
    private $removed = [];

    /**
     * @var bool
     */
    private $cleared = false;

    /**
     * @var array
     */
    private $flashed = [];

    /**
     * @var string
     */
    private $session_id;

    /**
     * @var int
     */
    private $session_ttl;

    public function __construct(CacheInterface $storage, $session_ttl = 60 * 60 * 24 * 14, $session_id = null)
    {
        $this->storage = $storage;

        $this->session_id = $session_id ?: base64_encode(UUID::create());

        $this->session_ttl = $session_ttl;
    }

    public function flash(SessionModel $object)
    {
        $this->writeDeffered($object, true);
    }

    public function set(SessionModel $object)
    {
        $this->writeDeffered($object, false);
    }

    public function get($type)
    {
        $object = $this->read_cache[$type] ?? $this->getFromStorage($type);

        if (is_null($object)) {
            throw new RuntimeException("Instance of SessionModel {$type} not found in session!");
        }

        return $object;
    }

    public function has($type)
    {
        return isset($this->read_cache[$type]) || $this->existsInStorage($type);
    }

    public function unset($type)
    {
        unset($this->read_cache[$type]);
        unset($this->write_cache[$type]);

        $this->removed[$type] = $type;
    }

    public function clear()
    {
        foreach ($this->write_cache as $type => $object) {
            unset($this->read_cache[$type]);
            unset($this->write_cache[$type]);
        }

        $this->cleared = true;
    }

    /**
     * Save Session data into storage and add session cookie to response
     *
     * @param ResponseInterface $response
     *
     * @return ResponseInterface
     */
    public function commit(ResponseInterface $response)
    {
        if ($this->cleared) {
            $this->storage->clear();
        }
        $flashes = $this->storage->get(self::FLASHES_STORAGE_INDEX) ?: [];

        foreach ($flashes as $type) {
            $this->storage->delete($this->storageKeyFromType($type));
        }

        foreach ($this->removed as $type) {
            $this->storage->delete($this->storageKeyFromType($type));
        }

        foreach ($this->write_cache as $type => $object) {
            $this->storage->set($this->storageKeyFromType($type), $object);
        }

        $this->storage->set(self::FLASHES_STORAGE_INDEX, $this->flashed);

        $this->read_cache = [];
        $this->write_cache = [];
        $this->cleared = false;

        // TODO move this to a session cookie component with an interface?!
        $cookie_string = sprintf(
            self::SESSION_COOKIE_KEY . "=%s; Max-Age=%s; Expires=%s; Path=/;",
            $this->session_id,
            $this->session_ttl,
            time() + $this->session_ttl
        );

        $response = $response->withAddedHeader("set-cookie", $cookie_string);

        return $response;
    }

    /**
     * A deffered write operation. The actual write operation will occure at commit()
     *
     * @param SessionModel $object
     * @param bool         $is_flash
     */
    protected function writeDeffered(SessionModel $object, $is_flash = false)
    {
        $type = get_class($object);

        unset($this->removed[$type]);

        $this->read_cache[$type] = $object;
        $this->write_cache[$type] = $object;

        if ($is_flash) {
            $this->flashed[$type] = $type;
        } else {
            unset($this->flashed[$type]);
        }
    }

    protected function existsInStorage($type)
    {
        if ($this->cleared || isset($this->removed[$type])) {
            return false;
        }

        $storage_key = $this->storageKeyFromType($type);

        return $this->storage->exists($storage_key);

    }

    protected function getFromStorage($type)
    {
        if ($this->cleared || isset($this->removed[$type])) {
            return null;
        }

        $storage_key = $this->storageKeyFromType($type);

        $object = $this->storage->get($storage_key);

        if ($object) {
            $this->read_cache[$type] = $object;
        }

        return $object;
    }

    protected function storageKeyFromType($type)
    {
        return self::SESSION_INDEX_PREFIX . $this->session_id . ".$type";
    }
}
