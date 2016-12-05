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
}
