<?php

namespace Kodus\Session\Service;

use Kodus\Session\SessionModel;
use Kodus\Session\SessionService;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;

// TODO clean up the logic of this class - for now we care about passing tests.
class CacheSessionService implements SessionService
{
    /**
     * @const string
     */
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

    public function __construct(CacheInterface $storage)
    {
        $this->storage = $storage;
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
        $object = @$this->read_cache[$type] ?: ($this->cleared ? null : $this->storage->get($type));

        if (is_null($object) || isset($this->removed[$type])) {
            throw new RuntimeException("Session model object of the type {$type} could not be found in session. Make sure to check to use SessionService::has() before SessionService::read()");
        }

        return $object;
    }

    public function has($type)
    {
        if ($this->cleared) {
            return isset($this->read_cache[$type]) && ! isset($this->removed[$type]);
        }

        return ((isset($this->read_cache[$type]) || $this->storage->exists($type))) && ! isset($this->removed[$type]);
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
     * TODO change according to eventual Middleware
     */
    public function commit()
    {
        if ($this->cleared) {
            $this->storage->clear();
        }

        $flashes = $this->storage->get(self::FLASHES_STORAGE_INDEX) ?: [];

        foreach ($flashes as $type) {
            $this->storage->delete($type);
        }

        foreach ($this->removed as $type) {
            $this->storage->delete($type);
        }

        foreach ($this->write_cache as $type => $object) {
            $this->storage->set($type, $object);
        }

        $this->storage->set(self::FLASHES_STORAGE_INDEX, $this->flashed);

        $this->read_cache = [];
        $this->write_cache = [];
        $this->cleared = false;
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
}
