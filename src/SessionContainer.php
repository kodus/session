<?php
namespace Kodus\Session;

/**
 * This interface defines a set of methods for writing and reading session values.
 *
 * All actions writing actions to the SessionContainer are cached and deffered to a final commit action.
 */
interface SessionContainer
{
    public function __construct(SessionStorage $storage);

    /**
     * Set the object in the session container state cache.
     *
     * @param SessionModel $object The session model to write to storage
     *
     * @return void
     */
    public function set(SessionModel $object);

    /**
     * Set an object in the SessionContainer state cache and mark it as a "flash" message.
     *
     * "Flash" messages will only live through the next request, that returns 2xx HTML response codes.
     *
     * That means that you can expect the flash messages to live through a redirect result, so for example if you have
     * multiple redirects in a POST-Redirect-GET pattern solution, the flash message is still available when you reach
     * the GET request.
     *
     * @param $object
     *
     * @return void
     */
    public function flash(SessionModel $object);

    /**
     * Read the instance of $type from the session storage.
     *
     * If the storage does not have an instance of that type, the SessionContainer implementation SHOULD throw a
     * RuntimeException, rather than return null.
     *
     * This method should only be called with parameter $type, if SessionContainer::has($type) returns true
     *
     * @param string $type The class name of the object to be read from the session storage
     *
     * @return mixed
     */
    public function get($type);

    /**
     * @param string $type The class name of the session model to check
     *
     * @return boolean Returns true if a session model of with class name matching $type is stored in session.
     */
    public function has($type);

    /**
     * Remove any instance of the class $type from the session.
     *
     * @param string $type
     *
     * @return void
     */
    public function unset($type);

    /**
     * Clear all objects stored in session on next commit().
     *
     * Any subsequent writes to the session are valid and stored on commit().
     *
     * @return void
     */
    public function clear();

    /**
     * All changes to the session done by calling write(), remove() or clear() are committed to the session storage.
     * @return void
     */
    public function commit();
}
