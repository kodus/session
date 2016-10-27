<?php declare(strict_types = 1);

namespace Kodus\Session;

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
     * @return SessionModel
     */
    public function get(string $type): SessionModel
    {
        return $this->storage->get($this->storageKey($type));
    }

    /**
     * @param string $type The class name of the session model to check
     *
     * @return boolean Returns true if a session model of with class name matching $type is stored in session.
     */
    public function has(string $type): bool
    {
        return $this->storage->has($this->storageKey($type));
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
    public function sessionID(): string
    {
        return $this->storage->sessionID();
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
