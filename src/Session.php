<?php
namespace Kodus\Session;

use Kodus\Session\Exceptions\InvalidTypeException;

/**
 * This interface defines the public portion of the SessionData model.
 *
 * Consumers should type-hint against this as a dependency.
 */
interface Session
{
    /**
     * @param string $type fully qualified class name (e.g. MyModel::class)
     *
     * @return SessionModel
     *
     * @throws InvalidTypeException is thrown if the class name given does not exist.
     */
    public function get(string $type): SessionModel;

    /**
     * Updates/saves the SessionModel instance in the session storage.
     *
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
     * @param string|SessionModel $model instance of SessionModel or fully qualified class name (e.g. MyModel::class)
     *
     * @return void
     */
    public function remove($model);

    /**
     * Clears all objects form the session
     *
     * @return void
     */
    public function clear();
}
