<?php declare(strict_types = 1);

namespace Kodus\Session;

use Kodus\Session\Interfaces\SessionModel;
use Kodus\Session\Interfaces\SessionStorage;

class SessionService
{
    const STORAGE_KEY_PREFIX = "#kodus#sessionmodel#";

    /**
     * @var SessionStorage
     */
    private $storage;

    public function __construct(SessionStorage $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Set a SessionModel object in the session storage.
     *
     * @param SessionModel $object
     *
     * @return void
     */
    public function set(SessionModel $object)
    {
        $this->storage->set($this->storageKey($object), $object);
    }

    /**
     * Set an object in the SessionService state cache and mark it as a "flash" message.
     *
     * "Flash" messages will only live through the next request, that returns 2xx HTML response codes.
     *
     * That means that you can expect the flash messages to live through a redirect result, so for example if you have
     * multiple redirects in a POST-Redirect-GET pattern solution, the flash message is still available when you reach
     * the GET request.
     *
     * @param SessionModel $object
     *
     * @return void
     */
    public function flash(SessionModel $object)
    {
        $this->storage->flash($this->storageKey($object), $object);
    }

    /**
     * Read the instance of $type from the session storage.
     *
     * If the storage does not have an instance of that type, the SessionService implementation SHOULD throw a
     * RuntimeException, rather than return null.
     *
     * This method should only be called with parameter $type, if SessionService::has($type) returns true
     *
     * @param string $type The class name of the object to be read from the session storage
     *
     * @return SessionModel|null
     */
    public function get(string $type)
    {
        return $this->storage->get($this->storageKey($type));
    }

    /**
     * Remove any instance of the class $type from the session.
     *
     * @param string $type
     *
     * @return void
     */
    public function unset(string $type)
    {
        $this->storage->unset($this->storageKey($type));
    }

    /**
     * Clear all objects stored in session on next commit().
     *
     * Any subsequent writes to the session are valid and stored on commit().
     *
     * @return void
     */
    public function clear()
    {
        $this->storage->clear();
    }

    /**
     * Returns the ID of the current session as a string.
     *
     * @return string
     */
    public function getSessionID(): string
    {
        return $this->storage->getSessionID();
    }

    /**
     * @param SessionModel|string $object_or_type
     *
     * @return string
     */
    private function storageKey($object_or_type): string
    {
        return self::STORAGE_KEY_PREFIX . (is_string($object_or_type) ? $object_or_type : get_class($object_or_type));
    }
}
