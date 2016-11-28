<?php
namespace Kodus\Session;

use Kodus\Session\SessionModel;

interface Session
{
    /**
     * @param string $type fully qualified class name (e.g. MyModel::class)
     *
     * @return SessionModel|null
     */
    public function get(string $type): SessionModel;

    /**
     * @param SessionModel $object
     *
     * @return void
     */
    public function put(SessionModel $object);

    /**
     * @param string $type
     *
     * @return bool returns true if an object of the specified type is stored in the current session
     */
    public function has(string $type): bool;

    /**
     * Removes the object of type $type, if it exists in session.
     *
     * @param string $type fully qualified class name (e.g. MyModel::class)
     *
     * @return void
     */
    public function remove(string $type);

    /**
     * Clears all objects form the session
     *
     * @return void
     */
    public function clear();
}
