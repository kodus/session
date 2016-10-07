<?php

namespace Kodus\Session;


use Closure;
use ReflectionFunction;

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

    public function flash(Closure $updater)
    {
        $this->update($updater, true);
    }

    public function write(Closure $updater)
    {
        $this->update($updater);
    }

    public function read(&$object)
    {
        $type = get_class($object);

        $object = $this->fetch($type, false) ?: $this->create($type, false);
    }

    public function remove($type)
    {
        unset($this->write_cache[$type]);
        unset($this->read_cache[$type]);
        $this->removed[$type] = $type;
    }

    public function clear()
    {
        foreach ($this->write_cache as $type => $object) {
            unset($this->write_cache[$type]);
        }

        $this->cleared = true;
    }

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
     * @param Closure $updater Closure for updating the values in the session model(s)
     * @param bool    $flash   If true, the session model(s) updated by the closure are marked as flash sessions
     */
    protected function update(Closure $updater, $flash = false)
    {
        $reflection = new ReflectionFunction($updater);

        $params = $reflection->getParameters();

        $args = [];

        foreach ($params as $param) {
            $type = $param->getClass()->getName();

            if ($param->isDefaultValueAvailable()) {
                $args[] = $this->fetch($type) ?: $param->getDefaultValue();
            } else {
                $args[] = $this->fetch($type) ?: $this->create($type);
            }

            if ($flash) {
                $this->flashed[$type] = $type;
            } else {
                unset($this->flashed[$type]);
            }

            unset($this->removed[$type]);
        }

        call_user_func_array($updater, $args);
    }

    /**
     * Fetch the stored session object by its type. The object is fetched from either the read cache if it is in there.
     * If there isn't an instance in the read cache, the object is fetched from storage.
     *
     * If the second parameter is set to true (default), the object is also registered in the write cache, and will
     * be written to storage on calling commit().
     *
     * @param string $type           The type (class name) of the object to fetch from storage or read cache
     * @param bool   $update_storage If true, the object will be set in both the read and write cache
     *
     * @return mixed|null
     */
    private function fetch($type, $update_storage = true)
    {
        if (@$this->removed[$type]) {
            return null;
        } else {
            $this->read_cache[$type] = @$this->read_cache[$type] ?: $this->storage->get($type);
        }

        if ($update_storage && $this->read_cache[$type]) {
            $this->write_cache[$type] = $this->read_cache[$type];
        }

        return $this->read_cache[$type];
    }

    /**
     * Create an object of the given type and store it in the read_cache.
     *
     * If the second parameter is set to true (default), the object is also registered in the write cache, and will
     * be written to storage on calling commit().
     *
     * @param string $type           The type (class name) of the object to create
     * @param bool   $update_storage If true, the object will be set in both the read and write cache
     *
     * @return mixed
     */
    private function create($type, $update_storage = true)
    {
        $this->read_cache[$type] = new $type;

        if ($update_storage) {
            $this->write_cache[$type] = $this->read_cache[$type];
        }

        return $this->read_cache[$type];
    }
}
