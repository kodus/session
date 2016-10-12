<?php
namespace Kodus\Session;

interface SessionContainer
{
    public function __construct(SessionStorage $storage);

    /**
     * Write an instance of SessionModel to the session storage.
     *
     * @param SessionModel $object   The session model to write to storage
     * @param bool         $is_flash If true, the object stored can only be read in the following request to the server.
     *
     * @return void
     */
    public function write(SessionModel $object, $is_flash = false);

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
    public function read($type);

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
    public function remove($type);

    /**
     * Clear all objects stored in session on next commit().
     *
     * Any subsequent writes to the session are valid and stored on commit().
     *
     * @return void
     */
    public function clear();

    /**
     * All changes to the session done by calling write(), remove() or clear() are committed to the session storage.*
     * @return void
     */
    public function commit();
}
