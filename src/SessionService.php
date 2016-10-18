<?php

namespace Kodus\Session;

use RuntimeException;

class SessionService implements SessionContainer
{
    /**
     * @const string
     */
    const FLASHES_STORAGE_INDEX = "kodus.session.flashes";

    /**
     * @var SessionStorage
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

    public function __construct(SessionStorage $storage)
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
        $object = @$this->read_cache[$type] ?: $this->storage->get($type);

        if (is_null($object) || isset($this->removed[$type])) {
            throw new RuntimeException("Session model object of the type {$type} could not be found in session. Make sure to check to use SessionContainer::has() before SessionContainer::read()");
        }

        return $object;
    }

    public function has($type)
    {
        return (isset($this->read_cache[$type]) || $this->storage->has($type)) && ! isset($this->removed[$type]);
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
            $this->storage->remove($type);
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
        }
    }
}
